<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RELEASE_SLUG = '2026-07-29-custodie-personala-si-retururi';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->reviseArticle(
                'circuitul-materialelor',
                5,
                6,
                <<<'MARKDOWN'

## Custodia personală a materialelor

Materialele pot fi alocate dintr-o locație unei persoane, predate altei persoane sau returnate la locația de origine. Aplicația păstrează cantitatea, unitatea, locația, persoanele implicate, confirmările și observațiile fiecărei operațiuni.

Custodia arată cine răspunde de o cantitate aflată într-o locație. Ea nu scade stocul locației și nu înlocuiește înregistrarea unui consum sau a unui transfer de stoc. La predarea între persoane, cantitatea trece din responsabilitatea uneia în responsabilitatea celeilalte numai după confirmările necesare.

Aplicația nu permite alocarea unei cantități mai mari decât partea din stoc care nu este deja în custodie sau rezervată de o altă alocare în așteptare.
MARKDOWN,
                'Custodia personală a materialelor și relația sa cu stocul locației.',
            );

            $this->reviseArticle(
                'pagini-si-operatiuni',
                6,
                7,
                <<<'MARKDOWN'

## Custodia mea

Pagina **Gestiune → Custodie personală** sau opțiunea **Custodia mea** de pe telefon reunește:

- echipamentele și cantitățile de materiale aflate în responsabilitatea utilizatorului;
- operațiunile care necesită confirmarea sa;
- alocarea dintr-o locație către o persoană;
- predarea între două persoane;
- returul către o locație;
- istoricul cu starea confirmărilor și observațiile.

La alocarea din locație, responsabilul locației confirmă operațiunea prin inițiere, iar persoana care primește trebuie să accepte. La predarea între persoane sunt necesare acordurile ambelor persoane. La retur, persoana care predă și un singur responsabil autorizat al locației confirmă operațiunea.

Toți responsabilii activi ai locației primesc notificarea de retur, dar este suficientă confirmarea unuia dintre ei. Un refuz trebuie însoțit de o observație. Pentru echipamente, starea declarată la retur este verificată de responsabil; un echipament deteriorat sau care necesită service este trecut automat în starea corespunzătoare.
MARKDOWN,
                'Pagina Custodia mea și cele trei operațiuni disponibile.',
            );

            $this->reviseArticle(
                'ghiduri-dupa-rol',
                7,
                8,
                <<<'MARKDOWN'

## Roluri în custodia personală

- **Muncitorul** vede bunurile proprii, confirmă operațiunile la care participă și poate iniția o predare sau un retur.
- **Șoferul** are același acces pentru bunurile proprii. La predarea către un coleg introduce codul utilizatorului; lista celorlalți șoferi nu este afișată.
- **Șeful de șantier** și **gestionarul de bază** văd custodiile locațiilor administrate, pot aloca bunuri și pot confirma retururile către acele locații.
- **Administratorul**, **super-administratorul** și **dispecerul** pot lucra cu toate locațiile.
- **Managerul** poate consulta situația generală și istoricul, fără să confirme sau să modifice operațiuni.

Un utilizator care inițiază o operațiune în calitate de participant este marcat automat ca fiind de acord. Ceilalți participanți trebuie să răspundă separat.
MARKDOWN,
                'Drepturile fiecărui rol în fluxul de custodie.',
            );

            $this->reviseArticle(
                'statusuri-si-termeni',
                1,
                2,
                <<<'MARKDOWN'

## Stările operațiunilor de custodie

- **În așteptare**: lipsește cel puțin o confirmare.
- **Acceptat**: toate confirmările necesare există, iar responsabilul curent a fost actualizat.
- **Refuzat**: un participant a respins operațiunea sau aplicația a constatat că bunul ori cantitatea nu mai este disponibilă.
- **Expirat**: operațiunea nu a fost confirmată în 24 de ore și trebuie inițiată din nou.

În fiecare înregistrare sunt afișate separat confirmarea persoanei care predă și confirmarea persoanei ori locației care primește.
MARKDOWN,
                'Stările și termenul operațiunilor de custodie.',
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
            $this->restoreArticle('statusuri-si-termeni', 2, 'Stările și termenul operațiunilor de custodie.');
            $this->restoreArticle('ghiduri-dupa-rol', 8, 'Drepturile fiecărui rol în fluxul de custodie.');
            $this->restoreArticle('pagini-si-operatiuni', 7, 'Pagina Custodia mea și cele trei operațiuni disponibile.');
            $this->restoreArticle('circuitul-materialelor', 6, 'Custodia personală a materialelor și relația sa cu stocul locației.');
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
            throw new RuntimeException('Nota de versiune pentru custodie există deja.');
        }

        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.07.29.6',
            'title' => 'Custodie personală pentru materiale și echipamente',
            'summary' => 'Bunurile pot fi alocate, predate între persoane și returnate unei locații, cu confirmări, observații și istoric complet.',
            'body_markdown' => <<<'MARKDOWN'
# Ce s-a schimbat

- pagina de teren a fost reorganizată ca **Custodia mea**, pentru utilizare rapidă inclusiv de pe telefon;
- aceeași pagină arată echipamentele și cantitățile de materiale aflate în responsabilitatea utilizatorului;
- un responsabil de locație poate aloca un echipament sau o cantitate de material unei persoane;
- bunurile pot fi predate între persoane, cu confirmarea ambelor părți;
- bunurile pot fi returnate unei locații, cu confirmarea persoanei și a unui responsabil al locației;
- toți responsabilii activi ai locației sunt notificați pentru retur, iar unul singur trebuie să confirme;
- refuzul include obligatoriu o observație;
- la returul unui echipament se înregistrează starea, iar bunurile deteriorate sunt marcate pentru verificare sau service;
- fiecare operațiune arată cine a confirmat, ce acord lipsește și ce observații au fost transmise;
- șoferii predau către un coleg folosind codul acestuia, fără să vadă lista celorlalți șoferi;
- custodia materialelor nu modifică stocul locației și nu înlocuiește consumul sau transferul de stoc.
MARKDOWN,
            'audience_roles' => json_encode([
                'super-admin', 'admin', 'dispecer', 'manager',
                'sef-santier', 'gestionar-baza', 'sofer', 'muncitor',
            ], JSON_UNESCAPED_UNICODE),
            'affected_modules' => json_encode(
                ['Custodie personală', 'Materiale', 'Echipamente', 'Notificări'],
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
            || $note->version !== '2026.07.29.6'
            || $note->title !== 'Custodie personală pentru materiale și echipamente'
            || $note->status !== 'published'
        ) {
            throw new RuntimeException('Nota despre custodie a fost modificată; revenirea a fost oprită.');
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
            'current_revision' => $currentRevision - 1,
            'updated_by' => null,
            'published_at' => $previous->published_at,
            'updated_at' => now(),
        ]);
    }
};
