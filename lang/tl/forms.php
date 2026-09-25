<?php

declare(strict_types=1);

return [
    // Common form elements
    'required' => 'Required',
    'optional' => 'Optional',
    'placeholder' => 'Pumili...',
    'select_option' => 'Pumili ng option',
    'search' => 'Maghanap...',
    'no_results' => 'Walang results',
    'loading' => 'Naglo-load...',
    'save' => 'I-save',
    'cancel' => 'Kanselahin',
    'submit' => 'Isumite',
    'reset' => 'I-reset',
    'add' => 'Magdagdag',
    'remove' => 'Tanggalin',
    'edit' => 'I-edit',
    'view' => 'Tingnan',
    'delete' => 'Burahin',
    'confirm' => 'Kumpirmahin',
    'back' => 'Bumalik',
    'next' => 'Susunod',
    'previous' => 'Nakaraang',
    'finish' => 'Tapusin',

    // Validation messages
    'validation_required' => 'Required ang field na ito',
    'validation_min' => 'Minimum :min characters',
    'validation_max' => 'Maximum :max characters',
    'validation_numeric' => 'Dapat number',
    'validation_integer' => 'Dapat integer',
    'validation_email' => 'Invalid email',
    'validation_date' => 'Invalid date',
    'validation_unique' => 'Existing na value ito',
    'validation_exists' => 'Invalid ang selected value',
    'validation_confirmed' => 'Hindi match ang confirmation',

    // Direct Transfer form
    'direct_transfer' => [
        'step1_title' => 'Location Mapping',
        'step1_desc' => 'Pumili ng origin at destination warehouse para sa transfer.',
        'step2_title' => 'Stock Allocation',
        'step2_desc' => 'Magdagdag ng product variants at quantities to transfer.',
        'step3_title' => 'Review & Verify',
        'step3_desc' => 'Suriiin ang complete transfer summary bago i-execute.',

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
            'line_notes_placeholder' => 'Optional notes para sa line',
            'transfer_notes' => 'Transfer Notes',
            'transfer_notes_placeholder' => 'Notes para sa entire transfer',
        ],

        'hints' => [
            'ratio_auto' => 'Auto-filled based on selected unit',
        ],

        'add_line' => 'Add Line',

        'review_verify' => 'Review & Verify',

        'complete_previous_steps' => 'Complete previous steps to review summary.',
        'origin' => 'Origin',
        'destination' => 'Destination',
        'sku' => 'SKU',
        'variant' => 'Variant',
        'qty_unit' => 'Qty / Unit',
        'base_units' => 'Base Units',
    ],

    // Transfer Requisition form
    'transfer_requisition' => [
        'step1_title' => 'Routing',
        'step1_desc' => 'Pumili ng origin at destination warehouse.',
        'step2_title' => 'Items',
        'step2_desc' => 'Magdagdag ng product variants at requested quantities.',
        'step3_title' => 'Review',
        'step3_desc' => 'Suriiin ang complete requisition bago ipadala.',

        'fields' => [
            'from_warehouse' => 'From Warehouse',
            'to_warehouse' => 'To Warehouse',
            'items' => 'Items',
            'product_variant' => 'Product Variant',
            'unit_name' => 'Unit',
            'unit_ratio' => 'Ratio (to Base)',
            'qty' => 'Quantity',
            'base_qty' => 'Base Quantity',
            'line_notes' => 'Line Notes',
        ],
    ],

    // Purchase Order form
    'purchase_order' => [
        'step1_title' => 'Supplier & Warehouse',
        'step1_desc' => 'Pumili ng supplier at receiving warehouse.',
        'step2_title' => 'Line Items',
        'step2_desc' => 'Magdagdag ng product variants, units, at quantities.',
        'step3_title' => 'Review & Notes',
        'step3_desc' => 'Suriiin ang complete order at magdagdag ng notes.',

        'fields' => [
            'supplier' => 'Supplier',
            'warehouse' => 'Receiving Warehouse',
            'items' => 'Lines',
            'product_variant' => 'Product Variant',
            'unit_name' => 'Unit',
            'unit_ratio' => 'Ratio (to Base)',
            'qty' => 'Quantity',
            'base_qty' => 'Base Quantity',
            'unit_cost' => 'Unit Cost',
            'line_notes' => 'Line Notes',
            'order_notes' => 'Order Notes',
        ],
    ],

    // Sales Order form
    'sales_order' => [
        'step1_title' => 'Customer & Warehouse',
        'step1_desc' => 'Pumili ng customer at dispatch warehouse.',
        'step2_title' => 'Line Items (Read-Only Sale Price Preview)',
        'step2_desc' => 'Suriiin ang lines na may captured sale prices.',
        'step3_title' => 'Review & Notes',
        'step3_desc' => 'Suriiin ang complete order at magdagdag ng notes.',

        'fields' => [
            'customer' => 'Customer',
            'warehouse' => 'Dispatch Warehouse',
            'items' => 'Lines',
            'product_variant' => 'Product Variant',
            'unit_name' => 'Unit',
            'unit_ratio' => 'Ratio (to Base)',
            'qty' => 'Quantity',
            'base_qty' => 'Base Quantity',
            'sale_price' => 'Sale Price',
            'line_notes' => 'Line Notes',
            'order_notes' => 'Order Notes',
        ],
    ],

    // Product form
    'product' => [
        'sections' => [
            'details' => 'Details',
            'inventory' => 'Inventory',
            'pricing' => 'Pricing',
        ],
        'fields' => [
            'sku' => 'SKU',
            'barcode' => 'Barcode',
            'name' => 'Name',
            'description' => 'Description',
            'base_unit' => 'Base Unit',
            'reorder_point' => 'Reorder Point',
            'is_active' => 'Active',
        ],
    ],

    // Warehouse form
    'warehouse' => [
        'sections' => [
            'details' => 'Details',
        ],
        'fields' => [
            'code' => 'Code',
            'name' => 'Name',
            'location' => 'Location',
            'is_active' => 'Active',
        ],
    ],

    // Supplier form
    'supplier' => [
        'fields' => [
            'name' => 'Name',
            'contact_person' => 'Contact Person',
            'email' => 'Email',
            'phone' => 'Phone',
            'address' => 'Address',
        ],
    ],

    // Customer form
    'customer' => [
        'fields' => [
            'name' => 'Name',
            'contact_person' => 'Contact Person',
            'email' => 'Email',
            'phone' => 'Phone',
            'address' => 'Address',
        ],
    ],

    // User form
    'user' => [
        'fields' => [
            'name' => 'Name',
            'email' => 'Email',
            'role' => 'Role',
            'warehouses' => 'Assigned Warehouses',
            'password' => 'Password',
            'password_confirmation' => 'Confirm Password',
        ],
    ],
];
