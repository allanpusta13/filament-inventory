<?php

declare(strict_types=1);

return [
    // Common table elements
    'actions' => 'Mga Aksyon',
    'no_records' => 'Walang nahanap na records.',
    'loading' => 'Naglo-load...',
    'search_placeholder' => 'Maghanap...',
    'filter_button' => 'I-filter',
    'clear_filters' => 'Linisiin ang filters',
    'showing' => 'Ipinapakita :from hanggang :of ng :total results',
    'per_page' => 'Bawat page: :count',
    'page' => 'Page :page',
    'of' => 'ng :pages',

    // Direct Transfer table
    'reference' => 'Sanggunian',
    'reference_code' => 'Kodigo ng Sanggunian',
    'from' => 'Mula',
    'to' => 'Sa',
    'from_warehouse' => 'Origin Warehouse',
    'to_warehouse' => 'Destination Warehouse',
    'by' => 'Ni',
    'transferred_by' => 'Inilipat Ni',
    'transferred_at' => 'Inilipat Noong',
    'items' => 'Mga Item',
    'items_count' => 'Bilang ng Item',
    'sku' => 'SKU',
    'variant' => 'Variant',
    'qty_unit' => 'Qty / Unit',
    'base_units' => 'Base Units',
    'line_notes' => 'Mga Tala ng Linya',
    'filter_from_warehouse' => 'I-filter by Origin Warehouse',
    'filter_to_warehouse' => 'I-filter by Destination Warehouse',

    // Stock Movement table
    'movement_type' => 'Uri',
    'quantity' => 'Dami',
    'warehouse' => 'Bodega',
    'product_variant' => 'Variant ng Produkto',
    'reference' => 'Sanggunian',
    'related_movement' => 'Kaugnay na Galaw',
    'created_at' => 'Nilikha Noong',

    // Product table
    'product_name' => 'Produkto',
    'variant_name' => 'Variant',
    'base_unit' => 'Base Unit',
    'reorder_point' => 'Reorder Point',
    'current_stock' => 'Kasalukuyang Stock',
    'price' => 'Presyo',

    // Warehouse table
    'warehouse_code' => 'Kodigo',
    'warehouse_name' => 'Pangalan',
    'warehouse_location' => 'Lokasyon',
    'warehouse_active' => 'Active',

    // Transfer Requisition table
    'requisition_reference' => 'Sanggunian',
    'requisition_status' => 'Katayuan',
    'requisition_from' => 'Mula',
    'requisition_to' => 'Sa',
    'requisition_requested_at' => 'Hiningi Noong',
    'requisition_confirmed_at' => 'Kinumpirma Noong',
    'requisition_dispatched_at' => 'Ipinadala Noong',
    'requisition_completed_at' => 'Natapos Noong',

    // Purchase Order table
    'po_reference' => 'Sanggunian',
    'po_supplier' => 'Supplier',
    'po_warehouse' => 'Bodega',
    'po_status' => 'Katayuan',
    'po_ordered_at' => 'In-order Noong',
    'po_received_at' => 'Natanggap Noong',
    'po_total' => 'Kabuuan',

    // Sales Order table
    'so_reference' => 'Sanggunian',
    'so_customer' => 'Kustomer',
    'so_warehouse' => 'Bodega',
    'so_status' => 'Katayuan',
    'so_confirmed_at' => 'Kinumpirma Noong',
    'so_dispatched_at' => 'Ipinadala Noong',
    'so_total' => 'Kabuuan',

    // InTransit table
    'in_transit_requisition' => 'Requisition',
    'in_transit_dispatched' => 'Ipinadala',
    'in_transit_dispatched_at' => 'Ipinadala Noong',
    'in_transit_status' => 'Katayuan',

    // Loss Ledger table
    'loss_requisition' => 'Requisition',
    'loss_variant' => 'Variant',
    'loss_qty' => 'Natapusang Dami',
    'loss_cost' => 'Unit Cost',
    'loss_total' => 'Kabuuang Loss',
    'loss_category' => 'Kategorya',
    'loss_recorded_at' => 'Naitala Noong',
    'loss_recorded_by' => 'Naitala Ni',

    // User table
    'user_name' => 'Pangalan',
    'user_email' => 'Email',
    'user_role' => 'Rol',
    'user_warehouses' => 'Mga Bodega',
    'user_active' => 'Active',
];
