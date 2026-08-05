<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const ARTICLE_SLUG = 'administrarea-accesului';

    private const ARTICLE_TITLE = 'Administrarea accesului';

    private const ARTICLE_SUMMARY = 'Verificarea rolurilor, domeniilor, excepțiilor și avertismentelor pentru fiecare utilizator.';

    private const CHANGE_SUMMARY = 'Administrarea și explicarea accesului efectiv al utilizatorilor.';

    private const RELEASE_SLUG = '2026-08-05-administrarea-accesului';

    private const LEGACY_PERMISSIONS = [
        'inventory.view',
        'inventory.view-commercial',
        'inventory.manage',
        'reception-documents.upload',
        'reception-details.edit-all',
        'reception-details.edit-expiration',
        'accounting.edit-operations',
        'users.impersonate',
        'consumption-reports.correct',
        'suppliers.manage',
    ];

    private const ROLE_PERMISSIONS = [
        'super-admin' => [
            'access.view', 'users.view', 'users.manage', 'users.assign-roles', 'users.impersonate', 'locations.view', 'locations.manage', 'locations.assign-responsibles', 'catalog.view', 'catalog.manage', 'suppliers.view', 'suppliers.manage', 'inventory.view', 'inventory.view-commercial', 'inventory.manage', 'tracked-assets.browse', 'tracked-assets.view', 'tracked-assets.manage', 'projects.view', 'projects.manage', 'transfers.view', 'transfers.create', 'transfers.update', 'transfers.approve', 'transfers.receive', 'transfers.cancel', 'transfers.archive', 'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign', 'tasks.comment', 'tasks.transition', 'reception-intakes.view', 'reception-intakes.create', 'reception-intakes.cancel', 'receptions.view', 'receptions.create', 'reception-documents.upload', 'reception-details.edit-all', 'reception-details.edit-expiration', 'negotiated-orders.view', 'negotiated-orders.manage', 'consumption-reports.view', 'consumption-reports.create', 'consumption-reports.correct', 'custody.view', 'custody.initiate', 'custody.manage', 'reports.view', 'alerts.view', 'alerts.manage', 'qr.scan',
        ],
        'admin' => [
            'access.view', 'users.view', 'users.manage', 'users.assign-roles', 'users.impersonate', 'locations.view', 'locations.manage', 'locations.assign-responsibles', 'catalog.view', 'catalog.manage', 'suppliers.view', 'suppliers.manage', 'inventory.view', 'inventory.view-commercial', 'inventory.manage', 'tracked-assets.browse', 'tracked-assets.view', 'tracked-assets.manage', 'projects.view', 'projects.manage', 'transfers.view', 'transfers.create', 'transfers.update', 'transfers.approve', 'transfers.receive', 'transfers.cancel', 'transfers.archive', 'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign', 'tasks.comment', 'tasks.transition', 'reception-intakes.view', 'reception-intakes.create', 'reception-intakes.cancel', 'receptions.view', 'receptions.create', 'reception-documents.upload', 'reception-details.edit-all', 'reception-details.edit-expiration', 'negotiated-orders.view', 'negotiated-orders.manage', 'consumption-reports.view', 'consumption-reports.create', 'consumption-reports.correct', 'custody.view', 'custody.initiate', 'custody.manage', 'reports.view', 'alerts.view', 'alerts.manage', 'qr.scan',
        ],
        'dispecer' => [
            'locations.view', 'locations.manage', 'locations.assign-responsibles', 'catalog.view', 'catalog.manage', 'suppliers.view', 'suppliers.manage', 'inventory.view', 'inventory.view-commercial', 'inventory.manage', 'tracked-assets.browse', 'tracked-assets.view', 'tracked-assets.manage', 'projects.view', 'transfers.view', 'transfers.create', 'transfers.update', 'transfers.approve', 'transfers.receive', 'transfers.cancel', 'transfers.archive', 'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign', 'tasks.comment', 'tasks.transition', 'reception-intakes.view', 'reception-intakes.create', 'reception-intakes.cancel', 'receptions.view', 'receptions.create', 'reception-documents.upload', 'consumption-reports.view', 'consumption-reports.create', 'custody.view', 'custody.initiate', 'custody.manage', 'reports.view', 'alerts.view', 'qr.scan',
        ],
        'manager' => [
            'locations.view', 'catalog.view', 'suppliers.view', 'inventory.view', 'inventory.view-commercial', 'tracked-assets.browse', 'tracked-assets.view', 'projects.view', 'projects.manage', 'transfers.view', 'tasks.view', 'tasks.comment', 'reception-intakes.view', 'receptions.view', 'consumption-reports.view', 'custody.view', 'reports.view', 'alerts.view',
        ],
        'gestionar-baza' => [
            'locations.view', 'catalog.view', 'catalog.manage', 'suppliers.view', 'inventory.view', 'tracked-assets.browse', 'tracked-assets.view', 'projects.view', 'transfers.view', 'transfers.create', 'transfers.update', 'transfers.approve', 'transfers.receive', 'transfers.cancel', 'transfers.archive', 'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign', 'tasks.comment', 'tasks.transition', 'reception-intakes.view', 'reception-intakes.create', 'reception-intakes.cancel', 'receptions.view', 'receptions.create', 'reception-documents.upload', 'reception-details.edit-expiration', 'consumption-reports.view', 'consumption-reports.create', 'custody.view', 'custody.initiate', 'custody.manage', 'reports.view', 'alerts.view', 'qr.scan',
        ],
        'sef-santier' => [
            'locations.view', 'catalog.view', 'suppliers.view', 'inventory.view', 'tracked-assets.browse', 'tracked-assets.view', 'projects.view', 'transfers.view', 'transfers.create', 'transfers.update', 'transfers.approve', 'transfers.receive', 'transfers.cancel', 'transfers.archive', 'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign', 'tasks.comment', 'tasks.transition', 'reception-intakes.view', 'reception-intakes.create', 'reception-intakes.cancel', 'receptions.view', 'receptions.create', 'reception-documents.upload', 'consumption-reports.view', 'consumption-reports.create', 'custody.view', 'custody.initiate', 'custody.manage', 'reports.view', 'alerts.view', 'qr.scan',
        ],
        'sofer' => [
            'tracked-assets.view', 'transfers.view', 'tasks.view', 'tasks.respond', 'tasks.comment', 'custody.view', 'custody.initiate', 'custody.manage', 'qr.scan',
        ],
        'muncitor' => [
            'tracked-assets.view', 'reception-intakes.view', 'reception-intakes.create', 'reception-documents.upload', 'custody.view', 'custody.initiate', 'custody.manage', 'qr.scan',
        ],
        'contabil' => [
            'suppliers.view', 'suppliers.manage', 'inventory.view', 'inventory.view-commercial', 'tracked-assets.view', 'receptions.view', 'consumption-reports.view', 'reports.view', 'alerts.view',
        ],
        'user' => [],
    ];

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->seedAccessCatalog();
            $this->publishHelpArticle();
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
            $this->removeReleaseNote();
            $this->removeHelpArticle();

            DB::table('permissions')
                ->where('guard_name', 'web')
                ->whereIn('name', array_values(array_diff($this->permissions(), self::LEGACY_PERMISSIONS)))
                ->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function seedAccessCatalog(): void
    {
        $timestamp = now();

        foreach (array_keys(self::ROLE_PERMISSIONS) as $role) {
            DB::table('roles')->insertOrIgnore([
                'name' => $role,
                'guard_name' => 'web',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        foreach ($this->permissions() as $permission) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $permission,
                'guard_name' => 'web',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        $roleIds = DB::table('roles')->where('guard_name', 'web')->pluck('id', 'name');
        $permissionIds = DB::table('permissions')->where('guard_name', 'web')->pluck('id', 'name');

        foreach (self::ROLE_PERMISSIONS as $role => $permissions) {
            foreach ($permissions as $permission) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionIds[$permission],
                    'role_id' => $roleIds[$role],
                ]);
            }
        }
    }

    /** @return array<int, string> */
    private function permissions(): array
    {
        return collect(self::ROLE_PERMISSIONS)
            ->flatten()
            ->push('accounting.edit-operations')
            ->unique()
            ->values()
            ->all();
    }

    private function publishHelpArticle(): void
    {
        if (DB::table('help_articles')->where('slug', self::ARTICLE_SLUG)->exists()) {
            throw new RuntimeException('Articolul despre administrarea accesului există deja.');
        }

        $body = <<<'MARKDOWN'
# Administrarea accesului

Administratorii găsesc în **Setări → Administrare acces** o vedere centralizată a conturilor și a drepturilor lor efective.

- lista utilizatorilor arată rolurile, locațiile administrate, numărul capabilităților permise și avertismentele de configurare;
- **Explică accesul** deschide fișa unei persoane și arată, pentru fiecare capabilitate, dacă este permisă, rolul din care provine și domeniul în care se aplică;
- matricea rolurilor descrie accesul standard al fiecărui rol;
- excepțiile atribuite direct sunt semnalate separat și nu modifică matricea standard;
- istoricul fișei păstrează modificările de rol, stare și responsabilități de locație.

Un drept poate fi **global**, limitat la **locațiile administrate**, la **înregistrările alocate**, la **datele proprii** sau la o consultare punctuală. Chiar dacă dreptul este permis, aplicația verifică în continuare condițiile operaționale, precum starea transferului, destinația, alocarea sau persoanele implicate.

Rolurile **Șef de șantier** și **Gestionar de bază** trebuie asociate cu cel puțin o locație. Dacă un utilizator devine inactiv sau pierde ultimul rol eligibil pentru administrarea locațiilor, responsabilitățile sale active sunt retrase automat. La selectarea responsabililor unei locații, aplicația acceptă numai utilizatori activi cu rol eligibil.

Rolurile se modifică în continuare din **Setări → Utilizatori**. Centrul de acces este destinat verificării și explicării rezultatului înainte și după o schimbare.
MARKDOWN;
        $timestamp = now();
        $articleId = DB::table('help_articles')->insertGetId([
            'slug' => self::ARTICLE_SLUG,
            'title' => self::ARTICLE_TITLE,
            'summary' => self::ARTICLE_SUMMARY,
            'body_markdown' => $body,
            'section' => 'roles',
            'audience_roles' => json_encode(['admin', 'super-admin'], JSON_UNESCAPED_UNICODE),
            'sort_order' => 35,
            'status' => 'published',
            'current_revision' => 1,
            'created_by' => null,
            'updated_by' => null,
            'published_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        DB::table('help_article_revisions')->insert([
            'help_article_id' => $articleId,
            'revision' => 1,
            'title' => self::ARTICLE_TITLE,
            'summary' => self::ARTICLE_SUMMARY,
            'body_markdown' => $body,
            'change_summary' => self::CHANGE_SUMMARY,
            'source' => 'system',
            'created_by' => null,
            'published_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    private function publishReleaseNote(): void
    {
        if (DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->exists()) {
            throw new RuntimeException('Nota despre administrarea accesului există deja.');
        }

        $timestamp = now();
        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.08.05.1',
            'title' => 'Administrarea și explicarea accesului',
            'summary' => 'Administratorii pot vedea drepturile efective ale fiecărui utilizator, domeniul lor și posibilele probleme de configurare.',
            'body_markdown' => <<<'MARKDOWN'
# Ce este nou

- în **Setări → Administrare acces** există o listă centralizată a utilizatorilor, rolurilor și domeniilor de acces;
- fiecare utilizator are o fișă care explică drepturile permise și refuzate, sursa accesului și condițiile aplicabile;
- matricea rolurilor arată accesul standard pe module;
- configurațiile neobișnuite, rolurile locale fără locație și excepțiile directe sunt semnalate administratorilor;
- schimbările de rol, stare și responsabilitate de locație sunt auditate;
- responsabilitățile de locație rămase fără un rol eligibil sunt retrase automat;
- selecția responsabililor unei locații este verificată și pe server, nu numai în formular.

# Ce trebuie să facă administratorul

Deschide **Administrare acces**, verifică avertismentele și folosește **Explică accesul** pentru conturile care necesită clarificare. Pentru modificarea rolurilor, continuă să folosești pagina **Utilizatori**.
MARKDOWN,
            'audience_roles' => json_encode(['admin', 'super-admin'], JSON_UNESCAPED_UNICODE),
            'affected_modules' => json_encode(['Administrare acces', 'Utilizatori', 'Locații', 'Securitate', 'Centru de ajutor'], JSON_UNESCAPED_UNICODE),
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
        if (! $note || $note->version !== '2026.08.05.1' || $note->title !== 'Administrarea și explicarea accesului' || $note->status !== 'published') {
            throw new RuntimeException('Nota despre administrarea accesului a fost modificată; revenirea a fost oprită.');
        }

        DB::table('release_notes')->where('id', $note->id)->delete();
    }

    private function removeHelpArticle(): void
    {
        $article = DB::table('help_articles')
            ->where('slug', self::ARTICLE_SLUG)
            ->lockForUpdate()
            ->first();
        if (! $article
            || $article->title !== self::ARTICLE_TITLE
            || $article->summary !== self::ARTICLE_SUMMARY
            || (int) $article->current_revision !== 1
            || $article->status !== 'published') {
            throw new RuntimeException('Articolul despre administrarea accesului a fost modificat; revenirea a fost oprită.');
        }

        $revision = DB::table('help_article_revisions')
            ->where('help_article_id', $article->id)
            ->where('revision', 1)
            ->first();
        if (! $revision
            || $revision->source !== 'system'
            || $revision->change_summary !== self::CHANGE_SUMMARY
            || $revision->body_markdown !== $article->body_markdown) {
            throw new RuntimeException('Istoricul articolului despre administrarea accesului a fost modificat; revenirea a fost oprită.');
        }

        DB::table('help_article_revisions')->where('id', $revision->id)->delete();
        DB::table('help_articles')->where('id', $article->id)->delete();
    }
};
