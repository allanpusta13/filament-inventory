<?php

declare(strict_types=1);

return [
    // Common attributes
    'id' => 'ID',
    'name' => 'Nombre',
    'email' => 'Correo electrónico',
    'phone' => 'Teléfono',
    'address' => 'Dirección',
    'code' => 'Código',
    'description' => 'Descripción',
    'status' => 'Estado',
    'created_at' => 'Creado el',
    'updated_at' => 'Actualizado el',
    'deleted_at' => 'Eliminado el',
    'is_active' => 'Activo',
    'notes' => 'Notas',
    'reference' => 'Referencia',
    'reference_code' => 'Código de referencia',
    'quantity' => 'Cantidad',
    'price' => 'Precio',
    'cost' => 'Costo',
    'total' => 'Total',

    // Product attributes
    'sku' => 'SKU',
    'barcode' => 'Código de barras',
    'base_unit' => 'Unidad base',
    'reorder_point' => 'Punto de reorden',
    'variant_name' => 'Nombre de variante',
    'variant_sku' => 'SKU de variante',
    'unit_name' => 'Nombre de unidad',
    'unit_ratio' => 'Proporción de unidad',
    'current_stock' => 'Stock actual',

    // Warehouse attributes
    'warehouse_code' => 'Código de almacén',
    'warehouse_name' => 'Nombre de almacén',
    'warehouse_location' => 'Ubicación',
    'warehouse_active' => 'Almacén activo',

    // Transfer attributes
    'from_warehouse' => 'Almacén origen',
    'to_warehouse' => 'Almacén destino',
    'transferred_by' => 'Transferido por',
    'transferred_at' => 'Transferido el',
    'items' => 'Ítems',
    'items_count' => 'Líneas',
    'line_notes' => 'Notas de línea',
    'transfer_notes' => 'Notas de transferencia',

    // Purchase Order attributes
    'supplier' => 'Proveedor',
    'receiving_warehouse' => 'Almacén de recepción',
    'ordered_at' => 'Pedido el',
    'received_at' => 'Recibido el',

    // Sales Order attributes
    'customer' => 'Cliente',
    'dispatch_warehouse' => 'Almacén de despacho',
    'confirmed_at' => 'Confirmado el',
    'dispatched_at' => 'Despachado el',

    // Stock Movement attributes
    'movement_type' => 'Tipo de movimiento',
    'product_variant' => 'Variante de producto',
    'related_movement' => 'Movimiento relacionado',

    // InTransit attributes
    'requisition' => 'Requisición',
    'dispatched_base' => 'Despachado (base)',
    'dispatched_at' => 'Despachado el',
    'cleared_at' => 'Despejado el',

    // Loss Ledger attributes
    'lost_qty' => 'Cantidad perdida',
    'unit_cost' => 'Costo unitario',
    'total_loss' => 'Pérdida total',
    'category' => 'Categoría',
    'recorded_at' => 'Registrado el',
    'recorded_by' => 'Registrado por',

    // User attributes
    'user_name' => 'Nombre',
    'user_email' => 'Correo',
    'user_role' => 'Rol',
    'user_warehouses' => 'Almacenes',
    'user_active' => 'Activo',
];
