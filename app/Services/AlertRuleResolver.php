<?php

namespace App\Services;

use App\Models\AlertRule;
use App\Models\OperationalAlert;
use App\Models\User;
use Illuminate\Support\Collection;

class AlertRuleResolver
{
    /**
     * @var Collection<int, AlertRule>|null
     */
    private ?Collection $cachedRules = null;

    public const TYPES = [
        'lot_expiration' => [
            'label' => 'Expirarea loturilor',
            'description' => 'Loturi cu stoc pozitiv și termen de expirare înregistrat.',
            'default_threshold_days' => 30,
            'default_roles' => [
                'super-admin', 'admin', 'dispecer', 'manager',
                'gestionar-baza', 'sef-santier', 'contabil',
            ],
        ],
        'reception_pending' => [
            'label' => 'Documente de recepție neprocesate',
            'description' => 'Documente trimise din teren care nu au fost încă transformate într-o recepție sau închise.',
            'default_threshold_days' => 2,
            'default_roles' => [
                'super-admin', 'admin', 'dispecer', 'manager',
                'gestionar-baza', 'sef-santier',
            ],
        ],
    ];

    public const CONFIGURABLE_ROLES = [
        'super-admin',
        'admin',
        'dispecer',
        'manager',
        'gestionar-baza',
        'sef-santier',
        'contabil',
    ];

    private const ROLE_PRIORITY = [
        'super-admin',
        'admin',
        'dispecer',
        'manager',
        'gestionar-baza',
        'sef-santier',
        'contabil',
    ];

    /**
     * @return array{enabled: bool, threshold_days: int, source: string}
     */
    public function resolve(User $user, string $alertType, ?int $locationId): array
    {
        $definition = self::TYPES[$alertType] ?? null;
        abort_unless($definition, 500, 'Tip de alertă necunoscut.');

        $systemRule = $this->allRules()
            ->first(fn (AlertRule $rule) => $rule->alert_type === $alertType
                && $rule->scope_key === 'system');
        $role = $this->primaryConfigurableRole($user);
        $enabledByDefault = $role !== null
            && in_array($role, $definition['default_roles'], true);
        $resolved = [
            'enabled' => (bool) ($systemRule?->enabled ?? true) && $enabledByDefault,
            'threshold_days' => (int) ($systemRule?->threshold_days ?? $definition['default_threshold_days']),
            'source' => 'system',
        ];

        if ($role) {
            $roleRule = $this->allRules()
                ->first(fn (AlertRule $rule) => $rule->alert_type === $alertType
                    && $rule->scope_key === "role:{$role}");
            if ($roleRule) {
                $resolved = [
                    'enabled' => $roleRule->enabled,
                    'threshold_days' => $roleRule->threshold_days,
                    'source' => 'role',
                ];
            }
        }

        if ($locationId) {
            $locationRule = $this->allRules()
                ->first(fn (AlertRule $rule) => $rule->alert_type === $alertType
                    && $rule->scope_key === "location:{$locationId}");
            if ($locationRule) {
                $resolved = [
                    'enabled' => $locationRule->enabled,
                    'threshold_days' => $locationRule->threshold_days,
                    'source' => 'location',
                ];
            }
        }

        $resolved['threshold_days'] = max(0, min(365, (int) $resolved['threshold_days']));

        return $resolved;
    }

    public function shouldReceive(User $user, OperationalAlert $alert): bool
    {
        $rule = $this->resolve($user, $alert->alert_type, $alert->location_id);

        if (! $rule['enabled']) {
            return false;
        }

        return match ($alert->alert_type) {
            'lot_expiration' => $alert->due_at !== null
                && $alert->due_at->startOfDay()->lte(now()->startOfDay()->addDays($rule['threshold_days'])),
            'reception_pending' => $alert->triggered_at
                ->lte(now()->subDays($rule['threshold_days'])),
            default => false,
        };
    }

    public function maximumThreshold(string $alertType): int
    {
        $default = (int) (self::TYPES[$alertType]['default_threshold_days'] ?? 0);
        $configured = $this->allRules()
            ->where('alert_type', $alertType)
            ->where('enabled', true)
            ->max('threshold_days');

        return max($default, min(365, (int) ($configured ?? 0)));
    }

    /**
     * @return Collection<int, AlertRule>
     */
    public function rules(): Collection
    {
        return AlertRule::query()
            ->with(['location', 'changedBy'])
            ->orderByRaw("CASE scope_type WHEN 'system' THEN 0 WHEN 'role' THEN 1 ELSE 2 END")
            ->orderBy('alert_type')
            ->orderBy('role_name')
            ->orderBy('location_id')
            ->get();
    }

    public function primaryConfigurableRole(User $user): ?string
    {
        foreach (self::ROLE_PRIORITY as $role) {
            if ($user->hasRole($role)) {
                return $role;
            }
        }

        return null;
    }

    public function refresh(): void
    {
        $this->cachedRules = AlertRule::query()->get();
    }

    /**
     * @return Collection<int, AlertRule>
     */
    private function allRules(): Collection
    {
        if ($this->cachedRules === null) {
            $this->refresh();
        }

        return $this->cachedRules;
    }
}
