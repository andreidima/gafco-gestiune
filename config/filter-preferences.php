<?php

return [
    'routes' => [
        'users.index' => [
            'role' => [
                'type' => 'enum',
                'values' => ['admin', 'dispecer', 'manager', 'gestionar-baza', 'sef-santier', 'sofer', 'muncitor', 'contabil', 'user'],
            ],
            'active' => ['type' => 'enum', 'values' => ['0', '1']],
        ],
        'locations.index' => [
            'type' => ['type' => 'enum', 'values' => ['base', 'site']],
            'active' => ['type' => 'enum', 'values' => ['0', '1']],
        ],
        'catalog-items.index' => [
            'category' => ['type' => 'enum', 'values' => ['material', 'equipment', 'tool']],
            'tracking_type' => ['type' => 'enum', 'values' => ['quantity', 'serialized']],
            'active' => ['type' => 'enum', 'values' => ['0', '1']],
        ],
        'tracked-assets.index' => [
            'location_id' => ['type' => 'visible_location'],
            'status' => ['type' => 'enum', 'values' => ['available', 'in_use', 'in_transfer', 'maintenance', 'lost']],
            'condition' => ['type' => 'enum', 'values' => ['good', 'used', 'damaged', 'needs_service']],
        ],
        'inventory.index' => [
            'location_id' => ['type' => 'visible_location'],
            'hide_zero' => ['type' => 'boolean'],
        ],
        'inventory.show' => [
            'location_id' => ['type' => 'visible_location'],
        ],
        'alerts.index' => [
            'alert_type' => ['type' => 'enum', 'values' => ['lot_expiration', 'reception_pending', 'project_plan_overrun']],
            'severity' => ['type' => 'enum', 'values' => ['warning', 'danger']],
            'status' => ['type' => 'enum', 'values' => ['active', 'resolved', 'all']],
            'location_id' => ['type' => 'visible_location'],
        ],
        'reception-intakes.index' => [
            'location_id' => ['type' => 'visible_location'],
            'status' => ['type' => 'enum', 'values' => ['created', 'closed']],
        ],
        'supplier-receptions.index' => [
            'location_id' => ['type' => 'visible_location'],
            'supplier_id' => ['type' => 'positive_integer'],
            'catalog_item_id' => ['type' => 'positive_integer'],
            'document_type' => ['type' => 'enum', 'values' => ['aviz', 'factura']],
        ],
        'negotiated-orders.index' => [
            'status' => ['type' => 'enum', 'values' => ['created', 'closed']],
            'location_id' => ['type' => 'visible_location'],
            'supplier_id' => ['type' => 'positive_integer'],
        ],
        'consumption-reports.index' => [
            'location_id' => ['type' => 'visible_location'],
            'catalog_item_id' => ['type' => 'positive_integer'],
        ],
        'transfers.index' => [
            'purpose' => ['type' => 'enum', 'values' => ['transfer', 'return']],
            'status' => ['type' => 'enum', 'values' => ['pending_approval', 'approved', 'in_transit', 'received', 'cancelled']],
            'source_location_id' => ['type' => 'visible_location'],
            'destination_location_id' => ['type' => 'visible_location'],
            'project_id' => ['type' => 'positive_integer'],
            'driver_id' => ['type' => 'positive_integer'],
            'approval_status' => ['type' => 'enum', 'values' => ['pending', 'approved', 'rejected']],
            'overdue' => ['type' => 'boolean'],
            'archived' => ['type' => 'boolean'],
        ],
        'projects.index' => [
            'status' => ['type' => 'enum', 'values' => ['draft', 'active', 'completed', 'archived']],
            'location_id' => ['type' => 'visible_location'],
        ],
        'tasks.index' => [
            'status' => ['type' => 'enum', 'values' => [
                'unassigned', 'pending_acceptance', 'accepted', 'in_progress',
                'completed', 'rejected', 'cancelled', 'archived',
            ]],
            'priority' => ['type' => 'enum', 'values' => ['low', 'normal', 'high', 'urgent']],
            'driver_id' => ['type' => 'positive_integer'],
            'location_id' => ['type' => 'visible_location'],
            'overdue' => ['type' => 'boolean'],
            'archived' => ['type' => 'boolean'],
        ],
        'tasks.dispatch' => [
            'overdue' => ['type' => 'boolean'],
        ],
        'reports.index' => [
            'location_id' => ['type' => 'visible_location'],
            'status' => ['type' => 'enum', 'values' => ['pending_approval', 'approved', 'in_transit', 'received', 'cancelled']],
        ],
        'field.site-manager' => [
            'transfer_status' => ['type' => 'enum', 'values' => ['pending_approval', 'approved', 'in_transit']],
        ],
    ],
];
