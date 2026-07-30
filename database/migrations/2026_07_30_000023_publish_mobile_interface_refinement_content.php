<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RELEASE_SLUG = '2026-07-30-interfata-mobila-compacta';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->reviseArticle(
                'pagini-si-operatiuni',
                11,
                12,
                <<<'MARKDOWN'

## Interfața mobilă compactă

Pe telefoane, paginile folosesc margini și spațieri mai mici pentru toate rolurile, fără a micșora acțiunile importante. Pe calculator, spațierea rămâne neschimbată.

În **Sarcinile mele**, filele de stare se așază pe mai multe rânduri. După finalizarea unei sarcini, șoferul revine la lista completă și vede confirmarea temporară.

Când există deja o estimare, pagina arată un rezumat compact. În primele cinci minute este disponibilă acțiunea **Corectează**, iar apoi acțiunea **Estimare nouă**. Formularul se deschide numai la cerere.
MARKDOWN,
                'Navigarea mobilă, pașii șoferului și istoricul estimărilor din sarcini.',
            );

            $this->reviseArticle(
                'ghiduri-dupa-rol',
                12,
                13,
                <<<'MARKDOWN'

## Șoferul: estimări compacte și finalizare

Prima estimare rămâne deschisă și este completată cu o oră înainte. După salvare, ora și observația apar într-un rezumat compact. Folosește **Corectează** în primele cinci minute sau **Estimare nouă** după expirarea perioadei.

După **Finalizează**, aplicația revine la lista nefiltrată **Sarcinile mele**.
MARKDOWN,
                'Responsabilitatea șoferului pentru estimare, pornire și actualizări succesive.',
            );

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
            $this->restoreArticle('ghiduri-dupa-rol', 13, 'Responsabilitatea șoferului pentru estimare, pornire și actualizări succesive.');
            $this->restoreArticle('pagini-si-operatiuni', 12, 'Navigarea mobilă, pașii șoferului și istoricul estimărilor din sarcini.');
        });
    }

    private function reviseArticle(
        string $slug,
        int $expectedRevision,
        int $nextRevision,
        string $appendix,
        string $changeSummary,
    ): void {
        $article = $this->lockedArticle($slug, $expectedRevision);
        $body = rtrim((string) $article->body_markdown)."\n".$appendix;
        $timestamp = now();

        DB::table('help_article_revisions')->insert([
            'help_article_id' => $article->id,
            'revision' => $nextRevision,
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
            'current_revision' => $nextRevision,
            'updated_by' => null,
            'published_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    private function publishReleaseNote(): void
    {
        if (DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->exists()) {
            throw new RuntimeException('Nota despre sarcinile șoferului pe mobil există deja.');
        }

        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.07.30.1',
            'title' => 'Interfață mobilă mai compactă și sarcini mai clare',
            'summary' => 'Toate rolurile folosesc mai eficient ecranul mobil, iar șoferii gestionează mai simplu estimările și finalizarea sarcinilor.',
            'body_markdown' => <<<'MARKDOWN'
# Ce s-a schimbat

- paginile autentificate folosesc mai bine spațiul disponibil pe telefon;
- dashboardul șoferului are un antet compact;
- filele sarcinilor se așază pe mai multe rânduri;
- estimarea existentă apare într-un rezumat compact;
- șoferul poate alege **Corectează** sau **Estimare nouă**, după caz;
- după finalizare, șoferul revine la lista completă de sarcini.

# Ce trebuie să facă utilizatorul

Nu este necesară nicio configurare. Șoferul deschide formularul numai când dorește să corecteze sau să comunice o estimare nouă.
MARKDOWN,
            'audience_roles' => json_encode(['sofer', 'dispecer', 'manager', 'admin', 'super-admin'], JSON_UNESCAPED_UNICODE),
            'affected_modules' => json_encode(['Sarcini șoferi', 'Centru de ajutor'], JSON_UNESCAPED_UNICODE),
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
            || $note->version !== '2026.07.30.1'
            || $note->title !== 'Interfață mobilă mai compactă și sarcini mai clare'
            || $note->status !== 'published'
        ) {
            throw new RuntimeException('Nota despre sarcinile șoferului a fost modificată; revenirea a fost oprită.');
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
