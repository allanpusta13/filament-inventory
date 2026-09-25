<?php

declare(strict_types=1);

return [
    // Common attributes
    'id' => 'ID',
    'name' => 'Pangalan',
    'email' => 'Email',
    'phone' => 'Telepono',
    'address' => 'Address',
    'code' => 'Kodigo',
    'description' => 'Deskripsyon',
    'status' => 'Katayuan',
    'created_at' => 'Nilikha noong',
    'updated_at' => 'Na-update noong',
    'deleted_at' => 'Binura noong',
    'is_active' => 'Active',
    'notes' => 'Mga tala',
    'reference' => 'Sanggunian',
    'reference_code' => 'Kodigo ng sanggunian',
    'quantity' => 'Dami',
    'price' => 'Presyo',
    'cost' => 'Gastos',
    'total' => 'Kabuuan',

    // Product attributes
    'sku' => 'SKU',
    'barcode' => 'Barcode',
    'base_unit' => 'Base unit',
    'reorder_point' => 'Punto ng reorder',
    'variant_name' => 'Pangalan ng variant',
    'variant_sku' => 'SKU ng variant',
    'unit_name' => 'Pangalan ng unit',
    'unit_ratio' => 'Ratio ng unit',
    'current_stock' => 'Kasalukuyang stock',

    // Warehouse attributes
    'warehouse_code' => 'Kodigo ng bodega',
    'warehouse_name' => 'Pangalan ng bodega',
    'warehouse_location' => 'Lokasyon',
    'warehouse_active' => 'Active na bodega',

    // Transfer attributes
    'from_warehouse' => 'Mula sa bodega',
    'to_warehouse' => 'Sa bodega',
    'transferred_by' => 'Inilipat ni',
    'transferred_at' => 'Inilipat noong',
    'items' => 'Mga item',
    'items_count' => 'Bilang ng linya',
    'line_notes' => 'Mga tala ng linya',
    'transfer_notes' => 'Mga tala ng transfer',

    // Purchase Order attributes
    'supplier' => 'Supplier',
    'receiving_warehouse' => 'Tumatanggap na bodega',
    'ordered_at' => 'In-order noong',
    'received_at' => 'Natanggap noong',

    // Sales Order attributes
    'customer' => 'Kustomer',
    'dispatch_warehouse' => 'Bodega ng pagpapadala',
    'confirmed_at' => 'Kinumpirma noong',
    'dispatched_at' => 'Ipinadala noong',

    // Stock Movement attributes
    'movement_type' => 'Uri ng galaw',
    'product_variant' => 'Variant ng produkto',
    'related_movement' => 'Kaugnay na galaw',

    // InTransit attributes
    'requisition' => 'Requisition',
    'dispatched_base' => 'Ipinadala (base)',
    'dispatched_at' => 'Ipinadala noong',
    'cleared_at' => 'Na-clear noong',

    // Loss Ledger attributes
    'lost_qty' => 'Natapusang dami',
    'unit_cost' => 'Cost kada unit',
    'total_loss' => 'Kabuuang loss',
    'category' => 'Kategorya',
    'recorded_at' => 'Naitala noong',
    'recorded_by' => 'Naitala ni',

    // User attributes
    'user_name' => 'Pangalan',
    'user_email' => 'Email',
    'user_role' => 'Rol',
    'user_warehouses' => 'Mga bodega',
    'user_active' => 'Active',
];
