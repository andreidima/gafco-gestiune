<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RELEASE_SLUG = '2026-07-29-comenzi-negociate';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->reviseArticle(
                'pagini-si-operatiuni',
                7,
                8,
                <<<'MARKDOWN'

## Comenzi negociate

Pagina **Gestiune → Comenzi negociate** este disponibilă administratorilor și păstrează lista simplă a comenzilor convenite cu furnizorii.

La crearea unei comenzi se aleg locația de destinație, furnizorul, moneda și materialele. Pentru fiecare material se introduc cantitatea și prețul unitar fără TVA. Furnizorul și observațiile pot fi completate sau corectate ulterior, cât timp comanda este în starea **Creat**.

O comandă nu modifică stocul. Din pagina ei, acțiunea **Transformă în recepție** deschide formularul de recepție cu locația, furnizorul, materialele, cantitățile și prețurile precompletate. Datele pot fi corectate pentru a corespunde livrării reale. Comanda se închide și stocul se actualizează numai când recepția este salvată cu succes.

Dacă o comandă nu mai continuă, administratorul o anulează și completează motivul. Comanda rămâne în istoric; nu există acțiune de ștergere.
MARKDOWN,
                'Pagina și fluxul simplu pentru comenzile negociate.',
            );

            $this->reviseArticle(
                'ghiduri-dupa-rol',
                8,
                9,
                <<<'MARKDOWN'

## Roluri pentru comenzile negociate

- **Administratorul** și **super-administratorul** pot consulta, crea, modifica, anula și transforma comenzile negociate în recepții.
- Celelalte roluri nu văd pagina și nu pot opera aceste comenzi.

Comenzile închise rămân disponibile doar pentru consultare, împreună cu utilizatorul și motivul sau recepția prin care au fost închise.
MARKDOWN,
                'Accesul administratorilor la comenzile negociate.',
            );

            $this->reviseArticle(
                'statusuri-si-termeni',
                2,
                3,
                <<<'MARKDOWN'

## Stările comenzilor negociate

- **Creat**: comanda poate fi modificată, anulată sau transformată într-o recepție. Nu a modificat stocul.
- **Închis**: comanda nu mai poate fi modificată. Detaliul închiderii arată dacă a fost anulată sau transformată într-o recepție.

O încercare de transformare care nu ajunge la salvarea recepției nu închide comanda.
MARKDOWN,
                'Cele două stări ale comenzilor negociate și efectul lor asupra stocului.',
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
            $this->restoreArticle('statusuri-si-termeni', 3, 'Cele două stări ale comenzilor negociate și efectul lor asupra stocului.');
            $this->restoreArticle('ghiduri-dupa-rol', 9, 'Accesul administratorilor la comenzile negociate.');
            $this->restoreArticle('pagini-si-operatiuni', 8, 'Pagina și fluxul simplu pentru comenzile negociate.');
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
            throw new RuntimeException('Nota de versiune pentru comenzile negociate există deja.');
        }

        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.07.29.7',
            'title' => 'Comenzi negociate transformabile în recepții',
            'summary' => 'Administratorii pot păstra comenzile convenite cu furnizorii și le pot închide prin anulare sau printr-o recepție precompletată.',
            'body_markdown' => <<<'MARKDOWN'
# Ce s-a schimbat

- a fost adăugată pagina **Gestiune → Comenzi negociate**, vizibilă numai administratorilor;
- o comandă include destinația, furnizorul, moneda, materialele, cantitățile și prețurile unitare fără TVA;
- lista poate fi căutată și filtrată după stare, locație, furnizor și perioadă;
- comenzile au doar două stări vizibile: **Creat** și **Închis**;
- o comandă creată poate fi modificată sau anulată cu motiv;
- o comandă poate porni o recepție în care datele sunt precompletate și pot fi corectate după livrarea reală;
- comanda se închide numai după salvarea cu succes a recepției;
- stocul nu este modificat de comandă, ci numai de recepția salvată;
- comenzile nu se șterg și rămân în istoric împreună cu modul în care au fost închise;
- nu a fost introdus un flux de cerere sau comparare a ofertelor furnizorilor.
MARKDOWN,
            'audience_roles' => json_encode(['super-admin', 'admin'], JSON_UNESCAPED_UNICODE),
            'affected_modules' => json_encode(
                ['Comenzi negociate', 'Recepții furnizori', 'Stoc materiale'],
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
            || $note->version !== '2026.07.29.7'
            || $note->title !== 'Comenzi negociate transformabile în recepții'
            || $note->status !== 'published'
        ) {
            throw new RuntimeException('Nota despre comenzile negociate a fost modificată; revenirea a fost oprită.');
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
