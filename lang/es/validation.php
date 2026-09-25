<?php

declare(strict_types=1);

return [
    // Common validation
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser una cadena de texto.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'numeric' => 'El campo :attribute debe ser un número.',
    'min' => [
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
    ],
    'max' => [
        'string' => 'El campo :attribute no debe exceder :max caracteres.',
        'numeric' => 'El campo :attribute no debe exceder :max.',
    ],
    'unique' => 'El campo :attribute ya ha sido tomado.',
    'exists' => 'El :attribute seleccionado no es válido.',
    'in' => 'El :attribute seleccionado no es válido.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'email' => 'El campo :attribute debe ser una dirección de correo válida.',
    'date' => 'El campo :attribute debe ser una fecha válida.',
    'before' => 'El campo :attribute debe ser una fecha anterior a :date.',
    'after' => 'El campo :attribute debe ser una fecha posterior a :date.',

    // Custom validation messages
    'direct_transfers' => [
        'same_warehouse' => 'El almacén de origen y destino deben ser diferentes.',
        'no_items' => 'Se requiere al menos un ítem para la transferencia.',
        'missing_fields' => 'Todos los campos del ítem son obligatorios.',
        'invalid_unit_ratio' => 'La proporción de unidad debe ser al menos 1. Recibido: :ratio.',
        'invalid_qty' => 'La cantidad debe ser al menos 1. Recibido: :qty.',
        'invalid_base_qty' => 'La cantidad base debe ser al menos 1. Recibido: :base_qty.',
        'insufficient_stock' => 'Stock insuficiente para :sku en almacén origen. Disponible: :available, Solicitado: :requested.',
    ],

    'transfer_requisition' => [
        'same_warehouse' => 'El almacén de origen y destino deben ser diferentes.',
        'not_confirmed' => 'La requisición debe estar confirmada antes de despachar.',
        'not_dispatched' => 'La requisición no está en estado recepcionable.',
        'no_approved_qty' => 'El ítem no tiene cantidad aprobada. La acción de confirmar debe materializar los campos aprobados antes de despachar.',
        'insufficient_stock' => 'Stock insuficiente en almacén origen para la requisición.',
    ],

    'purchase_order' => [
        'not_ordered' => 'La orden de compra debe estar en estado ordenado antes de recibir.',
        'no_items' => 'Se requiere al menos una línea de pedido.',
    ],

    'sales_order' => [
        'not_confirmed' => 'La orden de venta debe estar confirmada antes de despachar.',
        'no_items' => 'Se requiere al menos una línea de pedido.',
        'insufficient_stock' => 'Stock insuficiente para el despacho. Disponible: :available, Solicitado: :requested.',
    ],

    'inventory' => [
        'insufficient_stock' => 'Stock insuficiente. Disponible: :available, Solicitado: :requested.',
        'invalid_adjustment_type' => 'Tipo de ajuste inválido. Debe ser uno de: adjustment, receive, ship.',
    ],

    'scan' => [
        'invalid_payload' => 'Carga de escaneo inválida.',
        'requisition_not_receivable' => 'La requisición no está en estado recepcionable.',
        'duplicate_scan' => 'Escaneo duplicado detectado.',
    ],
];
