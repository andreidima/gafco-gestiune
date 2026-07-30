<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ARTICLE_SLUG = 'pagini-si-operatiuni';

    private const RELEASE_SLUG = '2026-07-30-filtrare-live-fara-pierderea-focusului';

    private const CHANGE_SUMMARY = 'Filtrarea listelor fără întreruperea tastării sau reîncărcarea completă a paginii.';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $article = $this->lockedArticle(14);
            $body = rtrim((string) $article->body_markdown)."\n".<<<'MARKDOWN'

## Filtrarea listelor fără întreruperea tastării

În paginile cu tabele sau carduri, rezultatele se actualizează acum fără reîncărcarea completă a paginii. Câmpul de căutare rămâne activ, astfel încât poți continua să scrii fără să pierzi litere sau poziția cursorului.

Căutarea automată pornește după minimum două caractere și se aplică după o scurtă pauză. Ștergerea textului afișează din nou toate rezultatele permise. Pentru o căutare formată dintr-un singur caracter poți folosi butonul de căutare sau tasta Enter.

Filtrele din liste, datele și bifele actualizează rezultatele imediat. Adresa paginii se actualizează împreună cu filtrele, iar paginarea, reîmprospătarea și butoanele Înapoi/Înainte continuă să funcționeze.
MARKDOWN;
            $timestamp = now();

            DB::table('help_article_revisions')->insert([
                'help_article_id' => $article->id,
                'revision' => 15,
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
                'current_revision' => 15,
                'updated_by' => null,
                'published_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

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
            $article = $this->lockedArticle(15);
            $current = DB::table('help_article_revisions')
                ->where('help_article_id', $article->id)
                ->where('revision', 15)
                ->first();

            if (! $current
                || $current->source !== 'system'
                || $current->change_summary !== self::CHANGE_SUMMARY
                || $current->body_markdown !== $article->body_markdown
            ) {
                throw new RuntimeException('Articolul despre pagini și operațiuni a fost modificat; revenirea a fost oprită.');
            }

            $previous = DB::table('help_article_revisions')
                ->where('help_article_id', $article->id)
                ->where('revision', 14)
                ->first();

            if (! $previous) {
                throw new RuntimeException('Revizia anterioară a articolului despre pagini și operațiuni nu există.');
            }

            DB::table('help_article_revisions')
                ->where('help_article_id', $article->id)
                ->where('revision', 15)
                ->delete();

            DB::table('help_articles')->where('id', $article->id)->update([
                'title' => $previous->title,
                'summary' => $previous->summary,
                'body_markdown' => $previous->body_markdown,
                'current_revision' => 14,
                'updated_by' => $previous->created_by,
                'published_at' => $previous->published_at,
                'updated_at' => now(),
            ]);
        });
    }

    private function publishReleaseNote(): void
    {
        if (DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->exists()) {
            throw new RuntimeException('Nota despre filtrarea live există deja.');
        }

        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.07.30.4',
            'title' => 'Filtrare rapidă fără întreruperea tastării',
            'summary' => 'Listele se filtrează fără reîncărcarea completă a paginii, iar câmpul de căutare rămâne activ.',
            'body_markdown' => <<<'MARKDOWN'
# Ce s-a schimbat

- rezultatele listelor se actualizează fără reîncărcarea completă a paginii;
- câmpul de căutare rămâne activ, astfel încât poți continua să scrii;
- căutarea automată pornește după minimum două caractere;
- filtrele din liste, datele și bifele actualizează rezultatele imediat;
- adresa paginii și paginarea rămân sincronizate cu filtrele aplicate.

# Ce trebuie să facă utilizatorul

Scrie minimum două caractere și continuă căutarea normal. Pentru un singur caracter, folosește butonul de căutare sau tasta Enter. Nu este necesară nicio configurare.
MARKDOWN,
            'audience_roles' => json_encode([
                'super-admin', 'admin', 'dispecer', 'manager', 'sef-santier',
                'gestionar-baza', 'sofer', 'muncitor', 'contabil', 'user',
            ], JSON_UNESCAPED_UNICODE),
            'affected_modules' => json_encode([
                'Liste și filtre', 'Locații', 'Inventar', 'Furnizori',
                'Recepții', 'Transferuri', 'Consum', 'Proiecte', 'Sarcini',
            ], JSON_UNESCAPED_UNICODE),
            'requires_action' => false,
            'status' => 'published',
            'released_at' => '2026-07-30',
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
            || $note->version !== '2026.07.30.4'
            || $note->title !== 'Filtrare rapidă fără întreruperea tastării'
            || $note->status !== 'published'
        ) {
            throw new RuntimeException('Nota despre filtrarea live a fost modificată; revenirea a fost oprită.');
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

            throw new RuntimeException("Articolul despre pagini și operațiuni are revizia {$actual}; era așteptată revizia {$expectedRevision}.");
        }

        return $article;
    }
};
