<?php

namespace Tests\Feature;

use App\Models\HelpArticle;
use App\Models\ReleaseNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        foreach (['super-admin', 'admin', 'dispecer', 'gestionar-baza', 'sef-santier', 'sofer', 'muncitor', 'contabil', 'user'] as $roleName) {
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

    public function test_initial_articles_have_matching_immutable_revisions(): void
    {
        $articles = HelpArticle::query()->with('revisions')->get();

        $this->assertCount(6, $articles);
        $articles->each(function (HelpArticle $article): void {
            $this->assertSame('published', $article->status);
            $this->assertSame(1, $article->current_revision);
            $this->assertCount(1, $article->revisions);
            $this->assertSame($article->title, $article->revisions->first()->title);
            $this->assertSame($article->body_markdown, $article->revisions->first()->body_markdown);
            $this->assertSame('system', $article->revisions->first()->source);
        });
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
                'Centru de ajutor și noutăți în aplicație',
                'Fluxuri complete pentru transferuri și sarcini',
                'Navigare mai clară în liste',
                'Recepții, consum și vizibilitate operațională',
                'Lansarea aplicației GAFCO Gestiune',
            ])
            ->assertDontSee('Noutate nepublicată');
        $this->actingAs($user)->get(route('release-notes.show', $draft))->assertNotFound();
    }
}
