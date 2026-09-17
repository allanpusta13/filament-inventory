<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Enum Translations
    |--------------------------------------------------------------------------
    */

    // TransferRequisitionStatus
    'Draft' => 'Draft',
    'Requested' => 'Requested',
    'Under review (fulfiller)' => 'Under review (fulfiller)',
    'Under review (requestor)' => 'Under review (requestor)',
    'Confirmed' => 'Confirmed',
    'Dispatched' => 'Dispatched',
    'Partially received' => 'Partially received',
    'Completed' => 'Completed',
    'Closed with loss' => 'Closed with loss',
    'Cancelled' => 'Cancelled',

    // InTransitStatus
    'In transit' => 'In transit',
    'Cleared' => 'Cleared',

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
