<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\InventoryLot;
use App\Models\InventoryLotBalance;
use App\Models\Location;
use App\Models\ReceptionDocument;
use App\Models\ReceptionIntake;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierReception;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReceptionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_worker_can_upload_private_documents_without_changing_stock(): void
    {
        Storage::fake('local');
        $worker = $this->userWithRole('muncitor');
        $otherWorker = $this->userWithRole('muncitor');
        $manager = $this->userWithRole('sef-santier');
        $location = $this->location('S-DOC');
        $this->assignLocation($manager, $location);

        $response = $this->actingAs($worker)->post(route('reception-intakes.store'), [
            'location_id' => $location->id,
            'notes' => 'Livrare la poartă',
            'attachments' => [[
                'type' => 'goods_photo',
                'file' => UploadedFile::fake()->image('marfa.jpg'),
            ]],
        ]);

        $intake = ReceptionIntake::query()->sole();
        $document = ReceptionDocument::query()->sole();
        $response->assertRedirect(route('reception-intakes.show', $intake));
        $this->assertSame('created', $intake->status);
        $this->assertSame($worker->id, $intake->submitted_by);
        $this->assertDatabaseCount('stock_levels', 0);
        $this->assertDatabaseCount('inventory_lots', 0);
        Storage::disk('local')->assertExists($document->stored_path);

        $download = $this->actingAs($worker)->get(route('reception-documents.download', $document))->assertOk();
        $this->assertStringContainsString('attachment', (string) $download->headers->get('content-disposition'));

        $preview = $this->actingAs($worker)->get(route('reception-documents.preview', $document))
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg')
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('x-frame-options', 'SAMEORIGIN');
        $this->assertStringContainsString('inline', (string) $preview->headers->get('content-disposition'));

        $this->actingAs($worker)
            ->get(route('reception-intakes.show', $intake))
            ->assertOk()
            ->assertSee(route('reception-documents.preview', $document), false)
            ->assertSee(route('reception-documents.download', $document), false)
            ->assertSee('Deschide previzualizarea');

        $this->actingAs($manager)->get(route('reception-documents.download', $document))->assertOk();
        $this->actingAs($manager)
            ->get(route('supplier-receptions.create', ['intake_id' => $intake->id]))
            ->assertOk()
            ->assertSee('supplier-reception-source-document-viewer')
            ->assertSee(route('reception-documents.preview', $document), false)
            ->assertSeeInOrder(
                ['data-reception-line-list', 'data-add-reception-line'],
                false,
            );
        $this->actingAs($otherWorker)->get(route('reception-intakes.show', $intake))->assertForbidden();
        $this->actingAs($otherWorker)->get(route('reception-documents.download', $document))->assertForbidden();
        $this->actingAs($otherWorker)->get(route('reception-documents.preview', $document))->assertForbidden();
    }

    public function test_browser_unsupported_document_keeps_download_without_inline_preview(): void
    {
        Storage::fake('local');
        $worker = $this->userWithRole('muncitor');
        $location = $this->location('S-HEIC');
        $intake = ReceptionIntake::create([
            'number' => 'DR-HEIC',
            'location_id' => $location->id,
            'submitted_by' => $worker->id,
            'status' => 'created',
        ]);
        $document = ReceptionDocument::create([
            'reception_intake_id' => $intake->id,
            'uploaded_by' => $worker->id,
            'document_type' => 'goods_photo',
            'original_name' => 'fotografie.heic',
            'stored_path' => 'reception-documents/fotografie.heic',
            'mime_type' => 'image/heic',
            'size_bytes' => 12,
            'sha256' => hash('sha256', 'heic-content'),
        ]);
        Storage::disk('local')->put($document->stored_path, 'heic-content');

        $this->actingAs($worker)
            ->get(route('reception-documents.preview', $document))
            ->assertStatus(415);

        $this->actingAs($worker)
            ->get(route('reception-intakes.show', $intake))
            ->assertOk()
            ->assertSee('Previzualizare indisponibilă')
            ->assertSee(route('reception-documents.download', $document), false)
            ->assertDontSee(route('reception-documents.preview', $document), false);
    }

    public function test_process_document_list_shows_completed_observations_and_stacks_the_desktop_date(): void
    {
        $admin = $this->userWithRole('admin');
        $location = $this->location('B-LISTA', 'base');

        ReceptionIntake::create([
            'number' => 'DR-FARA-OBS',
            'location_id' => $location->id,
            'submitted_by' => $admin->id,
            'status' => 'created',
            'notes' => null,
        ]);
        $withNotes = ReceptionIntake::create([
            'number' => 'DR-CU-OBS',
            'location_id' => $location->id,
            'submitted_by' => $admin->id,
            'status' => 'created',
            'notes' => 'Descărcare prin poarta laterală.',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('reception-intakes.index'))
            ->assertOk()
            ->assertSeeInOrder(
                ['<th>Locație</th>', '<th>Observații</th>', '<th>Trimis de</th>'],
                false,
            )
            ->assertSee('class="resource-cell-stack"', false)
            ->assertSeeText($withNotes->notes);

        $this->assertSame(2, substr_count($response->getContent(), '>Observații<'));
    }

    public function test_custom_document_type_requires_a_label(): void
    {
        Storage::fake('local');
        $worker = $this->userWithRole('muncitor');
        $location = $this->location('S-CUSTOM');

        $this->actingAs($worker)->post(route('reception-intakes.store'), [
            'location_id' => $location->id,
            'attachments' => [[
                'type' => 'custom',
                'custom_label' => '',
                'file' => UploadedFile::fake()->create('document.pdf', 20, 'application/pdf'),
            ]],
        ])->assertSessionHasErrors('attachments.0.custom_label');

        $this->assertDatabaseCount('reception_intakes', 0);
        $this->assertDatabaseCount('reception_documents', 0);
    }

    public function test_assigned_manager_converts_an_intake_into_a_multiline_reception(): void
    {
        Storage::fake('local');
        $worker = $this->userWithRole('muncitor');
        $manager = $this->userWithRole('sef-santier');
        $location = $this->location('S-RECEPTION');
        $this->assignLocation($manager, $location);
        $supplier = Supplier::create(['name' => 'Furnizor loturi', 'active' => true]);
        $firstItem = $this->item('MAT-R1', 'Material recepționat 1');
        $secondItem = $this->item('MAT-R2', 'Material recepționat 2');

        $this->actingAs($worker)->post(route('reception-intakes.store'), [
            'location_id' => $location->id,
            'attachments' => [[
                'type' => 'delivery_note',
                'file' => UploadedFile::fake()->image('aviz.jpg'),
            ]],
        ])->assertRedirect();
        $intake = ReceptionIntake::query()->sole();

        $response = $this->actingAs($manager)->post(route('supplier-receptions.store'), [
            'intake_id' => $intake->id,
            'location_id' => $location->id,
            'supplier_id' => $supplier->id,
            'document_type' => 'aviz',
            'document_number' => 'AV-2026-17',
            'notes' => 'Recepție verificată',
            'lines' => [
                [
                    'catalog_item_id' => $firstItem->id,
                    'quantity' => 7.5,
                    'lot_code' => 'LOT-A',
                    'expires_at' => '2027-05-10',
                    'unit_price' => 12.3456,
                    'currency' => 'RON',
                    'notes' => 'Palet intact',
                ],
                [
                    'catalog_item_id' => $secondItem->id,
                    'quantity' => 3,
                    'lot_code' => 'LOT-B',
                    'expires_at' => null,
                    'unit_price' => 8.5,
                    'currency' => 'EUR',
                    'notes' => null,
                ],
            ],
        ]);

        $reception = SupplierReception::query()->with('lines.inventoryLot')->sole();
        $response->assertRedirect(route('supplier-receptions.show', $reception));
        $this->assertCount(2, $reception->lines);
        $this->assertSame('closed', $intake->fresh()->status);
        $this->assertSame('converted', $intake->fresh()->closure_type);
        $this->assertSame($reception->id, $intake->fresh()->supplier_reception_id);
        $this->assertSame($reception->id, ReceptionDocument::query()->sole()->supplier_reception_id);
        $this->assertSame(10.5, (float) StockLevel::where('location_id', $location->id)->sum('quantity'));
        $this->assertDatabaseHas('inventory_lots', [
            'catalog_item_id' => $firstItem->id,
            'supplier_id' => $supplier->id,
            'lot_code' => 'LOT-A',
            'unit_price' => 12.3456,
            'currency' => 'RON',
        ]);
        $this->assertDatabaseHas('inventory_lots', [
            'catalog_item_id' => $secondItem->id,
            'supplier_id' => $supplier->id,
            'lot_code' => 'LOT-B',
            'unit_price' => 8.5,
            'currency' => 'EUR',
        ]);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_reception_metadata_permissions_do_not_allow_quantity_or_location_changes(): void
    {
        $manager = $this->userWithRole('sef-santier');
        $keeper = $this->userWithRole('gestionar-baza');
        $admin = $this->userWithRole('admin');
        $accountant = $this->userWithRole('contabil');
        $location = $this->location('B-EDIT', 'base');
        $otherLocation = $this->location('B-OTHER', 'base');
        $this->assignLocation($manager, $location);
        $this->assignLocation($keeper, $location);
        $item = $this->item('MAT-EDIT', 'Material editabil');
        $supplier = Supplier::create(['name' => 'Furnizor inițial', 'active' => true]);
        $otherSupplier = Supplier::create(['name' => 'Furnizor corectat', 'active' => true]);

        $this->actingAs($manager)->post(route('supplier-receptions.store'), [
            'location_id' => $location->id,
            'supplier_id' => $supplier->id,
            'document_type' => 'aviz',
            'document_number' => 'AV-OLD',
            'lines' => [[
                'catalog_item_id' => $item->id,
                'quantity' => 9,
                'unit_price' => 51.25,
                'currency' => 'RON',
            ]],
        ])->assertRedirect();
        $reception = SupplierReception::query()->with('lines.inventoryLot')->sole();
        $line = $reception->lines->sole();

        $this->actingAs($accountant)->get(route('supplier-receptions.edit', $reception))->assertForbidden();
        $this->actingAs($keeper)->get(route('supplier-receptions.edit', $reception))
            ->assertOk()
            ->assertDontSee('51.2500');

        $this->actingAs($keeper)->put(route('supplier-receptions.update', $reception), [
            'supplier_id' => $otherSupplier->id,
            'location_id' => $otherLocation->id,
            'lines' => [[
                'id' => $line->id,
                'expires_at' => '2027-11-30',
                'unit_price' => 999,
                'quantity' => 1,
            ]],
        ])->assertRedirect(route('supplier-receptions.show', $reception));

        $line->refresh();
        $this->assertSame('2027-11-30', $line->expires_at->format('Y-m-d'));
        $this->assertSame('51.2500', $line->unit_price);
        $this->assertSame(9.0, (float) $line->quantity);
        $this->assertSame($supplier->id, $reception->fresh()->supplier_id);
        $this->assertSame($location->id, $reception->fresh()->location_id);

        $this->actingAs($admin)->put(route('supplier-receptions.update', $reception), [
            'supplier_id' => $otherSupplier->id,
            'document_type' => 'factura',
            'document_number' => 'FACT-NEW',
            'notes' => 'Detalii corectate',
            'location_id' => $otherLocation->id,
            'lines' => [[
                'id' => $line->id,
                'lot_code' => 'LOT-EDIT',
                'expires_at' => '2028-01-15',
                'unit_price' => 23.75,
                'currency' => 'EUR',
                'notes' => 'Linie corectată',
                'quantity' => 2,
            ]],
        ])->assertRedirect(route('supplier-receptions.show', $reception));

        $line->refresh();
        $lot = $line->inventoryLot->fresh();
        $this->assertSame(9.0, (float) $line->quantity);
        $this->assertSame('LOT-EDIT', $line->lot_code);
        $this->assertSame('23.7500', $line->unit_price);
        $this->assertSame('FACT-NEW', $reception->fresh()->document_number);
        $this->assertSame($location->id, $reception->fresh()->location_id);
        $this->assertSame('LOT-EDIT', $lot->lot_code);
        $this->assertSame('23.7500', $lot->unit_price);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => SupplierReception::class,
            'subject_id' => $reception->id,
            'description' => 'Detalii recepție actualizate',
        ]);

        Permission::findOrCreate('accounting.edit-operations');
        $accountant->givePermissionTo('accounting.edit-operations');
        $this->actingAs($accountant)->get(route('supplier-receptions.edit', $reception))->assertOk();
    }

    public function test_fifo_fefo_proposal_can_be_adjusted_before_consumption(): void
    {
        $manager = $this->userWithRole('sef-santier');
        $location = $this->location('S-FEFO');
        $this->assignLocation($manager, $location);
        $item = $this->item('MAT-FEFO', 'Material FEFO');

        $this->createReception($manager, $location, $item, 5, 'LOT-LATE', '2028-12-31');
        $this->createReception($manager, $location, $item, 6, 'LOT-EARLY', '2027-01-31');

        $early = InventoryLot::where('lot_code', 'LOT-EARLY')->sole();
        $late = InventoryLot::where('lot_code', 'LOT-LATE')->sole();
        $this->actingAs($manager)
            ->getJson(route('consumption-reports.allocation-proposal', [
                'location_id' => $location->id,
                'catalog_item_id' => $item->id,
                'quantity' => 7,
            ]))
            ->assertOk()
            ->assertJsonPath('allocations.0.inventory_lot_id', $early->id)
            ->assertJsonPath('allocations.0.quantity', '6.000')
            ->assertJsonPath('allocations.1.inventory_lot_id', $late->id)
            ->assertJsonPath('allocations.1.quantity', '1.000');

        $this->actingAs($manager)->post(route('consumption-reports.store'), [
            'location_id' => $location->id,
            'catalog_item_id' => $item->id,
            'quantity' => 7,
            'allocations' => [
                ['inventory_lot_id' => $early->id, 'quantity' => 2],
                ['inventory_lot_id' => $late->id, 'quantity' => 5],
            ],
        ])->assertRedirect(route('consumption-reports.index'));

        $this->assertSame(4.0, (float) InventoryLotBalance::where('inventory_lot_id', $early->id)
            ->where('location_id', $location->id)
            ->value('quantity'));
        $this->assertSame(0.0, (float) InventoryLotBalance::where('inventory_lot_id', $late->id)
            ->where('location_id', $location->id)
            ->value('quantity'));
        $this->assertSame(4.0, (float) StockLevel::where('location_id', $location->id)
            ->where('catalog_item_id', $item->id)
            ->value('quantity'));
        $this->assertSame(
            [-5.0, -2.0],
            StockMovement::where('movement_type', 'consumption')
                ->orderBy('inventory_lot_id')
                ->pluck('quantity')
                ->map(fn ($quantity) => (float) $quantity)
                ->all(),
        );
    }

    public function test_invalid_custom_allocation_is_rejected_atomically(): void
    {
        $manager = $this->userWithRole('sef-santier');
        $location = $this->location('S-ALLOC');
        $this->assignLocation($manager, $location);
        $item = $this->item('MAT-ALLOC', 'Material alocare');
        $this->createReception($manager, $location, $item, 5, 'LOT-ONE', '2027-05-01');
        $lot = InventoryLot::query()->sole();

        $this->actingAs($manager)->post(route('consumption-reports.store'), [
            'location_id' => $location->id,
            'catalog_item_id' => $item->id,
            'quantity' => 4,
            'allocations' => [
                ['inventory_lot_id' => $lot->id, 'quantity' => 3],
            ],
        ])->assertSessionHasErrors('allocations');

        $this->assertDatabaseCount('consumption_reports', 0);
        $this->assertSame(5.0, (float) StockLevel::query()->value('quantity'));
        $this->assertSame(5.0, (float) InventoryLotBalance::query()->value('quantity'));
    }

    private function createReception(
        User $manager,
        Location $location,
        CatalogItem $item,
        float $quantity,
        string $lotCode,
        string $expiresAt,
    ): void {
        $this->actingAs($manager)->post(route('supplier-receptions.store'), [
            'location_id' => $location->id,
            'document_type' => 'aviz',
            'document_number' => 'DOC-'.$lotCode,
            'lines' => [[
                'catalog_item_id' => $item->id,
                'quantity' => $quantity,
                'lot_code' => $lotCode,
                'expires_at' => $expiresAt,
                'currency' => 'RON',
            ]],
        ])->assertRedirect();
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role);
        $user = User::factory()->create();
        $user->assignRole($role);

        if (in_array($role, ['super-admin', 'admin', 'dispecer', 'gestionar-baza', 'sef-santier', 'muncitor'], true)) {
            $user->givePermissionTo(Permission::findOrCreate('reception-documents.upload'));
        }
        if (in_array($role, ['super-admin', 'admin'], true)) {
            $user->givePermissionTo(Permission::findOrCreate('reception-details.edit-all'));
        }
        if (in_array($role, ['super-admin', 'admin', 'gestionar-baza'], true)) {
            $user->givePermissionTo(Permission::findOrCreate('reception-details.edit-expiration'));
        }

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

    private function item(string $sku, string $name): CatalogItem
    {
        return CatalogItem::create([
            'category' => 'material',
            'tracking_type' => 'quantity',
            'sku' => $sku,
            'name' => $name,
            'unit' => 'buc',
            'active' => true,
        ]);
    }
}
