<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RELEASE_SLUG = '2026-07-29-sarcini-sofer-optimizate-pentru-mobil';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->reviseArticle(
                'pagini-si-operatiuni',
                10,
                11,
                <<<'MARKDOWN'

## Sarcinile șoferului pe mobil

În pagina **Sarcinile mele**, șoferul poate trece rapid între filele **Toate**, **De răspuns**, **De pornit**, **În lucru** și **Finalizate**. Fiecare filă arată și numărul sarcinilor din acea stare.

Cardurile de pe mobil păstrează la vedere numai informațiile necesare în deplasare: traseul, termenul, estimarea proprie, starea și acțiunea următoare. Numele șoferului nu este repetat în propriul spațiu de lucru.

În pagina sarcinii, blocul **Acțiunea următoare** apare înaintea detaliilor:

1. acceptă sau refuză alocarea;
2. salvează estimarea de finalizare;
3. pornește sarcina printr-o acțiune separată;
4. finalizează sarcina când transportul s-a încheiat.

Ora estimată este completată automat cu o oră în avans, iar observația este opțională. Ultima estimare poate fi corectată timp de cinci minute de la prima salvare. După acest interval, orice actualizare devine o estimare nouă și rămâne vizibilă în istoric.

După salvarea estimării, aplicația amintește clar că sarcina nu este încă pornită. Mesajele de confirmare dispar automat după câteva secunde, iar mesajele de eroare rămân vizibile pentru a putea fi corectate.
MARKDOWN,
                'Navigarea mobilă, pașii șoferului și istoricul estimărilor din sarcini.',
            );

            $this->reviseArticle(
                'ghiduri-dupa-rol',
                11,
                12,
                <<<'MARKDOWN'

## Șoferul: estimare și pornire în doi pași

După acceptarea unei sarcini, șoferul comunică mai întâi ora estimată de finalizare, apoi apasă separat **Pornește sarcina**. Salvarea estimării nu schimbă singură sarcina în starea **În lucru**.

O greșeală din ultima estimare poate fi corectată în primele cinci minute. Corectarea păstrează aceeași înregistrare. După expirarea celor cinci minute, șoferul comunică o estimare nouă, iar estimările anterioare rămân disponibile în istoric pentru coordonare.
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
            $this->restoreArticle('ghiduri-dupa-rol', 12, 'Responsabilitatea șoferului pentru estimare, pornire și actualizări succesive.');
            $this->restoreArticle('pagini-si-operatiuni', 11, 'Navigarea mobilă, pașii șoferului și istoricul estimărilor din sarcini.');
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
            'version' => '2026.07.29.10',
            'title' => 'Sarcinile șoferului, mai rapide și mai clare pe mobil',
            'summary' => 'Șoferii au file de lucru, carduri compacte, estimare precompletată și un pas separat, clar, pentru pornirea sarcinii.',
            'body_markdown' => <<<'MARKDOWN'
# Ce s-a schimbat

- pagina **Sarcinile mele** are file separate pentru sarcinile de răspuns, de pornit, în lucru și finalizate;
- cardurile de pe mobil afișează compact traseul, termenul, estimarea și acțiunea următoare;
- informația redundantă despre șofer a fost eliminată din propriul spațiu de lucru;
- acțiunile importante apar primele în pagina sarcinii;
- ora estimată este completată automat cu o oră în avans;
- observația estimării nu mai este obligatorie;
- după salvarea estimării, aplicația arată clar că sarcina trebuie pornită separat;
- ultima estimare poate fi corectată în primele cinci minute;
- actualizările ulterioare creează estimări noi, păstrând istoricul complet;
- mesajele de confirmare dispar automat după câteva secunde, fără să ocupe permanent ecranul.

# Ce trebuie să facă șoferul

După acceptare, salvează estimarea și apoi apasă **Pornește sarcina**. Dacă ora se schimbă după mai mult de cinci minute, comunică o estimare nouă.
MARKDOWN,
            'audience_roles' => json_encode(['sofer', 'dispecer', 'manager', 'admin', 'super-admin'], JSON_UNESCAPED_UNICODE),
            'affected_modules' => json_encode(['Sarcini șoferi', 'Centru de ajutor'], JSON_UNESCAPED_UNICODE),
            'requires_action' => false,
            'status' => 'published',
            'released_at' => '2026-07-29',
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
            || $note->version !== '2026.07.29.10'
            || $note->title !== 'Sarcinile șoferului, mai rapide și mai clare pe mobil'
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
