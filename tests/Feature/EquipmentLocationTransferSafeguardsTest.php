<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\TrackedAsset;
use App\Models\Transfer;
use App\Models\TransferApproval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EquipmentLocationTransferSafeguardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'dispecer'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_equipment_edit_preserves_the_filtered_paginated_list_context_on_desktop_and_mobile(): void
    {
        $admin = $this->admin();
        $location = $this->location('CTX-BASE');
        $item = $this->item('CTX-EQP', 'serialized', 'Echipament filtrat');
        $asset = $this->asset($item, $location, 'CTX-001');

        foreach (range(2, 21) as $index) {
            $this->asset($item, $location, sprintf('CTX-%03d', $index));
        }

        $returnTo = route('tracked-assets.index', [
            'page' => 2,
            'search' => 'Echipament filtrat',
            'status' => 'available',
        ]);
        $editUrl = route('tracked-assets.edit', [
            'tracked_asset' => $asset,
            'return_to' => $returnTo,
        ]);
        $showUrl = route('tracked-assets.show', [
            'tracked_asset' => $asset,
            'return_to' => $returnTo,
        ]);

        $listResponse = $this->actingAs($admin)->get($returnTo);
        $listResponse
            ->assertOk()
            ->assertSee($editUrl)
            ->assertSee($showUrl);
        $this->assertGreaterThanOrEqual(2, substr_count($listResponse->getContent(), e($editUrl)));

        $this->get($showUrl)
            ->assertOk()
            ->assertSee('href="'.e($returnTo).'"', false)
            ->assertSee($editUrl);

        $this->get($editUrl)
            ->assertOk()
            ->assertSee('name="return_to"', false)
            ->assertSee(e($returnTo), false)
            ->assertSee('href="'.e($returnTo).'"', false);

        $this->put(route('tracked-assets.update', $asset), [
            'catalog_item_id' => $item->id,
            'asset_code' => 'CTX-001',
            'status' => 'maintenance',
            'condition' => 'needs_service',
            'current_location_id' => $location->id,
            'return_to' => $returnTo,
        ])->assertRedirect($returnTo);

        $this->assertSame('maintenance', $asset->fresh()->status);

        $this->put(route('tracked-assets.update', $asset), [
            'catalog_item_id' => $item->id,
            'asset_code' => 'CTX-001',
            'status' => 'available',
            'condition' => 'good',
            'current_location_id' => $location->id,
            'return_to' => 'https://example.invalid/alta-pagina?status=lost',
        ])->assertRedirect(route('tracked-assets.index'));
    }

    public function test_location_deactivation_reports_all_blockers_and_succeeds_after_they_are_resolved(): void
    {
        $admin = $this->admin();
        $location = $this->location('BLOCKED');
        $destination = $this->location('OTHER');
        $equipmentItem = $this->item('BLOCK-EQP', 'serialized', 'Echipament blocant');
        $material = $this->item('BLOCK-MAT', 'quantity', 'Material blocant');
        $asset = $this->asset($equipmentItem, $location, 'BLOCK-ASSET');
        $stock = StockLevel::create([
            'location_id' => $location->id,
            'catalog_item_id' => $material->id,
            'quantity' => 4.5,
        ]);
        $transfer = Transfer::create([
            'number' => 'TR-BLOCKED',
            'type' => 'base_to_site',
            'purpose' => 'transfer',
            'revision' => 2,
            'status' => 'pending_approval',
            'source_location_id' => $location->id,
            'destination_location_id' => $destination->id,
            'requested_by' => $admin->id,
            'requested_at' => now(),
        ]);
        $approval = TransferApproval::create([
            'transfer_id' => $transfer->id,
            'revision' => 2,
            'scope' => 'source_manager',
            'location_id' => $location->id,
            'status' => 'pending',
        ]);

        $message = 'Locația nu poate fi dezactivată. Rezolvă mai întâi: 1 echipament alocat; 1 material cu stoc pozitiv; 1 aprobare în așteptare; 1 transfer activ.';
        $this->actingAs($admin)->put(route('locations.update', $location), [
            'type' => 'base',
            'code' => 'BLOCKED',
            'name' => 'Nume care nu trebuie salvat',
            'active' => '0',
        ])->assertSessionHasErrors(['active' => $message]);

        $location->refresh();
        $this->assertTrue($location->active);
        $this->assertSame('BLOCKED', $location->name);

        $asset->update(['current_location_id' => null]);
        $stock->update(['quantity' => 0]);
        $approval->update(['status' => 'approved']);
        $transfer->update(['status' => 'received', 'received_at' => now()]);

        $this->put(route('locations.update', $location), [
            'type' => 'base',
            'code' => 'BLOCKED',
            'name' => 'Locație închisă',
            'active' => '0',
        ])->assertRedirect(route('locations.index'));

        $location->refresh();
        $this->assertFalse($location->active);
        $this->assertSame('Locație închisă', $location->name);
    }

    public function test_transfer_create_and_update_keep_the_server_side_different_location_rule(): void
    {
        $admin = $this->admin();
        $source = $this->location('SAME-SRC');
        $destination = $this->location('SAME-DST');
        $material = $this->item('SAME-MAT', 'quantity', 'Material transfer');
        StockLevel::create([
            'location_id' => $source->id,
            'catalog_item_id' => $material->id,
            'quantity' => 10,
        ]);
        $message = 'Locația de destinație trebuie să fie diferită de locația sursă.';
        $sameLocationPayload = $this->transferPayload($source, $source, $material);

        $this->actingAs($admin)->post(route('transfers.store'), $sameLocationPayload)
            ->assertSessionHasErrors(['destination_location_id' => $message]);
        $this->assertDatabaseCount('transfers', 0);
        session()->forget('_old_input');

        $transfer = Transfer::create([
            'number' => 'TR-DIFFERENT',
            'type' => 'base_to_site',
            'purpose' => 'transfer',
            'revision' => 1,
            'status' => 'pending_approval',
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'requested_by' => $admin->id,
            'requested_at' => now(),
        ]);
        $transfer->lines()->create([
            'catalog_item_id' => $material->id,
            'quantity' => 1,
            'unit' => 'buc',
        ]);

        $editResponse = $this->get(route('transfers.edit', $transfer))->assertOk();
        $this->assertMatchesRegularExpression(
            '/<select name="source_location_id".*?<option value="'.$destination->id.'"[^>]*disabled/s',
            $editResponse->getContent(),
        );
        $this->assertMatchesRegularExpression(
            '/<select name="destination_location_id".*?<option value="'.$source->id.'"[^>]*disabled/s',
            $editResponse->getContent(),
        );

        $this->put(route('transfers.update', $transfer), $sameLocationPayload)
            ->assertSessionHasErrors(['destination_location_id' => $message]);

        $transfer->refresh();
        $this->assertSame($destination->id, $transfer->destination_location_id);
        $this->assertSame(1, $transfer->revision);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['active' => true]);
        $admin->assignRole('admin');

        return $admin;
    }

    private function location(string $code): Location
    {
        return Location::create([
            'type' => 'base',
            'code' => $code,
            'name' => $code,
            'active' => true,
        ]);
    }

    private function item(string $sku, string $trackingType, string $name): CatalogItem
    {
        return CatalogItem::create([
            'category' => $trackingType === 'quantity' ? 'material' : 'equipment',
            'tracking_type' => $trackingType,
            'sku' => $sku,
            'name' => $name,
            'unit' => 'buc',
            'active' => true,
        ]);
    }

    private function asset(CatalogItem $item, Location $location, string $code): TrackedAsset
    {
        return TrackedAsset::create([
            'catalog_item_id' => $item->id,
            'asset_code' => $code,
            'qr_code' => 'QR-'.$code,
            'status' => 'available',
            'condition' => 'good',
            'current_location_id' => $location->id,
        ]);
    }

    private function transferPayload(Location $source, Location $destination, CatalogItem $material): array
    {
        return [
            'purpose' => 'transfer',
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'priority' => 'normal',
            'lines' => [[
                'catalog_item_id' => $material->id,
                'tracked_asset_id' => null,
                'quantity' => 1,
            ]],
        ];
    }
}
