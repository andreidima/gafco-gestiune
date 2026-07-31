<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RELEASE_SLUG = '2026-08-01-liste-transfer-actualizate-dupa-sursa';

    private const MATERIALS_CHANGE_SUMMARY = 'Reîncărcarea materialelor și echipamentelor la schimbarea sursei.';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->reviseMaterialsArticle();
            $this->publishReleaseNote();
        });
    }

    public function down(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->removeReleaseNote();
            $this->restoreMaterialsArticle();
        });
    }

    private function reviseMaterialsArticle(): void
    {
        $article = $this->lockedArticle('circuitul-materialelor', 8);
        $body = $this->replaceOrFail(
            (string) $article->body_markdown,
            <<<'MARKDOWN'
### Ce poate fi ales într-un transfer

După alegerea locației sursă, formularul afișează numai materialele care au stoc disponibil și echipamentele aflate în acea locație. Cantitatea disponibilă ține cont și de pozițiile deja rezervate în alte transferuri active.

La salvare, aplicația verifică din nou locația, stocul și rezervările. Astfel, o opțiune care nu mai este disponibilă între timp nu poate fi introdusă în transfer.
MARKDOWN,
            <<<'MARKDOWN'
### Ce poate fi ales într-un transfer

După alegerea locației sursă, formularul afișează numai materialele care au stoc disponibil și echipamentele aflate în acea locație. Cantitatea disponibilă ține cont și de pozițiile deja rezervate în alte transferuri active.

La fiecare schimbare a locației sursă, ambele liste se refac integral. Opțiunile și selecțiile din sursa anterioară sunt eliminate, iar materialul sau echipamentul trebuie ales din nou după încărcarea stocului curent.

La salvare, aplicația verifică din nou locația, stocul și rezervările. Astfel, o opțiune care nu mai este disponibilă între timp nu poate fi introdusă în transfer.
MARKDOWN,
        );

        $this->insertRevision($article, 9, $body);
    }

    private function publishReleaseNote(): void
    {
        if (DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->exists()) {
            throw new RuntimeException('Nota despre actualizarea listelor de transfer există deja.');
        }

        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.08.01.1',
            'title' => 'Liste corecte la schimbarea sursei transferului',
            'summary' => 'Materialele și echipamentele disponibile se actualizează corect de fiecare dată când este schimbată locația sursă.',
            'body_markdown' => <<<'MARKDOWN'
# Ce s-a schimbat

- lista materialelor se reîncarcă integral la fiecare schimbare a locației **Din**;
- lista echipamentelor se reîncarcă în același mod;
- opțiunile și selecțiile aparținând sursei anterioare nu mai rămân în formular;
- câmpurile așteaptă finalizarea încărcării înainte să poată fi folosite.

# Ce trebuie să faci

Dacă schimbi locația **Din**, așteaptă actualizarea stocului și alege din nou materialul sau echipamentul pentru fiecare poziție.
MARKDOWN,
            'audience_roles' => json_encode(
                ['super-admin', 'admin', 'dispecer', 'manager', 'sef-santier', 'gestionar-baza'],
                JSON_UNESCAPED_UNICODE,
            ),
            'affected_modules' => json_encode(['Transferuri'], JSON_UNESCAPED_UNICODE),
            'requires_action' => false,
            'status' => 'published',
            'released_at' => '2026-08-01',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function removeReleaseNote(): void
    {
        $note = DB::table('release_notes')
            ->where('slug', self::RELEASE_SLUG)
            ->lockForUpdate()
            ->first();

        if (! $note
            || $note->version !== '2026.08.01.1'
            || $note->title !== 'Liste corecte la schimbarea sursei transferului'
            || $note->status !== 'published'
        ) {
            throw new RuntimeException('Nota despre actualizarea listelor de transfer a fost modificată; revenirea a fost oprită.');
        }

        DB::table('release_notes')->where('id', $note->id)->delete();
    }

    private function lockedArticle(string $slug, int $expectedRevision): object
    {
        $article = DB::table('help_articles')
            ->where('slug', $slug)
            ->lockForUpdate()
            ->first();

        if (! $article || (int) $article->current_revision !== $expectedRevision) {
            $actual = $article ? (int) $article->current_revision : 'inexistent';

            throw new RuntimeException(
                "Articolul {$slug} are revizia {$actual}; era așteptată revizia {$expectedRevision}.",
            );
        }

        return $article;
    }

    private function replaceOrFail(string $body, string $search, string $replacement): string
    {
        if (! str_contains($body, $search)) {
            throw new RuntimeException('Conținutul așteptat nu a fost găsit în articolul de ajutor.');
        }

        return str_replace($search, $replacement, $body);
    }

    private function insertRevision(object $article, int $revision, string $body): void
    {
        $timestamp = now();

        DB::table('help_article_revisions')->insert([
            'help_article_id' => $article->id,
            'revision' => $revision,
            'title' => $article->title,
            'summary' => $article->summary,
            'body_markdown' => $body,
            'change_summary' => self::MATERIALS_CHANGE_SUMMARY,
            'source' => 'system',
            'created_by' => null,
            'published_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        DB::table('help_articles')->where('id', $article->id)->update([
            'body_markdown' => $body,
            'current_revision' => $revision,
            'updated_by' => null,
            'published_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    private function restoreMaterialsArticle(): void
    {
        $article = $this->lockedArticle('circuitul-materialelor', 9);
        $current = DB::table('help_article_revisions')
            ->where('help_article_id', $article->id)
            ->where('revision', 9)
            ->first();

        if (! $current
            || $current->source !== 'system'
            || $current->change_summary !== self::MATERIALS_CHANGE_SUMMARY
            || $current->body_markdown !== $article->body_markdown
        ) {
            throw new RuntimeException('Articolul circuitul-materialelor a fost modificat; revenirea a fost oprită.');
        }

        $previous = DB::table('help_article_revisions')
            ->where('help_article_id', $article->id)
            ->where('revision', 8)
            ->first();

        if (! $previous) {
            throw new RuntimeException('Revizia anterioară a articolului circuitul-materialelor nu există.');
        }

        DB::table('help_article_revisions')
            ->where('help_article_id', $article->id)
            ->where('revision', 9)
            ->delete();

        DB::table('help_articles')->where('id', $article->id)->update([
            'title' => $previous->title,
            'summary' => $previous->summary,
            'body_markdown' => $previous->body_markdown,
            'current_revision' => $previous->revision,
            'updated_by' => $previous->created_by,
            'published_at' => $previous->published_at,
            'updated_at' => now(),
        ]);
    }
};
