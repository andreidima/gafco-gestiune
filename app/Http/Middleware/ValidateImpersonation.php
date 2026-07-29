<?php

namespace App\Http\Middleware;

use App\Services\ImpersonationService;
use App\Support\ImpersonationContext;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateImpersonation
{
    public function __construct(
        private readonly ImpersonationContext $context,
        private readonly ImpersonationService $impersonation,
    ) {}

    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if (! $this->context->isActive()) {
            return $next($request);
        }

        $actor = $this->context->impersonator();
        $target = $request->user();
        $currentTarget = $target?->fresh();
        $reason = match (true) {
            ! $actor || ! $actor->active => 'impersonator_unavailable',
            ! $actor->can(ImpersonationContext::PERMISSION) => 'permission_revoked',
            ! $currentTarget || ! $currentTarget->active => 'target_unavailable',
            $target->getKey() !== $this->context->targetId() => 'target_mismatch',
            default => null,
        };

        if ($reason === null) {
            return $next($request);
        }

        $restored = $this->impersonation->restoreAfterInvalidState($request, $reason);

        return $restored
            ? redirect()->route('dashboard')->with(
                'status',
                'Impersonarea a fost încheiată deoarece accesul sau contul s-a modificat.',
            )
            : redirect()->route('login');
    }
}
