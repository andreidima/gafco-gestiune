<?php

namespace App\Http\Middleware;

use App\Support\ImpersonationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectImpersonatedRequest
{
    public function __construct(private readonly ImpersonationContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->context->isActive(), 403);

        return $next($request);
    }
}
