<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\Location;
use App\Models\OperationalAlert;
use App\Models\Project;
use App\Models\StockLevel;
use App\Models\TrackedAsset;
use App\Models\Transfer;
use App\Models\User;
use App\Services\ProjectMaterialPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectMaterialPlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_planner_creates_a_project_and_local_roles_only_see_their_locations(): void
    {
        $planner = $this->user('manager', 'Manager general');
        $localManager = $this->user('sef-santier', 'Șef locație');
        $otherManager = $this->user('sef-santier', 'Șef altă locație');
        $location = $this->location('SITE-PLAN', $localManager);
        $otherLocation = $this->location('SITE-OTHER', $otherManager);
        $material = $this->material('MAT-PLAN');

        $response = $this->actingAs($planner)->post(route('projects.store'), [
            'code' => ' prj-001 ',
            'name' => 'Extindere hală',
            'location_id' => $location->id,
            'status' => 'active',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-10-01',
            'notes' => 'Plan inițial',
            'lines' => [
                ['catalog_item_id' => $material->id, 'planned_quantity' => 20],
            ],
        ]);

        $project = Project::query()->sole();
        $response->assertRedirect(route('projects.show', $project));
        $this->assertSame('PRJ-001', $project->code);
        $this->assertDatabaseHas('project_material_plans', [
            'project_id' => $project->id,
            'catalog_item_id' => $material->id,
            'planned_quantity' => 20,
        ]);

        $this->actingAs($localManager)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('PRJ-001');
        $this->actingAs($localManager)->get(route('projects.create'))->assertForbidden();
        $this->actingAs($otherManager)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertDontSee('PRJ-001');
        $this->actingAs($otherManager)->get(route('projects.show', $project))->assertForbidden();

        $otherProject = $this->project('PRJ-OTHER', $planner, $otherLocation, $material, 5);
        $this->actingAs($planner)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('PRJ-001')
            ->assertSee($otherProject->code);
    }

    public function test_linked_transfers_are_accumulated_and_overrun_creates_visible_alerts(): void
    {
        $planner = $this->user('manager', 'Manager plan');
        $admin = $this->user('admin', 'Administrator plan');
        $localManager = $this->user('sef-santier', 'Șef proiect');
        $source = $this->location('BASE-SOURCE', $localManager, 'base');
        $destination = $this->location('SITE-DEST', $localManager);
        $material = $this->material('MAT-CEMENT');
        StockLevel::create([
            'location_id' => $source->id,
            'catalog_item_id' => $material->id,
            'quantity' => 100,
        ]);
        $project = $this->project('PRJ-LIMIT', $planner, $destination, $material, 10);

        $first = $this->createTransfer($localManager, $source, $destination, $project, $material, 6);
        $this->assertSame(6.0, $this->committedQuantity($project, $material));
        $this->assertDatabaseMissing('operational_alerts', [
            'fingerprint' => "project_plan_overrun:{$project->id}:{$material->id}",
        ]);

        $second = $this->createTransfer($localManager, $source, $destination, $project, $material, 5);
        $this->assertSame(11.0, $this->committedQuantity($project, $material));
        $alert = OperationalAlert::query()
            ->where('fingerprint', "project_plan_overrun:{$project->id}:{$material->id}")
            ->sole();
        $this->assertSame('danger', $alert->severity);
        $this->assertNull($alert->resolved_at);
        $this->assertTrue($alert->recipients->contains($planner));
        $this->assertTrue($alert->recipients->contains($admin));
        $this->assertTrue($alert->recipients->contains($localManager));

        $this->actingAs($localManager)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Planul este depășit')
            ->assertSee('11 buc')
            ->assertSee('+1 buc');
        $this->actingAs($admin)
            ->get(route('alerts.index', ['alert_type' => 'project_plan_overrun']))
            ->assertOk()
            ->assertSee('Plan de materiale depășit')
            ->assertSee('PRJ-LIMIT');
        $this->actingAs($localManager)
            ->get(route('transfers.show', $second))
            ->assertOk()
            ->assertSee('Planul proiectului PRJ-LIMIT este depășit');

        $this->actingAs($localManager)
            ->post(route('transfers.cancel', $second), ['notes' => 'Solicitare retrasă'])
            ->assertRedirect();
        $this->assertSame(6.0, $this->committedQuantity($project, $material));
        $this->assertNotNull($alert->fresh()->resolved_at);
        $this->assertSame('pending_approval', $first->fresh()->status);
    }

    public function test_unplanned_material_is_an_overrun_while_returns_and_equipment_are_excluded(): void
    {
        $admin = $this->user('admin', 'Administrator calcul');
        $manager = $this->user('gestionar-baza', 'Gestionar calcul');
        $source = $this->location('BASE-CALC', $manager, 'base');
        $destination = $this->location('SITE-CALC', $manager);
        $planned = $this->material('MAT-PLANNED');
        $unplanned = $this->material('MAT-EXTRA');
        $equipmentItem = CatalogItem::create([
            'category' => 'equipment',
            'tracking_type' => 'serialized',
            'sku' => 'EQ-PLAN',
            'name' => 'Generator',
            'unit' => 'buc',
            'active' => true,
        ]);
        $asset = TrackedAsset::create([
            'catalog_item_id' => $equipmentItem->id,
            'asset_code' => 'EQ-001',
            'qr_code' => 'QR-EQ-001',
            'status' => 'available',
            'condition' => 'good',
            'current_location_id' => $source->id,
        ]);
        $project = $this->project('PRJ-CALC', $admin, $destination, $planned, 20);

        $normal = $this->directTransfer($project, $source, $destination, 'transfer');
        $normal->lines()->create([
            'catalog_item_id' => $unplanned->id,
            'quantity' => 3,
            'unit' => 'buc',
        ]);
        $normal->lines()->create([
            'catalog_item_id' => $equipmentItem->id,
            'tracked_asset_id' => $asset->id,
            'quantity' => 1,
            'unit' => 'buc',
        ]);
        $return = $this->directTransfer($project, $destination, $source, 'return');
        $return->lines()->create([
            'catalog_item_id' => $planned->id,
            'quantity' => 50,
            'unit' => 'buc',
        ]);

        $progress = app(ProjectMaterialPlanService::class)->progress($project);
        $plannedLine = $progress->firstWhere(fn (array $line) => $line['catalog_item']->is($planned));
        $unplannedLine = $progress->firstWhere(fn (array $line) => $line['catalog_item']->is($unplanned));

        $this->assertSame(0.0, $plannedLine['committed_quantity']);
        $this->assertFalse($plannedLine['has_overrun']);
        $this->assertSame(0.0, $unplannedLine['planned_quantity']);
        $this->assertSame(3.0, $unplannedLine['overrun_quantity']);
        $this->assertTrue($unplannedLine['has_overrun']);
        $this->assertCount(2, $progress);
    }

    public function test_transfer_project_must_be_active_visible_and_match_the_destination(): void
    {
        $admin = $this->user('admin', 'Administrator asociere');
        $manager = $this->user('sef-santier', 'Șef asociere');
        $source = $this->location('BASE-LINK', $manager, 'base');
        $destination = $this->location('SITE-LINK', $manager);
        $otherDestination = $this->location('SITE-WRONG', $manager);
        $material = $this->material('MAT-LINK');
        StockLevel::create(['location_id' => $source->id, 'catalog_item_id' => $material->id, 'quantity' => 30]);
        $project = $this->project('PRJ-LINK', $admin, $destination, $material, 10);

        $this->actingAs($manager)
            ->post(route('transfers.store'), $this->transferPayload($source, $otherDestination, $project, $material, 2))
            ->assertStatus(422);

        $project->update(['status' => 'completed']);
        $this->actingAs($manager)
            ->post(route('transfers.store'), $this->transferPayload($source, $destination, $project, $material, 2))
            ->assertStatus(422);
        $this->assertDatabaseCount('transfers', 0);
    }

    public function test_project_schema_help_content_and_release_are_reversible(): void
    {
        $this->assertTrue(Schema::hasTable('projects'));
        $this->assertTrue(Schema::hasTable('project_material_plans'));
        $this->assertTrue(Schema::hasColumn('transfers', 'project_id'));
        $this->assertDatabaseHas('help_articles', ['slug' => 'circuitul-materialelor', 'current_revision' => 10]);
        $this->assertDatabaseHas('help_articles', ['slug' => 'pagini-si-operatiuni', 'current_revision' => 23]);
        $this->assertDatabaseHas('help_articles', ['slug' => 'ghiduri-dupa-rol', 'current_revision' => 16]);
        $this->assertDatabaseHas('help_articles', ['slug' => 'statusuri-si-termeni', 'current_revision' => 5]);
        $this->assertDatabaseHas('release_notes', [
            'slug' => '2026-07-29-planuri-materiale-pe-proiect',
            'version' => '2026.07.29.9',
            'status' => 'published',
        ]);

        $pdfPreviewFix = require database_path('migrations/2026_08_04_000037_publish_reception_pdf_preview_fix.php');
        $safeguards = require database_path('migrations/2026_08_04_000035_publish_equipment_location_and_transfer_safeguards.php');
        $codeAndQuantityControls = require database_path('migrations/2026_08_02_000034_normalize_internal_codes_and_publish_quantity_controls.php');
        $localizationMigration = require database_path('migrations/2026_08_02_000033_publish_romanian_interface_localization.php');
        $attachmentControlsMigration = require database_path('migrations/2026_08_01_000032_publish_mobile_attachment_controls.php');
        $transferSourceRefreshMigration = require database_path('migrations/2026_08_01_000031_publish_transfer_source_inventory_refresh.php');
        $documentPreviewMigration = require database_path('migrations/2026_07_31_000030_publish_reception_document_preview_content.php');
        $observationVisibilityMigration = require database_path('migrations/2026_07_31_000029_publish_reception_intake_observation_visibility.php');
        $driverTabsMigration = require database_path('migrations/2026_07_31_000028_publish_driver_active_task_tabs.php');
        $liveFilteringMigration = require database_path('migrations/2026_07_30_000026_publish_live_list_filtering.php');
        $searchableListsMigration = require database_path('migrations/2026_07_30_000025_publish_searchable_entity_lists.php');
        $navigationMigration = require database_path('migrations/2026_07_30_000024_publish_consistent_navigation_and_quantities.php');
        $mobileRefinementMigration = require database_path('migrations/2026_07_30_000023_publish_mobile_interface_refinement_content.php');
        $driverMigration = require database_path('migrations/2026_07_29_000020_publish_driver_mobile_task_content.php');
        $contentMigration = require database_path('migrations/2026_07_29_000018_publish_project_material_planning_content.php');
        $schemaMigration = require database_path('migrations/2026_07_29_000017_create_project_material_plans.php');
        DB::connection()->pretend(fn () => $contentMigration->up());
        $pdfPreviewFix->down();
        $safeguards->down();
        $codeAndQuantityControls->down();
        $localizationMigration->down();
        $attachmentControlsMigration->down();
        $transferSourceRefreshMigration->down();
        $documentPreviewMigration->down();
        $observationVisibilityMigration->down();
        $driverTabsMigration->down();
        $liveFilteringMigration->down();
        $searchableListsMigration->down();
        $navigationMigration->down();
        $mobileRefinementMigration->down();
        $driverMigration->down();
        $contentMigration->down();
        $schemaMigration->down();

        $this->assertFalse(Schema::hasTable('projects'));
        $this->assertFalse(Schema::hasColumn('transfers', 'project_id'));
        $this->assertDatabaseMissing('release_notes', ['slug' => '2026-07-29-planuri-materiale-pe-proiect']);

        $schemaMigration->up();
        $contentMigration->up();
        $driverMigration->up();
        $mobileRefinementMigration->up();
        $navigationMigration->up();
        $searchableListsMigration->up();
        $liveFilteringMigration->up();
        $driverTabsMigration->up();
        $observationVisibilityMigration->up();
        $documentPreviewMigration->up();
        $transferSourceRefreshMigration->up();
        $attachmentControlsMigration->up();
        $localizationMigration->up();
        $codeAndQuantityControls->up();
        $safeguards->up();
        $pdfPreviewFix->up();

        $this->assertTrue(Schema::hasTable('projects'));
        $this->assertDatabaseHas('release_notes', ['slug' => '2026-07-29-planuri-materiale-pe-proiect']);
    }

    private function createTransfer(
        User $actor,
        Location $source,
        Location $destination,
        Project $project,
        CatalogItem $material,
        float $quantity,
    ): Transfer {
        $this->actingAs($actor)
            ->post(route('transfers.store'), $this->transferPayload(
                $source,
                $destination,
                $project,
                $material,
                $quantity,
            ))
            ->assertRedirect();

        return Transfer::query()->latest('id')->firstOrFail();
    }

    private function transferPayload(
        Location $source,
        Location $destination,
        Project $project,
        CatalogItem $material,
        float $quantity,
    ): array {
        return [
            'purpose' => 'transfer',
            'project_id' => $project->id,
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'priority' => 'normal',
            'lines' => [
                ['catalog_item_id' => $material->id, 'tracked_asset_id' => null, 'quantity' => $quantity],
            ],
        ];
    }

    private function project(
        string $code,
        User $creator,
        Location $location,
        CatalogItem $material,
        float $quantity,
    ): Project {
        $project = Project::create([
            'code' => $code,
            'name' => 'Proiect '.$code,
            'location_id' => $location->id,
            'created_by' => $creator->id,
            'status' => 'active',
        ]);
        $project->materialPlans()->create([
            'catalog_item_id' => $material->id,
            'planned_quantity' => $quantity,
            'unit' => $material->unit,
        ]);

        return $project;
    }

    private function directTransfer(
        Project $project,
        Location $source,
        Location $destination,
        string $purpose,
    ): Transfer {
        return Transfer::create([
            'number' => strtoupper($purpose).'-'.fake()->unique()->numberBetween(1000, 9999),
            'type' => $source->type.'_to_'.$destination->type,
            'purpose' => $purpose,
            'project_id' => $project->id,
            'status' => 'pending_approval',
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'requested_by' => $project->created_by,
            'requested_at' => now(),
        ]);
    }

    private function committedQuantity(Project $project, CatalogItem $material): float
    {
        $line = app(ProjectMaterialPlanService::class)
            ->progress($project)
            ->firstWhere(fn (array $row) => $row['catalog_item']->is($material));

        return $line['committed_quantity'];
    }

    private function user(string $role, string $name): User
    {
        Role::findOrCreate($role);
        $user = User::factory()->create(['name' => $name, 'active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function location(
        string $code,
        ?User $manager = null,
        string $type = 'site',
    ): Location {
        $location = Location::create([
            'type' => $type,
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

    private function material(string $sku): CatalogItem
    {
        return CatalogItem::create([
            'category' => 'material',
            'tracking_type' => 'quantity',
            'sku' => $sku,
            'name' => 'Material '.$sku,
            'unit' => 'buc',
            'active' => true,
        ]);
    }
}
