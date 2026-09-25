<?php

declare(strict_types=1);

return [
    // Common table elements
    'actions' => 'Actions',
    'no_records' => 'No records found.',
    'loading' => 'Loading...',
    'search_placeholder' => 'Search...',
    'filter_button' => 'Filter',
    'clear_filters' => 'Clear Filters',
    'showing' => 'Showing :from to :of of :total results',
    'per_page' => 'Per page: :count',
    'page' => 'Page :page',
    'of' => 'of :pages',

    // Direct Transfer table
    'reference' => 'Reference',
    'reference_code' => 'Reference Code',
    'from' => 'From',
    'to' => 'To',
    'from_warehouse' => 'Origin Warehouse',
    'to_warehouse' => 'Destination Warehouse',
    'by' => 'By',
    'transferred_by' => 'Transferred By',
    'transferred_at' => 'Transferred At',
    'items' => 'Items',
    'items_count' => 'Items Count',
    'sku' => 'SKU',
    'variant' => 'Variant',
    'qty_unit' => 'Qty / Unit',
    'base_units' => 'Base Units',
    'line_notes' => 'Line Notes',
    'filter_from_warehouse' => 'Filter by Origin Warehouse',
    'filter_to_warehouse' => 'Filter by Destination Warehouse',

    // Stock Movement table
    'movement_type' => 'Type',
    'quantity' => 'Quantity',
    'warehouse' => 'Warehouse',
    'product_variant' => 'Product Variant',
    'reference' => 'Reference',
    'related_movement' => 'Related Movement',
    'created_at' => 'Created At',

    // Product table
    'product_name' => 'Product',
    'variant_name' => 'Variant',
    'base_unit' => 'Base Unit',
    'reorder_point' => 'Reorder Point',
    'current_stock' => 'Current Stock',
    'price' => 'Price',

    // Warehouse table
    'warehouse_code' => 'Code',
    'warehouse_name' => 'Name',
    'warehouse_location' => 'Location',
    'warehouse_active' => 'Active',

    // Transfer Requisition table
    'requisition_reference' => 'Reference',
    'requisition_status' => 'Status',
    'requisition_from' => 'From',
    'requisition_to' => 'To',
    'requisition_requested_at' => 'Requested At',
    'requisition_confirmed_at' => 'Confirmed At',
    'requisition_dispatched_at' => 'Dispatched At',
    'requisition_completed_at' => 'Completed At',

    // Purchase Order table
    'po_reference' => 'Reference',
    'po_supplier' => 'Supplier',
    'po_warehouse' => 'Warehouse',
    'po_status' => 'Status',
    'po_ordered_at' => 'Ordered At',
    'po_received_at' => 'Received At',
    'po_total' => 'Total',

    // Sales Order table
    'so_reference' => 'Reference',
    'so_customer' => 'Customer',
    'so_warehouse' => 'Warehouse',
    'so_status' => 'Status',
    'so_confirmed_at' => 'Confirmed At',
    'so_dispatched_at' => 'Dispatched At',
    'so_total' => 'Total',

    // InTransit table
    'in_transit_requisition' => 'Requisition',
    'in_transit_dispatched' => 'Dispatched',
    'in_transit_dispatched_at' => 'Dispatched At',
    'in_transit_status' => 'Status',

    // Loss Ledger table
    'loss_requisition' => 'Requisition',
    'loss_variant' => 'Variant',
    'loss_qty' => 'Lost Qty',
    'loss_cost' => 'Unit Cost',
    'loss_total' => 'Total Loss',
    'loss_category' => 'Category',
    'loss_recorded_at' => 'Recorded At',
    'loss_recorded_by' => 'Recorded By',

    // User table
    'user_name' => 'Name',
    'user_email' => 'Email',
    'user_role' => 'Role',
    'user_warehouses' => 'Warehouses',
    'user_active' => 'Active',
];
