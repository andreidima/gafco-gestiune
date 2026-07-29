<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\Location;
use App\Models\NegotiatedOrder;
use App\Models\Supplier;
use App\Models\SupplierReception;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);
    }

    public function test_agreed_roles_can_manage_suppliers_and_operational_roles_have_read_only_access(): void
    {
        $supplier = Supplier::create(['name' => 'Furnizor roluri', 'active' => true]);

        foreach (['super-admin', 'admin', 'dispecer', 'contabil'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('suppliers.index'))->assertOk();
            $this->actingAs($user)->get(route('suppliers.create'))->assertOk();
            $this->actingAs($user)->get(route('suppliers.edit', $supplier))->assertOk();
        }

        foreach (['manager', 'sef-santier', 'gestionar-baza'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('suppliers.index'))->assertOk();
            $this->actingAs($user)->get(route('suppliers.create'))->assertForbidden();
            $this->actingAs($user)->get(route('suppliers.edit', $supplier))->assertForbidden();
        }

        $driver = $this->userWithRole('sofer');
        $this->actingAs($driver)->get(route('suppliers.index'))->assertForbidden();
    }

    public function test_supplier_can_be_created_and_updated_with_complete_contact_details(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post(route('suppliers.store'), [
            'name' => 'Materiale Nord SRL',
            'cui' => 'RO 12.345.678',
            'registration_number' => 'J07/123/2024',
            'address' => 'Strada Principală 10, Botoșani',
            'contact_person' => 'Maria Popescu',
            'email' => 'contact@example.com',
            'phone' => '0740 000 000',
            'notes' => 'Livrează dimineața.',
        ])->assertRedirect(route('suppliers.index'));

        $supplier = Supplier::sole();
        $this->assertSame('RO12345678', $supplier->cui);
        $this->assertSame('12345678', $supplier->normalized_cui);
        $this->assertTrue($supplier->active);
        $this->assertSame('Maria Popescu', $supplier->contact_person);

        $this->actingAs($admin)->put(route('suppliers.update', $supplier), [
            'name' => 'Materiale Nord Actualizat SRL',
            'cui' => '12345678',
            'registration_number' => 'J07/123/2024',
            'address' => 'Adresă actualizată',
            'contact_person' => 'Ion Popescu',
            'email' => 'office@example.com',
            'phone' => '0750 000 000',
            'notes' => 'Contact actualizat.',
        ])->assertRedirect(route('suppliers.index'));

        $supplier->refresh();
        $this->assertSame('Materiale Nord Actualizat SRL', $supplier->name);
        $this->assertSame('12345678', $supplier->cui);
        $this->assertSame('12345678', $supplier->normalized_cui);
        $this->assertSame('Ion Popescu', $supplier->contact_person);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Supplier::class,
            'subject_id' => $supplier->id,
            'description' => 'Furnizor actualizat',
        ]);
    }

    public function test_duplicate_cui_is_rejected_after_normalization(): void
    {
        $admin = $this->userWithRole('admin');
        Supplier::create([
            'name' => 'Primul furnizor',
            'cui' => 'RO12345678',
            'normalized_cui' => '12345678',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('suppliers.create'))
            ->post(route('suppliers.store'), [
                'name' => 'Furnizor duplicat',
                'cui' => '12 345 678',
            ])
            ->assertRedirect(route('suppliers.create'))
            ->assertSessionHasErrors('cui');

        $this->assertDatabaseCount('suppliers', 1);
    }

    public function test_deactivation_is_blocked_with_an_explanation_while_an_order_is_open(): void
    {
        $admin = $this->userWithRole('admin');
        $supplier = Supplier::create(['name' => 'Furnizor cu comandă', 'active' => true]);
        $order = $this->openOrder($admin, $supplier);

        $this->actingAs($admin)
            ->post(route('suppliers.deactivate', $supplier))
            ->assertRedirect(route('suppliers.edit', $supplier))
            ->assertSessionHasErrors([
                'active' => 'Furnizorul nu poate fi dezactivat deoarece are o comandă negociată deschisă. Închide sau anulează comenzile înainte să dezactivezi furnizorul.',
            ]);

        $this->assertTrue($supplier->fresh()->active);
        $this->actingAs($admin)
            ->get(route('suppliers.edit', $supplier))
            ->assertOk()
            ->assertSee('Vezi comenzile deschise')
            ->assertSee(route('negotiated-orders.index', [
                'supplier_id' => $supplier->id,
                'status' => 'created',
            ]));

        $order->update(['status' => NegotiatedOrder::STATUS_CLOSED]);
        $this->actingAs($admin)
            ->post(route('suppliers.deactivate', $supplier))
            ->assertRedirect(route('suppliers.index'));

        $this->assertFalse($supplier->fresh()->active);
    }

    public function test_inactive_supplier_is_preserved_in_history_but_excluded_from_new_documents(): void
    {
        $admin = $this->userWithRole('admin');
        $supplier = Supplier::create(['name' => 'Furnizor istoric', 'active' => false]);
        $location = Location::create([
            'type' => 'base',
            'code' => 'BASE-SUP',
            'name' => 'Bază furnizori',
            'active' => true,
        ]);
        $reception = SupplierReception::create([
            'number' => 'RF-SUP-001',
            'location_id' => $location->id,
            'supplier_id' => $supplier->id,
            'received_by' => $admin->id,
            'document_type' => 'aviz',
            'status' => 'posted',
            'received_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('supplier-receptions.index'))
            ->assertOk()
            ->assertSee('Furnizor istoric')
            ->assertSee('Furnizor istoric (inactiv)');

        $this->actingAs($admin)
            ->get(route('supplier-receptions.create'))
            ->assertOk()
            ->assertDontSee('Furnizor istoric');

        $this->actingAs($admin)
            ->get(route('supplier-receptions.show', $reception))
            ->assertOk()
            ->assertSee('Furnizor istoric');

        $this->actingAs($admin)
            ->get(route('negotiated-orders.create'))
            ->assertOk()
            ->assertDontSee('Furnizor istoric');
    }

    public function test_supplier_schema_help_article_and_release_note_are_reversible(): void
    {
        $this->assertTrue(Schema::hasColumn('suppliers', 'normalized_cui'));
        $this->assertTrue(Schema::hasColumn('suppliers', 'registration_number'));
        $this->assertTrue(Schema::hasColumn('suppliers', 'address'));
        $this->assertTrue(Schema::hasColumn('suppliers', 'contact_person'));
        $this->assertTrue(Schema::hasColumn('suppliers', 'notes'));
        $this->assertDatabaseHas('permissions', ['name' => 'suppliers.manage', 'guard_name' => 'web']);
        $this->assertDatabaseHas('help_articles', [
            'slug' => 'administrarea-furnizorilor',
            'current_revision' => 1,
            'status' => 'published',
        ]);
        $this->assertDatabaseHas('release_notes', [
            'slug' => '2026-07-29-administrarea-furnizorilor',
            'version' => '2026.07.29.11',
            'status' => 'published',
        ]);

        $contentMigration = require database_path('migrations/2026_07_29_000022_publish_supplier_management_content.php');
        $schemaMigration = require database_path('migrations/2026_07_29_000021_expand_supplier_management.php');

        DB::connection()->pretend(fn () => $contentMigration->up());
        $contentMigration->down();
        $schemaMigration->down();

        $this->assertFalse(Schema::hasColumn('suppliers', 'normalized_cui'));
        $this->assertDatabaseMissing('help_articles', ['slug' => 'administrarea-furnizorilor']);
        $this->assertDatabaseMissing('release_notes', ['slug' => '2026-07-29-administrarea-furnizorilor']);

        $schemaMigration->up();
        $contentMigration->up();

        $this->assertTrue(Schema::hasColumn('suppliers', 'normalized_cui'));
        $this->assertDatabaseHas('help_articles', ['slug' => 'administrarea-furnizorilor']);
        $this->assertDatabaseHas('release_notes', ['slug' => '2026-07-29-administrarea-furnizorilor']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role));

        return $user;
    }

    private function openOrder(User $creator, Supplier $supplier): NegotiatedOrder
    {
        $location = Location::create([
            'type' => 'base',
            'code' => 'BASE-ORDER-SUP',
            'name' => 'Bază comandă',
            'active' => true,
        ]);
        $item = CatalogItem::create([
            'category' => 'material',
            'tracking_type' => 'quantity',
            'sku' => 'MAT-SUP-001',
            'name' => 'Material furnizor',
            'unit' => 'buc',
            'active' => true,
        ]);
        $order = NegotiatedOrder::create([
            'number' => 'CMD-SUP-001',
            'status' => NegotiatedOrder::STATUS_CREATED,
            'location_id' => $location->id,
            'supplier_id' => $supplier->id,
            'created_by' => $creator->id,
            'currency' => 'RON',
        ]);
        $order->lines()->create([
            'catalog_item_id' => $item->id,
            'quantity' => 1,
            'unit' => 'buc',
            'unit_price' => 10,
        ]);

        return $order;
    }
}
