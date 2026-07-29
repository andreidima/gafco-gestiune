<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ImpersonationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_impersonation_permission_is_granted_only_to_administrative_roles(): void
    {
        $dispatcher = Role::findOrCreate('dispecer');

        $this->assertTrue(Role::findByName('admin')->hasPermissionTo(ImpersonationContext::PERMISSION));
        $this->assertTrue(Role::findByName('super-admin')->hasPermissionTo(ImpersonationContext::PERMISSION));
        $this->assertFalse($dispatcher->hasPermissionTo(ImpersonationContext::PERMISSION));
    }

    public function test_only_authorized_administrators_see_the_user_switcher(): void
    {
        $admin = $this->userWithRole('admin', ['login_code' => 'IMP-ADMIN']);
        $dispatcher = $this->userWithRole('dispecer', ['login_code' => 'IMP-DISPATCH']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Schimbă utilizatorul')
            ->assertSee(route('impersonation.users'), false);

        $this->actingAs($dispatcher)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Schimbă utilizatorul')
            ->assertDontSee(route('impersonation.users'), false);
    }

    public function test_selector_returns_only_active_non_privileged_eligible_users(): void
    {
        $admin = $this->userWithRole('admin', ['login_code' => 'IMP-SELECTOR-ADMIN']);
        $driver = $this->userWithRole('sofer', [
            'name' => 'Șofer eligibil',
            'login_code' => 'IMP-ELIGIBLE',
        ]);
        $this->userWithRole('sofer', [
            'name' => 'Șofer inactiv',
            'login_code' => 'IMP-INACTIVE',
            'active' => false,
        ]);
        $this->userWithRole('admin', [
            'name' => 'Alt administrator',
            'login_code' => 'IMP-OTHER-ADMIN',
        ]);
        $this->userWithRole('user', [
            'name' => 'Cont sensibil',
            'login_code' => 'IMP-SENSITIVE',
            'email' => 'andrei.dima@usm.ro',
        ]);
        $directlyAuthorized = $this->userWithRole('user', [
            'name' => 'Viitor utilizator autorizat',
            'login_code' => 'IMP-DIRECT',
        ]);
        $directlyAuthorized->givePermissionTo(ImpersonationContext::PERMISSION);

        $response = $this->actingAs($admin)
            ->getJson(route('impersonation.users', ['search' => 'eligibil']));

        $response->assertOk()
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.id', $driver->getKey())
            ->assertJsonPath('users.0.name', 'Șofer eligibil')
            ->assertJsonMissing(['name' => 'Șofer inactiv'])
            ->assertJsonMissing(['name' => 'Alt administrator'])
            ->assertJsonMissing(['name' => 'Cont sensibil'])
            ->assertJsonMissing(['name' => 'Viitor utilizator autorizat']);
    }

    public function test_admin_can_take_switch_and_leave_impersonated_accounts(): void
    {
        $admin = $this->userWithRole('admin', [
            'name' => 'Administrator real',
            'login_code' => 'IMP-TAKE-ADMIN',
        ]);
        $driver = $this->userWithRole('sofer', [
            'name' => 'Șofer ales',
            'login_code' => 'IMP-TAKE-DRIVER',
        ]);
        $worker = $this->userWithRole('muncitor', [
            'name' => 'Muncitor ales',
            'login_code' => 'IMP-TAKE-WORKER',
        ]);

        $this->actingAs($admin)
            ->post(route('impersonation.take', $driver))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('impersonation.impersonator_id', $admin->getKey())
            ->assertSessionHas('impersonation.target_id', $driver->getKey())
            ->assertSessionHas('impersonation.session_uuid');

        $this->assertAuthenticatedAs($driver);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Vizualizezi aplicația ca')
            ->assertSee('Șofer ales')
            ->assertSee('Administrator real')
            ->assertSee('Revino la contul meu');

        $this->post(route('impersonation.take', $worker))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('impersonation.impersonator_id', $admin->getKey())
            ->assertSessionHas('impersonation.target_id', $worker->getKey());

        $this->assertAuthenticatedAs($worker);

        $this->post(route('impersonation.stop'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionMissing('impersonation.impersonator_id')
            ->assertSessionMissing('impersonation.target_id')
            ->assertSessionMissing('impersonation.session_uuid');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_unauthorized_and_privileged_targets_are_rejected(): void
    {
        $admin = $this->userWithRole('admin', ['login_code' => 'IMP-GUARD-ADMIN']);
        $dispatcher = $this->userWithRole('dispecer', ['login_code' => 'IMP-GUARD-DISPATCH']);
        $driver = $this->userWithRole('sofer', ['login_code' => 'IMP-GUARD-DRIVER']);
        $inactive = $this->userWithRole('sofer', [
            'login_code' => 'IMP-GUARD-INACTIVE',
            'active' => false,
        ]);
        $otherAdmin = $this->userWithRole('admin', ['login_code' => 'IMP-GUARD-OTHER-ADMIN']);
        $sensitive = $this->userWithRole('user', [
            'login_code' => 'IMP-GUARD-SENSITIVE',
            'email' => 'andrei.dima@usm.ro',
        ]);

        $this->actingAs($dispatcher)
            ->post(route('impersonation.take', $driver))
            ->assertForbidden();

        foreach ([$admin, $inactive, $otherAdmin, $sensitive] as $target) {
            $this->actingAs($admin)
                ->post(route('impersonation.take', $target))
                ->assertForbidden();
        }
    }

    public function test_sensitive_administration_routes_reject_an_impersonated_session(): void
    {
        $admin = $this->userWithRole('admin', ['login_code' => 'IMP-SAFE-ADMIN']);
        $privilegedTarget = $this->userWithRole('super-admin', [
            'login_code' => 'IMP-SAFE-TARGET',
            'email' => 'andrei.dima@usm.ro',
        ]);

        $session = [
            'impersonation.impersonator_id' => $admin->getKey(),
            'impersonation.target_id' => $privilegedTarget->getKey(),
            'impersonation.session_uuid' => fake()->uuid(),
            'impersonation.started_at' => now()->toIso8601String(),
        ];

        $this->actingAs($privilegedTarget)
            ->withSession($session)
            ->get(route('users.index'))
            ->assertForbidden();

        $this->actingAs($privilegedTarget)
            ->withSession($session)
            ->get(route('system.database'))
            ->assertForbidden();
    }

    public function test_impersonated_writes_and_business_activity_keep_both_identities(): void
    {
        $admin = $this->userWithRole('admin', ['login_code' => 'IMP-AUDIT-ADMIN']);
        $manager = $this->userWithRole('manager', ['login_code' => 'IMP-AUDIT-MANAGER']);

        $this->actingAs($admin)->post(route('impersonation.take', $manager));
        $sessionUuid = session('impersonation.session_uuid');

        $this->post(route('notifications.read-all'))->assertRedirect();

        $requestAudit = Activity::query()
            ->where('log_name', 'impersonation')
            ->where('description', 'Acțiune efectuată prin impersonare')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($admin->getKey(), $requestAudit->causer_id);
        $this->assertSame($manager->getKey(), $requestAudit->getExtraProperty('effective_user_id'));
        $this->assertSame($sessionUuid, $requestAudit->getExtraProperty('impersonation_session_uuid'));
        $this->assertSame('notifications.read-all', $requestAudit->getExtraProperty('route'));
        $this->assertArrayNotHasKey('request_payload', $requestAudit->properties->all());

        activity()
            ->causedBy($manager)
            ->log('Activitate operațională de test');

        $businessActivity = Activity::query()
            ->where('description', 'Activitate operațională de test')
            ->firstOrFail();

        $this->assertSame($admin->getKey(), $businessActivity->causer_id);
        $this->assertSame($manager->getKey(), $businessActivity->getExtraProperty('effective_user_id'));
        $this->assertSame($sessionUuid, $businessActivity->batch_uuid);
    }

    public function test_revoked_permission_or_inactive_target_ends_impersonation_safely(): void
    {
        $admin = $this->userWithRole('admin', ['login_code' => 'IMP-REVOKE-ADMIN']);
        $driver = $this->userWithRole('sofer', ['login_code' => 'IMP-REVOKE-DRIVER']);

        $this->actingAs($admin)->post(route('impersonation.take', $driver));
        Role::findByName('admin')->revokePermissionTo(ImpersonationContext::PERMISSION);

        $this->get(route('dashboard'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionMissing('impersonation.impersonator_id');
        $this->assertAuthenticatedAs($admin);

        Role::findByName('admin')->givePermissionTo(ImpersonationContext::PERMISSION);
        $this->post(route('impersonation.take', $driver));
        $driver->update(['active' => false]);

        $this->get(route('dashboard'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionMissing('impersonation.impersonator_id');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_logout_during_impersonation_ends_the_entire_session(): void
    {
        $admin = $this->userWithRole('admin', ['login_code' => 'IMP-LOGOUT-ADMIN']);
        $driver = $this->userWithRole('sofer', ['login_code' => 'IMP-LOGOUT-DRIVER']);
        $driverRememberToken = $driver->remember_token;

        $this->actingAs($admin)->post(route('impersonation.take', $driver));
        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame($driverRememberToken, $driver->fresh()->remember_token);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'impersonation',
            'description' => 'Impersonare încheiată',
            'causer_id' => $admin->getKey(),
            'subject_id' => $driver->getKey(),
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
