<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RELEASE_SLUG = '2026-08-04-navigare-echipamente-si-protectii-locatii-transferuri';

    private const PAGES_CHANGE_SUMMARY = 'Revenirea în lista de echipamente și condițiile de dezactivare a locațiilor.';

    private const MATERIALS_CHANGE_SUMMARY = 'Alegerea unor locații diferite și protejarea fluxurilor active.';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->revisePagesArticle();
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
            $this->restoreArticle('circuitul-materialelor', 10, self::MATERIALS_CHANGE_SUMMARY);
            $this->restoreArticle('pagini-si-operatiuni', 22, self::PAGES_CHANGE_SUMMARY);
        });
    }

    private function revisePagesArticle(): void
    {
        $article = $this->lockedArticle('pagini-si-operatiuni', 21);
        $body = rtrim((string) $article->body_markdown).<<<'MARKDOWN'


## Revenirea din editarea echipamentelor

Când deschizi un echipament dintr-o listă filtrată sau dintr-o anumită pagină, acțiunile **Salvează modificările**, **Renunță** și **Înapoi** te readuc în aceeași listă. Filtrele și pagina sunt păstrate atât pe calculator, cât și pe telefon.

## Dezactivarea unei locații

O locație sau o bază poate fi dezactivată numai după închiderea activității sale. Înainte de dezactivare trebuie mutate toate echipamentele alocate, consumat sau transferat stocul pozitiv, rezolvate aprobările în așteptare și încheiate ori anulate transferurile active.

Dacă există mai multe situații nerezolvate, formularul le afișează împreună. Celelalte modificări din formular nu sunt salvate până când blocajele nu sunt rezolvate.
MARKDOWN;

        $this->insertRevision($article, 22, $body, self::PAGES_CHANGE_SUMMARY);
    }

    private function reviseMaterialsArticle(): void
    {
        $article = $this->lockedArticle('circuitul-materialelor', 9);
        $body = rtrim((string) $article->body_markdown).<<<'MARKDOWN'


## Locațiile unui transfer

Locația sursă și locația de destinație trebuie să fie diferite. După alegerea uneia dintre ele, aceeași locație nu mai poate fi selectată în celălalt câmp. Verificarea se face și la salvare.

O locație implicată într-un transfer activ sau într-o aprobare în așteptare nu poate fi dezactivată. Finalizează sau anulează fluxul înainte de dezactivarea locației.
MARKDOWN;

        $this->insertRevision($article, 10, $body, self::MATERIALS_CHANGE_SUMMARY);
    }

    private function publishReleaseNote(): void
    {
        if (DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->exists()) {
            throw new RuntimeException('Nota despre echipamente, locații și transferuri există deja.');
        }

        $timestamp = now();
        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.08.04.1',
            'title' => 'Navigare mai clară și protecții pentru locații și transferuri',
            'summary' => 'Listele de echipamente își păstrează contextul, iar locațiile și transferurile sunt protejate de selecții sau dezactivări greșite.',
            'body_markdown' => <<<'MARKDOWN'
# Ce s-a schimbat

- după modificarea unui echipament sau renunțarea la modificări, revii la lista, filtrele și pagina din care ai pornit;
- revenirea corectă funcționează și din lista pentru telefon;
- o locație nu poate fi dezactivată cât timp are echipamente alocate, stoc pozitiv, aprobări în așteptare sau transferuri active;
- mesajul de validare arată toate situațiile care trebuie rezolvate înainte de dezactivare;
- într-un transfer, locația aleasă ca sursă nu mai poate fi aleasă și ca destinație, și invers.

# Ce trebuie să faci

Înainte de dezactivarea unei locații, mută echipamentele și stocul rămas și încheie aprobările și transferurile active. La un transfer nou sau modificat, alege două locații diferite.
MARKDOWN,
            'audience_roles' => json_encode(
                ['super-admin', 'admin', 'dispecer', 'manager', 'sef-santier', 'gestionar-baza', 'sofer', 'muncitor', 'contabil'],
                JSON_UNESCAPED_UNICODE,
            ),
            'affected_modules' => json_encode(
                ['Echipamente', 'Locații', 'Transferuri'],
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
            || $note->version !== '2026.08.04.1'
            || $note->title !== 'Navigare mai clară și protecții pentru locații și transferuri'
            || $note->status !== 'published'
        ) {
            throw new RuntimeException('Nota despre echipamente, locații și transferuri a fost modificată; revenirea a fost oprită.');
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
