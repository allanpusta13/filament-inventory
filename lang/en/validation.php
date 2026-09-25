<?php

declare(strict_types=1);

return [
    // Common validation
    'required' => 'The :attribute field is required.',
    'string' => 'The :attribute must be a string.',
    'integer' => 'The :attribute must be an integer.',
    'numeric' => 'The :attribute must be a number.',
    'min' => [
        'string' => 'The :attribute must be at least :min characters.',
        'numeric' => 'The :attribute must be at least :min.',
    ],
    'max' => [
        'string' => 'The :attribute must not exceed :max characters.',
        'numeric' => 'The :attribute must not exceed :max.',
    ],
    'unique' => 'The :attribute has already been taken.',
    'exists' => 'The selected :attribute is invalid.',
    'in' => 'The selected :attribute is invalid.',
    'confirmed' => 'The :attribute confirmation does not match.',
    'email' => 'The :attribute must be a valid email address.',
    'date' => 'The :attribute must be a valid date.',
    'before' => 'The :attribute must be a date before :date.',
    'after' => 'The :attribute must be a date after :date.',

    // Custom validation messages
    'direct_transfers' => [
        'same_warehouse' => 'Origin and destination warehouses must be different.',
        'no_items' => 'At least one item is required for the transfer.',
        'missing_fields' => 'All item fields are required.',
        'invalid_unit_ratio' => 'Unit ratio must be at least 1. Received: :ratio.',
        'invalid_qty' => 'Quantity must be at least 1. Received: :qty.',
        'invalid_base_qty' => 'Base quantity must be at least 1. Received: :base_qty.',
        'insufficient_stock' => 'Insufficient stock for :sku at origin warehouse. Available: :available, Requested: :requested.',
    ],

    'transfer_requisition' => [
        'same_warehouse' => 'Origin and destination warehouses must be different.',
        'not_confirmed' => 'Requisition must be confirmed before dispatch.',
        'not_dispatched' => 'Requisition is not in a receivable state.',
        'no_approved_qty' => 'Item has no approved quantity. Confirm action must materialize approved fields before dispatch.',
        'insufficient_stock' => 'Insufficient stock at origin warehouse for requisition.',
    ],

    'purchase_order' => [
        'not_ordered' => 'Purchase order must be ordered before receiving.',
        'no_items' => 'At least one line item is required.',
    ],

    'sales_order' => [
        'not_confirmed' => 'Sales order must be confirmed before dispatch.',
        'no_items' => 'At least one line item is required.',
        'insufficient_stock' => 'Insufficient stock for dispatch. Available: :available, Requested: :requested.',
    ],

    'inventory' => [
        'insufficient_stock' => 'Insufficient stock. Available: :available, Requested: :requested.',
        'invalid_adjustment_type' => 'Invalid adjustment type. Must be one of: adjustment, receive, ship.',
    ],

    'scan' => [
        'invalid_payload' => 'Invalid scan payload.',
        'requisition_not_receivable' => 'Requisition is not in a receivable state.',
        'duplicate_scan' => 'Duplicate scan detected.',
    ],
];
