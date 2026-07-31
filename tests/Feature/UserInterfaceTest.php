<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\Location;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_names_are_displayed_in_romanian_on_user_pages(): void
    {
        foreach (array_keys(config('roles.labels')) as $roleName) {
            Role::findOrCreate($roleName);
        }

        $admin = User::factory()->create([
            'login_code' => 'ADMIN-ROLURI',
            'email' => config('roles.protected_admin_email'),
        ]);
        $admin->assignRole('super-admin');

        $this->actingAs($admin)
            ->get(route('users.edit', $admin))
            ->assertOk()
            ->assertSee('Gestionar de bază')
            ->assertSee('Șef de șantier')
            ->assertSee('Șofer')
            ->assertSee('Utilizator')
            ->assertDontSee('Super administrator');

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Gestionar de bază')
            ->assertSee('Șef de șantier')
            ->assertSee('Șofer')
            ->assertSee('Utilizator')
            ->assertDontSee('Super administrator');
    }

    public function test_paginated_result_summary_is_displayed_in_romanian(): void
    {
        Role::findOrCreate('super-admin');

        $admin = User::factory()->create([
            'login_code' => 'ADMIN-PAGINARE',
            'email' => config('roles.protected_admin_email'),
        ]);
        $admin->assignRole('super-admin');
        User::factory()->count(20)->create();

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSeeText('Se afișează')
            ->assertSeeText('până la')
            ->assertSeeText('din')
            ->assertSeeText('21')
            ->assertSeeText('rezultate')
            ->assertDontSeeText('Showing')
            ->assertDontSeeText('results');
    }

    public function test_status_component_can_link_to_its_record(): void
    {
        $html = $this->blade('<x-status status="approved" href="/transfers/42" />');

        $html->assertSee('href="/transfers/42"', false)
            ->assertSee('status-badge-link', false)
            ->assertSeeText('Aprobat')
            ->assertSeeText('deschide înregistrarea');
    }

    public function test_back_link_has_a_safe_fallback_and_history_hook(): void
    {
        $this->blade('<x-back-link fallback="/transfers" />')
            ->assertSee('href="/transfers"', false)
            ->assertSee('data-smart-back', false)
            ->assertSeeText('Înapoi');
    }

    public function test_entity_filters_are_searchable_by_visible_and_secondary_identifiers(): void
    {
        Role::findOrCreate('admin');
        $admin = User::factory()->create(['login_code' => 'ADMIN-LISTE']);
        $admin->assignRole('admin');
        Location::create([
            'type' => 'base',
            'code' => 'B-CAUTARE',
            'name' => 'Baza pentru cautare',
            'active' => true,
        ]);
        Supplier::create([
            'name' => 'Furnizor pentru cautare',
            'cui' => 'RO123456',
            'normalized_cui' => '123456',
            'registration_number' => 'J40/123/2026',
            'active' => true,
        ]);
        CatalogItem::create([
            'category' => 'material',
            'tracking_type' => 'quantity',
            'sku' => 'MAT-CAUTARE',
            'barcode' => '594000000001',
            'name' => 'Material pentru cautare',
            'unit' => 'buc',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('supplier-receptions.index'))
            ->assertOk()
            ->assertSee('name="location_id" class="form-select" data-tom-select', false)
            ->assertSee('name="supplier_id" class="form-select" data-tom-select', false)
            ->assertSee('data-search="RO123456 J40/123/2026"', false)
            ->assertSee('name="catalog_item_id" class="form-select" data-tom-select', false)
            ->assertSee('data-search="MAT-CAUTARE 594000000001"', false)
            ->assertDontSee('name="document_type" class="form-select" data-tom-select', false);
    }

    public function test_every_entity_select_uses_the_shared_searchable_control(): void
    {
        $violations = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            $contents = File::get($file->getPathname());
            preg_match_all('/<select\b[^>]*>/s', $contents, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$tag, $offset]) {
                if (! preg_match('/(?:name|data-name)="[^"]*(?:location|supplier|catalog_item|driver|project|user|custodian|tracked_asset|material_custody)_(?:id|ids)[^"]*"/', $tag)) {
                    continue;
                }
                if (str_contains($tag, 'data-tom-select')) {
                    continue;
                }

                $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                $violations[] = $file->getRelativePathname().':'.$line;
            }
        }

        $this->assertSame([], $violations, 'Selectoare de entități fără căutare: '.implode(', ', $violations));
    }

    public function test_every_auto_submitted_filter_form_declares_a_live_results_fragment(): void
    {
        $violations = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            $contents = File::get($file->getPathname());
            preg_match_all('/<form\b[^>]*\bdata-auto-submit-filters\b[^>]*>/s', $contents, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$tag, $offset]) {
                $line = substr_count(substr($contents, 0, $offset), "\n") + 1;

                if (! preg_match('/\bdata-live-filter-target="#([^"]+)"/', $tag, $targetMatch)) {
                    $violations[] = $file->getRelativePathname().':'.$line.' (lipsește ținta)';

                    continue;
                }

                $targetId = preg_quote($targetMatch[1], '/');
                if (! preg_match('/<[^>]+(?=[^>]*\bid="'.$targetId.'")(?=[^>]*\bdata-live-filter-results\b)[^>]*>/s', $contents)) {
                    $violations[] = $file->getRelativePathname().':'.$line.' (țintă inexistentă)';
                }
            }
        }

        $this->assertSame([], $violations, 'Formulare fără rezultate live: '.implode(', ', $violations));
    }

    public function test_locations_list_exposes_the_focus_preserving_live_filter_contract(): void
    {
        Role::findOrCreate('admin');
        $admin = User::factory()->create(['login_code' => 'ADMIN-FILTRARE-LIVE']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('locations.index'))
            ->assertOk()
            ->assertSee('data-live-filter-target="#locations-results"', false)
            ->assertSee('id="locations-results"', false)
            ->assertSee('data-live-filter-results', false)
            ->assertSee('data-live-filter-summary', false);

        $script = File::get(resource_path('js/app.js'));

        $this->assertStringContainsString('const liveFilterMinLength = 2;', $script);
        $this->assertStringContainsString('new AbortController()', $script);
        $this->assertStringContainsString('new DOMParser()', $script);
        $this->assertStringContainsString('currentTarget.replaceWith(replacement)', $script);
    }

    public function test_transfer_source_refresh_replaces_searchable_inventory_options(): void
    {
        $script = File::get(resource_path('js/app.js'));
        $view = File::get(resource_path('views/transfers/form.blade.php'));

        $this->assertStringContainsString('const replaceSearchableSelectOptions = (element) => {', $script);
        $this->assertStringContainsString('select.clear(true);', $script);
        $this->assertStringContainsString('select.clearOptions();', $script);
        $this->assertStringContainsString('element.value = selectedValue;', $script);
        $this->assertStringContainsString('replaceOptions: replaceSearchableSelectOptions,', $script);

        $this->assertStringContainsString('GafcoSearchableSelect?.replaceOptions(select)', $view);
        $this->assertStringContainsString("source.addEventListener('change', () => loadInventory({ resetRows: true }))", $view);
        $this->assertStringContainsString('const controlsDisabled = inventoryLoading || !source.value;', $view);
        $this->assertStringContainsString('Se încarcă materialele…', $view);
        $this->assertStringContainsString('Alege echipamentul, dacă este cazul', $view);
    }
}
