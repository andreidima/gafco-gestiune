<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\ConsumptionReport;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\SupplierReception;
use App\Models\TrackedAsset;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryReportingSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_cannot_access_inventory_registers_or_post_stock_movements(): void
    {
        $driver = $this->userWithRole('sofer');
        $location = $this->location('BASE-1');
        $item = $this->quantityItem('MAT-1');

        $this->actingAs($driver)->get(route('supplier-receptions.index'))->assertForbidden();
        $this->actingAs($driver)->post(route('supplier-receptions.store'), $this->receptionPayload($location, $item))->assertForbidden();
        $this->actingAs($driver)->get(route('consumption-reports.index'))->assertForbidden();
        $this->actingAs($driver)->post(route('consumption-reports.store'), $this->consumptionPayload($location, $item))->assertForbidden();
        $this->actingAs($driver)->get(route('reports.index'))->assertForbidden();
    }

    public function test_manager_inventory_indexes_are_limited_to_active_managed_locations(): void
    {
        $manager = $this->userWithRole('sef-santier');
        $managed = $this->location('SITE-1');
        $foreign = $this->location('SITE-2');
        $inactive = $this->location('SITE-OLD', false);
        $this->manage($manager, $managed);
        $this->manage($manager, $inactive);

        $managedReception = $this->reception($managed, 'RF-MANAGED');
        $this->reception($foreign, 'RF-FOREIGN');
        $this->reception($inactive, 'RF-INACTIVE');
        $managedConsumption = $this->consumption($managed, 'CS-MANAGED');
        $this->consumption($foreign, 'CS-FOREIGN');
        $this->consumption($inactive, 'CS-INACTIVE');

        $this->actingAs($manager)->get(route('supplier-receptions.index'))
            ->assertOk()
            ->assertViewHas('receptions', fn ($receptions) => $receptions->pluck('id')->all() === [$managedReception->id])
            ->assertViewHas('locations', fn ($locations) => $locations->pluck('id')->all() === [$managed->id])
            ->assertViewHas('totalReceptions', 1)
            ->assertViewHas('canCreate', true);

        $this->actingAs($manager)->get(route('consumption-reports.index'))
            ->assertOk()
            ->assertViewHas('reports', fn ($reports) => $reports->pluck('id')->all() === [$managedConsumption->id])
            ->assertViewHas('locations', fn ($locations) => $locations->pluck('id')->all() === [$managed->id])
            ->assertViewHas('totalReports', 1)
            ->assertViewHas('canCreate', true);
    }

    public function test_manager_can_post_only_active_quantity_stock_for_an_active_managed_location(): void
    {
        $manager = $this->userWithRole('gestionar-baza');
        $managed = $this->location('BASE-1');
        $foreign = $this->location('BASE-2');
        $inactive = $this->location('BASE-OLD', false);
        $this->manage($manager, $managed);
        $this->manage($manager, $inactive);
        $item = $this->quantityItem('MAT-1');

        $this->actingAs($manager)->post(route('supplier-receptions.store'), $this->receptionPayload($foreign, $item))
            ->assertSessionHasErrors('location_id');
        $this->actingAs($manager)->post(route('supplier-receptions.store'), $this->receptionPayload($inactive, $item))
            ->assertSessionHasErrors('location_id');
        $this->actingAs($manager)->post(route('consumption-reports.store'), $this->consumptionPayload($foreign, $item))
            ->assertSessionHasErrors('location_id');

        $inactiveSupplier = Supplier::create(['name' => 'Furnizor inactiv', 'active' => false]);
        $inactiveItem = $this->quantityItem('MAT-OFF', false);
        $individualItem = CatalogItem::create([
            'category' => 'echipament',
            'tracking_type' => 'individual',
            'sku' => 'EQ-1',
            'name' => 'Echipament individual',
            'unit' => 'buc',
            'active' => true,
        ]);

        $this->actingAs($manager)->post(route('supplier-receptions.store'), [
            ...$this->receptionPayload($managed, $individualItem),
            'supplier_id' => $inactiveSupplier->id,
        ])->assertSessionHasErrors(['supplier_id', 'catalog_item_id']);
        $this->actingAs($manager)->post(route('consumption-reports.store'), $this->consumptionPayload($managed, $inactiveItem))
            ->assertSessionHasErrors('catalog_item_id');

        $response = $this->actingAs($manager)->post(route('supplier-receptions.store'), [
            ...$this->receptionPayload($managed, $item),
            'quantity' => 7.5,
        ]);
        $response->assertRedirect(route(
            'supplier-receptions.show',
            SupplierReception::query()->sole(),
        ));

        $this->assertDatabaseCount('supplier_receptions', 1);
        $this->assertDatabaseHas('stock_levels', [
            'location_id' => $managed->id,
            'catalog_item_id' => $item->id,
            'quantity' => 7.5,
        ]);
    }

    public function test_consumption_above_available_stock_is_rejected_without_creating_a_report(): void
    {
        $manager = $this->userWithRole('sef-santier');
        $location = $this->location('SITE-1');
        $this->manage($manager, $location);
        $item = $this->quantityItem('MAT-1');
        $stock = StockLevel::create([
            'location_id' => $location->id,
            'catalog_item_id' => $item->id,
            'quantity' => 5,
        ]);

        $this->actingAs($manager)->post(route('consumption-reports.store'), [
            ...$this->consumptionPayload($location, $item),
            'quantity' => 6,
        ])->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('consumption_reports', 0);
        $this->assertDatabaseCount('consumption_report_lines', 0);
        $this->assertSame(5.0, (float) $stock->fresh()->quantity);

        $this->actingAs($manager)->post(route('consumption-reports.store'), [
            ...$this->consumptionPayload($location, $item),
            'quantity' => 2,
        ])->assertRedirect(route('consumption-reports.index'));

        $this->assertDatabaseCount('consumption_reports', 1);
        $this->assertSame(3.0, (float) $stock->fresh()->quantity);
    }

    public function test_accounting_has_global_read_only_access_to_inventory_indexes(): void
    {
        $accountant = $this->userWithRole('contabil');
        $first = $this->location('BASE-1');
        $second = $this->location('SITE-1');
        $this->reception($first, 'RF-1');
        $this->reception($second, 'RF-2');
        $this->consumption($first, 'CS-1');
        $this->consumption($second, 'CS-2');

        $this->actingAs($accountant)->get(route('supplier-receptions.index'))
            ->assertOk()
            ->assertViewHas('receptions', fn ($receptions) => $receptions->total() === 2)
            ->assertViewHas('canCreate', false);
        $this->actingAs($accountant)->get(route('consumption-reports.index'))
            ->assertOk()
            ->assertViewHas('reports', fn ($reports) => $reports->total() === 2)
            ->assertViewHas('canCreate', false);

        $this->actingAs($accountant)->get(route('supplier-receptions.create'))->assertForbidden();
        $this->actingAs($accountant)->get(route('consumption-reports.create'))->assertForbidden();
    }

    public function test_reports_are_scoped_to_the_managers_active_locations_while_accounting_sees_all(): void
    {
        $manager = $this->userWithRole('sef-santier');
        $accountant = $this->userWithRole('contabil');
        $managed = $this->location('SITE-1');
        $foreign = $this->location('SITE-2');
        $this->manage($manager, $managed);
        $quantityItem = $this->quantityItem('MAT-1');
        $assetItem = CatalogItem::create([
            'category' => 'echipament',
            'tracking_type' => 'individual',
            'sku' => 'EQ-1',
            'name' => 'Echipament',
            'unit' => 'buc',
            'active' => true,
        ]);
        StockLevel::create(['location_id' => $managed->id, 'catalog_item_id' => $quantityItem->id, 'quantity' => 3]);
        StockLevel::create(['location_id' => $foreign->id, 'catalog_item_id' => $quantityItem->id, 'quantity' => 4]);
        $managedAsset = TrackedAsset::create([
            'catalog_item_id' => $assetItem->id,
            'asset_code' => 'ASSET-1',
            'qr_code' => 'QR-1',
            'status' => 'lost',
            'current_location_id' => $managed->id,
        ]);
        $foreignAsset = TrackedAsset::create([
            'catalog_item_id' => $assetItem->id,
            'asset_code' => 'ASSET-2',
            'qr_code' => 'QR-2',
            'status' => 'lost',
            'current_location_id' => $foreign->id,
        ]);
        $managedTransfer = $this->transfer($managed, $managed, 'TR-MANAGED');
        $foreignTransfer = $this->transfer($foreign, $foreign, 'TR-FOREIGN');
        $managedTransfer->lines()->create([
            'catalog_item_id' => $quantityItem->id,
            'quantity' => 1,
            'unit' => 'buc',
            'received_status' => 'missing',
        ]);
        $foreignTransfer->lines()->create([
            'catalog_item_id' => $quantityItem->id,
            'quantity' => 1,
            'unit' => 'buc',
            'received_status' => 'missing',
        ]);
        $managedConsumption = $this->consumption($managed, 'CS-MANAGED');
        $foreignConsumption = $this->consumption($foreign, 'CS-FOREIGN');

        $assertManagerScope = function ($response) use ($managed, $managedAsset, $managedTransfer, $managedConsumption): void {
            $response
                ->assertOk()
                ->assertViewHas('locations', fn ($locations) => $locations->pluck('id')->all() === [$managed->id])
                ->assertViewHas('assetsByLocation', fn ($locations) => $locations->pluck('id')->all() === [$managed->id])
                ->assertViewHas('missingAssets', fn ($assets) => $assets->pluck('id')->all() === [$managedAsset->id])
                ->assertViewHas('recentTransfers', fn ($transfers) => $transfers->pluck('id')->all() === [$managedTransfer->id])
                ->assertViewHas('inTransitAlerts', fn ($transfers) => $transfers->pluck('id')->all() === [$managedTransfer->id])
                ->assertViewHas('discrepancyLines', fn ($lines) => $lines->pluck('transfer_id')->all() === [$managedTransfer->id])
                ->assertViewHas('recentConsumption', fn ($reports) => $reports->pluck('id')->all() === [$managedConsumption->id]);
        };

        $assertManagerScope($this->actingAs($manager)->get(route('reports.index')));
        $this->actingAs($manager)->get(route('reports.index', ['location_id' => $foreign->id]))
            ->assertOk()
            ->assertViewHas('assetsByLocation', fn ($locations) => $locations->isEmpty())
            ->assertViewHas('recentTransfers', fn ($transfers) => $transfers->isEmpty())
            ->assertViewHas('recentConsumption', fn ($reports) => $reports->isEmpty());

        $this->actingAs($accountant)->get(route('reports.index'))
            ->assertOk()
            ->assertViewHas('missingAssets', fn ($assets) => $assets->pluck('id')->sort()->values()->all() === [$managedAsset->id, $foreignAsset->id])
            ->assertViewHas('recentTransfers', fn ($transfers) => $transfers->pluck('id')->sort()->values()->all() === [$managedTransfer->id, $foreignTransfer->id])
            ->assertViewHas('recentConsumption', fn ($reports) => $reports->pluck('id')->sort()->values()->all() === [$managedConsumption->id, $foreignConsumption->id]);
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function location(string $code, bool $active = true): Location
    {
        return Location::create([
            'type' => str_starts_with($code, 'BASE') ? 'base' : 'site',
            'code' => $code,
            'name' => $code,
            'active' => $active,
        ]);
    }

    private function manage(User $user, Location $location): void
    {
        $location->managers()->attach($user->id, ['active' => true, 'is_primary' => true]);
        $location->update(['manager_user_id' => $user->id]);
    }

    private function quantityItem(string $sku, bool $active = true): CatalogItem
    {
        return CatalogItem::create([
            'category' => 'material',
            'tracking_type' => 'quantity',
            'sku' => $sku,
            'name' => $sku,
            'unit' => 'buc',
            'active' => $active,
        ]);
    }

    private function reception(Location $location, string $number): SupplierReception
    {
        return SupplierReception::create([
            'number' => $number,
            'location_id' => $location->id,
            'document_type' => 'aviz',
            'status' => 'posted',
            'received_at' => now(),
        ]);
    }

    private function consumption(Location $location, string $number): ConsumptionReport
    {
        return ConsumptionReport::create([
            'number' => $number,
            'location_id' => $location->id,
            'status' => 'posted',
            'reported_at' => now(),
        ]);
    }

    private function transfer(Location $source, Location $destination, string $number): Transfer
    {
        return Transfer::create([
            'number' => $number,
            'type' => 'site_to_site',
            'purpose' => 'transfer',
            'status' => 'in_transit',
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'requested_at' => now()->subDay(),
            'dispatched_at' => now()->subDay(),
        ]);
    }

    /** @return array<string, mixed> */
    private function receptionPayload(Location $location, CatalogItem $item): array
    {
        return [
            'location_id' => $location->id,
            'supplier_id' => null,
            'document_type' => 'aviz',
            'document_number' => 'DOC-1',
            'catalog_item_id' => $item->id,
            'quantity' => 1,
        ];
    }

    /** @return array<string, mixed> */
    private function consumptionPayload(Location $location, CatalogItem $item): array
    {
        return [
            'location_id' => $location->id,
            'catalog_item_id' => $item->id,
            'quantity' => 1,
        ];
    }
}
