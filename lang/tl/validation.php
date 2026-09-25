<?php

declare(strict_types=1);

return [
    // Common validation
    'required' => 'Required ang :attribute field.',
    'string' => 'Dapat string ang :attribute.',
    'integer' => 'Dapat integer ang :attribute.',
    'numeric' => 'Dapat number ang :attribute.',
    'min' => [
        'string' => 'Minimum :min characters ang :attribute.',
        'numeric' => 'Minimum :min ang :attribute.',
    ],
    'max' => [
        'string' => 'Hindi dapat lumampas ng :max characters ang :attribute.',
        'numeric' => 'Hindi dapat lumampas ng :max ang :attribute.',
    ],
    'unique' => 'Taken na ang :attribute.',
    'exists' => 'Invalid ang selected :attribute.',
    'in' => 'Invalid ang selected :attribute.',
    'confirmed' => 'Hindi match ang :attribute confirmation.',
    'email' => 'Valid email address ang :attribute.',
    'date' => 'Valid date ang :attribute.',
    'before' => 'Dapat bago :date ang :attribute.',
    'after' => 'Dapat pagkatapos :date ang :attribute.',

    // Custom validation messages
    'direct_transfers' => [
        'same_warehouse' => 'Dapat magkaiba ang origin at destination warehouse.',
        'no_items' => 'Required ang at least one item para sa transfer.',
        'missing_fields' => 'Required lahat ng item fields.',
        'invalid_unit_ratio' => 'Minimum 1 ang unit ratio. Received: :ratio.',
        'invalid_qty' => 'Minimum 1 ang quantity. Received: :qty.',
        'invalid_base_qty' => 'Minimum 1 ang base quantity. Received: :base_qty.',
        'insufficient_stock' => 'Kulang stock para sa :sku sa origin. Available: :available, Hiningi: :requested.',
    ],

    'transfer_requisition' => [
        'same_warehouse' => 'Dapat magkaiba ang origin at destination warehouse.',
        'not_confirmed' => 'Dapat confirmed ang requisition bago ipadala.',
        'not_dispatched' => 'Hindi receivable state ang requisition.',
        'no_approved_qty' => 'Walang approved quantity ang item. Dapat ma-materialize ang approved fields bago dispatch.',
        'insufficient_stock' => 'Kulang stock sa origin warehouse para sa requisition.',
    ],

    'purchase_order' => [
        'not_ordered' => 'Dapat ordered state ang PO bago receive.',
        'no_items' => 'Required ang at least one line item.',
    ],

    'sales_order' => [
        'not_confirmed' => 'Dapat confirmed ang SO bago dispatch.',
        'no_items' => 'Required ang at least one line item.',
        'insufficient_stock' => 'Kulang stock para sa dispatch. Available: :available, Hiningi: :requested.',
    ],

    'inventory' => [
        'insufficient_stock' => 'Kulang stock. Available: :available, Hiningi: :requested.',
        'invalid_adjustment_type' => 'Invalid adjustment type. adjustment, receive, o ship lang.',
    ],

    'scan' => [
        'invalid_payload' => 'Invalid scan payload.',
        'requisition_not_receivable' => 'Hindi receivable state ang requisition.',
        'duplicate_scan' => 'Nadetect ang duplicate scan.',
    ],
];
