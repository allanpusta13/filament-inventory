<?php

declare(strict_types=1);

return [
    // Common form elements
    'required' => 'This field is required.',
    'optional' => 'Optional',
    'placeholder_select' => 'Select...',
    'placeholder_search' => 'Search...',
    'no_results' => 'No results found',
    'loading' => 'Loading...',

    // Direct Transfer form
    'sections' => [
        'routing' => 'Routing',
        'stock_allocation' => 'Stock Allocation',
        'review' => 'Review & Confirm',
        'transfer_notes' => 'Transfer Notes',
    ],
    'fields' => [
        'from_warehouse' => 'Origin Warehouse',
        'to_warehouse' => 'Destination Warehouse',
        'variant_sku' => 'Product Variant (SKU)',
        'unit' => 'Unit',
        'ratio_base' => 'Ratio to Base',
        'qty' => 'Qty',
        'base_qty' => 'Base Qty (Computed)',
        'notes' => 'Notes',
        'transfer_notes' => 'Transfer Notes',
        'reference_code' => 'Reference Code',
        'transferred_at' => 'Transferred At',
        'transferred_by' => 'Transferred By',
    ],
    'placeholders' => [
        'variant_sku' => 'Select product variant...',
        'unit' => 'Select unit...',
        'qty' => 'e.g., 10',
        'notes' => 'Optional line notes...',
        'transfer_notes' => 'Optional transfer notes...',
        'line_notes_placeholder' => 'Optional line notes...',
        'transfer_notes_placeholder' => 'Optional notes for this transfer...',
    ],
    'hints' => [
        'ratio_auto' => 'Auto-filled from variant\'s unit conversion',
        'base_qty_computed' => 'Calculated as Qty × Ratio',
    ],
    'steps' => [
        'location_mapping' => 'Step 1: Location Mapping',
        'location_mapping_desc' => 'Select the origin and destination warehouses for this transfer.',
        'stock_allocation' => 'Step 2: Stock Allocation',
        'stock_allocation_desc' => 'Add the product variants and quantities to transfer.',
        'review_verify' => 'Step 3: Review & Verify',
        'review_verify_desc' => 'Review all details before executing the transfer.',
    ],
    'validation' => [
        'warehouse_mismatch' => 'Origin and destination warehouses must be different.',
        'complete_previous_steps' => 'Please complete the previous steps first.',
    ],

    // Product form
    'product' => [
        'sections' => [
            'basic_info' => 'Basic Information',
            'inventory' => 'Inventory',
            'pricing' => 'Pricing',
        ],
        'fields' => [
            'name' => 'Name',
            'sku' => 'SKU',
            'barcode' => 'Barcode',
            'base_unit_name' => 'Base Unit',
            'reorder_point' => 'Reorder Point',
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

    // Transfer Requisition form
    'transfer_requisition' => [
        'sections' => [
            'routing' => 'Routing',
            'items' => 'Items',
            'review' => 'Review',
        ],
        'fields' => [
            'from_warehouse' => 'From Warehouse',
            'to_warehouse' => 'To Warehouse',
            'product_variant' => 'Product Variant',
            'requested_qty' => 'Requested Qty',
            'requested_unit' => 'Requested Unit',
        ],
    ],

    // Purchase Order form
    'purchase_order' => [
        'sections' => [
            'supplier_warehouse' => 'Supplier & Warehouse',
            'line_items' => 'Line Items',
            'review' => 'Review & Notes',
        ],
        'fields' => [
            'supplier' => 'Supplier',
            'warehouse' => 'Receiving Warehouse',
            'product_variant' => 'Product Variant',
            'order_qty' => 'Order Qty',
            'order_unit' => 'Order Unit',
            'unit_cost' => 'Unit Cost',
            'update_cost_price' => 'Update Variant Cost Price on Receipt',
        ],
    ],

    // Sales Order form
    'sales_order' => [
        'sections' => [
            'customer_warehouse' => 'Customer & Warehouse',
            'line_items' => 'Line Items (Read-Only Sale Price Preview)',
            'review' => 'Review & Notes',
        ],
        'fields' => [
            'customer' => 'Customer',
            'warehouse' => 'Dispatch Warehouse',
            'product_variant' => 'Product Variant',
            'order_qty' => 'Order Qty',
            'order_unit' => 'Order Unit',
            'sale_price_preview' => 'Sale Price (Preview)',
        ],
    ],

    // User form
    'user' => [
        'sections' => [
            'profile' => 'Profile',
            'roles' => 'Roles & Warehouses',
        ],
        'fields' => [
            'name' => 'Name',
            'email' => 'Email',
            'password' => 'Password',
            'password_confirmation' => 'Confirm Password',
            'role' => 'Role',
            'warehouses' => 'Assigned Warehouses',
        ],
    ],

    // Supplier/Customer form
    'party' => [
        'sections' => [
            'profile' => 'Profile',
            'contact' => 'Contact',
        ],
        'fields' => [
            'name' => 'Name',
            'contact_person' => 'Contact Person',
            'email' => 'Email',
            'phone' => 'Phone',
            'address' => 'Address',
        ],
    ],
];
