<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\SupplierReception;
use App\Models\User;
use App\Services\AccessCatalog;
use App\Services\ReceptionAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_open_access_center_and_non_administrator_cannot(): void
    {
        $admin = $this->userWithRole('admin');
        $manager = $this->userWithRole('manager');

        $this->actingAs($admin)->get(route('access.index'))
            ->assertOk()
            ->assertSee('Administrare acces')
            ->assertSee('Matricea rolurilor standard');

        $this->actingAs($manager)->get(route('access.index'))->assertForbidden();
    }

    public function test_regular_administrator_cannot_inspect_protected_account(): void
    {
        $admin = $this->userWithRole('admin');
        $protected = $this->userWithRole('super-admin', [
            'name' => 'Identitate secretă unică',
            'email' => config('roles.protected_admin_email'),
            'login_code' => 'PROTEJAT',
        ]);

        $this->actingAs($admin)->get(route('access.index'))
            ->assertOk()
            ->assertDontSee('Identitate secretă unică');
        $this->actingAs($admin)->get(route('access.show', $protected))->assertForbidden();

        $this->actingAs($protected)->get(route('access.show', $protected))
            ->assertOk()
            ->assertSee('Identitatea contului protejat');
    }

    public function test_effective_access_explains_roles_locations_and_direct_exceptions(): void
    {
        $admin = $this->userWithRole('admin');
        $siteManager = $this->userWithRole('sef-santier', ['name' => 'Responsabil Șantier']);
        $siteManager->givePermissionTo(Permission::findByName('accounting.edit-operations'));
        $location = $this->location(['code' => 'S-01', 'name' => 'Șantier Central']);
        $location->managers()->attach($siteManager, ['active' => true, 'is_primary' => true]);

        $this->actingAs($admin)->get(route('access.show', $siteManager))
            ->assertOk()
            ->assertSee('Responsabil Șantier')
            ->assertSee('S-01 - Șantier Central')
            ->assertSee('Excepție atribuită direct')
            ->assertSee('Locațiile administrate')
            ->assertSee('accounting.edit-operations');
    }

    public function test_catalog_and_seeded_role_permissions_stay_in_sync(): void
    {
        $catalog = app(AccessCatalog::class);
        $this->assertSame(
            collect(array_keys($catalog->seedablePermissions()))->sort()->values()->all(),
            Permission::query()->pluck('name')->sort()->values()->all(),
        );

        foreach ($catalog->roleNames() as $roleName) {
            $actual = Role::findByName($roleName)->permissions->pluck('name')->sort()->values()->all();
            $expected = collect($catalog->permissionsForRole($roleName))->sort()->values()->all();

            $this->assertSame($expected, $actual, "Drepturile rolului {$roleName} nu corespund catalogului.");
        }

        $this->assertDatabaseMissing('permissions', ['name' => 'access-database-tools']);
    }

    public function test_access_help_and_release_content_is_published_and_reversible(): void
    {
        $migration = require database_path('migrations/2026_08_05_000041_add_access_administration_foundation.php');

        $this->assertDatabaseHas('help_articles', [
            'slug' => 'administrarea-accesului',
            'current_revision' => 1,
            'status' => 'published',
        ]);
        $this->assertDatabaseHas('release_notes', [
            'slug' => '2026-08-05-administrarea-accesului',
            'version' => '2026.08.05.1',
            'status' => 'published',
        ]);

        $migration->down();

        $this->assertDatabaseMissing('help_articles', ['slug' => 'administrarea-accesului']);
        $this->assertDatabaseMissing('release_notes', ['slug' => '2026-08-05-administrarea-accesului']);
        $this->assertDatabaseMissing('permissions', ['name' => 'access.view']);

        $migration->up();
    }

    public function test_removing_last_eligible_role_withdraws_stale_location_responsibility_and_audits_change(): void
    {
        $admin = $this->userWithRole('admin');
        $responsible = $this->userWithRole('sef-santier', ['name' => 'Responsabil vechi']);
        $location = $this->location(['manager_user_id' => $responsible->id]);
        $location->managers()->attach($responsible, ['active' => true, 'is_primary' => true]);

        $this->actingAs($admin)->put(route('users.update', $responsible), [
            'name' => $responsible->name,
            'login_code' => $responsible->login_code,
            'email' => $responsible->email,
            'phone' => $responsible->phone,
            'roles' => [],
            'active' => 1,
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('location_manager', [
            'location_id' => $location->id,
            'user_id' => $responsible->id,
            'active' => false,
            'is_primary' => false,
        ]);
        $this->assertNull($location->fresh()->manager_user_id);
        $this->assertFalse($responsible->fresh()->hasRole('sef-santier'));

        $activity = Activity::query()->where('description', 'Acces utilizator actualizat')->latest()->firstOrFail();
        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertSame(['B-01 - Baza Centrală'], $activity->properties->get('removed_location_responsibilities'));
    }

    public function test_location_rejects_an_inactive_or_ineligible_responsible_from_crafted_request(): void
    {
        $admin = $this->userWithRole('admin');
        $worker = $this->userWithRole('muncitor');
        $location = $this->location();

        $this->actingAs($admin)->put(route('locations.update', $location), [
            'type' => $location->type,
            'code' => $location->code,
            'name' => $location->name,
            'manager_user_ids' => [$worker->id],
            'active' => 1,
        ])->assertSessionHasErrors('manager_user_ids');

        $this->assertDatabaseMissing('location_manager', [
            'location_id' => $location->id,
            'user_id' => $worker->id,
            'active' => true,
        ]);
    }

    public function test_location_responsibility_change_appears_in_the_users_access_history(): void
    {
        $admin = $this->userWithRole('admin');
        $responsible = $this->userWithRole('gestionar-baza');
        $location = $this->location();

        $this->actingAs($admin)->put(route('locations.update', $location), [
            'type' => $location->type,
            'code' => $location->code,
            'name' => $location->name,
            'manager_user_ids' => [$responsible->id],
            'active' => 1,
        ])->assertRedirect(route('locations.index'));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'access',
            'description' => 'Responsabilitate de locație actualizată',
            'subject_type' => $responsible->getMorphClass(),
            'subject_id' => $responsible->id,
            'causer_id' => $admin->id,
        ]);
        $this->actingAs($admin)->get(route('access.show', $responsible))
            ->assertOk()
            ->assertSee('Responsabilitate de locație actualizată')
            ->assertSee('B-01 - Baza Centrală');
    }

    public function test_stale_location_pivot_alone_cannot_expose_a_reception(): void
    {
        $rolelessUser = User::factory()->create();
        $location = $this->location();
        $location->managers()->attach($rolelessUser, ['active' => true, 'is_primary' => true]);
        $reception = SupplierReception::create([
            'number' => 'REC-ACCESS-001',
            'location_id' => $location->id,
            'received_by' => $rolelessUser->id,
            'document_type' => 'aviz',
            'status' => 'posted',
        ]);

        $this->assertFalse(
            app(ReceptionAccessService::class)->visibleReceptions($rolelessUser)->whereKey($reception)->exists()
        );
    }

    public function test_manager_and_driver_role_combination_is_warned_and_not_assignable_as_driver(): void
    {
        $admin = $this->userWithRole('admin');
        $mixedUser = User::factory()->create(['name' => 'Manager Șofer']);
        $mixedUser->syncRoles(['manager', 'sofer']);

        $this->assertFalse(User::query()->assignableDrivers()->whereKey($mixedUser)->exists());

        $this->actingAs($admin)->get(route('access.show', $mixedUser))
            ->assertOk()
            ->assertSee('Rolul de management are prioritate');
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::findByName($role));

        return $user;
    }

    private function location(array $attributes = []): Location
    {
        return Location::create(array_merge([
            'type' => 'base',
            'code' => 'B-01',
            'name' => 'Baza Centrală',
            'active' => true,
        ], $attributes));
    }
}
