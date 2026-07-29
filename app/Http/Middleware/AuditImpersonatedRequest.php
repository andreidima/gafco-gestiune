<?php

namespace App\Http\Middleware;

use App\Support\ImpersonationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditImpersonatedRequest
{
    public function __construct(private readonly ImpersonationContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $shouldAudit = $this->context->isActive()
            && ! $request->isMethodSafe()
            && ! $request->routeIs('impersonation.*');
        $actor = $shouldAudit ? $this->context->impersonator() : null;
        $effectiveUser = $shouldAudit ? $request->user() : null;
        $sessionUuid = $shouldAudit ? $this->context->sessionUuid() : null;

        $response = $next($request);

        if ($shouldAudit && $actor && $effectiveUser && $response->getStatusCode() < 400) {
            activity('impersonation')
                ->causedBy($actor)
                ->withProperties([
                    'impersonation_session_uuid' => $sessionUuid,
                    'impersonator_user_id' => $actor->getKey(),
                    'effective_user_id' => $effectiveUser->getKey(),
                    'route' => $request->route()?->getName(),
                    'method' => $request->method(),
                    'response_status' => $response->getStatusCode(),
                ])
                ->log('Acțiune efectuată prin impersonare');
        }

        return $response;
    }
}
