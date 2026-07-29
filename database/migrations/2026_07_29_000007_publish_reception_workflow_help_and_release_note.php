<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RELEASE_SLUG = '2026-07-29-documente-receptii-si-loturi';

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
                5,
                'Responsabilități pentru trimiterea documentelor și completarea recepțiilor.',
            );
            $this->restoreArticle(
                'pagini-si-operatiuni',
                4,
                'Pagini pentru documentele preliminare și recepțiile complete.',
            );
            $this->restoreArticle(
                'circuitul-materialelor',
                3,
                'Flux complet de recepție, loturi și alegerea loturilor la consum.',
            );
        });
    }

    private function reviseMaterialsArticle(): void
    {
        $article = $this->lockedArticle('circuitul-materialelor', 2);
        $body = (string) $article->body_markdown;
        $reception = <<<'MARKDOWN'
## Intrare: documentele și recepția de la furnizor

O persoană din teren poate trimite mai întâi, din **Gestiune → Documente de procesat**, una sau mai multe fotografii ori fișiere PDF. În această etapă se alege locația și se pot adăuga observații, dar stocul nu se modifică.

Responsabilul locației deschide înregistrarea și poate:

1. să creeze recepția pe baza documentelor primite;
2. să închidă înregistrarea ca anulată, păstrând motivul în istoric.

În recepție se pot adăuga mai multe materiale. Pentru fiecare material se păstrează separat cantitatea, numărul lotului, termenul de expirare, prețul unitar fără TVA, moneda și observațiile. Furnizorul și documentul principal aparțin recepției.

La confirmarea recepției:

1. documentele trimise sunt legate de recepție;
2. se creează câte un lot pentru fiecare material;
3. stocul locației crește cu toate cantitățile introduse;
4. înregistrarea preliminară trece în starea **Închis**.

Fișierele atașate sunt disponibile numai utilizatorilor care au acces la locația și recepția respectivă.

MARKDOWN;

        $body = preg_replace(
            '/## Intrare: recepția de la furnizor.*?(?=## Mișcare:)/su',
            $reception,
            $body,
            1,
            $receptionReplacements,
        );
        if ($receptionReplacements !== 1) {
            throw new RuntimeException('Secțiunea despre recepție nu are forma așteptată.');
        }

        $consumption = <<<'MARKDOWN'
## Ieșire: consumul

În **Gestiune → Consum**, utilizatorul alege locația, materialul și cantitatea consumată. Aplicația afișează loturile disponibile și propune automat ordinea:

1. **FEFO** — lotul care expiră primul;
2. **FIFO** — dacă expirarea nu stabilește ordinea, lotul intrat primul.

Utilizatorul poate modifica repartiția propusă înainte de salvare. Cantitățile alese pe loturi trebuie să însumeze exact consumul și nu pot depăși soldul fiecărui lot.

La salvare, aplicația verifică din nou stocul, creează raportul de consum, scade cantitatea totală și păstrează în istoricul de mișcări loturile folosite.

În forma actuală, o salvare de consum conține un singur material.

MARKDOWN;

        $body = preg_replace(
            '/## Ieșire: consumul.*?(?=## Returul)/su',
            $consumption,
            $body,
            1,
            $consumptionReplacements,
        );
        if ($consumptionReplacements !== 1) {
            throw new RuntimeException('Secțiunea despre consum nu are forma așteptată.');
        }

        $this->insertRevision(
            $article,
            3,
            $body,
            'Flux complet de recepție, loturi și alegerea loturilor la consum.',
        );
    }

    private function revisePagesArticle(): void
    {
        $article = $this->lockedArticle('pagini-si-operatiuni', 3);
        $body = rtrim((string) $article->body_markdown)."\n".<<<'MARKDOWN'

## Documente de procesat

Pagina reunește fotografiile și documentele trimise înainte de recepție. Starea **Creat** înseamnă că înregistrarea așteaptă verificarea. Starea **Închis** arată că documentele au fost transformate într-o recepție sau că înregistrarea a fost anulată cu motiv.

Butonul **Trimite documente** permite încărcarea mai multor fotografii sau fișiere PDF pentru o locație. Încărcarea nu modifică stocul.

## Recepții

Pagina afișează recepțiile salvate, materialele principale și numărul fișierelor atașate. Din detaliile recepției se văd toate materialele, loturile, termenele de expirare și, pentru rolurile autorizate, informațiile comerciale.

La creare se pot introduce mai multe materiale într-o singură recepție. Modificarea ulterioară este limitată la detalii; materialul, cantitatea și locația nu se schimbă din acest formular.
MARKDOWN;

        $this->insertRevision(
            $article,
            4,
            $body,
            'Pagini pentru documentele preliminare și recepțiile complete.',
        );
    }

    private function reviseRolesArticle(): void
    {
        $article = $this->lockedArticle('ghiduri-dupa-rol', 4);
        $body = rtrim((string) $article->body_markdown)."\n".<<<'MARKDOWN'

## Responsabilități în fluxul de recepție

- **Muncitorul** poate trimite fotografii și documente pentru o locație și își vede propriile înregistrări.
- **Șeful de șantier** și **gestionarul de bază** văd documentele locațiilor alocate și pot crea recepția sau închide o înregistrare cu motiv.
- **Gestionarul de bază** poate completa ulterior termenul de expirare pentru recepțiile locațiilor alocate.
- **Administratorul** poate corecta detaliile recepției, inclusiv furnizorul, documentul, lotul, prețul și observațiile. Materialul, cantitatea și locația rămân neschimbate.
- **Contabilul** consultă recepțiile și informațiile comerciale. Modificarea lor necesită o permisiune suplimentară.

Fiecare actualizare a detaliilor este păstrată în istoricul intern.
MARKDOWN;

        $this->insertRevision(
            $article,
            5,
            $body,
            'Responsabilități pentru trimiterea documentelor și completarea recepțiilor.',
        );
    }

    private function publishReleaseNote(): void
    {
        if (DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->exists()) {
            throw new RuntimeException('Nota de versiune pentru recepții există deja.');
        }

        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.07.29.3',
            'title' => 'Documente, recepții complete și loturi la consum',
            'summary' => 'Documentele din teren pot fi procesate într-o recepție cu mai multe materiale, iar loturile propuse la consum pot fi verificate și ajustate.',
            'body_markdown' => <<<'MARKDOWN'
# Ce s-a schimbat

- fotografiile și documentele de recepție pot fi trimise înainte de completarea recepției;
- înregistrările în așteptare apar în pagina **Documente de procesat**;
- o recepție poate conține mai multe materiale;
- pentru fiecare material pot fi păstrate lotul, expirarea, prețul unitar fără TVA, moneda și observațiile;
- documentele atașate sunt private și sunt legate de recepția corespunzătoare;
- detaliile recepției pot fi completate ulterior în funcție de rol, fără modificarea materialului, cantității sau locației;
- la consum, aplicația propune loturile în ordinea FEFO/FIFO, iar utilizatorul poate ajusta cantitățile înainte de salvare.

Utilizatorii care înregistrează recepții trebuie să verifice materialele și cantitățile înainte de confirmare. Corectarea cantităților rămâne un flux separat.
MARKDOWN,
            'audience_roles' => json_encode(['all'], JSON_UNESCAPED_UNICODE),
            'affected_modules' => json_encode(
                ['Documente de procesat', 'Recepții', 'Fișă inventar materiale', 'Consum'],
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
            || $note->version !== '2026.07.29.3'
            || $note->title !== 'Documente, recepții complete și loturi la consum'
            || $note->status !== 'published'
        ) {
            throw new RuntimeException('Nota despre recepții a fost modificată; revenirea a fost oprită.');
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
