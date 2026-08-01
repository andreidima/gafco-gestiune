<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RELEASE_SLUG = '2026-08-01-butoane-fisiere-clare-pe-mobil';

    private const PAGES_CHANGE_SUMMARY = 'Butoane compacte și clare pentru eliminarea fișierelor.';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->revisePagesArticle();
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
            $this->restoreArticle();
        });
    }

    private function revisePagesArticle(): void
    {
        $article = $this->lockedArticle(18);
        $body = $this->replaceOrFail(
            (string) $article->body_markdown,
            <<<'MARKDOWN'
În listele în care pot fi adăugate mai multe materiale, poziții sau fișiere, butonul de adăugare apare după ultima înregistrare. După adăugare, aplicația mută atenția la noua poziție.
MARKDOWN,
            <<<'MARKDOWN'
În listele în care pot fi adăugate mai multe materiale, poziții sau fișiere, butonul de adăugare apare după ultima înregistrare. După adăugare, aplicația mută atenția la noua poziție.

În formularul **Trimite documente**, fiecare fișier suplimentar poate fi eliminat prin butonul roșu aflat lângă tipul documentului. Primul fișier este obligatoriu, de aceea butonul de eliminare apare numai după adăugarea unui al doilea fișier. Pe telefon, butonul rămâne compact pentru a păstra formularul ușor de urmărit.
MARKDOWN,
        );

        $this->insertRevision($article, 19, $body);
    }

    private function publishReleaseNote(): void
    {
        if (DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->exists()) {
            throw new RuntimeException('Nota despre butoanele fișierelor există deja.');
        }

        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.08.01.2',
            'title' => 'Fișiere mai ușor de gestionat pe telefon',
            'summary' => 'Butonul pentru eliminarea unui fișier este compact, vizibil și așezat lângă tipul documentului.',
            'body_markdown' => <<<'MARKDOWN'
# Ce s-a schimbat

- butonul pentru eliminarea unui fișier este pătrat și roșu, cu o pictogramă clară;
- pe telefon, butonul apare lângă tipul documentului și nu mai ocupă toată lățimea formularului;
- primul fișier obligatoriu nu mai afișează un buton care nu poate elimina poziția;
- butonul **Adaugă fișier** rămâne după ultimul fișier din listă.

# Ce trebuie să faci

Pentru a elimina un fișier suplimentar, apasă butonul roșu cu pictograma coșului. Dacă există un singur fișier obligatoriu, alege fișierul direct din câmpul **Fișier**.
MARKDOWN,
            'audience_roles' => json_encode(
                ['super-admin', 'admin', 'dispecer', 'manager', 'sef-santier', 'gestionar-baza', 'muncitor'],
                JSON_UNESCAPED_UNICODE,
            ),
            'affected_modules' => json_encode(['Documente de procesat', 'Recepții'], JSON_UNESCAPED_UNICODE),
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
            || $note->version !== '2026.08.01.2'
            || $note->title !== 'Fișiere mai ușor de gestionat pe telefon'
            || $note->status !== 'published'
        ) {
            throw new RuntimeException('Nota despre butoanele fișierelor a fost modificată; revenirea a fost oprită.');
        }

        DB::table('release_notes')->where('id', $note->id)->delete();
    }

    private function lockedArticle(int $expectedRevision): object
    {
        $article = DB::table('help_articles')
            ->where('slug', 'pagini-si-operatiuni')
            ->lockForUpdate()
            ->first();

        if (! $article || (int) $article->current_revision !== $expectedRevision) {
            $actual = $article ? (int) $article->current_revision : 'inexistent';

            throw new RuntimeException(
                "Articolul pagini-si-operatiuni are revizia {$actual}; era așteptată revizia {$expectedRevision}.",
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
            'change_summary' => self::PAGES_CHANGE_SUMMARY,
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

    private function restoreArticle(): void
    {
        $article = $this->lockedArticle(19);
        $current = DB::table('help_article_revisions')
            ->where('help_article_id', $article->id)
            ->where('revision', 19)
            ->first();

        if (! $current
            || $current->source !== 'system'
            || $current->change_summary !== self::PAGES_CHANGE_SUMMARY
            || $current->body_markdown !== $article->body_markdown
        ) {
            throw new RuntimeException('Articolul pagini-si-operatiuni a fost modificat; revenirea a fost oprită.');
        }

        $previous = DB::table('help_article_revisions')
            ->where('help_article_id', $article->id)
            ->where('revision', 18)
            ->first();

        if (! $previous) {
            throw new RuntimeException('Revizia anterioară a articolului pagini-si-operatiuni nu există.');
        }

        DB::table('help_article_revisions')
            ->where('help_article_id', $article->id)
            ->where('revision', 19)
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
