<?php

namespace Database\Seeders;

use App\Models\CatalogItem;
use App\Models\ConsumptionReport;
use App\Models\CustodyTransfer;
use App\Models\DriverRequest;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\SupplierReception;
use App\Models\TrackedAsset;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LogisticsSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Administrator', 'password' => Hash::make('password'), 'active' => true, 'email_verified_at' => now()]
        );
        $admin->syncRoles(['super-admin', 'admin']);

        $dispatcher = User::updateOrCreate(
            ['email' => 'dispecer@example.com'],
            ['name' => 'Dispecer GAFCO', 'password' => Hash::make('password'), 'active' => true, 'email_verified_at' => now()]
        );
        $dispatcher->syncRoles(['dispecer']);

        $driver = User::updateOrCreate(
            ['email' => 'sofer@example.com'],
            ['name' => 'Ion Sofer', 'password' => Hash::make('password'), 'active' => true, 'email_verified_at' => now()]
        );
        $driver->syncRoles(['sofer']);

        $manager = User::updateOrCreate(
            ['email' => 'santier@example.com'],
            ['name' => 'Sef Santier', 'password' => Hash::make('password'), 'active' => true, 'email_verified_at' => now()]
        );
        $manager->syncRoles(['sef-santier']);

        $base = Location::updateOrCreate(
            ['code' => 'B-BUC'],
            ['type' => 'base', 'name' => 'Baza Bucecea', 'address' => 'Bucecea', 'manager_user_id' => $dispatcher->id, 'active' => true]
        );
        $siteOne = Location::updateOrCreate(
            ['code' => 'S-001'],
            ['type' => 'site', 'name' => 'Santier Centura Nord', 'address' => 'Botosani', 'manager_user_id' => $manager->id, 'active' => true]
        );
        $siteTwo = Location::updateOrCreate(
            ['code' => 'S-002'],
            ['type' => 'site', 'name' => 'Santier Parc Industrial', 'address' => 'Suceava', 'active' => true]
        );
        $siteThree = Location::updateOrCreate(
            ['code' => 'S-003'],
            ['type' => 'site', 'name' => 'Santier Pod Vest', 'address' => 'Dorohoi', 'active' => true]
        );

        $extraManagers = collect(range(1, 5))->map(function (int $index) {
            $user = User::updateOrCreate(
                ['email' => "sef.santier{$index}@example.com"],
                ['name' => "Sef Santier {$index}", 'password' => Hash::make('password'), 'active' => true, 'email_verified_at' => now()]
            );
            $user->syncRoles(['sef-santier']);

            return $user;
        });

        $extraDrivers = collect(range(1, 6))->map(function (int $index) {
            $user = User::updateOrCreate(
                ['email' => "sofer{$index}@example.com"],
                ['name' => "Sofer {$index}", 'password' => Hash::make('password'), 'active' => true, 'email_verified_at' => now()]
            );
            $user->syncRoles(['sofer']);

            return $user;
        });

        $extraWorkers = collect(range(1, 12))->map(function (int $index) {
            $user = User::updateOrCreate(
                ['email' => "muncitor{$index}@example.com"],
                ['name' => "Muncitor {$index}", 'password' => Hash::make('password'), 'active' => true, 'email_verified_at' => now()]
            );
            $user->syncRoles(['muncitor']);

            return $user;
        });

        $extraLocations = collect(range(4, 15))->map(function (int $index) use ($extraManagers) {
            return Location::updateOrCreate(
                ['code' => 'S-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT)],
                [
                    'type' => 'site',
                    'name' => 'Santier Demo '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                    'address' => ['Iasi', 'Bacau', 'Neamt', 'Vaslui', 'Galati', 'Focsani'][$index % 6],
                    'manager_user_id' => $extraManagers[($index - 4) % $extraManagers->count()]->id,
                    'active' => true,
                ]
            );
        });

        $locations = collect([$base, $siteOne, $siteTwo, $siteThree])->merge($extraLocations)->values();
        $drivers = collect([$driver])->merge($extraDrivers)->values();
        $managers = collect([$manager])->merge($extraManagers)->values();
        $workers = $extraWorkers->values();

        $catalogSeeds = collect([
            ['sku' => 'MAT-CIM-25', 'category' => 'material', 'tracking_type' => 'quantity', 'name' => 'Ciment 25 kg', 'unit' => 'sac'],
            ['sku' => 'SCU-ROT-001', 'category' => 'tool', 'tracking_type' => 'serialized', 'name' => 'Rotopercutor Bosch', 'unit' => 'buc'],
            ['sku' => 'EQP-GEN-001', 'category' => 'equipment', 'tracking_type' => 'serialized', 'name' => 'Generator 5 kW', 'unit' => 'buc'],
            ['sku' => 'SCU-FLE-001', 'category' => 'tool', 'tracking_type' => 'serialized', 'name' => 'Flex Makita 125 mm', 'unit' => 'buc'],
            ['sku' => 'EQP-COM-001', 'category' => 'equipment', 'tracking_type' => 'serialized', 'name' => 'Compactor placi', 'unit' => 'buc'],
            ['sku' => 'MAT-FIER-12', 'category' => 'material', 'tracking_type' => 'quantity', 'name' => 'Fier beton 12 mm', 'unit' => 'kg'],
        ]);
        foreach (range(1, 24) as $index) {
            $catalogSeeds->push([
                'sku' => 'MAT-DEMO-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'category' => 'material',
                'tracking_type' => 'quantity',
                'name' => ['Balast', 'Nisip', 'Plasa sudata', 'Teava PVC', 'Caramida', 'Adeziv'][$index % 6].' demo '.$index,
                'unit' => ['mc', 'kg', 'buc', 'ml'][$index % 4],
            ]);
        }
        foreach (range(1, 36) as $index) {
            $catalogSeeds->push([
                'sku' => ($index % 2 === 0 ? 'EQP' : 'SCU').'-DEMO-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'category' => $index % 2 === 0 ? 'equipment' : 'tool',
                'tracking_type' => 'serialized',
                'name' => ['Masina gaurit', 'Pompa apa', 'Schela mobila', 'Aparat sudura', 'Laser nivel', 'Mai compactor'][$index % 6].' demo '.$index,
                'unit' => 'buc',
            ]);
        }

        $items = $catalogSeeds->map(fn ($item) => CatalogItem::updateOrCreate(['sku' => $item['sku']], $item + ['active' => true]));

        $supplier = Supplier::updateOrCreate(
            ['name' => 'Depozit Materiale Bucecea'],
            ['cui' => 'RO12345678', 'phone' => '0755000000', 'active' => true]
        );
        $suppliers = collect([$supplier])->merge(collect(range(1, 18))->map(function (int $index) {
            return Supplier::updateOrCreate(
                ['name' => 'Furnizor Demo '.str_pad((string) $index, 2, '0', STR_PAD_LEFT)],
                [
                    'cui' => 'RO'.str_pad((string) (12000000 + $index), 8, '0', STR_PAD_LEFT),
                    'email' => "furnizor{$index}@example.com",
                    'phone' => '0755'.str_pad((string) $index, 6, '0', STR_PAD_LEFT),
                    'active' => true,
                ]
            );
        }))->values();

        foreach ($locations as $location) {
            foreach ($items->where('tracking_type', 'quantity') as $item) {
                StockLevel::updateOrCreate(
                    ['location_id' => $location->id, 'catalog_item_id' => $item->id],
                    ['quantity' => $location->is($base) ? 120 + ($item->id % 80) : 5 + (($location->id + $item->id) % 45)]
                );
            }
        }

        $assets = collect([
            ['asset_code' => 'SCU-ROT-001-A1', 'sku' => 'SCU-ROT-001', 'location' => $base, 'custodian' => $dispatcher, 'status' => 'available', 'condition' => 'good'],
            ['asset_code' => 'SCU-ROT-001-A2', 'sku' => 'SCU-ROT-001', 'location' => $siteOne, 'custodian' => $manager, 'status' => 'in_use', 'condition' => 'used'],
            ['asset_code' => 'EQP-GEN-001-A1', 'sku' => 'EQP-GEN-001', 'location' => $siteTwo, 'custodian' => $manager, 'status' => 'in_transfer', 'condition' => 'good'],
            ['asset_code' => 'SCU-FLE-001-A1', 'sku' => 'SCU-FLE-001', 'location' => $siteThree, 'custodian' => $manager, 'status' => 'lost', 'condition' => 'used'],
            ['asset_code' => 'EQP-COM-001-A1', 'sku' => 'EQP-COM-001', 'location' => $base, 'custodian' => $dispatcher, 'status' => 'maintenance', 'condition' => 'needs_service'],
        ])->map(function ($assetData) use ($items) {
            $item = $items->firstWhere('sku', $assetData['sku']);

            return TrackedAsset::updateOrCreate(
                ['asset_code' => $assetData['asset_code']],
                [
                    'catalog_item_id' => $item->id,
                    'qr_code' => 'QR-'.$assetData['asset_code'],
                    'serial_number' => 'SN-'.$assetData['asset_code'],
                    'status' => $assetData['status'],
                    'condition' => $assetData['condition'],
                    'current_location_id' => $assetData['location']->id,
                    'current_custodian_id' => $assetData['custodian']->id,
                    'last_verified_at' => now()->subHours(rand(2, 72)),
                    'notes' => 'Asset demo cu QR.',
                ]
            );
        });

        $transfer = Transfer::updateOrCreate(
            ['number' => 'TR-DEMO-001'],
            [
                'type' => 'base_to_site',
                'status' => 'assigned',
                'source_location_id' => $base->id,
                'destination_location_id' => $siteOne->id,
                'requested_by' => $manager->id,
                'approved_by' => $manager->id,
                'driver_id' => $driver->id,
                'confirmed_by' => null,
                'document_number' => 'AVZ-TR-001',
                'requested_at' => now()->subDay(),
                'assigned_at' => now()->subHours(20),
                'approved_at' => now()->subHours(22),
                'notes' => 'Transfer initial pentru demo.',
            ]
        );
        $transfer->lines()->updateOrCreate(
            ['catalog_item_id' => $items->firstWhere('sku', 'MAT-CIM-25')->id],
            ['quantity' => 30, 'unit' => 'sac']
        );

        $assetTransfer = Transfer::updateOrCreate(
            ['number' => 'TR-DEMO-002'],
            [
                'type' => 'site_to_site',
                'status' => 'in_transit',
                'source_location_id' => $siteTwo->id,
                'destination_location_id' => $siteThree->id,
                'requested_by' => $manager->id,
                'approved_by' => $manager->id,
                'driver_id' => $driver->id,
                'document_number' => 'AVZ-TR-002',
                'requested_at' => now()->subHours(9),
                'approved_at' => now()->subHours(8),
                'assigned_at' => now()->subHours(7),
                'dispatched_at' => now()->subHours(3),
                'notes' => 'Generator in drum catre santier.',
            ]
        );
        $assetTransfer->lines()->updateOrCreate(
            ['tracked_asset_id' => $assets->firstWhere('asset_code', 'EQP-GEN-001-A1')->id],
            [
                'catalog_item_id' => $items->firstWhere('sku', 'EQP-GEN-001')->id,
                'quantity' => 1,
                'unit' => 'buc',
            ]
        );

        $receivedTransfer = Transfer::updateOrCreate(
            ['number' => 'TR-DEMO-003'],
            [
                'type' => 'base_to_site',
                'status' => 'received',
                'source_location_id' => $base->id,
                'destination_location_id' => $siteOne->id,
                'requested_by' => $manager->id,
                'approved_by' => $manager->id,
                'driver_id' => $driver->id,
                'confirmed_by' => $manager->id,
                'document_number' => 'AVZ-TR-003',
                'requested_at' => now()->subDays(3),
                'approved_at' => now()->subDays(3)->addHour(),
                'assigned_at' => now()->subDays(3)->addHours(2),
                'dispatched_at' => now()->subDays(3)->addHours(4),
                'received_at' => now()->subDays(2),
                'notes' => 'Rotopercutor primit si confirmat cu QR.',
            ]
        );
        $receivedTransfer->lines()->updateOrCreate(
            ['tracked_asset_id' => $assets->firstWhere('asset_code', 'SCU-ROT-001-A2')->id],
            [
                'catalog_item_id' => $items->firstWhere('sku', 'SCU-ROT-001')->id,
                'quantity' => 1,
                'unit' => 'buc',
                'received_status' => 'received',
            ]
        );

        DriverRequest::updateOrCreate(
            ['number' => 'DR-DEMO-001'],
            [
                'site_id' => $siteTwo->id,
                'requested_by' => $manager->id,
                'assigned_driver_id' => $driver->id,
                'status' => 'assigned',
                'needed_at' => now()->addHours(6),
                'pickup_address' => 'Baza Bucecea',
                'delivery_address' => 'Santier Parc Industrial',
                'assigned_at' => now(),
                'notes' => 'Transport materiale urgente.',
            ]
        );

        $reception = SupplierReception::updateOrCreate(
            ['number' => 'RF-DEMO-001'],
            [
                'location_id' => $base->id,
                'supplier_id' => $supplier->id,
                'received_by' => $dispatcher->id,
                'document_type' => 'aviz',
                'document_number' => 'AVZ-1001',
                'status' => 'posted',
                'received_at' => now()->subHours(4),
                'notes' => 'Marfa intrata cu aviz.',
            ]
        );
        $reception->lines()->updateOrCreate(
            ['catalog_item_id' => $items->firstWhere('sku', 'MAT-FIER-12')->id],
            ['quantity' => 500, 'unit' => 'kg']
        );

        $serializedItems = $items->where('tracking_type', 'serialized')->values();
        $quantityItems = $items->where('tracking_type', 'quantity')->values();
        $assetStatuses = ['available', 'in_use', 'in_use', 'in_use', 'in_transfer', 'maintenance', 'lost'];
        $conditions = ['good', 'good', 'used', 'used', 'damaged', 'needs_service'];

        foreach (range(1, 320) as $index) {
            $item = $serializedItems[$index % $serializedItems->count()];
            $location = $locations[$index % $locations->count()];
            $custodianPool = $index % 4 === 0 ? $workers : $managers;
            $custodian = $custodianPool[$index % $custodianPool->count()];
            $status = $assetStatuses[$index % count($assetStatuses)];

            TrackedAsset::updateOrCreate(
                ['asset_code' => 'AST-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT)],
                [
                    'catalog_item_id' => $item->id,
                    'qr_code' => 'QR-AST-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                    'serial_number' => 'SN-DEMO-'.str_pad((string) $index, 5, '0', STR_PAD_LEFT),
                    'status' => $status,
                    'condition' => $conditions[$index % count($conditions)],
                    'current_location_id' => $location->id,
                    'current_custodian_id' => $custodian->id,
                    'last_verified_at' => now()->subHours(($index % 240) + 1),
                    'notes' => 'Inregistrare demo generata pentru prezentare.',
                ]
            );
        }

        $allAssets = TrackedAsset::with('catalogItem')->orderBy('id')->get();
        $transferStatuses = ['pending_approval', 'approved', 'assigned', 'in_transit', 'received', 'cancelled'];
        foreach (range(1, 260) as $index) {
            $source = $locations[$index % $locations->count()];
            $destination = $locations[($index + 3) % $locations->count()];
            if ($source->id === $destination->id) {
                $destination = $locations[($index + 4) % $locations->count()];
            }

            $status = $transferStatuses[$index % count($transferStatuses)];
            $requestedAt = now()->subDays($index % 90)->subHours($index % 12);
            $driverForTransfer = $drivers[$index % $drivers->count()];
            $managerForTransfer = $managers[$index % $managers->count()];

            $transfer = Transfer::updateOrCreate(
                ['number' => 'TR-BULK-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT)],
                [
                    'type' => ['base_to_site', 'site_to_site', 'site_to_base'][$index % 3],
                    'status' => $status,
                    'source_location_id' => $source->id,
                    'destination_location_id' => $destination->id,
                    'requested_by' => $managerForTransfer->id,
                    'approved_by' => in_array($status, ['approved', 'assigned', 'in_transit', 'received'], true) ? $managerForTransfer->id : null,
                    'driver_id' => in_array($status, ['assigned', 'in_transit', 'received'], true) ? $driverForTransfer->id : null,
                    'confirmed_by' => $status === 'received' ? $managerForTransfer->id : null,
                    'document_number' => 'AVZ-BULK-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                    'requested_at' => $requestedAt,
                    'approved_at' => in_array($status, ['approved', 'assigned', 'in_transit', 'received'], true) ? $requestedAt->copy()->addHours(2) : null,
                    'assigned_at' => in_array($status, ['assigned', 'in_transit', 'received'], true) ? $requestedAt->copy()->addHours(4) : null,
                    'dispatched_at' => in_array($status, ['in_transit', 'received'], true) ? $requestedAt->copy()->addHours(8) : null,
                    'received_at' => $status === 'received' ? $requestedAt->copy()->addDay() : null,
                    'received_with_discrepancy' => $status === 'received' && $index % 17 === 0,
                    'discrepancy_notes' => $status === 'received' && $index % 17 === 0 ? 'Diferenta demo constatata la primire.' : null,
                    'notes' => 'Transfer demo generat pentru volum de date.',
                ]
            );

            if ($index % 3 === 0) {
                $asset = $allAssets[$index % $allAssets->count()];
                $transfer->lines()->updateOrCreate(
                    ['tracked_asset_id' => $asset->id],
                    [
                        'catalog_item_id' => $asset->catalog_item_id,
                        'quantity' => 1,
                        'unit' => $asset->catalogItem?->unit ?? 'buc',
                        'received_status' => $status === 'received' && $index % 17 !== 0 ? 'received' : 'pending',
                    ]
                );
            } else {
                $item = $quantityItems[$index % $quantityItems->count()];
                $transfer->lines()->updateOrCreate(
                    ['catalog_item_id' => $item->id],
                    [
                        'quantity' => 1 + ($index % 80),
                        'unit' => $item->unit,
                        'received_status' => $status === 'received' && $index % 17 !== 0 ? 'received' : 'pending',
                    ]
                );
            }
        }

        $driverStatuses = ['open', 'assigned', 'in_progress', 'closed', 'cancelled'];
        foreach (range(1, 180) as $index) {
            $site = $locations->where('type', 'site')->values()[$index % $locations->where('type', 'site')->count()];
            $status = $driverStatuses[$index % count($driverStatuses)];
            $neededAt = now()->subDays($index % 45)->addHours($index % 18);

            DriverRequest::updateOrCreate(
                ['number' => 'DR-BULK-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT)],
                [
                    'site_id' => $site->id,
                    'requested_by' => $managers[$index % $managers->count()]->id,
                    'assigned_driver_id' => in_array($status, ['assigned', 'in_progress', 'closed'], true) ? $drivers[$index % $drivers->count()]->id : null,
                    'status' => $status,
                    'needed_at' => $neededAt,
                    'pickup_address' => $index % 2 === 0 ? 'Baza Bucecea' : 'Depozit furnizor '.$index,
                    'delivery_address' => $site->name,
                    'assigned_at' => in_array($status, ['assigned', 'in_progress', 'closed'], true) ? $neededAt->copy()->subHours(2) : null,
                    'closed_at' => $status === 'closed' ? $neededAt->copy()->addHours(5) : null,
                    'notes' => 'Cerere sofer demo generata.',
                ]
            );
        }

        foreach (range(1, 170) as $index) {
            $location = $locations[$index % $locations->count()];
            $supplierForReception = $suppliers[$index % $suppliers->count()];
            $item = $quantityItems[$index % $quantityItems->count()];
            $receivedAt = now()->subDays($index % 120)->subHours($index % 10);

            $bulkReception = SupplierReception::updateOrCreate(
                ['number' => 'RF-BULK-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT)],
                [
                    'location_id' => $location->id,
                    'supplier_id' => $supplierForReception->id,
                    'received_by' => $dispatcher->id,
                    'document_type' => $index % 4 === 0 ? 'factura' : 'aviz',
                    'document_number' => 'DOC-RF-'.str_pad((string) $index, 5, '0', STR_PAD_LEFT),
                    'status' => 'posted',
                    'received_at' => $receivedAt,
                    'notes' => 'Receptie demo generata pentru volum de date.',
                ]
            );
            $bulkReception->lines()->updateOrCreate(
                ['catalog_item_id' => $item->id],
                [
                    'quantity' => 10 + ($index % 300),
                    'unit' => $item->unit,
                ]
            );
        }

        foreach (range(1, 140) as $index) {
            $site = $locations->where('type', 'site')->values()[$index % $locations->where('type', 'site')->count()];
            $item = $quantityItems[$index % $quantityItems->count()];
            $quantity = 1 + ($index % 35);
            $reportedAt = now()->subDays($index % 80)->subHours($index % 9);

            $consumption = ConsumptionReport::updateOrCreate(
                ['number' => 'CS-BULK-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT)],
                [
                    'location_id' => $site->id,
                    'reported_by' => $managers[$index % $managers->count()]->id,
                    'status' => 'posted',
                    'reported_at' => $reportedAt,
                    'notes' => 'Consum demo raportat din santier.',
                ]
            );
            $consumption->lines()->updateOrCreate(
                ['catalog_item_id' => $item->id],
                [
                    'quantity' => $quantity,
                    'unit' => $item->unit,
                    'notes' => 'Consum demo pe lucrare.',
                ]
            );

            $stock = StockLevel::firstOrCreate(
                ['location_id' => $site->id, 'catalog_item_id' => $item->id],
                ['quantity' => 0]
            );
            $stock->update(['quantity' => max(0, (float) $stock->quantity - $quantity)]);
        }

        foreach (range(1, 80) as $index) {
            $asset = $allAssets[$index % $allAssets->count()];
            $fromUser = $workers[$index % $workers->count()];
            $toUser = $workers[($index + 3) % $workers->count()];
            $status = $index % 4 === 0 ? 'pending' : 'accepted';

            $custodyTransfer = CustodyTransfer::updateOrCreate(
                ['qr_token' => 'CUST-DEMO-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT)],
                [
                    'tracked_asset_id' => $asset->id,
                    'from_user_id' => $fromUser->id,
                    'to_user_id' => $toUser->id,
                    'status' => $status,
                    'expires_at' => now()->addDay(),
                    'accepted_at' => $status === 'accepted' ? now()->subDays($index % 20) : null,
                    'notes' => 'Predare demo intre muncitori.',
                ]
            );

            if ($custodyTransfer->status === 'accepted') {
                $asset->update([
                    'current_custodian_id' => $toUser->id,
                    'status' => 'in_use',
                    'last_verified_at' => $custodyTransfer->accepted_at,
                ]);
            }
        }
    }
}
