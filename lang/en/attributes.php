<?php

declare(strict_types=1);

return [
    // Common attributes
    'id' => 'ID',
    'name' => 'Name',
    'code' => 'Code',
    'sku' => 'SKU',
    'barcode' => 'Barcode',
    'description' => 'Description',
    'email' => 'Email',
    'phone' => 'Phone',
    'address' => 'Address',
    'location' => 'Location',
    'notes' => 'Notes',
    'status' => 'Status',
    'is_active' => 'Active',
    'created_at' => 'Created At',
    'updated_at' => 'Updated At',
    'deleted_at' => 'Deleted At',
    'created_by' => 'Created By',
    'updated_by' => 'Updated By',
    'reference_code' => 'Reference Code',
    'reference' => 'Reference',

    // Product attributes
    'product_id' => 'Product',
    'variant' => 'Variant',
    'base_unit' => 'Base Unit',
    'base_unit_name' => 'Base Unit Name',
    'reorder_point' => 'Reorder Point',
    'attributes' => 'Attributes',
    'images' => 'Images',

    // Warehouse attributes
    'warehouse' => 'Warehouse',
    'from_warehouse' => 'From Warehouse',
    'to_warehouse' => 'To Warehouse',
    'warehouse_id' => 'Warehouse ID',

    // Transfer attributes
    'product_variant_id' => 'Product Variant',
    'unit_name' => 'Unit',
    'unit_ratio' => 'Ratio',
    'qty' => 'Quantity',
    'base_qty' => 'Base Quantity',
    'requested_qty' => 'Requested Qty',
    'requested_base_qty' => 'Requested Base Qty',
    'approved_qty' => 'Approved Qty',
    'approved_base_qty' => 'Approved Base Qty',
    'approved_unit_name' => 'Approved Unit',
    'approved_unit_ratio' => 'Approved Ratio',
    'shipped_base_qty' => 'Shipped Base Qty',
    'received_good_base_qty' => 'Received Good Base Qty',
    'received_damaged_base_qty' => 'Received Damaged Base Qty',
    'dispatched_base_qty' => 'Dispatched Base Qty',
    'substitute_product_variant_id' => 'Substitute Variant',

    // Movement attributes
    'type' => 'Type',
    'quantity' => 'Quantity',
    'unit_name_used' => 'Unit Used',
    'unit_ratio_used' => 'Ratio Used',
    'related_movement_id' => 'Related Movement',
    'reference_type' => 'Reference Type',
    'reference_id' => 'Reference ID',

    // User attributes
    'user' => 'User',
    'role' => 'Role',
    'warehouses' => 'Warehouses',

    // Supplier/Customer attributes
    'supplier' => 'Supplier',
    'customer' => 'Customer',
    'contact_person' => 'Contact Person',

    // Order attributes
    'order_date' => 'Order Date',
    'expected_date' => 'Expected Date',
    'total' => 'Total',
    'line_items' => 'Line Items',

    // Price attributes
    'price_type' => 'Price Type',
    'cost_price' => 'Cost Price',
    'sale_price' => 'Sale Price',
    'unit_cost_price' => 'Unit Cost Price',
    'total_financial_loss' => 'Total Financial Loss',
    'loss_category' => 'Loss Category',

    // InTransit attributes
    'transfer_requisition' => 'Transfer Requisition',
    'dispatched_at' => 'Dispatched At',
    'cleared_at' => 'Cleared At',
];
