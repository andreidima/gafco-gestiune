<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const ARTICLE_SLUG = 'ghiduri-dupa-rol';

    private const EXPECTED_ARTICLE_REVISION = 2;

    private const CHANGE_SUMMARY = 'Schimbarea temporară și auditată a utilizatorului de către administratori.';

    private const PERMISSION = 'users.impersonate';

    private const RELEASE_SLUG = '2026-07-29-schimbare-utilizator';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $permissionId = $this->ensurePermissionAndRoles();

            $roleIds = DB::table('roles')
                ->where('guard_name', 'web')
                ->whereIn('name', ['admin', 'super-admin'])
                ->pluck('id');

            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }

            $this->reviseHelpArticle();
            $this->publishReleaseNote();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->removeReleaseContent();

            DB::table('permissions')
                ->where('name', self::PERMISSION)
                ->where('guard_name', 'web')
                ->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function ensurePermissionAndRoles(): int
    {
        $timestamp = now();

        DB::table('permissions')->insertOrIgnore([
            'name' => self::PERMISSION,
            'guard_name' => 'web',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        foreach (['admin', 'super-admin'] as $role) {
            DB::table('roles')->insertOrIgnore([
                'name' => $role,
                'guard_name' => 'web',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        return (int) DB::table('permissions')
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->value('id');
    }

    private function reviseHelpArticle(): void
    {
        $article = DB::table('help_articles')
            ->where('slug', self::ARTICLE_SLUG)
            ->lockForUpdate()
            ->first();

        if (! $article) {
            throw new RuntimeException('Articolul Ghiduri după rol nu există.');
        }

        if ((int) $article->current_revision !== self::EXPECTED_ARTICLE_REVISION) {
            throw new RuntimeException(
                "Articolul {$article->slug} are revizia {$article->current_revision}; era așteptată revizia ".self::EXPECTED_ARTICLE_REVISION.'. Publicarea a fost oprită pentru a proteja modificările editoriale.'
            );
        }

        $nextRevision = self::EXPECTED_ARTICLE_REVISION + 1;
        $body = rtrim((string) $article->body_markdown)."\n".<<<'MARKDOWN'

## Schimbarea utilizatorului

Administratorii și super-administratorii pot folosi butonul **Schimbă utilizatorul** din meniul principal pentru a intra temporar în contul unui utilizator activ. Conturile administrative și conturile inactive nu pot fi selectate.

În timpul schimbării:

- aplicația afișează paginile și drepturile utilizatorului ales;
- o bandă galbenă arată permanent utilizatorul curent și administratorul care a pornit schimbarea;
- butonul **Schimbă** permite trecerea directă la alt utilizator eligibil;
- butonul **Revino la contul meu** încheie schimbarea;
- acțiunile efectuate sunt reale și sunt înregistrate împreună cu identitatea administratorului.

Zonele administrative sensibile nu sunt disponibile în timpul schimbării utilizatorului. Pentru accesarea lor, administratorul trebuie să revină mai întâi la propriul cont.
MARKDOWN;

        DB::table('help_article_revisions')->insert([
            'help_article_id' => $article->id,
            'revision' => $nextRevision,
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
            'current_revision' => $nextRevision,
            'updated_by' => null,
            'published_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function publishReleaseNote(): void
    {
        if (DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->exists()) {
            throw new RuntimeException('Nota de versiune pentru schimbarea utilizatorului există deja.');
        }

        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.07.29.1',
            'title' => 'Schimbare rapidă între utilizatori',
            'summary' => 'Administratorii pot verifica aplicația din perspectiva unui utilizator și pot trece rapid de la un cont la altul.',
            'body_markdown' => <<<'MARKDOWN'
# Ce este nou

- administratorii și super-administratorii au un buton nou pentru schimbarea utilizatorului;
- utilizatorii activi pot fi găsiți rapid după nume, cod de conectare sau rol;
- trecerea la alt utilizator se poate face din orice pagină;
- o bandă galbenă arată permanent când este activă schimbarea și oferă revenirea imediată la contul administratorului;
- conturile administrative, conturile inactive și zonele administrative sensibile sunt protejate;
- acțiunile efectuate în timpul schimbării sunt înregistrate cu utilizatorul ales și administratorul real.

Ceilalți utilizatori nu trebuie să facă nimic.
MARKDOWN,
            'audience_roles' => json_encode(['admin', 'super-admin'], JSON_UNESCAPED_UNICODE),
            'affected_modules' => json_encode(['Utilizatori', 'Navigare', 'Securitate'], JSON_UNESCAPED_UNICODE),
            'requires_action' => false,
            'status' => 'published',
            'released_at' => '2026-07-29',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function removeReleaseContent(): void
    {
        $article = DB::table('help_articles')
            ->where('slug', self::ARTICLE_SLUG)
            ->lockForUpdate()
            ->first();

        $currentRevision = $article
            ? DB::table('help_article_revisions')
                ->where('help_article_id', $article->id)
                ->where('revision', self::EXPECTED_ARTICLE_REVISION + 1)
                ->first()
            : null;

        if (
            ! $article
            || (int) $article->current_revision !== self::EXPECTED_ARTICLE_REVISION + 1
            || ! $currentRevision
            || $currentRevision->source !== 'system'
            || $currentRevision->change_summary !== self::CHANGE_SUMMARY
            || $article->body_markdown !== $currentRevision->body_markdown
        ) {
            throw new RuntimeException(
                'Articolul despre schimbarea utilizatorului a fost modificat; revenirea a fost oprită pentru a proteja conținutul editorial.'
            );
        }

        $previousRevision = DB::table('help_article_revisions')
            ->where('help_article_id', $article->id)
            ->where('revision', self::EXPECTED_ARTICLE_REVISION)
            ->first();

        if (! $previousRevision) {
            throw new RuntimeException('Revizia anterioară a articolului Ghiduri după rol nu există.');
        }

        $releaseNote = DB::table('release_notes')
            ->where('slug', self::RELEASE_SLUG)
            ->lockForUpdate()
            ->first();

        if ($releaseNote && (
            $releaseNote->version !== '2026.07.29.1'
            || $releaseNote->title !== 'Schimbare rapidă între utilizatori'
            || $releaseNote->status !== 'published'
        )) {
            throw new RuntimeException(
                'Nota de versiune despre schimbarea utilizatorului a fost modificată; revenirea a fost oprită.'
            );
        }

        DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->delete();
        DB::table('help_article_revisions')
            ->where('help_article_id', $article->id)
            ->where('revision', self::EXPECTED_ARTICLE_REVISION + 1)
            ->delete();
        DB::table('help_articles')->where('id', $article->id)->update([
            'title' => $previousRevision->title,
            'summary' => $previousRevision->summary,
            'body_markdown' => $previousRevision->body_markdown,
            'current_revision' => self::EXPECTED_ARTICLE_REVISION,
            'updated_by' => null,
            'published_at' => $previousRevision->published_at,
            'updated_at' => now(),
        ]);
    }
};
