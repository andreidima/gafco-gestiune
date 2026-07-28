<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RELEASE_SLUG = '2026-07-28-fisa-inventar-materiale';

    public function up(): void
    {
        DB::transaction(function (): void {
            $this->reviseArticle(
                'incepe-de-aici',
                1,
                <<<'MARKDOWN'

## Fișa de inventar materiale

Pagina **Gestiune → Fișă inventar materiale** este punctul principal pentru verificarea cantităților. Ea include materialele cu stoc și materialele ajunse la zero, iar opțiunea **Ascunde stocul zero** poate compacta lista.

Selectarea unei locații limitează totalurile, loturile și istoricul la acea locație. Utilizatorul vede numai locațiile pentru care are drept de consultare.
MARKDOWN
            );

            $this->reviseArticle(
                'cum-functioneaza-aplicatia',
                1,
                <<<'MARKDOWN'

## 6. Fișa de inventar și istoricul cantităților

Stocul curent este însoțit de o fișă de mișcări. Recepțiile, consumurile și transferurile păstrează originea cantităților, locația și momentul operațiunii.

Cantitățile existente înaintea activării fișei au fost preluate ca **Sold inițial**. Pentru aceste cantități aplicația nu inventează furnizori, documente, prețuri sau termene de expirare.

Filtrele, coloanele vizibile și densitatea tabelului sunt salvate în contul utilizatorului și se păstrează pe celelalte dispozitive.
MARKDOWN
            );

            $this->reviseArticle(
                'circuitul-materialelor',
                1,
                <<<'MARKDOWN'

## Evidența pe loturi

Fiecare cantitate intrată după activarea fișei este legată de un lot de inventar. Un lot poate păstra furnizorul, documentul, data intrării, data expirării și prețul, atunci când aceste informații există.

La un consum sau transfer, aplicația păstrează loturile din care au provenit cantitățile. Totalul curent și istoricul pot fi consultate din fișa materialului.

Stocul agregat al locației și totalul loturilor sunt actualizate împreună. Dacă aplicația detectează o neconcordanță care ar produce un stoc negativ, operațiunea este oprită pentru verificare.
MARKDOWN
            );

            $this->reviseArticle(
                'ghiduri-dupa-rol',
                1,
                <<<'MARKDOWN'

## Manager

- consultă toate locațiile, materialele, echipamentele, transferurile, sarcinile și rapoartele;
- poate deschide fișa de inventar și istoricul materialelor;
- nu poate crea, modifica, aproba, confirma sau închide operațiuni;
- informațiile comerciale sunt disponibile numai în paginile în care acest lucru este permis.

## Vizibilitatea locațiilor

Gestionarii de bază și șefii de șantier văd numai locațiile active care le sunt alocate. Această regulă se aplică listelor, filtrelor și câmpurilor de selectare a locației.
MARKDOWN
            );

            $this->reviseArticle(
                'pagini-si-operatiuni',
                1,
                <<<'MARKDOWN'

## Fișă inventar materiale

Pagina centralizează:

- cantitatea totală pe material și unitate de măsură;
- distribuția cantității pe locații;
- loturile active și datele lor disponibile;
- soldurile inițiale preluate din aplicație;
- istoricul recepțiilor, consumurilor și transferurilor.

Apăsarea unui material deschide fișa detaliată. Filtrele se aplică automat, iar preferințele de afișare sunt memorate în cont.
MARKDOWN
            );

            DB::table('release_notes')->insert([
                'slug' => self::RELEASE_SLUG,
                'version' => '2026.07.28.2',
                'title' => 'Fișă completă de inventar pentru materiale',
                'summary' => 'Materialele pot fi consultate pe locații și loturi, cu sold inițial, istoric și preferințe de afișare salvate.',
                'body_markdown' => <<<'MARKDOWN'
# Ce este nou

- a fost adăugată pagina **Fișă inventar materiale**;
- materialele sunt centralizate pentru o locație sau pentru toate locațiile permise;
- materialele cu stoc zero rămân vizibile și pot fi ascunse dintr-un filtru;
- fiecare material are o pagină cu loturile curente și istoricul mișcărilor;
- stocurile existente au fost preluate ca **Sold inițial**, fără completarea unor informații care nu existau;
- recepțiile, consumurile și transferurile actualizează împreună totalul și istoricul pe loturi;
- filtrele, coloanele și densitatea tabelului se salvează în contul utilizatorului;
- a fost introdus rolul **Manager**, cu vizibilitate generală și fără drept de modificare;
- șefii de șantier și gestionarii văd numai locațiile care le sunt alocate.

Utilizatorii pot continua operațiunile existente în același mod. Pentru verificarea stocului este recomandată noua fișă de inventar.
MARKDOWN,
                'audience_roles' => json_encode([
                    'super-admin', 'admin', 'dispecer', 'manager',
                    'sef-santier', 'gestionar-baza', 'contabil',
                ], JSON_UNESCAPED_UNICODE),
                'affected_modules' => json_encode([
                    'Inventar', 'Materiale', 'Recepții', 'Consum', 'Transferuri', 'Roluri',
                ], JSON_UNESCAPED_UNICODE),
                'requires_action' => false,
                'status' => 'published',
                'released_at' => '2026-07-28',
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->delete();

            foreach ([
                'incepe-de-aici',
                'cum-functioneaza-aplicatia',
                'circuitul-materialelor',
                'ghiduri-dupa-rol',
                'pagini-si-operatiuni',
            ] as $slug) {
                $article = DB::table('help_articles')->where('slug', $slug)->lockForUpdate()->first();
                if (! $article || (int) $article->current_revision !== 2) {
                    continue;
                }
                $previous = DB::table('help_article_revisions')
                    ->where('help_article_id', $article->id)
                    ->where('revision', 1)
                    ->first();
                if (! $previous) {
                    continue;
                }

                DB::table('help_article_revisions')
                    ->where('help_article_id', $article->id)
                    ->where('revision', 2)
                    ->delete();
                DB::table('help_articles')->where('id', $article->id)->update([
                    'title' => $previous->title,
                    'summary' => $previous->summary,
                    'body_markdown' => $previous->body_markdown,
                    'current_revision' => 1,
                    'published_at' => $previous->published_at,
                    'updated_at' => now(),
                ]);
            }
        });
    }

    private function reviseArticle(string $slug, int $expectedRevision, string $appendix): void
    {
        $article = DB::table('help_articles')->where('slug', $slug)->lockForUpdate()->first();
        if (! $article) {
            throw new RuntimeException("Articolul de ajutor {$slug} nu există.");
        }
        if ((int) $article->current_revision !== $expectedRevision) {
            throw new RuntimeException(
                "Articolul {$slug} are revizia {$article->current_revision}; era așteptată revizia {$expectedRevision}. Publicarea a fost oprită pentru a proteja modificările editoriale."
            );
        }

        $nextRevision = $expectedRevision + 1;
        $body = rtrim((string) $article->body_markdown)."\n".$appendix;
        DB::table('help_article_revisions')->insert([
            'help_article_id' => $article->id,
            'revision' => $nextRevision,
            'title' => $article->title,
            'summary' => $article->summary,
            'body_markdown' => $body,
            'change_summary' => 'Fișa de inventar, istoricul pe loturi, rolul Manager și vizibilitatea locațiilor.',
            'source' => 'system',
            'created_by' => null,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('help_articles')->where('id', $article->id)->update([
            'body_markdown' => $body,
            'current_revision' => $nextRevision,
            'updated_by' => null,
            'published_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
