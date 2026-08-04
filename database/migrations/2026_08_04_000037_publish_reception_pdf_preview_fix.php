<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RELEASE_SLUG = '2026-08-04-previzualizare-pdf-documente-receptie';

    private const PAGES_CHANGE_SUMMARY = 'Afișarea sigură a documentelor PDF în previzualizarea recepțiilor.';

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
            $this->restoreArticle('pagini-si-operatiuni', 23, self::PAGES_CHANGE_SUMMARY);
        });
    }

    private function revisePagesArticle(): void
    {
        $article = $this->lockedArticle('pagini-si-operatiuni', 22);
        $body = rtrim((string) $article->body_markdown).<<<'MARKDOWN'


## Fișiere PDF în previzualizarea recepțiilor

Documentele PDF atașate unei înregistrări din **Documente de procesat** sau unei recepții sunt afișate pagină cu pagină direct în aplicație. Fotografiile se deschid în aceeași previzualizare, iar butonul de descărcare rămâne disponibil separat pentru fiecare document.

Dacă un PDF este deteriorat sau nu poate fi afișat, aplicația arată un mesaj clar. Folosește butonul de descărcare din partea de sus pentru a deschide fișierul în altă aplicație.
MARKDOWN;

        $this->insertRevision($article, 23, $body, self::PAGES_CHANGE_SUMMARY);
    }

    private function publishReleaseNote(): void
    {
        if (DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->exists()) {
            throw new RuntimeException('Nota despre previzualizarea documentelor PDF există deja.');
        }

        $timestamp = now();
        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.08.04.3',
            'title' => 'Documentele PDF se afișează corect în recepții',
            'summary' => 'Fișierele PDF atașate recepțiilor se deschid pagină cu pagină în previzualizarea aplicației.',
            'body_markdown' => <<<'MARKDOWN'
# Ce s-a schimbat

- documentele PDF din **Documente de procesat** și **Recepții** sunt afișate pagină cu pagină direct în aplicație;
- previzualizarea nu mai depinde de suportul PDF al browserului;
- fotografiile, filele documentelor și controalele existente rămân neschimbate;
- dacă un PDF este deteriorat sau nu poate fi afișat, apare un mesaj clar, iar descărcarea rămâne disponibilă.

# Ce trebuie să faci

Apasă pe numele documentului PDF pentru a-l previzualiza. Dacă aplicația anunță că documentul nu poate fi afișat, folosește butonul de descărcare din partea de sus.
MARKDOWN,
            'audience_roles' => json_encode(
                ['super-admin', 'admin', 'dispecer', 'manager', 'sef-santier', 'gestionar-baza', 'muncitor', 'contabil'],
                JSON_UNESCAPED_UNICODE,
            ),
            'affected_modules' => json_encode(
                ['Documente de procesat', 'Recepții'],
                JSON_UNESCAPED_UNICODE,
            ),
            'requires_action' => false,
            'status' => 'published',
            'released_at' => '2026-08-04',
            'published_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    private function removeReleaseNote(): void
    {
        $note = DB::table('release_notes')
            ->where('slug', self::RELEASE_SLUG)
            ->lockForUpdate()
            ->first();

        if (! $note
            || $note->version !== '2026.08.04.3'
            || $note->title !== 'Documentele PDF se afișează corect în recepții'
            || $note->status !== 'published'
        ) {
            throw new RuntimeException('Nota despre previzualizarea documentelor PDF a fost modificată; revenirea a fost oprită.');
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

    private function insertRevision(object $article, int $revision, string $body, string $changeSummary): void
    {
        $timestamp = now();

        DB::table('help_article_revisions')->insert([
            'help_article_id' => $article->id,
            'revision' => $revision,
            'title' => $article->title,
            'summary' => $article->summary,
            'body_markdown' => $body,
            'change_summary' => $changeSummary,
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

    private function restoreArticle(string $slug, int $currentRevision, string $changeSummary): void
    {
        $article = $this->lockedArticle($slug, $currentRevision);
        $current = DB::table('help_article_revisions')
            ->where('help_article_id', $article->id)
            ->where('revision', $currentRevision)
            ->first();

        if (! $current
            || $current->source !== 'system'
            || $current->change_summary !== $changeSummary
            || $current->body_markdown !== $article->body_markdown
        ) {
            throw new RuntimeException("Articolul {$slug} a fost modificat; revenirea a fost oprită.");
        }

        $previous = DB::table('help_article_revisions')
            ->where('help_article_id', $article->id)
            ->where('revision', $currentRevision - 1)
            ->first();

        if (! $previous) {
            throw new RuntimeException("Revizia anterioară a articolului {$slug} nu există.");
        }

        DB::table('help_article_revisions')
            ->where('help_article_id', $article->id)
            ->where('revision', $currentRevision)
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
