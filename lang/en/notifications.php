<?php

declare(strict_types=1);

/**
 * Domain notification bodies (§22.3b / §0A.2a).
 *
 * Each key resolves to a `.body` entry, matching how the §22.3b
 * notification classes call `__('notifications.<key>.body', [...])`.
 *
 * `purchase_order_cancelled.body` is a gap-fill: §0A.2a does not list
 * it, but the §6.4 service fires the `PurchaseOrderCancelled` event and
 * the gap-fill `PurchaseOrderCancelledNotification` resolves it. Every
 * additional locale must carry the same key (§0A.15
 * TranslationKeyParityTest).
 */
return [
    'transfer_confirmed' => [
        'body' => 'Transfer :reference has been confirmed.',
    ],

    'transfer_cancelled' => [
        'body' => 'Transfer :reference has been cancelled.',
    ],

    'transfer_dispatched' => [
        'body' => 'Transfer :reference has been dispatched.',
    ],

    'transfer_received' => [
        'body' => 'Transfer :reference has been received.',
    ],

    'loss_recorded' => [
        'body' => 'Loss of :qty base units recorded for :sku.',
    ],

    'purchase_order_received' => [
        'body' => 'Purchase order :reference has been received.',
    ],

    // Gap-fill — see class docblock of PurchaseOrderCancelledNotification.
    'purchase_order_cancelled' => [
        'body' => 'Purchase order :reference has been cancelled.',
    ],

    'sales_order_dispatched' => [
        'body' => 'Sales order :reference has been dispatched.',
    ],

    'inventory_below_reorder_point' => [
        'body' => ':sku is below its reorder point in :warehouse.',
    ],
];
