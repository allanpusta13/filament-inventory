<?php

declare(strict_types=1);

/**
 * Shipment Note print / scan views (§21 / §0A.2a).
 */
return [
    'print' => [
        'title' => 'Shipment note :ref',
        'heading' => 'Shipment note',
        'route' => ':from → :to',
        'status' => 'Status: :status',
        'variant' => 'Variant',
        'sku' => 'SKU',
        'qty' => 'Qty',
        'print_button' => 'Print',
    ],

    'scan' => [
        'title' => 'Scan intake :ref',
        'heading' => 'Scan intake',
        'route' => ':from → :to',
        'sku' => 'SKU',
        'received_good' => 'Received (good)',
        'received_damaged' => 'Received (damaged)',
        'submit' => 'Submit scan',
    ],
];
