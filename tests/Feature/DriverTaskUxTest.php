<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\TaskWorkflowService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DriverTaskUxTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_driver_sees_the_next_action_first_and_a_one_hour_default_estimate(): void
    {
        Carbon::setTestNow('2026-07-29 10:15:00');
        [$manager, $driver] = $this->users();
        [$task] = $this->acceptedTask($manager, $driver, 'TSK-DRIVER-NEXT');

        $response = $this->actingAs($driver)->get(route('tasks.show', $task));

        $response->assertOk()
            ->assertSeeInOrder(['Acțiunea următoare', 'Detalii operationale'])
            ->assertSee('Estimează și pornește sarcina')
            ->assertSee('Ora este completată automat cu o oră în avans.')
            ->assertSee('value="2026-07-29T11:15"', false)
            ->assertSee('Observație')
            ->assertSee('Pornește sarcina');

        $this->assertStringNotContainsString(
            'name="driver_estimate_note" class="form-control" rows="2" required',
            $response->getContent(),
        );

        Carbon::setTestNow();
    }

    public function test_driver_task_list_has_status_tabs_and_compact_action_cards(): void
    {
        [$manager, $driver] = $this->users();
        $workflow = app(TaskWorkflowService::class);

        $pending = $this->task($manager, 'TSK-DRIVER-PENDING');
        $workflow->assign($pending, $driver, $manager);

        $this->acceptedTask($manager, $driver, 'TSK-DRIVER-ACCEPTED');
        [$inProgress] = $this->acceptedTask($manager, $driver, 'TSK-DRIVER-PROGRESS');
        $workflow->transition($inProgress, $driver, 'in_progress');

        [$completed] = $this->acceptedTask($manager, $driver, 'TSK-DRIVER-COMPLETED');
        $workflow->transition($completed, $driver, 'in_progress');
        $workflow->transition($completed, $driver, 'completed');

        $response = $this->actingAs($driver)->get(route('tasks.index', ['filters_reset' => 1]));

        $response->assertOk()
            ->assertSeeInOrder(['Toate', 'De răspuns', 'De pornit', 'În lucru', 'Finalizate'])
            ->assertSee('driver-task-mobile-card', false)
            ->assertSee('Răspunde')
            ->assertSee('Estimează și pornește')
            ->assertSee('Continuă sarcina')
            ->assertSee('Vezi sarcina');
        $this->assertStringNotContainsString(
            '<span class="resource-filter-label">Alocare</span>',
            $response->getContent(),
        );
    }

    public function test_latest_estimate_is_corrected_for_five_minutes_then_new_history_is_created(): void
    {
        Carbon::setTestNow('2026-07-29 08:00:00');
        [$manager, $driver] = $this->users();
        [$task, $assignment] = $this->acceptedTask($manager, $driver, 'TSK-DRIVER-ESTIMATE');

        $firstEstimate = Carbon::parse('2026-07-29 09:00:00');
        $this->actingAs($driver)->post(route('task-assignments.estimate', $assignment), [
            'driver_estimate_at' => $firstEstimate->format('Y-m-d H:i:s'),
        ])->assertRedirect()
            ->assertSessionHas('status', 'Estimarea a fost salvata. Sarcina nu este inca pornita.');

        $this->assertDatabaseCount('task_assignment_estimates', 1);
        $history = $assignment->estimates()->sole();
        $this->assertNull($history->note);
        $this->assertTrue($history->estimated_at->equalTo($firstEstimate));
        $this->assertTrue($history->created_at->equalTo(Carbon::parse('2026-07-29 08:00:00')));
        $this->assertTrue($history->correctable_until->equalTo(Carbon::parse('2026-07-29 08:05:00')));

        Carbon::setTestNow('2026-07-29 08:04:59');
        $correctedEstimate = Carbon::parse('2026-07-29 09:30:00');
        $this->actingAs($driver)->post(route('task-assignments.estimate', $assignment), [
            'driver_estimate_at' => $correctedEstimate->format('Y-m-d H:i:s'),
            'driver_estimate_note' => 'Trafic mai intens.',
        ])->assertRedirect();

        $this->assertDatabaseCount('task_assignment_estimates', 1);
        $history->refresh();
        $this->assertTrue($history->estimated_at->equalTo($correctedEstimate));
        $this->assertTrue($history->created_at->equalTo(Carbon::parse('2026-07-29 08:00:00')));

        Carbon::setTestNow('2026-07-29 08:05:01');
        $newEstimate = Carbon::parse('2026-07-29 10:00:00');
        $this->actingAs($driver)->post(route('task-assignments.estimate', $assignment), [
            'driver_estimate_at' => $newEstimate->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $this->assertDatabaseCount('task_assignment_estimates', 2);
        $this->assertTrue($assignment->fresh()->driver_estimate_at->equalTo($newEstimate));
        $this->assertSame(
            [$correctedEstimate->format('Y-m-d H:i:s'), $newEstimate->format('Y-m-d H:i:s')],
            $assignment->estimates()->oldest('id')->pluck('estimated_at')->map->format('Y-m-d H:i:s')->all(),
        );
        $this->assertSame(3, $task->comments()->where('type', 'estimate')->count());

        Carbon::setTestNow();
    }

    public function test_success_flash_is_marked_for_temporary_display(): void
    {
        [, $driver] = $this->users();

        $this->actingAs($driver)
            ->withSession(['status' => 'Estimarea a fost salvata.'])
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('data-flash-message', false)
            ->assertSee('data-flash-timeout="4500"', false);
    }

    public function test_completed_driver_task_redirects_to_the_unfiltered_task_list(): void
    {
        [$manager, $driver] = $this->users();
        [$task] = $this->acceptedTask($manager, $driver, 'TSK-DRIVER-REDIRECT');
        app(TaskWorkflowService::class)->transition($task, $driver, 'in_progress');

        $this->actingAs($driver)
            ->from(route('tasks.show', $task).'?status=in_progress')
            ->post(route('tasks.transition', $task), ['status' => 'completed'])
            ->assertRedirect(route('tasks.index'))
            ->assertSessionHas('status', 'Sarcina a fost finalizată.');
    }

    public function test_existing_estimate_uses_a_compact_correction_or_new_estimate_summary(): void
    {
        Carbon::setTestNow('2026-07-30 08:00:00');
        [$manager, $driver] = $this->users();
        [$task, $assignment] = $this->acceptedTask($manager, $driver, 'TSK-DRIVER-SUMMARY');
        app(TaskWorkflowService::class)->updateEstimate($assignment, $driver, '2026-07-30 09:00:00', 'Trafic aglomerat pe centură.');

        $this->actingAs($driver)->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('driver-estimate-summary', false)
            ->assertSee('30.07.2026 09:00')
            ->assertSee('Trafic aglomerat pe centură.')
            ->assertSee('Corectează')
            ->assertSee('collapse', false);

        Carbon::setTestNow('2026-07-30 08:06:00');

        $this->actingAs($driver)->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('Estimare nouă')
            ->assertDontSee('collapse show', false);
    }

    private function users(): array
    {
        Role::findOrCreate('sef-santier');
        Role::findOrCreate('sofer');
        $manager = User::factory()->create(['login_code' => 'DRIVER-UX-MANAGER']);
        $manager->assignRole('sef-santier');
        $driver = User::factory()->create(['login_code' => 'DRIVER-UX-DRIVER']);
        $driver->assignRole('sofer');

        return [$manager, $driver];
    }

    private function task(User $manager, string $number): Task
    {
        return Task::create([
            'number' => $number,
            'title' => 'Transport pentru testul de mobil',
            'category' => 'transport',
            'created_by' => $manager->id,
            'status' => 'unassigned',
            'priority' => 'normal',
            'manager_deadline' => now()->addHours(6),
        ]);
    }

    private function acceptedTask(User $manager, User $driver, string $number): array
    {
        $task = $this->task($manager, $number);
        $workflow = app(TaskWorkflowService::class);
        $assignment = $workflow->assign($task, $driver, $manager);
        $workflow->respond($assignment, $driver, 'accepted', null);

        return [$task->fresh(), TaskAssignment::findOrFail($assignment->id)];
    }
}
