<?php

namespace App\Services;

use App\Models\InventoryLotBalance;
use App\Models\OperationalAlert;
use App\Models\Project;
use App\Models\ReceptionIntake;
use App\Models\User;
use App\Notifications\OperationalAlertNotification;
use App\Support\LocalizedNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OperationalAlertSyncService
{
    private const CACHE_KEY = 'operational-alerts:last-sync';

    /**
     * @var array<int, Collection<int, User>>
     */
    private array $eligibleUsersByLocation = [];

    public function __construct(
        private readonly AlertRuleResolver $rules,
        private readonly OperationalAlertAccessService $access,
        private readonly ProjectMaterialPlanService $projectPlans,
    ) {}

    /**
     * @return array{detected: int, resolved: int, notifications: int}
     */
    public function sync(bool $force = false): array
    {
        if (! $this->tablesAreAvailable()) {
            return ['detected' => 0, 'resolved' => 0, 'notifications' => 0];
        }

        if (! $force && ! app()->environment('testing')) {
            $lastSync = (int) Cache::get(self::CACHE_KEY, 0);
            if ($lastSync > now()->subMinutes(5)->timestamp) {
                return ['detected' => 0, 'resolved' => 0, 'notifications' => 0];
            }
        }

        $this->rules->refresh();
        $this->eligibleUsersByLocation = [];

        $result = DB::transaction(function (): array {
            $detectedAt = now();
            $seen = [
                'lot_expiration' => [],
                'reception_pending' => [],
                'project_plan_overrun' => [],
            ];
            $notifications = 0;

            $expirationLimit = now()->startOfDay()->addDays(
                $this->rules->maximumThreshold('lot_expiration'),
            );
            InventoryLotBalance::query()
                ->where('quantity', '>', 0)
                ->whereHas('lot', fn ($lots) => $lots
                    ->whereNotNull('expires_at')
                    ->whereDate('expires_at', '<=', $expirationLimit))
                ->with(['location', 'lot.catalogItem'])
                ->orderBy('id')
                ->each(function (InventoryLotBalance $balance) use (
                    &$seen,
                    &$notifications,
                    $detectedAt,
                ): void {
                    if (! $balance->location || ! $balance->lot?->catalogItem || ! $balance->lot->expires_at) {
                        return;
                    }

                    $lot = $balance->lot;
                    $expired = $lot->expires_at->startOfDay()->lt(now()->startOfDay());
                    $fingerprint = "lot_expiration:{$lot->id}:{$balance->location_id}";
                    $seen['lot_expiration'][] = $fingerprint;
                    $lotLabel = $lot->lot_code ?: 'fără cod';
                    $title = $expired ? 'Lot expirat' : 'Lot aproape de expirare';
                    $message = sprintf(
                        '%s, lot %s, are %s %s la %s și termenul %s.',
                        $lot->catalogItem->name,
                        $lotLabel,
                        $this->quantity($balance->quantity),
                        $lot->catalogItem->unit,
                        $balance->location->code,
                        $expired ? 'a expirat la '.$lot->expires_at->format('d.m.Y') : 'expiră la '.$lot->expires_at->format('d.m.Y'),
                    );
                    $alert = $this->persistAlert([
                        'alert_type' => 'lot_expiration',
                        'fingerprint' => $fingerprint,
                        'severity' => $expired ? 'danger' : 'warning',
                        'location_id' => $balance->location_id,
                        'alertable_type' => $lot::class,
                        'alertable_id' => $lot->id,
                        'title' => $title,
                        'message' => $message,
                        'url' => route('inventory.show', [
                            'catalogItem' => $lot->catalog_item_id,
                            'location_id' => $balance->location_id,
                        ], false)."#lot-{$lot->id}-{$balance->location_id}",
                        'metadata' => [
                            'catalog_item_id' => $lot->catalog_item_id,
                            'inventory_lot_id' => $lot->id,
                            'quantity' => (string) $balance->quantity,
                            'unit' => $lot->catalogItem->unit,
                            'expires_at' => $lot->expires_at->format('Y-m-d'),
                        ],
                        'triggered_at' => $detectedAt,
                        'due_at' => $lot->expires_at->endOfDay(),
                        'last_detected_at' => $detectedAt,
                    ]);
                    $notifications += $this->synchronizeRecipients($alert);
                });

            ReceptionIntake::query()
                ->where('status', 'created')
                ->with('location')
                ->orderBy('id')
                ->each(function (ReceptionIntake $intake) use (
                    &$seen,
                    &$notifications,
                    $detectedAt,
                ): void {
                    if (! $intake->location) {
                        return;
                    }

                    $fingerprint = "reception_pending:{$intake->id}";
                    $seen['reception_pending'][] = $fingerprint;
                    $alert = $this->persistAlert([
                        'alert_type' => 'reception_pending',
                        'fingerprint' => $fingerprint,
                        'severity' => 'warning',
                        'location_id' => $intake->location_id,
                        'alertable_type' => $intake::class,
                        'alertable_id' => $intake->id,
                        'title' => 'Documente de recepție neprocesate',
                        'message' => sprintf(
                            '%s de la %s așteaptă verificarea pentru locația %s.',
                            $intake->number,
                            $intake->created_at->format('d.m.Y H:i'),
                            $intake->location->code,
                        ),
                        'url' => route('reception-intakes.show', $intake, false),
                        'metadata' => [
                            'reception_intake_id' => $intake->id,
                            'submitted_at' => $intake->created_at->toIso8601String(),
                        ],
                        'triggered_at' => $intake->created_at,
                        'due_at' => null,
                        'last_detected_at' => $detectedAt,
                    ]);
                    $notifications += $this->synchronizeRecipients($alert);
                });

            if (Schema::hasTable('projects')
                && Schema::hasTable('project_material_plans')
                && Schema::hasColumn('transfers', 'project_id')) {
                $activeProjects = Project::query()
                    ->where('status', 'active')
                    ->with(['location', 'creator', 'materialPlans.catalogItem'])
                    ->orderBy('id')
                    ->get();
                $projectProgress = $this->projectPlans->progressForProjects($activeProjects);
                foreach ($activeProjects as $project) {
                    foreach ($projectProgress->get($project->id, collect())->where('has_overrun', true) as $line) {
                        $catalogItem = $line['catalog_item'];
                        $fingerprint = "project_plan_overrun:{$project->id}:{$catalogItem->id}";
                        $seen['project_plan_overrun'][] = $fingerprint;
                        $alert = $this->persistAlert([
                            'alert_type' => 'project_plan_overrun',
                            'fingerprint' => $fingerprint,
                            'severity' => 'danger',
                            'location_id' => $project->location_id,
                            'alertable_type' => $project::class,
                            'alertable_id' => $project->id,
                            'title' => 'Plan de materiale depășit',
                            'message' => sprintf(
                                '%s are %s %s solicitați pentru proiectul %s, față de %s %s planificați (+%s %s).',
                                $catalogItem->name,
                                $this->quantity($line['committed_quantity']),
                                $line['unit'],
                                $project->code,
                                $this->quantity($line['planned_quantity']),
                                $line['unit'],
                                $this->quantity($line['overrun_quantity']),
                                $line['unit'],
                            ),
                            'url' => route('projects.show', $project, false)."#material-plan-{$catalogItem->id}",
                            'metadata' => [
                                'project_id' => $project->id,
                                'catalog_item_id' => $catalogItem->id,
                                'planned_quantity' => (string) $line['planned_quantity'],
                                'committed_quantity' => (string) $line['committed_quantity'],
                                'overrun_quantity' => (string) $line['overrun_quantity'],
                                'unit' => $line['unit'],
                            ],
                            'triggered_at' => $detectedAt,
                            'due_at' => null,
                            'last_detected_at' => $detectedAt,
                        ]);
                        $notifications += $this->synchronizeRecipients($alert);
                    }
                }
            }

            $resolved = 0;
            foreach ($seen as $alertType => $fingerprints) {
                $query = OperationalAlert::query()
                    ->where('alert_type', $alertType)
                    ->whereNull('resolved_at');
                if ($fingerprints !== []) {
                    $query->whereNotIn('fingerprint', $fingerprints);
                }
                $resolved += $query->update([
                    'resolved_at' => $detectedAt,
                    'updated_at' => $detectedAt,
                ]);
            }

            return [
                'detected' => collect($seen)->sum(fn (array $fingerprints) => count($fingerprints)),
                'resolved' => $resolved,
                'notifications' => $notifications,
            ];
        }, 3);

        Cache::put(self::CACHE_KEY, now()->timestamp, now()->addMinutes(5));

        return $result;
    }

    private function persistAlert(array $attributes): OperationalAlert
    {
        $alert = OperationalAlert::query()
            ->lockForUpdate()
            ->firstOrNew(['fingerprint' => $attributes['fingerprint']]);
        $wasResolved = $alert->exists && $alert->resolved_at !== null;
        $alert->fill($attributes);
        $alert->resolved_at = null;
        $alert->save();

        if ($wasResolved) {
            $alert->recipients()->detach();
        }

        return $alert->fresh(['location', 'alertable']);
    }

    private function synchronizeRecipients(OperationalAlert $alert): int
    {
        if (! $alert->location) {
            return 0;
        }

        $recipients = $alert->alert_type === 'project_plan_overrun'
            ? $this->projectOverrunRecipients($alert)
            : ($this->eligibleUsersByLocation[$alert->location_id]
                    ??= $this->access->eligibleUsers($alert->location))
                ->filter(fn ($user) => $this->rules->shouldReceive($user, $alert))
                ->values();
        $recipientIds = $recipients->pluck('id')->all();

        if ($recipientIds === []) {
            $alert->recipients()->detach();

            return 0;
        }

        $existingRecipientIds = DB::table('operational_alert_user')
            ->where('operational_alert_id', $alert->id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $recipientIdsToRemove = array_values(array_diff($existingRecipientIds, $recipientIds));
        if ($recipientIdsToRemove !== []) {
            $alert->recipients()->detach($recipientIdsToRemove);
        }
        $notifications = 0;

        foreach ($recipients as $user) {
            $pivot = DB::table('operational_alert_user')
                ->where('operational_alert_id', $alert->id)
                ->where('user_id', $user->id)
                ->first();
            $isEscalation = $alert->severity === 'danger'
                && $pivot?->last_notified_severity !== 'danger';

            if (! $pivot || $isEscalation) {
                $timestamp = now();
                DB::table('operational_alert_user')->updateOrInsert(
                    [
                        'operational_alert_id' => $alert->id,
                        'user_id' => $user->id,
                    ],
                    [
                        'last_notified_severity' => $alert->severity,
                        'notified_at' => $timestamp,
                        'created_at' => $pivot?->created_at ?? $timestamp,
                        'updated_at' => $timestamp,
                    ],
                );
                $user->notify(new OperationalAlertNotification($alert));
                $notifications++;
            }
        }

        return $notifications;
    }

    /**
     * @return Collection<int, User>
     */
    private function projectOverrunRecipients(OperationalAlert $alert): Collection
    {
        $project = $alert->alertable instanceof Project
            ? $alert->alertable
            : Project::find($alert->alertable_id);
        if (! $project) {
            return collect();
        }

        return User::permission('alerts.view')
            ->where('active', true)
            ->with(['roles.permissions', 'permissions'])
            ->get()
            ->filter(fn (User $user): bool => (int) $user->id === (int) $project->created_by
                || $user->hasLocationAbility('alerts.view', (int) $project->location_id))
            ->values();
    }

    private function tablesAreAvailable(): bool
    {
        return Schema::hasTable('alert_rules')
            && Schema::hasTable('operational_alerts')
            && Schema::hasTable('operational_alert_user');
    }

    private function quantity(mixed $quantity): string
    {
        return LocalizedNumber::quantity($quantity);
    }
}
