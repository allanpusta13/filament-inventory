<?php

declare(strict_types=1);

return [
    // Common table elements
    'actions' => 'Acciones',
    'no_records' => 'No se encontraron registros.',
    'loading' => 'Cargando...',
    'search_placeholder' => 'Buscar...',
    'filter_button' => 'Filtrar',
    'clear_filters' => 'Limpiar filtros',
    'showing' => 'Mostrando :from a :of de :total resultados',
    'per_page' => 'Por página: :count',
    'page' => 'Página :page',
    'of' => 'de :pages',

    // Direct Transfer table
    'reference' => 'Referencia',
    'reference_code' => 'Código de referencia',
    'from' => 'Desde',
    'to' => 'Hacia',
    'from_warehouse' => 'Almacén origen',
    'to_warehouse' => 'Almacén destino',
    'by' => 'Por',
    'transferred_by' => 'Transferido por',
    'transferred_at' => 'Transferido el',
    'items' => 'Ítems',
    'items_count' => 'Líneas',
    'sku' => 'SKU',
    'variant' => 'Variante',
    'qty_unit' => 'Cant / Unidad',
    'base_units' => 'Unidades base',
    'line_notes' => 'Notas de línea',
    'filter_from_warehouse' => 'Filtrar por almacén origen',
    'filter_to_warehouse' => 'Filtrar por almacén destino',

    // Stock Movement table
    'movement_type' => 'Tipo',
    'quantity' => 'Cantidad',
    'warehouse' => 'Almacén',
    'product_variant' => 'Variante de producto',
    'reference' => 'Referencia',
    'related_movement' => 'Movimiento relacionado',
    'created_at' => 'Creado el',

    // Product table
    'product_name' => 'Producto',
    'variant_name' => 'Variante',
    'base_unit' => 'Unidad base',
    'reorder_point' => 'Punto de reorden',
    'current_stock' => 'Stock actual',
    'price' => 'Precio',

    // Warehouse table
    'warehouse_code' => 'Código',
    'warehouse_name' => 'Nombre',
    'warehouse_location' => 'Ubicación',
    'warehouse_active' => 'Activo',

    // Transfer Requisition table
    'requisition_reference' => 'Referencia',
    'requisition_status' => 'Estado',
    'requisition_from' => 'Desde',
    'requisition_to' => 'Hacia',
    'requisition_requested_at' => 'Solicitado el',
    'requisition_confirmed_at' => 'Confirmado el',
    'requisition_dispatched_at' => 'Despachado el',
    'requisition_completed_at' => 'Completado el',

    // Purchase Order table
    'po_reference' => 'Referencia',
    'po_supplier' => 'Proveedor',
    'po_warehouse' => 'Almacén',
    'po_status' => 'Estado',
    'po_ordered_at' => 'Pedido el',
    'po_received_at' => 'Recibido el',
    'po_total' => 'Total',

    // Sales Order table
    'so_reference' => 'Referencia',
    'so_customer' => 'Cliente',
    'so_warehouse' => 'Almacén',
    'so_status' => 'Estado',
    'so_confirmed_at' => 'Confirmado el',
    'so_dispatched_at' => 'Despachado el',
    'so_total' => 'Total',

    // InTransit table
    'in_transit_requisition' => 'Requisición',
    'in_transit_dispatched' => 'Despachado',
    'in_transit_dispatched_at' => 'Despachado el',
    'in_transit_status' => 'Estado',

    // Loss Ledger table
    'loss_requisition' => 'Requisición',
    'loss_variant' => 'Variante',
    'loss_qty' => 'Cantidad perdida',
    'loss_cost' => 'Costo unitario',
    'loss_total' => 'Pérdida total',
    'loss_category' => 'Categoría',
    'loss_recorded_at' => 'Registrado el',
    'loss_recorded_by' => 'Registrado por',

    // User table
    'user_name' => 'Nombre',
    'user_email' => 'Correo',
    'user_role' => 'Rol',
    'user_warehouses' => 'Almacenes',
    'user_active' => 'Activo',
];
