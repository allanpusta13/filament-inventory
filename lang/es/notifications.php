<?php

declare(strict_types=1);

return [
    // Common notifications
    'created' => 'Registro creado correctamente.',
    'updated' => 'Registro actualizado correctamente.',
    'deleted' => 'Registro eliminado correctamente.',
    'restored' => 'Registro restaurado correctamente.',
    'saved' => 'Cambios guardados correctamente.',
    'error' => 'Ha ocurrido un error. Inténtelo de nuevo.',
    'permission_denied' => 'No tiene permiso para realizar esta acción.',

    // Direct Transfer notifications
    'transfer_executed' => 'Transferencia ejecutada',
    'transfer_completed' => 'La transferencia directa :code se ha completado correctamente.',
    'transfer_failed' => 'La transferencia ha fallado. Revise los detalles e inténtelo de nuevo.',
    'insufficient_stock' => 'Stock insuficiente para :sku en almacén origen. Disponible: :available, Solicitado: :requested.',

    // Transfer Requisition notifications
    'requisition_created' => 'Requisición de transferencia creada.',
    'requisition_confirmed' => 'Requisición de transferencia confirmada.',
    'requisition_dispatched' => 'Requisición de transferencia despachada.',
    'requisition_received' => 'Requisición de transferencia recibida.',
    'requisition_completed' => 'Requisición de transferencia completada.',
    'requisition_cancelled' => 'Requisición de transferencia cancelada.',
    'negotiation_started' => 'Negociación iniciada.',
    'negotiation_accepted' => 'Negociación aceptada.',
    'negotiation_rejected' => 'Negociación rechazada.',

    // Purchase Order notifications
    'po_created' => 'Orden de compra creada.',
    'po_confirmed' => 'Orden de compra confirmada.',
    'po_received' => 'Orden de compra recibida.',
    'po_cancelled' => 'Orden de compra cancelada.',
    'cost_updated' => 'Precio de costo actualizado al recibir.',

    // Sales Order notifications
    'so_created' => 'Orden de venta creada.',
    'so_confirmed' => 'Orden de venta confirmada.',
    'so_dispatched' => 'Orden de venta despachada.',
    'return_recorded' => 'Devolución registrada.',

    // Inventory notifications
    'stock_adjusted' => 'Stock ajustado correctamente.',
    'price_set' => 'Precio establecido correctamente.',

    // Scan notifications
    'scan_success' => 'Escaneo procesado correctamente.',
    'scan_duplicate' => 'Escaneo duplicado detectado. No se realizaron cambios.',
    'scan_error' => 'Error en el escaneo. Inténtelo de nuevo.',

    // Authentication
    'login_success' => 'Inicio de sesión correcto.',
    'logout_success' => 'Cierre de sesión correcto.',
    'unauthorized' => 'Acceso no autorizado.',

    // Validation
    'validation_failed' => 'Validación fallida. Revise el formulario.',
];
