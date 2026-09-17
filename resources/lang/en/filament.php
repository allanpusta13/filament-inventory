<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Filament Resource Translations
    |--------------------------------------------------------------------------
    */

    // Common labels
    'ORIGIN WAREHOUSE' => 'Origin Warehouse',
    'DESTINATION WAREHOUSE' => 'Destination Warehouse',
    'ORIGIN WAREHOUSE (FULFILLER)' => 'Origin Warehouse (Fulfiller)',
    'DESTINATION WAREHOUSE (REQUESTOR)' => 'Destination Warehouse (Requestor)',
    'PRODUCT VARIANT' => 'Product Variant',
    'PRODUCT VARIANT (SKU)' => 'Product Variant (SKU)',
    'PACKAGING FORMAT' => 'Packaging Format',
    'UNIT RATIO' => 'Unit Ratio',
    'ORDER QUANTITY' => 'Order Quantity',
    'AUDIT NOTES' => 'Audit Notes',
    'BASE UNITS TO TRANSFER' => 'Base Units to Transfer',
    'REVIEW & VERIFY' => 'Review & Verify',
    'VERIFY TRANSFER REQUISITION DETAILS' => 'Verify Transfer Requisition Details',
    'REQUESTED MATERIAL MANIFEST' => 'Requested Material Manifest',

    // Steps
    'Location Mapping' => 'Location Mapping',
    'Stock Allocation' => 'Stock Allocation',
    'Review & Confirm' => 'Review & Confirm',
    'Warehouse Location Routing' => 'Warehouse Location Routing',
    'Line Items & Packaging Formats' => 'Line Items & Packaging Formats',
    'Reactive Live Verification Summary' => 'Reactive Live Verification Summary',

    // Step descriptions
    'Select origin and destination warehouses' => 'Select origin and destination warehouses',
    'Select variant and quantity to transfer' => 'Select variant and quantity to transfer',
    'Verify all details before executing transfer' => 'Verify all details before executing transfer',
    'Select SKU variants, packaging formats, conversion ratios, and quantities.' => 'Select SKU variants, packaging formats, conversion ratios, and quantities.',

    // Validation messages
    'Destination warehouse cannot match origin warehouse.' => 'Destination warehouse cannot match origin warehouse.',
    'Destination warehouse cannot match the origin warehouse.' => 'Destination warehouse cannot match the origin warehouse.',

    // Placeholders
    'e.g., 100' => 'e.g., 100',
    'Reason for transfer, approval ref, etc.' => 'Reason for transfer, approval ref, etc.',
    'Box' => 'Box',
    'Complete previous steps to construct the verification sheet.' => 'Complete previous steps to construct the verification sheet.',

    // Helper text
    'Base units per package.' => 'Base units per package.',

    // Table headers (Review step)
    'Fulfilling Origin' => 'Fulfilling Origin',
    'Receiving Destination' => 'Receiving Destination',
    'SKU' => 'SKU',
    'Packaging' => 'Packaging',
    'Ratio' => 'Ratio',
    'Qty' => 'Qty',
    'Computed Base' => 'Computed Base',
    'Pcs' => 'Pcs',

    // Navigation groups
    'CATALOG' => 'Catalog',
    'OPERATIONS' => 'Operations',
    'SYSTEM ADMIN' => 'System Admin',
];
