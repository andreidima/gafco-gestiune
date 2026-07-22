<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\Process;
use Throwable;

class DatabaseController extends Controller
{
    private const BACKUP_RETENTION_DAYS = 90;

    public function index(Request $request)
    {
        $dbMigrations = Schema::hasTable('migrations')
            ? DB::table('migrations')
                ->orderBy('batch')
                ->orderBy('migration')
                ->get(['migration', 'batch'])
                ->map(fn (object $migration): array => [
                    'migration' => $migration->migration,
                    'batch' => $migration->batch,
                ])
            : collect();

        $repoMigrations = $this->repoMigrationFiles();
        $dbMigrationNames = $dbMigrations->pluck('migration');
        $repoMigrationNames = $repoMigrations->pluck('migration');
        $pendingMigrations = $repoMigrations
            ->reject(fn (array $migration): bool => $dbMigrationNames->contains($migration['migration']))
            ->values();
        $destructivePendingMigrations = $this->detectDestructiveMigrations($pendingMigrations);
        $ranMigrations = $repoMigrations
            ->map(function (array $migration) use ($dbMigrations): array {
                $databaseMigration = $dbMigrations->firstWhere('migration', $migration['migration']);

                return [...$migration, 'batch' => $databaseMigration['batch'] ?? null];
            })
            ->filter(fn (array $migration): bool => $migration['batch'] !== null)
            ->values();
        $dbOnlyMigrations = $dbMigrations
            ->reject(fn (array $migration): bool => $repoMigrationNames->contains($migration['migration']))
            ->values();

        $pretendOutput = '';
        $pretendError = null;

        try {
            Artisan::call('migrate', ['--pretend' => true, '--force' => true]);
            $pretendOutput = trim(Artisan::output());
        } catch (Throwable $exception) {
            $pretendError = $exception->getMessage();
        }

        $schemaDumpPath = database_path('schema/mysql-schema.sql');
        $backupPath = $this->backupDirectory();
        $recentBackups = File::isDirectory($backupPath)
            ? collect(File::files($backupPath))
                ->filter(fn ($file): bool => $file->getExtension() === 'sql')
                ->sortByDesc(fn ($file): int => $file->getMTime())
                ->take(10)
                ->map(fn ($file): array => [
                    'filename' => $file->getFilename(),
                    'path' => $file->getRealPath(),
                    'size' => $file->getSize(),
                    'modified_at' => date('Y-m-d H:i:s', $file->getMTime()),
                ])
                ->values()
            : collect();

        return view('system.database', [
            'databaseInfo' => [
                'connection' => config('database.default'),
                'database' => config('database.connections.'.config('database.default').'.database'),
                'host' => config('database.connections.'.config('database.default').'.host'),
                'port' => config('database.connections.'.config('database.default').'.port'),
                'app_env' => config('app.env'),
                'app_url' => config('app.url'),
            ],
            'tables' => $this->getTables(),
            'dbMigrations' => $dbMigrations,
            'repoMigrations' => $repoMigrations,
            'pendingMigrations' => $pendingMigrations,
            'destructivePendingMigrations' => $destructivePendingMigrations,
            'ranMigrations' => $ranMigrations,
            'dbOnlyMigrations' => $dbOnlyMigrations,
            'pretendOutput' => $pretendOutput,
            'pretendError' => $pretendError,
            'schemaDumpExists' => File::exists($schemaDumpPath),
            'schemaDumpPath' => $schemaDumpPath,
            'schemaDumpSize' => File::exists($schemaDumpPath) ? File::size($schemaDumpPath) : null,
            'schemaDumpModifiedAt' => File::exists($schemaDumpPath) ? date('Y-m-d H:i:s', File::lastModified($schemaDumpPath)) : null,
            'backupPath' => $backupPath,
            'recentBackups' => $recentBackups,
            'backupRetentionDays' => self::BACKUP_RETENTION_DAYS,
            'lastMigrationOutput' => $request->session()->get('migration_output'),
            'lastBackupOutput' => $request->session()->get('backup_output'),
            'lastComposerOutput' => $request->session()->get('composer_output'),
            'composerPharExists' => File::exists(base_path('composer.phar')),
            'composerPharPath' => base_path('composer.phar'),
            'composerPharSize' => File::exists(base_path('composer.phar')) ? File::size(base_path('composer.phar')) : null,
        ]);
    }

    public function migrate(Request $request): RedirectResponse
    {
        $lock = $this->migrationLock();

        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            return back()->with('error', 'O alta operatie de migrare este deja in curs.');
        }

        try {
            $destructive = $this->detectDestructiveMigrations($this->pendingMigrationFiles());

            if ($destructive->isNotEmpty() && ! $request->boolean('confirm_destructive_migrations')) {
                return back()->with('error', 'Exista migrari pending cu operatii potential distructive. Confirma explicit inainte de rulare.');
            }

            $this->cleanupOldManagedBackups();
            $backup = $this->createDatabaseBackup('pre-migrate');
            Artisan::call('migrate', ['--force' => true]);
            $migrationOutput = trim(Artisan::output());
            Artisan::call('optimize:clear');

            return back()->with([
                'status' => 'Backup-ul bazei de date a fost creat, apoi migrarile pending au fost rulate.',
                'migration_output' => $migrationOutput,
                'backup_output' => $backup['output'],
                'backup_filename' => $backup['filename'],
            ]);
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function backup(): RedirectResponse
    {
        try {
            $this->cleanupOldManagedBackups();
            $backup = $this->createDatabaseBackup('manual-db');

            return redirect()
                ->route('system.database.backups.download', ['filename' => $backup['filename']])
                ->with('status', 'Backup-ul bazei de date a fost creat pentru download.');
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function downloadBackup(string $filename): BinaryFileResponse
    {
        abort_unless($filename === basename($filename) && str_ends_with($filename, '.sql'), 404);

        $backupPath = $this->backupDirectory().DIRECTORY_SEPARATOR.$filename;
        abort_unless(File::exists($backupPath), 404);

        return response()
            ->download($backupPath, $filename)
            ->deleteFileAfterSend(! str_starts_with($filename, 'pre-migrate-'));
    }

    public function testMysqlDump(): RedirectResponse
    {
        $testDumpPath = null;

        try {
            File::ensureDirectoryExists($this->backupDirectory());
            $connection = config('database.connections.'.config('database.default'));
            $database = $connection['database'] ?? null;
            $username = $connection['username'] ?? null;

            if (! $database || ! $username) {
                throw new RuntimeException('Configuratia bazei de date nu contine database si username.');
            }

            $binary = $this->mysqlDumpBinary();
            $versionOutput = $this->runShellCommand($binary.' --version');
            $testDumpPath = $this->backupDirectory().DIRECTORY_SEPARATOR.'mysqldump-test-'.now()->format('Y-m-d-H-i-s').'.sql';
            $command = implode(' ', [
                $binary,
                '--host='.escapeshellarg((string) ($connection['host'] ?? '127.0.0.1')),
                '--port='.escapeshellarg((string) ($connection['port'] ?? '3306')),
                '--user='.escapeshellarg((string) $username),
                '--no-data',
                '--single-transaction',
                '--skip-lock-tables',
                '--result-file='.escapeshellarg($testDumpPath),
                escapeshellarg((string) $database),
            ]);

            $this->runShellCommand($command, $this->mysqlDumpEnvironment((string) ($connection['password'] ?? '')));

            if (! File::exists($testDumpPath) || File::size($testDumpPath) === 0) {
                throw new RuntimeException('mysqldump a rulat, dar nu a creat un fisier SQL valid.');
            }

            $size = File::size($testDumpPath);
            File::delete($testDumpPath);

            return back()->with([
                'status' => 'mysqldump este disponibil si poate crea un dump schema-only.',
                'mysqldump_output' => trim($versionOutput."\nSchema-only test dump creat si sters: ".number_format($size / 1024, 1).' KB'),
            ]);
        } catch (Throwable $exception) {
            if ($testDumpPath && File::exists($testDumpPath)) {
                File::delete($testDumpPath);
            }

            return back()->with([
                'error' => 'Testul mysqldump a esuat: '.$exception->getMessage(),
                'mysqldump_output' => $exception->getMessage(),
            ]);
        }
    }

    public function composerInstall(): RedirectResponse
    {
        try {
            $this->clearBootstrapCaches();
            $output = $this->runShellCommand(
                $this->composerBinary().' install --no-dev --optimize-autoloader --no-interaction',
                $this->composerEnvironment(),
            );
            Artisan::call('optimize:clear');

            return back()->with([
                'status' => 'Composer install a fost rulat.',
                'composer_output' => trim($output."\n\n".Artisan::output()),
            ]);
        } catch (Throwable $exception) {
            return back()->with(['error' => $exception->getMessage(), 'composer_output' => $exception->getMessage()]);
        }
    }

    public function downloadComposer(): RedirectResponse
    {
        try {
            $composerPath = base_path('composer.phar');
            $contents = @file_get_contents('https://getcomposer.org/download/latest-stable/composer.phar');

            if ($contents === false || file_put_contents($composerPath, $contents) === false) {
                throw new RuntimeException('Nu am putut descarca sau salva composer.phar.');
            }

            return back()->with([
                'status' => 'composer.phar a fost descarcat.',
                'composer_output' => 'Downloaded '.number_format(File::size($composerPath) / 1024 / 1024, 2).' MB.',
            ]);
        } catch (Throwable $exception) {
            return back()->with(['error' => $exception->getMessage(), 'composer_output' => $exception->getMessage()]);
        }
    }

    private function getTables(): Collection
    {
        if (DB::getDriverName() !== 'mysql') {
            return collect(Schema::getTables())
                ->map(fn (array $table): array => [
                    'name' => $table['name'],
                    'rows' => null,
                    'engine' => null,
                    'collation' => null,
                    'columns' => Schema::getColumnListing($table['name']),
                ]);
        }

        return collect(DB::select('SHOW TABLE STATUS'))
            ->map(function (object $row): array {
                $table = (array) $row;
                $name = $table['Name'];

                return [
                    'name' => $name,
                    'rows' => $table['Rows'],
                    'engine' => $table['Engine'],
                    'collation' => $table['Collation'],
                    'columns' => Schema::getColumnListing($name),
                ];
            })
            ->sortBy('name')
            ->values();
    }

    private function createDatabaseBackup(string $prefix): array
    {
        $filename = $prefix.'-'.now()->format('Y-m-d-H-i-s').'.sql';
        $path = $this->backupDirectory().DIRECTORY_SEPARATOR.$filename;
        File::ensureDirectoryExists($this->backupDirectory());

        try {
            return $this->createMysqlDumpBackup($filename, $path);
        } catch (Throwable $exception) {
            return $this->createPhpDatabaseBackup($filename, $path, $exception->getMessage());
        }
    }

    private function createMysqlDumpBackup(string $filename, string $path): array
    {
        $connection = config('database.connections.'.config('database.default'));
        $database = $connection['database'] ?? null;
        $username = $connection['username'] ?? null;

        if (! $database || ! $username) {
            throw new RuntimeException('Configuratia bazei de date nu contine database si username.');
        }

        $command = implode(' ', [
            $this->mysqlDumpBinary(),
            '--host='.escapeshellarg((string) ($connection['host'] ?? '127.0.0.1')),
            '--port='.escapeshellarg((string) ($connection['port'] ?? '3306')),
            '--user='.escapeshellarg((string) $username),
            '--single-transaction',
            '--skip-lock-tables',
            '--routines',
            '--triggers',
            '--events',
            '--result-file='.escapeshellarg($path),
            escapeshellarg((string) $database),
        ]);

        $this->runShellCommand($command, $this->mysqlDumpEnvironment((string) ($connection['password'] ?? '')));

        if (! File::exists($path) || File::size($path) === 0) {
            throw new RuntimeException('mysqldump nu a creat un fisier SQL valid.');
        }

        return [
            'filename' => $filename,
            'path' => $path,
            'driver' => 'mysqldump',
            'output' => 'Backup SQL creat cu mysqldump: '.$filename.' ('.number_format(File::size($path) / 1024, 1).' KB)',
        ];
    }

    private function createPhpDatabaseBackup(string $filename, string $path, string $fallbackReason): array
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Fallback-ul PHP pentru backup este disponibil numai pentru MySQL. '.$fallbackReason);
        }

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Nu am putut crea fisierul temporar de backup.');
        }

        try {
            $this->writeDatabaseSqlDump($handle);
        } finally {
            fclose($handle);
        }

        return [
            'filename' => $filename,
            'path' => $path,
            'driver' => 'php',
            'output' => 'Backup SQL creat cu fallback PHP: '.$filename.' ('.number_format(File::size($path) / 1024, 1)." KB)\nMotiv fallback mysqldump: ".$fallbackReason,
        ];
    }

    private function writeDatabaseSqlDump($handle): void
    {
        $database = config('database.connections.'.config('database.default').'.database');
        $pdo = DB::connection()->getPdo();
        fwrite($handle, "-- GAFCO Gestiune production database backup\n");
        fwrite($handle, "-- Database: {$database}\n");
        fwrite($handle, '-- Created: '.now()->toDateTimeString()."\n\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach ($this->baseTables() as $table) {
            $quotedTable = $this->quoteIdentifier($table);
            $createRow = (array) DB::selectOne('SHOW CREATE TABLE '.$quotedTable);
            $createSql = array_values($createRow)[1] ?? null;

            if (! $createSql) {
                continue;
            }

            fwrite($handle, "-- Table: {$table}\nDROP TABLE IF EXISTS {$quotedTable};\n{$createSql};\n\n");

            foreach (DB::table($table)->cursor() as $row) {
                $values = (array) $row;
                $columnsSql = collect(array_keys($values))->map(fn (string $column): string => $this->quoteIdentifier($column))->implode(', ');
                $valuesSql = collect(array_values($values))->map(fn ($value): string => $this->quoteValue($value, $pdo))->implode(', ');
                fwrite($handle, "INSERT INTO {$quotedTable} ({$columnsSql}) VALUES ({$valuesSql});\n");
            }

            fwrite($handle, "\n");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    }

    private function baseTables(): Collection
    {
        return collect(DB::select('SHOW FULL TABLES'))
            ->map(fn (object $row): array => array_values((array) $row))
            ->filter(fn (array $row): bool => ($row[1] ?? null) === 'BASE TABLE')
            ->map(fn (array $row): string => $row[0])
            ->values();
    }

    private function pendingMigrationFiles(): Collection
    {
        if (! Schema::hasTable('migrations')) {
            return $this->repoMigrationFiles();
        }

        $ran = DB::table('migrations')->pluck('migration');

        return $this->repoMigrationFiles()
            ->reject(fn (array $migration): bool => $ran->contains($migration['migration']))
            ->values();
    }

    private function repoMigrationFiles(): Collection
    {
        if (! File::isDirectory(database_path('migrations'))) {
            return collect();
        }

        return collect(File::files(database_path('migrations')))
            ->map(fn ($file): array => [
                'migration' => pathinfo($file->getFilename(), PATHINFO_FILENAME),
                'filename' => $file->getFilename(),
                'path' => $file->getRealPath(),
                'contents' => File::get($file->getRealPath()),
            ])
            ->sortBy('migration')
            ->values();
    }

    private function detectDestructiveMigrations(Collection $migrations): Collection
    {
        $patterns = [
            'dropTable' => '/Schema::\s*(drop|dropIfExists)\s*\(/i',
            'dropColumn' => '/->\s*dropColumn\s*\(/i',
            'renameTable' => '/Schema::\s*rename\s*\(/i',
            'renameColumn' => '/->\s*renameColumn\s*\(/i',
            'raw DROP/TRUNCATE' => '/DB::\s*(statement|unprepared)\s*\([^;]*(DROP\s+TABLE|DROP\s+COLUMN|TRUNCATE\s+TABLE)/is',
        ];

        return $migrations
            ->map(function (array $migration) use ($patterns): array {
                $upContents = preg_split('/public\s+function\s+down\s*\(/i', $migration['contents'], 2)[0];
                $matches = collect($patterns)
                    ->filter(fn (string $pattern): bool => preg_match($pattern, $upContents) === 1)
                    ->keys()
                    ->values();

                return [...$migration, 'destructive_matches' => $matches];
            })
            ->filter(fn (array $migration): bool => $migration['destructive_matches']->isNotEmpty())
            ->values();
    }

    private function cleanupOldManagedBackups(): void
    {
        if (! File::isDirectory($this->backupDirectory())) {
            return;
        }

        collect(File::files($this->backupDirectory()))
            ->filter(fn ($file): bool => str_starts_with($file->getFilename(), 'pre-migrate-') || str_starts_with($file->getFilename(), 'manual-db-'))
            ->filter(fn ($file): bool => $file->getMTime() < now()->subDays(self::BACKUP_RETENTION_DAYS)->getTimestamp())
            ->each(fn ($file) => File::delete($file->getRealPath()));
    }

    private function backupDirectory(): string
    {
        return storage_path('app/temporary-db-backups');
    }

    private function migrationLock()
    {
        File::ensureDirectoryExists(storage_path('framework'));
        $handle = fopen(storage_path('framework/database-migration.lock'), 'c+');

        if ($handle === false) {
            throw new RuntimeException('Nu am putut crea lock-ul pentru migrari.');
        }

        return $handle;
    }

    private function composerBinary(): string
    {
        return File::exists(base_path('composer.phar'))
            ? $this->phpCliBinary().' '.escapeshellarg(base_path('composer.phar'))
            : 'composer';
    }

    private function phpCliBinary(): string
    {
        $configured = env('COMPOSER_PHP_BINARY');
        if ($configured) {
            return escapeshellcmd($configured);
        }

        foreach (array_unique(array_filter(['php', 'php-cli', '/usr/bin/php', '/usr/local/bin/php', PHP_BINARY])) as $candidate) {
            try {
                if (trim($this->runShellCommand(escapeshellcmd($candidate).' -r "echo PHP_SAPI;"')) === 'cli') {
                    return escapeshellcmd($candidate);
                }
            } catch (Throwable) {
                // Try the next candidate.
            }
        }

        throw new RuntimeException('Nu am gasit un PHP CLI compatibil pentru Composer.');
    }

    private function mysqlDumpBinary(): string
    {
        return escapeshellcmd(env('MYSQLDUMP_BINARY', 'mysqldump'));
    }

    private function runShellCommand(string $command, ?array $environment = null): string
    {
        $process = Process::fromShellCommandline($command, base_path(), $environment, null, 300);
        $process->run();
        $output = trim($process->getOutput()."\n".$process->getErrorOutput());

        if (! $process->isSuccessful()) {
            throw new RuntimeException($output ?: 'Comanda a esuat.');
        }

        return $output;
    }

    private function mysqlDumpEnvironment(string $password): array
    {
        return $password === '' ? $this->baseProcessEnvironment() : [
            ...$this->baseProcessEnvironment(),
            'MYSQL_PWD' => $password,
        ];
    }

    private function composerEnvironment(): array
    {
        $home = storage_path('app/composer-home');
        $cache = storage_path('app/composer-cache');
        File::ensureDirectoryExists($home);
        File::ensureDirectoryExists($cache);

        return [
            ...$this->baseProcessEnvironment(),
            'HOME' => $home,
            'COMPOSER_HOME' => $home,
            'COMPOSER_CACHE_DIR' => $cache,
        ];
    }

    private function baseProcessEnvironment(): array
    {
        $environment = getenv();

        if (! is_array($environment)) {
            return [];
        }

        return array_filter(
            $environment,
            static fn ($value): bool => is_string($value),
        );
    }

    private function clearBootstrapCaches(): void
    {
        collect([
            base_path('bootstrap/cache/packages.php'),
            base_path('bootstrap/cache/services.php'),
            base_path('bootstrap/cache/config.php'),
            base_path('bootstrap/cache/routes-v7.php'),
            base_path('bootstrap/cache/events.php'),
        ])->filter(fn (string $path): bool => File::exists($path))->each(fn (string $path) => File::delete($path));
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private function quoteValue(mixed $value, \PDO $pdo): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return $pdo->quote((string) $value);
    }
}
