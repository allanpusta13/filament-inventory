<?php

declare(strict_types=1);

return [
    // Product resource
    'products' => [
        'model' => [
            'singular' => 'Product',
            'plural' => 'Products',
        ],
        'fields' => [
            'sku' => 'SKU',
            'barcode' => 'Barcode',
            'base_unit' => 'Base Unit',
            'reorder_point' => 'Reorder Point',
        ],
        'sections' => [
            'inventory' => 'Inventory',
            'pricing' => 'Pricing',
        ],
        'actions' => [
            'manage_units' => 'Manage Units',
            'set_price' => 'Set Price',
            'quick_adjustment' => 'Quick Adjustment',
        ],
        'notifications' => [
            'created' => 'Product created successfully.',
            'updated' => 'Product updated successfully.',
        ],
        'wizard' => [
            'review' => 'Review',
        ],
    ],

    // Transfer Requisition resource
    'transfer_requisitions' => [
        'model' => [
            'singular' => 'Transfer Requisition',
            'plural' => 'Transfer Requisitions',
        ],
        'fields' => [
            'from_warehouse' => 'From Warehouse',
            'to_warehouse' => 'To Warehouse',
        ],
        'steps' => [
            'routing' => 'Routing',
            'items' => 'Items',
            'review' => 'Review',
        ],
        'actions' => [
            'dispatch' => 'Dispatch',
            'receive' => 'Receive',
            'confirm' => 'Confirm',
            'negotiate' => 'Negotiate',
        ],
        'notifications' => [
            'created' => 'Transfer requisition created.',
            'confirmed' => 'Transfer requisition confirmed.',
            'dispatched' => 'Transfer requisition dispatched.',
            'received' => 'Transfer requisition received.',
            'completed' => 'Transfer requisition completed.',
            'cancelled' => 'Transfer requisition cancelled.',
        ],
        'badge' => [
            'tooltip' => 'Transfer requisitions ready to receive',
        ],
    ],

    // Direct Transfers resource (from direct_transfers.php)
    'direct_transfers' => [
        'model' => [
            'singular' => 'Direct Transfer',
            'plural' => 'Direct Transfers',
        ],
        'page' => [
            'create_heading' => 'Create Direct Transfer',
            'create_description' => 'Execute multi-line direct transfer between warehouses',
            'execute_transfer' => 'Execute Transfer',
        ],
        'form' => [
            'step1_title' => 'Location Mapping',
            'step1_desc' => 'Select origin and destination warehouses for transfer.',
            'step2_title' => 'Stock Allocation',
            'step2_desc' => 'Add product variants and quantities to transfer.',
            'step3_title' => 'Review & Verify',
            'step3_desc' => 'Review complete transfer summary before execution.',

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
                'line_notes_placeholder' => 'Optional notes for line',
                'transfer_notes' => 'Transfer Notes',
                'transfer_notes_placeholder' => 'Notes for entire transfer',
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

        'notifications' => [
            'transfer_executed' => 'Direct Transfer Executed',
            'transfer_completed' => 'Direct transfer :code has been completed successfully.',
        ],

        'actions' => [
            'view' => 'View',
        ],

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
    ],

    // InTransit resource
    'in_transits' => [
        'model' => [
            'singular' => 'In-Transit',
            'plural' => 'In-Transits',
        ],
        'fields' => [
            'requisition' => 'Requisition',
            'sku' => 'SKU',
            'dispatched_base' => 'Dispatched Base Qty',
            'dispatched_at' => 'Dispatched At',
            'badge' => [
                'tooltip' => 'In-transit movements in progress',
            ],
            'status' => 'Status',
            'cleared_at' => 'Cleared At',
        ],
    ],

    // Stock Movement resource
    'stock_movements' => [
        'model' => [
            'singular' => 'Stock Movement',
            'plural' => 'Stock Movements',
        ],
        'fields' => [
            'type' => 'Type',
            'quantity' => 'Quantity',
            'warehouse' => 'Warehouse',
            'product_variant' => 'Product Variant',
            'reference' => 'Reference',
            'related_movement' => 'Related Movement',
            'created_at' => 'Created At',
        ],
    ],

    // Loss Ledger resource
    'loss_ledgers' => [
        'model' => [
            'singular' => 'Loss Ledger',
            'plural' => 'Loss Ledgers',
        ],
        'fields' => [
            'requisition' => 'Requisition',
            'variant' => 'Variant',
            'lost_qty' => 'Lost Qty',
            'unit_cost' => 'Unit Cost',
            'total_loss' => 'Total Loss',
            'category' => 'Category',
            'recorded_at' => 'Recorded At',
            'recorded_by' => 'Recorded By',
        ],
    ],

    // Warehouse resource
    'warehouses' => [
        'model' => [
            'singular' => 'Warehouse',
            'plural' => 'Warehouses',
        ],
        'fields' => [
            'code' => 'Code',
            'name' => 'Name',
            'location' => 'Location',
            'is_active' => 'Active',
        ],
        'sections' => [
            'details' => 'Details',
        ],
    ],

    // User resource
    'users' => [
        'model' => [
            'singular' => 'User',
            'plural' => 'Users',
        ],
        'fields' => [
            'name' => 'Name',
            'email' => 'Email',
            'role' => 'Role',
            'warehouses' => 'Warehouses',
        ],
    ],

    // Purchase Order resource
    'purchase_orders' => [
        'model' => [
            'singular' => 'Purchase Order',
            'plural' => 'Purchase Orders',
        ],
        'fields' => [
            'supplier' => 'Supplier',
            'warehouse' => 'Receiving Warehouse',
        ],
        'steps' => [
            'supplier_warehouse' => 'Supplier & Warehouse',
            'line_items' => 'Line Items',
            'review_verify' => 'Review & Notes',
        ],
        'actions' => [
            'receive' => 'Receive',
            'update_cost' => 'Update Cost',
        ],
        'notifications' => [
            'created' => 'Purchase order created.',
            'confirmed' => 'Purchase order confirmed.',
            'received' => 'Purchase order received.',
            'cancelled' => 'Purchase order cancelled.',
        ],
        'badge' => [
            'tooltip' => 'Purchase orders awaiting receipt',
        ],
    ],

    // Supplier resource
    'suppliers' => [
        'model' => [
            'singular' => 'Supplier',
            'plural' => 'Suppliers',
        ],
        'fields' => [
            'name' => 'Name',
            'contact_person' => 'Contact Person',
            'email' => 'Email',
            'phone' => 'Phone',
            'address' => 'Address',
        ],
    ],

    // Sales Order resource
    'sales_orders' => [
        'model' => [
            'singular' => 'Sales Order',
            'plural' => 'Sales Orders',
        ],
        'fields' => [
            'customer' => 'Customer',
            'warehouse' => 'Dispatch Warehouse',
        ],
        'steps' => [
            'customer_warehouse' => 'Customer & Warehouse',
            'line_items' => 'Line Items (Read-Only Sale Price Preview)',
            'review_verify' => 'Review & Notes',
        ],
        'actions' => [
            'dispatch' => 'Dispatch',
            'record_return' => 'Record Return',
        ],
        'notifications' => [
            'created' => 'Sales order created.',
            'confirmed' => 'Sales order confirmed.',
            'dispatched' => 'Sales order dispatched.',
        ],
        'badge' => [
            'tooltip' => 'Sales orders awaiting dispatch',
        ],
    ],

    // Customer resource
    'customers' => [
        'model' => [
            'singular' => 'Customer',
            'plural' => 'Customers',
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
