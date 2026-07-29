<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\Task;
use App\Models\Transfer;
use App\Policies\ProjectPolicy;
use App\Policies\TaskPolicy;
use App\Policies\TransferPolicy;
use App\Support\ImpersonationContext;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

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
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Transfer::class, TransferPolicy::class);

        Activity::creating(function (Activity $activity): void {
            $context = app(ImpersonationContext::class);
            if (! $context->isActive() || $activity->log_name === 'impersonation') {
                return;
            }

            $impersonator = $context->impersonator();
            $effectiveUserId = auth()->id();
            if (! $impersonator || ! $effectiveUserId) {
                return;
            }

            $activity->causer_type = $impersonator->getMorphClass();
            $activity->causer_id = $impersonator->getKey();
            $activity->batch_uuid = $context->sessionUuid();
            $activity->properties = collect($activity->properties ?? [])->merge([
                'impersonation_session_uuid' => $context->sessionUuid(),
                'impersonator_user_id' => $impersonator->getKey(),
                'effective_user_id' => $effectiveUserId,
            ]);
        });
    }
}
