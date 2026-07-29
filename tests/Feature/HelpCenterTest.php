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

        $this->assertCount(6, $articles);
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

    public function test_minor_corrections_are_not_published_in_help_or_release_notes(): void
    {
        $article = HelpArticle::query()
            ->with('revisions')
            ->where('slug', 'pagini-si-operatiuni')
            ->sole();

        $this->assertSame(9, $article->current_revision);
        $this->assertCount(9, $article->revisions);
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
        $operationalMigration = require database_path('migrations/2026_07_29_000016_publish_operational_improvements_content.php');
        $orderMigration = require database_path('migrations/2026_07_29_000015_publish_negotiated_orders_content.php');
        $custodyMigration = require database_path('migrations/2026_07_29_000013_publish_personal_custody_content.php');
        $alertMigration = require database_path('migrations/2026_07_29_000011_publish_operational_alert_content.php');
        $latestMigration = require database_path('migrations/2026_07_29_000009_publish_transfer_consumption_correction_content.php');
        $receptionMigration = require database_path('migrations/2026_07_29_000007_publish_reception_workflow_help_and_release_note.php');
        $currentMigration = require database_path('migrations/2026_07_29_000005_publish_saved_filters_and_account_protection_content.php');
        $migration = require database_path('migrations/2026_07_29_000002_remove_minor_corrections_help_and_release_note.php');

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
                'Mai multă claritate în activitatea zilnică',
                'Comenzi negociate transformabile în recepții',
                'Custodie personală pentru materiale și echipamente',
                'Alerte pentru stoc și documente de recepție',
                'Stoc disponibil în transferuri și consumuri corectabile',
                'Documente, recepții complete și loturi la consum',
                'Filtre memorate și administrare standardizată',
                'Schimbare rapidă între utilizatori',
                'Fișă completă de inventar pentru materiale',
                'Centru de ajutor și noutăți în aplicație',
                'Fluxuri complete pentru transferuri și sarcini',
                'Navigare mai clară în liste',
            ])
            ->assertDontSee('Afișare completă a rolurilor și a listelor')
            ->assertDontSee('Noutate nepublicată');
        $this->actingAs($user)
            ->get(route('release-notes.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Recepții, consum și vizibilitate operațională')
            ->assertSee('Lansarea aplicației GAFCO Gestiune')
            ->assertDontSee('Noutate nepublicată');
        $this->actingAs($user)->get(route('release-notes.show', $draft))->assertNotFound();
    }

    public function test_impersonation_release_updates_help_without_overwriting_history(): void
    {
        $article = HelpArticle::query()
            ->with('revisions')
            ->where('slug', 'ghiduri-dupa-rol')
            ->sole();

        $this->assertSame(10, $article->current_revision);
        $this->assertCount(10, $article->revisions);
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

    public function test_saved_filters_content_migration_supports_sql_preview_and_is_reversible(): void
    {
        $operationalMigration = require database_path('migrations/2026_07_29_000016_publish_operational_improvements_content.php');
        $orderMigration = require database_path('migrations/2026_07_29_000015_publish_negotiated_orders_content.php');
        $custodyMigration = require database_path('migrations/2026_07_29_000013_publish_personal_custody_content.php');
        $alertMigration = require database_path('migrations/2026_07_29_000011_publish_operational_alert_content.php');
        $latestMigration = require database_path('migrations/2026_07_29_000009_publish_transfer_consumption_correction_content.php');
        $receptionMigration = require database_path('migrations/2026_07_29_000007_publish_reception_workflow_help_and_release_note.php');
        $migration = require database_path('migrations/2026_07_29_000005_publish_saved_filters_and_account_protection_content.php');

        DB::connection()->pretend(fn () => $migration->up());
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
    }

    public function test_reception_content_migration_preserves_revisions_and_is_reversible(): void
    {
        $operationalMigration = require database_path('migrations/2026_07_29_000016_publish_operational_improvements_content.php');
        $orderMigration = require database_path('migrations/2026_07_29_000015_publish_negotiated_orders_content.php');
        $custodyMigration = require database_path('migrations/2026_07_29_000013_publish_personal_custody_content.php');
        $alertMigration = require database_path('migrations/2026_07_29_000011_publish_operational_alert_content.php');
        $latestMigration = require database_path('migrations/2026_07_29_000009_publish_transfer_consumption_correction_content.php');
        $migration = require database_path('migrations/2026_07_29_000007_publish_reception_workflow_help_and_release_note.php');

        DB::connection()->pretend(fn () => $migration->up());
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
    }

    public function test_transfer_and_consumption_content_migration_is_reversible(): void
    {
        $operationalMigration = require database_path('migrations/2026_07_29_000016_publish_operational_improvements_content.php');
        $orderMigration = require database_path('migrations/2026_07_29_000015_publish_negotiated_orders_content.php');
        $custodyMigration = require database_path('migrations/2026_07_29_000013_publish_personal_custody_content.php');
        $alertMigration = require database_path('migrations/2026_07_29_000011_publish_operational_alert_content.php');
        $migration = require database_path('migrations/2026_07_29_000009_publish_transfer_consumption_correction_content.php');

        DB::connection()->pretend(fn () => $migration->up());
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
    }

    public function test_personal_custody_content_migration_is_reversible(): void
    {
        $operationalMigration = require database_path('migrations/2026_07_29_000016_publish_operational_improvements_content.php');
        $orderMigration = require database_path('migrations/2026_07_29_000015_publish_negotiated_orders_content.php');
        $migration = require database_path('migrations/2026_07_29_000013_publish_personal_custody_content.php');

        DB::connection()->pretend(fn () => $migration->up());
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
    }

    public function test_negotiated_orders_content_migration_is_reversible(): void
    {
        $operationalMigration = require database_path('migrations/2026_07_29_000016_publish_operational_improvements_content.php');
        $migration = require database_path('migrations/2026_07_29_000015_publish_negotiated_orders_content.php');

        DB::connection()->pretend(fn () => $migration->up());
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
    }
}
