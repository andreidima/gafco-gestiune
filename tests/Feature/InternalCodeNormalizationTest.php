<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\Location;
use App\Models\Project;
use App\Models\TrackedAsset;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalCodeNormalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
    }

    public function test_models_normalize_only_internal_codes(): void
    {
        $user = User::factory()->create(['name' => 'Nume Mixt', 'login_code' => ' usr-mic ']);
        $location = Location::create(['type' => 'base', 'code' => ' b-mic ', 'name' => 'Bază Mixtă', 'active' => true]);
        $item = CatalogItem::create([
            'category' => 'equipment', 'tracking_type' => 'serialized', 'sku' => ' eq-unu ',
            'barcode' => 'BarCode-mixt', 'name' => 'Utilaj Mixt', 'unit' => 'buc', 'active' => true,
        ]);
        $asset = TrackedAsset::create([
            'catalog_item_id' => $item->id, 'asset_code' => ' utilaj-unu ',
            'qr_code' => 'QR-utilaj-unu', 'serial_number' => 'Serie-Mixtă',
            'status' => 'available', 'condition' => 'good',
        ]);
        $project = Project::create([
            'code' => ' prj-mic ', 'name' => 'Proiect Mixt', 'location_id' => $location->id,
            'created_by' => $user->id, 'status' => 'draft',
        ]);

        $this->assertSame('USR-MIC', $user->login_code);
        $this->assertSame('B-MIC', $location->code);
        $this->assertSame('EQ-UNU', $item->sku);
        $this->assertSame('UTILAJ-UNU', $asset->asset_code);
        $this->assertSame('PRJ-MIC', $project->code);
        $this->assertSame('Nume Mixt', $user->name);
        $this->assertSame('BarCode-mixt', $item->barcode);
        $this->assertSame('Serie-Mixtă', $asset->serial_number);

        $item->update(['sku' => '   ']);
        $this->assertNull($item->refresh()->sku);
    }

    public function test_quantity_forms_expose_decimal_safe_increment_controls(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Location::create([
            'type' => 'base',
            'code' => 'FORM-LOC',
            'name' => 'Locație formular',
            'active' => true,
        ]);

        $this->actingAs($admin)->get(route('supplier-receptions.create'))
            ->assertOk()
            ->assertSee('data-quantity-stepper', false);
    }
}
