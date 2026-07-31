<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RELEASE_SLUG = '2026-07-31-previzualizare-documente-receptie';

    private const PAGES_CHANGE_SUMMARY = 'Previzualizarea documentelor și adăugarea pozițiilor la sfârșitul listelor.';

    private const MATERIALS_CHANGE_SUMMARY = 'Documentele sursă rămân disponibile în timpul completării recepției.';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->revisePagesArticle();
            $this->reviseMaterialsArticle();
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
            $this->restoreArticle('circuitul-materialelor', 8, self::MATERIALS_CHANGE_SUMMARY);
            $this->restoreArticle('pagini-si-operatiuni', 18, self::PAGES_CHANGE_SUMMARY);
        });
    }

    private function revisePagesArticle(): void
    {
        $article = $this->lockedArticle('pagini-si-operatiuni', 17);
        $body = $this->replaceOrFail(
            (string) $article->body_markdown,
            'Butonul **Trimite documente** permite încărcarea mai multor fotografii sau fișiere PDF pentru o locație. Încărcarea nu modifică stocul.',
            <<<'MARKDOWN'
Butonul **Trimite documente** permite încărcarea mai multor fotografii sau fișiere PDF pentru o locație. Încărcarea nu modifică stocul.

În detaliile unei înregistrări, apasă pe numele documentului pentru a-l previzualiza direct în aplicație. Butonul de descărcare rămâne disponibil separat. Fotografiile și fișierele PDF pot fi deschise în previzualizare; pentru un format care nu este acceptat de browser se folosește descărcarea.

La crearea unei recepții pornite din **Documente de procesat**, documentele sursă rămân disponibile într-un panou alături de formular pe calculator. Panoul poate fi restrâns sau extins. Pe telefon, butonul **Documente** deschide o vizualizare pe tot ecranul și revine apoi la formular fără să șteargă datele introduse.

În listele în care pot fi adăugate mai multe materiale, poziții sau fișiere, butonul de adăugare apare după ultima înregistrare. După adăugare, aplicația mută atenția la noua poziție.
MARKDOWN,
        );

        $this->insertRevision(
            $article,
            18,
            $body,
            self::PAGES_CHANGE_SUMMARY,
        );
    }

    private function reviseMaterialsArticle(): void
    {
        $article = $this->lockedArticle('circuitul-materialelor', 7);
        $body = $this->replaceOrFail(
            (string) $article->body_markdown,
            'În recepție se pot adăuga mai multe materiale. Pentru fiecare material se păstrează separat cantitatea, numărul lotului, termenul de expirare, prețul unitar fără TVA, moneda și observațiile. Furnizorul și documentul principal aparțin recepției.',
            <<<'MARKDOWN'
Documentele primite pot fi previzualizate direct în aplicație, fără descărcare. Descărcarea rămâne o acțiune separată. Când recepția este pornită dintr-o înregistrare preliminară, documentul selectat rămâne disponibil în timpul completării formularului: într-un panou alături de formular pe calculator și într-o vizualizare pe tot ecranul pe telefon.

În recepție se pot adăuga mai multe materiale. Butonul **Adaugă material** apare după ultimul material, astfel încât următoarea poziție poate fi introdusă fără revenire la începutul listei. Pentru fiecare material se păstrează separat cantitatea, numărul lotului, termenul de expirare, prețul unitar fără TVA, moneda și observațiile. Furnizorul și documentul principal aparțin recepției.
MARKDOWN,
        );

        $this->insertRevision(
            $article,
            8,
            $body,
            self::MATERIALS_CHANGE_SUMMARY,
        );
    }

    private function publishReleaseNote(): void
    {
        if (DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->exists()) {
            throw new RuntimeException('Nota despre previzualizarea documentelor de recepție există deja.');
        }

        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.07.31.3',
            'title' => 'Documentele rămân la vedere în timpul recepției',
            'summary' => 'Fotografiile și fișierele PDF pot fi previzualizate în aplicație în timp ce sunt completate materialele recepției.',
            'body_markdown' => <<<'MARKDOWN'
# Ce s-a schimbat

- documentele din **Documente de procesat** se deschid într-o previzualizare în aplicație;
- descărcarea fișierului rămâne disponibilă printr-un buton separat;
- la crearea recepției, documentele sursă rămân disponibile lângă formular pe calculator;
- panoul documentului poate fi restrâns sau extins, iar fotografiile pot fi mărite și rotite;
- pe telefon, documentele se deschid pe tot ecranul și formularul își păstrează datele introduse;
- butoanele pentru adăugarea materialelor, pozițiilor și fișierelor apar după ultima înregistrare din listă;
- după adăugare, aplicația duce utilizatorul direct la noua poziție.

# Ce trebuie să faci

Apasă pe numele unui document pentru previzualizare sau pe pictograma de descărcare pentru a salva fișierul. În formularul recepției, folosește butonul **Documente** dacă panoul este restrâns sau dacă lucrezi de pe telefon.
MARKDOWN,
            'audience_roles' => json_encode(
                ['super-admin', 'admin', 'dispecer', 'manager', 'sef-santier', 'gestionar-baza', 'muncitor', 'contabil'],
                JSON_UNESCAPED_UNICODE,
            ),
            'affected_modules' => json_encode(
                ['Documente de procesat', 'Recepții', 'Comenzi negociate', 'Consum', 'Transferuri', 'Proiecte materiale'],
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
            || $note->version !== '2026.07.31.3'
            || $note->title !== 'Documentele rămân la vedere în timpul recepției'
            || $note->status !== 'published'
        ) {
            throw new RuntimeException('Nota despre previzualizarea documentelor a fost modificată; revenirea a fost oprită.');
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
                "Articolul {$slug} are revizia {$actual}; era așteptată revizia {$expectedRevision}.",
            );
        }

        return $article;
    }

    private function replaceOrFail(string $body, string $search, string $replacement): string
    {
        if (! str_contains($body, $search)) {
            throw new RuntimeException('Conținutul așteptat nu a fost găsit în articolul de ajutor.');
        }

        return str_replace($search, $replacement, $body);
    }

    private function insertRevision(object $article, int $revision, string $body, string $changeSummary): void
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
