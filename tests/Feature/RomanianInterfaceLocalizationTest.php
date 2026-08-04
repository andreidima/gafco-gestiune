<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Support\RomanianUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RomanianInterfaceLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_facing_named_routes_generate_romanian_urls(): void
    {
        $routes = [
            'login' => '/autentificare',
            'dashboard' => '/acasa',
            'locations.index' => '/locatii',
            'locations.create' => '/locatii/adauga',
            'locations.edit' => '/locatii/7/modifica',
            'catalog-items.index' => '/nomenclator',
            'suppliers.index' => '/furnizori',
            'inventory.index' => '/inventar',
            'tracked-assets.show' => '/echipamente/7',
            'projects.index' => '/proiecte',
            'transfers.index' => '/transferuri',
            'driver-requests.index' => '/cereri-sofer',
            'tasks.index' => '/sarcini',
            'tasks.dispatch' => '/sarcini/situatie-soferi',
            'notifications.index' => '/notificari',
            'alerts.index' => '/alerte',
            'alert-rules.index' => '/setari/alerte',
            'reception-intakes.index' => '/documente-de-procesat',
            'reception-intakes.create' => '/documente-de-procesat/trimite',
            'supplier-receptions.index' => '/receptii',
            'negotiated-orders.index' => '/comenzi-negociate',
            'consumption-reports.index' => '/consumuri',
            'returns.index' => '/retururi',
            'field.driver' => '/teren/sofer',
            'qr-scan.index' => '/scanare-qr',
            'reports.index' => '/rapoarte',
            'users.index' => '/utilizatori',
        ];

        $parameters = [
            'locations.edit' => ['location' => 7],
            'tracked-assets.show' => ['tracked_asset' => 7],
        ];

        foreach ($routes as $name => $expected) {
            $this->assertSame($expected, route($name, $parameters[$name] ?? [], false), $name);
        }
    }

    public function test_old_english_links_redirect_to_the_romanian_equivalent_and_keep_the_query(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard?perioada=luna')
            ->assertRedirect('/acasa?perioada=luna');

        $this->actingAs($user)
            ->get('/reception-intakes/create?location_id=12')
            ->assertRedirect('/documente-de-procesat/trimite?location_id=12');

        $this->actingAs($user)
            ->get('/tracked-assets/42?din=qr')
            ->assertRedirect('/echipamente/42?din=qr');
    }

    public function test_stored_application_urls_are_localized_without_changing_external_urls(): void
    {
        config(['app.url' => 'https://gafco.test']);
        $translator = app(RomanianUrl::class);

        $this->assertSame(
            '/documente-receptie/9/descarca?download=1#fisier',
            $translator->translate('/reception-documents/9/download?download=1#fisier'),
        );
        $this->assertSame(
            'https://gafco.test/sarcini/15/modifica',
            $translator->translate('https://gafco.test/tasks/15/edit'),
        );
        $this->assertSame(
            'https://example.com/tasks/15/edit',
            $translator->translate('https://example.com/tasks/15/edit'),
        );
    }

    public function test_an_existing_notification_with_an_english_url_opens_the_romanian_page(): void
    {
        $user = User::factory()->create();
        $user->notify(new WorkflowNotification(
            'Sarcină actualizată',
            'Deschide sarcina pentru detalii.',
            '/tasks/42/edit?din=notificare',
        ));
        $notification = $user->notifications()->sole();

        $this->actingAs($user)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect('/sarcini/42/modifica?din=notificare');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_standard_validation_message_and_error_page_are_in_romanian(): void
    {
        $validator = Validator::make([], ['location_id' => ['required']]);

        $this->assertSame('Câmpul locație este obligatoriu.', $validator->errors()->first('location_id'));

        $this->get('/pagina-care-nu-exista')
            ->assertNotFound()
            ->assertSee('Pagina nu a fost găsită')
            ->assertSee('Înapoi în aplicație')
            ->assertDontSee('Not Found');
    }

    public function test_localization_content_migration_is_reversible(): void
    {
        $pdfScrollingFix = require database_path('migrations/2026_08_04_000038_publish_reception_pdf_scrolling_fix.php');
        $pdfPreviewFix = require database_path('migrations/2026_08_04_000037_publish_reception_pdf_preview_fix.php');
        $safeguards = require database_path('migrations/2026_08_04_000035_publish_equipment_location_and_transfer_safeguards.php');
        $codeAndQuantityControls = require database_path('migrations/2026_08_02_000034_normalize_internal_codes_and_publish_quantity_controls.php');
        $migration = require database_path('migrations/2026_08_02_000033_publish_romanian_interface_localization.php');

        $this->assertDatabaseHas('help_articles', [
            'slug' => 'pagini-si-operatiuni',
            'current_revision' => 24,
        ]);
        $this->assertDatabaseHas('release_notes', [
            'slug' => '2026-08-02-interfata-complet-in-romana',
            'version' => '2026.08.02',
            'status' => 'published',
        ]);
        $this->assertStringNotContainsString(
            'Live',
            (string) DB::table('help_articles')->where('slug', 'pagini-si-operatiuni')->value('body_markdown'),
        );
        $this->assertStringNotContainsString(
            'dashboardul',
            (string) DB::table('release_notes')->where('slug', '2026-07-30-interfata-mobila-compacta')->value('body_markdown'),
        );

        $pdfScrollingFix->down();
        $pdfPreviewFix->down();
        $safeguards->down();
        $codeAndQuantityControls->down();
        $migration->down();

        $this->assertDatabaseHas('help_articles', [
            'slug' => 'pagini-si-operatiuni',
            'current_revision' => 19,
        ]);
        $this->assertDatabaseMissing('release_notes', [
            'slug' => '2026-08-02-interfata-complet-in-romana',
        ]);
        $this->assertStringContainsString(
            'dashboardul',
            (string) DB::table('release_notes')->where('slug', '2026-07-30-interfata-mobila-compacta')->value('body_markdown'),
        );

        $migration->up();
        $codeAndQuantityControls->up();
        $safeguards->up();
        $pdfPreviewFix->up();
        $pdfScrollingFix->up();

        $this->assertDatabaseHas('help_articles', [
            'slug' => 'pagini-si-operatiuni',
            'current_revision' => 24,
        ]);
    }
}
