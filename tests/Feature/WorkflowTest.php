<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\CustodyTransfer;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\Task;
use App\Models\TrackedAsset;
use App\Models\Transfer;
use App\Models\User;
use App\Services\TaskWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_with_code_and_password(): void
    {
        $user = User::factory()->create([
            'login_code' => 'SOFER-101',
            'password' => Hash::make('secret123'),
            'active' => true,
        ]);

        $this->post(route('login.store'), [
            'login_code' => 'sofer-101',
            'password' => 'secret123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_one_of_multiple_location_managers_can_satisfy_the_location_approval(): void
    {
        [$creator, $destinationManagerOne, $destinationManagerTwo, $driver] = $this->workflowUsers();
        Role::findOrCreate('dispecer');
        $creator->assignRole('dispecer');
        $source = $this->location('B-1', 'base', [$creator]);
        $destination = $this->location('S-1', 'site', [$destinationManagerOne, $destinationManagerTwo]);
        $item = CatalogItem::create([
            'category' => 'material', 'tracking_type' => 'quantity', 'sku' => 'MAT-1',
            'name' => 'Material test', 'unit' => 'buc', 'active' => true,
        ]);
        StockLevel::create(['location_id' => $source->id, 'catalog_item_id' => $item->id, 'quantity' => 100]);

        $this->actingAs($creator)->post(route('transfers.store'), [
            'purpose' => 'transfer',
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'driver_id' => $driver->id,
            'manager_deadline' => now()->addDay()->format('Y-m-d H:i:s'),
            'priority' => 'normal',
            'lines' => [['catalog_item_id' => $item->id, 'tracked_asset_id' => null, 'quantity' => 20]],
        ])->assertRedirect();

        $transfer = Transfer::latest('id')->firstOrFail();
        $this->assertDatabaseHas('transfer_approvals', [
            'transfer_id' => $transfer->id, 'scope' => 'source_manager', 'status' => 'approved',
            'decided_by_user_id' => $creator->id,
        ]);
        $destinationApproval = $transfer->approvals()->where('scope', 'destination_manager')->firstOrFail();
        $this->assertSame('pending', $destinationApproval->status);
        $this->assertSame(1, $destinationManagerOne->notifications()->count());
        $this->assertSame(1, $destinationManagerTwo->notifications()->count());

        $this->actingAs($destinationManagerOne)->put(route('transfer-approvals.update', $destinationApproval), [
            'decision' => 'approved',
        ])->assertRedirect();
        $this->assertSame('approved', $destinationApproval->fresh()->status);

        $assignment = $transfer->task->currentAssignment;
        $this->actingAs($driver)->post(route('task-assignments.respond', $assignment), [
            'decision' => 'accepted',
        ])->assertRedirect();

        $this->assertSame('approved', $transfer->fresh()->status);
        $this->assertDatabaseHas('transfer_approvals', [
            'transfer_id' => $transfer->id, 'scope' => 'driver', 'expected_user_id' => $driver->id, 'status' => 'approved',
        ]);
    }

    public function test_rejected_replacement_keeps_the_original_driver_responsible(): void
    {
        [$manager, , , $firstDriver, $secondDriver] = $this->workflowUsers(includeSecondDriver: true);
        $task = Task::create([
            'number' => 'TSK-TEST-1', 'title' => 'Sarcina test', 'category' => 'general',
            'created_by' => $manager->id, 'status' => 'unassigned', 'priority' => 'normal',
        ]);
        $workflow = app(TaskWorkflowService::class);
        $firstAssignment = $workflow->assign($task, $firstDriver, $manager);
        $workflow->respond($firstAssignment, $firstDriver, 'accepted', null);

        $this->actingAs($firstDriver)->post(route('task-assignments.request-reassignment', $firstAssignment), [
            'notes' => 'Nu pot finaliza cursa.',
        ])->assertRedirect();
        $this->actingAs($manager)->post(route('tasks.assignments.store', $task), [
            'driver_id' => $secondDriver->id,
        ])->assertRedirect();

        $replacement = $task->assignments()->latest('id')->firstOrFail();
        $this->actingAs($secondDriver)->post(route('task-assignments.respond', $replacement), [
            'decision' => 'rejected',
            'response_notes' => 'Sunt indisponibil.',
        ])->assertRedirect();

        $current = $task->fresh()->currentAssignment;
        $this->assertSame($firstDriver->id, $current->driver_id);
        $this->assertSame('accepted', $current->status);
    }

    public function test_driver_estimate_does_not_replace_manager_deadline(): void
    {
        [$manager, , , $driver] = $this->workflowUsers();
        $deadline = now()->addHours(6)->startOfMinute();
        $task = Task::create([
            'number' => 'TSK-TEST-2', 'title' => 'Deadline separat', 'category' => 'general',
            'created_by' => $manager->id, 'status' => 'unassigned', 'priority' => 'normal',
            'manager_deadline' => $deadline,
        ]);
        $workflow = app(TaskWorkflowService::class);
        $assignment = $workflow->assign($task, $driver, $manager);
        $workflow->respond($assignment, $driver, 'accepted', null);
        $estimate = now()->addHours(9)->startOfMinute();

        $this->actingAs($driver)->post(route('task-assignments.estimate', $assignment), [
            'driver_estimate_at' => $estimate->format('Y-m-d H:i:s'),
            'driver_estimate_note' => 'Trafic si incarcare intarziata.',
        ])->assertRedirect();

        $this->assertTrue($task->fresh()->manager_deadline->equalTo($deadline));
        $this->assertTrue($assignment->fresh()->driver_estimate_at->equalTo($estimate));
        $this->assertDatabaseHas('task_comments', ['task_id' => $task->id, 'type' => 'estimate']);
        $this->assertGreaterThan(0, $manager->notifications()->count());
    }

    public function test_driver_task_list_does_not_expose_other_drivers_tasks(): void
    {
        [$manager, , , $driver, $otherDriver] = $this->workflowUsers(includeSecondDriver: true);
        $workflow = app(TaskWorkflowService::class);
        $own = Task::create(['number' => 'TSK-OWN', 'title' => 'Sarcina mea', 'category' => 'general', 'created_by' => $manager->id, 'status' => 'unassigned', 'priority' => 'normal']);
        $other = Task::create(['number' => 'TSK-OTHER', 'title' => 'Sarcina altuia', 'category' => 'general', 'created_by' => $manager->id, 'status' => 'unassigned', 'priority' => 'normal']);
        $workflow->assign($own, $driver, $manager);
        $workflow->assign($other, $otherDriver, $manager);

        $this->actingAs($driver)->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('TSK-OWN')
            ->assertDontSee('TSK-OTHER');
    }

    public function test_driver_loses_task_visibility_only_after_reassignment_is_accepted(): void
    {
        [$manager, , , $firstDriver, $secondDriver] = $this->workflowUsers(includeSecondDriver: true);
        $task = Task::create([
            'number' => 'TSK-PRIVATE-REALLOCATED',
            'title' => 'Sarcina realocata privat',
            'category' => 'general',
            'created_by' => $manager->id,
            'status' => 'unassigned',
            'priority' => 'normal',
        ]);
        $workflow = app(TaskWorkflowService::class);
        $firstAssignment = $workflow->assign($task, $firstDriver, $manager);
        $workflow->respond($firstAssignment, $firstDriver, 'accepted', null);

        $this->actingAs($firstDriver)->post(route('task-assignments.request-reassignment', $firstAssignment), [
            'notes' => 'Solicit realocarea.',
        ])->assertRedirect();
        $this->actingAs($manager)->post(route('tasks.assignments.store', $task), [
            'driver_id' => $secondDriver->id,
        ])->assertRedirect();

        $this->actingAs($firstDriver)->get(route('tasks.index'))
            ->assertViewHas('tasks', fn ($tasks) => $tasks->getCollection()->contains('id', $task->id));
        $this->actingAs($secondDriver)->get(route('tasks.index'))
            ->assertViewHas('tasks', fn ($tasks) => $tasks->getCollection()->contains('id', $task->id));
        $this->actingAs($firstDriver)->get(route('tasks.show', $task))
            ->assertOk()
            ->assertDontSee($secondDriver->name);
        $this->actingAs($secondDriver)->get(route('tasks.show', $task))
            ->assertOk()
            ->assertDontSee($firstDriver->name);

        $replacement = $task->assignments()->latest('id')->firstOrFail();
        $this->actingAs($secondDriver)->post(route('task-assignments.respond', $replacement), [
            'decision' => 'accepted',
        ])->assertRedirect();

        $this->actingAs($firstDriver)->get(route('tasks.index'))
            ->assertViewHas('tasks', fn ($tasks) => ! $tasks->getCollection()->contains('id', $task->id));
        $this->actingAs($firstDriver)->get(route('tasks.show', $task))->assertForbidden();
        $this->actingAs($secondDriver)->get(route('tasks.show', $task))->assertOk();
    }

    public function test_driver_cannot_start_a_task_before_accepting_it(): void
    {
        [$manager, , , $driver] = $this->workflowUsers();
        $task = Task::create([
            'number' => 'TSK-PENDING', 'title' => 'Asteapta acceptarea', 'category' => 'general',
            'created_by' => $manager->id, 'status' => 'unassigned', 'priority' => 'normal',
        ]);
        app(TaskWorkflowService::class)->assign($task, $driver, $manager);

        $this->actingAs($driver)->post(route('tasks.transition', $task), [
            'status' => 'in_progress',
        ])->assertForbidden();

        $this->assertSame('pending_acceptance', $task->fresh()->status);
    }

    public function test_custody_transfer_finishes_only_after_both_people_approve(): void
    {
        Role::findOrCreate('muncitor');
        $from = User::factory()->create(['login_code' => 'WORKER-1']);
        $from->assignRole('muncitor');
        $to = User::factory()->create(['login_code' => 'WORKER-2']);
        $to->assignRole('muncitor');
        $item = CatalogItem::create([
            'category' => 'echipament', 'tracking_type' => 'individual', 'sku' => 'TOOL-1',
            'name' => 'Unealta test', 'unit' => 'buc', 'active' => true,
        ]);
        $asset = TrackedAsset::create([
            'catalog_item_id' => $item->id,
            'asset_code' => 'ASSET-1',
            'qr_code' => 'QR-ASSET-1',
            'status' => 'in_use',
            'current_custodian_id' => $from->id,
        ]);

        $this->actingAs($from)->post(route('custody-transfers.store'), [
            'tracked_asset_id' => $asset->id,
            'to_user_id' => $to->id,
        ])->assertRedirect();

        $transfer = CustodyTransfer::latest('id')->firstOrFail();
        $this->assertNotNull($transfer->from_approved_at);
        $this->assertNull($transfer->to_approved_at);
        $this->assertSame('pending', $transfer->status);
        $this->assertSame($from->id, $asset->fresh()->current_custodian_id);

        $this->actingAs($to)->put(route('custody-transfers.update', $transfer), [
            'decision' => 'approved',
        ])->assertRedirect();

        $this->assertSame('accepted', $transfer->fresh()->status);
        $this->assertNotNull($transfer->fresh()->to_approved_at);
        $this->assertSame($to->id, $asset->fresh()->current_custodian_id);
    }

    public function test_whatsapp_link_normalizes_a_romanian_local_phone_number(): void
    {
        [$manager, , , $driver] = $this->workflowUsers();
        $driver->update(['phone' => '0712 345 678']);
        $task = Task::create([
            'number' => 'TSK-WA', 'title' => 'Mesaj WhatsApp', 'category' => 'general',
            'created_by' => $manager->id, 'status' => 'unassigned', 'priority' => 'normal',
        ]);

        $response = $this->actingAs($manager)->get(route('tasks.whatsapp', [
            'task' => $task,
            'user_id' => $driver->id,
        ]));

        $response->assertRedirectContains('https://wa.me/40712345678?text=');
    }

    public function test_resource_lists_have_dedicated_create_and_edit_pages(): void
    {
        foreach (['super-admin', 'sef-santier', 'sofer'] as $role) {
            Role::findOrCreate($role);
        }
        $admin = User::factory()->create([
            'login_code' => 'ADMIN-UI',
            'email' => config('roles.protected_admin_email'),
        ]);
        $admin->assignRole('super-admin');
        $location = Location::create(['code' => 'UI-BASE', 'name' => 'Baza UI', 'type' => 'base', 'active' => true]);
        $item = CatalogItem::create([
            'category' => 'equipment', 'tracking_type' => 'serialized', 'sku' => 'UI-EQP',
            'name' => 'Echipament UI', 'unit' => 'buc', 'active' => true,
        ]);
        $asset = TrackedAsset::create([
            'catalog_item_id' => $item->id, 'asset_code' => 'UI-ASSET', 'qr_code' => 'QR-UI-ASSET',
            'status' => 'available', 'condition' => 'good', 'current_location_id' => $location->id,
        ]);

        $this->actingAs($admin);

        foreach ([
            route('catalog-items.create'),
            route('catalog-items.edit', $item),
            route('locations.create'),
            route('locations.edit', $location),
            route('tracked-assets.create'),
            route('tracked-assets.edit', $asset),
            route('supplier-receptions.create'),
            route('consumption-reports.create'),
            route('tasks.create'),
            route('transfers.create'),
            route('users.create'),
            route('users.edit', $admin),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_master_resources_can_be_created_and_updated_from_the_shared_forms(): void
    {
        foreach (['super-admin', 'sef-santier'] as $role) {
            Role::findOrCreate($role);
        }
        $admin = User::factory()->create([
            'login_code' => 'ADMIN-CRUD',
            'email' => config('roles.protected_admin_email'),
        ]);
        $admin->assignRole('super-admin');
        $manager = User::factory()->create(['login_code' => 'MANAGER-CRUD']);
        $manager->assignRole('sef-santier');
        $this->actingAs($admin);

        $this->post(route('catalog-items.store'), [
            'category' => 'equipment', 'tracking_type' => 'serialized', 'sku' => 'CRUD-EQP',
            'name' => 'Echipament CRUD', 'unit' => 'buc', 'active' => '1',
        ])->assertRedirect(route('catalog-items.index'));
        $item = CatalogItem::where('sku', 'CRUD-EQP')->firstOrFail();
        $this->put(route('catalog-items.update', $item), [
            'category' => 'tool', 'tracking_type' => 'serialized', 'sku' => 'CRUD-EQP',
            'name' => 'Echipament actualizat', 'unit' => 'buc', 'active' => '1',
        ])->assertRedirect(route('catalog-items.index'));

        $this->post(route('locations.store'), [
            'type' => 'base', 'code' => 'CRUD-BASE', 'name' => 'Baza CRUD',
            'manager_user_ids' => [$manager->id], 'active' => '1',
        ])->assertRedirect(route('locations.index'));
        $location = Location::where('code', 'CRUD-BASE')->firstOrFail();
        $this->assertTrue($location->activeManagers()->whereKey($manager->id)->exists());

        $this->post(route('tracked-assets.store'), [
            'catalog_item_id' => $item->id, 'asset_code' => 'CRUD-ASSET', 'status' => 'available',
            'condition' => 'good', 'current_location_id' => $location->id,
        ])->assertRedirect(route('tracked-assets.index'));
        $asset = TrackedAsset::where('asset_code', 'CRUD-ASSET')->firstOrFail();
        $this->put(route('tracked-assets.update', $asset), [
            'catalog_item_id' => $item->id, 'asset_code' => 'CRUD-ASSET', 'status' => 'maintenance',
            'condition' => 'needs_service', 'current_location_id' => $location->id,
        ])->assertRedirect(route('tracked-assets.index'));

        $this->post(route('users.store'), [
            'name' => 'Sofer CRUD', 'login_code' => 'DRIVER-CRUD', 'password' => 'secret123',
            'roles' => [], 'active' => '1',
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('catalog_items', ['id' => $item->id, 'name' => 'Echipament actualizat']);
        $this->assertDatabaseHas('tracked_assets', ['id' => $asset->id, 'condition' => 'needs_service']);
        $this->assertDatabaseHas('users', ['login_code' => 'DRIVER-CRUD', 'email' => 'driver-crud@login.invalid']);
    }

    private function workflowUsers(bool $includeSecondDriver = false): array
    {
        foreach (['sef-santier', 'sofer'] as $role) {
            Role::findOrCreate($role);
        }
        $manager = User::factory()->create(['login_code' => 'MANAGER-1']);
        $manager->assignRole('sef-santier');
        $destinationOne = User::factory()->create(['login_code' => 'MANAGER-2']);
        $destinationOne->assignRole('sef-santier');
        $destinationTwo = User::factory()->create(['login_code' => 'MANAGER-3']);
        $destinationTwo->assignRole('sef-santier');
        $driver = User::factory()->create(['login_code' => 'DRIVER-1']);
        $driver->assignRole('sofer');
        $users = [$manager, $destinationOne, $destinationTwo, $driver];
        if ($includeSecondDriver) {
            $second = User::factory()->create(['login_code' => 'DRIVER-2']);
            $second->assignRole('sofer');
            $users[] = $second;
        }

        return $users;
    }

    private function location(string $code, string $type, array $managers): Location
    {
        $location = Location::create(['code' => $code, 'name' => $code, 'type' => $type, 'active' => true]);
        $location->managers()->sync(collect($managers)->mapWithKeys(fn (User $user, int $index) => [
            $user->id => ['active' => true, 'is_primary' => $index === 0],
        ])->all());
        $location->update(['manager_user_id' => $managers[0]->id]);

        return $location;
    }
}
