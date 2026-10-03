<?php

declare(strict_types=1);

/**
 * Backed enum labels (§4.x / §0A.2a).
 *
 * Every enum case routes its human-readable label through these keys
 * (§0A.9). Enum backing values (the keys below) remain stable and
 * untranslated (§0A.1).
 *
 * `user_role.branch_manager` is an owner-direction extension — see the
 * UserRole enum docblock for the open-semantics warning.
 */
return [
    'transfer_requisition_status' => [
        'draft' => 'Draft',
        'requested' => 'Requested',
        'under_review_fulfiller' => 'Under review (fulfiller)',
        'under_review_requestor' => 'Under review (requestor)',
        'confirmed' => 'Confirmed',
        'dispatched' => 'Dispatched',
        'partially_received' => 'Partially received',
        'completed' => 'Completed',
        'closed_with_loss' => 'Closed with loss',
        'cancelled' => 'Cancelled',
    ],

    'purchase_order_status' => [
        'draft' => 'Draft',
        'ordered' => 'Ordered',
        'partially_received' => 'Partially received',
        'received' => 'Received',
        'cancelled' => 'Cancelled',
    ],

    'sales_order_status' => [
        'draft' => 'Draft',
        'confirmed' => 'Confirmed',
        'partially_dispatched' => 'Partially dispatched',
        'dispatched' => 'Dispatched',
        'cancelled' => 'Cancelled',
    ],

    'stock_movement_type' => [
        'transfer_in' => 'Transfer in',
        'transfer_out' => 'Transfer out',
        'purchase' => 'Purchase',
        'purchase_return' => 'Purchase return',
        'sale' => 'Sale',
        'sale_return' => 'Sale return',
        'adjustment' => 'Adjustment',
        'loss' => 'Loss',
        'damage' => 'Damage',
    ],

    'revision_status' => [
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
    ],

    'negotiation_side' => [
        'requestor' => 'Requestor',
        'fulfiller' => 'Fulfiller',
    ],

    'in_transit_status' => [
        'in_transit' => 'In transit',
        'cleared' => 'Cleared',
        'lost' => 'Lost',
    ],

    'loss_category' => [
        'shortfall' => 'Shortfall',
        'damage' => 'Damage',
        'spoilage' => 'Spoilage',
        'theft' => 'Theft',
        'other' => 'Other',
    ],

    'user_role' => [
        'admin' => 'Admin',
        'auditor' => 'Auditor',
        'warehouse_staff' => 'Warehouse staff',
        // Owner-direction extension — capability matrix pending.
        'branch_manager' => 'Branch manager',
    ],
];
