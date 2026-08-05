<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ARTICLE_SLUG = 'administrarea-accesului';

    private const EXPECTED_ARTICLE_REVISION = 2;

    private const CHANGE_SUMMARY = 'Aplicarea drepturilor și a domeniilor configurate în toate fluxurile aplicației.';

    private const RELEASE_SLUG = '2026-08-05-aplicarea-drepturilor-configurate';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->reviseHelpArticle();
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
            $this->restoreHelpArticle();
        });
    }

    private function reviseHelpArticle(): void
    {
        $article = $this->lockedArticle(self::EXPECTED_ARTICLE_REVISION);
        $body = rtrim((string) $article->body_markdown)."\n".<<<'MARKDOWN'

## Aplicarea drepturilor configurate

Drepturile afișate în fișa de acces controlează acum efectiv paginile, acțiunile și înregistrările disponibile utilizatorului. Denumirea unui rol nu acordă acces de una singură: contează drepturile atribuite rolului și eventualele excepții individuale.

O modificare a drepturilor se aplică imediat tuturor utilizatorilor acelui rol. Retragerea unui drept ascunde opțiunile corespunzătoare și blochează accesarea lor directă. Acordarea unui drept nou activează numai domeniul indicat în fișa de acces.

## Cum se interpretează domeniul de acces

- **Global**: utilizatorul poate consulta sau administra toate înregistrările acoperite de drept.
- **Locațiile administrate** sau **înregistrări vizibile**: accesul este limitat la locațiile active atribuite utilizatorului și, unde fluxul o permite, la înregistrările create de el.
- **Înregistrări alocate**: utilizatorul vede numai sarcinile ori transferurile care i-au fost alocate.
- **Personal**: utilizatorul vede numai custodia și operațiunile proprii.
- **Locație selectată**: utilizatorul alege locația activă pentru operațiunea punctuală permisă.

Fișa de acces și aplicația folosesc aceeași evaluare. Astfel, administratorul poate verifica pentru fiecare drept dacă este permis, de unde provine, ce domeniu are și ce locații îl limitează.

## Verificarea după modificarea unui rol

După schimbarea unui rol, deschideți fișa de acces a unui utilizator afectat și verificați drepturile importante, domeniul și avertismentele. Pentru rolurile locale, confirmați că utilizatorul are cel puțin o locație administrată activă. Folosiți excepțiile individuale numai când nevoia nu trebuie acordată tuturor utilizatorilor rolului.
MARKDOWN;
        $revision = self::EXPECTED_ARTICLE_REVISION + 1;
        $timestamp = now();

        DB::table('help_article_revisions')->insert([
            'help_article_id' => $article->id,
            'revision' => $revision,
            'title' => $article->title,
            'summary' => $article->summary,
            'body_markdown' => $body,
            'change_summary' => self::CHANGE_SUMMARY,
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

    private function publishReleaseNote(): void
    {
        if (DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->exists()) {
            throw new RuntimeException('Nota despre aplicarea drepturilor configurate există deja.');
        }

        $timestamp = now();
        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.08.05.3',
            'title' => 'Drepturile configurate controlează accesul efectiv',
            'summary' => 'Rolurile și excepțiile configurate se aplică unitar paginilor, acțiunilor și domeniului de date.',
            'body_markdown' => <<<'MARKDOWN'
# Ce este nou

- drepturile configurate pentru roluri controlează efectiv paginile și acțiunile disponibile;
- retragerea unui drept blochează imediat accesul, fără reguli ascunse bazate pe denumirea rolului;
- rolurile personalizate și excepțiile individuale sunt respectate în aceleași condiții ca rolurile standard;
- accesul la date este limitat unitar la domeniul global, locațiile administrate, înregistrările alocate sau datele personale;
- meniul afișează opțiunile conform drepturilor efective;
- fișa de acces a utilizatorului folosește aceeași evaluare ca aplicația, astfel încât administratorul poate verifica exact ce se va întâmpla.

# Ce trebuie să facă administratorii

Verificați rolurile pe care le-ați personalizat și fișele de acces ale utilizatorilor afectați. Acordați locații active rolurilor locale și corectați avertismentele afișate. Nu este necesară nicio acțiune pentru rolurile standard care nu au fost modificate.
MARKDOWN,
            'audience_roles' => json_encode(['admin', 'super-admin'], JSON_UNESCAPED_UNICODE),
            'affected_modules' => json_encode(['Administrare acces', 'Utilizatori', 'Meniu', 'Transferuri', 'Sarcini', 'Recepții', 'Inventar', 'Custodie', 'Rapoarte', 'Alerte'], JSON_UNESCAPED_UNICODE),
            'requires_action' => true,
            'status' => 'published',
            'released_at' => '2026-08-05',
            'published_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    private function removeReleaseNote(): void
    {
        $note = DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->lockForUpdate()->first();
        if (! $note
            || $note->version !== '2026.08.05.3'
            || $note->title !== 'Drepturile configurate controlează accesul efectiv'
            || $note->status !== 'published') {
            throw new RuntimeException('Nota despre aplicarea drepturilor configurate a fost modificată; revenirea a fost oprită.');
        }

        DB::table('release_notes')->where('id', $note->id)->delete();
    }

    private function restoreHelpArticle(): void
    {
        $revision = self::EXPECTED_ARTICLE_REVISION + 1;
        $article = $this->lockedArticle($revision);
        $current = DB::table('help_article_revisions')
            ->where('help_article_id', $article->id)
            ->where('revision', $revision)
            ->first();
        if (! $current
            || $current->source !== 'system'
            || $current->change_summary !== self::CHANGE_SUMMARY
            || $current->body_markdown !== $article->body_markdown) {
            throw new RuntimeException('Articolul despre administrarea accesului a fost modificat; revenirea a fost oprită.');
        }

        $previous = DB::table('help_article_revisions')
            ->where('help_article_id', $article->id)
            ->where('revision', self::EXPECTED_ARTICLE_REVISION)
            ->first();
        if (! $previous) {
            throw new RuntimeException('Revizia anterioară a articolului despre administrarea accesului nu există.');
        }

        DB::table('help_article_revisions')->where('id', $current->id)->delete();
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

    private function lockedArticle(int $expectedRevision): object
    {
        $article = DB::table('help_articles')->where('slug', self::ARTICLE_SLUG)->lockForUpdate()->first();
        if (! $article || (int) $article->current_revision !== $expectedRevision) {
            $actual = $article ? (int) $article->current_revision : 'inexistent';
            throw new RuntimeException('Articolul '.self::ARTICLE_SLUG." are revizia {$actual}; era așteptată revizia {$expectedRevision}.");
        }

        return $article;
    }
};
