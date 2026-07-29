<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\ConsumptionReport;
use App\Models\InventoryLotBalance;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\TrackedAsset;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaterialOperationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumption_lines_remain_readable_before_correction_columns_are_migrated(): void
    {
        $administrator = $this->user('admin');
        $location = $this->location('S-BOOTSTRAP', 'site');
        $material = $this->material('MAT-BOOTSTRAP');
        $report = ConsumptionReport::create([
            'number' => 'CS-BOOTSTRAP',
            'location_id' => $location->id,
            'reported_by' => $administrator->id,
            'status' => 'posted',
            'reported_at' => now(),
        ]);
        DB::table('consumption_report_lines')->insert([
            'consumption_report_id' => $report->id,
            'catalog_item_id' => $material->id,
            'quantity' => 1,
            'unit' => 'buc',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('consumption_report_lines', function ($table): void {
            $table->dropIndex(['superseded_at']);
        });
        Schema::table('consumption_report_lines', function ($table): void {
            $table->dropColumn(['revision', 'superseded_at']);
        });

        $this->assertCount(1, ConsumptionReport::with('lines')->findOrFail($report->id)->lines);
    }

    public function test_transfer_source_options_and_validation_exclude_reserved_stock_and_assets(): void
    {
        $dispatcher = $this->user('dispecer');
        $source = $this->location('B-SOURCE', 'base');
        $destination = $this->location('S-DEST', 'site');
        $material = $this->material('MAT-AVAILABLE');
        $emptyMaterial = $this->material('MAT-EMPTY');
        StockLevel::create(['location_id' => $source->id, 'catalog_item_id' => $material->id, 'quantity' => 10]);
        StockLevel::create(['location_id' => $source->id, 'catalog_item_id' => $emptyMaterial->id, 'quantity' => 0]);

        $assetItem = CatalogItem::create([
            'category' => 'equipment',
            'tracking_type' => 'individual',
            'sku' => 'EQ-ITEM',
            'name' => 'Echipament',
            'unit' => 'buc',
            'active' => true,
        ]);
        $reservedAsset = $this->asset($assetItem, $source, 'EQ-RESERVED');
        $availableAsset = $this->asset($assetItem, $source, 'EQ-AVAILABLE');

        $openTransfer = $this->transfer($dispatcher, $source, $destination);
        $openTransfer->lines()->create([
            'catalog_item_id' => $material->id,
            'quantity' => 4,
            'unit' => 'buc',
        ]);
        $openTransfer->lines()->create([
            'tracked_asset_id' => $reservedAsset->id,
            'catalog_item_id' => $assetItem->id,
            'quantity' => 1,
            'unit' => 'buc',
        ]);

        $response = $this->actingAs($dispatcher)->getJson(route('transfers.source-options', [
            'source_location_id' => $source->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('materials.0.id', $material->id)
            ->assertJsonPath('materials.0.available', '6.000');
        $this->assertSame([$material->id], collect($response->json('materials'))->pluck('id')->all());
        $this->assertSame([$availableAsset->id], collect($response->json('assets'))->pluck('id')->all());

        $this->actingAs($dispatcher)->post(route('transfers.store'), [
            'purpose' => 'transfer',
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'priority' => 'normal',
            'lines' => [[
                'catalog_item_id' => $material->id,
                'tracked_asset_id' => null,
                'quantity' => 7,
            ]],
        ])->assertSessionHasErrors('lines.0.quantity');
    }

    public function test_one_consumption_report_can_post_multiple_materials_atomically(): void
    {
        $keeper = $this->user('gestionar-baza');
        $location = $this->location('B-MULTI', 'base');
        $this->manage($keeper, $location);
        $first = $this->material('MAT-FIRST');
        $second = $this->material('MAT-SECOND');
        StockLevel::create(['location_id' => $location->id, 'catalog_item_id' => $first->id, 'quantity' => 10]);
        StockLevel::create(['location_id' => $location->id, 'catalog_item_id' => $second->id, 'quantity' => 8]);

        $this->actingAs($keeper)->post(route('consumption-reports.store'), [
            'location_id' => $location->id,
            'notes' => 'Consum pentru lucrarea A',
            'lines' => [
                ['catalog_item_id' => $first->id, 'quantity' => 3, 'notes' => 'Zona 1'],
                ['catalog_item_id' => $second->id, 'quantity' => 2.5, 'notes' => 'Zona 2'],
            ],
        ])->assertRedirect(route('consumption-reports.index'));

        $report = ConsumptionReport::query()->sole();
        $this->assertCount(2, $report->lines);
        $this->assertSame(7.0, (float) StockLevel::where('location_id', $location->id)->where('catalog_item_id', $first->id)->value('quantity'));
        $this->assertSame(5.5, (float) StockLevel::where('location_id', $location->id)->where('catalog_item_id', $second->id)->value('quantity'));
        $this->assertSame(2, StockMovement::where('movement_type', 'consumption')->count());
    }

    public function test_administrator_correction_preserves_versions_and_posts_reversal_movements(): void
    {
        $administrator = $this->user('admin');
        $manager = $this->user('sef-santier');
        $location = $this->location('S-CORRECTION', 'site');
        $this->manage($manager, $location);
        $material = $this->material('MAT-CORRECTION');
        StockLevel::create(['location_id' => $location->id, 'catalog_item_id' => $material->id, 'quantity' => 10]);

        $this->actingAs($administrator)->post(route('consumption-reports.store'), [
            'location_id' => $location->id,
            'catalog_item_id' => $material->id,
            'quantity' => 4,
            'notes' => 'Cantitate introdusă greșit',
        ])->assertRedirect(route('consumption-reports.index'));
        $report = ConsumptionReport::query()->sole();

        $this->actingAs($manager)->put(route('consumption-reports.update', $report), [
            'location_id' => $location->id,
            'correction_reason' => 'Încercare neautorizată',
            'lines' => [['catalog_item_id' => $material->id, 'quantity' => 2]],
        ])->assertForbidden();

        $this->actingAs($administrator)->put(route('consumption-reports.update', $report), [
            'location_id' => $location->id,
            'notes' => 'Cantitate verificată',
            'correction_reason' => 'Cantitatea corectă este 2, nu 4.',
            'lines' => [['catalog_item_id' => $material->id, 'quantity' => 2]],
        ])->assertRedirect(route('consumption-reports.index'));

        $report->refresh();
        $this->assertSame('modified', $report->status);
        $this->assertSame(2, $report->revision);
        $this->assertSame($administrator->id, $report->modified_by);
        $this->assertSame(2.0, (float) $report->lines()->sole()->quantity);
        $this->assertSame(2, $report->allLines()->count());
        $this->assertNotNull($report->allLines()->where('revision', 1)->sole()->superseded_at);
        $this->assertSame(1, $report->revisions()->count());
        $this->assertSame(8.0, (float) StockLevel::where('location_id', $location->id)->where('catalog_item_id', $material->id)->value('quantity'));
        $this->assertSame(8.0, (float) InventoryLotBalance::where('location_id', $location->id)->sum('quantity'));
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'consumption_correction_reversal',
            'reference_id' => $report->id,
            'quantity' => 4,
        ]);
        $this->assertSame(2, StockMovement::where('movement_type', 'consumption')->count());
    }

    public function test_legacy_consumption_without_lot_movements_can_be_corrected_safely(): void
    {
        $administrator = $this->user('admin');
        $location = $this->location('S-LEGACY', 'site');
        $material = $this->material('MAT-LEGACY');
        StockLevel::create([
            'location_id' => $location->id,
            'catalog_item_id' => $material->id,
            'quantity' => 6,
        ]);
        $report = ConsumptionReport::create([
            'number' => 'CS-LEGACY',
            'location_id' => $location->id,
            'reported_by' => $administrator->id,
            'status' => 'posted',
            'revision' => 1,
            'reported_at' => now()->subMonth(),
        ]);
        $report->lines()->create([
            'revision' => 1,
            'catalog_item_id' => $material->id,
            'quantity' => 4,
            'unit' => 'buc',
        ]);

        $this->actingAs($administrator)->put(route('consumption-reports.update', $report), [
            'location_id' => $location->id,
            'correction_reason' => 'Raportul istoric trebuia să conțină 2 bucăți.',
            'lines' => [['catalog_item_id' => $material->id, 'quantity' => 2]],
        ])->assertRedirect(route('consumption-reports.index'));

        $this->assertSame(8.0, (float) StockLevel::where('location_id', $location->id)->where('catalog_item_id', $material->id)->value('quantity'));
        $this->assertSame(8.0, (float) InventoryLotBalance::where('location_id', $location->id)->sum('quantity'));
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'consumption_correction_reversal',
            'reference_id' => $report->id,
            'quantity' => 4,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'consumption',
            'reference_id' => $report->id,
            'quantity' => -2,
        ]);
    }

    private function user(string $role): User
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

    private function material(string $sku): CatalogItem
    {
        return CatalogItem::create([
            'category' => 'material',
            'tracking_type' => 'quantity',
            'sku' => $sku,
            'name' => $sku,
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

    private function transfer(User $user, Location $source, Location $destination): Transfer
    {
        return Transfer::create([
            'number' => 'TR-'.Str::upper(Str::random(10)),
            'type' => $source->type.'_to_'.$destination->type,
            'purpose' => 'transfer',
            'revision' => 1,
            'status' => 'pending_approval',
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'requested_by' => $user->id,
            'requested_at' => now(),
        ]);
    }

    private function manage(User $user, Location $location): void
    {
        $location->managers()->attach($user->id, ['active' => true, 'is_primary' => true]);
        $location->update(['manager_user_id' => $user->id]);
    }
}
