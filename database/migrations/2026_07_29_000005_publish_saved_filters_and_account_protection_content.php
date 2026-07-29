<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RELEASE_SLUG = '2026-07-29-filtre-memorate-si-administrare-conturi';

    private const IMPERSONATION_RELEASE_SLUG = '2026-07-29-schimbare-utilizator';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->revisePagesArticle();
            $this->reviseRolesArticle();
            $this->correctImpersonationReleaseNote();
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
            $this->restoreImpersonationReleaseNote();
            $this->restoreArticle('ghiduri-dupa-rol', 4, 'Filtre memorate și administrarea conturilor disponibile.');
            $this->restoreArticle('pagini-si-operatiuni', 3, 'Filtre memorate și coduri de locație standardizate.');
        });
    }

    private function revisePagesArticle(): void
    {
        $article = $this->lockedArticle('pagini-si-operatiuni', 2);
        $body = rtrim((string) $article->body_markdown)."\n".<<<'MARKDOWN'

## Filtrele listelor

Selecțiile din listele derulante și filtrele de tip bifă sunt memorate în contul utilizatorului. Ele sunt reaplicate când utilizatorul revine în aceeași pagină și sunt disponibile după conectarea de pe alt dispozitiv.

Câmpurile în care se scrie text și intervalele de dată nu sunt memorate. Butonul de resetare șterge și selecțiile salvate pentru pagina respectivă.

## Codurile locațiilor

Codurile locațiilor sunt standardizate automat cu litere majuscule, indiferent cum sunt introduse la creare sau modificare.
MARKDOWN;

        $this->insertRevision(
            $article,
            3,
            $body,
            'Filtre memorate și coduri de locație standardizate.',
        );
    }

    private function reviseRolesArticle(): void
    {
        $article = $this->lockedArticle('ghiduri-dupa-rol', 3);
        $body = str_replace(
            [
                '## Administrator și super-administrator',
                'Administratorii și super-administratorii pot folosi',
            ],
            [
                '## Administrator',
                'Administratorii pot folosi',
            ],
            (string) $article->body_markdown,
        );

        if ($body === $article->body_markdown) {
            throw new RuntimeException('Conținutul despre roluri nu conține formulările așteptate.');
        }

        $body = rtrim($body)."\n".<<<'MARKDOWN'

## Administrarea conturilor

Pagina **Utilizatori** afișează numai conturile și rolurile care pot fi administrate de utilizatorul conectat. Rolurile interne și conturile protejate nu apar ca opțiuni de creare sau modificare.
MARKDOWN;

        $this->insertRevision(
            $article,
            4,
            $body,
            'Filtre memorate și administrarea conturilor disponibile.',
        );
    }

    private function correctImpersonationReleaseNote(): void
    {
        $note = DB::table('release_notes')
            ->where('slug', self::IMPERSONATION_RELEASE_SLUG)
            ->lockForUpdate()
            ->first();

        if (! $note) {
            throw new RuntimeException('Nota despre schimbarea utilizatorului nu există.');
        }

        $corrected = str_replace(
            '- administratorii și super-administratorii au un buton nou',
            '- administratorii au un buton nou',
            (string) $note->body_markdown,
        );

        if ($corrected === $note->body_markdown) {
            throw new RuntimeException('Nota despre schimbarea utilizatorului nu are formularea așteptată.');
        }

        DB::table('release_notes')->where('id', $note->id)->update([
            'body_markdown' => $corrected,
            'updated_at' => now(),
        ]);
    }

    private function restoreImpersonationReleaseNote(): void
    {
        $note = DB::table('release_notes')
            ->where('slug', self::IMPERSONATION_RELEASE_SLUG)
            ->lockForUpdate()
            ->first();

        if (! $note) {
            throw new RuntimeException('Nota despre schimbarea utilizatorului nu există.');
        }

        $restored = str_replace(
            '- administratorii au un buton nou',
            '- administratorii și super-administratorii au un buton nou',
            (string) $note->body_markdown,
        );

        if ($restored === $note->body_markdown) {
            throw new RuntimeException('Corecția notei despre schimbarea utilizatorului a fost modificată.');
        }

        DB::table('release_notes')->where('id', $note->id)->update([
            'body_markdown' => $restored,
            'updated_at' => now(),
        ]);
    }

    private function publishReleaseNote(): void
    {
        if (DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->exists()) {
            throw new RuntimeException('Nota de versiune pentru filtrele memorate există deja.');
        }

        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.07.29.2',
            'title' => 'Filtre memorate și administrare standardizată',
            'summary' => 'Selecțiile folosite frecvent în filtre sunt păstrate în cont, iar codurile locațiilor și administrarea rolurilor sunt standardizate.',
            'body_markdown' => <<<'MARKDOWN'
# Ce s-a schimbat

- selecțiile din listele derulante și filtrele de tip bifă sunt memorate separat pentru fiecare utilizator;
- filtrele memorate sunt disponibile și după conectarea de pe alt dispozitiv;
- textele introduse și intervalele de dată nu sunt memorate;
- resetarea filtrelor șterge selecțiile salvate pentru pagina respectivă;
- codurile locațiilor sunt salvate automat cu litere majuscule;
- pagina de utilizatori afișează numai conturile și rolurile care pot fi administrate.

Nu este necesară nicio acțiune din partea utilizatorilor.
MARKDOWN,
            'audience_roles' => json_encode(['all'], JSON_UNESCAPED_UNICODE),
            'affected_modules' => json_encode(
                ['Liste și filtre', 'Locații', 'Utilizatori'],
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
            || $note->version !== '2026.07.29.2'
            || $note->title !== 'Filtre memorate și administrare standardizată'
            || $note->status !== 'published'
        ) {
            throw new RuntimeException('Nota despre filtrele memorate a fost modificată; revenirea a fost oprită.');
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
