<?php

declare(strict_types=1);

return [
    // Common form elements
    'required' => 'Obligatorio',
    'optional' => 'Opcional',
    'placeholder' => 'Seleccione...',
    'select_option' => 'Seleccionar una opción',
    'search' => 'Buscar...',
    'no_results' => 'Sin resultados',
    'loading' => 'Cargando...',
    'save' => 'Guardar',
    'cancel' => 'Cancelar',
    'submit' => 'Enviar',
    'reset' => 'Restablecer',
    'add' => 'Agregar',
    'remove' => 'Eliminar',
    'edit' => 'Editar',
    'view' => 'Ver',
    'delete' => 'Eliminar',
    'confirm' => 'Confirmar',
    'back' => 'Volver',
    'next' => 'Siguiente',
    'previous' => 'Anterior',
    'finish' => 'Finalizar',

    // Validation messages
    'validation_required' => 'Este campo es obligatorio',
    'validation_min' => 'Mínimo :min caracteres',
    'validation_max' => 'Máximo :max caracteres',
    'validation_numeric' => 'Debe ser un número',
    'validation_integer' => 'Debe ser un número entero',
    'validation_email' => 'Correo electrónico inválido',
    'validation_date' => 'Fecha inválida',
    'validation_unique' => 'Este valor ya existe',
    'validation_exists' => 'El valor seleccionado no es válido',
    'validation_confirmed' => 'La confirmación no coincide',

    // Direct Transfer form
    'direct_transfer' => [
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

    // Transfer Requisition form
    'transfer_requisition' => [
        'step1_title' => 'Enrutamiento',
        'step1_desc' => 'Seleccione almacén de origen y destino.',
        'step2_title' => 'Ítems',
        'step2_desc' => 'Agregue variantes de producto y cantidades solicitadas.',
        'step3_title' => 'Revisar',
        'step3_desc' => 'Revise la requisición completa antes de enviar.',

        'fields' => [
            'from_warehouse' => 'Desde almacén',
            'to_warehouse' => 'Hacia almacén',
            'items' => 'Ítems',
            'product_variant' => 'Variante de producto',
            'unit_name' => 'Unidad',
            'unit_ratio' => 'Proporción (a base)',
            'qty' => 'Cantidad',
            'base_qty' => 'Cantidad base',
            'line_notes' => 'Notas de línea',
        ],
    ],

    // Purchase Order form
    'purchase_order' => [
        'step1_title' => 'Proveedor y almacén',
        'step1_desc' => 'Seleccione proveedor y almacén de recepción.',
        'step2_title' => 'Líneas del pedido',
        'step2_desc' => 'Agregue variantes de producto, unidades y cantidades.',
        'step3_title' => 'Revisar y notas',
        'step3_desc' => 'Revise el pedido completo y agregue notas.',

        'fields' => [
            'supplier' => 'Proveedor',
            'warehouse' => 'Almacén de recepción',
            'items' => 'Líneas',
            'product_variant' => 'Variante de producto',
            'unit_name' => 'Unidad',
            'unit_ratio' => 'Proporción (a base)',
            'qty' => 'Cantidad',
            'base_qty' => 'Cantidad base',
            'unit_cost' => 'Costo unitario',
            'line_notes' => 'Notas de línea',
            'order_notes' => 'Notas del pedido',
        ],
    ],

    // Sales Order form
    'sales_order' => [
        'step1_title' => 'Cliente y almacén',
        'step1_desc' => 'Seleccione cliente y almacén de despacho.',
        'step2_title' => 'Líneas (vista previa precio de venta)',
        'step2_desc' => 'Revise las líneas con precios de venta capturados.',
        'step3_title' => 'Revisar y notas',
        'step3_desc' => 'Revise el pedido completo y agregue notas.',

        'fields' => [
            'customer' => 'Cliente',
            'warehouse' => 'Almacén de despacho',
            'items' => 'Líneas',
            'product_variant' => 'Variante de producto',
            'unit_name' => 'Unidad',
            'unit_ratio' => 'Proporción (a base)',
            'qty' => 'Cantidad',
            'base_qty' => 'Cantidad base',
            'sale_price' => 'Precio de venta',
            'line_notes' => 'Notas de línea',
            'order_notes' => 'Notas del pedido',
        ],
    ],

    // Product form
    'product' => [
        'sections' => [
            'details' => 'Detalles',
            'inventory' => 'Inventario',
            'pricing' => 'Precios',
        ],
        'fields' => [
            'sku' => 'SKU',
            'barcode' => 'Código de barras',
            'name' => 'Nombre',
            'description' => 'Descripción',
            'base_unit' => 'Unidad base',
            'reorder_point' => 'Punto de reorden',
            'is_active' => 'Activo',
        ],
    ],

    // Warehouse form
    'warehouse' => [
        'sections' => [
            'details' => 'Detalles',
        ],
        'fields' => [
            'code' => 'Código',
            'name' => 'Nombre',
            'location' => 'Ubicación',
            'is_active' => 'Activo',
        ],
    ],

    // Supplier form
    'supplier' => [
        'fields' => [
            'name' => 'Nombre',
            'contact_person' => 'Persona de contacto',
            'email' => 'Correo electrónico',
            'phone' => 'Teléfono',
            'address' => 'Dirección',
        ],
    ],

    // Customer form
    'customer' => [
        'fields' => [
            'name' => 'Nombre',
            'contact_person' => 'Persona de contacto',
            'email' => 'Correo electrónico',
            'phone' => 'Teléfono',
            'address' => 'Dirección',
        ],
    ],

    // User form
    'user' => [
        'fields' => [
            'name' => 'Nombre',
            'email' => 'Correo electrónico',
            'role' => 'Rol',
            'warehouses' => 'Almacenes asignados',
            'password' => 'Contraseña',
            'password_confirmation' => 'Confirmar contraseña',
        ],
    ],
];
