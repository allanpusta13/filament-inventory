<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Filament Resource Translations (Español)
    |--------------------------------------------------------------------------
    */

    // Common labels
    'ORIGIN WAREHOUSE' => 'Almacén de Origen',
    'DESTINATION WAREHOUSE' => 'Almacén de Destino',
    'ORIGIN WAREHOUSE (FULFILLER)' => 'Almacén de Origen (Proveedor)',
    'DESTINATION WAREHOUSE (REQUESTOR)' => 'Almacén de Destino (Solicitante)',
    'PRODUCT VARIANT' => 'Variante de Producto',
    'PRODUCT VARIANT (SKU)' => 'Variante de Producto (SKU)',
    'PACKAGING FORMAT' => 'Formato de Empaque',
    'UNIT RATIO' => 'Ratio de Unidad',
    'ORDER QUANTITY' => 'Cantidad Ordenada',
    'AUDIT NOTES' => 'Notas de Auditoría',
    'BASE UNITS TO TRANSFER' => 'Unidades Base a Transferir',
    'REVIEW & VERIFY' => 'Revisar y Verificar',
    'VERIFY TRANSFER REQUISITION DETAILS' => 'Verificar Detalles de Requisición de Transferencia',
    'REQUESTED MATERIAL MANIFEST' => 'Manifiesto de Material Solicitado',

    // Steps
    'Location Mapping' => 'Mapeo de Ubicaciones',
    'Stock Allocation' => 'Asignación de Stock',
    'Review & Confirm' => 'Revisar y Confirmar',
    'Warehouse Location Routing' => 'Enrutamiento de Ubicaciones de Almacén',
    'Line Items & Packaging Formats' => 'Líneas y Formatos de Empaque',
    'Reactive Live Verification Summary' => 'Resumen de Verificación Reactiva en Vivo',

    // Step descriptions
    'Select origin and destination warehouses' => 'Seleccionar almacenes de origen y destino',
    'Select variant and quantity to transfer' => 'Seleccionar variante y cantidad a transferir',
    'Verify all details before executing transfer' => 'Verificar todos los detalles antes de ejecutar la transferencia',
    'Select SKU variants, packaging formats, conversion ratios, and quantities.' => 'Seleccionar variantes SKU, formatos de empaque, ratios de conversión y cantidades.',

    // Validation messages
    'Destination warehouse cannot match origin warehouse.' => 'El almacén de destino no puede coincidir con el de origen.',
    'Destination warehouse cannot match the origin warehouse.' => 'El almacén de destino no puede coincidir con el de origen.',

    // Placeholders
    'e.g., 100' => 'ej., 100',
    'Reason for transfer, approval ref, etc.' => 'Motivo de transferencia, ref. de aprobación, etc.',
    'Box' => 'Caja',
    'Complete previous steps to construct the verification sheet.' => 'Complete los pasos previos para construir la hoja de verificación.',

    // Helper text
    'Base units per package.' => 'Unidades base por paquete.',

    // Table headers (Review step)
    'Fulfilling Origin' => 'Origen Proveedor',
    'Receiving Destination' => 'Destino Receptor',
    'SKU' => 'SKU',
    'Packaging' => 'Empaque',
    'Ratio' => 'Ratio',
    'Qty' => 'Cant.',
    'Computed Base' => 'Base Calculada',
    'Pcs' => 'Unds',

    // Navigation groups
    'CATALOG' => 'Catálogo',
    'OPERATIONS' => 'Operaciones',
    'SYSTEM ADMIN' => 'Admin Sistema',
];
