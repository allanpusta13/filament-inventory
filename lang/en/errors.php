<?php

declare(strict_types=1);

return [
    // Common errors
    'general' => 'An unexpected error occurred.',
    'not_found' => 'The requested resource was not found.',
    'forbidden' => 'You do not have permission to access this resource.',
    'unauthorized' => 'Please log in to access this resource.',
    'server_error' => 'Server error. Please try again later.',
    'validation_failed' => 'Validation failed. Please check your input.',
    'database_error' => 'Database error. Please contact support.',
    'rate_limited' => 'Too many requests. Please slow down.',
    'csrf_mismatch' => 'Invalid CSRF token. Please refresh the page.',

    // Direct Transfer errors
    'direct_transfers' => [
        'same_warehouse' => 'Origin and destination warehouses must be different.',
        'no_items' => 'At least one item is required for the transfer.',
        'missing_fields' => 'All item fields are required.',
        'invalid_unit_ratio' => 'Unit ratio must be at least 1.',
        'invalid_qty' => 'Quantity must be at least 1.',
        'invalid_base_qty' => 'Base quantity must be at least 1.',
        'insufficient_stock' => 'Insufficient stock for transfer.',
        'variant_not_found' => 'Product variant not found.',
        'warehouse_not_found' => 'Warehouse not found.',
    ],

    // Transfer Requisition errors
    'transfer_requisition' => [
        'same_warehouse' => 'Origin and destination warehouses must be different.',
        'not_confirmed' => 'Requisition must be confirmed before dispatch.',
        'not_dispatched' => 'Requisition is not in a receivable state.',
        'no_approved_qty' => 'Item has no approved quantity.',
        'insufficient_stock' => 'Insufficient stock at origin warehouse.',
        'already_dispatched' => 'Requisition has already been dispatched.',
        'already_cancelled' => 'Requisition has already been cancelled.',
    ],

    // Purchase Order errors
    'purchase_order' => [
        'not_ordered' => 'Purchase order must be ordered before receiving.',
        'already_received' => 'Purchase order has already been received.',
        'already_cancelled' => 'Purchase order has already been cancelled.',
        'no_items' => 'At least one line item is required.',
    ],

    // Sales Order errors
    'sales_order' => [
        'not_confirmed' => 'Sales order must be confirmed before dispatch.',
        'already_dispatched' => 'Sales order has already been dispatched.',
        'already_cancelled' => 'Sales order has already been cancelled.',
        'no_items' => 'At least one line item is required.',
        'insufficient_stock' => 'Insufficient stock for dispatch.',
    ],

    // Scan errors
    'scan' => [
        'invalid_payload' => 'Invalid scan payload.',
        'requisition_not_receivable' => 'Requisition is not in a receivable state.',
        'duplicate_scan' => 'Duplicate scan detected.',
        'qr_expired' => 'QR code has expired.',
        'qr_invalid' => 'Invalid QR code.',
    ],

    // Inventory errors
    'inventory' => [
        'insufficient_stock' => 'Insufficient stock.',
        'invalid_adjustment_type' => 'Invalid adjustment type.',
        'variant_not_found' => 'Product variant not found.',
        'warehouse_not_found' => 'Warehouse not found.',
    ],
];
