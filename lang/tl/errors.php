<?php

declare(strict_types=1);

return [
    // Common errors
    'not_found' => 'Hindi nahanap ang resource.',
    'unauthorized' => 'Unauthorized.',
    'forbidden' => 'Bawal ang access.',
    'server_error' => 'Internal server error.',
    'validation_error' => 'Validation error.',
    'database_error' => 'Database error.',
    'network_error' => 'Network error.',
    'timeout' => 'Naubos ang oras.',

    // Direct Transfer errors
    'direct_transfer' => [
        'same_warehouse' => 'Hindi pwede magkapareho ang origin at destination warehouse.',
        'empty_items' => 'Dapat may laman ang transfer.',
        'invalid_item' => 'Invalid item data.',
        'insufficient_stock' => 'Kulang stock para sa :sku. Available: :available, Hiningi: :requested.',
        'variant_not_found' => 'Hindi nahanap ang product variant.',
        'warehouse_not_found' => 'Hindi nahanap ang warehouse.',
        'execution_failed' => 'Nabigo ang execution ng transfer.',
    ],

    // Transfer Requisition errors
    'transfer_requisition' => [
        'not_found' => 'Hindi nahanap ang requisition.',
        'not_confirmed' => 'Dapat confirmed ang requisition.',
        'not_dispatched' => 'Hindi dispatched ang requisition.',
        'already_received' => 'Natanggap na ang requisition.',
        'negotiation_failed' => 'Nabigo ang negotiation.',
    ],

    // Purchase Order errors
    'purchase_order' => [
        'not_found' => 'Hindi nahanap ang purchase order.',
        'not_ordered' => 'Dapat ordered state ang order.',
        'already_received' => 'Natanggap na ang order.',
    ],

    // Sales Order errors
    'sales_order' => [
        'not_found' => 'Hindi nahanap ang sales order.',
        'not_confirmed' => 'Dapat confirmed ang order.',
        'already_dispatched' => 'Ipinadala na ang order.',
        'insufficient_stock' => 'Kulang stock para sa :sku. Available: :available, Hiningi: :requested.',
    ],

    // Inventory errors
    'inventory' => [
        'insufficient_stock' => 'Kulang stock.',
        'invalid_adjustment_type' => 'Invalid adjustment type.',
        'variant_not_found' => 'Hindi nahanap ang product variant.',
        'warehouse_not_found' => 'Hindi nahanap ang warehouse.',
    ],

    // Scan errors
    'scan' => [
        'invalid_payload' => 'Invalid scan payload.',
        'requisition_not_receivable' => 'Hindi receivable state ang requisition.',
        'duplicate_scan' => 'Nadetect ang duplicate scan.',
        'idempotency_violation' => 'Idempotency violation.',
    ],

    // Authorization errors
    'authorization' => [
        'warehouse_scope' => 'Walang access sa warehouse na ito.',
        'policy_denied' => 'Pinagbawal ng policy ang action na ito.',
        'admin_required' => 'Kailangan admin role.',
    ],
];
