<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ImpersonationContext
{
    public const PERMISSION = 'users.impersonate';

    private const IMPERSONATOR_ID = 'impersonation.impersonator_id';

    private const TARGET_ID = 'impersonation.target_id';

    private const SESSION_UUID = 'impersonation.session_uuid';

    private const STARTED_AT = 'impersonation.started_at';

    private const RECENT_TARGETS = 'impersonation.recent_targets';

    public function isActive(): bool
    {
        return session()->has(self::IMPERSONATOR_ID)
            && session()->has(self::TARGET_ID)
            && session()->has(self::SESSION_UUID);
    }

    public function impersonatorId(): ?int
    {
        $id = session(self::IMPERSONATOR_ID);

        return $id === null ? null : (int) $id;
    }

    public function targetId(): ?int
    {
        $id = session(self::TARGET_ID);

        return $id === null ? null : (int) $id;
    }

    public function sessionUuid(): ?string
    {
        $uuid = session(self::SESSION_UUID);

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
    }

    public function startedAt(): ?string
    {
        $startedAt = session(self::STARTED_AT);

        return is_string($startedAt) && $startedAt !== '' ? $startedAt : null;
    }

    public function impersonator(): ?User
    {
        if (! $this->isActive()) {
            return null;
        }

        return User::find($this->impersonatorId());
    }

    public function actor(): ?User
    {
        return $this->impersonator() ?? Auth::user();
    }

    /**
     * @return array<int>
     */
    public function recentTargetIds(): array
    {
        return collect(session(self::RECENT_TARGETS, []))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    public function begin(User $impersonator, User $target, string $sessionUuid): void
    {
        session()->put([
            self::IMPERSONATOR_ID => $impersonator->getKey(),
            self::TARGET_ID => $target->getKey(),
            self::SESSION_UUID => $sessionUuid,
            self::STARTED_AT => session(self::STARTED_AT, now()->toIso8601String()),
            self::RECENT_TARGETS => collect([$target->getKey()])
                ->merge($this->recentTargetIds())
                ->unique()
                ->take(8)
                ->values()
                ->all(),
        ]);

    }

    public function updateTarget(User $target): void
    {
        session()->put([
            self::TARGET_ID => $target->getKey(),
            self::RECENT_TARGETS => collect([$target->getKey()])
                ->merge($this->recentTargetIds())
                ->unique()
                ->take(8)
                ->values()
                ->all(),
        ]);
    }

    public function clear(): void
    {
        session()->forget([
            self::IMPERSONATOR_ID,
            self::TARGET_ID,
            self::SESSION_UUID,
            self::STARTED_AT,
        ]);
    }
}
