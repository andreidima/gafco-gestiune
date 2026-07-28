<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\InventoryLotBalance;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\TrackedAsset;
use App\Models\Transfer;
use App\Models\TransferApproval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TransferWorkflowSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'dispecer', 'gestionar-baza', 'sef-santier', 'sofer'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_only_destination_manager_can_receive_an_in_transit_transfer_and_receipt_is_idempotent(): void
    {
        $sourceManager = $this->user('sef-santier');
        $destinationManager = $this->user('sef-santier');
        $source = $this->location('SEC-SRC', 'base', [$sourceManager]);
        $destination = $this->location('SEC-DST', 'site', [$destinationManager]);
        $item = $this->material('SEC-MAT-1');
        StockLevel::create(['location_id' => $source->id, 'catalog_item_id' => $item->id, 'quantity' => 20]);
        $transfer = $this->transfer($source, $destination, $sourceManager, 'pending_approval');
        $transfer->lines()->create(['catalog_item_id' => $item->id, 'quantity' => 5, 'unit' => 'buc']);

        $this->actingAs($sourceManager)
            ->post(route('transfers.receive', $transfer))
            ->assertForbidden();

        $this->actingAs($destinationManager)
            ->post(route('transfers.receive', $transfer))
            ->assertForbidden();
        $this->assertSame(20.0, (float) StockLevel::where('location_id', $source->id)->where('catalog_item_id', $item->id)->value('quantity'));

        $transfer->update(['status' => 'in_transit']);
        $this->actingAs($destinationManager)
            ->post(route('transfers.receive', $transfer))
            ->assertRedirect();
        $this->assertSame('received', $transfer->fresh()->status);
        $this->assertSame(15.0, (float) StockLevel::where('location_id', $source->id)->where('catalog_item_id', $item->id)->value('quantity'));
        $this->assertSame(5.0, (float) StockLevel::where('location_id', $destination->id)->where('catalog_item_id', $item->id)->value('quantity'));
        $this->assertSame(15.0, (float) InventoryLotBalance::where('location_id', $source->id)->sum('quantity'));
        $this->assertSame(5.0, (float) InventoryLotBalance::where('location_id', $destination->id)->sum('quantity'));
        $this->assertDatabaseHas('stock_movements', [
            'catalog_item_id' => $item->id,
            'location_id' => $source->id,
            'movement_type' => 'transfer_out',
            'quantity' => -5,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'catalog_item_id' => $item->id,
            'location_id' => $destination->id,
            'movement_type' => 'transfer_in',
            'quantity' => 5,
        ]);

        $this->actingAs($destinationManager)
            ->post(route('transfers.receive', $transfer))
            ->assertRedirect();
        $this->assertSame(15.0, (float) StockLevel::where('location_id', $source->id)->where('catalog_item_id', $item->id)->value('quantity'));
        $this->assertSame(5.0, (float) StockLevel::where('location_id', $destination->id)->where('catalog_item_id', $item->id)->value('quantity'));
        $this->assertDatabaseCount('stock_movements', 3);
    }

    public function test_archive_requires_visibility_and_a_terminal_unarchived_transfer(): void
    {
        $sourceManager = $this->user('sef-santier');
        $destinationManager = $this->user('sef-santier');
        $unrelatedManager = $this->user('sef-santier');
        $source = $this->location('ARC-SRC', 'base', [$sourceManager]);
        $destination = $this->location('ARC-DST', 'site', [$destinationManager]);
        $this->location('ARC-OTHER', 'site', [$unrelatedManager]);
        $transfer = $this->transfer($source, $destination, $sourceManager, 'received');

        $this->actingAs($unrelatedManager)
            ->post(route('transfers.archive', $transfer))
            ->assertForbidden();

        $this->actingAs($destinationManager)
            ->post(route('transfers.archive', $transfer))
            ->assertRedirect(route('transfers.index'));
        $this->assertNotNull($transfer->fresh()->archived_at);

        $active = $this->transfer($source, $destination, $sourceManager, 'approved');
        $this->actingAs($destinationManager)
            ->post(route('transfers.archive', $active))
            ->assertForbidden();
    }

    public function test_transfer_cannot_be_edited_after_transport_starts(): void
    {
        $manager = $this->user('sef-santier');
        $destinationManager = $this->user('sef-santier');
        $source = $this->location('EDIT-SRC', 'base', [$manager]);
        $destination = $this->location('EDIT-DST', 'site', [$destinationManager]);
        $transfer = $this->transfer($source, $destination, $manager, 'in_transit');

        $this->actingAs($manager)
            ->put(route('transfers.update', $transfer), [])
            ->assertForbidden();
    }

    public function test_generic_approval_endpoint_rejects_driver_scope_replays_and_terminal_changes(): void
    {
        $sourceManager = $this->user('sef-santier');
        $destinationManager = $this->user('sef-santier');
        $driver = $this->user('sofer');
        $source = $this->location('APR-SRC', 'base', [$sourceManager]);
        $destination = $this->location('APR-DST', 'site', [$destinationManager]);
        $transfer = $this->transfer($source, $destination, $sourceManager, 'pending_approval', ['driver_id' => $driver->id]);
        $driverApproval = $this->approval($transfer, 'driver', null, $driver);
        $destinationApproval = $this->approval($transfer, 'destination_manager', $destination);

        $this->actingAs($driver)
            ->put(route('transfer-approvals.update', $driverApproval), ['decision' => 'approved'])
            ->assertForbidden();
        $this->assertSame('pending', $driverApproval->fresh()->status);

        $this->actingAs($destinationManager)
            ->put(route('transfer-approvals.update', $destinationApproval), ['decision' => 'approved'])
            ->assertRedirect();
        $this->actingAs($destinationManager)
            ->put(route('transfer-approvals.update', $destinationApproval), ['decision' => 'rejected', 'decision_note' => 'Replay'])
            ->assertSessionHasErrors('decision');
        $this->assertSame('approved', $destinationApproval->fresh()->status);

        $terminalApproval = $this->approval($transfer, 'source_manager', $source);
        $transfer->update(['status' => 'received']);
        $this->actingAs($sourceManager)
            ->put(route('transfer-approvals.update', $terminalApproval), ['decision' => 'approved'])
            ->assertForbidden();
        $this->assertSame('pending', $terminalApproval->fresh()->status);
    }

    public function test_line_validation_uses_aggregate_material_quantity_and_rejects_duplicate_or_ambiguous_assets(): void
    {
        $manager = $this->user('sef-santier');
        $manager->assignRole('dispecer');
        $destinationManager = $this->user('sef-santier');
        $source = $this->location('LINE-SRC', 'base', [$manager]);
        $destination = $this->location('LINE-DST', 'site', [$destinationManager]);
        $item = $this->material('LINE-MAT');
        StockLevel::create(['location_id' => $source->id, 'catalog_item_id' => $item->id, 'quantity' => 10]);

        $payload = $this->payload($source, $destination, [
            ['catalog_item_id' => $item->id, 'tracked_asset_id' => null, 'quantity' => 6],
            ['catalog_item_id' => $item->id, 'tracked_asset_id' => null, 'quantity' => 6],
        ]);
        $this->actingAs($manager)->post(route('transfers.store'), $payload)
            ->assertSessionHasErrors('lines.0.quantity');

        $assetItem = CatalogItem::create([
            'category' => 'equipment', 'tracking_type' => 'serialized', 'sku' => 'LINE-ASSET-ITEM',
            'name' => 'Echipament securitate', 'unit' => 'buc', 'active' => true,
        ]);
        $asset = TrackedAsset::create([
            'catalog_item_id' => $assetItem->id, 'asset_code' => 'LINE-ASSET', 'qr_code' => 'QR-LINE-ASSET',
            'status' => 'available', 'condition' => 'good', 'current_location_id' => $source->id,
        ]);

        $duplicateAssetPayload = $this->payload($source, $destination, [
            ['catalog_item_id' => null, 'tracked_asset_id' => $asset->id, 'quantity' => 1],
            ['catalog_item_id' => null, 'tracked_asset_id' => $asset->id, 'quantity' => 1],
        ]);
        $this->actingAs($manager)->post(route('transfers.store'), $duplicateAssetPayload)
            ->assertSessionHasErrors('lines.1.tracked_asset_id');

        $ambiguousPayload = $this->payload($source, $destination, [
            ['catalog_item_id' => $item->id, 'tracked_asset_id' => $asset->id, 'quantity' => 1],
        ]);
        $this->actingAs($manager)->post(route('transfers.store'), $ambiguousPayload)
            ->assertSessionHasErrors('lines.0.catalog_item_id');

        $this->assertDatabaseCount('transfers', 0);
    }

    public function test_return_requires_an_accessible_received_original_transfer(): void
    {
        $sourceManager = $this->user('sef-santier');
        $sourceManager->assignRole('dispecer');
        $destinationManager = $this->user('sef-santier');
        $source = $this->location('RET-SRC', 'base', [$sourceManager]);
        $destination = $this->location('RET-DST', 'site', [$destinationManager]);
        $item = $this->material('RET-MAT');
        StockLevel::create(['location_id' => $destination->id, 'catalog_item_id' => $item->id, 'quantity' => 10]);
        $original = $this->transfer($source, $destination, $sourceManager, 'pending_approval');

        $this->actingAs($sourceManager)
            ->get(route('transfers.create', ['return_of' => $original->id]))
            ->assertStatus(422);
        $this->actingAs($sourceManager)
            ->post(route('transfers.store'), $this->payload($destination, $source, [
                ['catalog_item_id' => $item->id, 'tracked_asset_id' => null, 'quantity' => 2],
            ], ['purpose' => 'return', 'parent_transfer_id' => $original->id]))
            ->assertStatus(422);

        $original->update(['status' => 'received']);
        $unrelatedManager = $this->user('sef-santier');
        $unrelatedLocation = $this->location('RET-OTHER', 'site', [$unrelatedManager]);
        StockLevel::create(['location_id' => $unrelatedLocation->id, 'catalog_item_id' => $item->id, 'quantity' => 10]);
        $this->actingAs($unrelatedManager)
            ->post(route('transfers.store'), $this->payload($unrelatedLocation, $source, [
                ['catalog_item_id' => $item->id, 'tracked_asset_id' => null, 'quantity' => 1],
            ], ['purpose' => 'return', 'parent_transfer_id' => $original->id]))
            ->assertForbidden();

        $this->actingAs($sourceManager)
            ->get(route('transfers.create', ['return_of' => $original->id]))
            ->assertOk();
        $this->actingAs($sourceManager)
            ->post(route('transfers.store'), $this->payload($destination, $source, [
                ['catalog_item_id' => $item->id, 'tracked_asset_id' => null, 'quantity' => 2],
            ], ['purpose' => 'return', 'parent_transfer_id' => $original->id]))
            ->assertRedirect();

        $return = Transfer::where('purpose', 'return')->firstOrFail();
        $this->assertTrue($return->parentTransfer->is($original));
    }

    public function test_non_admin_manager_cannot_create_or_reroute_between_unmanaged_locations(): void
    {
        $manager = $this->user('sef-santier');
        $otherManager = $this->user('sef-santier');
        $managed = $this->location('SCOPE-MANAGED', 'site', [$manager]);
        $otherOne = $this->location('SCOPE-ONE', 'base', [$otherManager]);
        $otherTwo = $this->location('SCOPE-TWO', 'site', [$otherManager]);
        $item = $this->material('SCOPE-MAT');
        StockLevel::create(['location_id' => $otherOne->id, 'catalog_item_id' => $item->id, 'quantity' => 20]);

        $this->actingAs($manager)
            ->post(route('transfers.store'), $this->payload($otherOne, $otherTwo, [
                ['catalog_item_id' => $item->id, 'tracked_asset_id' => null, 'quantity' => 1],
            ]))
            ->assertForbidden();

        StockLevel::create(['location_id' => $managed->id, 'catalog_item_id' => $item->id, 'quantity' => 20]);
        $transfer = $this->transfer($managed, $otherOne, $manager, 'pending_approval');
        $transfer->lines()->create(['catalog_item_id' => $item->id, 'quantity' => 1, 'unit' => 'buc']);
        $this->actingAs($manager)
            ->put(route('transfers.update', $transfer), $this->payload($otherOne, $otherTwo, [
                ['catalog_item_id' => $item->id, 'tracked_asset_id' => null, 'quantity' => 1],
            ]))
            ->assertForbidden();
    }

    public function test_transfer_rejects_inactive_or_non_driver_assignees(): void
    {
        $manager = $this->user('sef-santier');
        $manager->assignRole('dispecer');
        $destinationManager = $this->user('sef-santier');
        $source = $this->location('DRV-SRC', 'base', [$manager]);
        $destination = $this->location('DRV-DST', 'site', [$destinationManager]);
        $item = $this->material('DRV-MAT');
        StockLevel::create(['location_id' => $source->id, 'catalog_item_id' => $item->id, 'quantity' => 20]);
        $inactiveDriver = $this->user('sofer', false);
        $nonDriver = $this->user('sef-santier');
        $basePayload = $this->payload($source, $destination, [
            ['catalog_item_id' => $item->id, 'tracked_asset_id' => null, 'quantity' => 1],
        ]);

        $this->actingAs($manager)
            ->post(route('transfers.store'), $basePayload + ['driver_id' => $inactiveDriver->id])
            ->assertSessionHasErrors('driver_id');
        $this->actingAs($manager)
            ->post(route('transfers.store'), $basePayload + ['driver_id' => $nonDriver->id])
            ->assertSessionHasErrors('driver_id');
        $this->assertDatabaseCount('transfers', 0);
    }

    public function test_serialized_asset_is_reserved_by_only_one_open_transfer(): void
    {
        $manager = $this->user('sef-santier');
        $manager->assignRole('dispecer');
        $destinationManager = $this->user('sef-santier');
        $source = $this->location('RESERVE-SRC', 'base', [$manager]);
        $destination = $this->location('RESERVE-DST', 'site', [$destinationManager]);
        $assetItem = CatalogItem::create([
            'category' => 'equipment', 'tracking_type' => 'serialized', 'sku' => 'RESERVE-ITEM',
            'name' => 'Echipament rezervat', 'unit' => 'buc', 'active' => true,
        ]);
        $asset = TrackedAsset::create([
            'catalog_item_id' => $assetItem->id, 'asset_code' => 'RESERVE-ASSET', 'qr_code' => 'QR-RESERVE-ASSET',
            'status' => 'available', 'condition' => 'good', 'current_location_id' => $source->id,
        ]);
        $payload = $this->payload($source, $destination, [[
            'catalog_item_id' => null, 'tracked_asset_id' => $asset->id, 'quantity' => 1,
        ]]);

        $this->actingAs($manager)->post(route('transfers.store'), $payload)->assertRedirect();
        $this->actingAs($manager)->post(route('transfers.store'), $payload)
            ->assertSessionHasErrors('lines.0.tracked_asset_id');

        $this->assertSame(1, Transfer::whereHas('lines', fn ($line) => $line->where('tracked_asset_id', $asset->id))->count());
    }

    public function test_driver_can_be_explicitly_cleared_and_revision_snapshot_keeps_approval_state(): void
    {
        $manager = $this->user('sef-santier');
        $manager->assignRole('dispecer');
        $destinationManager = $this->user('sef-santier');
        $driver = $this->user('sofer');
        $source = $this->location('UNASSIGN-SRC', 'base', [$manager]);
        $destination = $this->location('UNASSIGN-DST', 'site', [$destinationManager]);
        $item = $this->material('UNASSIGN-MAT');
        StockLevel::create(['location_id' => $source->id, 'catalog_item_id' => $item->id, 'quantity' => 20]);
        $payload = $this->payload($source, $destination, [[
            'catalog_item_id' => $item->id, 'tracked_asset_id' => null, 'quantity' => 2,
        ]]);

        $this->actingAs($manager)->post(route('transfers.store'), $payload + ['driver_id' => $driver->id])->assertRedirect();
        $transfer = Transfer::latest('id')->firstOrFail();
        $this->assertSame($driver->id, $transfer->task->currentAssignment->driver_id);

        $deadline = now()->addDay()->startOfMinute();
        $this->actingAs($manager)->put(route('transfers.update', $transfer), [
            ...$payload,
            'driver_id' => '',
            'priority' => 'urgent',
            'manager_deadline' => $deadline->format('Y-m-d H:i:s'),
            'notes' => 'Sofer eliminat explicit.',
        ])->assertRedirect();

        $transfer->refresh();
        $this->assertNull($transfer->driver_id);
        $this->assertSame(2, $transfer->revision);
        $this->assertSame('unassigned', $transfer->task->fresh()->status);
        $this->assertNull($transfer->task->currentAssignment);
        $driverApproval = $transfer->approvals()->where('revision', 2)->where('scope', 'driver')->firstOrFail();
        $this->assertNull($driverApproval->expected_user_id);
        $this->assertSame('pending', $driverApproval->status);

        $snapshot = $transfer->revisions()->where('revision', 2)->firstOrFail()->snapshot;
        $this->assertNull($snapshot['driver_id']);
        $this->assertSame('urgent', $snapshot['priority']);
        $this->assertNull($snapshot['parent_transfer_id']);
        $this->assertNotEmpty($snapshot['manager_deadline']);
    }

    private function user(string $role, bool $active = true): User
    {
        Role::findOrCreate($role);
        $user = User::factory()->create(['active' => $active]);
        $user->assignRole($role);

        return $user;
    }

    private function location(string $code, string $type, array $managers): Location
    {
        $location = Location::create(['code' => $code, 'name' => $code, 'type' => $type, 'active' => true]);
        $location->managers()->sync(collect($managers)->mapWithKeys(fn (User $manager, int $index) => [
            $manager->id => ['active' => true, 'is_primary' => $index === 0],
        ])->all());
        $location->update(['manager_user_id' => $managers[0]->id ?? null]);

        return $location;
    }

    private function material(string $sku): CatalogItem
    {
        return CatalogItem::create([
            'category' => 'material', 'tracking_type' => 'quantity', 'sku' => $sku,
            'name' => $sku, 'unit' => 'buc', 'active' => true,
        ]);
    }

    private function transfer(Location $source, Location $destination, User $requester, string $status, array $overrides = []): Transfer
    {
        return Transfer::create($overrides + [
            'number' => 'SEC-'.Str::upper(Str::random(12)),
            'type' => $source->type.'_to_'.$destination->type,
            'purpose' => 'transfer',
            'revision' => 1,
            'status' => $status,
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'requested_by' => $requester->id,
            'requested_at' => now(),
        ]);
    }

    private function approval(Transfer $transfer, string $scope, ?Location $location = null, ?User $expected = null): TransferApproval
    {
        return $transfer->approvals()->create([
            'revision' => $transfer->revision,
            'scope' => $scope,
            'location_id' => $location?->id,
            'expected_user_id' => $expected?->id,
            'status' => 'pending',
        ]);
    }

    private function payload(Location $source, Location $destination, array $lines, array $overrides = []): array
    {
        return $overrides + [
            'purpose' => 'transfer',
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'priority' => 'normal',
            'lines' => $lines,
        ];
    }
}
