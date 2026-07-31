<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ARTICLE_SLUG = 'pagini-si-operatiuni';

    private const RELEASE_SLUG = '2026-07-31-observatii-vizibile-documente-procesat';

    private const CHANGE_SUMMARY = 'Observațiile sunt vizibile direct în lista documentelor de procesat.';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->reviseArticle();
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

    private function reviseArticle(): void
    {
        $article = $this->lockedArticle(16);
        $body = $this->replaceOrFail(
            (string) $article->body_markdown,
            'Butonul **Trimite documente** permite încărcarea mai multor fotografii sau fișiere PDF pentru o locație. Încărcarea nu modifică stocul.',
            <<<'MARKDOWN'
Butonul **Trimite documente** permite încărcarea mai multor fotografii sau fișiere PDF pentru o locație. Încărcarea nu modifică stocul.

Observațiile completate apar direct în lista **Documente de procesat**: pe cardurile de telefon și în coloana **Observații** de pe calculator. Înregistrările fără observații nu afișează un câmp gol pe card. Pe calculator, data și ora apar sub numărul înregistrării.
MARKDOWN,
        );

        $timestamp = now();

        DB::table('help_article_revisions')->insert([
            'help_article_id' => $article->id,
            'revision' => 17,
            'title' => $article->title,
            'summary' => $article->summary,
            'body_markdown' => $body,
            'change_summary' => self::CHANGE_SUMMARY,
            'source' => 'system',
            'created_by' => null,
            'published_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        DB::table('help_articles')->where('id', $article->id)->update([
            'body_markdown' => $body,
            'current_revision' => 17,
            'updated_by' => null,
            'published_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    private function publishReleaseNote(): void
    {
        if (DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->exists()) {
            throw new RuntimeException('Nota despre observațiile documentelor de procesat există deja.');
        }

        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.07.31.2',
            'title' => 'Observații vizibile în lista documentelor de procesat',
            'summary' => 'Observațiile completate pot fi citite direct din listă, atât pe telefon, cât și pe calculator.',
            'body_markdown' => <<<'MARKDOWN'
# Ce s-a schimbat

- observațiile completate sunt afișate direct pe cardurile din varianta pentru telefon;
- pe calculator, observațiile apar într-o coloană nouă, între **Locație** și **Trimis de**;
- data și ora sunt afișate sub numărul înregistrării, pentru o citire mai clară a listei;
- înregistrările fără observații nu afișează un câmp gol pe cardurile de telefon.

# Ce trebuie să faci

Nu este necesară nicio configurare. Deschide pagina **Documente de procesat** pentru a vedea observațiile disponibile direct în listă.
MARKDOWN,
            'audience_roles' => json_encode(
                ['super-admin', 'admin', 'dispecer', 'manager', 'sef-santier', 'gestionar-baza', 'muncitor'],
                JSON_UNESCAPED_UNICODE,
            ),
            'affected_modules' => json_encode(
                ['Documente de procesat', 'Centru de ajutor'],
                JSON_UNESCAPED_UNICODE,
            ),
            'requires_action' => false,
            'status' => 'published',
            'released_at' => '2026-07-31',
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
            || $note->version !== '2026.07.31.2'
            || $note->title !== 'Observații vizibile în lista documentelor de procesat'
            || $note->status !== 'published'
        ) {
            throw new RuntimeException('Nota despre observațiile documentelor de procesat a fost modificată; revenirea a fost oprită.');
        }

        DB::table('release_notes')->where('id', $note->id)->delete();
    }

    private function lockedArticle(int $expectedRevision): object
    {
        $article = DB::table('help_articles')
            ->where('slug', self::ARTICLE_SLUG)
            ->lockForUpdate()
            ->first();

        if (! $article || (int) $article->current_revision !== $expectedRevision) {
            $actual = $article ? (int) $article->current_revision : 'inexistent';

            throw new RuntimeException(
                'Articolul '.self::ARTICLE_SLUG." are revizia {$actual}; era așteptată revizia {$expectedRevision}.",
            );
        }

        return $article;
    }

    private function replaceOrFail(string $body, string $search, string $replacement): string
    {
        if (! str_contains($body, $search)) {
            throw new RuntimeException('Conținutul așteptat din articolul '.self::ARTICLE_SLUG.' nu a fost găsit.');
        }

        return str_replace($search, $replacement, $body);
    }

    private function restoreArticle(): void
    {
        $article = $this->lockedArticle(17);
        $current = DB::table('help_article_revisions')
            ->where('help_article_id', $article->id)
            ->where('revision', 17)
            ->first();

        if (! $current
            || $current->source !== 'system'
            || $current->change_summary !== self::CHANGE_SUMMARY
            || $current->body_markdown !== $article->body_markdown
        ) {
            throw new RuntimeException('Articolul '.self::ARTICLE_SLUG.' a fost modificat; revenirea a fost oprită.');
        }

        $previous = DB::table('help_article_revisions')
            ->where('help_article_id', $article->id)
            ->where('revision', 16)
            ->first();

        if (! $previous) {
            throw new RuntimeException('Revizia anterioară a articolului '.self::ARTICLE_SLUG.' nu există.');
        }

        DB::table('help_article_revisions')
            ->where('help_article_id', $article->id)
            ->where('revision', 17)
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
