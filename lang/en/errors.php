<?php

declare(strict_types=1);

/**
 * Typed domain exception translations (§6.3 key catalogue / §0A.10).
 *
 * Every key carries a `.title` and `.body` entry. The presentation layer
 * resolves them from `$exception->translationKey()` +
 * `$exception->context()`.
 *
 * Keys are enumerated in §6.3; `errors.reference_code_exhausted` is a
 * gap-fill referenced by §7B.2 / §7C.2 but missing from the §0A.2a
 * canonical listing.
 */
return [
    'insufficient_stock' => [
        'title' => 'Insufficient stock',
        'body' => 'Requested :requested, available :available for variant :variant.',
    ],

    'outstanding_quantity_exceeded' => [
        'title' => 'Outstanding quantity exceeded',
        'body' => 'Attempted :attempted against outstanding :outstanding for item :item.',
    ],

    'invalid_document_state' => [
        'title' => 'Invalid document state',
        'body' => 'Action :action is not allowed while status is :status.',
    ],

    'invalid_revision_transition' => [
        'title' => 'Invalid revision transition',
        'body' => 'Cannot move revision from :actual to :target.',
    ],

    'product_family_has_variants' => [
        'title' => 'Product family has variants',
        'body' => 'Product :product still has variants and cannot be deleted.',
    ],

    'negotiation_not_allowed' => [
        'title' => 'Negotiation not allowed',
        'body' => 'Requisition :requisition cannot be negotiated while :status.',
    ],

    'revision_already_resolved' => [
        'title' => 'Revision already resolved',
        'body' => 'Revision :revision has already been resolved.',
    ],

    'invalid_movement_type' => [
        'title' => 'Invalid movement type',
        'body' => 'Movement type :type is not allowed here.',
    ],

    'invalid_unit_ratio' => [
        'title' => 'Invalid unit ratio',
        'body' => 'Unit ratio :ratio is invalid.',
    ],

    'same_warehouse_transfer' => [
        'title' => 'Same warehouse transfer',
        'body' => 'Source and destination warehouse :warehouse must differ.',
    ],

    'empty_transfer_items' => [
        'title' => 'Empty transfer',
        'body' => 'A direct transfer requires at least one line item.',
    ],

    'duplicate_transfer_variant' => [
        'title' => 'Duplicate transfer variant',
        'body' => 'Line :index repeats variant :variant; a transfer contains distinct variants only.',
    ],

    'empty_purchase_items' => [
        'title' => 'Empty purchase order',
        'body' => 'A purchase order requires at least one line item.',
    ],

    'empty_purchase_receipt' => [
        'title' => 'Empty purchase receipt',
        'body' => 'No receipt quantities were provided.',
    ],

    'empty_sales_items' => [
        'title' => 'Empty sales order',
        'body' => 'A sales order requires at least one line item.',
    ],

    'empty_sales_dispatch' => [
        'title' => 'Empty sales dispatch',
        'body' => 'No dispatch quantities were provided.',
    ],

    'empty_requisition_items' => [
        'title' => 'Empty requisition',
        'body' => 'A transfer requisition requires at least one manifest item.',
    ],

    'invalid_return_quantity' => [
        'title' => 'Invalid return quantity',
        'body' => 'Return quantity :qty for item :item must be at least 1.',
    ],

    'missing_item_field' => [
        'title' => 'Missing item field',
        'body' => 'Line :index is missing :field.',
    ],

    'invalid_item_quantity' => [
        'title' => 'Invalid item quantity',
        'body' => 'Line :index has invalid quantity :qty.',
    ],

    'unknown_variant' => [
        'title' => 'Unknown variant',
        'body' => 'Variant :variant does not exist.',
    ],

    'undefined_unit' => [
        'title' => 'Undefined unit',
        'body' => 'Unit :unit is not defined for variant :variant.',
    ],

    'unit_ratio_mismatch' => [
        'title' => 'Unit ratio mismatch',
        'body' => 'Line :index declares unit :unit with a wrong ratio.',
    ],

    'unknown_requisition_item' => [
        'title' => 'Unknown requisition item',
        'body' => 'Item :item does not belong to this requisition.',
    ],

    'non_integer_payload' => [
        'title' => 'Non-integer payload',
        'body' => 'Scan payload for item :item must be an integer.',
    ],

    'negative_payload' => [
        'title' => 'Negative payload',
        'body' => 'Payload for item :item must not be negative.',
    ],

    'empty_loss' => [
        'title' => 'Empty loss',
        'body' => 'Loss entry for item :item records zero quantity.',
    ],

    'invalid_proposed_quantity' => [
        'title' => 'Invalid proposed quantity',
        'body' => 'Proposed quantity :qty is invalid.',
    ],

    'cross_item_revision' => [
        'title' => 'Cross-item revision',
        'body' => 'Revision :revision does not belong to item :item.',
    ],

    'missing_approved_quantity' => [
        'title' => 'Missing approved quantity',
        'body' => 'No approved quantity is set.',
    ],

    'warehouse_out_of_scope' => [
        'title' => 'Warehouse out of scope',
        'body' => 'Warehouses :from / :to are outside your assignment.',
    ],

    'warehouse_code_exhausted' => [
        'title' => 'Warehouse code exhausted',
        'body' => 'Could not derive a unique code for :name.',
    ],

    // Referenced by §7B.2 / §7C.2 reference-code retry paths.
    'reference_code_exhausted' => [
        'title' => 'Reference code exhausted',
        'body' => 'Could not generate a unique :prefix reference code after :attempts attempts.',
    ],
];
