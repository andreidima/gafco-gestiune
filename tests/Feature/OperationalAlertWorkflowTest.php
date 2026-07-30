<?php

namespace Tests\Feature;

use App\Models\AlertRule;
use App\Models\CatalogItem;
use App\Models\InventoryLot;
use App\Models\InventoryLotBalance;
use App\Models\Location;
use App\Models\OperationalAlert;
use App\Models\ReceptionIntake;
use App\Models\User;
use App\Services\OperationalAlertSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationalAlertWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_expiration_alert_is_targeted_deduplicated_escalated_and_closed_automatically(): void
    {
        $admin = $this->userWithRole('admin');
        $localManager = $this->userWithRole('sef-santier');
        $otherManager = $this->userWithRole('sef-santier');
        $accountant = $this->userWithRole('contabil');
        $worker = $this->userWithRole('muncitor');
        $location = $this->location('S-ALERT');
        $otherLocation = $this->location('S-OTHER');
        $this->assignLocation($localManager, $location);
        $this->assignLocation($otherManager, $otherLocation);
        $item = $this->item('MAT-ALERT');
        [$lot, $balance] = $this->lot($item, $location, now()->addDays(12)->toDateString(), 7);

        $service = app(OperationalAlertSyncService::class);
        $result = $service->sync(force: true);
        $alert = OperationalAlert::query()->sole();

        $this->assertSame(1, $result['detected']);
        $this->assertSame('warning', $alert->severity);
        $this->assertNull($alert->resolved_at);
        $this->assertStringContainsString("#lot-{$lot->id}-{$location->id}", $alert->url);
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(1, $localManager->notifications()->count());
        $this->assertSame(1, $accountant->notifications()->count());
        $this->assertSame(0, $otherManager->notifications()->count());
        $this->assertSame(0, $worker->notifications()->count());

        $service->sync(force: true);
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(1, $localManager->notifications()->count());

        $this->actingAs($localManager)->get(route('alerts.index'))
            ->assertOk()
            ->assertSee('MAT-ALERT')
            ->assertSee('Lot aproape de expirare');
        $this->actingAs($otherManager)->get(route('alerts.index'))
            ->assertOk()
            ->assertDontSee('MAT-ALERT');
        $this->actingAs($worker)->get(route('alerts.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Stoc și recepții care cer verificare.')
            ->assertSee(route('alerts.index'), false);

        $lot->update(['expires_at' => now()->subDay()->toDateString()]);
        $service->sync(force: true);
        $this->assertSame('danger', $alert->fresh()->severity);
        $this->assertSame(2, $localManager->notifications()->count());

        $balance->update(['quantity' => 0]);
        $service->sync(force: true);
        $this->assertNotNull($alert->fresh()->resolved_at);
        $this->actingAs($localManager)->get(route('alerts.index', ['status' => 'resolved']))
            ->assertOk()
            ->assertSee('Închisă automat');
    }

    public function test_pending_reception_alert_obeys_threshold_and_closes_when_processed(): void
    {
        $admin = $this->userWithRole('admin');
        $manager = $this->userWithRole('gestionar-baza');
        $accountant = $this->userWithRole('contabil');
        $location = $this->location('B-DOC', 'base');
        $this->assignLocation($manager, $location);

        $oldIntake = ReceptionIntake::create([
            'number' => 'DR-OLD',
            'location_id' => $location->id,
            'submitted_by' => $admin->id,
            'status' => 'created',
        ]);
        $oldIntake->forceFill([
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ])->saveQuietly();
        ReceptionIntake::create([
            'number' => 'DR-NEW',
            'location_id' => $location->id,
            'submitted_by' => $admin->id,
            'status' => 'created',
        ]);

        app(OperationalAlertSyncService::class)->sync(force: true);

        $this->assertDatabaseHas('operational_alerts', [
            'fingerprint' => "reception_pending:{$oldIntake->id}",
            'severity' => 'warning',
        ]);
        $this->assertSame(1, $manager->operationalAlerts()->active()->count());
        $this->assertSame(1, $admin->operationalAlerts()->active()->count());
        $this->assertSame(0, $accountant->operationalAlerts()->count());
        $this->actingAs($manager)->get(route('alerts.index'))
            ->assertOk()
            ->assertSee('DR-OLD')
            ->assertDontSee('DR-NEW');

        $oldIntake->update([
            'status' => 'closed',
            'closure_type' => 'cancelled',
            'closed_at' => now(),
        ]);
        app(OperationalAlertSyncService::class)->sync(force: true);

        $this->assertNotNull(
            OperationalAlert::where('fingerprint', "reception_pending:{$oldIntake->id}")
                ->sole()
                ->resolved_at,
        );
    }

    public function test_location_rule_has_priority_and_optional_lot_data_does_not_create_noise(): void
    {
        $admin = $this->userWithRole('admin');
        $manager = $this->userWithRole('sef-santier');
        $location = $this->location('S-RULE');
        $this->assignLocation($manager, $location);
        $item = $this->item('MAT-RULE');
        $this->lot($item, $location, now()->addDays(20)->toDateString(), 4);
        $this->lot($item, $location, null, 8);

        AlertRule::create([
            'alert_type' => 'lot_expiration',
            'scope_key' => 'role:sef-santier',
            'scope_type' => 'role',
            'role_name' => 'sef-santier',
            'enabled' => true,
            'threshold_days' => 60,
            'changed_by' => $admin->id,
        ]);
        AlertRule::create([
            'alert_type' => 'lot_expiration',
            'scope_key' => "location:{$location->id}",
            'scope_type' => 'location',
            'location_id' => $location->id,
            'enabled' => true,
            'threshold_days' => 10,
            'changed_by' => $admin->id,
        ]);

        app(OperationalAlertSyncService::class)->sync(force: true);

        $this->assertSame(0, $manager->operationalAlerts()->count());
        $this->assertDatabaseCount('operational_alerts', 1);

        $this->actingAs($admin)->post(route('alert-rules.store'), [
            'alert_type' => 'lot_expiration',
            'scope_type' => 'location',
            'location_id' => $location->id,
            'enabled' => 1,
            'threshold_days' => 25,
        ])->assertRedirect();

        $this->assertSame(1, $manager->fresh()->operationalAlerts()->active()->count());
        $this->actingAs($manager)->get(route('alert-rules.index'))->assertForbidden();
    }

    public function test_alert_rule_table_keeps_status_and_threshold_in_separate_readable_cells(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->get(route('alert-rules.index'));

        $response
            ->assertOk()
            ->assertSee('alert-rules-table', false)
            ->assertSee('alert-rule-status-cell', false)
            ->assertSee('alert-rule-threshold-cell', false)
            ->assertSee('alert-rule-threshold-control', false)
            ->assertSee('form="alert-rule-update-', false)
            ->assertSee('value="30"', false)
            ->assertSee('>zile</span>', false);

        $this->assertStringNotContainsString(
            '<td colspan="2">',
            (string) $response->getContent(),
        );
    }

    public function test_help_articles_and_release_note_explain_the_alert_workflow(): void
    {
        $this->assertDatabaseHas('help_articles', [
            'slug' => 'circuitul-materialelor',
            'current_revision' => 7,
        ]);
        $this->assertDatabaseHas('help_articles', [
            'slug' => 'pagini-si-operatiuni',
            'current_revision' => 15,
        ]);
        $this->assertDatabaseHas('help_articles', [
            'slug' => 'ghiduri-dupa-rol',
            'current_revision' => 15,
        ]);
        $this->assertStringContainsString(
            'Regula locației are prioritatea cea mai mare',
            (string) DB::table('help_articles')
                ->where('slug', 'pagini-si-operatiuni')
                ->value('body_markdown'),
        );
        $this->assertDatabaseHas('release_notes', [
            'slug' => '2026-07-29-alerte-stoc-si-receptii',
            'version' => '2026.07.29.5',
            'status' => 'published',
        ]);
        $this->assertDatabaseHas('release_notes', [
            'slug' => '2026-07-30-praguri-vizibile-pentru-regulile-de-alertare',
            'version' => '2026.07.30.5',
            'status' => 'published',
        ]);
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function location(string $code, string $type = 'site'): Location
    {
        return Location::create([
            'type' => $type,
            'code' => $code,
            'name' => $code,
            'active' => true,
        ]);
    }

    private function assignLocation(User $user, Location $location): void
    {
        $location->managers()->attach($user->id, ['active' => true, 'is_primary' => true]);
        $location->update(['manager_user_id' => $user->id]);
    }

    private function item(string $sku): CatalogItem
    {
        return CatalogItem::create([
            'category' => 'material',
            'tracking_type' => 'quantity',
            'sku' => $sku,
            'name' => $sku,
            'unit' => 'buc',
            'active' => true,
        ]);
    }

    /**
     * @return array{InventoryLot, InventoryLotBalance}
     */
    private function lot(
        CatalogItem $item,
        Location $location,
        ?string $expiresAt,
        float $quantity,
    ): array {
        $lot = InventoryLot::create([
            'catalog_item_id' => $item->id,
            'source_key' => 'test-lot-'.fake()->uuid(),
            'lot_code' => 'LOT-'.fake()->unique()->numberBetween(100, 999),
            'received_at' => now()->subMonth(),
            'expires_at' => $expiresAt,
            'currency' => 'RON',
        ]);
        $balance = InventoryLotBalance::create([
            'inventory_lot_id' => $lot->id,
            'location_id' => $location->id,
            'quantity' => $quantity,
        ]);

        return [$lot, $balance];
    }
}
