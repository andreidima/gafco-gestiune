<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\Location;
use App\Models\Task;
use App\Models\Transfer;
use App\Models\User;
use App\Services\TaskWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationalImprovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_sees_the_complete_transfer_contents_inside_the_task(): void
    {
        $manager = $this->user('sef-santier', 'Manager transport');
        $driver = $this->user('sofer', 'Șofer încărcătură');
        $source = $this->location('BASE-CONTENT', $manager);
        $destination = $this->location('SITE-CONTENT', $manager);
        $item = CatalogItem::create([
            'category' => 'material',
            'tracking_type' => 'quantity',
            'sku' => 'MAT-CONTENT',
            'name' => 'Plăci termoizolante',
            'unit' => 'buc',
            'active' => true,
        ]);
        $transfer = $this->transfer('TR-CONTENT', $source, $destination, $manager);
        $transfer->update([
            'document_number' => 'AVZ-778',
            'notes' => 'Încărcare pe paleți.',
        ]);
        $transfer->lines()->create([
            'catalog_item_id' => $item->id,
            'quantity' => 12.5,
            'unit' => 'buc',
            'received_status' => 'pending',
            'notes' => 'A se proteja de ploaie.',
        ]);
        $task = $this->task('TSK-CONTENT', $manager, $source, $destination, $transfer);
        app(TaskWorkflowService::class)->assign($task, $driver, $manager);

        $this->actingAs($driver)
            ->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('Conținutul transferului TR-CONTENT')
            ->assertSee('Plăci termoizolante')
            ->assertSee('12,5 buc')
            ->assertSee('AVZ-778')
            ->assertSee('A se proteja de ploaie.')
            ->assertSee('Încărcare pe paleți.');
    }

    public function test_closed_tasks_are_read_only_and_reject_new_comments(): void
    {
        $manager = $this->user('sef-santier', 'Manager finalizare');
        $driver = $this->user('sofer', 'Șofer finalizare');
        $task = Task::create([
            'number' => 'TSK-LOCKED',
            'title' => 'Sarcină finalizată',
            'category' => 'general',
            'created_by' => $manager->id,
            'status' => 'unassigned',
            'priority' => 'normal',
        ]);
        $workflow = app(TaskWorkflowService::class);
        $assignment = $workflow->assign($task, $driver, $manager);
        $workflow->respond($assignment, $driver, 'accepted', null);
        $task->update(['status' => 'completed', 'completed_at' => now()]);

        $this->actingAs($driver)
            ->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('Sarcină închisă, disponibilă doar pentru consultare.')
            ->assertDontSee('Adauga o observatie');

        $this->actingAs($driver)
            ->post(route('tasks.comments.store', $task), ['body' => 'Nu trebuie salvat.'])
            ->assertForbidden();
        $this->actingAs($manager)->get(route('tasks.edit', $task))->assertForbidden();
        $this->actingAs($manager)
            ->post(route('tasks.assignments.store', $task), ['driver_id' => $driver->id])
            ->assertForbidden();

        $this->assertDatabaseMissing('task_comments', [
            'task_id' => $task->id,
            'body' => 'Nu trebuie salvat.',
        ]);
    }

    public function test_dispatch_recommends_a_driver_with_an_active_task_on_the_same_directional_route(): void
    {
        $dispatcher = $this->user('dispecer', 'Dispecer test');
        $matchingDriver = $this->user('sofer', 'Șofer rută comună');
        $freeDriver = $this->user('sofer', 'Șofer liber');
        $source = $this->location('BASE-ROUTE');
        $destination = $this->location('SITE-ROUTE');

        $active = $this->task('TSK-ACTIVE-ROUTE', $dispatcher, $source, $destination);
        $workflow = app(TaskWorkflowService::class);
        $assignment = $workflow->assign($active, $matchingDriver, $dispatcher);
        $workflow->respond($assignment, $matchingDriver, 'accepted', null);
        $active->update(['status' => 'in_progress', 'started_at' => now()]);

        $this->task('TSK-TO-ALLOCATE', $dispatcher, $source, $destination);

        $this->actingAs($dispatcher)
            ->get(route('tasks.dispatch'))
            ->assertOk()
            ->assertSee('Șofer rută comună are deja TSK-ACTIVE-ROUTE pe aceeași rută')
            ->assertSee('Recomandat · Șofer rută comună - aceeași rută · Ocupat')
            ->assertSee($freeDriver->name)
            ->assertSee('data-live-view', false)
            ->assertSee('data-auto-submit-filters', false);
    }

    public function test_dashboard_and_location_form_explain_the_new_operational_controls(): void
    {
        $admin = $this->user('super-admin', 'Administrator operațional', [
            'email' => config('roles.protected_admin_email'),
        ]);
        $manager = $this->user('sef-santier', 'Responsabil locație');
        $location = $this->location('SITE-MANAGERS', $manager);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Acces rapid')
            ->assertSee('Situație șoferi')
            ->assertSee('data-live-view-key="dashboard"', false);

        $this->actingAs($admin)
            ->get(route('locations.edit', $location))
            ->assertOk()
            ->assertSee('Responsabil locație · Șef de șantier')
            ->assertSee('aprobarea unuia singur este suficientă')
            ->assertSee('Eliminarea oprește notificările viitoare');
    }

    public function test_help_center_and_release_note_explain_the_operational_improvements(): void
    {
        $this->assertDatabaseHas('help_articles', [
            'slug' => 'pagini-si-operatiuni',
            'current_revision' => 14,
        ]);
        $this->assertDatabaseHas('help_articles', [
            'slug' => 'ghiduri-dupa-rol',
            'current_revision' => 15,
        ]);
        $this->assertDatabaseHas('help_articles', [
            'slug' => 'statusuri-si-termeni',
            'current_revision' => 5,
        ]);
        $this->assertStringContainsString(
            'actualizează automat la fiecare 5 minute',
            (string) DB::table('help_articles')
                ->where('slug', 'pagini-si-operatiuni')
                ->value('body_markdown'),
        );
        $this->assertDatabaseHas('release_notes', [
            'slug' => '2026-07-29-claritate-operationala-si-vizualizare-live',
            'version' => '2026.07.29.8',
            'status' => 'published',
        ]);
    }

    private function user(string $role, string $name, array $overrides = []): User
    {
        Role::findOrCreate($role);
        $user = User::factory()->create($overrides + [
            'name' => $name,
            'active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function location(string $code, ?User $manager = null): Location
    {
        $location = Location::create([
            'type' => str_starts_with($code, 'BASE') ? 'base' : 'site',
            'code' => $code,
            'name' => 'Locația '.$code,
            'active' => true,
            'manager_user_id' => $manager?->id,
        ]);
        if ($manager) {
            $location->managers()->attach($manager->id, [
                'active' => true,
                'is_primary' => true,
            ]);
        }

        return $location;
    }

    private function transfer(
        string $number,
        Location $source,
        Location $destination,
        User $manager,
    ): Transfer {
        return Transfer::create([
            'number' => $number,
            'type' => 'base_to_site',
            'purpose' => 'transfer',
            'status' => 'pending_approval',
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'requested_by' => $manager->id,
            'requested_at' => now(),
        ]);
    }

    private function task(
        string $number,
        User $creator,
        ?Location $source = null,
        ?Location $destination = null,
        ?Transfer $transfer = null,
    ): Task {
        return Task::create([
            'number' => $number,
            'title' => 'Sarcina '.$number,
            'category' => $transfer ? 'transport' : 'general',
            'transfer_id' => $transfer?->id,
            'created_by' => $creator->id,
            'source_location_id' => $source?->id,
            'destination_location_id' => $destination?->id,
            'status' => 'unassigned',
            'priority' => 'normal',
        ]);
    }
}
