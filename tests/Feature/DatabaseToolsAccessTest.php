<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseToolsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_andrei_can_open_database_tools_and_other_users_cannot(): void
    {
        $andrei = User::factory()->create([
            'email' => 'andrei.dima@usm.ro',
            'login_code' => 'ANDREI',
        ]);
        $otherUser = User::factory()->create();

        $this->actingAs($andrei)
            ->get(route('system.database'))
            ->assertOk()
            ->assertSee('Baza de date si migrari');

        $this->actingAs($otherUser)
            ->get(route('system.database'))
            ->assertForbidden();
    }

    public function test_user_can_sign_in_with_email_in_the_login_code_field(): void
    {
        $user = User::factory()->create([
            'email' => 'andrei.dima@usm.ro',
            'login_code' => 'ANDREI',
            'password' => 'password',
        ]);

        $this->post(route('login.store'), [
            'login_code' => 'ANDREI.DIMA@USM.RO',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }
}
