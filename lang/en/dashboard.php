<?php

declare(strict_types=1);

/**
 * Dashboard widget strings (§10 / §0A.2a).
 *
 * The four `stats.*_description` keys were added alongside the corrected
 * `StatsOverviewWidget` (Filament v5 stat cards — see the class docblock
 * in app/Filament/Widgets/StatsOverviewWidget.php for the blueprint
 * correction). Each card renders a label + a value + a description.
 */
return [
    'stats' => [
        'on_hand' => 'On hand',
        'in_transit' => 'In transit',
        'pending' => 'Pending',
        'write_off' => 'Write-off',
        'warehouse' => 'Warehouse',

        // Stat-card descriptions for StatsOverviewWidget (§10).
        'on_hand_description' => 'Total base units across in-scope warehouses.',
        'pending_description' => 'Requisitions awaiting dispatch or confirmation.',
        'in_transit_description' => 'Dispatched cargo not yet accounted at destination.',
        'write_off_description' => 'Total financial loss recorded.',
    ],

    'charts' => [
        'available' => 'Available',
        'dispatched' => 'Dispatched',
        'net_movement' => 'Net movement',
        'purchases' => 'Purchases',
        'revenue' => 'Revenue',
        'sales' => 'Sales',
    ],

    'pending' => [
        'purchases' => 'Pending purchases',
        'sales' => 'Pending sales',
        'warehouse' => 'Warehouse',
    ],

    'quick_actions' => [
        'new_transfer' => 'New transfer',
        'new_direct_transfer' => 'New direct transfer',
        'new_purchase' => 'New purchase',
        'new_sale' => 'New sale',
    ],

    'active_in_transit' => [
        'qty' => 'Qty',
        'requisition' => 'Requisition',
        'sku' => 'SKU',
        'status' => 'Status',
    ],
];
