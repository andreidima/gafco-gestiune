<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RELEASE_SLUG = '2026-07-29-alerte-stoc-si-receptii';

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
                7,
                'Vizibilitatea alertelor și administrarea regulilor.',
            );
            $this->restoreArticle(
                'pagini-si-operatiuni',
                6,
                'Pagina Alerte și regulile sale de funcționare.',
            );
            $this->restoreArticle(
                'circuitul-materialelor',
                5,
                'Alerte pentru expirarea loturilor și documente neprocesate.',
            );
        });
    }

    private function reviseMaterialsArticle(): void
    {
        $article = $this->lockedArticle('circuitul-materialelor', 4);
        $body = rtrim((string) $article->body_markdown)."\n".<<<'MARKDOWN'

## Alerte pentru loturi și documente

Un lot cu stoc pozitiv poate genera o alertă numai dacă are un termen de expirare completat. Alerta apare în intervalul stabilit prin reguli și devine critică după depășirea termenului. Dacă stocul lotului ajunge la zero sau termenul este modificat astfel încât situația nu mai necesită atenție, alerta se închide automat.

Documentele trimise din teren generează o alertă dacă rămân în starea **Creat** mai mult decât intervalul configurat. Alerta se închide automat când documentele sunt transformate într-o recepție sau când înregistrarea este închisă cu motiv.

Lipsa unor informații opționale, precum codul lotului, termenul de expirare, furnizorul sau prețul, nu generează singură o alertă.
MARKDOWN;

        $this->insertRevision(
            $article,
            5,
            $body,
            'Alerte pentru expirarea loturilor și documente neprocesate.',
        );
    }

    private function revisePagesArticle(): void
    {
        $article = $this->lockedArticle('pagini-si-operatiuni', 5);
        $body = rtrim((string) $article->body_markdown)."\n".<<<'MARKDOWN'

## Alerte

Pagina **Gestiune → Alerte** reunește situațiile care necesită verificare:

- loturi care se apropie de termenul de expirare sau sunt deja expirate;
- documente de recepție care au rămas neprocesate peste intervalul stabilit.

Lista poate fi filtrată după text, tip, locație, prioritate și stare. Butonul **Verifică** deschide direct materialul și lotul sau înregistrarea de recepție care a generat alerta.

Starea **Activă** înseamnă că motivul există în continuare. Starea **Închisă automat** arată că stocul, termenul sau documentul s-a modificat și nu mai îndeplinește condiția de alertare. Citirea unei notificări nu închide alerta.

La apariția unei situații relevante, destinatarii primesc și o notificare internă. O nouă notificare nu este trimisă la fiecare verificare; ea reapare dacă alerta se reactivează sau dacă un lot trece de la avertizare la expirat.

## Reguli de alertare

Administratorii pot modifica intervalele și pot activa sau dezactiva un tip de alertă. Regulile se aplică în această ordine:

1. regula generală;
2. excepția pentru rol;
3. excepția pentru locație.

Regula locației are prioritatea cea mai mare. În lipsa unei excepții, se aplică nivelul anterior.
MARKDOWN;

        $this->insertRevision(
            $article,
            6,
            $body,
            'Pagina Alerte și regulile sale de funcționare.',
        );
    }

    private function reviseRolesArticle(): void
    {
        $article = $this->lockedArticle('ghiduri-dupa-rol', 6);
        $body = rtrim((string) $article->body_markdown)."\n".<<<'MARKDOWN'

## Cine vede alertele

- **Administratorul**, **super-administratorul**, **dispecerul** și **managerul** văd alertele din toate locațiile.
- **Gestionarul de bază** și **șeful de șantier** văd numai alertele locațiilor pe care le administrează activ.
- **Contabilul** vede implicit alertele de expirare, dar nu și documentele de recepție rămase neprocesate.
- **Administratorul** și **super-administratorul** pot modifica regulile generale și pot adăuga excepții pentru un rol sau o locație.

Destinatarii unei alerte sunt calculați din rolurile și locațiile active. Schimbarea responsabilului unei locații actualizează și aria alertelor sale.
MARKDOWN;

        $this->insertRevision(
            $article,
            7,
            $body,
            'Vizibilitatea alertelor și administrarea regulilor.',
        );
    }

    private function publishReleaseNote(): void
    {
        if (DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->exists()) {
            throw new RuntimeException('Nota de versiune pentru alerte există deja.');
        }

        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.07.29.5',
            'title' => 'Alerte pentru stoc și documente de recepție',
            'summary' => 'Aplicația semnalează loturile care expiră și documentele de recepție rămase neprocesate, cu vizibilitate adaptată rolului și locației.',
            'body_markdown' => <<<'MARKDOWN'
# Ce s-a schimbat

- a fost adăugată pagina **Gestiune → Alerte**;
- loturile cu stoc pozitiv și termen completat sunt semnalate înainte de expirare;
- loturile expirate sunt marcate ca alerte critice;
- documentele trimise din teren sunt semnalate dacă rămân neprocesate peste intervalul configurat;
- fiecare alertă deschide direct lotul sau înregistrarea care trebuie verificată;
- alertele se închid automat când motivul dispare;
- utilizatorii primesc notificări interne fără dubluri la fiecare verificare;
- vizibilitatea respectă rolul și locațiile administrate;
- administratorii pot configura reguli generale și excepții pentru roluri sau locații;
- câmpurile opționale necompletate nu generează alerte inutile.

Pragurile inițiale sunt de 30 de zile înaintea expirării unui lot și de 2 zile pentru documentele de recepție neprocesate.
MARKDOWN,
            'audience_roles' => json_encode(['all'], JSON_UNESCAPED_UNICODE),
            'affected_modules' => json_encode(
                ['Alerte', 'Fișă inventar materiale', 'Documente de procesat', 'Notificări'],
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
            || $note->version !== '2026.07.29.5'
            || $note->title !== 'Alerte pentru stoc și documente de recepție'
            || $note->status !== 'published'
        ) {
            throw new RuntimeException('Nota despre alerte a fost modificată; revenirea a fost oprită.');
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
