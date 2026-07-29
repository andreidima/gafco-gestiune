<?php

namespace App\Http\Middleware;

use App\Services\OperationalAlertSyncService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SyncOperationalAlerts
{
    public function __construct(private readonly OperationalAlertSyncService $alerts) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('testing')) {
            try {
                $this->alerts->sync();
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $next($request);
    }
}
