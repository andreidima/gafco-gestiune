<?php

namespace App\Services;

use App\Models\CatalogItem;
use App\Models\CustodyTransfer;
use App\Models\Location;
use App\Models\MaterialCustody;
use App\Models\StockLevel;
use App\Models\TrackedAsset;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustodyWorkflowService
{
    public function __construct(private readonly LocationAccessService $locationAccess) {}

    public function canDecide(CustodyTransfer $transfer, User $actor): bool
    {
        return $transfer->status === 'pending'
            && ! $transfer->expires_at?->isPast()
            && $this->approvalFieldsFor($transfer, $actor) !== [];
    }

    /** @param array<string, mixed> $data */
    public function initiate(User $actor, array $data): CustodyTransfer
    {
        return DB::transaction(function () use ($actor, $data): CustodyTransfer {
            $this->expirePendingOperations();

            $operation = $data['operation_type'];
            $itemType = $data['item_type'];

            $attributes = $itemType === 'equipment'
                ? $this->equipmentAttributes($actor, $operation, $data)
                : $this->materialAttributes($actor, $operation, $data);

            $transfer = CustodyTransfer::create($attributes + [
                'operation_type' => $operation,
                'initiated_by' => $actor->id,
                'status' => 'pending',
                'qr_token' => 'CUST-'.Str::upper(Str::random(10)),
                'expires_at' => now()->addDay(),
                'notes' => $data['notes'] ?? null,
            ]);

            $outcome = $this->finalizeIfReady($transfer);
            if ($outcome !== null) {
                throw ValidationException::withMessages(['custody' => $this->outcomeMessage($outcome)]);
            }

            if ($transfer->status === 'pending') {
                $this->notifyPendingApprovers($transfer, $actor);
            }

            return $transfer->fresh([
                'trackedAsset.catalogItem', 'catalogItem', 'fromUser', 'toUser', 'location',
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function decide(CustodyTransfer $transfer, User $actor, array $data): ?string
    {
        return DB::transaction(function () use ($transfer, $actor, $data): ?string {
            $transfer = CustodyTransfer::lockForUpdate()->findOrFail($transfer->id);
            abort_unless($transfer->status === 'pending', 422);

            if ($transfer->expires_at?->isPast()) {
                $transfer->update(['status' => 'expired']);

                return 'expired';
            }

            $approvalFields = $this->approvalFieldsFor($transfer, $actor);
            abort_unless($approvalFields !== [], 403);

            if ($data['decision'] === 'rejected') {
                $transfer->update([
                    'status' => 'rejected',
                    'rejected_at' => now(),
                    'rejected_by' => $actor->id,
                    'response_notes' => $data['response_notes'],
                ]);
                $this->notifyOutcome($transfer, $actor, 'Operațiune de custodie refuzată');

                return null;
            }

            $updates = array_fill_keys($approvalFields, now());
            if (in_array('to_approved_at', $approvalFields, true) && $transfer->operation_type === 'return') {
                $updates['manager_approved_by'] = $actor->id;
            }
            if ($transfer->operation_type === 'return' && ! $transfer->isMaterial() && isset($data['return_condition'])) {
                $updates['return_condition'] = $data['return_condition'];
            }
            if (! empty($data['response_notes'])) {
                $updates['response_notes'] = $data['response_notes'];
            }

            $transfer->update($updates);
            $transfer->refresh();
            $outcome = $this->finalizeIfReady($transfer);

            if ($transfer->status === 'accepted') {
                $this->notifyOutcome($transfer, $actor, 'Custodie actualizată');
            }

            return $outcome;
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function equipmentAttributes(User $actor, string $operation, array $data): array
    {
        $asset = TrackedAsset::with('catalogItem')->lockForUpdate()->findOrFail($data['tracked_asset_id']);
        $this->ensureOperationalAsset($asset);

        if (CustodyTransfer::where('tracked_asset_id', $asset->id)
            ->where('status', 'pending')
            ->when(isset($data['current_transfer_id']), fn ($query) => $query->whereKeyNot($data['current_transfer_id']))
            ->exists()) {
            throw ValidationException::withMessages([
                'tracked_asset_id' => 'Există deja o operațiune în așteptare pentru acest echipament.',
            ]);
        }

        if ($operation === 'issue') {
            if ($asset->current_custodian_id !== null || ! $asset->current_location_id) {
                throw ValidationException::withMessages([
                    'tracked_asset_id' => 'Poate fi dat în custodie numai un echipament disponibil într-o locație și fără responsabil.',
                ]);
            }
            abort_unless($this->locationAccess->canWrite($actor, (int) $asset->current_location_id), 403);
            $toUser = $this->activeRecipient($data['to_user_id'] ?? null);

            return [
                'tracked_asset_id' => $asset->id,
                'from_user_id' => null,
                'to_user_id' => $toUser->id,
                'location_id' => $asset->current_location_id,
                'quantity' => 1,
                'unit' => $asset->catalogItem?->unit ?? 'buc',
                'from_approved_at' => now(),
                'to_approved_at' => (int) $toUser->id === (int) $actor->id ? now() : null,
            ];
        }

        if (! $asset->current_custodian_id) {
            throw ValidationException::withMessages([
                'tracked_asset_id' => 'Echipamentul nu este în custodia unei persoane.',
            ]);
        }

        $holderId = (int) $asset->current_custodian_id;
        $canActForHolder = $holderId === (int) $actor->id
            || $actor->hasGlobalAbility('custody.manage')
            || ($asset->current_location_id && $this->locationAccess->canWrite($actor, (int) $asset->current_location_id));
        abort_unless($canActForHolder, 403);

        if ($operation === 'handoff') {
            $toUser = $this->activeRecipient($data['to_user_id'] ?? null);
            if ((int) $toUser->id === $holderId) {
                throw ValidationException::withMessages(['to_user_id' => 'Destinatarul trebuie să fie diferit de persoana care predă.']);
            }

            return [
                'tracked_asset_id' => $asset->id,
                'from_user_id' => $holderId,
                'to_user_id' => $toUser->id,
                'location_id' => $asset->current_location_id,
                'quantity' => 1,
                'unit' => $asset->catalogItem?->unit ?? 'buc',
                'from_approved_at' => $holderId === (int) $actor->id ? now() : null,
                'to_approved_at' => (int) $toUser->id === (int) $actor->id ? now() : null,
            ];
        }

        $location = Location::where('active', true)->findOrFail($data['location_id']);
        $managerApproved = $this->locationAccess->canWrite($actor, (int) $location->id);

        return [
            'tracked_asset_id' => $asset->id,
            'from_user_id' => $holderId,
            'to_user_id' => null,
            'location_id' => $location->id,
            'quantity' => 1,
            'unit' => $asset->catalogItem?->unit ?? 'buc',
            'return_condition' => $data['return_condition'] ?? $asset->condition,
            'from_approved_at' => $holderId === (int) $actor->id ? now() : null,
            'to_approved_at' => $managerApproved ? now() : null,
            'manager_approved_by' => $managerApproved ? $actor->id : null,
        ];
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function materialAttributes(User $actor, string $operation, array $data): array
    {
        $quantity = round((float) ($data['quantity'] ?? 0), 3);
        if ($quantity <= 0.0005) {
            throw ValidationException::withMessages(['quantity' => 'Introdu o cantitate mai mare decât zero.']);
        }

        if ($operation === 'issue') {
            $location = Location::where('active', true)->findOrFail($data['location_id']);
            abort_unless($this->locationAccess->canWrite($actor, (int) $location->id), 403);
            $item = CatalogItem::where('active', true)->findOrFail($data['catalog_item_id']);
            if ($item->tracking_type === 'serialized') {
                throw ValidationException::withMessages(['catalog_item_id' => 'Echipamentele individuale se selectează după codul lor unic.']);
            }
            $toUser = $this->activeRecipient($data['to_user_id'] ?? null);
            $this->ensureMaterialAvailableForIssue($item, $location, $quantity);

            return [
                'catalog_item_id' => $item->id,
                'from_user_id' => null,
                'to_user_id' => $toUser->id,
                'location_id' => $location->id,
                'quantity' => $quantity,
                'unit' => $item->unit,
                'from_approved_at' => now(),
                'to_approved_at' => (int) $toUser->id === (int) $actor->id ? now() : null,
            ];
        }

        $holding = MaterialCustody::with('catalogItem')->lockForUpdate()->findOrFail($data['material_custody_id']);
        $canActForHolder = (int) $holding->user_id === (int) $actor->id
            || $actor->hasGlobalAbility('custody.manage')
            || $this->locationAccess->canWrite($actor, (int) $holding->location_id);
        abort_unless($canActForHolder, 403);

        $reserved = (float) CustodyTransfer::where('status', 'pending')
            ->whereIn('operation_type', ['handoff', 'return'])
            ->where('catalog_item_id', $holding->catalog_item_id)
            ->where('location_id', $holding->location_id)
            ->where('from_user_id', $holding->user_id)
            ->sum('quantity');
        if ($quantity - max(0, (float) $holding->quantity - $reserved) > 0.0005) {
            throw ValidationException::withMessages([
                'quantity' => 'Cantitatea depășește soldul liber din custodie. Verifică operațiunile deja în așteptare.',
            ]);
        }

        if ($operation === 'handoff') {
            $toUser = $this->activeRecipient($data['to_user_id'] ?? null);
            if ((int) $toUser->id === (int) $holding->user_id) {
                throw ValidationException::withMessages(['to_user_id' => 'Destinatarul trebuie să fie diferit de persoana care predă.']);
            }

            return [
                'catalog_item_id' => $holding->catalog_item_id,
                'from_user_id' => $holding->user_id,
                'to_user_id' => $toUser->id,
                'location_id' => $holding->location_id,
                'quantity' => $quantity,
                'unit' => $holding->unit,
                'from_approved_at' => (int) $holding->user_id === (int) $actor->id ? now() : null,
                'to_approved_at' => (int) $toUser->id === (int) $actor->id ? now() : null,
            ];
        }

        $managerApproved = $this->locationAccess->canWrite($actor, (int) $holding->location_id);

        return [
            'catalog_item_id' => $holding->catalog_item_id,
            'from_user_id' => $holding->user_id,
            'to_user_id' => null,
            'location_id' => $holding->location_id,
            'quantity' => $quantity,
            'unit' => $holding->unit,
            'from_approved_at' => (int) $holding->user_id === (int) $actor->id ? now() : null,
            'to_approved_at' => $managerApproved ? now() : null,
            'manager_approved_by' => $managerApproved ? $actor->id : null,
        ];
    }

    private function finalizeIfReady(CustodyTransfer $transfer): ?string
    {
        if (! $transfer->from_approved_at || ! $transfer->to_approved_at) {
            return null;
        }

        $outcome = $transfer->isMaterial()
            ? $this->finalizeMaterial($transfer)
            : $this->finalizeEquipment($transfer);

        if ($outcome !== null) {
            $transfer->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'response_notes' => $this->outcomeMessage($outcome),
            ]);

            return $outcome;
        }

        $transfer->update(['status' => 'accepted', 'accepted_at' => now()]);

        return null;
    }

    private function finalizeEquipment(CustodyTransfer $transfer): ?string
    {
        $asset = TrackedAsset::lockForUpdate()->findOrFail($transfer->tracked_asset_id);
        if (! in_array($asset->status, ['available', 'in_use'], true)) {
            return 'asset_unavailable';
        }

        if ($transfer->operation_type === 'issue') {
            if ($asset->current_custodian_id !== null || (int) $asset->current_location_id !== (int) $transfer->location_id) {
                return 'custody_changed';
            }
            $asset->update([
                'current_custodian_id' => $transfer->to_user_id,
                'status' => 'in_use',
                'last_verified_at' => now(),
            ]);

            return null;
        }

        if ((int) $asset->current_custodian_id !== (int) $transfer->from_user_id) {
            return 'custody_changed';
        }

        if ($transfer->operation_type === 'handoff') {
            $asset->update([
                'current_custodian_id' => $transfer->to_user_id,
                'status' => 'in_use',
                'last_verified_at' => now(),
            ]);

            return null;
        }

        $condition = $transfer->return_condition ?: $asset->condition ?: 'good';
        $asset->update([
            'current_custodian_id' => null,
            'current_location_id' => $transfer->location_id,
            'condition' => $condition,
            'status' => in_array($condition, ['damaged', 'needs_service'], true) ? 'maintenance' : 'available',
            'last_verified_at' => now(),
        ]);

        return null;
    }

    private function finalizeMaterial(CustodyTransfer $transfer): ?string
    {
        if ($transfer->operation_type === 'issue') {
            $item = CatalogItem::findOrFail($transfer->catalog_item_id);
            $location = Location::findOrFail($transfer->location_id);
            if (! $this->materialIssueStillAvailable($transfer, $item, $location)) {
                return 'material_unavailable';
            }
            $this->changeMaterialCustody(
                (int) $transfer->to_user_id,
                (int) $item->id,
                (int) $location->id,
                (float) $transfer->quantity,
                (string) $transfer->unit,
            );

            return null;
        }

        $holding = MaterialCustody::where([
            'user_id' => $transfer->from_user_id,
            'catalog_item_id' => $transfer->catalog_item_id,
            'location_id' => $transfer->location_id,
        ])->lockForUpdate()->first();
        if (! $holding || (float) $transfer->quantity - (float) $holding->quantity > 0.0005) {
            return 'custody_changed';
        }

        $this->changeMaterialCustody(
            (int) $transfer->from_user_id,
            (int) $transfer->catalog_item_id,
            (int) $transfer->location_id,
            -(float) $transfer->quantity,
            (string) $transfer->unit,
        );

        if ($transfer->operation_type === 'handoff') {
            $this->changeMaterialCustody(
                (int) $transfer->to_user_id,
                (int) $transfer->catalog_item_id,
                (int) $transfer->location_id,
                (float) $transfer->quantity,
                (string) $transfer->unit,
            );
        }

        return null;
    }

    /** @return array<int, string> */
    private function approvalFieldsFor(CustodyTransfer $transfer, User $actor): array
    {
        $fields = [];
        if ((int) $transfer->from_user_id === (int) $actor->id && ! $transfer->from_approved_at) {
            $fields[] = 'from_approved_at';
        }
        if ($transfer->operation_type !== 'return'
            && (int) $transfer->to_user_id === (int) $actor->id
            && ! $transfer->to_approved_at
        ) {
            $fields[] = 'to_approved_at';
        }
        if ($transfer->operation_type === 'return'
            && ! $transfer->to_approved_at
            && $transfer->location_id
            && $this->locationAccess->canWrite($actor, (int) $transfer->location_id)
        ) {
            $fields[] = 'to_approved_at';
        }

        return $fields;
    }

    private function ensureOperationalAsset(TrackedAsset $asset): void
    {
        if (! in_array($asset->status, ['available', 'in_use'], true)) {
            throw ValidationException::withMessages([
                'tracked_asset_id' => 'Echipamentul nu poate fi predat cât timp este în transfer, service sau marcat lipsă.',
            ]);
        }
    }

    private function activeRecipient(mixed $userId): User
    {
        if (! $userId) {
            throw ValidationException::withMessages(['to_user_id' => 'Alege persoana care primește custodia.']);
        }

        $user = User::where('active', true)->findOrFail($userId);
        if (! $user->hasAbility('custody.view')) {
            throw ValidationException::withMessages([
                'to_user_id' => 'Persoana aleasă nu are un rol operațional pentru gestionarea custodiei.',
            ]);
        }

        return $user;
    }

    private function ensureMaterialAvailableForIssue(CatalogItem $item, Location $location, float $quantity): void
    {
        if (! $this->materialIssueAvailable($item, $location, $quantity)) {
            throw ValidationException::withMessages([
                'quantity' => 'Cantitatea depășește stocul locației rămas fără responsabil personal.',
            ]);
        }
    }

    private function materialIssueAvailable(CatalogItem $item, Location $location, float $quantity, ?int $exceptTransferId = null): bool
    {
        $stock = StockLevel::where('location_id', $location->id)
            ->where('catalog_item_id', $item->id)
            ->lockForUpdate()
            ->value('quantity') ?? 0;
        $held = MaterialCustody::where('location_id', $location->id)
            ->where('catalog_item_id', $item->id)
            ->sum('quantity');
        $pending = CustodyTransfer::where('operation_type', 'issue')
            ->where('status', 'pending')
            ->where('catalog_item_id', $item->id)
            ->where('location_id', $location->id)
            ->when($exceptTransferId, fn ($query) => $query->whereKeyNot($exceptTransferId))
            ->sum('quantity');

        return $quantity - max(0, (float) $stock - (float) $held - (float) $pending) <= 0.0005;
    }

    private function materialIssueStillAvailable(CustodyTransfer $transfer, CatalogItem $item, Location $location): bool
    {
        return $this->materialIssueAvailable(
            $item,
            $location,
            (float) $transfer->quantity,
            (int) $transfer->id,
        );
    }

    private function changeMaterialCustody(
        int $userId,
        int $catalogItemId,
        int $locationId,
        float $quantity,
        string $unit,
    ): void {
        $holding = MaterialCustody::firstOrCreate(
            [
                'user_id' => $userId,
                'catalog_item_id' => $catalogItemId,
                'location_id' => $locationId,
            ],
            ['quantity' => 0, 'unit' => $unit],
        );
        $holding = MaterialCustody::whereKey($holding->id)->lockForUpdate()->firstOrFail();
        $newQuantity = round((float) $holding->quantity + $quantity, 3);
        if ($newQuantity < -0.0005) {
            throw ValidationException::withMessages(['quantity' => 'Cantitatea din custodie nu poate deveni negativă.']);
        }
        if ($newQuantity <= 0.0005) {
            $holding->delete();

            return;
        }
        $holding->update(['quantity' => $newQuantity, 'unit' => $unit]);
    }

    private function expirePendingOperations(): void
    {
        CustodyTransfer::where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);
    }

    private function notifyPendingApprovers(CustodyTransfer $transfer, User $actor): void
    {
        $recipients = collect();
        if (! $transfer->from_approved_at && $transfer->from_user_id) {
            $recipients->push($transfer->fromUser);
        }
        if (! $transfer->to_approved_at) {
            if ($transfer->operation_type === 'return') {
                $locationManagers = $transfer->location?->activeManagers()
                    ->where('users.active', true)
                    ->get() ?? collect();
                $recipients = $recipients->merge(
                    $locationManagers->isNotEmpty()
                        ? $locationManagers
                        : User::permission('custody.manage')
                            ->where('active', true)
                            ->with(['roles.permissions', 'permissions'])
                            ->get()
                            ->filter(fn (User $user): bool => $user->hasGlobalAbility('custody.manage')),
                );
            } elseif ($transfer->to_user_id) {
                $recipients->push($transfer->toUser);
            }
        }

        $recipients->filter()
            ->unique('id')
            ->reject(fn (User $user) => (int) $user->id === (int) $actor->id)
            ->each(fn (User $user) => $user->notify(new WorkflowNotification(
                'Confirmare de custodie necesară',
                $this->operationLabel($transfer).' '.$transfer->qr_token.' așteaptă acordul tău.',
                route('field.worker', ['status' => 'pending']),
            )));
    }

    private function notifyOutcome(CustodyTransfer $transfer, User $actor, string $title): void
    {
        User::whereIn('id', array_filter([
            $transfer->initiated_by,
            $transfer->from_user_id,
            $transfer->to_user_id,
        ]))
            ->where('id', '!=', $actor->id)
            ->get()
            ->each(fn (User $user) => $user->notify(new WorkflowNotification(
                $title,
                $this->operationLabel($transfer).' '.$transfer->qr_token.' are acum starea '.$transfer->fresh()->status.'.',
                route('field.worker'),
            )));
    }

    private function operationLabel(CustodyTransfer $transfer): string
    {
        return match ($transfer->operation_type) {
            'issue' => 'Alocarea',
            'return' => 'Returul',
            default => 'Predarea',
        };
    }

    private function outcomeMessage(string $outcome): string
    {
        return match ($outcome) {
            'expired' => 'Această operațiune a expirat. Inițiază una nouă.',
            'asset_unavailable' => 'Operațiunea a fost închisă deoarece echipamentul nu mai este disponibil operațional.',
            'material_unavailable' => 'Operațiunea a fost închisă deoarece stocul disponibil nu mai acoperă cantitatea.',
            default => 'Operațiunea a fost închisă deoarece custodia s-a schimbat între timp.',
        };
    }
}
