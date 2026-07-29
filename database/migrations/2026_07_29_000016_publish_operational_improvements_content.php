<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RELEASE_SLUG = '2026-07-29-claritate-operationala-si-vizualizare-live';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->reviseArticle(
                'pagini-si-operatiuni',
                8,
                9,
                <<<'MARKDOWN'

## Vizualizare Live și filtre rapide

Paginile operaționale principale afișează controlul **Live**: panoul principal, lista sarcinilor, situația șoferilor, transferurile, fișa de inventar, alertele și panoul șantierului. Când este activ, pagina se actualizează automat la fiecare 5 minute. Utilizatorul poate opri actualizarea sau poate reîncărca imediat datele.

Actualizarea este amânată dacă pagina nu este activă sau dacă utilizatorul completează un câmp, pentru a nu întrerupe lucrul în curs. Starea pornit/oprit se păstrează pe dispozitiv pentru fiecare pagină.

Căutarea și filtrele din liste se aplică automat după selectare sau după o scurtă pauză la scriere. Selecțiile permise din liste și bifele se salvează în cont și se păstrează între dispozitive. Textul căutat și intervalele de date nu sunt memorate.

## Informații complete în sarcina șoferului

Pentru o sarcină creată dintr-un transfer, pagina sarcinii arată direct toate pozițiile transferului: materialul sau echipamentul, identificarea echipamentului, cantitatea, unitatea, starea la primire, avizul și observațiile. Șoferul poate verifica astfel încărcătura fără să părăsească sarcina.

În **Situație șoferi**, aplicația semnalează șoferii care au deja o sarcină activă pe exact același traseu și în aceeași direcție. Aceasta este doar o recomandare pentru dispecer sau manager; aplicația nu selectează și nu alocă automat șoferul.

O sarcină finalizată, anulată sau arhivată devine disponibilă numai pentru consultare. Detaliile, alocarea, estimările și observațiile nu mai pot fi modificate, iar istoricul rămâne vizibil.
MARKDOWN,
                'Vizualizarea Live, filtrele rapide și informațiile operaționale din sarcinile șoferilor.',
            );

            $this->reviseArticle(
                'ghiduri-dupa-rol',
                9,
                10,
                <<<'MARKDOWN'

## Scurtături și recomandări operaționale

Panoul principal afișează scurtături potrivite rolului:

- șoferul ajunge direct la sarcinile și custodia sa sau la scanarea QR;
- muncitorul ajunge direct la custodie și scanarea QR;
- dispecerul și responsabilii de locație au acces rapid la situația șoferilor, sarcini, transferuri, recepții, inventar și alerte, în limita drepturilor lor;
- rolurile de consultare văd numai paginile pe care le pot deschide.

Dispecerul, administratorul, șeful de șantier și gestionarul de bază pot folosi recomandarea **aceeași rută** când alocă o sarcină. Decizia și trimiterea spre acceptare rămân întotdeauna la utilizator.

Șoferul vede numai sarcinile care îl privesc și poate consulta în ele conținutul complet al transferului. Nu vede lista sau activitatea celorlalți șoferi.

La administrarea unei locații pot fi aleși mai mulți responsabili. Toți sunt notificați, dar aprobarea unuia este suficientă. Eliminarea unei persoane oprește notificările viitoare și accesul local asociat, fără să șteargă aprobările deja păstrate în istoric.
MARKDOWN,
                'Scurtăturile pe rol, recomandările de traseu și administrarea responsabililor locației.',
            );

            $this->reviseArticle(
                'statusuri-si-termeni',
                3,
                4,
                <<<'MARKDOWN'

## Blocarea sarcinilor închise

- **Finalizat**: execuția s-a încheiat, iar sarcina este blocată pentru modificări operaționale.
- **Anulat**: sarcina nu mai continuă și este blocată pentru modificări operaționale.
- **Arhivat**: sarcina este păstrată în istoric și rămâne numai pentru consultare.

În aceste stări nu mai pot fi schimbate detaliile, șoferul, estimarea sau observațiile. Informațiile și istoricul anterior rămân vizibile.
MARKDOWN,
                'Regula de consultare fără modificări pentru sarcinile închise.',
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
            $this->restoreArticle('statusuri-si-termeni', 4, 'Regula de consultare fără modificări pentru sarcinile închise.');
            $this->restoreArticle('ghiduri-dupa-rol', 10, 'Scurtăturile pe rol, recomandările de traseu și administrarea responsabililor locației.');
            $this->restoreArticle('pagini-si-operatiuni', 9, 'Vizualizarea Live, filtrele rapide și informațiile operaționale din sarcinile șoferilor.');
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
            throw new RuntimeException('Nota de versiune pentru îmbunătățirile operaționale există deja.');
        }

        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.07.29.8',
            'title' => 'Mai multă claritate în activitatea zilnică',
            'summary' => 'Sarcinile arată încărcătura completă, dispecerii primesc recomandări de traseu, iar paginile operaționale se pot actualiza automat.',
            'body_markdown' => <<<'MARKDOWN'
# Ce s-a schimbat

- sarcina unui transfer arată direct toate materialele și echipamentele, cantitățile, identificarea, avizul și observațiile;
- o sarcină finalizată, anulată sau arhivată rămâne numai pentru consultare și nu mai acceptă modificări ori observații noi;
- în pagina **Situație șoferi** sunt marcați șoferii care au deja o sarcină pe exact același traseu;
- recomandarea de traseu nu alocă automat șoferul; utilizatorul păstrează decizia și trimite sarcina spre acceptare;
- panoul principal afișează scurtături potrivite rolului și drepturilor utilizatorului;
- paginile operaționale importante au un control **Live** cu actualizare automată la 5 minute, pauză și actualizare imediată;
- actualizarea automată este amânată când utilizatorul completează un câmp sau când pagina nu este activă;
- căutarea și filtrele se aplică mai repede și în același mod în liste;
- selecțiile din filtre continuă să fie salvate în cont, în timp ce textul căutat și datele introduse nu sunt memorate;
- selectorul responsabililor unei locații arată mai clar rolurile, numărul persoanelor selectate, regula unei singure aprobări și efectul eliminării unui responsabil.
MARKDOWN,
            'audience_roles' => json_encode(['all'], JSON_UNESCAPED_UNICODE),
            'affected_modules' => json_encode(
                ['Panou principal', 'Sarcini șoferi', 'Situație șoferi', 'Transferuri', 'Inventar', 'Alerte', 'Locații'],
                JSON_UNESCAPED_UNICODE,
            ),
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
            || $note->version !== '2026.07.29.8'
            || $note->title !== 'Mai multă claritate în activitatea zilnică'
            || $note->status !== 'published'
        ) {
            throw new RuntimeException('Nota despre îmbunătățirile operaționale a fost modificată; revenirea a fost oprită.');
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
                "Articolul {$slug} are revizia {$actual}; era așteptată revizia {$expectedRevision}."
            );
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
