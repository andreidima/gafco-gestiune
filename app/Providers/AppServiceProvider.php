<?php

namespace App\Providers;

use App\Models\Task;
use App\Models\Transfer;
use App\Policies\TaskPolicy;
use App\Policies\TransferPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Transfer::class, TransferPolicy::class);
    }
}
