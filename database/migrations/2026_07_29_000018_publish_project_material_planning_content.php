<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RELEASE_SLUG = '2026-07-29-planuri-materiale-pe-proiect';

    public function up(): void
    {
        if (DB::connection()->pretending()) {
            return;
        }

        DB::transaction(function (): void {
            $this->reviseArticle(
                'circuitul-materialelor',
                6,
                7,
                <<<'MARKDOWN'

## Planul de materiale al unui proiect

Un proiect poate avea o listă de materiale și cantități planificate pentru o singură locație. Transferurile obișnuite legate de proiect sunt adunate automat și comparate cu planul.

În calcul intră toate transferurile neanulate legate de proiect, indiferent dacă așteaptă aprobarea, sunt în tranzit sau au fost recepționate. Retururile și echipamentele urmărite individual nu consumă cantitățile planului.

Dacă un transfer conține un material care nu exista în plan, cantitatea planificată pentru acel material este considerată zero. Transferul rămâne permis, dar materialul este marcat ca neplanificat și apare ca depășire.

Anularea transferului sau mărirea cantității planificate recalculează situația. Alerta se închide automat când cantitatea solicitată nu mai depășește planul.
MARKDOWN,
                'Planificarea cantităților de materiale și calculul depășirilor pe proiect.',
            );

            $this->reviseArticle(
                'pagini-si-operatiuni',
                9,
                10,
                <<<'MARKDOWN'

## Proiecte și planuri de materiale

Pagina **Proiecte materiale** arată proiectele vizibile, locația lor, numărul materialelor planificate, transferurile luate în calcul și eventualele depășiri.

În pagina unui proiect, fiecare material arată:

- cantitatea planificată;
- cantitatea solicitată prin transferuri neanulate;
- cantitatea rămasă sau depășirea;
- progresul procentual și materialele care nu existau în plan.

La crearea sau modificarea unui transfer obișnuit poate fi ales un proiect activ pentru locația de destinație. Formularul estimează impactul documentului asupra planului înainte de salvare. Depășirea nu blochează transferul, deoarece poate reprezenta o nevoie reală, dar este evidențiată și notificată.

În **Alerte**, tipul **Plan de materiale depășit** trimite utilizatorul direct la materialul și proiectul care necesită verificare.
MARKDOWN,
                'Pagina proiectelor, legarea transferului și alertele pentru depășirea planului.',
            );

            $this->reviseArticle(
                'ghiduri-dupa-rol',
                10,
                11,
                <<<'MARKDOWN'

## Responsabilități pentru planurile de materiale

- administratorul și managerul general pot crea proiecte, pot stabili cantitățile planificate și pot modifica starea proiectului;
- șeful de șantier și gestionarul de bază văd proiectele locațiilor pe care le administrează și pot lega un transfer de un proiect activ;
- dispecerul poate consulta proiectele și depășirile, fără să modifice planul;
- șoferul nu vede planul general al proiectului; în sarcină rămân vizibile numai informațiile necesare transportului.

Când planul este depășit, alerta ajunge la utilizatorul care a creat proiectul, la administratori și la responsabilii activi ai locației. Astfel, solicitarea rămâne vizibilă atât pentru coordonarea generală, cât și pentru echipa locală.
MARKDOWN,
                'Rolurile care definesc, folosesc și verifică planurile de materiale.',
            );

            $this->reviseArticle(
                'statusuri-si-termeni',
                4,
                5,
                <<<'MARKDOWN'

## Stările proiectului

- **Ciornă**: planul este în pregătire și nu poate fi ales în transferuri noi.
- **Activ**: proiectul poate fi ales în transferuri, iar cantitățile sunt monitorizate.
- **Finalizat**: proiectul rămâne în istoric și nu mai poate fi ales în transferuri noi.
- **Arhivat**: proiectul este păstrat numai pentru consultare și nu mai poate fi modificat.

**Cantitate solicitată** înseamnă totalul materialului din transferurile neanulate legate de proiect. **Depășire** înseamnă diferența pozitivă dintre cantitatea solicitată și cantitatea planificată.
MARKDOWN,
                'Stările proiectului și termenii folosiți pentru cantitățile planificate.',
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
            $this->restoreArticle('statusuri-si-termeni', 5, 'Stările proiectului și termenii folosiți pentru cantitățile planificate.');
            $this->restoreArticle('ghiduri-dupa-rol', 11, 'Rolurile care definesc, folosesc și verifică planurile de materiale.');
            $this->restoreArticle('pagini-si-operatiuni', 10, 'Pagina proiectelor, legarea transferului și alertele pentru depășirea planului.');
            $this->restoreArticle('circuitul-materialelor', 7, 'Planificarea cantităților de materiale și calculul depășirilor pe proiect.');
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
            throw new RuntimeException('Nota de versiune pentru planurile de materiale există deja.');
        }

        DB::table('release_notes')->insert([
            'slug' => self::RELEASE_SLUG,
            'version' => '2026.07.29.9',
            'title' => 'Planuri de materiale pe proiect și alerte la depășire',
            'summary' => 'Cantitățile planificate pot fi urmărite față de transferurile solicitate, iar depășirile sunt evidențiate și notificate.',
            'body_markdown' => <<<'MARKDOWN'
# Ce s-a schimbat

- administratorii și managerii generali pot crea proiecte pentru o locație și pot defini cantitățile planificate pentru fiecare material;
- responsabilii locali văd proiectele locațiilor lor și pot alege un proiect activ când creează un transfer;
- formularul transferului estimează cantitatea de după document și arată dacă planul va fi depășit;
- transferul nu este blocat când planul este depășit, astfel încât o nevoie reală poate fi înregistrată;
- pagina proiectului compară cantitatea planificată cu totalul transferurilor neanulate;
- materialele care nu existau în plan sunt marcate ca neplanificate și sunt considerate depășiri de la zero;
- retururile și echipamentele urmărite individual nu consumă planul de materiale;
- depășirile apar în lista proiectelor, în transfer și în pagina **Alerte**;
- alerta ajunge la responsabilul planului, la administratori și la responsabilii locației;
- anularea transferului sau corectarea planului închide automat alerta când depășirea dispare.
MARKDOWN,
            'audience_roles' => json_encode(
                ['super-admin', 'admin', 'manager', 'dispecer', 'sef-santier', 'gestionar-baza'],
                JSON_UNESCAPED_UNICODE,
            ),
            'affected_modules' => json_encode(
                ['Proiecte materiale', 'Transferuri', 'Alerte', 'Panou principal'],
                JSON_UNESCAPED_UNICODE,
            ),
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
            || $note->version !== '2026.07.29.9'
            || $note->title !== 'Planuri de materiale pe proiect și alerte la depășire'
            || $note->status !== 'published'
        ) {
            throw new RuntimeException('Nota despre planurile de materiale a fost modificată; revenirea a fost oprită.');
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

            throw new RuntimeException(
                "Articolul {$slug} are revizia {$actual}; era așteptată revizia {$expectedRevision}."
            );
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
