<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\Location;
use App\Models\NegotiatedOrder;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\SupplierReception;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NegotiatedOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_center_and_release_note_explain_the_order_workflow(): void
    {
        $this->assertDatabaseHas('help_articles', [
            'slug' => 'pagini-si-operatiuni',
            'current_revision' => 14,
        ]);
        $this->assertDatabaseHas('help_articles', [
            'slug' => 'ghiduri-dupa-rol',
            'current_revision' => 15,
        ]);
        $this->assertDatabaseHas('help_articles', [
            'slug' => 'statusuri-si-termeni',
            'current_revision' => 5,
        ]);
        $this->assertStringContainsString(
            'Comenzi negociate',
            (string) DB::table('help_articles')->where('slug', 'pagini-si-operatiuni')->value('body_markdown'),
        );
        $this->assertDatabaseHas('release_notes', [
            'slug' => '2026-07-29-comenzi-negociate',
            'version' => '2026.07.29.7',
            'status' => 'published',
        ]);
    }

    public function test_order_schema_and_content_migrations_are_reversible_together(): void
    {
        $searchableListsMigration = require database_path('migrations/2026_07_30_000025_publish_searchable_entity_lists.php');
        $navigationMigration = require database_path('migrations/2026_07_30_000024_publish_consistent_navigation_and_quantities.php');
        $mobileRefinementMigration = require database_path('migrations/2026_07_30_000023_publish_mobile_interface_refinement_content.php');
        $driverMigration = require database_path('migrations/2026_07_29_000020_publish_driver_mobile_task_content.php');
        $planningMigration = require database_path('migrations/2026_07_29_000018_publish_project_material_planning_content.php');
        $operationalMigration = require database_path('migrations/2026_07_29_000016_publish_operational_improvements_content.php');
        $contentMigration = require database_path('migrations/2026_07_29_000015_publish_negotiated_orders_content.php');
        $schemaMigration = require database_path('migrations/2026_07_29_000014_create_negotiated_orders.php');

        $searchableListsMigration->down();
        $navigationMigration->down();
        $mobileRefinementMigration->down();
        $driverMigration->down();
        $planningMigration->down();
        $operationalMigration->down();
        $contentMigration->down();
        $schemaMigration->down();

        $this->assertFalse(Schema::hasTable('negotiated_order_lines'));
        $this->assertFalse(Schema::hasTable('negotiated_orders'));
        $this->assertFalse(Schema::hasColumn('supplier_receptions', 'negotiated_order_id'));

        $schemaMigration->up();
        $contentMigration->up();
        $operationalMigration->up();
        $planningMigration->up();
        $driverMigration->up();
        $mobileRefinementMigration->up();
        $navigationMigration->up();
        $searchableListsMigration->up();

        $this->assertTrue(Schema::hasTable('negotiated_orders'));
        $this->assertTrue(Schema::hasColumn('supplier_receptions', 'negotiated_order_id'));
    }

    public function test_administrator_creates_an_order_without_changing_stock(): void
    {
        $admin = $this->userWithRole('admin');
        $location = $this->location();
        $supplier = $this->supplier();
        [$firstItem, $secondItem] = $this->items();

        $response = $this->actingAs($admin)->post(route('negotiated-orders.store'), [
            'location_id' => $location->id,
            'supplier_id' => $supplier->id,
            'currency' => 'EUR',
            'notes' => 'Condiții negociate',
            'lines' => [
                [
                    'catalog_item_id' => $firstItem->id,
                    'quantity' => 10.5,
                    'unit_price' => 12.25,
                    'notes' => 'Prima poziție',
                ],
                [
                    'catalog_item_id' => $secondItem->id,
                    'quantity' => 3,
                    'unit_price' => 8,
                ],
            ],
        ]);

        $order = NegotiatedOrder::with('lines')->sole();
        $response->assertRedirect(route('negotiated-orders.show', $order));
        $this->assertMatchesRegularExpression('/^CMD-\d{4}-\d{5}$/', $order->number);
        $this->assertSame('created', $order->status);
        $this->assertSame('EUR', $order->currency);
        $this->assertSame($admin->id, $order->created_by);
        $this->assertCount(2, $order->lines);
        $this->assertSame('buc', $order->lines->first()->unit);
        $this->assertDatabaseCount('stock_levels', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_orders_are_visible_and_editable_only_to_administrators(): void
    {
        $admin = $this->userWithRole('admin');
        $superAdmin = $this->userWithRole('super-admin');
        $dispatcher = $this->userWithRole('dispecer');
        $manager = $this->userWithRole('manager');
        $order = $this->createOrder($admin);

        $this->actingAs($admin)->get(route('negotiated-orders.index'))->assertOk();
        $this->actingAs($superAdmin)->get(route('negotiated-orders.show', $order))->assertOk();

        foreach ([$dispatcher, $manager] as $user) {
            $this->actingAs($user)->get(route('negotiated-orders.index'))->assertForbidden();
            $this->actingAs($user)->get(route('negotiated-orders.show', $order))->assertForbidden();
            $this->actingAs($user)->post(route('negotiated-orders.store'), $this->payload())->assertForbidden();
        }
    }

    public function test_created_order_can_be_updated_and_cancelled_but_never_deleted(): void
    {
        $admin = $this->userWithRole('admin');
        $order = $this->createOrder($admin);
        $otherSupplier = Supplier::create(['name' => 'Furnizor actualizat', 'active' => true]);
        $otherItem = CatalogItem::create([
            'category' => 'material',
            'tracking_type' => 'quantity',
            'sku' => 'MAT-UPDATED',
            'name' => 'Material actualizat',
            'unit' => 'kg',
            'active' => true,
        ]);

        $this->actingAs($admin)->put(route('negotiated-orders.update', $order), [
            'location_id' => $order->location_id,
            'supplier_id' => $otherSupplier->id,
            'currency' => 'RON',
            'notes' => 'Comandă corectată',
            'lines' => [[
                'catalog_item_id' => $otherItem->id,
                'quantity' => 15,
                'unit_price' => 4.75,
            ]],
        ])->assertRedirect(route('negotiated-orders.show', $order));

        $order->refresh()->load('lines');
        $this->assertSame($otherSupplier->id, $order->supplier_id);
        $this->assertCount(1, $order->lines);
        $this->assertSame('kg', $order->lines->sole()->unit);

        $this->actingAs($admin)
            ->post(route('negotiated-orders.cancel', $order), ['closure_reason' => 'Furnizorul nu mai poate livra.'])
            ->assertRedirect(route('negotiated-orders.show', $order));

        $order->refresh();
        $this->assertSame('closed', $order->status);
        $this->assertSame('cancelled', $order->closure_type);
        $this->assertSame($admin->id, $order->closed_by);
        $this->assertNotNull($order->closed_at);
        $this->actingAs($admin)->get(route('negotiated-orders.edit', $order))->assertStatus(409);
        $this->assertFalse(collect(app('router')->getRoutes()->getRoutes())
            ->contains(fn ($route) => $route->getName() === 'negotiated-orders.destroy'));
    }

    public function test_reception_is_prefilled_and_closes_order_only_after_successful_save(): void
    {
        $admin = $this->userWithRole('admin');
        $order = $this->createOrder($admin);
        $line = $order->lines()->sole();

        $this->actingAs($admin)
            ->get(route('supplier-receptions.create', ['negotiated_order_id' => $order->id]))
            ->assertOk()
            ->assertSee($order->number)
            ->assertSee('name="negotiated_order_id" value="'.$order->id.'"', false)
            ->assertSee('value="'.$line->quantity.'"', false);

        $this->actingAs($admin)->post(route('supplier-receptions.store'), [
            'negotiated_order_id' => $order->id,
            'location_id' => $order->location_id,
            'supplier_id' => $order->supplier_id,
            'lines' => [[
                'catalog_item_id' => $line->catalog_item_id,
                'quantity' => 7,
                'unit_price' => 11,
                'currency' => 'RON',
            ]],
        ])->assertSessionHasErrors('document_type');

        $this->assertSame('created', $order->fresh()->status);
        $this->assertDatabaseCount('supplier_receptions', 0);
        $this->assertDatabaseCount('stock_levels', 0);

        $response = $this->actingAs($admin)->post(route('supplier-receptions.store'), [
            'negotiated_order_id' => $order->id,
            'location_id' => $order->location_id,
            'supplier_id' => $order->supplier_id,
            'document_type' => 'aviz',
            'document_number' => 'AV-CMD-1',
            'lines' => [[
                'catalog_item_id' => $line->catalog_item_id,
                'quantity' => 7,
                'unit_price' => 11,
                'currency' => 'RON',
            ]],
        ]);

        $reception = SupplierReception::sole();
        $response->assertRedirect(route('supplier-receptions.show', $reception));
        $this->assertSame($order->id, $reception->negotiated_order_id);
        $this->assertSame('closed', $order->fresh()->status);
        $this->assertSame('reception', $order->fresh()->closure_type);
        $this->assertSame($admin->id, $order->fresh()->closed_by);
        $this->assertSame(7.0, (float) StockLevel::sole()->quantity);
        $this->actingAs($admin)
            ->get(route('supplier-receptions.create', ['negotiated_order_id' => $order->id]))
            ->assertStatus(409);
    }

    public function test_order_list_can_be_filtered_by_state_supplier_location_and_material(): void
    {
        $admin = $this->userWithRole('admin');
        $order = $this->createOrder($admin);
        $otherLocation = Location::create([
            'type' => 'base',
            'code' => 'B-OTHER',
            'name' => 'Altă bază',
            'active' => true,
        ]);
        $otherItem = CatalogItem::create([
            'category' => 'material',
            'tracking_type' => 'quantity',
            'sku' => 'MAT-OTHER',
            'name' => 'Material fără rezultat',
            'unit' => 'buc',
            'active' => true,
        ]);
        NegotiatedOrder::create([
            'number' => 'CMD-2026-99999',
            'status' => 'closed',
            'location_id' => $otherLocation->id,
            'created_by' => $admin->id,
            'currency' => 'RON',
            'closure_type' => 'cancelled',
            'closed_by' => $admin->id,
            'closed_at' => now(),
        ])->lines()->create([
            'catalog_item_id' => $otherItem->id,
            'quantity' => 1,
            'unit' => 'buc',
            'unit_price' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('negotiated-orders.index', [
                'status' => 'created',
                'location_id' => $order->location_id,
                'supplier_id' => $order->supplier_id,
                'search' => $order->lines()->first()->catalogItem->name,
            ]))
            ->assertOk()
            ->assertSee($order->number)
            ->assertDontSee('CMD-2026-99999');
    }

    private function createOrder(User $admin): NegotiatedOrder
    {
        $payload = $this->payload();
        $this->actingAs($admin)->post(route('negotiated-orders.store'), $payload)->assertRedirect();

        return NegotiatedOrder::with('lines')->latest('id')->firstOrFail();
    }

    private function payload(): array
    {
        $location = $this->location();
        $supplier = $this->supplier();
        [$item] = $this->items();

        return [
            'location_id' => $location->id,
            'supplier_id' => $supplier->id,
            'currency' => 'RON',
            'notes' => 'Comandă test',
            'lines' => [[
                'catalog_item_id' => $item->id,
                'quantity' => 5,
                'unit_price' => 10.5,
                'notes' => 'Ambalare standard',
            ]],
        ];
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function location(): Location
    {
        return Location::create([
            'type' => 'site',
            'code' => 'S-'.strtoupper(fake()->unique()->bothify('###??')),
            'name' => fake()->unique()->company(),
            'active' => true,
        ]);
    }

    private function supplier(): Supplier
    {
        return Supplier::create([
            'name' => fake()->unique()->company(),
            'active' => true,
        ]);
    }

    private function items(): array
    {
        return [
            CatalogItem::create([
                'category' => 'material',
                'tracking_type' => 'quantity',
                'sku' => fake()->unique()->bothify('MAT-####??'),
                'name' => fake()->unique()->words(3, true),
                'unit' => 'buc',
                'active' => true,
            ]),
            CatalogItem::create([
                'category' => 'material',
                'tracking_type' => 'quantity',
                'sku' => fake()->unique()->bothify('MAT-####??'),
                'name' => fake()->unique()->words(3, true),
                'unit' => 'kg',
                'active' => true,
            ]),
        ];
    }
}
