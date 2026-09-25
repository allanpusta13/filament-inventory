<?php

declare(strict_types=1);

return [
    // Common errors
    'not_found' => 'Recurso no encontrado.',
    'unauthorized' => 'No autorizado.',
    'forbidden' => 'Acceso prohibido.',
    'server_error' => 'Error interno del servidor.',
    'validation_error' => 'Error de validación.',
    'database_error' => 'Error de base de datos.',
    'network_error' => 'Error de red.',
    'timeout' => 'Tiempo de espera agotado.',

    // Direct Transfer errors
    'direct_transfer' => [
        'same_warehouse' => 'El almacén de origen y destino no pueden ser el mismo.',
        'empty_items' => 'La transferencia debe contener al menos un ítem.',
        'invalid_item' => 'Datos de ítem inválidos.',
        'insufficient_stock' => 'Stock insuficiente para :sku. Disponible: :available, Solicitado: :requested.',
        'variant_not_found' => 'Variante de producto no encontrada.',
        'warehouse_not_found' => 'Almacén no encontrado.',
        'execution_failed' => 'Error al ejecutar la transferencia.',
    ],

    // Transfer Requisition errors
    'transfer_requisition' => [
        'not_found' => 'Requisición no encontrada.',
        'not_confirmed' => 'La requisición debe estar confirmada.',
        'not_dispatched' => 'La requisición no ha sido despachada.',
        'already_received' => 'La requisición ya ha sido recibida.',
        'negotiation_failed' => 'Error en la negociación.',
    ],

    // Purchase Order errors
    'purchase_order' => [
        'not_found' => 'Orden de compra no encontrada.',
        'not_ordered' => 'La orden debe estar en estado ordenado.',
        'already_received' => 'La orden ya ha sido recibida.',
    ],

    // Sales Order errors
    'sales_order' => [
        'not_found' => 'Orden de venta no encontrada.',
        'not_confirmed' => 'La orden debe estar confirmada.',
        'already_dispatched' => 'La orden ya ha sido despachada.',
        'insufficient_stock' => 'Stock insuficiente para :sku. Disponible: :available, Solicitado: :requested.',
    ],

    // Inventory errors
    'inventory' => [
        'insufficient_stock' => 'Stock insuficiente.',
        'invalid_adjustment_type' => 'Tipo de ajuste inválido.',
        'variant_not_found' => 'Variante de producto no encontrada.',
        'warehouse_not_found' => 'Almacén no encontrado.',
    ],

    // Scan errors
    'scan' => [
        'invalid_payload' => 'Carga de escaneo inválida.',
        'requisition_not_receivable' => 'La requisición no está en estado recepcionable.',
        'duplicate_scan' => 'Escaneo duplicado detectado.',
        'idempotency_violation' => 'Violación de idempotencia.',
    ],

    // Authorization errors
    'authorization' => [
        'warehouse_scope' => 'No tiene acceso a este almacén.',
        'policy_denied' => 'La política deniega esta acción.',
        'admin_required' => 'Se requiere rol de administrador.',
    ],
];
