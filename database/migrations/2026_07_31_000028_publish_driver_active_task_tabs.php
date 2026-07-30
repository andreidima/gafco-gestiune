<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RELEASE_SLUG = '2026-07-31-sarcini-active-pentru-soferi';

    private const PAGES_CHANGE_SUMMARY = 'Filele Active și Acceptate pentru organizarea sarcinilor șoferului.';

    private const ROLES_CHANGE_SUMMARY = 'Revenirea șoferului la sarcinile active după finalizare.';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->revisePagesArticle();
            $this->reviseRolesArticle();
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
            $this->restoreArticle('ghiduri-dupa-rol', 16, self::ROLES_CHANGE_SUMMARY);
            $this->restoreArticle('pagini-si-operatiuni', 16, self::PAGES_CHANGE_SUMMARY);
        });
    }

    private function revisePagesArticle(): void
    {
        $article = $this->lockedArticle('pagini-si-operatiuni', 15);
        $body = (string) $article->body_markdown;
        $body = $this->replaceOrFail(
            $body,
            'În pagina **Sarcinile mele**, șoferul poate trece rapid între filele **Toate**, **De răspuns**, **De pornit**, **În lucru** și **Finalizate**. Fiecare filă arată și numărul sarcinilor din acea stare.',
            'În pagina **Sarcinile mele**, șoferul poate trece rapid între filele **Active**, **De răspuns**, **Acceptate**, **În lucru** și **Finalizate**. Fiecare filă arată și numărul sarcinilor din acea stare. Fila **Active** se deschide implicit și cuprinde sarcinile la care șoferul mai are de acționat: cele la care trebuie să răspundă, cele acceptate și cele aflate în lucru. Sarcinile finalizate rămân separat în fila **Finalizate**. **Acceptate** sunt sarcinile confirmate de șofer, dar nepornite încă.',
            'pagini-si-operatiuni',
        );
        $body = $this->replaceOrFail(
            $body,
            'În **Sarcinile mele**, filele de stare se așază pe mai multe rânduri. După finalizarea unei sarcini, șoferul revine la lista completă și vede confirmarea temporară.',
            'În **Sarcinile mele**, filele de stare se așază pe mai multe rânduri. După finalizarea unei sarcini, șoferul revine la fila **Active** și vede confirmarea temporară.',
            'pagini-si-operatiuni',
        );

        $this->saveRevision($article, 16, $body, self::PAGES_CHANGE_SUMMARY);
    }

    private function reviseRolesArticle(): void
    {
        $article = $this->lockedArticle('ghiduri-dupa-rol', 15);
        $body = $this->replaceOrFail(
            (string) $article->body_markdown,
            'După **Finalizează**, aplicația revine la lista nefiltrată **Sarcinile mele**.',
            'După **Finalizează**, aplicația revine la fila **Active** din **Sarcinile mele**. Sarcina finalizată rămâne disponibilă în fila **Finalizate**.',
            'ghiduri-dupa-rol',
        );

        $this->saveRevision($article, 16, $body, self::ROLES_CHANGE_SUMMARY);
    }

    private function replaceOrFail(string $body, string $search, string $replacement, string $slug): string
    {
        if (! str_contains($body, $search)) {
            throw new RuntimeException("Conținutul așteptat din articolul {$slug} nu a fost găsit.");
        }

        return str_replace($search, $replacement, $body);
    }

    private function saveRevision(object $article, int $revision, string $body, string $changeSummary): void
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

    private function publishReleaseNote(): void
    {
        if (DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->exists()) {
            throw new RuntimeException('Nota despre sarcinile active ale șoferilor există deja.');
        }

        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.07.31.1',
            'title' => 'Sarcinile active, separate de cele finalizate',
            'summary' => 'Șoferii văd implicit doar sarcinile la care mai au de acționat, iar sarcinile acceptate sunt denumite mai clar.',
            'body_markdown' => <<<'MARKDOWN'
# Ce s-a schimbat

- fila **Active** înlocuiește fila **Toate** și se deschide implicit;
- **Active** cuprinde sarcinile **De răspuns**, **Acceptate** și **În lucru**;
- sarcinile finalizate nu mai aglomerează lista de lucru și rămân disponibile în fila **Finalizate**;
- fila **De pornit** a fost redenumită **Acceptate**.

# Ce înseamnă „Acceptate”

Sunt sarcinile pe care șoferul le-a acceptat, dar pe care nu le-a pornit încă. După apăsarea acțiunii **Pornește sarcina**, acestea trec în fila **În lucru**.

# Ce trebuie să facă șoferul

Nu este necesară nicio configurare. La deschiderea paginii **Sarcinile mele**, șoferul vede direct sarcinile active și poate folosi fila **Finalizate** pentru istoric.
MARKDOWN,
            'audience_roles' => json_encode(
                ['sofer', 'dispecer', 'manager', 'admin', 'super-admin'],
                JSON_UNESCAPED_UNICODE,
            ),
            'affected_modules' => json_encode(
                ['Sarcini șoferi', 'Centru de ajutor'],
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
            || $note->version !== '2026.07.31.1'
            || $note->title !== 'Sarcinile active, separate de cele finalizate'
            || $note->status !== 'published'
        ) {
            throw new RuntimeException('Nota despre sarcinile active ale șoferilor a fost modificată; revenirea a fost oprită.');
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

            throw new RuntimeException("Articolul {$slug} are revizia {$actual}; era așteptată revizia {$expectedRevision}.");
        }

        return $article;
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
