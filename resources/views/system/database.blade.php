@extends('layouts.system')

@section('title', 'Baza de date si migrari')

@section('content')
<div class="container">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="fa-solid fa-database me-2"></i>Baza de date si migrari</h1>
            <div class="text-muted">Instrumente de productie pentru backup, verificare si migrari Laravel.</div>
        </div>
        <span class="badge text-bg-dark fs-6">{{ $databaseInfo['app_env'] }}</span>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @foreach(['mysqldump_output' => 'mysqldump', 'migration_output' => 'Migration output', 'backup_output' => 'Backup output', 'composer_output' => 'Composer output'] as $sessionKey => $label)
        @if(session($sessionKey))
            <div class="alert alert-secondary">
                <div class="fw-bold mb-2">{{ $label }}</div>
                <pre class="small mb-0" style="white-space: pre-wrap">{{ session($sessionKey) }}</pre>
            </div>
        @endif
    @endforeach

    <div class="card shadow-sm mb-4">
        <div class="card-header text-white culoare2"><i class="fa-solid fa-circle-info me-1"></i>Configuratie productie</div>
        <div class="card-body">
            <div class="row g-3">
                @foreach([
                    'Connection' => $databaseInfo['connection'],
                    'Database' => $databaseInfo['database'],
                    'Host' => $databaseInfo['host'].':'.$databaseInfo['port'],
                    'Tables' => $tables->count(),
                ] as $label => $value)
                    <div class="col-md-6 col-xl-3">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="small text-muted">{{ $label }}</div>
                            <div class="fw-bold text-break">{{ $value }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="alert alert-warning mt-3 mb-0">
                Verifica lista de migrari pending si SQL preview. Rularea migrarilor creeaza obligatoriu un backup complet inainte de <code>migrate --force</code>.
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header text-white culoare2"><i class="fa-solid fa-code-branch me-1"></i>Migration status</div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                @foreach(['Repo migrations' => $repoMigrations->count(), 'Applied' => $ranMigrations->count(), 'Pending' => $pendingMigrations->count()] as $label => $value)
                    <div class="col-md-4"><div class="border rounded-3 p-3"><div class="small text-muted">{{ $label }}</div><div class="fs-4 fw-bold">{{ $value }}</div></div></div>
                @endforeach
            </div>

            @if($dbOnlyMigrations->isNotEmpty())
                <div class="alert alert-danger">
                    <div class="fw-bold">Migration history mismatch</div>
                    @foreach($dbOnlyMigrations as $migration)<div><code>{{ $migration['migration'] }}</code> (batch {{ $migration['batch'] }})</div>@endforeach
                </div>
            @endif

            @if($schemaDumpExists)
                <div class="alert alert-success">Schema baseline: <code>{{ $schemaDumpPath }}</code> ({{ number_format(($schemaDumpSize ?? 0) / 1024, 1) }} KB, {{ $schemaDumpModifiedAt }})</div>
            @else
                <div class="alert alert-warning">Nu exista schema dump in <code>database/schema/mysql-schema.sql</code>.</div>
            @endif

            <div class="table-responsive mb-3">
                <table class="table table-sm table-striped align-middle">
                    <thead><tr><th>Pending migration</th><th>Risk</th></tr></thead>
                    <tbody>
                    @forelse($pendingMigrations as $migration)
                        @php($danger = $destructivePendingMigrations->firstWhere('migration', $migration['migration']))
                        <tr>
                            <td><code>{{ $migration['filename'] }}</code></td>
                            <td>
                                @if($danger)
                                    <span class="badge text-bg-danger">Potential distructiv</span>
                                    <span class="small">{{ $danger['destructive_matches']->implode(', ') }}</span>
                                @else
                                    <span class="badge text-bg-success">Aditiv / normal</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-muted">Nu exista migrari pending.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($pretendError)
                <div class="alert alert-danger"><strong>SQL preview error:</strong> {{ $pretendError }}</div>
            @else
                <details class="border rounded-3 p-3 mb-3">
                    <summary class="fw-bold">SQL preview</summary>
                    <pre class="small mt-3 mb-0" style="white-space: pre-wrap">{{ $pretendOutput ?: 'No SQL statements.' }}</pre>
                </details>
            @endif

            <form method="POST" action="{{ route('system.database.migrate') }}" class="border rounded-3 p-3">
                @csrf
                @if($destructivePendingMigrations->isNotEmpty())
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" name="confirm_destructive_migrations" id="confirm_destructive_migrations">
                        <label class="form-check-label fw-bold text-danger" for="confirm_destructive_migrations">Confirm explicit migrarile potential distructive.</label>
                    </div>
                @endif
                <button class="btn btn-danger" type="submit" @disabled($pendingMigrations->isEmpty())>
                    <i class="fa-solid fa-play me-1"></i>Create backup and run pending migrations
                </button>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header text-white culoare2"><i class="fa-solid fa-box-archive me-1"></i>Database backups</div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <form method="POST" action="{{ route('system.database.backup') }}">@csrf<button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-download me-1"></i>Download database backup</button></form>
                <form method="POST" action="{{ route('system.database.test-mysqldump') }}">@csrf<button class="btn btn-outline-secondary" type="submit">Test mysqldump availability</button></form>
            </div>
            <div class="small text-muted mb-2">Managed backups are retained for {{ $backupRetentionDays }} days. Pre-migration backups remain after download.</div>
            <div class="text-break mb-3"><code>{{ $backupPath }}</code></div>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead><tr><th>Recent backup</th><th>Size</th><th>Modified</th><th></th></tr></thead>
                    <tbody>
                    @forelse($recentBackups as $backup)
                        <tr>
                            <td><code>{{ $backup['filename'] }}</code></td>
                            <td>{{ number_format($backup['size'] / 1024, 1) }} KB</td>
                            <td>{{ $backup['modified_at'] }}</td>
                            <td><a href="{{ route('system.database.backups.download', ['filename' => $backup['filename']]) }}">Download</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">Nu exista backup-uri gestionate.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header text-white culoare2"><i class="fa-brands fa-php me-1"></i>Composer / shared hosting</div>
        <div class="card-body">
            <div class="mb-3">
                <strong>composer.phar:</strong>
                @if($composerPharExists)
                    <span class="badge text-bg-success">available</span> <code>{{ $composerPharPath }}</code> ({{ number_format(($composerPharSize ?? 0) / 1024 / 1024, 2) }} MB)
                @else
                    <span class="badge text-bg-warning">missing</span>
                @endif
            </div>
            <div class="d-flex flex-wrap gap-2">
                <form method="POST" action="{{ route('system.database.composer-download') }}">@csrf<button class="btn btn-outline-secondary" type="submit">Download composer.phar</button></form>
                <form method="POST" action="{{ route('system.database.composer-install') }}">@csrf<button class="btn btn-outline-danger" type="submit">Run production Composer install</button></form>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header text-white culoare2"><i class="fa-solid fa-table-list me-1"></i>Database tables</div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead><tr><th>Table</th><th>Rows</th><th>Engine</th><th>Columns</th></tr></thead>
                <tbody>
                @foreach($tables as $table)
                    <tr><td><code>{{ $table['name'] }}</code></td><td>{{ $table['rows'] ?? 'n/a' }}</td><td>{{ $table['engine'] ?? 'n/a' }}</td><td class="small">{{ implode(', ', $table['columns']) }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
