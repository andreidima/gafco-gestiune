<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ARTICLE_SLUG = 'pagini-si-operatiuni';

    private const RELEASE_SLUG = '2026-07-29-afisare-roluri-si-liste';

    private const CHANGE_SUMMARY = 'Selectarea rolurilor și informațiile de paginare în limba română.';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $article = $this->lockedArticle();
            $currentRevision = $this->revision($article->id, 3);
            $previousRevision = $this->revision($article->id, 2);

            if ((int) $article->current_revision !== 3) {
                throw new RuntimeException(
                    "Articolul {$article->slug} are revizia {$article->current_revision}; era așteptată revizia 3. Eliminarea a fost oprită pentru a proteja modificările editoriale."
                );
            }

            if (
                ! $currentRevision
                || $currentRevision->source !== 'system'
                || $currentRevision->change_summary !== self::CHANGE_SUMMARY
                || $article->body_markdown !== $currentRevision->body_markdown
            ) {
                throw new RuntimeException(
                    'Revizia introdusă pentru corecțiile minore nu mai corespunde conținutului generat de sistem.'
                );
            }

            if (! $previousRevision) {
                throw new RuntimeException('Revizia 2 a articolului Pagini și operațiuni nu există.');
            }

            $releaseNote = DB::table('release_notes')
                ->where('slug', self::RELEASE_SLUG)
                ->lockForUpdate()
                ->first();

            if ($releaseNote && (
                $releaseNote->version !== '2026.07.29'
                || $releaseNote->title !== 'Afișare completă a rolurilor și a listelor'
                || $releaseNote->status !== 'published'
            )) {
                throw new RuntimeException(
                    'Nota de versiune pentru corecțiile minore a fost modificată; eliminarea a fost oprită.'
                );
            }

            DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->delete();

            DB::table('help_article_revisions')
                ->where('help_article_id', $article->id)
                ->where('revision', 3)
                ->delete();

            DB::table('help_articles')->where('id', $article->id)->update([
                'title' => $previousRevision->title,
                'summary' => $previousRevision->summary,
                'body_markdown' => $previousRevision->body_markdown,
                'current_revision' => 2,
                'updated_by' => null,
                'published_at' => $previousRevision->published_at,
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $article = $this->lockedArticle();

            if ((int) $article->current_revision !== 2) {
                throw new RuntimeException(
                    "Articolul {$article->slug} are revizia {$article->current_revision}; era așteptată revizia 2. Restaurarea a fost oprită pentru a proteja modificările editoriale."
                );
            }

            if ($this->revision($article->id, 3)) {
                throw new RuntimeException('Revizia 3 există deja și nu poate fi restaurată automat.');
            }

            if (DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->exists()) {
                throw new RuntimeException('Nota de versiune există deja și nu poate fi restaurată automat.');
            }

            $body = $this->bodyWithMinorCorrections((string) $article->body_markdown);

            DB::table('help_article_revisions')->insert([
                'help_article_id' => $article->id,
                'revision' => 3,
                'title' => $article->title,
                'summary' => $article->summary,
                'body_markdown' => $body,
                'change_summary' => self::CHANGE_SUMMARY,
                'source' => 'system',
                'created_by' => null,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('help_articles')->where('id', $article->id)->update([
                'body_markdown' => $body,
                'current_revision' => 3,
                'updated_by' => null,
                'published_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('release_notes')->insert([
                'slug' => self::RELEASE_SLUG,
                'version' => '2026.07.29',
                'title' => 'Afișare completă a rolurilor și a listelor',
                'summary' => 'Selectarea rolurilor este mai clară, iar informațiile de paginare sunt afișate în limba română.',
                'body_markdown' => <<<'MARKDOWN'
# Ce s-a îmbunătățit

- lista de roluri din formularul unui utilizator se poate deschide complet, fără să fie limitată de marginea formularului;
- rolurile sunt afișate cu denumiri clare în limba română;
- intervalul și numărul total de rezultate din listele împărțite în pagini sunt afișate în limba română;
- butoanele pentru pagina anterioară și pagina următoare sunt, de asemenea, în limba română.

Nu este necesară nicio acțiune din partea utilizatorilor.
MARKDOWN,
                'audience_roles' => json_encode(['all'], JSON_UNESCAPED_UNICODE),
                'affected_modules' => json_encode(['Utilizatori', 'Liste', 'Navigare'], JSON_UNESCAPED_UNICODE),
                'requires_action' => false,
                'status' => 'published',
                'released_at' => '2026-07-29',
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    private function lockedArticle(): object
    {
        $article = DB::table('help_articles')
            ->where('slug', self::ARTICLE_SLUG)
            ->lockForUpdate()
            ->first();

        if (! $article) {
            throw new RuntimeException('Articolul de ajutor Pagini și operațiuni nu există.');
        }

        return $article;
    }

    private function revision(int $articleId, int $revision): ?object
    {
        return DB::table('help_article_revisions')
            ->where('help_article_id', $articleId)
            ->where('revision', $revision)
            ->first();
    }

    private function bodyWithMinorCorrections(string $body): string
    {
        return rtrim($body)."\n".<<<'MARKDOWN'

## Utilizatori și liste

Administratorii și super-administratorii pot modifica rolurile din pagina **Utilizatori**. Câmpul **Roluri** permite selectarea mai multor responsabilități și afișează întreaga listă de opțiuni.

În paginile cu multe înregistrări, sub listă este afișat intervalul curent și numărul total de rezultate. Butoanele **Anterior** și **Următor** permit trecerea între pagini.
MARKDOWN;
    }
};
