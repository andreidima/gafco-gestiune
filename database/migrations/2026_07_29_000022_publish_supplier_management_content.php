<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ARTICLE_SLUG = 'administrarea-furnizorilor';

    private const RELEASE_SLUG = '2026-07-29-administrarea-furnizorilor';

    private const ARTICLE_TITLE = 'Administrarea furnizorilor';

    private const ARTICLE_SUMMARY = 'Cum sunt adăugați, modificați, dezactivați și păstrați în istoric furnizorii.';

    private const CHANGE_SUMMARY = 'Administrarea datelor furnizorilor și regulile de activare sau dezactivare.';

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
            throw new RuntimeException('Articolul despre administrarea furnizorilor există deja.');
        }

        $timestamp = now();
        $body = $this->articleBody();
        $articleId = DB::table('help_articles')->insertGetId([
            'slug' => self::ARTICLE_SLUG,
            'title' => self::ARTICLE_TITLE,
            'summary' => self::ARTICLE_SUMMARY,
            'body_markdown' => $body,
            'section' => 'reference',
            'audience_roles' => json_encode([
                'super-admin',
                'admin',
                'dispecer',
                'manager',
                'sef-santier',
                'gestionar-baza',
                'contabil',
            ], JSON_UNESCAPED_UNICODE),
            'sort_order' => 55,
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
            throw new RuntimeException('Nota despre administrarea furnizorilor există deja.');
        }

        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.07.29.11',
            'title' => 'Furnizorii pot fi administrați direct din aplicație',
            'summary' => 'Datele de identificare și contact ale furnizorilor pot fi adăugate, actualizate, dezactivate și păstrate în istoricul documentelor.',
            'body_markdown' => <<<'MARKDOWN'
# Ce s-a schimbat

- pagina **Gestiune → Furnizori** reunește furnizorii activi și inactivi;
- furnizorii pot fi căutați după denumire, CUI, persoană de contact, email sau telefon;
- administratorii, dispecerii și utilizatorii din contabilitate pot adăuga și modifica furnizori;
- pentru fiecare furnizor pot fi salvate denumirea, CUI-ul, numărul de la Registrul Comerțului, adresa, persoana de contact, emailul, telefonul și observațiile;
- aplicația verifică existența unui CUI identic și oprește înregistrarea duplicatelor;
- lista arată numărul recepțiilor, comenzile negociate și ultima activitate;
- un furnizor inactiv rămâne vizibil în istoricul comenzilor, recepțiilor și stocului, dar nu mai poate fi ales în documente noi;
- furnizorul nu poate fi dezactivat cât timp are comenzi negociate deschise;
- mesajul de blocare explică motivul și indică faptul că acele comenzi trebuie închise sau anulate;
- un furnizor inactiv poate fi reactivat oricând.

# Ce trebuie să facă utilizatorii

Folosiți **Gestiune → Furnizori** pentru actualizarea datelor. Dacă dezactivarea este blocată, închideți sau anulați mai întâi comenzile negociate deschise.
MARKDOWN,
            'audience_roles' => json_encode([
                'super-admin',
                'admin',
                'dispecer',
                'manager',
                'sef-santier',
                'gestionar-baza',
                'contabil',
            ], JSON_UNESCAPED_UNICODE),
            'affected_modules' => json_encode([
                'Furnizori',
                'Comenzi negociate',
                'Recepții',
                'Fișă inventar materiale',
                'Centru de ajutor',
            ], JSON_UNESCAPED_UNICODE),
            'requires_action' => false,
            'status' => 'published',
            'released_at' => '2026-07-29',
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
            || $note->version !== '2026.07.29.11'
            || $note->title !== 'Furnizorii pot fi administrați direct din aplicație'
            || $note->status !== 'published'
        ) {
            throw new RuntimeException('Nota despre administrarea furnizorilor a fost modificată; revenirea a fost oprită.');
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
            || (int) $article->current_revision !== 1
            || $article->title !== self::ARTICLE_TITLE
            || $article->summary !== self::ARTICLE_SUMMARY
            || $article->body_markdown !== $this->articleBody()
        ) {
            throw new RuntimeException('Articolul despre administrarea furnizorilor a fost modificat; revenirea a fost oprită.');
        }

        $revision = DB::table('help_article_revisions')
            ->where('help_article_id', $article->id)
            ->where('revision', 1)
            ->first();

        if (! $revision
            || $revision->source !== 'system'
            || $revision->change_summary !== self::CHANGE_SUMMARY
            || $revision->body_markdown !== $article->body_markdown
        ) {
            throw new RuntimeException('Revizia articolului despre furnizori a fost modificată; revenirea a fost oprită.');
        }

        DB::table('help_articles')->where('id', $article->id)->delete();
    }

    private function articleBody(): string
    {
        return <<<'MARKDOWN'
# Administrarea furnizorilor

Pagina **Gestiune → Furnizori** păstrează datele de identificare și contact folosite în comenzi, recepții și istoricul stocului.

## Cine poate administra furnizorii

Administratorii, dispecerii și utilizatorii din contabilitate pot:

- adăuga un furnizor;
- modifica datele unui furnizor;
- dezactiva sau reactiva un furnizor.

Managerii, șefii de șantier și gestionarii de bază pot consulta lista și pot vedea activitatea furnizorului, fără să îi modifice datele.

## Datele furnizorului

Pentru fiecare furnizor pot fi salvate:

- denumirea;
- CUI-ul;
- numărul de la Registrul Comerțului;
- adresa;
- persoana de contact;
- emailul și telefonul;
- observații interne.

CUI-ul este verificat fără să conteze spațiile, semnele de separare sau prefixul **RO**. Dacă există deja un furnizor cu același CUI, salvarea este oprită pentru a evita dublurile.

## Activ și inactiv

Un furnizor activ poate fi ales în comenzi negociate și recepții noi.

Un furnizor inactiv:

- nu mai apare între opțiunile documentelor noi;
- rămâne vizibil în comenzile, recepțiile, loturile și filtrele istorice;
- poate fi reactivat fără să se piardă istoricul.

Furnizorii nu sunt șterși definitiv din interfață, deoarece documentele existente trebuie să păstreze legătura cu furnizorul corect.

## De ce poate fi blocată dezactivarea

Dacă furnizorul are una sau mai multe comenzi negociate deschise, dezactivarea este blocată. Aplicația arată câte comenzi sunt deschise și explică faptul că acestea trebuie închise sau anulate înainte.

Administratorii pot deschide direct lista filtrată a comenzilor. Pentru celelalte roluri care administrează furnizori, mesajul indică faptul că un administrator trebuie să închidă sau să anuleze comenzile.
MARKDOWN;
    }
};
