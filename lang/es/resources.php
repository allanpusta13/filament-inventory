<?php

declare(strict_types=1);

return [
    // Product resource
    'products' => [
        'model' => [
            'singular' => 'Producto',
            'plural' => 'Productos',
        ],
        'fields' => [
            'sku' => 'SKU',
            'barcode' => 'Código de barras',
            'base_unit' => 'Unidad base',
            'reorder_point' => 'Punto de reorden',
        ],
        'sections' => [
            'inventory' => 'Inventario',
            'pricing' => 'Precios',
        ],
        'actions' => [
            'manage_units' => 'Gestionar unidades',
            'set_price' => 'Establecer precio',
            'quick_adjustment' => 'Ajuste rápido',
        ],
        'notifications' => [
            'created' => 'Producto creado correctamente.',
            'updated' => 'Producto actualizado correctamente.',
        ],
        'wizard' => [
            'review' => 'Revisar',
        ],
    ],

    // Transfer Requisition resource
    'transfer_requisitions' => [
        'model' => [
            'singular' => 'Requisición de transferencia',
            'plural' => 'Requisiciones de transferencia',
        ],
        'fields' => [
            'from_warehouse' => 'Desde almacén',
            'to_warehouse' => 'Hacia almacén',
        ],
        'steps' => [
            'routing' => 'Enrutamiento',
            'items' => 'Ítems',
            'review' => 'Revisar',
        ],
        'actions' => [
            'dispatch' => 'Despachar',
            'receive' => 'Recibir',
            'confirm' => 'Confirmar',
            'negotiate' => 'Negociar',
        ],
        'notifications' => [
            'created' => 'Requisición de transferencia creada.',
            'confirmed' => 'Requisición de transferencia confirmada.',
            'dispatched' => 'Requisición de transferencia despachada.',
            'received' => 'Requisición de transferencia recibida.',
            'completed' => 'Requisición de transferencia completada.',
            'cancelled' => 'Requisición de transferencia cancelada.',
        ],
    ],

    // Direct Transfers resource
    'direct_transfers' => [
        'model' => [
            'singular' => 'Transferencia directa',
            'plural' => 'Transferencias directas',
        ],
        'page' => [
            'create_heading' => 'Crear transferencia directa',
            'create_description' => 'Ejecutar transferencia directa multi-línea entre almacenes',
            'execute_transfer' => 'Ejecutar transferencia',
        ],
        'form' => [
            'step1_title' => 'Mapeo de ubicaciones',
            'step1_desc' => 'Seleccione almacenes de origen y destino para la transferencia.',
            'step2_title' => 'Asignación de stock',
            'step2_desc' => 'Agregue variantes de producto y cantidades a transferir.',
            'step3_title' => 'Revisar y verificar',
            'step3_desc' => 'Revise el resumen completo de la transferencia antes de ejecutar.',

            'sections' => [
                'routing' => 'Enrutamiento de almacén',
            ],

            'fields' => [
                'origin_warehouse' => 'Almacén origen',
                'destination_warehouse' => 'Almacén destino',
                'items' => 'Ítems',
                'product_variant' => 'Variante de producto',
                'unit_name' => 'Unidad',
                'unit_ratio' => 'Proporción (a base)',
                'qty' => 'Cantidad',
                'base_qty' => 'Cantidad base (calculada)',
                'line_notes' => 'Notas de línea',
                'line_notes_placeholder' => 'Notas opcionales para la línea',
                'transfer_notes' => 'Notas de transferencia',
                'transfer_notes_placeholder' => 'Notas para toda la transferencia',
            ],

            'hints' => [
                'ratio_auto' => 'Se completa automáticamente según la unidad seleccionada',
            ],

            'add_line' => 'Agregar línea',

            'review_verify' => 'Revisar y verificar',

            'complete_previous_steps' => 'Complete los pasos anteriores para revisar el resumen.',
            'origin' => 'Origen',
            'destination' => 'Destino',
            'sku' => 'SKU',
            'variant' => 'Variante',
            'qty_unit' => 'Cant / Unidad',
            'base_units' => 'Unidades base',
        ],

        'table' => [
            'reference_code' => 'Referencia',
            'reference' => 'Referencia',
            'from_warehouse' => 'Desde',
            'to_warehouse' => 'Hacia',
            'transferred_by' => 'Transferido por',
            'items_count' => 'Líneas',
            'items' => 'Líneas',
            'transferred_at' => 'Transferido el',
            'filter_from_warehouse' => 'Almacén origen',
            'filter_to_warehouse' => 'Almacén destino',
        ],

        'notifications' => [
            'transfer_executed' => 'Transferencia Directa Ejecutada',
            'transfer_completed' => 'La transferencia directa :code se ha completado correctamente.',
        ],

        'actions' => [
            'view' => 'Ver',
        ],

        'fields' => [
            'reference_code' => 'Código de referencia',
            'transferred_at' => 'Transferido el',
            'from_warehouse' => 'Almacén origen',
            'to_warehouse' => 'Almacén destino',
            'transferred_by' => 'Transferido por',
            'sku' => 'SKU',
            'qty' => 'Cantidad',
            'ratio' => 'Proporción',
            'base_qty' => 'Cantidad base',
        ],
    ],

    // InTransit resource
    'in_transits' => [
        'model' => [
            'singular' => 'En Tránsito',
            'plural' => 'En Tránsitos',
        ],
        'fields' => [
            'requisition' => 'Requisición',
            'sku' => 'SKU',
            'dispatched_base' => 'Despachado (Base)',
            'dispatched_at' => 'Despachado el',
            'status' => 'Estado',
            'cleared_at' => 'Despejado el',
        ],
    ],

    // Stock Movement resource
    'stock_movements' => [
        'model' => [
            'singular' => 'Movimiento de Stock',
            'plural' => 'Movimientos de Stock',
        ],
        'fields' => [
            'type' => 'Tipo',
            'quantity' => 'Cantidad',
            'warehouse' => 'Almacén',
            'product_variant' => 'Variante de Producto',
            'reference' => 'Referencia',
            'related_movement' => 'Movimiento Relacionado',
            'created_at' => 'Creado el',
        ],
    ],

    // Loss Ledger resource
    'loss_ledgers' => [
        'model' => [
            'singular' => 'Registro de Pérdidas',
            'plural' => 'Registros de Pérdidas',
        ],
        'fields' => [
            'requisition' => 'Requisición',
            'variant' => 'Variante',
            'lost_qty' => 'Cantidad Perdida',
            'unit_cost' => 'Costo Unitario',
            'total_loss' => 'Pérdida Total',
            'category' => 'Categoría',
            'recorded_at' => 'Registrado el',
            'recorded_by' => 'Registrado por',
        ],
    ],

    // Warehouse resource
    'warehouses' => [
        'model' => [
            'singular' => 'Almacén',
            'plural' => 'Almacenes',
        ],
        'fields' => [
            'code' => 'Código',
            'name' => 'Nombre',
            'location' => 'Ubicación',
            'is_active' => 'Activo',
        ],
        'sections' => [
            'details' => 'Detalles',
        ],
    ],

    // User resource
    'users' => [
        'model' => [
            'singular' => 'Usuario',
            'plural' => 'Usuarios',
        ],
        'fields' => [
            'name' => 'Nombre',
            'email' => 'Correo',
            'role' => 'Rol',
            'warehouses' => 'Almacenes',
        ],
    ],

    // Purchase Order resource
    'purchase_orders' => [
        'model' => [
            'singular' => 'Orden de Compra',
            'plural' => 'Órdenes de Compra',
        ],
        'fields' => [
            'supplier' => 'Proveedor',
            'warehouse' => 'Almacén de Recepción',
        ],
        'steps' => [
            'supplier_warehouse' => 'Proveedor y Almacén',
            'line_items' => 'Líneas del Pedido',
            'review_verify' => 'Revisar y Notas',
        ],
        'actions' => [
            'receive' => 'Recibir',
            'update_cost' => 'Actualizar Costo',
        ],
        'notifications' => [
            'created' => 'Orden de compra creada.',
            'confirmed' => 'Orden de compra confirmada.',
            'received' => 'Orden de compra recibida.',
            'cancelled' => 'Orden de compra cancelada.',
        ],
    ],

    // Supplier resource
    'suppliers' => [
        'model' => [
            'singular' => 'Proveedor',
            'plural' => 'Proveedores',
        ],
        'fields' => [
            'name' => 'Nombre',
            'contact_person' => 'Persona de Contacto',
            'email' => 'Correo',
            'phone' => 'Teléfono',
            'address' => 'Dirección',
        ],
    ],

    // Sales Order resource
    'sales_orders' => [
        'model' => [
            'singular' => 'Orden de Venta',
            'plural' => 'Órdenes de Venta',
        ],
        'fields' => [
            'customer' => 'Cliente',
            'warehouse' => 'Almacén de Despacho',
        ],
        'steps' => [
            'customer_warehouse' => 'Cliente y Almacén',
            'line_items' => 'Líneas (Vista Previa Precio de Venta)',
            'review_verify' => 'Revisar y Notas',
        ],
        'actions' => [
            'dispatch' => 'Despachar',
            'record_return' => 'Registrar Devolución',
        ],
        'notifications' => [
            'created' => 'Orden de venta creada.',
            'confirmed' => 'Orden de venta confirmada.',
            'dispatched' => 'Orden de venta despachada.',
        ],
    ],

    // Customer resource
    'customers' => [
        'model' => [
            'singular' => 'Cliente',
            'plural' => 'Clientes',
        ],
        'fields' => [
            'name' => 'Nombre',
            'contact_person' => 'Persona de Contacto',
            'email' => 'Correo',
            'phone' => 'Teléfono',
            'address' => 'Dirección',
        ],
    ],
];
