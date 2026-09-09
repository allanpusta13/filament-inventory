<?php

declare(strict_types=1);

use App\Models\ProductVariant;
use App\Models\TransferRequisitionItem;
use App\Models\TransferRequisitionItemRevision;

it('belongs to its parent requisition', function () {
    $item = TransferRequisitionItem::factory()->create();

    expect($item->transferRequisition)->not->toBeNull();
});

it('computes outstanding base qty from approved when set', function () {
    $item = TransferRequisitionItem::factory()->create([
        'requested_base_qty' => 100,
        'approved_base_qty' => 80,
        'received_good_base_qty' => 30,
    ]);

    expect($item->outstandingBaseQty())->toBe(50);
});

it('falls back to requested base qty when nothing was approved', function () {
    $item = TransferRequisitionItem::factory()->create([
        'requested_base_qty' => 100,
        'approved_base_qty' => null,
        'received_good_base_qty' => 40,
    ]);

    expect($item->outstandingBaseQty())->toBe(60);
});

it('never returns a negative outstanding qty', function () {
    $item = TransferRequisitionItem::factory()->create([
        'requested_base_qty' => 100,
        'approved_base_qty' => 80,
        'received_good_base_qty' => 120,
    ]);

    expect($item->outstandingBaseQty())->toBe(0);
});

it('cascades deletion when the parent requisition is deleted', function () {
    $item = TransferRequisitionItem::factory()->create();
    $requisitionId = $item->transfer_requisition_id;

    $item->transferRequisition->forceDelete();

    expect(TransferRequisitionItem::find($item->id))->toBeNull();
});

it('returns the full negotiation history in chronological order', function () {
    $item = TransferRequisitionItem::factory()->create();

    $first = TransferRequisitionItemRevision::factory()->for($item, 'item')->create();
    $second = $first->counterWith([
        'user_id' => $first->user_id,
        'product_variant_id' => $first->product_variant_id,
        'proposed_unit_name' => 'Box',
        'proposed_qty' => 5,
        'proposed_base_qty' => 120,
        'side' => App\Enums\NegotiationSide::Fulfiller,
    ]);

    $history = $item->negotiationHistory()->pluck('id');

    expect($history->toArray())->toBe([$first->id, $second->id]);
});

it('filters to only pending revisions', function () {
    $item = TransferRequisitionItem::factory()->create();

    $resolved = TransferRequisitionItemRevision::factory()->accepted()->for($item, 'item')->create();
    $pending = TransferRequisitionItemRevision::factory()->for($item, 'item')->create();

    expect($item->pendingRevisions()->pluck('id')->toArray())->toBe([$pending->id])
        ->and($item->pendingRevisions()->pluck('id')->toArray())->not->toContain($resolved->id);
});

it('belongs to a product variant', function () {
    $variant = ProductVariant::factory()->create();
    $item = TransferRequisitionItem::factory()->create(['product_variant_id' => $variant->id]);

    expect($item->variant->is($variant))->toBeTrue();
});

it('belongs to a substitute variant when set', function () {
    $substitute = ProductVariant::factory()->create();
    $item = TransferRequisitionItem::factory()->create(['substitute_product_variant_id' => $substitute->id]);

    expect($item->substituteVariant->is($substitute))->toBeTrue();
});

it('has many revisions', function () {
    $item = TransferRequisitionItem::factory()->create();
    TransferRequisitionItemRevision::factory()->count(3)->for($item, 'item')->create();

    expect($item->revisions)->toHaveCount(3);
});
