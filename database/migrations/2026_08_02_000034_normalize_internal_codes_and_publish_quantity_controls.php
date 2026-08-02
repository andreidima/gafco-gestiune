<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const RELEASE_SLUG = '2026-08-02-coduri-si-cantitati-consecvente';
    private const CHANGE_SUMMARY = 'Coduri interne cu majuscule și controale rapide pentru cantități.';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->normalizeCodes();
            $article = $this->lockedArticle(20);
            $body = rtrim((string) $article->body_markdown).<<<'MARKDOWN'


## Coduri interne și ajustarea cantităților

Codurile interne pentru locații, utilizatori, materiale, proiecte și echipamente sunt transformate automat în majuscule. Denumirile, descrierile, seriile și celelalte texte rămân exact cum sunt introduse.

În formularele pentru materiale, cantitatea poate fi scrisă direct, inclusiv cu zecimale, sau ajustată rapid cu butoanele **−1** și **+1**. Butoanele păstrează partea zecimală și respectă cantitatea minimă și stocul disponibil.
MARKDOWN;
            $this->insertRevision($article, 21, $body);
            $this->publishReleaseNote();
        });
    }

    public function down(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $note = DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->lockForUpdate()->first();
            if (! $note || $note->version !== '2026.08.02.1' || $note->status !== 'published') {
                throw new RuntimeException('Nota despre coduri și cantități a fost modificată; revenirea a fost oprită.');
            }
            DB::table('release_notes')->where('id', $note->id)->delete();
            $article = $this->lockedArticle(21);
            $revision = DB::table('help_article_revisions')
                ->where('help_article_id', $article->id)->where('revision', 21)->first();
            if (! $revision || $revision->source !== 'system' || $revision->change_summary !== self::CHANGE_SUMMARY) {
                throw new RuntimeException('Revizia despre coduri și cantități a fost modificată; revenirea a fost oprită.');
            }
            $previous = DB::table('help_article_revisions')
                ->where('help_article_id', $article->id)->where('revision', 20)->first();
            if (! $previous) {
                throw new RuntimeException('Revizia anterioară a articolului nu există.');
            }
            DB::table('help_article_revisions')->where('id', $revision->id)->delete();
            DB::table('help_articles')->where('id', $article->id)->update([
                'title' => $previous->title, 'summary' => $previous->summary,
                'body_markdown' => $previous->body_markdown, 'current_revision' => 20,
                'updated_by' => $previous->created_by, 'published_at' => $previous->published_at,
                'updated_at' => now(),
            ]);
        });
    }

    private function normalizeCodes(): void
    {
        foreach ([
            ['locations', 'code', false], ['users', 'login_code', true],
            ['catalog_items', 'sku', true], ['tracked_assets', 'asset_code', false],
            ['projects', 'code', false],
        ] as [$table, $column, $nullable]) {
            $rows = DB::table($table)->select('id', $column)->orderBy('id')->get();
            $normalized = $rows->map(fn ($row) => [
                'id' => $row->id,
                'value' => $row->{$column} === null && $nullable
                    ? null : Str::upper(trim((string) $row->{$column})),
            ]);
            $collisions = $normalized->whereNotNull('value')->groupBy('value')->filter(fn ($group) => $group->count() > 1);
            if ($collisions->isNotEmpty()) {
                throw new RuntimeException("Normalizarea {$table}.{$column} ar crea coduri duplicate: ".$collisions->keys()->join(', '));
            }
            foreach ($normalized as $row) {
                if ($row['value'] === '' && ! $nullable) {
                    throw new RuntimeException("{$table}.{$column} conține un cod gol.");
                }
                DB::table($table)->where('id', $row['id'])->update([$column => $row['value']]);
            }
        }
    }

    private function lockedArticle(int $revision): object
    {
        $article = DB::table('help_articles')->where('slug', 'pagini-si-operatiuni')->lockForUpdate()->first();
        if (! $article || (int) $article->current_revision !== $revision) {
            $actual = $article ? (int) $article->current_revision : 'inexistent';
            throw new RuntimeException("Articolul pagini-si-operatiuni are revizia {$actual}; era așteptată revizia {$revision}.");
        }
        return $article;
    }

    private function insertRevision(object $article, int $revision, string $body): void
    {
        $timestamp = now();
        DB::table('help_article_revisions')->insert([
            'help_article_id' => $article->id, 'revision' => $revision,
            'title' => $article->title, 'summary' => $article->summary,
            'body_markdown' => $body, 'change_summary' => self::CHANGE_SUMMARY,
            'source' => 'system', 'created_by' => null, 'published_at' => $timestamp,
            'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
        DB::table('help_articles')->where('id', $article->id)->update([
            'body_markdown' => $body, 'current_revision' => $revision,
            'updated_by' => null, 'published_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
    }

    private function publishReleaseNote(): void
    {
        if (DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->exists()) {
            throw new RuntimeException('Nota despre coduri și cantități există deja.');
        }
        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG, 'version' => '2026.08.02.1',
            'title' => 'Coduri și cantități mai ușor de folosit',
            'summary' => 'Codurile interne folosesc majuscule, iar cantitățile pot fi ajustate rapid fără pierderea zecimalelor.',
            'body_markdown' => "# Ce s-a schimbat\n\n- codurile interne sunt transformate automat în majuscule la introducere și salvare;\n- denumirile și celelalte texte nu sunt modificate;\n- cantitățile întregi nu afișează zerouri zecimale inutile;\n- butoanele **−1** și **+1** ajustează rapid cantitatea și păstrează partea zecimală.\n\n# Ce trebuie să faci\n\nNu este necesară nicio configurare. Poți continua să scrii manual orice cantitate zecimală.",
            'audience_roles' => json_encode(['super-admin', 'admin', 'dispecer', 'manager', 'sef-santier', 'gestionar-baza', 'sofer', 'muncitor', 'contabil'], JSON_UNESCAPED_UNICODE),
            'affected_modules' => json_encode(['Locații', 'Utilizatori', 'Materiale', 'Echipamente', 'Stocuri'], JSON_UNESCAPED_UNICODE),
            'requires_action' => false, 'status' => 'published', 'released_at' => '2026-08-02',
            'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
};
