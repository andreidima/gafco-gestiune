<?php

namespace Tests\Feature;

use App\Models\AccessRoleProfile;
use App\Models\User;
use App\Services\LocationResponsibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessRoleAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_protected_administrator_can_manage_roles_and_exceptions(): void
    {
        $admin = $this->userWithRole('admin');
        $protected = $this->protectedAdministrator();
        $target = $this->userWithRole('manager');

        $this->actingAs($admin)->get(route('access.roles.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('access.exceptions.edit', $target))->assertForbidden();

        $this->actingAs($protected)->get(route('access.roles.index'))
            ->assertOk()
            ->assertSee('Roluri și drepturi');
        $this->actingAs($protected)->get(route('access.exceptions.edit', $target))
            ->assertOk()
            ->assertSee('Excepții individuale');
    }

    public function test_protected_administrator_can_create_configure_and_audit_a_custom_role(): void
    {
        $protected = $this->protectedAdministrator();

        $this->actingAs($protected)->post(route('access.roles.store'), $this->metadata())
            ->assertRedirect();

        $role = Role::findByName('coordonator-logistica');
        $this->assertDatabaseHas('access_role_profiles', [
            'role_id' => $role->id,
            'label' => 'Coordonator logistică',
            'requires_locations' => true,
            'created_by' => $protected->id,
        ]);

        $preview = $this->actingAs($protected)->post(route('access.roles.preview', $role), $this->metadata([
            'permissions' => ['inventory.view', 'transfers.view'],
        ]));
        $preview->assertOk()
            ->assertSee('Drepturi adăugate')
            ->assertSee('inventory.view');

        $this->actingAs($protected)->put(route('access.roles.update', $role), [
            'confirmation_token' => $this->confirmationToken($preview),
        ])->assertRedirect(route('access.roles.index'));

        $this->assertSame(
            ['inventory.view', 'transfers.view'],
            $role->fresh()->permissions->pluck('name')->sort()->values()->all(),
        );
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'access',
            'description' => 'Drepturi rol actualizate',
            'causer_id' => $protected->id,
        ]);
    }

    public function test_reserved_permissions_and_the_protected_role_cannot_be_modified(): void
    {
        $protected = $this->protectedAdministrator();
        $role = $this->customRole();

        $this->actingAs($protected)->post(route('access.roles.preview', $role), $this->metadata([
            'permissions' => ['roles.manage'],
        ]))->assertSessionHasErrors('permissions.0');

        $this->actingAs($protected)->post(
            route('access.roles.preview', Role::findByName('super-admin')),
            ['permissions' => []],
        )->assertForbidden();
    }

    public function test_a_stale_role_preview_cannot_overwrite_a_concurrent_change(): void
    {
        $protected = $this->protectedAdministrator();
        $role = $this->customRole();
        $preview = $this->actingAs($protected)->post(route('access.roles.preview', $role), $this->metadata([
            'permissions' => ['inventory.view'],
        ]));
        $preview->assertOk();

        $role->givePermissionTo('catalog.view');

        $this->actingAs($protected)->put(route('access.roles.update', $role), [
            'confirmation_token' => $this->confirmationToken($preview),
        ])->assertSessionHasErrors('confirmation');

        $this->assertTrue($role->fresh()->hasPermissionTo('catalog.view'));
        $this->assertFalse($role->fresh()->hasPermissionTo('inventory.view'));
    }

    public function test_custom_roles_can_only_be_deleted_when_unused(): void
    {
        $protected = $this->protectedAdministrator();
        $role = $this->customRole();
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($protected)->delete(route('access.roles.destroy', $role))
            ->assertSessionHasErrors('role');
        $this->assertDatabaseHas('roles', ['id' => $role->id]);

        $user->removeRole($role);
        $this->actingAs($protected)->delete(route('access.roles.destroy', $role))
            ->assertRedirect(route('access.roles.index'));
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_custom_location_role_is_visible_assignable_and_warned_without_a_location(): void
    {
        $protected = $this->protectedAdministrator();
        $role = $this->customRole();
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->assertTrue(app(LocationResponsibilityService::class)->eligibleUsers()->contains($user));

        $this->actingAs($protected)->get(route('access.show', $user))
            ->assertOk()
            ->assertSee('Rol local fără nicio locație administrată');
        $this->actingAs($protected)->get(route('users.index'))
            ->assertOk()
            ->assertSee('Coordonator logistică');
    }

    public function test_direct_exception_requires_a_reason_and_is_explained_and_audited(): void
    {
        $protected = $this->protectedAdministrator();
        $target = $this->userWithRole('manager', ['name' => 'Utilizator excepție']);

        $this->actingAs($protected)->post(route('access.exceptions.preview', $target), [
            'permissions' => ['accounting.edit-operations'],
            'reason' => 'scurt',
        ])->assertSessionHasErrors('reason');

        $reason = 'Înlocuiește temporar contabilul în perioada concediului.';
        $preview = $this->actingAs($protected)->post(route('access.exceptions.preview', $target), [
            'permissions' => ['accounting.edit-operations'],
            'reason' => $reason,
        ]);
        $preview->assertOk()->assertSee('accounting.edit-operations');

        $this->actingAs($protected)->put(route('access.exceptions.update', $target), [
            'confirmation_token' => $this->confirmationToken($preview),
        ])->assertRedirect(route('access.show', $target));

        $permission = Permission::findByName('accounting.edit-operations');
        $this->assertTrue($target->fresh()->hasDirectPermission($permission));
        $this->assertDatabaseHas('access_permission_exceptions', [
            'user_id' => $target->id,
            'permission_id' => $permission->id,
            'reason' => $reason,
            'granted_by' => $protected->id,
        ]);
        $this->actingAs($protected)->get(route('access.show', $target))
            ->assertOk()
            ->assertSee($reason)
            ->assertSee('Excepție atribuită direct');
        $this->assertTrue(Activity::query()
            ->where('description', 'Excepții individuale actualizate')
            ->where('causer_id', $protected->id)
            ->exists());
    }

    public function test_reserved_direct_exception_and_stale_exception_preview_are_rejected(): void
    {
        $protected = $this->protectedAdministrator();
        $target = $this->userWithRole('manager');

        $this->actingAs($protected)->post(route('access.exceptions.preview', $target), [
            'permissions' => ['roles.manage'],
            'reason' => 'Încercare nepermisă de delegare a administrării.',
        ])->assertSessionHasErrors('permissions.0');

        $preview = $this->actingAs($protected)->post(route('access.exceptions.preview', $target), [
            'permissions' => ['accounting.edit-operations'],
            'reason' => 'Înlocuire temporară justificată pentru verificare.',
        ]);
        $preview->assertOk();
        $target->givePermissionTo('suppliers.view');

        $this->actingAs($protected)->put(route('access.exceptions.update', $target), [
            'confirmation_token' => $this->confirmationToken($preview),
        ])->assertSessionHasErrors('confirmation');

        $this->assertFalse($target->fresh()->hasDirectPermission('accounting.edit-operations'));

        $target->givePermissionTo('roles.manage');
        $withdrawal = $this->actingAs($protected)->post(route('access.exceptions.preview', $target), [
            'permissions' => ['suppliers.view'],
            'reason' => 'Retragere imediată a unei excepții rezervate moștenite.',
        ]);
        $withdrawal->assertOk()->assertSee('roles.manage');
        $this->actingAs($protected)->put(route('access.exceptions.update', $target), [
            'confirmation_token' => $this->confirmationToken($withdrawal),
        ])->assertRedirect(route('access.show', $target));
        $this->assertFalse($target->fresh()->hasDirectPermission('roles.manage'));
    }

    public function test_role_administration_content_migration_is_reversible(): void
    {
        $migration = require database_path('migrations/2026_08_05_000042_create_access_role_administration.php');
        $enforcement = require database_path('migrations/2026_08_05_000043_enforce_configured_access.php');

        $this->assertDatabaseHas('help_articles', [
            'slug' => 'administrarea-accesului',
            'current_revision' => 3,
        ]);
        $this->assertDatabaseHas('release_notes', [
            'slug' => '2026-08-05-roluri-si-exceptii-de-acces',
            'version' => '2026.08.05.2',
        ]);
        $this->assertDatabaseHas('release_notes', [
            'slug' => '2026-08-05-aplicarea-drepturilor-configurate',
            'version' => '2026.08.05.3',
        ]);

        $enforcement->down();

        $this->assertDatabaseHas('help_articles', [
            'slug' => 'administrarea-accesului',
            'current_revision' => 2,
        ]);

        $migration->down();

        $this->assertDatabaseHas('help_articles', [
            'slug' => 'administrarea-accesului',
            'current_revision' => 1,
        ]);
        $this->assertDatabaseMissing('release_notes', [
            'slug' => '2026-08-05-roluri-si-exceptii-de-acces',
        ]);
        $this->assertFalse(AccessRoleProfile::query()->getConnection()->getSchemaBuilder()->hasTable('access_role_profiles'));

        $migration->up();
        $enforcement->up();
    }

    private function protectedAdministrator(): User
    {
        return $this->userWithRole('super-admin', [
            'email' => config('roles.protected_admin_email'),
            'login_code' => 'ADMIN-PROTEJAT',
        ]);
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::findByName($role));

        return $user;
    }

    private function customRole(): Role
    {
        $role = Role::create(['name' => 'coordonator-logistica', 'guard_name' => 'web']);
        AccessRoleProfile::create([
            'role_id' => $role->id,
            'label' => 'Coordonator logistică',
            'description' => 'Coordonează operațiunile logistice.',
            'workspace' => 'Logistică',
            'requires_locations' => true,
        ]);

        return $role;
    }

    private function metadata(array $overrides = []): array
    {
        return array_merge([
            'name' => 'coordonator-logistica',
            'label' => 'Coordonator logistică',
            'description' => 'Coordonează operațiunile logistice.',
            'workspace' => 'Logistică',
            'requires_locations' => 1,
        ], $overrides);
    }

    private function confirmationToken(TestResponse $response): string
    {
        preg_match('/name="confirmation_token" value="([^"]+)"/', $response->getContent(), $matches);
        $this->assertArrayHasKey(1, $matches, 'Tokenul de confirmare nu a fost randat.');

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
