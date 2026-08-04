<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RELEASE_SLUG = '2026-08-04-controale-cantitate-clare-in-transferuri';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            if (DB::table('release_notes')->where('slug', self::RELEASE_SLUG)->exists()) {
                throw new RuntimeException('Nota despre afișarea cantităților în transferuri există deja.');
            }

            DB::table('release_notes')->insert([
                'slug' => self::RELEASE_SLUG,
                'version' => '2026.08.04.2',
                'title' => 'Controale de cantitate clare în transferuri',
                'summary' => 'Butoanele de ajustare a cantității și ștergerea poziției sunt afișate separat în formularele de transfer și retur.',
                'body_markdown' => <<<'MARKDOWN'
# Ce s-a îmbunătățit

- controlul de cantitate are suficient spațiu pentru butoanele **−1** și **+1**, precum și pentru valoarea introdusă;
- butonul de ștergere a poziției rămâne separat și nu mai acoperă ajustarea cantității;
- aranjarea se adaptează atât ecranelor mari, cât și ferestrelor mai înguste.

Calculul cantității și regulile transferului nu s-au schimbat.

# Ce trebuie să facă utilizatorul

Nu este necesară nicio configurare. Cantitatea poate fi introdusă sau ajustată ca înainte.
MARKDOWN,
                'audience_roles' => json_encode(
                    ['super-admin', 'admin', 'dispecer', 'manager', 'sef-santier', 'gestionar-baza'],
                    JSON_UNESCAPED_UNICODE,
                ),
                'affected_modules' => json_encode(['Transferuri'], JSON_UNESCAPED_UNICODE),
                'requires_action' => false,
                'status' => 'published',
                'released_at' => '2026-08-04',
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
                || $note->version !== '2026.08.04.2'
                || $note->title !== 'Controale de cantitate clare în transferuri'
                || $note->status !== 'published'
            ) {
                throw new RuntimeException('Nota despre afișarea cantităților în transferuri a fost modificată; revenirea a fost oprită.');
            }

            DB::table('release_notes')->where('id', $note->id)->delete();
        });
    }
};
