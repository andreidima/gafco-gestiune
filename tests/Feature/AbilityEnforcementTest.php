<?php

namespace Tests\Feature;

use App\Models\AccessRoleProfile;
use App\Models\Location;
use App\Models\User;
use App\Services\AccessScopeService;
use App\Services\EffectiveAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route as RouteFacade;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AbilityEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_role_permission_controls_route_access_immediately(): void
    {
        $role = Role::findOrCreate('consultant-furnizori');
        $role->givePermissionTo('suppliers.view');
        AccessRoleProfile::create([
            'role_id' => $role->id,
            'label' => 'Consultant furnizori',
            'description' => 'Consultă furnizorii fără alte drepturi.',
            'workspace' => 'management',
            'requires_locations' => false,
        ]);
        $consultant = User::factory()->create();
        $consultant->assignRole($role);
        $withoutAccess = User::factory()->create();

        $this->actingAs($consultant)->get(route('suppliers.index'))->assertOk();
        $this->actingAs($withoutAccess)->get(route('suppliers.index'))->assertForbidden();

        $role->revokePermissionTo('suppliers.view');

        $this->actingAs($consultant->fresh())->get(route('suppliers.index'))->assertForbidden();
    }

    public function test_custom_local_role_is_restricted_to_its_assigned_locations(): void
    {
        $visible = $this->location('LOC-VIZ', 'Locație permisă');
        $hidden = $this->location('LOC-ASC', 'Locație ascunsă');
        $role = Role::findOrCreate('consultant-inventar-local');
        $role->givePermissionTo('inventory.view');
        AccessRoleProfile::create([
            'role_id' => $role->id,
            'label' => 'Consultant inventar local',
            'description' => 'Consultă inventarul locațiilor alocate.',
            'workspace' => 'management',
            'requires_locations' => true,
        ]);
        $user = User::factory()->create();
        $user->assignRole($role);
        $visible->managers()->attach($user, ['active' => true, 'is_primary' => true]);

        $this->assertSame('visible_records', $user->abilityScope('inventory.view'));
        $this->assertTrue($user->hasLocationAbility('inventory.view', $visible->id));
        $this->assertFalse($user->hasLocationAbility('inventory.view', $hidden->id));

        $this->actingAs($user)->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('LOC-VIZ')
            ->assertDontSee('LOC-ASC');
    }

    public function test_direct_exception_is_enforced_and_reported_with_the_same_scope(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findByName('suppliers.view'));

        $runtime = app(AccessScopeService::class);
        $decision = app(EffectiveAccessService::class)->decisions($user)
            ->firstWhere('ability', 'suppliers.view');

        $this->assertTrue($runtime->allows($user, 'suppliers.view'));
        $this->assertSame('global', $runtime->scope($user, 'suppliers.view'));
        $this->assertNotNull($decision);
        $this->assertTrue($decision->allowed);
        $this->assertSame($runtime->scope($user, 'suppliers.view'), $decision->scope);
        $this->assertSame('direct', $decision->sources[0]['type']);
        $this->actingAs($user)->get(route('suppliers.index'))->assertOk();
    }

    public function test_standard_role_permission_removal_is_enforced_without_role_name_fallback(): void
    {
        $manager = User::factory()->create();
        $role = Role::findByName('manager');
        $manager->assignRole($role);

        $this->actingAs($manager)->get(route('suppliers.index'))->assertOk();

        $role->revokePermissionTo('suppliers.view');

        $this->actingAs($manager->fresh())->get(route('suppliers.index'))->assertForbidden();
    }

    public function test_application_routes_no_longer_use_role_middleware_for_authorization(): void
    {
        $roleMiddleware = collect(RouteFacade::getRoutes()->getRoutes())
            ->flatMap(fn ($route) => $route->gatherMiddleware())
            ->filter(fn (string $middleware): bool => str_starts_with($middleware, 'role:'));

        $this->assertSame([], $roleMiddleware->values()->all());
    }

    public function test_custom_role_with_driver_capabilities_is_assignable_without_a_role_name_fallback(): void
    {
        $role = Role::findOrCreate('transportator-personalizat');
        $role->givePermissionTo(['tasks.view', 'tasks.respond']);
        AccessRoleProfile::create([
            'role_id' => $role->id,
            'label' => 'Transportator personalizat',
            'description' => 'Primește și execută sarcini alocate.',
            'workspace' => 'Transport',
            'requires_locations' => false,
        ]);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->assertSame('visible_records', $user->abilityScope('tasks.view'));
        $this->assertSame('assigned_records', $user->abilityScope('tasks.respond'));
        $this->assertTrue(User::query()->assignableDrivers()->whereKey($user)->exists());
    }

    private function location(string $code, string $name): Location
    {
        return Location::create([
            'type' => 'site',
            'code' => $code,
            'name' => $name,
            'active' => true,
        ]);
    }
}
