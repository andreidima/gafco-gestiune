<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\CustodyTransfer;
use App\Models\Location;
use App\Models\MaterialCustody;
use App\Models\StockLevel;
use App\Models\TrackedAsset;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PersonalCustodyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_issue_material_without_changing_location_stock(): void
    {
        [$manager, $worker, $location] = $this->managerWorkerAndLocation();
        $item = $this->material('MAT-CUST-1');
        StockLevel::create([
            'location_id' => $location->id,
            'catalog_item_id' => $item->id,
            'quantity' => 10,
        ]);

        $this->actingAs($manager)->post(route('custody-transfers.store'), [
            'operation_type' => 'issue',
            'item_type' => 'material',
            'catalog_item_id' => $item->id,
            'location_id' => $location->id,
            'to_user_id' => $worker->id,
            'quantity' => 3.5,
        ])->assertRedirect();

        $transfer = CustodyTransfer::latest('id')->firstOrFail();
        $this->assertSame('pending', $transfer->status);
        $this->assertNotNull($transfer->from_approved_at);
        $this->assertNull($transfer->to_approved_at);

        $this->actingAs($worker)->put(route('custody-transfers.update', $transfer), [
            'decision' => 'approved',
        ])->assertRedirect();

        $this->assertSame('accepted', $transfer->fresh()->status);
        $this->assertDatabaseHas('material_custodies', [
            'user_id' => $worker->id,
            'catalog_item_id' => $item->id,
            'location_id' => $location->id,
            'quantity' => 3.5,
        ]);
        $this->assertSame('10.000', StockLevel::firstOrFail()->quantity);
    }

    public function test_material_handoff_moves_personal_responsibility_after_both_approvals(): void
    {
        [, $from, $location] = $this->managerWorkerAndLocation();
        $to = $this->userWithRole('muncitor', 'WORKER-TO');
        $item = $this->material('MAT-CUST-2');
        $holding = MaterialCustody::create([
            'user_id' => $from->id,
            'catalog_item_id' => $item->id,
            'location_id' => $location->id,
            'quantity' => 5,
            'unit' => 'buc',
        ]);

        $this->actingAs($from)->post(route('custody-transfers.store'), [
            'operation_type' => 'handoff',
            'item_type' => 'material',
            'material_custody_id' => $holding->id,
            'to_user_id' => $to->id,
            'quantity' => 2,
            'notes' => 'Predare pentru echipa de montaj.',
        ])->assertRedirect();

        $transfer = CustodyTransfer::latest('id')->firstOrFail();
        $this->assertNotNull($transfer->from_approved_at);
        $this->assertNull($transfer->to_approved_at);

        $this->actingAs($to)->put(route('custody-transfers.update', $transfer), [
            'decision' => 'approved',
        ])->assertRedirect();

        $this->assertSame('3.000', $holding->fresh()->quantity);
        $this->assertDatabaseHas('material_custodies', [
            'user_id' => $to->id,
            'catalog_item_id' => $item->id,
            'location_id' => $location->id,
            'quantity' => 2,
        ]);
    }

    public function test_material_return_notifies_all_location_managers_and_one_can_confirm(): void
    {
        Notification::fake();
        [$manager, $worker, $location] = $this->managerWorkerAndLocation();
        $secondManager = $this->userWithRole('sef-santier', 'MANAGER-2');
        $location->managers()->attach($secondManager->id, ['active' => true, 'is_primary' => false]);
        $item = $this->material('MAT-CUST-3');
        $holding = MaterialCustody::create([
            'user_id' => $worker->id,
            'catalog_item_id' => $item->id,
            'location_id' => $location->id,
            'quantity' => 4,
            'unit' => 'kg',
        ]);

        $this->actingAs($worker)->post(route('custody-transfers.store'), [
            'operation_type' => 'return',
            'item_type' => 'material',
            'material_custody_id' => $holding->id,
            'quantity' => 1.5,
        ])->assertRedirect();

        $transfer = CustodyTransfer::latest('id')->firstOrFail();
        Notification::assertSentTo([$manager, $secondManager], WorkflowNotification::class);

        $this->actingAs($secondManager)->put(route('custody-transfers.update', $transfer), [
            'decision' => 'approved',
            'response_notes' => 'Cantitate verificată la primire.',
        ])->assertRedirect();

        $this->assertSame('accepted', $transfer->fresh()->status);
        $this->assertSame($secondManager->id, $transfer->fresh()->manager_approved_by);
        $this->assertSame('2.500', $holding->fresh()->quantity);
    }

    public function test_equipment_return_clears_custodian_and_records_condition(): void
    {
        [$manager, $worker, $location] = $this->managerWorkerAndLocation();
        $destination = Location::create([
            'type' => 'base',
            'code' => 'BASE-CUST-2',
            'name' => 'Baza retur',
            'active' => true,
        ]);
        $destination->managers()->attach($manager->id, ['active' => true, 'is_primary' => true]);
        $item = CatalogItem::create([
            'category' => 'echipament',
            'tracking_type' => 'serialized',
            'sku' => 'EQ-CUST-1',
            'name' => 'Polizor',
            'unit' => 'buc',
            'active' => true,
        ]);
        $asset = TrackedAsset::create([
            'catalog_item_id' => $item->id,
            'asset_code' => 'ASSET-CUST-1',
            'qr_code' => 'QR-ASSET-CUST-1',
            'status' => 'in_use',
            'condition' => 'used',
            'current_location_id' => $location->id,
            'current_custodian_id' => $worker->id,
        ]);

        $this->actingAs($worker)->post(route('custody-transfers.store'), [
            'operation_type' => 'return',
            'item_type' => 'equipment',
            'tracked_asset_id' => $asset->id,
            'location_id' => $destination->id,
            'return_condition' => 'used',
        ])->assertRedirect();

        $transfer = CustodyTransfer::latest('id')->firstOrFail();
        $this->actingAs($manager)->put(route('custody-transfers.update', $transfer), [
            'decision' => 'approved',
            'return_condition' => 'damaged',
            'response_notes' => 'Necesită verificare înainte de reutilizare.',
        ])->assertRedirect();

        $asset->refresh();
        $this->assertNull($asset->current_custodian_id);
        $this->assertSame($destination->id, $asset->current_location_id);
        $this->assertSame('damaged', $asset->condition);
        $this->assertSame('maintenance', $asset->status);
    }

    public function test_rejection_requires_an_observation(): void
    {
        [, $from, $location] = $this->managerWorkerAndLocation();
        $to = $this->userWithRole('muncitor', 'WORKER-REJECT');
        $item = $this->material('MAT-CUST-4');
        $holding = MaterialCustody::create([
            'user_id' => $from->id,
            'catalog_item_id' => $item->id,
            'location_id' => $location->id,
            'quantity' => 2,
            'unit' => 'buc',
        ]);
        $this->actingAs($from)->post(route('custody-transfers.store'), [
            'operation_type' => 'handoff',
            'item_type' => 'material',
            'material_custody_id' => $holding->id,
            'to_user_id' => $to->id,
            'quantity' => 1,
        ]);
        $transfer = CustodyTransfer::latest('id')->firstOrFail();

        $this->actingAs($to)
            ->from(route('field.worker'))
            ->put(route('custody-transfers.update', $transfer), ['decision' => 'rejected'])
            ->assertRedirect(route('field.worker'))
            ->assertSessionHasErrors('response_notes');

        $this->assertSame('pending', $transfer->fresh()->status);
    }

    public function test_driver_uses_recipient_code_without_seeing_other_driver_list(): void
    {
        $driver = $this->userWithRole('sofer', 'DRIVER-SELF');
        $otherDriver = $this->userWithRole('sofer', 'DRIVER-OTHER');

        $this->actingAs($driver)
            ->get(route('field.worker'))
            ->assertOk()
            ->assertSee('Cod utilizator')
            ->assertDontSee($otherDriver->name);
    }

    public function test_read_only_manager_can_view_custody_without_operation_forms(): void
    {
        $manager = $this->userWithRole('manager', 'MANAGER-READ');

        $this->actingAs($manager)
            ->get(route('field.worker'))
            ->assertOk()
            ->assertSee('Custodia mea')
            ->assertDontSee('Operațiune nouă');

        $this->actingAs($manager)->post(route('custody-transfers.store'), [
            'operation_type' => 'handoff',
            'item_type' => 'equipment',
        ])->assertForbidden();
    }

    /** @return array{User, User, Location} */
    private function managerWorkerAndLocation(): array
    {
        $manager = $this->userWithRole('sef-santier', 'MANAGER-1');
        $worker = $this->userWithRole('muncitor', 'WORKER-1');
        $location = Location::create([
            'type' => 'site',
            'code' => 'SITE-CUST-1',
            'name' => 'Șantier custodie',
            'active' => true,
        ]);
        $location->managers()->attach($manager->id, ['active' => true, 'is_primary' => true]);

        return [$manager, $worker, $location];
    }

    private function userWithRole(string $role, string $code): User
    {
        Role::findOrCreate($role);
        $user = User::factory()->create(['login_code' => $code]);
        $user->assignRole($role);

        return $user;
    }

    private function material(string $sku): CatalogItem
    {
        return CatalogItem::create([
            'category' => 'material',
            'tracking_type' => 'quantity',
            'sku' => $sku,
            'name' => 'Material '.$sku,
            'unit' => str_ends_with($sku, '3') ? 'kg' : 'buc',
            'active' => true,
        ]);
    }
}
