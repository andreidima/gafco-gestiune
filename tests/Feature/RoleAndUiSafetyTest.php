<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\Location;
use App\Models\Task;
use App\Models\TrackedAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAndUiSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_data_mutations_are_restricted_by_role(): void
    {
        foreach (['super-admin', 'dispecer', 'gestionar-baza', 'sofer'] as $role) {
            Role::findOrCreate($role);
        }

        $driver = User::factory()->create(['login_code' => 'ROLE-DRIVER']);
        $driver->assignRole('sofer');
        $storekeeper = User::factory()->create(['login_code' => 'ROLE-STORE']);
        $storekeeper->assignRole('gestionar-baza');
        $dispatcher = User::factory()->create(['login_code' => 'ROLE-DISPATCH']);
        $dispatcher->assignRole('dispecer');

        $location = Location::create(['code' => 'ROLE-BASE', 'name' => 'Baza roluri', 'type' => 'base', 'active' => true]);
        $item = CatalogItem::create([
            'category' => 'equipment', 'tracking_type' => 'serialized', 'sku' => 'ROLE-EQP',
            'name' => 'Echipament roluri', 'unit' => 'buc', 'active' => true,
        ]);
        $asset = TrackedAsset::create([
            'catalog_item_id' => $item->id, 'asset_code' => 'ROLE-ASSET', 'qr_code' => 'QR-ROLE-ASSET',
            'status' => 'available', 'condition' => 'good', 'current_location_id' => $location->id,
        ]);

        $this->actingAs($driver)->get(route('locations.edit', $location))->assertForbidden();
        $this->actingAs($driver)->get(route('catalog-items.edit', $item))->assertForbidden();
        $this->actingAs($driver)->get(route('tracked-assets.edit', $asset))->assertForbidden();
        $this->actingAs($driver)->get(route('locations.index'))->assertForbidden();
        $this->actingAs($driver)->get(route('catalog-items.index'))->assertForbidden();
        $this->actingAs($driver)->get(route('tracked-assets.index'))->assertForbidden();

        $this->actingAs($storekeeper)->get(route('catalog-items.edit', $item))->assertOk();
        $this->actingAs($storekeeper)->get(route('locations.edit', $location))->assertForbidden();
        $this->actingAs($storekeeper)->get(route('tracked-assets.edit', $asset))->assertForbidden();

        $this->actingAs($dispatcher)->get(route('locations.edit', $location))->assertOk();
        $this->actingAs($dispatcher)->get(route('tracked-assets.edit', $asset))->assertOk();
    }

    public function test_management_role_takes_precedence_over_driver_role(): void
    {
        Role::findOrCreate('sef-santier');
        Role::findOrCreate('sofer');
        $user = User::factory()->create(['login_code' => 'ROLE-MIXED']);
        $user->assignRole(['sef-santier', 'sofer']);

        $this->assertFalse($user->usesDriverWorkspace());
        $this->assertTrue($user->isManagementUser());

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Panou operational')
            ->assertDontSee('Spatiul meu de lucru');
    }

    public function test_driver_asset_view_does_not_expose_another_custodian(): void
    {
        Role::findOrCreate('sofer');
        $viewer = User::factory()->create(['login_code' => 'ROLE-ASSET-VIEWER']);
        $viewer->assignRole('sofer');
        $otherDriver = User::factory()->create(['name' => 'Sofer care trebuie ascuns', 'login_code' => 'ROLE-ASSET-OTHER']);
        $otherDriver->assignRole('sofer');
        $item = CatalogItem::create([
            'category' => 'equipment', 'tracking_type' => 'serialized', 'sku' => 'ROLE-ASSET-ITEM',
            'name' => 'Echipament privat', 'unit' => 'buc', 'active' => true,
        ]);
        $asset = TrackedAsset::create([
            'catalog_item_id' => $item->id, 'asset_code' => 'ROLE-PRIVATE-ASSET', 'qr_code' => 'QR-ROLE-PRIVATE-ASSET',
            'status' => 'in_use', 'condition' => 'good', 'current_custodian_id' => $otherDriver->id,
        ]);

        $this->actingAs($viewer)->get(route('tracked-assets.show', $asset))
            ->assertOk()
            ->assertSee('Alocat unui coleg')
            ->assertDontSee('Sofer care trebuie ascuns')
            ->assertSee(route('qr-scan.index'), false);
    }

    public function test_deactivated_session_is_revoked_on_the_next_request(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create(['active' => false, 'login_code' => 'ROLE-INACTIVE']);
        $user->assignRole('super-admin');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_roleless_user_gets_no_operational_dashboard_or_module_access(): void
    {
        $user = User::factory()->create(['login_code' => 'ROLE-NONE']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Rol operational neatribuit')
            ->assertDontSee('Situatie stocuri')
            ->assertDontSee('Situatie soferi');
        $this->actingAs($user)->get(route('tasks.index'))->assertForbidden();
        $this->actingAs($user)->get(route('transfers.index'))->assertForbidden();
        $this->actingAs($user)->get(route('reports.index'))->assertForbidden();
    }

    public function test_role_removal_revokes_creator_based_task_access(): void
    {
        Role::findOrCreate('sef-santier');
        $manager = User::factory()->create(['login_code' => 'ROLE-REMOVED']);
        $manager->assignRole('sef-santier');
        $task = Task::create([
            'number' => 'ROLE-REMOVED-TASK',
            'title' => 'Sarcina istorica',
            'category' => 'general',
            'created_by' => $manager->id,
            'status' => 'unassigned',
            'priority' => 'normal',
        ]);
        $manager->removeRole('sef-santier');

        $this->actingAs($manager)->get(route('tasks.show', $task))->assertForbidden();
    }

    public function test_default_application_timezone_matches_the_romanian_client(): void
    {
        $this->assertSame('Europe/Bucharest', config('app.timezone'));
    }
}
