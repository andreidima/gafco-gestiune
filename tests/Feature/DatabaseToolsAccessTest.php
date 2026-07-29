<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
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
        Role::findOrCreate('super-admin');
        $andrei->assignRole('super-admin');
        $otherUser = User::factory()->create();

        $this->actingAs($andrei)
            ->get(route('system.database'))
            ->assertOk()
            ->assertSee('Setari')
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

    public function test_login_page_displays_the_shared_verification_account(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('admin@example.com')
            ->assertSee('password');
    }

    public function test_database_tools_remain_available_before_reception_tables_are_migrated(): void
    {
        $andrei = User::factory()->create([
            'email' => 'andrei.dima@usm.ro',
            'login_code' => 'ANDREI',
        ]);
        Role::findOrCreate('super-admin');
        $andrei->assignRole('super-admin');

        Schema::dropIfExists('reception_documents');
        Schema::dropIfExists('reception_intakes');

        $this->actingAs($andrei)
            ->get(route('system.database'))
            ->assertOk()
            ->assertDontSee('Documente de procesat');
    }
}
