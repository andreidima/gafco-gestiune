<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const ARTICLE_SLUG = 'administrarea-accesului';

    private const EXPECTED_ARTICLE_REVISION = 1;

    private const CHANGE_SUMMARY = 'Administrarea controlată a rolurilor și a excepțiilor individuale.';

    private const RELEASE_SLUG = '2026-08-05-roluri-si-exceptii-de-acces';

    private const PERMISSIONS = ['roles.manage', 'permissions.assign-direct'];

    public function up(): void
    {
        Schema::create('access_role_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->unique()->constrained('roles')->cascadeOnDelete();
            $table->string('label', 120);
            $table->text('description');
            $table->string('workspace', 120);
            $table->boolean('requires_locations')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('access_permission_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'permission_id']);
        });

        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->seedProtectedPermissions();
            $this->backfillExistingExceptions();
            $this->reviseHelpArticle();
            $this->publishReleaseNote();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! DB::connection()->pretending()) {
            DB::transaction(function (): void {
                $this->removeReleaseNote();
                $this->restoreHelpArticle();
            });
        }

        Schema::dropIfExists('access_permission_exceptions');
        Schema::dropIfExists('access_role_profiles');

        if (! DB::connection()->pretending()) {
            DB::table('permissions')->where('guard_name', 'web')->whereIn('name', self::PERMISSIONS)->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    private function seedProtectedPermissions(): void
    {
        $timestamp = now();
        foreach (self::PERMISSIONS as $permission) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $permission,
                'guard_name' => 'web',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        $roleId = DB::table('roles')->where('name', 'super-admin')->where('guard_name', 'web')->value('id');
        $permissionIds = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->pluck('id');
        foreach ($permissionIds as $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    private function backfillExistingExceptions(): void
    {
        $timestamp = now();
        $rows = DB::table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_type', User::class)
            ->where('permissions.guard_name', 'web')
            ->get(['model_has_permissions.model_id', 'model_has_permissions.permission_id']);

        foreach ($rows as $row) {
            DB::table('access_permission_exceptions')->insertOrIgnore([
                'user_id' => $row->model_id,
                'permission_id' => $row->permission_id,
                'reason' => 'Excepție existentă înainte de introducerea justificărilor obligatorii.',
                'granted_by' => null,
                'updated_by' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }

    private function reviseHelpArticle(): void
    {
        $article = $this->lockedArticle(self::EXPECTED_ARTICLE_REVISION);
        $body = rtrim((string) $article->body_markdown)."\n".<<<'MARKDOWN'

## Administrarea rolurilor

Contul protejat poate deschide **Roluri și drepturi** din centrul de acces. Rolurile standard pot primi sau pierde drepturi, dar nu pot fi redenumite ori șterse. Rolul **Super administrator** și drepturile rezervate rămân blocate pentru protejarea administrării aplicației.

Rolurile personalizate se creează separat, cu o denumire clară, o descriere și spațiul de lucru în care vor fi folosite. Un rol personalizat poate fi șters numai dacă nu este atribuit niciunui utilizator.

Înainte de salvarea drepturilor unui rol, aplicația arată exact ce se adaugă, ce se retrage și câți utilizatori sunt afectați. Dacă alt administrator modifică între timp același rol, salvarea este oprită și previzualizarea trebuie refăcută.

## Excepțiile individuale

O excepție individuală acordă unui utilizator un drept suplimentar fără modificarea rolurilor sale. Excepțiile se folosesc numai pentru situații punctuale și trebuie să aibă o justificare.

În fișa de acces a utilizatorului, contul protejat poate selecta drepturile permise pentru excepții, poate vedea modificările înainte de aplicare și poate retrage excepțiile existente. Drepturile de administrare și cele rezervate contului protejat nu pot fi delegate prin excepție.

Fișa utilizatorului arată separat excepțiile, justificarea lor și administratorul care le-a acordat. Acordarea și retragerea sunt înregistrate în istoricul accesului.
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
            throw new RuntimeException('Nota despre roluri și excepții există deja.');
        }

        $timestamp = now();
        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.08.05.2',
            'title' => 'Roluri configurabile și excepții de acces justificate',
            'summary' => 'Contul protejat poate configura drepturile rolurilor și poate acorda controlat excepții individuale.',
            'body_markdown' => <<<'MARKDOWN'
# Ce este nou

- contul protejat poate crea roluri personalizate și poate configura drepturile rolurilor;
- rolurile standard sunt protejate împotriva redenumirii și ștergerii;
- drepturile rezervate și rolul Super administrator nu pot fi delegate sau slăbite;
- fiecare modificare este previzualizată înainte de aplicare;
- schimbările concurente sunt detectate, iar salvarea este oprită până la refacerea previzualizării;
- excepțiile individuale necesită o justificare și sunt afișate separat în fișa utilizatorului;
- toate acordările și retragerile sunt păstrate în istoricul accesului.

# Ce trebuie să facă administratorii

Administratorii obișnuiți pot consulta în continuare accesul efectiv. Configurarea rolurilor și excepțiilor este rezervată contului protejat. Folosiți excepțiile numai pentru nevoi individuale care nu justifică modificarea unui rol pentru toți utilizatorii săi.
MARKDOWN,
            'audience_roles' => json_encode(['admin', 'super-admin'], JSON_UNESCAPED_UNICODE),
            'affected_modules' => json_encode(['Administrare acces', 'Roluri', 'Utilizatori', 'Securitate', 'Centru de ajutor'], JSON_UNESCAPED_UNICODE),
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
        if (! $note || $note->version !== '2026.08.05.2' || $note->title !== 'Roluri configurabile și excepții de acces justificate' || $note->status !== 'published') {
            throw new RuntimeException('Nota despre roluri și excepții a fost modificată; revenirea a fost oprită.');
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
        if (! $current || $current->source !== 'system' || $current->change_summary !== self::CHANGE_SUMMARY || $current->body_markdown !== $article->body_markdown) {
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
