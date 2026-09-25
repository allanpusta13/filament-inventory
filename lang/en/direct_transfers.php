<?php

declare(strict_types=1);

return [
    // Model labels
    'model' => [
        'singular' => 'Direct Transfer',
        'plural' => 'Direct Transfers',
    ],

    // Page headings
    'page' => [
        'create_heading' => 'Create Direct Transfer',
        'create_description' => 'Execute a multi-line direct transfer between warehouses',
        'execute_transfer' => 'Execute Transfer',
    ],

    // Form fields and sections
    'form' => [
        'step1_title' => 'Location Mapping',
        'step1_desc' => 'Select the origin and destination warehouses for this transfer.',
        'step2_title' => 'Stock Allocation',
        'step2_desc' => 'Add product variants and quantities to transfer.',
        'step3_title' => 'Review & Verify',
        'step3_desc' => 'Review the complete transfer summary before execution.',

        'sections' => [
            'routing' => 'Warehouse Routing',
        ],

        'fields' => [
            'origin_warehouse' => 'Origin Warehouse',
            'destination_warehouse' => 'Destination Warehouse',
            'items' => 'Items',
            'product_variant' => 'Product Variant',
            'unit_name' => 'Unit',
            'unit_ratio' => 'Ratio (to Base)',
            'qty' => 'Quantity',
            'base_qty' => 'Base Quantity (Computed)',
            'line_notes' => 'Line Notes',
            'line_notes_placeholder' => 'Optional notes for this line',
            'transfer_notes' => 'Transfer Notes',
            'transfer_notes_placeholder' => 'Notes for the entire transfer',
        ],

        'hints' => [
            'ratio_auto' => 'Auto-filled based on selected unit',
        ],

        'add_line' => 'Add Line',

        'review_verify' => 'Review & Verify',

        'complete_previous_steps' => 'Complete the previous steps to see a review summary.',

        'origin' => 'Origin',
        'destination' => 'Destination',
        'sku' => 'SKU',
        'variant' => 'Variant',
        'qty_unit' => 'Qty / Unit',
        'base_units' => 'Base Units',
        'notes' => 'Notes',

        'errors' => [
            'same_warehouse' => 'Origin and destination warehouses cannot be the same.',
            'no_items' => 'At least one transfer line is required.',
            'missing_fields' => 'All fields are required for each transfer line.',
            'invalid_unit_ratio' => 'Unit ratio must be at least 1.',
            'invalid_qty' => 'Quantity must be at least 1.',
            'invalid_base_qty' => 'Base quantity must be at least 1.',
        ],
    ],

    // Table columns and filters
    'table' => [
        'reference_code' => 'Reference',
        'reference' => 'Reference',
        'from_warehouse' => 'From',
        'to_warehouse' => 'To',
        'transferred_by' => 'Transferred By',
        'items_count' => 'Lines',
        'items' => 'Lines',
        'transferred_at' => 'Transferred At',
        'filter_from_warehouse' => 'From Warehouse',
        'filter_to_warehouse' => 'To Warehouse',
    ],

    // Notifications
    'notifications' => [
        'transfer_executed' => 'Direct Transfer Executed',
        'transfer_completed' => 'Direct transfer :code has been completed successfully.',
    ],

    // Actions
    'actions' => [
        'view' => 'View',
    ],

    // Infolist
    'fields' => [
        'reference_code' => 'Reference Code',
        'transferred_at' => 'Transferred At',
        'from_warehouse' => 'Origin Warehouse',
        'to_warehouse' => 'Destination Warehouse',
        'transferred_by' => 'Transferred By',
        'sku' => 'SKU',
        'qty' => 'Quantity',
        'ratio' => 'Ratio',
        'base_qty' => 'Base Quantity',
    ],
];
