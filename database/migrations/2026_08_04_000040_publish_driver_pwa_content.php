<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ARTICLE_SLUG = 'aplicatia-pentru-soferi';

    private const RELEASE_SLUG = '2026-08-04-aplicatie-instalabila-pentru-soferi';

    private const ARTICLE_TITLE = 'Aplicația pentru șoferi';

    private const ARTICLE_SUMMARY = 'Instalarea pe telefon, navigarea rapidă, traseele, notificările și lucrul în siguranță când conexiunea lipsește.';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->publishArticle();
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
            $this->removeArticle();
        });
    }

    private function publishArticle(): void
    {
        if (DB::table('help_articles')->where('slug', self::ARTICLE_SLUG)->exists()) {
            throw new RuntimeException('Articolul despre aplicația pentru șoferi există deja.');
        }

        $timestamp = now();
        $body = <<<'MARKDOWN'
# Instalarea pe telefon

Șoferul poate instala **GAFCO Șofer** direct din browser. După instalare, aplicația are pictogramă proprie și se deschide pe tot ecranul, fără bara browserului.

- pe Android, apasă **Instalează** când apare recomandarea din aplicație;
- pe iPhone, deschide aplicația în Safari și folosește **Partajare → Adaugă la ecranul principal**.

# Navigarea rapidă

În partea de jos rămân permanent disponibile scurtăturile pentru **Sarcini**, **Transferuri**, **QR**, **Custodie** și **Notificări**. Sigla GAFCO din partea de sus revine la pagina principală.

Prima sarcină din pagina principală arată explicit următoarea acțiune: răspuns la alocare, estimare și pornire sau continuarea sarcinii.

# Traseul

În sarcini, butoanele **Traseu** și **Waze** deschid navigarea către destinație. Adresa locației este folosită atunci când este completată; în lipsa ei, aplicația caută după numele locației. Verifică destinația afișată înainte de pornirea navigării.

# Notificările

Șoferul poate activa notificările separat pe fiecare telefon. Sunt trimise alocările și schimbările importante din fluxul de lucru. Activarea se face numai după acordul șoferului și poate fi revocată oricând de pe același telefon.

Dacă schimbi telefonul, activează notificările pe noul dispozitiv și dezactivează-le pe cel vechi.

# Lucrul fără conexiune

Când telefonul nu are internet, aplicația afișează clar starea **Offline** și blochează confirmările. Datele operaționale și acțiunile nu sunt salvate într-o coadă offline, pentru a evita acceptarea, pornirea sau finalizarea unei sarcini care între timp a fost schimbată sau realocată.

Verifică indicatorul **Online** înainte de o confirmare importantă. Dacă apare **Offline**, așteaptă revenirea conexiunii și reîncarcă pagina.
MARKDOWN;

        $articleId = DB::table('help_articles')->insertGetId([
            'slug' => self::ARTICLE_SLUG,
            'title' => self::ARTICLE_TITLE,
            'summary' => self::ARTICLE_SUMMARY,
            'body_markdown' => $body,
            'section' => 'roles',
            'audience_roles' => json_encode(
                ['sofer', 'dispecer', 'manager', 'admin', 'super-admin'],
                JSON_UNESCAPED_UNICODE,
            ),
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
            'change_summary' => 'Publicarea ghidului pentru aplicația instalabilă a șoferilor.',
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
            throw new RuntimeException('Nota despre aplicația instalabilă pentru șoferi există deja.');
        }

        $timestamp = now();
        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.08.04.5',
            'title' => 'Aplicație instalabilă pentru șoferi',
            'summary' => 'Șoferii pot instala aplicația pe telefon, pot folosi navigarea simplificată și pot activa notificările pentru sarcini.',
            'body_markdown' => <<<'MARKDOWN'
# Ce s-a schimbat

- aplicația **GAFCO Șofer** poate fi instalată direct pe ecranul principal al telefonului;
- aplicația instalată se deschide pe tot ecranul și are pictogramă proprie;
- șoferii au o bară simplă de navigare pentru sarcini, transferuri, QR, custodie și notificări;
- prima sarcină arată clar următoarea acțiune necesară;
- traseul poate fi deschis direct în Google Maps sau Waze;
- notificările pentru alocări și schimbări importante pot fi activate separat pe fiecare telefon;
- lipsa conexiunii este semnalată clar, iar confirmările sunt blocate până revine internetul.

# Ce trebuie să facă șoferul

Deschide aplicația din browser și apasă **Instalează**. Pe iPhone, folosește **Partajare → Adaugă la ecranul principal**. După instalare, deschide pagina principală și apasă **Activează** la notificări, dacă dorești să primești alocările direct pe telefon.

Înainte de acceptarea, pornirea sau finalizarea unei sarcini, verifică dacă aplicația afișează starea **Online**.
MARKDOWN,
            'audience_roles' => json_encode(
                ['sofer', 'dispecer', 'manager', 'admin', 'super-admin'],
                JSON_UNESCAPED_UNICODE,
            ),
            'affected_modules' => json_encode(
                ['Aplicație șoferi', 'Sarcini șoferi', 'Notificări', 'Centru de ajutor'],
                JSON_UNESCAPED_UNICODE,
            ),
            'requires_action' => true,
            'status' => 'published',
            'released_at' => '2026-08-04',
            'published_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    private function removeReleaseNote(): void
    {
        $note = DB::table('release_notes')
            ->where('slug', self::RELEASE_SLUG)
            ->lockForUpdate()
            ->first();

        if (! $note
            || $note->version !== '2026.08.04.5'
            || $note->title !== 'Aplicație instalabilă pentru șoferi'
            || $note->status !== 'published'
        ) {
            throw new RuntimeException('Nota despre aplicația pentru șoferi a fost modificată; revenirea a fost oprită.');
        }

        DB::table('release_notes')->where('id', $note->id)->delete();
    }

    private function removeArticle(): void
    {
        $article = DB::table('help_articles')
            ->where('slug', self::ARTICLE_SLUG)
            ->lockForUpdate()
            ->first();

        if (! $article
            || $article->title !== self::ARTICLE_TITLE
            || $article->summary !== self::ARTICLE_SUMMARY
            || (int) $article->current_revision !== 1
            || $article->status !== 'published'
        ) {
            throw new RuntimeException('Articolul despre aplicația pentru șoferi a fost modificat; revenirea a fost oprită.');
        }

        $revision = DB::table('help_article_revisions')
            ->where('help_article_id', $article->id)
            ->where('revision', 1)
            ->first();

        if (! $revision
            || $revision->source !== 'system'
            || $revision->body_markdown !== $article->body_markdown
        ) {
            throw new RuntimeException('Istoricul articolului despre aplicația pentru șoferi a fost modificat; revenirea a fost oprită.');
        }

        DB::table('help_article_revisions')->where('help_article_id', $article->id)->delete();
        DB::table('help_articles')->where('id', $article->id)->delete();
    }
};
