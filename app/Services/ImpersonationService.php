<?php

namespace App\Services;

use App\Models\User;
use App\Support\ImpersonationContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ImpersonationService
{
    public function __construct(private readonly ImpersonationContext $context) {}

    public function canTake(User $actor, User $target): bool
    {
        return $actor->active
            && $actor->can(ImpersonationContext::PERMISSION)
            && $target->active
            && ! $target->is($actor)
            && ! $target->hasAnyRole(['admin', 'super-admin'])
            && ! $target->can(ImpersonationContext::PERMISSION)
            && Gate::forUser($target)->denies('access-database-tools');
    }

    public function take(Request $request, User $target): void
    {
        $actor = $this->context->actor();
        abort_unless($actor && $this->canTake($actor, $target), 403);
        abort_if($this->context->isActive() && $request->user()?->is($target), 422);

        $previousTarget = $this->context->isActive() ? $request->user() : null;
        $sessionUuid = $this->context->sessionUuid() ?? (string) Str::uuid();

        $this->logLifecycle(
            $previousTarget ? 'Utilizator impersonat schimbat' : 'Impersonare pornită',
            $actor,
            $target,
            $request,
            $sessionUuid,
            $previousTarget ? ['previous_effective_user_id' => $previousTarget->getKey()] : [],
        );

        Auth::login($target);

        if ($previousTarget) {
            $this->context->updateTarget($target);
        } else {
            $this->context->begin($actor, $target, $sessionUuid);
        }

        $request->session()->regenerate();
    }

    public function stop(Request $request, string $reason = 'manual'): ?User
    {
        abort_unless($this->context->isActive(), 403);

        $actor = $this->context->impersonator();
        $target = $request->user();

        if (! $actor || ! $actor->active) {
            $this->recordForcedEnd($request, $reason);
            $this->context->clear();
            Auth::guard()->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return null;
        }

        $this->logLifecycle(
            'Impersonare încheiată',
            $actor,
            $target,
            $request,
            $this->context->sessionUuid(),
            ['reason' => $reason],
        );

        $this->context->clear();
        Auth::login($actor);
        $request->session()->regenerate();

        return $actor;
    }

    public function endBeforeLogout(Request $request): void
    {
        if (! $this->context->isActive()) {
            return;
        }

        $this->stop($request, 'logout');
    }

    public function restoreAfterInvalidState(Request $request, string $reason): bool
    {
        if (! $this->context->isActive()) {
            return false;
        }

        $actor = $this->context->impersonator();
        if (! $actor || ! $actor->active) {
            $this->recordForcedEnd($request, $reason);
            $this->context->clear();
            Auth::guard()->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return false;
        }

        $this->logLifecycle(
            'Impersonare încheiată automat',
            $actor,
            $request->user(),
            $request,
            $this->context->sessionUuid(),
            ['reason' => $reason],
        );

        $this->context->clear();
        Auth::login($actor);
        $request->session()->regenerate();

        return true;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function logLifecycle(
        string $description,
        User $actor,
        ?User $target,
        Request $request,
        ?string $sessionUuid,
        array $properties = [],
    ): void {
        $logger = activity('impersonation')
            ->causedBy($actor)
            ->withProperties(array_merge([
                'impersonation_session_uuid' => $sessionUuid,
                'impersonator_user_id' => $actor->getKey(),
                'effective_user_id' => $target?->getKey(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            ], $properties));

        if ($target) {
            $logger->performedOn($target);
        }

        $logger->log($description);
    }

    private function recordForcedEnd(Request $request, string $reason): void
    {
        $target = $request->user();
        $actorId = $this->context->impersonatorId();

        activity('impersonation')
            ->withProperties([
                'impersonation_session_uuid' => $this->context->sessionUuid(),
                'impersonator_user_id' => $actorId,
                'effective_user_id' => $target?->getKey(),
                'reason' => $reason,
                'ip_address' => $request->ip(),
            ])
            ->log('Impersonare invalidată');
    }
}
