<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RELEASE_SLUG = '2026-07-30-praguri-vizibile-pentru-regulile-de-alertare';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            if (DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->exists()) {
                throw new RuntimeException('Nota despre afișarea pragurilor regulilor de alertare există deja.');
            }

            DB::table('release_notes')->insert([
                'slug' => self::RELEASE_SLUG,
                'version' => '2026.07.30.5',
                'title' => 'Praguri vizibile pentru regulile de alertare',
                'summary' => 'Starea și numărul de zile sunt afișate complet și aliniat pentru fiecare regulă de alertare.',
                'body_markdown' => <<<'MARKDOWN'
# Ce s-a îmbunătățit

- coloanele **Activă** și **Prag** sunt afișate separat și aliniat pentru fiecare regulă;
- numărul de zile rămâne vizibil și atunci când fereastra aplicației este mai îngustă;
- butonul de salvare rămâne asociat clar regulii modificate.

Funcționarea și prioritatea regulilor de alertare nu s-au schimbat.

# Ce trebuie să facă utilizatorul

Nu este necesară nicio configurare. Valorile existente sunt păstrate și pot fi modificate ca înainte.
MARKDOWN,
                'audience_roles' => json_encode(
                    ['super-admin', 'admin'],
                    JSON_UNESCAPED_UNICODE,
                ),
                'affected_modules' => json_encode(
                    ['Alerte', 'Reguli de alertare'],
                    JSON_UNESCAPED_UNICODE,
                ),
                'requires_action' => false,
                'status' => 'published',
                'released_at' => '2026-07-30',
                'published_at' => now(),
                'created_at' => now(),
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
            $note = DB::table('release_notes')
                ->where('slug', self::RELEASE_SLUG)
                ->lockForUpdate()
                ->first();

            if (! $note
                || $note->version !== '2026.07.30.5'
                || $note->title !== 'Praguri vizibile pentru regulile de alertare'
                || $note->status !== 'published'
            ) {
                throw new RuntimeException('Nota despre pragurile regulilor de alertare a fost modificată; revenirea a fost oprită.');
            }

            DB::table('release_notes')->where('id', $note->id)->delete();
        });
    }
};
