<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Task;
use App\Models\Transfer;
use App\Models\TransferApproval;
use App\Models\User;
use App\Services\TaskWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TaskWorkflowSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('sef-santier');
        Role::findOrCreate('sofer');
    }

    public function test_repeated_replacement_proposals_keep_the_original_incumbent_chain(): void
    {
        $manager = $this->userWithRole('sef-santier');
        $incumbent = $this->userWithRole('sofer');
        $firstCandidate = $this->userWithRole('sofer');
        $secondCandidate = $this->userWithRole('sofer');
        $task = $this->task($manager);
        $workflow = app(TaskWorkflowService::class);

        $incumbentAssignment = $workflow->assign($task, $incumbent, $manager);
        $workflow->respond($incumbentAssignment, $incumbent, 'accepted', null);
        $supersededCandidate = $workflow->assign($task, $firstCandidate, $manager);
        $latestCandidate = $workflow->assign($task, $secondCandidate, $manager);

        $this->assertSame('accepted', $task->fresh()->status);
        $this->assertSame('accepted', $incumbentAssignment->fresh()->status);
        $this->assertSame('replaced', $supersededCandidate->fresh()->status);
        $this->assertSame($incumbentAssignment->id, $latestCandidate->replaced_assignment_id);

        $workflow->respond($latestCandidate, $secondCandidate, 'rejected', 'Nu pot prelua sarcina.');

        $current = $task->fresh()->currentAssignment;
        $this->assertSame($incumbent->id, $current->driver_id);
        $this->assertSame('accepted', $current->status);
        $this->assertSame('accepted', $task->fresh()->status);
    }

    public function test_incumbent_keeps_visibility_and_can_work_until_replacement_accepts(): void
    {
        $manager = $this->userWithRole('sef-santier');
        $incumbent = $this->userWithRole('sofer');
        $candidate = $this->userWithRole('sofer');
        $task = $this->task($manager);
        $workflow = app(TaskWorkflowService::class);

        $incumbentAssignment = $workflow->assign($task, $incumbent, $manager);
        $workflow->respond($incumbentAssignment, $incumbent, 'accepted', null);
        $candidateAssignment = $workflow->assign($task, $candidate, $manager);

        $this->actingAs($incumbent)->get(route('tasks.index'))
            ->assertOk()
            ->assertViewHas('tasks', fn ($tasks) => $tasks->getCollection()->contains('id', $task->id));
        $this->actingAs($incumbent)->get(route('tasks.show', $task))->assertOk();
        $this->actingAs($incumbent)->post(route('tasks.transition', $task), [
            'status' => 'in_progress',
        ])->assertRedirect();
        $this->assertSame('in_progress', $task->fresh()->status);

        $this->actingAs($candidate)->post(route('task-assignments.respond', $candidateAssignment), [
            'decision' => 'accepted',
        ])->assertRedirect();

        $this->assertSame('in_progress', $task->fresh()->status);
        $this->assertSame('replaced', $incumbentAssignment->fresh()->status);
        $this->actingAs($incumbent)->get(route('tasks.show', $task))->assertForbidden();
        $this->actingAs($candidate)->get(route('tasks.show', $task))->assertOk();
    }

    public function test_task_state_machine_prevents_skipping_and_reopening(): void
    {
        $manager = $this->userWithRole('sef-santier');
        $driver = $this->userWithRole('sofer');
        $task = $this->task($manager);
        $workflow = app(TaskWorkflowService::class);
        $assignment = $workflow->assign($task, $driver, $manager);
        $workflow->respond($assignment, $driver, 'accepted', null);

        $this->actingAs($driver)->post(route('tasks.transition', $task), [
            'status' => 'completed',
        ])->assertSessionHasErrors('status');
        $this->assertSame('accepted', $task->fresh()->status);

        $this->actingAs($driver)->post(route('tasks.transition', $task), [
            'status' => 'in_progress',
        ])->assertRedirect();
        $this->actingAs($driver)->post(route('tasks.transition', $task), [
            'status' => 'completed',
        ])->assertRedirect();
        $this->assertSame('completed', $task->fresh()->status);

        $this->actingAs($driver)->post(route('tasks.transition', $task), [
            'status' => 'in_progress',
        ])->assertForbidden();
        $this->assertSame('completed', $task->fresh()->status);

        $this->actingAs($driver)->post(route('task-assignments.estimate', $assignment), [
            'driver_estimate_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'driver_estimate_note' => 'Estimare dupa inchidere',
        ])->assertForbidden();
        $this->actingAs($driver)->post(route('task-assignments.request-reassignment', $assignment), [
            'notes' => 'Realocare dupa inchidere',
        ])->assertForbidden();

        $this->actingAs($manager)->post(route('tasks.transition', $task), [
            'status' => 'archived',
        ])->assertRedirect();
        $this->assertSame('archived', $task->fresh()->status);

        $this->actingAs($manager)->post(route('tasks.transition', $task), [
            'status' => 'cancelled',
        ])->assertForbidden();
        $this->assertSame('archived', $task->fresh()->status);
    }

    public function test_direct_task_edits_respect_location_scope_and_immutable_workflow_history(): void
    {
        $manager = $this->userWithRole('sef-santier');
        $otherManager = $this->userWithRole('sef-santier');
        $managed = Location::create(['code' => 'TASK-MANAGED', 'name' => 'Gestionata', 'type' => 'site', 'active' => true]);
        $foreignOne = Location::create(['code' => 'TASK-FOREIGN-1', 'name' => 'Straina 1', 'type' => 'site', 'active' => true]);
        $foreignTwo = Location::create(['code' => 'TASK-FOREIGN-2', 'name' => 'Straina 2', 'type' => 'base', 'active' => true]);
        $managed->managers()->attach($manager->id, ['active' => true, 'is_primary' => true]);
        $foreignOne->managers()->attach($otherManager->id, ['active' => true, 'is_primary' => true]);
        $foreignTwo->managers()->attach($otherManager->id, ['active' => true, 'is_primary' => true]);
        $this->actingAs($manager)->post(route('tasks.store'), [
            'title' => 'Creare in locatii straine',
            'category' => 'general',
            'source_location_id' => $foreignOne->id,
            'destination_location_id' => $foreignTwo->id,
            'priority' => 'normal',
        ])->assertForbidden();
        $this->assertDatabaseMissing('tasks', ['title' => 'Creare in locatii straine']);
        $task = $this->task($manager);
        $task->update(['source_location_id' => $managed->id]);

        $payload = [
            'title' => 'Mutare nepermisa',
            'category' => 'general',
            'source_location_id' => $foreignOne->id,
            'destination_location_id' => $foreignTwo->id,
            'priority' => 'normal',
        ];
        $this->actingAs($manager)->put(route('tasks.update', $task), $payload)->assertForbidden();
        $this->assertSame($managed->id, $task->fresh()->source_location_id);

        $task->update(['status' => 'completed']);
        $this->actingAs($manager)->put(route('tasks.update', $task), [
            ...$payload,
            'source_location_id' => $managed->id,
            'destination_location_id' => null,
        ])->assertForbidden();

        $transfer = Transfer::create([
            'number' => 'TASK-LINKED-TRANSFER',
            'type' => 'site_to_base',
            'purpose' => 'transfer',
            'revision' => 1,
            'status' => 'pending_approval',
            'source_location_id' => $managed->id,
            'destination_location_id' => $foreignTwo->id,
            'requested_by' => $manager->id,
        ]);
        $transferTask = $this->task($manager, $transfer);
        $transferTask->update(['source_location_id' => $managed->id, 'destination_location_id' => $foreignTwo->id]);
        $this->actingAs($manager)->put(route('tasks.update', $transferTask), [
            ...$payload,
            'source_location_id' => $managed->id,
        ])->assertForbidden();
        $this->assertSame('Sarcina securizata', $transferTask->fresh()->title);
    }

    public function test_transfer_driver_approval_is_demoted_and_restored_around_replacement(): void
    {
        $manager = $this->userWithRole('sef-santier');
        $incumbent = $this->userWithRole('sofer');
        $candidate = $this->userWithRole('sofer');
        $source = Location::create(['code' => 'BASE-SEC', 'name' => 'Baza securitate', 'type' => 'base', 'active' => true]);
        $destination = Location::create(['code' => 'SITE-SEC', 'name' => 'Santier securitate', 'type' => 'site', 'active' => true]);
        $transfer = Transfer::create([
            'number' => 'TR-SEC-1',
            'type' => 'base_to_site',
            'purpose' => 'transfer',
            'revision' => 1,
            'status' => 'approved',
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'requested_by' => $manager->id,
            'driver_id' => $incumbent->id,
            'requested_at' => now(),
            'approved_by' => $manager->id,
            'approved_at' => now(),
        ]);
        $task = $this->task($manager, $transfer);
        $task->update(['status' => 'accepted']);
        $incumbentAssignment = $task->assignments()->create([
            'driver_id' => $incumbent->id,
            'assigned_by' => $manager->id,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
        TransferApproval::create([
            'transfer_id' => $transfer->id, 'revision' => 1, 'scope' => 'source_manager',
            'location_id' => $source->id, 'decided_by_user_id' => $manager->id,
            'status' => 'approved', 'decided_at' => now(),
        ]);
        TransferApproval::create([
            'transfer_id' => $transfer->id, 'revision' => 1, 'scope' => 'destination_manager',
            'location_id' => $destination->id, 'decided_by_user_id' => $manager->id,
            'status' => 'approved', 'decided_at' => now(),
        ]);
        TransferApproval::create([
            'transfer_id' => $transfer->id, 'revision' => 1, 'scope' => 'driver',
            'expected_user_id' => $incumbent->id, 'decided_by_user_id' => $incumbent->id,
            'status' => 'approved', 'decided_at' => now(),
        ]);
        $workflow = app(TaskWorkflowService::class);

        $candidateAssignment = $workflow->assign($task, $candidate, $manager);

        $this->assertSame('pending_approval', $transfer->fresh()->status);
        $this->assertDatabaseHas('transfer_approvals', [
            'transfer_id' => $transfer->id,
            'revision' => 1,
            'scope' => 'driver',
            'expected_user_id' => $candidate->id,
            'status' => 'pending',
        ]);

        $workflow->respond($candidateAssignment, $candidate, 'rejected', 'Nu pot prelua transferul.');

        $this->assertSame('approved', $transfer->fresh()->status);
        $this->assertSame('accepted', $incumbentAssignment->fresh()->status);
        $this->assertDatabaseHas('transfer_approvals', [
            'transfer_id' => $transfer->id,
            'revision' => 1,
            'scope' => 'driver',
            'expected_user_id' => $incumbent->id,
            'status' => 'approved',
        ]);

        $this->actingAs($manager)->post(route('tasks.transition', $task), [
            'status' => 'cancelled',
        ])->assertSessionHasErrors('status');
        $this->assertSame('accepted', $task->fresh()->status);
        $this->assertSame('approved', $transfer->fresh()->status);
    }

    public function test_assignment_service_rejects_non_drivers_and_inactive_drivers(): void
    {
        $manager = $this->userWithRole('sef-santier');
        $nonDriver = $this->userWithRole('sef-santier');
        $inactiveDriver = $this->userWithRole('sofer', active: false);
        $task = $this->task($manager);
        $workflow = app(TaskWorkflowService::class);

        foreach ([$nonDriver, $inactiveDriver] as $invalidDriver) {
            try {
                $workflow->assign($task, $invalidDriver, $manager);
                $this->fail('Alocarea unui utilizator neeligibil trebuia respinsa.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('driver_id', $exception->errors());
            }
        }

        $this->assertSame(0, $task->assignments()->count());
        $this->assertSame('unassigned', $task->fresh()->status);
    }

    private function userWithRole(string $role, bool $active = true): User
    {
        $user = User::factory()->create(['active' => $active]);
        $user->assignRole($role);

        return $user;
    }

    private function task(User $manager, ?Transfer $transfer = null): Task
    {
        return Task::create([
            'number' => 'TSK-SEC-'.str()->upper(str()->random(8)),
            'title' => 'Sarcina securizata',
            'category' => 'general',
            'transfer_id' => $transfer?->id,
            'created_by' => $manager->id,
            'status' => 'unassigned',
            'priority' => 'normal',
        ]);
    }
}
