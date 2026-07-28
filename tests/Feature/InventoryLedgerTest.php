<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\InventoryLotBalance;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reception_and_consumption_update_aggregate_stock_and_lot_history_together(): void
    {
        $keeper = $this->userWithRole('gestionar-baza');
        $location = $this->location('B-LEDGER', 'base');
        $this->assignLocation($keeper, $location);
        $item = $this->item('MAT-LEDGER', 'Material ledger');

        $this->actingAs($keeper)->post(route('supplier-receptions.store'), [
            'location_id' => $location->id,
            'document_type' => 'aviz',
            'document_number' => 'AVZ-LEDGER-1',
            'catalog_item_id' => $item->id,
            'quantity' => 10,
        ])->assertRedirect(route('supplier-receptions.index'));

        $this->assertDatabaseHas('stock_levels', [
            'location_id' => $location->id,
            'catalog_item_id' => $item->id,
            'quantity' => 10,
        ]);
        $this->assertDatabaseHas('inventory_lots', [
            'catalog_item_id' => $item->id,
            'document_number' => 'AVZ-LEDGER-1',
            'is_opening_balance' => false,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'catalog_item_id' => $item->id,
            'location_id' => $location->id,
            'movement_type' => 'reception',
            'quantity' => 10,
        ]);

        $this->actingAs($keeper)->post(route('consumption-reports.store'), [
            'location_id' => $location->id,
            'catalog_item_id' => $item->id,
            'quantity' => 3,
            'notes' => 'Consum test',
        ])->assertRedirect(route('consumption-reports.index'));

        $this->assertSame(7.0, (float) StockLevel::where('location_id', $location->id)->where('catalog_item_id', $item->id)->value('quantity'));
        $this->assertSame(7.0, (float) InventoryLotBalance::where('location_id', $location->id)->sum('quantity'));
        $this->assertDatabaseHas('stock_movements', [
            'catalog_item_id' => $item->id,
            'movement_type' => 'consumption',
            'quantity' => -3,
        ]);

        $this->actingAs($keeper)->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Fișă inventar materiale')
            ->assertSee('Material ledger')
            ->assertSee('7,000');

        $this->actingAs($keeper)->get(route('inventory.show', $item))
            ->assertOk()
            ->assertSee('AVZ-LEDGER-1')
            ->assertSee('Recepție')
            ->assertSee('Consum');
    }

    public function test_legacy_aggregate_stock_is_reconciled_as_an_opening_lot_before_consumption(): void
    {
        $manager = $this->userWithRole('sef-santier');
        $location = $this->location('S-OPENING', 'site');
        $this->assignLocation($manager, $location);
        $item = $this->item('MAT-OPENING', 'Material existent');
        StockLevel::create([
            'location_id' => $location->id,
            'catalog_item_id' => $item->id,
            'quantity' => 5,
        ]);

        $this->actingAs($manager)->post(route('consumption-reports.store'), [
            'location_id' => $location->id,
            'catalog_item_id' => $item->id,
            'quantity' => 2,
        ])->assertRedirect(route('consumption-reports.index'));

        $this->assertDatabaseHas('inventory_lots', [
            'catalog_item_id' => $item->id,
            'is_opening_balance' => true,
            'received_at' => null,
        ]);
        $this->assertSame(3.0, (float) InventoryLotBalance::where('location_id', $location->id)->sum('quantity'));
        $this->assertSame(3.0, (float) StockLevel::where('location_id', $location->id)->where('catalog_item_id', $item->id)->value('quantity'));
        $this->assertSame(2, StockMovement::where('catalog_item_id', $item->id)->count());
    }

    public function test_inventory_includes_zero_stock_and_persists_user_preferences(): void
    {
        $manager = $this->userWithRole('sef-santier');
        $location = $this->location('S-PREF', 'site');
        $this->assignLocation($manager, $location);
        $zeroItem = $this->item('MAT-ZERO', 'Material fără stoc');

        $this->actingAs($manager)->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Material fără stoc');

        $this->actingAs($manager)->putJson(route('preferences.inventory.update'), [
            'filters' => [
                'search' => 'fără stoc',
                'location_id' => $location->id,
                'hide_zero' => true,
            ],
            'columns' => ['locations', 'lots'],
            'density' => 'comfortable',
        ])->assertOk()->assertJson(['saved' => true]);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $manager->id,
            'key' => 'inventory.index',
        ]);

        $this->actingAs($manager)->get(route('inventory.index'))
            ->assertOk()
            ->assertViewHas('filters', fn (array $filters) => $filters['search'] === 'fără stoc'
                && $filters['location_id'] === $location->id
                && $filters['hide_zero'] === true)
            ->assertViewHas('density', 'comfortable')
            ->assertDontSee($zeroItem->name);
    }

    public function test_site_manager_cannot_see_or_select_an_unassigned_location(): void
    {
        $siteManager = $this->userWithRole('sef-santier');
        $managed = $this->location('S-ASSIGNED', 'site');
        $foreign = $this->location('S-HIDDEN', 'site');
        $this->assignLocation($siteManager, $managed);
        $item = $this->item('MAT-SCOPED', 'Material limitat');
        StockLevel::create(['location_id' => $managed->id, 'catalog_item_id' => $item->id, 'quantity' => 2]);
        StockLevel::create(['location_id' => $foreign->id, 'catalog_item_id' => $item->id, 'quantity' => 8]);

        $this->actingAs($siteManager)->get(route('inventory.index', ['filters_submitted' => 1]))
            ->assertOk()
            ->assertSee('S-ASSIGNED')
            ->assertDontSee('S-HIDDEN')
            ->assertSee('2,000')
            ->assertDontSee('10,000');

        $this->actingAs($siteManager)->get(route('transfers.create'))
            ->assertOk()
            ->assertSee('S-ASSIGNED')
            ->assertDontSee('S-HIDDEN');
    }

    public function test_global_manager_can_read_all_inventory_but_cannot_modify_operations(): void
    {
        $manager = $this->userWithRole('manager');
        $first = $this->location('B-GLOBAL', 'base');
        $second = $this->location('S-GLOBAL', 'site');
        $item = $this->item('MAT-GLOBAL', 'Material global');
        StockLevel::create(['location_id' => $first->id, 'catalog_item_id' => $item->id, 'quantity' => 4]);
        StockLevel::create(['location_id' => $second->id, 'catalog_item_id' => $item->id, 'quantity' => 6]);

        $this->actingAs($manager)->get(route('inventory.index', ['filters_submitted' => 1]))
            ->assertOk()
            ->assertSee('B-GLOBAL')
            ->assertSee('S-GLOBAL')
            ->assertSee('10,000');
        $this->actingAs($manager)->get(route('supplier-receptions.index'))->assertOk();
        $this->actingAs($manager)->get(route('locations.create'))->assertForbidden();
        $this->actingAs($manager)->get(route('supplier-receptions.create'))->assertForbidden();
        $this->actingAs($manager)->get(route('tasks.create'))->assertForbidden();
        $this->actingAs($manager)->get(route('transfers.create'))->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function location(string $code, string $type): Location
    {
        return Location::create([
            'type' => $type,
            'code' => $code,
            'name' => $code,
            'active' => true,
        ]);
    }

    private function assignLocation(User $user, Location $location): void
    {
        $location->managers()->attach($user->id, ['active' => true, 'is_primary' => true]);
        $location->update(['manager_user_id' => $user->id]);
    }

    private function item(string $sku, string $name): CatalogItem
    {
        return CatalogItem::create([
            'category' => 'material',
            'tracking_type' => 'quantity',
            'sku' => $sku,
            'name' => $name,
            'unit' => 'buc',
            'active' => true,
        ]);
    }
}
