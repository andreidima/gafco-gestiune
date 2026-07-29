<?php

namespace App\Providers;

use App\Http\Controllers\System\DatabaseController;
use App\Http\Middleware\RejectImpersonatedRequest;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class DatabaseToolsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('access-database-tools', static function (User $user): bool {
            return Str::lower((string) $user->email) === 'andrei.dima@usm.ro';
        });

        Route::middleware(['web', 'auth', RejectImpersonatedRequest::class, 'can:access-database-tools'])
            ->group(function (): void {
                Route::get('/system/database', [DatabaseController::class, 'index'])->name('system.database');
                Route::post('/system/database/backup', [DatabaseController::class, 'backup'])->name('system.database.backup');
                Route::get('/system/database/backups/{filename}', [DatabaseController::class, 'downloadBackup'])->name('system.database.backups.download');
                Route::post('/system/database/test-mysqldump', [DatabaseController::class, 'testMysqlDump'])->name('system.database.test-mysqldump');
                Route::post('/system/database/migrate', [DatabaseController::class, 'migrate'])->name('system.database.migrate');
                Route::post('/system/database/composer-download', [DatabaseController::class, 'downloadComposer'])->name('system.database.composer-download');
                Route::post('/system/database/composer-install', [DatabaseController::class, 'composerInstall'])->name('system.database.composer-install');
            });
    }
}
