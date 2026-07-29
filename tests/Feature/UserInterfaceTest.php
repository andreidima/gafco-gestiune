<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_names_are_displayed_in_romanian_on_user_pages(): void
    {
        foreach (array_keys(config('roles.labels')) as $roleName) {
            Role::findOrCreate($roleName);
        }

        $admin = User::factory()->create(['login_code' => 'ADMIN-ROLURI']);
        $admin->assignRole('super-admin');

        $this->actingAs($admin)
            ->get(route('users.edit', $admin))
            ->assertOk()
            ->assertSee('Gestionar de bază')
            ->assertSee('Șef de șantier')
            ->assertSee('Șofer')
            ->assertSee('Utilizator');

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Gestionar de bază')
            ->assertSee('Șef de șantier')
            ->assertSee('Șofer')
            ->assertSee('Utilizator');
    }

    public function test_paginated_result_summary_is_displayed_in_romanian(): void
    {
        Role::findOrCreate('super-admin');

        $admin = User::factory()->create(['login_code' => 'ADMIN-PAGINARE']);
        $admin->assignRole('super-admin');
        User::factory()->count(20)->create();

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSeeText('Se afișează')
            ->assertSeeText('până la')
            ->assertSeeText('din')
            ->assertSeeText('21')
            ->assertSeeText('rezultate')
            ->assertDontSeeText('Showing')
            ->assertDontSeeText('results');
    }
}
