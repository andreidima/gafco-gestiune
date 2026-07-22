<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Task;
use App\Models\Transfer;
use App\Models\TransferApproval;
use App\Models\User;
use App\Services\TaskWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DispatchDashboardUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_dashboard_and_navigation_do_not_expose_other_drivers(): void
    {
        Role::findOrCreate('sef-santier');
        Role::findOrCreate('sofer');
        $manager = User::factory()->create(['login_code' => 'UX-MANAGER']);
        $manager->assignRole('sef-santier');
        $driver = User::factory()->create(['name' => 'Sofer propriu', 'login_code' => 'UX-DRIVER']);
        $driver->assignRole('sofer');
        $otherDriver = User::factory()->create(['name' => 'Sofer secret', 'login_code' => 'UX-OTHER']);
        $otherDriver->assignRole('sofer');

        $ownTask = $this->task($manager, 'TSK-UX-OWN', 'Sarcina proprie');
        $otherTask = $this->task($manager, 'TSK-UX-OTHER', 'Sarcina secreta');
        $workflow = app(TaskWorkflowService::class);
        $workflow->assign($ownTask, $driver, $manager);
        $workflow->assign($otherTask, $otherDriver, $manager);

        $this->actingAs($driver)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Sarcina proprie')
            ->assertDontSee('Sarcina secreta')
            ->assertDontSee('Sofer secret')
            ->assertDontSee('Situatie soferi')
            ->assertDontSee('Sef santier');
    }

    public function test_dispatch_prioritizes_free_drivers_and_shows_operational_context(): void
    {
        Role::findOrCreate('sef-santier');
        Role::findOrCreate('sofer');
        $manager = User::factory()->create(['login_code' => 'UX-DISPATCHER']);
        $manager->assignRole('sef-santier');
        $freeDriver = User::factory()->create(['name' => 'A Sofer liber', 'login_code' => 'UX-FREE']);
        $freeDriver->assignRole('sofer');
        $busyDriver = User::factory()->create(['name' => 'B Sofer ocupat', 'login_code' => 'UX-BUSY']);
        $busyDriver->assignRole('sofer');
        $source = Location::create(['code' => 'B-UX', 'name' => 'Baza UX', 'type' => 'base', 'active' => true]);
        $destination = Location::create(['code' => 'S-UX', 'name' => 'Santier UX', 'type' => 'site', 'active' => true]);
        $manager->managedLocations()->attach($source, ['active' => true, 'is_primary' => true]);
        $task = Task::create([
            'number' => 'TSK-UX-BUSY',
            'title' => 'Cursa in lucru',
            'category' => 'transport',
            'created_by' => $manager->id,
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'status' => 'unassigned',
            'priority' => 'normal',
            'manager_deadline' => now()->addHours(8),
        ]);
        $workflow = app(TaskWorkflowService::class);
        $assignment = $workflow->assign($task, $busyDriver, $manager);
        $workflow->respond($assignment, $busyDriver, 'accepted', null);
        $this->task($manager, 'TSK-UX-OPEN', 'Sarcina de alocat');

        $this->actingAs($manager)->get(route('tasks.dispatch'))
            ->assertOk()
            ->assertSeeInOrder(['A Sofer liber', 'B Sofer ocupat'])
            ->assertSee('Liber acum')
            ->assertSee('Cursa in lucru')
            ->assertSee('B-UX')
            ->assertSee('S-UX')
            ->assertSee('Alege soferul');
    }

    public function test_manager_dashboard_leads_with_actionable_queues(): void
    {
        Role::findOrCreate('sef-santier');
        Role::findOrCreate('sofer');
        $manager = User::factory()->create(['login_code' => 'UX-QUEUE']);
        $manager->assignRole('sef-santier');
        $driver = User::factory()->create(['login_code' => 'UX-AVAILABLE']);
        $driver->assignRole('sofer');
        $this->task($manager, 'TSK-UX-QUEUE', 'Sarcina de alocat');

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Necesita aprobarea mea', 'Sarcini intarziate', 'Sarcini nealocate', 'Soferi disponibili'])
            ->assertSee(route('tasks.dispatch').'#unassigned-tasks', false);
    }

    public function test_driver_cannot_open_site_manager_workspace(): void
    {
        Role::findOrCreate('sofer');
        $driver = User::factory()->create(['login_code' => 'UX-NO-MANAGER']);
        $driver->assignRole('sofer');

        $this->actingAs($driver)->get(route('field.site-manager'))->assertForbidden();
    }

    public function test_manager_workspace_renders_actionable_sections(): void
    {
        Role::findOrCreate('sef-santier');
        $manager = User::factory()->create(['login_code' => 'UX-SITE-MANAGER']);
        $manager->assignRole('sef-santier');
        $location = Location::create(['code' => 'S-UX-M', 'name' => 'Santier manager', 'type' => 'site', 'active' => true]);
        $base = Location::create(['code' => 'B-UX-M', 'name' => 'Baza manager', 'type' => 'base', 'active' => true]);
        $manager->managedLocations()->attach($location, ['active' => true, 'is_primary' => true]);
        $transfer = Transfer::create([
            'number' => 'TR-UX-MANAGER',
            'type' => 'base_to_site',
            'purpose' => 'transfer',
            'revision' => 1,
            'status' => 'pending_approval',
            'source_location_id' => $base->id,
            'destination_location_id' => $location->id,
            'requested_by' => $manager->id,
        ]);
        TransferApproval::create([
            'transfer_id' => $transfer->id,
            'revision' => 1,
            'scope' => 'destination_manager',
            'location_id' => $location->id,
            'status' => 'pending',
        ]);

        $this->actingAs($manager)->get(route('field.site-manager'))
            ->assertOk()
            ->assertSee('Necesita decizia mea')
            ->assertSee('TR-UX-MANAGER')
            ->assertSee('Transferuri active')
            ->assertSee('Inregistreaza consum')
            ->assertSee('Initiaza retur');
    }

    private function task(User $manager, string $number, string $title): Task
    {
        return Task::create([
            'number' => $number,
            'title' => $title,
            'category' => 'general',
            'created_by' => $manager->id,
            'status' => 'unassigned',
            'priority' => 'normal',
        ]);
    }
}
