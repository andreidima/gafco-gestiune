<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('body_markdown');
            $table->string('section', 40)->default('reference')->index();
            $table->json('audience_roles')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedInteger('current_revision')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('help_article_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_article_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('body_markdown');
            $table->text('change_summary')->nullable();
            $table->string('source', 24)->default('system');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['help_article_id', 'revision']);
        });

        Schema::create('release_notes', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 140)->unique();
            $table->string('version', 40)->nullable()->index();
            $table->string('title');
            $table->text('summary');
            $table->longText('body_markdown');
            $table->json('audience_roles')->nullable();
            $table->json('affected_modules')->nullable();
            $table->boolean('requires_action')->default(false);
            $table->string('status', 24)->default('draft')->index();
            $table->date('released_at')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $this->insertInitialContent();
    }

    public function down(): void
    {
        Schema::dropIfExists('release_notes');
        Schema::dropIfExists('help_article_revisions');
        Schema::dropIfExists('help_articles');
    }

    private function insertInitialContent(): void
    {
        $publishedAt = now();
        $articles = [
            [
                'slug' => 'incepe-de-aici',
                'title' => 'Începe de aici',
                'summary' => 'O vedere de ansamblu pentru utilizatorii care deschid aplicația pentru prima dată.',
                'section' => 'start',
                'sort_order' => 10,
                'body' => trim(<<<'MARKDOWN'
# Ce este GAFCO Gestiune

Aplicația ține evidența materialelor, sculelor și utilajelor aflate în baze și pe șantiere. Ea leagă stocul de documentele prin care acesta se modifică și arată cine trebuie să aprobe, să transporte sau să confirme fiecare operațiune.

## Cele patru idei de bază

1. **Locațiile** sunt bazele și șantierele în care se află bunurile.
2. **Nomenclatorul** definește denumirea, categoria și unitatea de măsură a fiecărui articol.
3. **Materialele cantitative** sunt urmărite printr-o cantitate pe fiecare locație.
4. **Echipamentele individuale** au un cod propriu și pot fi identificate prin QR.

## De unde să începi

- Dacă lucrezi cu materiale, consultă mai întâi **Circuitul materialelor**.
- Dacă ai primit o sarcină, verifică **Ghidurile după rol**.
- Dacă nu înțelegi o stare precum „În aprobare” sau „În tranzit”, deschide **Statusuri și termeni**.
- Pagina **Noutăți** explică schimbările vizibile aduse aplicației.

## Ce nu modifică stocul

Simpla consultare a paginilor, crearea unei sarcini, alocarea unui șofer sau acordarea unei aprobări nu modifică singure cantitățile. Stocul se schimbă prin recepție, consum sau confirmarea primirii unui transfer.
MARKDOWN),
            ],
            [
                'slug' => 'cum-functioneaza-aplicatia',
                'title' => 'Cum funcționează aplicația',
                'summary' => 'Legătura dintre locații, nomenclator, stoc, transferuri, sarcini și rapoarte.',
                'section' => 'workflows',
                'sort_order' => 20,
                'body' => trim(<<<'MARKDOWN'
# Cum funcționează aplicația

## 1. Datele de bază

Administratorii și dispecerii configurează locațiile și utilizatorii. Nomenclatorul conține materialele, sculele și utilajele folosite în operațiuni.

Un articol poate fi urmărit:

- **cantitativ**, printr-un stoc exprimat în unitatea lui de măsură;
- **individual**, printr-un echipament cu identificator și cod QR.

## 2. Intrările și ieșirile

- Recepția de la furnizor adaugă material în locația selectată.
- Consumul scade materialul din locația selectată.
- Transferul mută materiale și echipamente între două locații.
- Returul este un transfer nou, în sens invers, legat de transferul inițial.

## 3. Transferul și sarcina șoferului

La crearea unui transfer se creează și o sarcină de transport. Aplicația urmărește separat:

- aprobările locației sursă și destinație;
- alocarea și răspunsul șoferului;
- termenul stabilit de manager;
- starea transportului;
- confirmarea primirii.

Modificarea datelor importante ale transferului creează o revizie nouă și relansează aprobările necesare.

## 4. Vizibilitatea

Administratorii și dispecerii au vedere operațională globală. Gestionarii și șefii de șantier lucrează cu locațiile pe care le administrează. Șoferii și muncitorii văd spații de lucru adaptate activității lor. Contabilitatea are acces de consultare la informațiile relevante pentru recepții, consum și rapoarte.

## 5. Rapoartele

Rapoartele reunesc stocurile pe locații, echipamentele care necesită atenție, transferurile recente, transporturile rămase în tranzit și consumurile înregistrate.
MARKDOWN),
            ],
            [
                'slug' => 'circuitul-materialelor',
                'title' => 'Circuitul materialelor',
                'summary' => 'Când și prin ce documente cresc, se mută sau scad cantitățile.',
                'section' => 'workflows',
                'sort_order' => 30,
                'body' => trim(<<<'MARKDOWN'
# Circuitul materialelor

## Intrare: recepția de la furnizor

În **Gestiune → Recepții**, utilizatorul alege locația, furnizorul, documentul, materialul și cantitatea. La salvare:

1. se creează documentul de recepție;
2. se păstrează materialul și cantitatea primită;
3. stocul locației crește imediat cu cantitatea introdusă.

În forma actuală, o salvare de recepție conține un singur material. Pentru alt material se înregistrează o recepție nouă.

## Mișcare: transferul între locații

La crearea transferului, aplicația verifică dacă sursa are cantitatea solicitată. Crearea, aprobarea și alocarea șoferului nu mută încă stocul.

Fluxul normal este:

1. solicitarea transferului;
2. aprobările și acceptarea sarcinii de către șofer;
3. pornirea transportului și starea **În tranzit**;
4. confirmarea primirii la destinație;
5. scăderea cantității din sursă și adăugarea ei la destinație.

Cantitatea se mută efectiv numai la confirmarea primirii. Dacă între timp stocul sursei nu mai este suficient, confirmarea este oprită și situația trebuie clarificată.

O diferență la primire poate fi consemnată prin observații. În forma actuală, confirmarea mută întreaga cantitate din transfer; observația nu modifică separat cantitatea primită.

## Ieșire: consumul

În **Gestiune → Consum**, utilizatorul alege locația, materialul și cantitatea consumată. La salvare:

1. aplicația verifică stocul locației;
2. refuză o cantitate mai mare decât stocul disponibil;
3. creează raportul de consum;
4. scade imediat cantitatea.

În forma actuală, o salvare de consum conține un singur material.

## Returul

Returul nu anulează documentul inițial. El creează un transfer nou, legat de transferul recepționat, și urmează același circuit de transport și confirmare.

## Echipamentele cu QR

Echipamentele individuale nu folosesc cantități de stoc. La pornirea transportului, echipamentul primește starea **În transfer**. La confirmarea primirii, locația lui curentă devine destinația.
MARKDOWN),
            ],
            [
                'slug' => 'ghiduri-dupa-rol',
                'title' => 'Ghiduri după rol',
                'summary' => 'Responsabilitățile principale ale fiecărui tip de utilizator.',
                'section' => 'roles',
                'sort_order' => 40,
                'body' => trim(<<<'MARKDOWN'
# Ghiduri după rol

## Administrator și super-administrator

- configurează utilizatorii, rolurile și datele operaționale;
- consultă toate locațiile, stocurile, transferurile, sarcinile și rapoartele;
- poate interveni în fluxurile operaționale atunci când este necesar.

## Dispecer

- are vedere operațională globală;
- creează și urmărește transferuri și sarcini;
- alocă șoferi și urmărește răspunsurile și termenele;
- consultă recepțiile, consumurile și rapoartele.

## Gestionar de bază

- gestionează nomenclatorul și stocul locațiilor alocate;
- înregistrează recepții și consumuri;
- solicită, aprobă, expediază sau recepționează transferuri, în limitele locațiilor administrate.

## Șef de șantier

- urmărește locațiile administrate;
- solicită și aprobă transferuri;
- confirmă primirea la destinație;
- înregistrează consumul și poate crea sarcini pentru șoferi.

## Șofer

- vede sarcinile și transferurile care îi sunt alocate;
- acceptă sau refuză o alocare;
- poate comunica o estimare și poate solicita realocarea;
- marchează începutul transportului din sarcină.

Șoferul nu confirmă stocul la destinație. Confirmarea primirii aparține responsabilului locației destinație sau unui utilizator operațional autorizat.

## Muncitor

- vede echipamentele aflate în custodia sa;
- poate iniția predarea unui echipament către alt muncitor;
- predarea se finalizează numai după acordul ambelor persoane.

## Contabil

- consultă recepțiile, consumurile și rapoartele;
- nu modifică datele operaționale doar prin rolul de contabil.
MARKDOWN),
            ],
            [
                'slug' => 'pagini-si-operatiuni',
                'title' => 'Pagini și operațiuni',
                'summary' => 'Ce găsești în fiecare zonă importantă a aplicației.',
                'section' => 'reference',
                'sort_order' => 50,
                'body' => trim(<<<'MARKDOWN'
# Pagini și operațiuni

## Acasă

Panoul principal se adaptează rolului. El afișează acțiunile urgente, sarcinile proprii sau situația operațională generală.

## Gestiune

- **Locații**: baze, șantiere și responsabili activi.
- **Nomenclator**: materialele, sculele și utilajele recunoscute de aplicație.
- **Echipamente**: bunurile urmărite individual prin cod și QR.
- **Recepții**: intrările cantitative de la furnizori.
- **Consum**: materialele consumate într-o locație.
- **Retururi**: transferurile inverse legate de un transfer inițial.

## Operațiuni

- **Transferuri**: documentul care descrie sursa, destinația, conținutul, aprobările și primirea.
- **Sarcini șoferi**: alocarea, acceptarea, termenul, estimarea și progresul transportului.
- **Situație șoferi**: disponibilitatea șoferilor și sarcinile care trebuie alocate.

## Teren

O interfață simplificată pentru șeful de șantier, muncitor și scanarea QR. Aici sunt aduse în față aprobările, transferurile active, consumul și echipamentele în custodie.

## Rapoarte

Centralizează informațiile despre stoc și echipamente pe locații, transferuri, întârzieri, diferențe și consum.

## Notificări

Anunță aprobări, alocări și schimbări care necesită atenția utilizatorului. Deschiderea unei notificări nu modifică stocul.
MARKDOWN),
            ],
            [
                'slug' => 'statusuri-si-termeni',
                'title' => 'Statusuri și termeni',
                'summary' => 'Explicații pentru stările folosite în transferuri, sarcini și echipamente.',
                'section' => 'reference',
                'sort_order' => 60,
                'body' => trim(<<<'MARKDOWN'
# Statusuri și termeni

## Transferuri

- **În aprobare**: transferul a fost creat și mai există decizii sau acceptări în așteptare.
- **Aprobat**: toate aprobările curente sunt înregistrate.
- **În tranzit**: sarcina de transport a fost pornită.
- **Recepționat**: destinația a confirmat primirea, iar stocul sau locația echipamentelor a fost actualizată.
- **Anulat**: operațiunea a fost oprită fără ștergerea istoricului.
- **Arhivat**: operațiunea închisă a fost mutată din activitatea curentă.
- **Revizie**: versiunea datelor unui transfer. Modificările importante pot relansa aprobările.

## Sarcini

- **Nealocat**: sarcina nu are șofer.
- **Așteaptă șoferul**: șoferul trebuie să accepte sau să refuze.
- **Acceptat**: șoferul a acceptat sarcina.
- **În lucru**: șoferul a început sarcina; pentru un transfer, aceasta corespunde transportului în tranzit.
- **Finalizat**: sarcina a fost încheiată.
- **Refuzat**: șoferul propus a refuzat alocarea.

## Echipamente

- **Disponibil**: poate fi utilizat sau transferat.
- **În folosință**: este alocat unui responsabil.
- **În transfer**: face parte dintr-un transport pornit.
- **În service**: nu este disponibil pentru utilizare normală.
- **Lipsă**: necesită verificare și intervenție.

## Termeni

- **Sursă**: locația din care pleacă materialul sau echipamentul.
- **Destinație**: locația care confirmă primirea.
- **Custodie**: persoana responsabilă în prezent de un echipament individual.
- **Recepție**: document de intrare în stoc.
- **Consum**: document de scădere din stoc.
MARKDOWN),
            ],
        ];

        foreach ($articles as $article) {
            $articleId = DB::table('help_articles')->insertGetId([
                'slug' => $article['slug'],
                'title' => $article['title'],
                'summary' => $article['summary'],
                'body_markdown' => $article['body'],
                'section' => $article['section'],
                'audience_roles' => json_encode(['all'], JSON_UNESCAPED_UNICODE),
                'sort_order' => $article['sort_order'],
                'status' => 'published',
                'current_revision' => 1,
                'published_at' => $publishedAt,
                'created_at' => $publishedAt,
                'updated_at' => $publishedAt,
            ]);

            DB::table('help_article_revisions')->insert([
                'help_article_id' => $articleId,
                'revision' => 1,
                'title' => $article['title'],
                'summary' => $article['summary'],
                'body_markdown' => $article['body'],
                'change_summary' => 'Conținut inițial publicat odată cu Centrul de ajutor.',
                'source' => 'system',
                'published_at' => $publishedAt,
                'created_at' => $publishedAt,
                'updated_at' => $publishedAt,
            ]);
        }

        $releaseNotes = [
            [
                'slug' => '2026-06-30-lansare-initiala',
                'version' => '2026.06.30',
                'title' => 'Lansarea aplicației GAFCO Gestiune',
                'summary' => 'Prima versiune a centralizat locațiile, nomenclatorul, stocurile, echipamentele și transferurile.',
                'released_at' => '2026-06-30',
                'modules' => ['Locații', 'Nomenclator', 'Echipamente', 'Transferuri'],
                'body' => trim(<<<'MARKDOWN'
# Ce a fost introdus

- evidența bazelor și șantierelor;
- nomenclator pentru materiale, scule și utilaje;
- stoc cantitativ pe locație;
- echipamente urmărite individual prin cod și QR;
- transferuri între locații;
- utilizatori și roluri operaționale;
- panou principal și rapoarte de bază.

Această versiune a creat fundația comună pentru urmărirea bunurilor și a mișcărilor dintre locații.
MARKDOWN),
            ],
            [
                'slug' => '2026-07-02-fluxuri-logistice',
                'version' => '2026.07.02',
                'title' => 'Recepții, consum și vizibilitate operațională',
                'summary' => 'Au fost extinse documentele care explică intrările și ieșirile din stoc și rapoartele aferente.',
                'released_at' => '2026-07-02',
                'modules' => ['Recepții', 'Consum', 'Rapoarte', 'Teren'],
                'body' => trim(<<<'MARKDOWN'
# Ce s-a schimbat

- recepțiile de la furnizori pot adăuga cantități în stoc;
- rapoartele de consum scad cantitățile din locația selectată;
- au fost adăugate informații despre diferențe și operațiuni care necesită atenție;
- panoul principal și modul de teren au primit mai multe informații operaționale;
- rapoartele reunesc mai clar stocurile, transferurile și consumurile.
MARKDOWN),
            ],
            [
                'slug' => '2026-07-06-navigare-liste',
                'version' => '2026.07.06',
                'title' => 'Navigare mai clară în liste',
                'summary' => 'Butoanele de trecere între paginile listelor au fost corectate pentru a rămâne ușor de citit și folosit.',
                'released_at' => '2026-07-06',
                'modules' => ['Liste', 'Navigare'],
                'body' => trim(<<<'MARKDOWN'
# Ce s-a schimbat

- navigarea între paginile listelor este afișată într-un format potrivit aplicației;
- butoanele pentru pagina anterioară, pagina următoare și numerele paginilor sunt mai clare;
- listele lungi pot fi parcurse fără elemente supradimensionate sau greu de folosit.

Nu este necesară nicio acțiune din partea utilizatorilor.
MARKDOWN),
            ],
            [
                'slug' => '2026-07-22-fluxuri-operationale',
                'version' => '2026.07.22',
                'title' => 'Fluxuri complete pentru transferuri și sarcini',
                'summary' => 'Transferurile, aprobările și activitatea șoferilor au fost organizate într-un circuit complet.',
                'released_at' => '2026-07-22',
                'modules' => ['Transferuri', 'Sarcini șoferi', 'Aprobări', 'Utilizatori', 'Notificări'],
                'body' => trim(<<<'MARKDOWN'
# Ce s-a schimbat

- fiecare transfer are un circuit de aprobări pentru sursă, destinație și șofer;
- modificările importante creează revizii și relansează aprobările;
- transferurile și cererile de șofer sunt legate de sarcini;
- șoferii pot accepta, refuza, estima și solicita realocarea;
- managerii pot urmări termenele și disponibilitatea șoferilor;
- transferurile pot fi anulate sau arhivate fără pierderea istoricului;
- retururile sunt legate de transferurile inițiale;
- navigarea și panourile au fost adaptate mai clar fiecărui rol.
MARKDOWN),
            ],
            [
                'slug' => '2026-07-28-centru-ajutor',
                'version' => '2026.07.28',
                'title' => 'Centru de ajutor și noutăți în aplicație',
                'summary' => 'Utilizatorii pot consulta direct în aplicație fluxurile, rolurile, statusurile și schimbările importante.',
                'released_at' => '2026-07-28',
                'modules' => ['Centru de ajutor', 'Noutăți', 'Navigare'],
                'body' => trim(<<<'MARKDOWN'
# Ce este nou

- în bara principală a fost adăugată pictograma **Ajutor și noutăți**;
- Centrul de ajutor explică aplicația, circuitul materialelor, rolurile, paginile și statusurile;
- pagina Noutăți prezintă schimbările importante într-un limbaj orientat către utilizatori;
- informațiile sunt disponibile tuturor utilizatorilor autentificați;
- conținutul păstrează revizii pentru a permite administrarea și comentariile într-o etapă viitoare.

Nu este necesară nicio acțiune din partea utilizatorilor.
MARKDOWN),
            ],
        ];

        foreach ($releaseNotes as $note) {
            DB::table('release_notes')->insert([
                'slug' => $note['slug'],
                'version' => $note['version'],
                'title' => $note['title'],
                'summary' => $note['summary'],
                'body_markdown' => $note['body'],
                'audience_roles' => json_encode(['all'], JSON_UNESCAPED_UNICODE),
                'affected_modules' => json_encode($note['modules'], JSON_UNESCAPED_UNICODE),
                'requires_action' => false,
                'status' => 'published',
                'released_at' => $note['released_at'],
                'published_at' => $publishedAt,
                'created_at' => $publishedAt,
                'updated_at' => $publishedAt,
            ]);
        }
    }
};
