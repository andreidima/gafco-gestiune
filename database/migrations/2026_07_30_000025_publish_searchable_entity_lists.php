<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RELEASE_SLUG = '2026-07-30-liste-cautabile-in-aplicatie';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->reviseArticle(
                'pagini-si-operatiuni',
                13,
                14,
                <<<'MARKDOWN'

## Liste cautabile în formulare și filtre

Listele în care se aleg furnizori, materiale, locații, proiecte, persoane, șoferi sau echipamente permit acum scrierea directă pentru găsirea rapidă a unei opțiuni.

Căutarea verifică orice parte a denumirii și identificatorii disponibili, precum CUI-ul furnizorului, codul materialului, codul locației, codul echipamentului sau codul utilizatorului. Ordinea cuvintelor, literele mari și diacriticele nu schimbă rezultatele.

Listele scurte, precum statusul, moneda, prioritatea sau tipul documentului, rămân liste simple.
MARKDOWN,
                'Căutarea consecventă în listele de entități din formulare și filtre.',
            );

            $this->reviseArticle(
                'ghiduri-dupa-rol',
                14,
                15,
                <<<'MARKDOWN'

## Alegerea rapidă din liste

În listele pentru furnizori, materiale, locații, proiecte, persoane, șoferi și echipamente poți începe să scrii orice parte din denumire sau cod. De exemplu, un material poate fi găsit după denumire ori SKU, iar un furnizor după denumire ori CUI.

Căutarea nu ține cont de litere mari, litere mici sau diacritice. Dacă scrii mai multe cuvinte, acestea pot apărea în orice ordine în informațiile opțiunii.
MARKDOWN,
                'Regula comună pentru căutarea și alegerea înregistrărilor din liste.',
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
            $this->restoreArticle(
                'ghiduri-dupa-rol',
                15,
                'Regula comună pentru căutarea și alegerea înregistrărilor din liste.',
            );
            $this->restoreArticle(
                'pagini-si-operatiuni',
                14,
                'Căutarea consecventă în listele de entități din formulare și filtre.',
            );
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
            throw new RuntimeException('Nota despre listele căutabile există deja.');
        }

        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.07.30.3',
            'title' => 'Căutare rapidă în listele din aplicație',
            'summary' => 'Furnizorii, materialele, locațiile, proiectele, persoanele și echipamentele pot fi găsite după orice parte a denumirii sau codului.',
            'body_markdown' => <<<'MARKDOWN'
# Ce s-a schimbat

- listele de furnizori, materiale, locații, proiecte, persoane, șoferi și echipamente permit căutarea directă;
- o înregistrare poate fi găsită după orice parte a denumirii sau a codului;
- furnizorii pot fi căutați și după CUI, materialele după SKU, iar utilizatorii și echipamentele după cod;
- căutarea nu ține cont de litere mari, litere mici sau diacritice;
- listele scurte, precum statusul, moneda sau prioritatea, rămân simple.

# Ce trebuie să facă utilizatorul

Deschide lista și începe să scrii denumirea sau codul căutat. Nu este necesară nicio configurare.
MARKDOWN,
            'audience_roles' => json_encode([
                'super-admin', 'admin', 'dispecer', 'manager', 'sef-santier',
                'gestionar-baza', 'sofer', 'muncitor', 'contabil', 'user',
            ], JSON_UNESCAPED_UNICODE),
            'affected_modules' => json_encode([
                'Liste și filtre', 'Recepții', 'Transferuri', 'Consum',
                'Proiecte', 'Sarcini', 'Custodie personală',
            ], JSON_UNESCAPED_UNICODE),
            'requires_action' => false,
            'status' => 'published',
            'released_at' => '2026-07-30',
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
            || $note->version !== '2026.07.30.3'
            || $note->title !== 'Căutare rapidă în listele din aplicație'
            || $note->status !== 'published'
        ) {
            throw new RuntimeException('Nota despre listele căutabile a fost modificată; revenirea a fost oprită.');
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

            throw new RuntimeException("Articolul {$slug} are revizia {$actual}; era așteptată revizia {$expectedRevision}.");
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
