<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RELEASE_SLUG = '2026-07-30-navigare-si-cantitati-consecvente';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->reviseArticle(
                'pagini-si-operatiuni',
                12,
                13,
                <<<'MARKDOWN'

## Navigare și cantități consecvente

În liste, rapoarte și panouri, statusul unei înregistrări poate fi apăsat pentru a deschide pagina acesteia. Statusurile fără pagină proprie rămân simple informații.

Acțiunea **Înapoi** revine la pagina internă vizitată anterior, păstrând filtrele și paginarea. Dacă pagina a fost deschisă direct sau din afara aplicației, acțiunea folosește pagina principală sigură a secțiunii.

Cantitățile sunt afișate fără zerouri zecimale inutile. De exemplu, `5,000` apare ca `5`, iar `5,120` apare ca `5,12`.
MARKDOWN,
                'Navigarea prin statusuri, revenirea în context și afișarea cantităților.',
            );

            $this->reviseArticle(
                'ghiduri-dupa-rol',
                13,
                14,
                <<<'MARKDOWN'

## Reguli comune de navigare

Apasă statusul unui transfer sau al unei sarcini pentru a deschide înregistrarea. Folosește **Înapoi** pentru a reveni în locul din care ai venit. Cantitățile întregi nu mai afișează zerouri după virgulă, iar cantitățile fracționare păstrează numai zecimalele semnificative.
MARKDOWN,
                'Regulile comune pentru statusuri, revenire și afișarea cantităților.',
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
            $this->restoreArticle('ghiduri-dupa-rol', 14, 'Regulile comune pentru statusuri, revenire și afișarea cantităților.');
            $this->restoreArticle('pagini-si-operatiuni', 13, 'Navigarea prin statusuri, revenirea în context și afișarea cantităților.');
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
            throw new RuntimeException('Nota despre navigare și cantități există deja.');
        }

        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.07.30.2',
            'title' => 'Navigare mai rapidă și cantități mai clare',
            'summary' => 'Statusurile deschid înregistrările, revenirea păstrează contextul, iar cantitățile nu mai afișează zerouri inutile.',
            'body_markdown' => <<<'MARKDOWN'
# Ce s-a schimbat

- statusul unei înregistrări poate fi apăsat pentru a deschide pagina acesteia;
- butoanele **Înapoi** revin la pagina internă vizitată anterior și păstrează filtrele sau paginarea;
- dacă nu există o pagină anterioară sigură, aplicația revine la lista secțiunii;
- cantitățile afișează numai zecimalele semnificative.

# Ce trebuie să facă utilizatorul

Nu este necesară nicio configurare. Apasă un status pentru detalii și folosește **Înapoi** pentru a reveni în contextul anterior.
MARKDOWN,
            'audience_roles' => json_encode(['sofer', 'dispecer', 'manager', 'admin', 'super-admin'], JSON_UNESCAPED_UNICODE),
            'affected_modules' => json_encode(['Navigare', 'Stocuri și materiale', 'Centru de ajutor'], JSON_UNESCAPED_UNICODE),
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
            || $note->version !== '2026.07.30.2'
            || $note->title !== 'Navigare mai rapidă și cantități mai clare'
            || $note->status !== 'published'
        ) {
            throw new RuntimeException('Nota despre navigare și cantități a fost modificată; revenirea a fost oprită.');
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
