<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProtectedSuperAdministratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_administrator_cannot_see_or_manage_the_protected_account_or_role(): void
    {
        $protected = $this->protectedAdministrator();
        $admin = $this->userWithRole('admin', ['name' => 'Administrator obișnuit']);

        $this->actingAs($admin)->get(route('users.index'))
            ->assertOk()
            ->assertDontSee($protected->name)
            ->assertDontSee($protected->email)
            ->assertDontSee('Super administrator');

        $this->actingAs($admin)->get(route('users.create'))
            ->assertOk()
            ->assertDontSee('Super administrator');

        $this->actingAs($admin)->get(route('users.edit', $protected))->assertForbidden();
        $this->actingAs($admin)->put(route('users.update', $protected), [
            'name' => 'Încercare',
            'login_code' => $protected->login_code,
            'email' => $protected->email,
            'active' => 1,
        ])->assertForbidden();
    }

    public function test_super_administrator_role_cannot_be_assigned_through_a_crafted_request(): void
    {
        $admin = $this->userWithRole('admin');
        Role::findOrCreate('super-admin');

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Cont nou',
            'login_code' => 'CONT-NOU',
            'email' => 'cont.nou@example.com',
            'password' => 'password',
            'roles' => ['super-admin'],
            'active' => 1,
        ])->assertSessionHasErrors('roles.0');

        $this->assertDatabaseMissing('users', ['login_code' => 'CONT-NOU']);
    }

    public function test_protected_administrator_can_update_own_visible_fields_without_losing_protection(): void
    {
        Role::findOrCreate('admin');
        $protected = $this->protectedAdministrator();

        $this->actingAs($protected)->get(route('users.edit', $protected))
            ->assertOk()
            ->assertSee('readonly', false)
            ->assertDontSee('Super administrator');

        $this->actingAs($protected)->put(route('users.update', $protected), [
            'name' => 'Andrei actualizat',
            'login_code' => 'andrei-admin',
            'email' => 'schimbat@example.com',
            'roles' => ['admin'],
            'active' => 1,
        ])->assertRedirect(route('users.index'));

        $protected->refresh();
        $this->assertSame('Andrei actualizat', $protected->name);
        $this->assertSame('ANDREI-ADMIN', $protected->login_code);
        $this->assertSame(config('roles.protected_admin_email'), $protected->email);
        $this->assertTrue($protected->hasRole('super-admin'));
        $this->assertTrue($protected->hasRole('admin'));
    }

    public function test_data_migration_normalizes_location_codes_and_removes_other_super_administrators(): void
    {
        $protected = $this->protectedAdministrator();
        $other = $this->userWithRole('super-admin');
        $location = Location::create([
            'type' => 'base',
            'code' => '  b-mic  ',
            'name' => 'Bază test',
            'active' => true,
        ]);
        $migration = require database_path(
            'migrations/2026_07_29_000004_normalize_location_codes_and_protect_super_administrator.php'
        );

        $migration->up();

        $this->assertSame('B-MIC', $location->refresh()->code);
        $this->assertTrue($protected->fresh()->hasRole('super-admin'));
        $this->assertFalse($other->fresh()->hasRole('super-admin'));
    }

    public function test_location_codes_are_uppercase_on_create_and_update(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post(route('locations.store'), [
            'type' => 'base',
            'code' => '  b-nou  ',
            'name' => 'Bază nouă',
            'active' => 1,
        ])->assertRedirect(route('locations.index'));

        $location = Location::query()->where('code', 'B-NOU')->sole();

        $this->actingAs($admin)->put(route('locations.update', $location), [
            'type' => 'site',
            'code' => '  s-nou  ',
            'name' => 'Șantier nou',
            'active' => 1,
        ])->assertRedirect(route('locations.index'));

        $this->assertSame('S-NOU', $location->refresh()->code);
    }

    private function protectedAdministrator(): User
    {
        return $this->userWithRole('super-admin', [
            'name' => 'Andrei Dima',
            'email' => config('roles.protected_admin_email'),
            'login_code' => 'ANDREI',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRole(string $role, array $attributes = []): User
    {
        Role::findOrCreate($role);
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
