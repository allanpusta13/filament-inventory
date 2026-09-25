<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Enum Translations
    |--------------------------------------------------------------------------
    */

    // TransferRequisitionStatus
    'transfer_requisition_status.draft' => 'Draft',
    'transfer_requisition_status.requested' => 'Requested',
    'transfer_requisition_status.under_review_fulfiller' => 'Under review (fulfiller)',
    'transfer_requisition_status.under_review_requestor' => 'Under review (requestor)',
    'transfer_requisition_status.confirmed' => 'Confirmed',
    'transfer_requisition_status.dispatched' => 'Dispatched',
    'transfer_requisition_status.partially_received' => 'Partially received',
    'transfer_requisition_status.completed' => 'Completed',
    'transfer_requisition_status.closed_with_loss' => 'Closed with loss',
    'transfer_requisition_status.cancelled' => 'Cancelled',

    // InTransitStatus
    'in_transit_status.in_transit' => 'In transit',
    'in_transit_status.partially_received' => 'Partially received',
    'in_transit_status.cleared' => 'Cleared',

    // MovementType
    'Receive' => 'Receive',
    'Ship' => 'Ship',
    'Transfer Out' => 'Transfer Out',
    'Transfer In' => 'Transfer In',
    'Transit Out' => 'Transit Out',
    'Transit In' => 'Transit In',
    'Adjustment' => 'Adjustment',
    'Loss' => 'Loss',

    // StockMovementType
    // Same as MovementType - reuses labels

    // NegotiationSide
    'Fulfiller' => 'Fulfiller',
    'Requestor' => 'Requestor',

    // PriceType
    'Cost' => 'Cost',
    'Sale' => 'Sale',

    // RevisionStatus
    'Pending' => 'Pending',
    'Accepted' => 'Accepted',
    'Rejected' => 'Rejected',
    'Superseded' => 'Superseded',

    // UserRole
    'Administrator' => 'Administrator',
    'Logistics Auditor' => 'Logistics Auditor',
    'Branch Manager' => 'Branch Manager',
    'Warehouse Staff' => 'Warehouse Staff',
];
