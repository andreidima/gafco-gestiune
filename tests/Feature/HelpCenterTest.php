<?php

namespace Tests\Feature;

use App\Models\HelpArticle;
use App\Models\ReleaseNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HelpCenterTest extends TestCase
{
    use RefreshDatabase;

    private function attachmentControlsMigration(): object
    {
        $pdfScrollingFix = require database_path('migrations/2026_08_04_000038_publish_reception_pdf_scrolling_fix.php');
        $pdfPreviewFix = require database_path('migrations/2026_08_04_000037_publish_reception_pdf_preview_fix.php');
        $safeguards = require database_path('migrations/2026_08_04_000035_publish_equipment_location_and_transfer_safeguards.php');
        $codeAndQuantityControls = require database_path('migrations/2026_08_02_000034_normalize_internal_codes_and_publish_quantity_controls.php');
        $localization = require database_path('migrations/2026_08_02_000033_publish_romanian_interface_localization.php');
        $attachmentControls = require database_path('migrations/2026_08_01_000032_publish_mobile_attachment_controls.php');

        return new class($pdfScrollingFix, $pdfPreviewFix, $safeguards, $codeAndQuantityControls, $localization, $attachmentControls)
        {
            public function __construct(
                private readonly object $pdfScrollingFix,
                private readonly object $pdfPreviewFix,
                private readonly object $safeguards,
                private readonly object $codeAndQuantityControls,
                private readonly object $localization,
                private readonly object $attachmentControls,
            ) {}

            public function down(): void
            {
                $this->pdfScrollingFix->down();
                $this->pdfPreviewFix->down();
                $this->safeguards->down();
                $this->codeAndQuantityControls->down();
                $this->localization->down();
                $this->attachmentControls->down();
            }

            public function up(): void
            {
                $this->attachmentControls->up();
                $this->localization->up();
                $this->codeAndQuantityControls->up();
                $this->safeguards->up();
                $this->pdfPreviewFix->up();
                $this->pdfScrollingFix->up();
            }
        };
    }

    public function test_help_center_and_release_notes_require_authentication(): void
    {
        $this->get(route('help.index'))->assertRedirect(route('login'));
        $this->get(route('release-notes.index'))->assertRedirect(route('login'));
    }

    public function test_every_active_authenticated_role_can_read_published_help_content(): void
    {
        foreach (['super-admin', 'admin', 'dispecer', 'manager', 'gestionar-baza', 'sef-santier', 'sofer', 'muncitor', 'contabil', 'user'] as $roleName) {
            Role::findOrCreate($roleName);
            $user = User::factory()->create([
                'login_code' => 'HELP-'.strtoupper($roleName),
                'active' => true,
            ]);
            $user->assignRole($roleName);

            $this->actingAs($user)
                ->get(route('help.index'))
                ->assertOk()
                ->assertSee('Centru de ajutor')
                ->assertSee('Circuitul materialelor');

            $this->actingAs($user)
                ->get(route('release-notes.index'))
                ->assertOk()
                ->assertSee('Ce s-a schimbat în aplicație');
        }
    }

    public function test_published_articles_have_matching_immutable_revisions(): void
    {
        $articles = HelpArticle::query()->with('revisions')->get();

        $this->assertCount(7, $articles);
        $articles->each(function (HelpArticle $article): void {
            $revisions = $article->revisions->sortBy('revision')->values();
            $currentRevision = $revisions->last();

            $this->assertSame('published', $article->status);
            $this->assertSame(range(1, $article->current_revision), $revisions->pluck('revision')->all());
            $this->assertSame($article->current_revision, $currentRevision->revision);
            $this->assertSame($article->title, $currentRevision->title);
            $this->assertSame($article->body_markdown, $currentRevision->body_markdown);
            $this->assertTrue($revisions->every(fn ($revision) => $revision->source === 'system'));
        });
    }

    public function test_interface_release_migration_supports_sql_preview(): void
    {
        $migration = require database_path('migrations/2026_07_29_000001_publish_interface_corrections_help_and_release_note.php');

        DB::connection()->pretend(fn () => $migration->up());

        $this->assertTrue(true);
    }

    public function test_equipment_location_and_transfer_safeguards_content_migration_is_reversible(): void
    {
        $pdfScrollingFix = require database_path('migrations/2026_08_04_000038_publish_reception_pdf_scrolling_fix.php');
        $pdfPreviewFix = require database_path('migrations/2026_08_04_000037_publish_reception_pdf_preview_fix.php');
        $migration = require database_path('migrations/2026_08_04_000035_publish_equipment_location_and_transfer_safeguards.php');

        DB::connection()->pretend(fn () => $migration->up());

        $this->assertSame(24, HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('current_revision'));
        $this->assertSame(10, HelpArticle::query()->where('slug', 'circuitul-materialelor')->value('current_revision'));
        $this->assertStringContainsString(
            'Salvează modificările',
            (string) HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('body_markdown'),
        );
        $this->assertStringContainsString(
            'Locația sursă și locația de destinație trebuie să fie diferite',
            (string) HelpArticle::query()->where('slug', 'circuitul-materialelor')->value('body_markdown'),
        );
        $this->assertDatabaseHas('release_notes', [
            'slug' => '2026-08-04-navigare-echipamente-si-protectii-locatii-transferuri',
            'version' => '2026.08.04.1',
            'status' => 'published',
        ]);

        $pdfScrollingFix->down();
        $pdfPreviewFix->down();
        $migration->down();

        $this->assertSame(21, HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('current_revision'));
        $this->assertSame(9, HelpArticle::query()->where('slug', 'circuitul-materialelor')->value('current_revision'));
        $this->assertDatabaseMissing('release_notes', [
            'slug' => '2026-08-04-navigare-echipamente-si-protectii-locatii-transferuri',
        ]);

        $migration->up();

        $this->assertSame(22, HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('current_revision'));
        $this->assertSame(10, HelpArticle::query()->where('slug', 'circuitul-materialelor')->value('current_revision'));
        $pdfPreviewFix->up();
        $pdfScrollingFix->up();
    }

    public function test_reception_pdf_preview_fix_content_migration_is_reversible(): void
    {
        $scrollingFix = require database_path('migrations/2026_08_04_000038_publish_reception_pdf_scrolling_fix.php');
        $migration = require database_path('migrations/2026_08_04_000037_publish_reception_pdf_preview_fix.php');

        DB::connection()->pretend(fn () => $migration->up());
        $scrollingFix->down();

        $this->assertSame(23, HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('current_revision'));
        $this->assertStringContainsString(
            'afișate pagină cu pagină direct în aplicație',
            (string) HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('body_markdown'),
        );
        $this->assertDatabaseHas('release_notes', [
            'slug' => '2026-08-04-previzualizare-pdf-documente-receptie',
            'version' => '2026.08.04.3',
            'status' => 'published',
        ]);

        $migration->down();

        $this->assertSame(22, HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('current_revision'));
        $this->assertDatabaseMissing('release_notes', [
            'slug' => '2026-08-04-previzualizare-pdf-documente-receptie',
        ]);

        $migration->up();
        $scrollingFix->up();

        $this->assertSame(24, HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('current_revision'));
    }

    public function test_reception_pdf_scrolling_fix_content_migration_is_reversible(): void
    {
        $migration = require database_path('migrations/2026_08_04_000038_publish_reception_pdf_scrolling_fix.php');

        DB::connection()->pretend(fn () => $migration->up());

        $this->assertSame(24, HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('current_revision'));
        $this->assertStringContainsString(
            'derularea verticală din interiorul documentului',
            (string) HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('body_markdown'),
        );
        $this->assertDatabaseHas('release_notes', [
            'slug' => '2026-08-04-derulare-completa-documente-pdf',
            'version' => '2026.08.04.4',
            'status' => 'published',
        ]);

        $migration->down();

        $this->assertSame(23, HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('current_revision'));
        $this->assertDatabaseMissing('release_notes', [
            'slug' => '2026-08-04-derulare-completa-documente-pdf',
        ]);

        $migration->up();

        $this->assertSame(24, HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('current_revision'));
    }

    public function test_transfer_quantity_layout_release_note_migration_is_reversible(): void
    {
        $migration = require database_path('migrations/2026_08_04_000036_publish_transfer_quantity_layout_fix.php');

        DB::connection()->pretend(fn () => $migration->up());

        $this->assertDatabaseHas('release_notes', [
            'slug' => '2026-08-04-controale-cantitate-clare-in-transferuri',
            'version' => '2026.08.04.2',
            'status' => 'published',
        ]);

        $migration->down();

        $this->assertDatabaseMissing('release_notes', [
            'slug' => '2026-08-04-controale-cantitate-clare-in-transferuri',
        ]);

        $migration->up();

        $this->assertDatabaseHas('release_notes', [
            'slug' => '2026-08-04-controale-cantitate-clare-in-transferuri',
            'version' => '2026.08.04.2',
            'status' => 'published',
        ]);
    }

    public function test_minor_corrections_are_not_published_in_help_or_release_notes(): void
    {
        $article = HelpArticle::query()
            ->with('revisions')
            ->where('slug', 'pagini-si-operatiuni')
            ->sole();

        $this->assertSame(24, $article->current_revision);
        $this->assertCount(24, $article->revisions);
        $this->assertStringNotContainsString('Utilizatori și liste', $article->body_markdown);
        $this->assertStringContainsString('Filtrele listelor', $article->body_markdown);
        $this->assertFalse(
            ReleaseNote::query()->where('slug', '2026-07-29-afisare-roluri-si-liste')->exists()
        );
    }

    public function test_minor_corrections_removal_migration_supports_sql_preview(): void
    {
        $migration = require database_path('migrations/2026_07_29_000002_remove_minor_corrections_help_and_release_note.php');

        DB::connection()->pretend(fn () => $migration->up());

        $this->assertTrue(true);
    }

    public function test_minor_corrections_removal_migration_is_reversible(): void
    {
        $attachmentControlsMigration = $this->attachmentControlsMigration();
        $transferSourceRefreshMigration = require database_path('migrations/2026_08_01_000031_publish_transfer_source_inventory_refresh.php');
        $documentPreviewMigration = require database_path('migrations/2026_07_31_000030_publish_reception_document_preview_content.php');
        $observationVisibilityMigration = require database_path('migrations/2026_07_31_000029_publish_reception_intake_observation_visibility.php');
        $driverTabsMigration = require database_path('migrations/2026_07_31_000028_publish_driver_active_task_tabs.php');
        $liveFilteringMigration = require database_path('migrations/2026_07_30_000026_publish_live_list_filtering.php');
        $searchableListsMigration = require database_path('migrations/2026_07_30_000025_publish_searchable_entity_lists.php');
        $navigationMigration = require database_path('migrations/2026_07_30_000024_publish_consistent_navigation_and_quantities.php');
        $mobileRefinementMigration = require database_path('migrations/2026_07_30_000023_publish_mobile_interface_refinement_content.php');
        $driverMigration = require database_path('migrations/2026_07_29_000020_publish_driver_mobile_task_content.php');
        $planningMigration = require database_path('migrations/2026_07_29_000018_publish_project_material_planning_content.php');
        $operationalMigration = require database_path('migrations/2026_07_29_000016_publish_operational_improvements_content.php');
        $orderMigration = require database_path('migrations/2026_07_29_000015_publish_negotiated_orders_content.php');
        $custodyMigration = require database_path('migrations/2026_07_29_000013_publish_personal_custody_content.php');
        $alertMigration = require database_path('migrations/2026_07_29_000011_publish_operational_alert_content.php');
        $latestMigration = require database_path('migrations/2026_07_29_000009_publish_transfer_consumption_correction_content.php');
        $receptionMigration = require database_path('migrations/2026_07_29_000007_publish_reception_workflow_help_and_release_note.php');
        $currentMigration = require database_path('migrations/2026_07_29_000005_publish_saved_filters_and_account_protection_content.php');
        $migration = require database_path('migrations/2026_07_29_000002_remove_minor_corrections_help_and_release_note.php');

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
        $planningMigration->down();
        $operationalMigration->down();
        $orderMigration->down();
        $custodyMigration->down();
        $alertMigration->down();
        $latestMigration->down();
        $receptionMigration->down();
        $currentMigration->down();
        $migration->down();

        $article = HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->sole();
        $this->assertSame(3, $article->current_revision);
        $this->assertStringContainsString('Utilizatori și liste', $article->body_markdown);
        $this->assertTrue(
            ReleaseNote::query()->where('slug', '2026-07-29-afisare-roluri-si-liste')->exists()
        );

        $migration->up();

        $article->refresh();
        $this->assertSame(2, $article->current_revision);
        $this->assertStringNotContainsString('Utilizatori și liste', $article->body_markdown);
        $this->assertFalse(
            ReleaseNote::query()->where('slug', '2026-07-29-afisare-roluri-si-liste')->exists()
        );

        $currentMigration->up();
        $receptionMigration->up();
        $latestMigration->up();
        $alertMigration->up();
        $custodyMigration->up();
        $orderMigration->up();
        $operationalMigration->up();
        $planningMigration->up();
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
    }

    public function test_drafts_are_not_exposed_and_markdown_strips_unsafe_html(): void
    {
        $user = User::factory()->create(['login_code' => 'HELP-SAFE']);
        $draft = HelpArticle::create([
            'slug' => 'schita-nepublicata',
            'title' => 'Schiță nepublicată',
            'summary' => 'Nu trebuie afișată.',
            'body_markdown' => 'Conținut',
            'section' => 'reference',
            'status' => 'draft',
            'current_revision' => 1,
        ]);
        $safe = HelpArticle::create([
            'slug' => 'continut-sigur',
            'title' => 'Conținut sigur',
            'summary' => 'Test randare.',
            'body_markdown' => "<script>alert(\"x\")</script>\n\n**Text evidențiat**",
            'section' => 'reference',
            'status' => 'published',
            'current_revision' => 1,
            'published_at' => now(),
        ]);

        $this->actingAs($user)->get(route('help.show', $draft))->assertNotFound();
        $this->actingAs($user)->get(route('help.index'))->assertDontSee('Schiță nepublicată');
        $this->actingAs($user)->get(route('help.show', $safe))
            ->assertOk()
            ->assertDontSee('<script>', false)
            ->assertSee('<strong>Text evidențiat</strong>', false);
    }

    public function test_help_search_and_navigation_find_database_content(): void
    {
        $user = User::factory()->create(['login_code' => 'HELP-SEARCH']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ajutor și noutăți')
            ->assertSee(route('help.index'), false)
            ->assertSee(route('release-notes.index'), false);

        $this->actingAs($user)
            ->get(route('help.index', ['q' => 'cantitatea']))
            ->assertOk()
            ->assertSee('Rezultate pentru „cantitatea”')
            ->assertSee('Circuitul materialelor');
    }

    public function test_release_notes_are_ordered_newest_first_and_drafts_are_hidden(): void
    {
        $user = User::factory()->create(['login_code' => 'HELP-NEWS']);
        $draft = ReleaseNote::create([
            'slug' => 'noutate-nepublicata',
            'version' => 'draft',
            'title' => 'Noutate nepublicată',
            'summary' => 'Nu trebuie afișată.',
            'body_markdown' => 'Conținut',
            'status' => 'draft',
            'released_at' => today(),
        ]);

        $response = $this->actingAs($user)->get(route('release-notes.index'));

        $response->assertOk()
            ->assertSeeInOrder([
                'Documentele PDF pot fi derulate complet în previzualizare',
                'Documentele PDF se afișează corect în recepții',
                'Controale de cantitate clare în transferuri',
                'Navigare mai clară și protecții pentru locații și transferuri',
                'Coduri și cantități mai ușor de folosit',
                'Interfață completă în limba română',
                'Fișiere mai ușor de gestionat pe telefon',
                'Liste corecte la schimbarea sursei transferului',
                'Documentele rămân la vedere în timpul recepției',
                'Observații vizibile în lista documentelor de procesat',
                'Sarcinile active, separate de cele finalizate',
                'Praguri vizibile pentru regulile de alertare',
            ])
            ->assertDontSee('Afișare completă a rolurilor și a listelor')
            ->assertDontSee('Noutate nepublicată');
        $this->actingAs($user)
            ->get(route('release-notes.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Filtrare rapidă fără întreruperea tastării')
            ->assertSee('Căutare rapidă în listele din aplicație')
            ->assertSee('Navigare mai rapidă și cantități mai clare')
            ->assertSee('Sarcinile șoferului, mai rapide și mai clare pe mobil')
            ->assertSee('Planuri de materiale pe proiect și alerte la depășire')
            ->assertSee('Mai multă claritate în activitatea zilnică')
            ->assertSee('Comenzi negociate transformabile în recepții')
            ->assertSee('Alerte pentru stoc și documente de recepție')
            ->assertSee('Stoc disponibil în transferuri și consumuri corectabile')
            ->assertSee('Custodie personală pentru materiale și echipamente')
            ->assertDontSee('Noutate nepublicată');
        $this->actingAs($user)
            ->get(route('release-notes.index', ['page' => 3]))
            ->assertOk()
            ->assertSee('Documente, recepții complete și loturi la consum')
            ->assertSee('Filtre memorate și administrare standardizată')
            ->assertSee('Schimbare rapidă între utilizatori')
            ->assertSee('Fișă completă de inventar pentru materiale')
            ->assertSee('Centru de ajutor și noutăți în aplicație')
            ->assertSee('Fluxuri complete pentru transferuri și sarcini')
            ->assertSee('Navigare mai clară în liste')
            ->assertDontSee('Noutate nepublicată');
        $this->actingAs($user)->get(route('release-notes.show', $draft))->assertNotFound();
    }

    public function test_impersonation_release_updates_help_without_overwriting_history(): void
    {
        $article = HelpArticle::query()
            ->with('revisions')
            ->where('slug', 'ghiduri-dupa-rol')
            ->sole();

        $this->assertSame(16, $article->current_revision);
        $this->assertCount(16, $article->revisions);
        $this->assertStringContainsString('Schimbarea utilizatorului', $article->body_markdown);
        $this->assertStringContainsString('Revino la contul meu', $article->body_markdown);
        $this->assertStringContainsString('Corectarea consumurilor', $article->body_markdown);
        $this->assertTrue(
            ReleaseNote::query()->where('slug', '2026-07-29-schimbare-utilizator')->exists()
        );
    }

    public function test_impersonation_content_migration_supports_sql_preview(): void
    {
        $migration = require database_path('migrations/2026_07_29_000003_add_user_impersonation_permission_and_content.php');

        DB::connection()->pretend(fn () => $migration->up());

        $this->assertTrue(true);
    }

    public function test_searchable_lists_content_migration_is_reversible(): void
    {
        $attachmentControlsMigration = $this->attachmentControlsMigration();
        $transferSourceRefreshMigration = require database_path('migrations/2026_08_01_000031_publish_transfer_source_inventory_refresh.php');
        $documentPreviewMigration = require database_path('migrations/2026_07_31_000030_publish_reception_document_preview_content.php');
        $observationVisibilityMigration = require database_path('migrations/2026_07_31_000029_publish_reception_intake_observation_visibility.php');
        $driverTabsMigration = require database_path('migrations/2026_07_31_000028_publish_driver_active_task_tabs.php');
        $liveFilteringMigration = require database_path('migrations/2026_07_30_000026_publish_live_list_filtering.php');
        $migration = require database_path('migrations/2026_07_30_000025_publish_searchable_entity_lists.php');

        DB::connection()->pretend(fn () => $migration->up());
        $attachmentControlsMigration->down();
        $transferSourceRefreshMigration->down();
        $documentPreviewMigration->down();
        $observationVisibilityMigration->down();
        $driverTabsMigration->down();
        $liveFilteringMigration->down();

        $this->assertSame(
            14,
            HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('current_revision'),
        );
        $this->assertSame(
            15,
            HelpArticle::query()->where('slug', 'ghiduri-dupa-rol')->value('current_revision'),
        );
        $this->assertStringContainsString(
            'orice parte a denumirii',
            HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('body_markdown'),
        );
        $this->assertTrue(
            ReleaseNote::query()->where('slug', '2026-07-30-liste-cautabile-in-aplicatie')->exists(),
        );

        $migration->down();

        $this->assertSame(
            13,
            HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('current_revision'),
        );
        $this->assertSame(
            14,
            HelpArticle::query()->where('slug', 'ghiduri-dupa-rol')->value('current_revision'),
        );
        $this->assertFalse(
            ReleaseNote::query()->where('slug', '2026-07-30-liste-cautabile-in-aplicatie')->exists(),
        );

        $migration->up();
        $liveFilteringMigration->up();
        $driverTabsMigration->up();
        $observationVisibilityMigration->up();
        $documentPreviewMigration->up();
        $transferSourceRefreshMigration->up();
        $attachmentControlsMigration->up();
    }

    public function test_live_list_filtering_content_migration_is_reversible(): void
    {
        $attachmentControlsMigration = $this->attachmentControlsMigration();
        $transferSourceRefreshMigration = require database_path('migrations/2026_08_01_000031_publish_transfer_source_inventory_refresh.php');
        $documentPreviewMigration = require database_path('migrations/2026_07_31_000030_publish_reception_document_preview_content.php');
        $observationVisibilityMigration = require database_path('migrations/2026_07_31_000029_publish_reception_intake_observation_visibility.php');
        $driverTabsMigration = require database_path('migrations/2026_07_31_000028_publish_driver_active_task_tabs.php');
        $migration = require database_path('migrations/2026_07_30_000026_publish_live_list_filtering.php');

        DB::connection()->pretend(fn () => $migration->up());
        $attachmentControlsMigration->down();
        $transferSourceRefreshMigration->down();
        $documentPreviewMigration->down();
        $observationVisibilityMigration->down();
        $driverTabsMigration->down();

        $this->assertSame(
            15,
            HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('current_revision'),
        );
        $this->assertStringContainsString(
            'minimum două caractere',
            HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('body_markdown'),
        );
        $this->assertTrue(
            ReleaseNote::query()->where('slug', '2026-07-30-filtrare-live-fara-pierderea-focusului')->exists(),
        );

        $migration->down();

        $this->assertSame(
            14,
            HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('current_revision'),
        );
        $this->assertFalse(
            ReleaseNote::query()->where('slug', '2026-07-30-filtrare-live-fara-pierderea-focusului')->exists(),
        );

        $migration->up();
        $driverTabsMigration->up();
        $observationVisibilityMigration->up();
        $documentPreviewMigration->up();
        $transferSourceRefreshMigration->up();
        $attachmentControlsMigration->up();
    }

    public function test_reception_intake_observation_visibility_content_migration_is_reversible(): void
    {
        $attachmentControlsMigration = $this->attachmentControlsMigration();
        $transferSourceRefreshMigration = require database_path('migrations/2026_08_01_000031_publish_transfer_source_inventory_refresh.php');
        $documentPreviewMigration = require database_path('migrations/2026_07_31_000030_publish_reception_document_preview_content.php');
        $migration = require database_path('migrations/2026_07_31_000029_publish_reception_intake_observation_visibility.php');

        DB::connection()->pretend(fn () => $migration->up());

        $this->assertDatabaseHas('help_articles', [
            'slug' => 'pagini-si-operatiuni',
            'current_revision' => 24,
        ]);
        $this->assertStringContainsString(
            'Observațiile completate apar direct',
            (string) HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('body_markdown'),
        );
        $this->assertDatabaseHas('release_notes', [
            'slug' => '2026-07-31-observatii-vizibile-documente-procesat',
            'version' => '2026.07.31.2',
            'status' => 'published',
        ]);

        $attachmentControlsMigration->down();
        $transferSourceRefreshMigration->down();
        $documentPreviewMigration->down();
        $migration->down();

        $this->assertDatabaseHas('help_articles', [
            'slug' => 'pagini-si-operatiuni',
            'current_revision' => 16,
        ]);
        $this->assertStringNotContainsString(
            'Observațiile completate apar direct',
            (string) HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('body_markdown'),
        );
        $this->assertDatabaseMissing('release_notes', [
            'slug' => '2026-07-31-observatii-vizibile-documente-procesat',
        ]);

        $migration->up();
        $documentPreviewMigration->up();
        $transferSourceRefreshMigration->up();
        $attachmentControlsMigration->up();
    }

    public function test_reception_document_preview_content_migration_is_reversible(): void
    {
        $attachmentControlsMigration = $this->attachmentControlsMigration();
        $transferSourceRefreshMigration = require database_path('migrations/2026_08_01_000031_publish_transfer_source_inventory_refresh.php');
        $migration = require database_path('migrations/2026_07_31_000030_publish_reception_document_preview_content.php');

        DB::connection()->pretend(fn () => $migration->up());
        $attachmentControlsMigration->down();
        $transferSourceRefreshMigration->down();
        $migration->down();

        $this->assertSame(17, HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('current_revision'));
        $this->assertSame(7, HelpArticle::query()->where('slug', 'circuitul-materialelor')->value('current_revision'));
        $this->assertFalse(
            ReleaseNote::query()->where('slug', '2026-07-31-previzualizare-documente-receptie')->exists(),
        );

        $migration->up();

        $this->assertStringContainsString(
            'panou alături de formular',
            HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('body_markdown'),
        );
        $this->assertStringContainsString(
            'Butonul **Adaugă material** apare după ultimul material',
            HelpArticle::query()->where('slug', 'circuitul-materialelor')->value('body_markdown'),
        );
        $this->assertTrue(
            ReleaseNote::query()->where('slug', '2026-07-31-previzualizare-documente-receptie')->exists(),
        );
        $transferSourceRefreshMigration->up();
        $attachmentControlsMigration->up();
    }

    public function test_transfer_source_inventory_refresh_content_migration_is_reversible(): void
    {
        $pdfScrollingFix = require database_path('migrations/2026_08_04_000038_publish_reception_pdf_scrolling_fix.php');
        $pdfPreviewFix = require database_path('migrations/2026_08_04_000037_publish_reception_pdf_preview_fix.php');
        $safeguards = require database_path('migrations/2026_08_04_000035_publish_equipment_location_and_transfer_safeguards.php');
        $migration = require database_path('migrations/2026_08_01_000031_publish_transfer_source_inventory_refresh.php');

        DB::connection()->pretend(fn () => $migration->up());
        $pdfScrollingFix->down();
        $pdfPreviewFix->down();
        $safeguards->down();

        $this->assertSame(
            9,
            HelpArticle::query()->where('slug', 'circuitul-materialelor')->value('current_revision'),
        );
        $this->assertStringContainsString(
            'ambele liste se refac integral',
            HelpArticle::query()->where('slug', 'circuitul-materialelor')->value('body_markdown'),
        );
        $this->assertDatabaseHas('release_notes', [
            'slug' => '2026-08-01-liste-transfer-actualizate-dupa-sursa',
            'version' => '2026.08.01.1',
            'status' => 'published',
        ]);

        $migration->down();

        $this->assertSame(
            8,
            HelpArticle::query()->where('slug', 'circuitul-materialelor')->value('current_revision'),
        );
        $this->assertDatabaseMissing('release_notes', [
            'slug' => '2026-08-01-liste-transfer-actualizate-dupa-sursa',
        ]);

        $migration->up();

        $this->assertSame(
            9,
            HelpArticle::query()->where('slug', 'circuitul-materialelor')->value('current_revision'),
        );
        $this->assertDatabaseHas('release_notes', [
            'slug' => '2026-08-01-liste-transfer-actualizate-dupa-sursa',
            'version' => '2026.08.01.1',
            'status' => 'published',
        ]);
        $safeguards->up();
        $pdfPreviewFix->up();
        $pdfScrollingFix->up();
    }

    public function test_mobile_attachment_controls_content_migration_is_reversible(): void
    {
        $pdfScrollingFix = require database_path('migrations/2026_08_04_000038_publish_reception_pdf_scrolling_fix.php');
        $pdfPreviewFix = require database_path('migrations/2026_08_04_000037_publish_reception_pdf_preview_fix.php');
        $safeguards = require database_path('migrations/2026_08_04_000035_publish_equipment_location_and_transfer_safeguards.php');
        $codeAndQuantityControls = require database_path('migrations/2026_08_02_000034_normalize_internal_codes_and_publish_quantity_controls.php');
        $localizationMigration = require database_path('migrations/2026_08_02_000033_publish_romanian_interface_localization.php');
        $migration = require database_path('migrations/2026_08_01_000032_publish_mobile_attachment_controls.php');

        DB::connection()->pretend(fn () => $migration->up());
        $pdfScrollingFix->down();
        $pdfPreviewFix->down();
        $safeguards->down();
        $codeAndQuantityControls->down();
        $localizationMigration->down();

        $this->assertSame(
            19,
            HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('current_revision'),
        );
        $this->assertStringContainsString(
            'Primul fișier este obligatoriu',
            HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('body_markdown'),
        );
        $this->assertDatabaseHas('release_notes', [
            'slug' => '2026-08-01-butoane-fisiere-clare-pe-mobil',
            'version' => '2026.08.01.2',
            'status' => 'published',
        ]);

        $migration->down();

        $this->assertSame(
            18,
            HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('current_revision'),
        );
        $this->assertDatabaseMissing('release_notes', [
            'slug' => '2026-08-01-butoane-fisiere-clare-pe-mobil',
        ]);

        $migration->up();

        $this->assertSame(
            19,
            HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('current_revision'),
        );
        $this->assertDatabaseHas('release_notes', [
            'slug' => '2026-08-01-butoane-fisiere-clare-pe-mobil',
            'version' => '2026.08.01.2',
            'status' => 'published',
        ]);

        $localizationMigration->up();
        $codeAndQuantityControls->up();
        $safeguards->up();
        $pdfPreviewFix->up();
        $pdfScrollingFix->up();
    }

    public function test_driver_active_task_tabs_content_migration_is_reversible(): void
    {
        $attachmentControlsMigration = $this->attachmentControlsMigration();
        $transferSourceRefreshMigration = require database_path('migrations/2026_08_01_000031_publish_transfer_source_inventory_refresh.php');
        $documentPreviewMigration = require database_path('migrations/2026_07_31_000030_publish_reception_document_preview_content.php');
        $observationVisibilityMigration = require database_path('migrations/2026_07_31_000029_publish_reception_intake_observation_visibility.php');
        $migration = require database_path('migrations/2026_07_31_000028_publish_driver_active_task_tabs.php');

        DB::connection()->pretend(fn () => $migration->up());
        $attachmentControlsMigration->down();
        $transferSourceRefreshMigration->down();
        $documentPreviewMigration->down();
        $observationVisibilityMigration->down();

        $this->assertDatabaseHas('help_articles', [
            'slug' => 'pagini-si-operatiuni',
            'current_revision' => 16,
        ]);
        $this->assertDatabaseHas('help_articles', [
            'slug' => 'ghiduri-dupa-rol',
            'current_revision' => 16,
        ]);
        $this->assertStringContainsString(
            'filele **Active**, **De răspuns**, **Acceptate**',
            (string) HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('body_markdown'),
        );
        $this->assertDatabaseHas('release_notes', [
            'slug' => '2026-07-31-sarcini-active-pentru-soferi',
            'version' => '2026.07.31.1',
            'status' => 'published',
        ]);

        $migration->down();

        $this->assertDatabaseHas('help_articles', [
            'slug' => 'pagini-si-operatiuni',
            'current_revision' => 15,
        ]);
        $this->assertDatabaseHas('help_articles', [
            'slug' => 'ghiduri-dupa-rol',
            'current_revision' => 15,
        ]);
        $this->assertDatabaseMissing('release_notes', [
            'slug' => '2026-07-31-sarcini-active-pentru-soferi',
        ]);

        $migration->up();
        $observationVisibilityMigration->up();
        $documentPreviewMigration->up();
        $transferSourceRefreshMigration->up();
        $attachmentControlsMigration->up();
    }

    public function test_saved_filters_content_migration_supports_sql_preview_and_is_reversible(): void
    {
        $attachmentControlsMigration = $this->attachmentControlsMigration();
        $transferSourceRefreshMigration = require database_path('migrations/2026_08_01_000031_publish_transfer_source_inventory_refresh.php');
        $documentPreviewMigration = require database_path('migrations/2026_07_31_000030_publish_reception_document_preview_content.php');
        $observationVisibilityMigration = require database_path('migrations/2026_07_31_000029_publish_reception_intake_observation_visibility.php');
        $driverTabsMigration = require database_path('migrations/2026_07_31_000028_publish_driver_active_task_tabs.php');
        $liveFilteringMigration = require database_path('migrations/2026_07_30_000026_publish_live_list_filtering.php');
        $searchableListsMigration = require database_path('migrations/2026_07_30_000025_publish_searchable_entity_lists.php');
        $navigationMigration = require database_path('migrations/2026_07_30_000024_publish_consistent_navigation_and_quantities.php');
        $mobileRefinementMigration = require database_path('migrations/2026_07_30_000023_publish_mobile_interface_refinement_content.php');
        $driverMigration = require database_path('migrations/2026_07_29_000020_publish_driver_mobile_task_content.php');
        $planningMigration = require database_path('migrations/2026_07_29_000018_publish_project_material_planning_content.php');
        $operationalMigration = require database_path('migrations/2026_07_29_000016_publish_operational_improvements_content.php');
        $orderMigration = require database_path('migrations/2026_07_29_000015_publish_negotiated_orders_content.php');
        $custodyMigration = require database_path('migrations/2026_07_29_000013_publish_personal_custody_content.php');
        $alertMigration = require database_path('migrations/2026_07_29_000011_publish_operational_alert_content.php');
        $latestMigration = require database_path('migrations/2026_07_29_000009_publish_transfer_consumption_correction_content.php');
        $receptionMigration = require database_path('migrations/2026_07_29_000007_publish_reception_workflow_help_and_release_note.php');
        $migration = require database_path('migrations/2026_07_29_000005_publish_saved_filters_and_account_protection_content.php');

        DB::connection()->pretend(fn () => $migration->up());
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
        $planningMigration->down();
        $operationalMigration->down();
        $orderMigration->down();
        $custodyMigration->down();
        $alertMigration->down();
        $latestMigration->down();
        $receptionMigration->down();
        $migration->down();

        $this->assertSame(
            2,
            HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('current_revision')
        );
        $this->assertSame(
            3,
            HelpArticle::query()->where('slug', 'ghiduri-dupa-rol')->value('current_revision')
        );
        $this->assertFalse(ReleaseNote::query()->where('slug', '2026-07-29-filtre-memorate-si-administrare-conturi')->exists());

        $migration->up();

        $this->assertStringContainsString(
            'Filtrele listelor',
            HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('body_markdown')
        );
        $this->assertTrue(ReleaseNote::query()->where('slug', '2026-07-29-filtre-memorate-si-administrare-conturi')->exists());
        $receptionMigration->up();
        $latestMigration->up();
        $alertMigration->up();
        $custodyMigration->up();
        $orderMigration->up();
        $operationalMigration->up();
        $planningMigration->up();
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
    }

    public function test_reception_content_migration_preserves_revisions_and_is_reversible(): void
    {
        $attachmentControlsMigration = $this->attachmentControlsMigration();
        $transferSourceRefreshMigration = require database_path('migrations/2026_08_01_000031_publish_transfer_source_inventory_refresh.php');
        $documentPreviewMigration = require database_path('migrations/2026_07_31_000030_publish_reception_document_preview_content.php');
        $observationVisibilityMigration = require database_path('migrations/2026_07_31_000029_publish_reception_intake_observation_visibility.php');
        $driverTabsMigration = require database_path('migrations/2026_07_31_000028_publish_driver_active_task_tabs.php');
        $liveFilteringMigration = require database_path('migrations/2026_07_30_000026_publish_live_list_filtering.php');
        $searchableListsMigration = require database_path('migrations/2026_07_30_000025_publish_searchable_entity_lists.php');
        $navigationMigration = require database_path('migrations/2026_07_30_000024_publish_consistent_navigation_and_quantities.php');
        $mobileRefinementMigration = require database_path('migrations/2026_07_30_000023_publish_mobile_interface_refinement_content.php');
        $driverMigration = require database_path('migrations/2026_07_29_000020_publish_driver_mobile_task_content.php');
        $planningMigration = require database_path('migrations/2026_07_29_000018_publish_project_material_planning_content.php');
        $operationalMigration = require database_path('migrations/2026_07_29_000016_publish_operational_improvements_content.php');
        $orderMigration = require database_path('migrations/2026_07_29_000015_publish_negotiated_orders_content.php');
        $custodyMigration = require database_path('migrations/2026_07_29_000013_publish_personal_custody_content.php');
        $alertMigration = require database_path('migrations/2026_07_29_000011_publish_operational_alert_content.php');
        $latestMigration = require database_path('migrations/2026_07_29_000009_publish_transfer_consumption_correction_content.php');
        $migration = require database_path('migrations/2026_07_29_000007_publish_reception_workflow_help_and_release_note.php');

        DB::connection()->pretend(fn () => $migration->up());
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
        $planningMigration->down();
        $operationalMigration->down();
        $orderMigration->down();
        $custodyMigration->down();
        $alertMigration->down();
        $latestMigration->down();
        $migration->down();

        $this->assertSame(2, HelpArticle::query()->where('slug', 'circuitul-materialelor')->value('current_revision'));
        $this->assertSame(3, HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('current_revision'));
        $this->assertSame(4, HelpArticle::query()->where('slug', 'ghiduri-dupa-rol')->value('current_revision'));
        $this->assertFalse(
            ReleaseNote::query()->where('slug', '2026-07-29-documente-receptii-si-loturi')->exists(),
        );

        $migration->up();

        $this->assertStringContainsString(
            'Documente de procesat',
            HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('body_markdown'),
        );
        $this->assertStringContainsString(
            'FEFO',
            HelpArticle::query()->where('slug', 'circuitul-materialelor')->value('body_markdown'),
        );
        $this->assertTrue(
            ReleaseNote::query()->where('slug', '2026-07-29-documente-receptii-si-loturi')->exists(),
        );
        $latestMigration->up();
        $alertMigration->up();
        $custodyMigration->up();
        $orderMigration->up();
        $operationalMigration->up();
        $planningMigration->up();
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
    }

    public function test_transfer_and_consumption_content_migration_is_reversible(): void
    {
        $attachmentControlsMigration = $this->attachmentControlsMigration();
        $transferSourceRefreshMigration = require database_path('migrations/2026_08_01_000031_publish_transfer_source_inventory_refresh.php');
        $documentPreviewMigration = require database_path('migrations/2026_07_31_000030_publish_reception_document_preview_content.php');
        $observationVisibilityMigration = require database_path('migrations/2026_07_31_000029_publish_reception_intake_observation_visibility.php');
        $driverTabsMigration = require database_path('migrations/2026_07_31_000028_publish_driver_active_task_tabs.php');
        $liveFilteringMigration = require database_path('migrations/2026_07_30_000026_publish_live_list_filtering.php');
        $searchableListsMigration = require database_path('migrations/2026_07_30_000025_publish_searchable_entity_lists.php');
        $navigationMigration = require database_path('migrations/2026_07_30_000024_publish_consistent_navigation_and_quantities.php');
        $mobileRefinementMigration = require database_path('migrations/2026_07_30_000023_publish_mobile_interface_refinement_content.php');
        $driverMigration = require database_path('migrations/2026_07_29_000020_publish_driver_mobile_task_content.php');
        $planningMigration = require database_path('migrations/2026_07_29_000018_publish_project_material_planning_content.php');
        $operationalMigration = require database_path('migrations/2026_07_29_000016_publish_operational_improvements_content.php');
        $orderMigration = require database_path('migrations/2026_07_29_000015_publish_negotiated_orders_content.php');
        $custodyMigration = require database_path('migrations/2026_07_29_000013_publish_personal_custody_content.php');
        $alertMigration = require database_path('migrations/2026_07_29_000011_publish_operational_alert_content.php');
        $migration = require database_path('migrations/2026_07_29_000009_publish_transfer_consumption_correction_content.php');

        DB::connection()->pretend(fn () => $migration->up());
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
        $planningMigration->down();
        $operationalMigration->down();
        $orderMigration->down();
        $custodyMigration->down();
        $alertMigration->down();
        $migration->down();

        $this->assertSame(3, HelpArticle::query()->where('slug', 'circuitul-materialelor')->value('current_revision'));
        $this->assertSame(4, HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('current_revision'));
        $this->assertSame(5, HelpArticle::query()->where('slug', 'ghiduri-dupa-rol')->value('current_revision'));
        $this->assertFalse(
            ReleaseNote::query()->where('slug', '2026-07-29-stoc-transferuri-consumuri-corectii')->exists(),
        );

        $migration->up();

        $this->assertStringContainsString(
            'mai multe materiale',
            HelpArticle::query()->where('slug', 'circuitul-materialelor')->value('body_markdown'),
        );
        $this->assertTrue(
            ReleaseNote::query()->where('slug', '2026-07-29-stoc-transferuri-consumuri-corectii')->exists(),
        );
        $alertMigration->up();
        $custodyMigration->up();
        $orderMigration->up();
        $operationalMigration->up();
        $planningMigration->up();
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
    }

    public function test_personal_custody_content_migration_is_reversible(): void
    {
        $attachmentControlsMigration = $this->attachmentControlsMigration();
        $transferSourceRefreshMigration = require database_path('migrations/2026_08_01_000031_publish_transfer_source_inventory_refresh.php');
        $documentPreviewMigration = require database_path('migrations/2026_07_31_000030_publish_reception_document_preview_content.php');
        $observationVisibilityMigration = require database_path('migrations/2026_07_31_000029_publish_reception_intake_observation_visibility.php');
        $driverTabsMigration = require database_path('migrations/2026_07_31_000028_publish_driver_active_task_tabs.php');
        $liveFilteringMigration = require database_path('migrations/2026_07_30_000026_publish_live_list_filtering.php');
        $searchableListsMigration = require database_path('migrations/2026_07_30_000025_publish_searchable_entity_lists.php');
        $navigationMigration = require database_path('migrations/2026_07_30_000024_publish_consistent_navigation_and_quantities.php');
        $mobileRefinementMigration = require database_path('migrations/2026_07_30_000023_publish_mobile_interface_refinement_content.php');
        $driverMigration = require database_path('migrations/2026_07_29_000020_publish_driver_mobile_task_content.php');
        $planningMigration = require database_path('migrations/2026_07_29_000018_publish_project_material_planning_content.php');
        $operationalMigration = require database_path('migrations/2026_07_29_000016_publish_operational_improvements_content.php');
        $orderMigration = require database_path('migrations/2026_07_29_000015_publish_negotiated_orders_content.php');
        $migration = require database_path('migrations/2026_07_29_000013_publish_personal_custody_content.php');

        DB::connection()->pretend(fn () => $migration->up());
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
        $planningMigration->down();
        $operationalMigration->down();
        $orderMigration->down();
        $migration->down();

        $this->assertSame(5, HelpArticle::query()->where('slug', 'circuitul-materialelor')->value('current_revision'));
        $this->assertSame(6, HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('current_revision'));
        $this->assertSame(7, HelpArticle::query()->where('slug', 'ghiduri-dupa-rol')->value('current_revision'));
        $this->assertSame(1, HelpArticle::query()->where('slug', 'statusuri-si-termeni')->value('current_revision'));
        $this->assertFalse(
            ReleaseNote::query()->where('slug', '2026-07-29-custodie-personala-si-retururi')->exists(),
        );

        $migration->up();

        $this->assertStringContainsString(
            'Custodia mea',
            HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('body_markdown'),
        );
        $this->assertStringContainsString(
            'nu scade stocul locației',
            HelpArticle::query()->where('slug', 'circuitul-materialelor')->value('body_markdown'),
        );
        $this->assertTrue(
            ReleaseNote::query()->where('slug', '2026-07-29-custodie-personala-si-retururi')->exists(),
        );
        $orderMigration->up();
        $operationalMigration->up();
        $planningMigration->up();
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
    }

    public function test_negotiated_orders_content_migration_is_reversible(): void
    {
        $attachmentControlsMigration = $this->attachmentControlsMigration();
        $transferSourceRefreshMigration = require database_path('migrations/2026_08_01_000031_publish_transfer_source_inventory_refresh.php');
        $documentPreviewMigration = require database_path('migrations/2026_07_31_000030_publish_reception_document_preview_content.php');
        $observationVisibilityMigration = require database_path('migrations/2026_07_31_000029_publish_reception_intake_observation_visibility.php');
        $driverTabsMigration = require database_path('migrations/2026_07_31_000028_publish_driver_active_task_tabs.php');
        $liveFilteringMigration = require database_path('migrations/2026_07_30_000026_publish_live_list_filtering.php');
        $searchableListsMigration = require database_path('migrations/2026_07_30_000025_publish_searchable_entity_lists.php');
        $navigationMigration = require database_path('migrations/2026_07_30_000024_publish_consistent_navigation_and_quantities.php');
        $mobileRefinementMigration = require database_path('migrations/2026_07_30_000023_publish_mobile_interface_refinement_content.php');
        $driverMigration = require database_path('migrations/2026_07_29_000020_publish_driver_mobile_task_content.php');
        $planningMigration = require database_path('migrations/2026_07_29_000018_publish_project_material_planning_content.php');
        $operationalMigration = require database_path('migrations/2026_07_29_000016_publish_operational_improvements_content.php');
        $migration = require database_path('migrations/2026_07_29_000015_publish_negotiated_orders_content.php');

        DB::connection()->pretend(fn () => $migration->up());
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
        $planningMigration->down();
        $operationalMigration->down();
        $migration->down();

        $this->assertSame(7, HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('current_revision'));
        $this->assertSame(8, HelpArticle::query()->where('slug', 'ghiduri-dupa-rol')->value('current_revision'));
        $this->assertSame(2, HelpArticle::query()->where('slug', 'statusuri-si-termeni')->value('current_revision'));
        $this->assertFalse(
            ReleaseNote::query()->where('slug', '2026-07-29-comenzi-negociate')->exists(),
        );

        $migration->up();

        $this->assertStringContainsString(
            'Comenzi negociate',
            HelpArticle::query()->where('slug', 'pagini-si-operatiuni')->value('body_markdown'),
        );
        $this->assertTrue(
            ReleaseNote::query()->where('slug', '2026-07-29-comenzi-negociate')->exists(),
        );
        $operationalMigration->up();
        $planningMigration->up();
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
    }
}
