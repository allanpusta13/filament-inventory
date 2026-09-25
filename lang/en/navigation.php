<?php

declare(strict_types=1);

return [
    // Navigation groups
    'groups' => [
        'catalog' => 'CATALOG',
        'operations' => 'OPERATIONS',
        'purchasing' => 'PURCHASING',
        'sales' => 'SALES',
        'audit_ledgers' => 'AUDIT LEDGERS',
        'system_admin' => 'SYSTEM ADMIN',
    ],

    // Resource labels
    'resources' => [
        'products' => [
            'singular' => 'Product',
            'plural' => 'Products',
            'navigation' => 'Products',
        ],
        'transfer_requisitions' => [
            'singular' => 'Transfer Requisition',
            'plural' => 'Transfer Requisitions',
            'navigation' => 'Transfer Requisitions',
        ],
        'direct_transfers' => [
            'singular' => 'Direct Transfer',
            'plural' => 'Direct Transfers',
            'navigation' => 'Direct Transfers',
        ],
        'in_transits' => [
            'singular' => 'In-Transit',
            'plural' => 'In-Transits',
            'navigation' => 'In-Transits',
        ],
        'stock_movements' => [
            'singular' => 'Stock Movement',
            'plural' => 'Stock Movements',
            'navigation' => 'Stock Movements',
        ],
        'loss_ledgers' => [
            'singular' => 'Loss Ledger',
            'plural' => 'Loss Ledgers',
            'navigation' => 'Loss Ledgers',
        ],
        'warehouses' => [
            'singular' => 'Warehouse',
            'plural' => 'Warehouses',
            'navigation' => 'Warehouses',
        ],
        'users' => [
            'singular' => 'User',
            'plural' => 'Users',
            'navigation' => 'Users',
        ],
        'purchase_orders' => [
            'singular' => 'Purchase Order',
            'plural' => 'Purchase Orders',
            'navigation' => 'Purchase Orders',
        ],
        'suppliers' => [
            'singular' => 'Supplier',
            'plural' => 'Suppliers',
            'navigation' => 'Suppliers',
        ],
        'sales_orders' => [
            'singular' => 'Sales Order',
            'plural' => 'Sales Orders',
            'navigation' => 'Sales Orders',
        ],
        'customers' => [
            'singular' => 'Customer',
            'plural' => 'Customers',
            'navigation' => 'Customers',
        ],
    ],

    // Badge tooltips
    'badge_tooltips' => [
        'transfer_requisitions' => 'Pending transfer requisitions in your assigned warehouses.',
        'purchase_orders' => 'Pending purchase orders in your assigned warehouses.',
        'sales_orders' => 'Confirmed sales orders awaiting dispatch in your assigned warehouses.',
        'in_transits' => 'In-transit shipments for your assigned warehouses.',
    ],
];
