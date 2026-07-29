<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RELEASE_SLUG = '2026-07-29-stoc-transferuri-consumuri-corectii';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->reviseMaterialsArticle();
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
            $this->restoreArticle(
                'ghiduri-dupa-rol',
                6,
                'Corectarea controlată a consumurilor de către administratori.',
            );
            $this->restoreArticle(
                'pagini-si-operatiuni',
                5,
                'Selecția stocului la transfer și consumurile cu mai multe materiale.',
            );
            $this->restoreArticle(
                'circuitul-materialelor',
                4,
                'Stoc disponibil la transfer, consum multiplu și corecții trasabile.',
            );
        });
    }

    private function reviseMaterialsArticle(): void
    {
        $article = $this->lockedArticle('circuitul-materialelor', 3);
        $body = (string) $article->body_markdown;

        $transferAvailability = <<<'MARKDOWN'
### Ce poate fi ales într-un transfer

După alegerea locației sursă, formularul afișează numai materialele care au stoc disponibil și echipamentele aflate în acea locație. Cantitatea disponibilă ține cont și de pozițiile deja rezervate în alte transferuri active.

La salvare, aplicația verifică din nou locația, stocul și rezervările. Astfel, o opțiune care nu mai este disponibilă între timp nu poate fi introdusă în transfer.

MARKDOWN;

        if (! str_contains($body, '### Ce poate fi ales într-un transfer')) {
            $body = str_replace(
                '## Ieșire: consumul',
                $transferAvailability."\n## Ieșire: consumul",
                $body,
                $insertions,
            );
            if ($insertions !== 1) {
                throw new RuntimeException('Secțiunea despre transferuri nu are forma așteptată.');
            }
        }

        $consumption = <<<'MARKDOWN'
## Ieșire: consumul

În **Gestiune → Consum**, utilizatorul alege locația și poate adăuga mai multe materiale în același raport. Pentru fiecare poziție se văd stocul disponibil, cantitatea solicitată și observațiile proprii.

Pentru fiecare material, aplicația afișează loturile disponibile și propune automat ordinea:

1. **FEFO** — lotul care expiră primul;
2. **FIFO** — dacă expirarea nu stabilește ordinea, lotul intrat primul.

Utilizatorul poate modifica repartiția propusă înainte de salvare. Cantitățile alese pe loturi trebuie să însumeze exact consumul și nu pot depăși soldul fiecărui lot.

Toate pozițiile sunt salvate împreună. Dacă o singură cantitate nu mai este disponibilă, raportul nu este înregistrat și niciun stoc nu este modificat.

Administratorul poate corecta ulterior un consum înregistrat. Corecția cere un motiv, schimbă starea raportului în **Modificat** și păstrează versiunea anterioară. În fișa de inventar apar separat anularea mișcărilor vechi și noile scăderi, astfel încât istoricul cantităților rămâne verificabil.

MARKDOWN;

        $body = preg_replace(
            '/## Ieșire: consumul.*?(?=## Returul)/su',
            $consumption,
            $body,
            1,
            $replacements,
        );
        if ($replacements !== 1) {
            throw new RuntimeException('Secțiunea despre consum nu are forma așteptată.');
        }

        $this->insertRevision(
            $article,
            4,
            $body,
            'Stoc disponibil la transfer, consum multiplu și corecții trasabile.',
        );
    }

    private function revisePagesArticle(): void
    {
        $article = $this->lockedArticle('pagini-si-operatiuni', 4);
        $body = rtrim((string) $article->body_markdown)."\n".<<<'MARKDOWN'

## Transferuri: conținut disponibil în sursă

În formularul de transfer, materialele și echipamentele sunt încărcate după alegerea locației **Din**. Pentru materiale apare cantitatea disponibilă și nerezervată, iar echipamentele deja incluse în alt transfer activ nu mai pot fi alese.

La schimbarea locației sursă, lista este reîncărcată. Verificarea se repetă la salvare, inclusiv pentru cantitatea totală a aceluiași material.

## Consum: mai multe materiale și corecții

Butonul **Raportează consum** deschide un formular în care pot fi adăugate mai multe materiale. Fiecare poziție arată stocul disponibil, cantitatea, observațiile și propunerea de loturi FEFO/FIFO.

Administratorii au în listă acțiunea **Corectează**. Formularul pornește de la datele curente, cere motivul corecției și afișează istoricul corecțiilor anterioare. După salvare, starea este **Modificat**.
MARKDOWN;

        $this->insertRevision(
            $article,
            5,
            $body,
            'Selecția stocului la transfer și consumurile cu mai multe materiale.',
        );
    }

    private function reviseRolesArticle(): void
    {
        $article = $this->lockedArticle('ghiduri-dupa-rol', 5);
        $body = rtrim((string) $article->body_markdown)."\n".<<<'MARKDOWN'

## Corectarea consumurilor

- **Șeful de șantier**, **gestionarul de bază** și rolurile operaționale pot înregistra un consum cu mai multe materiale, numai în locațiile în care au drept de operare.
- **Administratorul** și **super-administratorul** pot corecta un consum deja înregistrat. Corecția necesită un motiv și nu șterge versiunea ori mișcările inițiale.
- Celelalte roluri care au acces la consum văd starea **Modificat**, momentul și utilizatorul care a făcut ultima corecție, dar nu pot schimba operațiunea.
MARKDOWN;

        $this->insertRevision(
            $article,
            6,
            $body,
            'Corectarea controlată a consumurilor de către administratori.',
        );
    }

    private function publishReleaseNote(): void
    {
        if (DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->exists()) {
            throw new RuntimeException('Nota de versiune pentru transferuri și consumuri există deja.');
        }

        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.07.29.4',
            'title' => 'Stoc disponibil în transferuri și consumuri corectabile',
            'summary' => 'Transferurile folosesc conținutul disponibil în sursă, iar un raport de consum poate include mai multe materiale și poate fi corectat cu istoric.',
            'body_markdown' => <<<'MARKDOWN'
# Ce s-a schimbat

- după alegerea sursei unui transfer sunt afișate numai materialele și echipamentele disponibile în acea locație;
- cantitatea afișată pentru transfer ține cont de materialele rezervate în alte transferuri active;
- stocul și rezervările sunt verificate din nou la salvarea transferului;
- un raport de consum poate conține mai multe materiale;
- fiecare poziție de consum arată stocul disponibil și propunerea de loturi FEFO/FIFO;
- dacă o poziție nu poate fi salvată, întregul raport este oprit fără modificări parțiale de stoc;
- administratorii pot corecta un consum înregistrat, cu motiv obligatoriu;
- raportul corectat are starea **Modificat**, iar versiunile și mișcările de stoc anterioare rămân în istoric.

Pentru transferuri, alege mai întâi locația sursă. Pentru consumuri, verifică stocul afișat la fiecare material înainte de salvare.
MARKDOWN,
            'audience_roles' => json_encode(['all'], JSON_UNESCAPED_UNICODE),
            'affected_modules' => json_encode(
                ['Transferuri', 'Consum', 'Fișă inventar materiale'],
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
            || $note->version !== '2026.07.29.4'
            || $note->title !== 'Stoc disponibil în transferuri și consumuri corectabile'
            || $note->status !== 'published'
        ) {
            throw new RuntimeException('Nota despre transferuri și consumuri a fost modificată; revenirea a fost oprită.');
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

    private function insertRevision(object $article, int $revision, string $body, string $summary): void
    {
        $timestamp = now();

        DB::table('help_article_revisions')->insert([
            'help_article_id' => $article->id,
            'revision' => $revision,
            'title' => $article->title,
            'summary' => $article->summary,
            'body_markdown' => $body,
            'change_summary' => $summary,
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
            'current_revision' => $currentRevision - 1,
            'updated_by' => null,
            'published_at' => $previous->published_at,
            'updated_at' => now(),
        ]);
    }
};
