<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RELEASE_SLUG = '2026-08-02-interfata-complet-in-romana';

    private const PAGES_CHANGE_SUMMARY = 'Adrese, selectarea fișierelor și mesaje de sistem în limba română.';

    private const HELP_ADDITION = <<<'MARKDOWN'

## Interfața și adresele paginilor

Adresele paginilor pe care le deschid utilizatorii sunt afișate în limba română. De exemplu, pagina **Documente de procesat** folosește adresa `/documente-de-procesat`, iar formularul **Trimite documente** folosește `/documente-de-procesat/trimite`.

Linkurile mai vechi, scrise în engleză, continuă să funcționeze și trimit automat la noua adresă. Acest lucru este valabil și pentru linkurile păstrate în notificări, favorite sau coduri QR tipărite anterior.

La încărcarea documentelor, selectorul afișează **Alege fișierul** și **Niciun fișier selectat**, indiferent de limba browserului. Mesajele de validare și paginile de eroare uzuale sunt de asemenea afișate în română.
MARKDOWN;

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->revisePagesArticle();
            $this->localizeHistoricalReleaseNote();
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
            $this->restoreHistoricalReleaseNote();
            $this->restoreArticle();
        });
    }

    private function revisePagesArticle(): void
    {
        $article = $this->lockedArticle(19);
        $replacementCount = 0;
        $localizedBody = str_replace(
            ['Vizualizare Live', 'controlul **Live**'],
            ['Actualizare automată', 'controlul **Actualizare automată**'],
            (string) $article->body_markdown,
            $replacementCount,
        );

        if ($replacementCount !== 2) {
            throw new RuntimeException('Termenii în engleză așteptați nu au fost găsiți în articolul de ajutor.');
        }

        $body = rtrim($localizedBody).self::HELP_ADDITION;

        if (substr_count($body, '## Interfața și adresele paginilor') !== 1) {
            throw new RuntimeException('Secțiunea despre interfața în limba română există deja în articolul de ajutor.');
        }

        $this->insertRevision($article, 20, $body);
    }

    private function localizeHistoricalReleaseNote(): void
    {
        $note = DB::table('release_notes')
            ->where('slug', '2026-07-29-claritate-operationala-si-vizualizare-live')
            ->lockForUpdate()
            ->first();

        if (! $note || $note->version !== '2026.07.29.8' || $note->status !== 'published') {
            throw new RuntimeException('Nota despre actualizarea automată nu corespunde versiunii așteptate.');
        }

        $body = str_replace(
            'un control **Live** cu actualizare automată',
            'un control de **actualizare automată**',
            (string) $note->body_markdown,
            $replacementCount,
        );

        if ($replacementCount !== 1) {
            throw new RuntimeException('Termenul Live nu a fost găsit în nota de versiune așteptată.');
        }

        DB::table('release_notes')->where('id', $note->id)->update([
            'body_markdown' => $body,
            'updated_at' => now(),
        ]);

        $mobileNote = DB::table('release_notes')
            ->where('slug', '2026-07-30-interfata-mobila-compacta')
            ->lockForUpdate()
            ->first();

        if (! $mobileNote || $mobileNote->version !== '2026.07.30.1' || $mobileNote->status !== 'published') {
            throw new RuntimeException('Nota despre interfața mobilă nu corespunde versiunii așteptate.');
        }

        $mobileBody = str_replace(
            'dashboardul șoferului',
            'panoul principal al șoferului',
            (string) $mobileNote->body_markdown,
            $mobileReplacementCount,
        );

        if ($mobileReplacementCount !== 1) {
            throw new RuntimeException('Termenul dashboard nu a fost găsit în nota de versiune așteptată.');
        }

        DB::table('release_notes')->where('id', $mobileNote->id)->update([
            'body_markdown' => $mobileBody,
            'updated_at' => now(),
        ]);
    }

    private function restoreHistoricalReleaseNote(): void
    {
        $note = DB::table('release_notes')
            ->where('slug', '2026-07-29-claritate-operationala-si-vizualizare-live')
            ->lockForUpdate()
            ->first();

        if (! $note || $note->version !== '2026.07.29.8' || $note->status !== 'published') {
            throw new RuntimeException('Nota despre actualizarea automată a fost modificată; revenirea a fost oprită.');
        }

        $body = str_replace(
            'un control de **actualizare automată**',
            'un control **Live** cu actualizare automată',
            (string) $note->body_markdown,
            $replacementCount,
        );

        if ($replacementCount !== 1) {
            throw new RuntimeException('Nota despre actualizarea automată nu mai conține textul așteptat.');
        }

        DB::table('release_notes')->where('id', $note->id)->update([
            'body_markdown' => $body,
            'updated_at' => now(),
        ]);

        $mobileNote = DB::table('release_notes')
            ->where('slug', '2026-07-30-interfata-mobila-compacta')
            ->lockForUpdate()
            ->first();

        if (! $mobileNote || $mobileNote->version !== '2026.07.30.1' || $mobileNote->status !== 'published') {
            throw new RuntimeException('Nota despre interfața mobilă a fost modificată; revenirea a fost oprită.');
        }

        $mobileBody = str_replace(
            'panoul principal al șoferului',
            'dashboardul șoferului',
            (string) $mobileNote->body_markdown,
            $mobileReplacementCount,
        );

        if ($mobileReplacementCount !== 1) {
            throw new RuntimeException('Nota despre interfața mobilă nu mai conține textul așteptat.');
        }

        DB::table('release_notes')->where('id', $mobileNote->id)->update([
            'body_markdown' => $mobileBody,
            'updated_at' => now(),
        ]);
    }

    private function publishReleaseNote(): void
    {
        if (DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->exists()) {
            throw new RuntimeException('Nota despre interfața în limba română există deja.');
        }

        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.08.02',
            'title' => 'Interfață completă în limba română',
            'summary' => 'Adresele paginilor, selectorul de fișiere și mesajele de sistem sunt acum afișate în română.',
            'body_markdown' => <<<'MARKDOWN'
# Ce s-a schimbat

- adresele paginilor vizibile utilizatorilor sunt acum în limba română;
- linkurile vechi în engleză și codurile QR tipărite anterior continuă să funcționeze și trimit automat la adresa nouă;
- selectorul de documente afișează **Alege fișierul** și numele fișierului în română, indiferent de limba browserului;
- mesajele standard de validare și paginile de eroare uzuale sunt afișate în română.

# Ce trebuie să faci

Nu este necesară nicio acțiune. Poți folosi în continuare linkurile și codurile QR existente. Când salvezi o pagină nouă la favorite sau copiezi un link, vei vedea adresa în limba română.
MARKDOWN,
            'audience_roles' => json_encode(
                ['super-admin', 'admin', 'dispecer', 'manager', 'sef-santier', 'gestionar-baza', 'sofer', 'muncitor', 'contabil'],
                JSON_UNESCAPED_UNICODE,
            ),
            'affected_modules' => json_encode(['Navigare', 'Documente', 'Validare', 'Mesaje de sistem'], JSON_UNESCAPED_UNICODE),
            'requires_action' => false,
            'status' => 'published',
            'released_at' => '2026-08-02',
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
            || $note->version !== '2026.08.02'
            || $note->title !== 'Interfață completă în limba română'
            || $note->status !== 'published'
        ) {
            throw new RuntimeException('Nota despre interfața în limba română a fost modificată; revenirea a fost oprită.');
        }

        DB::table('release_notes')->where('id', $note->id)->delete();
    }

    private function lockedArticle(int $expectedRevision): object
    {
        $article = DB::table('help_articles')
            ->where('slug', 'pagini-si-operatiuni')
            ->lockForUpdate()
            ->first();

        if (! $article || (int) $article->current_revision !== $expectedRevision) {
            $actual = $article ? (int) $article->current_revision : 'inexistent';

            throw new RuntimeException(
                "Articolul pagini-si-operatiuni are revizia {$actual}; era așteptată revizia {$expectedRevision}.",
            );
        }

        return $article;
    }

    private function insertRevision(object $article, int $revision, string $body): void
    {
        $timestamp = now();

        DB::table('help_article_revisions')->insert([
            'help_article_id' => $article->id,
            'revision' => $revision,
            'title' => $article->title,
            'summary' => $article->summary,
            'body_markdown' => $body,
            'change_summary' => self::PAGES_CHANGE_SUMMARY,
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

    private function restoreArticle(): void
    {
        $article = $this->lockedArticle(20);
        $current = DB::table('help_article_revisions')
            ->where('help_article_id', $article->id)
            ->where('revision', 20)
            ->first();

        if (! $current
            || $current->source !== 'system'
            || $current->change_summary !== self::PAGES_CHANGE_SUMMARY
            || $current->body_markdown !== $article->body_markdown
        ) {
            throw new RuntimeException('Articolul pagini-si-operatiuni a fost modificat; revenirea a fost oprită.');
        }

        $previous = DB::table('help_article_revisions')
            ->where('help_article_id', $article->id)
            ->where('revision', 19)
            ->first();

        if (! $previous) {
            throw new RuntimeException('Revizia anterioară a articolului pagini-si-operatiuni nu există.');
        }

        DB::table('help_article_revisions')
            ->where('help_article_id', $article->id)
            ->where('revision', 20)
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
