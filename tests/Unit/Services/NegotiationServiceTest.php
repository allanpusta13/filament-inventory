<?php

declare(strict_types=1);

use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use App\Models\ProductVariant;
use App\Models\TransferRequisitionItem;
use App\Models\User;
use App\Services\NegotiationService;

beforeEach(function () {
    $this->service = new NegotiationService();
    $this->requestor = User::factory()->create();
    $this->fulfiller = User::factory()->create();
});

it('opens a new negotiation thread with no responds_to_revision_id', function () {
    $item = TransferRequisitionItem::factory()->create();

    $revision = $this->service->propose(
        item: $item,
        user: $this->requestor,
        side: NegotiationSide::Requestor,
        unitName: 'Box',
        unitRatio: 24,
        qty: 5,
    );

    expect($revision->responds_to_revision_id)->toBeNull()
        ->and($revision->proposed_base_qty)->toBe(120)
        ->and($revision->status)->toBe(RevisionStatus::Pending)
        ->and($revision->side)->toBe(NegotiationSide::Requestor);
});

it('accepting a proposal syncs approved_* fields onto the item', function () {
    $item = TransferRequisitionItem::factory()->create([
        'approved_base_qty' => null,
        'approved_unit_name' => null,
        'approved_unit_ratio' => null,
        'approved_qty' => null,
    ]);

    $revision = $this->service->propose($item, $this->fulfiller, NegotiationSide::Fulfiller, 'Pallet', 480, 2);

    $this->service->accept($revision);

    expect($item->fresh()->approved_base_qty)->toBe(960)
        ->and($item->fresh()->approved_unit_name)->toBe('Pallet')
        ->and($item->fresh()->approved_unit_ratio)->toBe(480)
        ->and($item->fresh()->approved_qty)->toBe(2)
        ->and($revision->fresh()->status)->toBe(RevisionStatus::Accepted);
});

it('rejects accepting an already-resolved revision', function () {
    $item = TransferRequisitionItem::factory()->create();
    $revision = $this->service->propose($item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 5);
    $this->service->accept($revision);

    expect(fn () => $this->service->accept($revision))->toThrow(Exception::class);
});

it('rejects rejecting an already-resolved revision', function () {
    $item = TransferRequisitionItem::factory()->create();
    $revision = $this->service->propose($item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 5);
    $this->service->reject($revision);

    expect(fn () => $this->service->reject($revision))->toThrow(Exception::class);
});

it('counters a pending revision from the opposite side automatically', function () {
    $item = TransferRequisitionItem::factory()->create();
    $opening = $this->service->propose($item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 10);

    $counter = $this->service->counter($opening, $this->fulfiller, 'Box', 24, 8);

    expect($counter->side)->toBe(NegotiationSide::Fulfiller)
        ->and($counter->responds_to_revision_id)->toBe($opening->id)
        ->and($opening->fresh()->status)->toBe(RevisionStatus::Superseded);
});

it('rejects countering an already-resolved revision', function () {
    $item = TransferRequisitionItem::factory()->create();
    $opening = $this->service->propose($item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 10);
    $this->service->reject($opening);

    expect(fn () => $this->service->counter($opening, $this->fulfiller, 'Box', 24, 8))->toThrow(Exception::class);
});

it('propagates a proposed substitute variant onto the item when accepted', function () {
    $item = TransferRequisitionItem::factory()->create(['substitute_product_variant_id' => null]);
    $substitute = ProductVariant::factory()->create();

    $revision = $this->service->propose(
        $item, $this->fulfiller, NegotiationSide::Fulfiller, 'Box', 24, 5,
        substituteProductVariantId: $substitute->id,
    );

    $this->service->accept($revision);

    expect($item->fresh()->substitute_product_variant_id)->toBe($substitute->id);
});

it('threads a counter-of-a-counter correctly via propose with respondsTo', function () {
    $item = TransferRequisitionItem::factory()->create();
    $opening = $this->service->propose($item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 10);
    $counter1 = $this->service->counter($opening, $this->fulfiller, 'Box', 24, 6);

    $counter2 = $this->service->propose(
        $item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 8,
        respondsTo: $counter1,
    );

    expect($counter2->responds_to_revision_id)->toBe($counter1->id)
        ->and($counter2->threadRoot()->is($opening))->toBeTrue();
});

it('counter creates a pending revision with different unit details and opposite side', function () {
    $item = TransferRequisitionItem::factory()->create();
    $opening = $this->service->propose($item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 10);

    $counter = $this->service->counter($opening, $this->fulfiller, 'Pallet', 480, 1);

    expect($counter->status)->toBe(RevisionStatus::Pending)
        ->and($counter->side)->toBe(NegotiationSide::Fulfiller)
        ->and($counter->proposed_unit_name)->toBe('Pallet')
        ->and($counter->proposed_unit_ratio)->toBe(480)
        ->and($counter->proposed_qty)->toBe(1)
        ->and($counter->proposed_base_qty)->toBe(480)
        ->and($counter->responds_to_revision_id)->toBe($opening->id)
        ->and($opening->fresh()->status)->toBe(RevisionStatus::Superseded);
});

it('accepting a revision marks it as Accepted and stores approved values', function () {
    $item = TransferRequisitionItem::factory()->create([
        'approved_base_qty' => null,
        'approved_unit_name' => null,
        'approved_unit_ratio' => null,
        'approved_qty' => null,
    ]);

    $revision = $this->service->propose($item, $this->fulfiller, NegotiationSide::Fulfiller, 'Case', 12, 20);

    $this->service->accept($revision);

    expect($revision->fresh()->status)->toBe(RevisionStatus::Accepted)
        ->and($item->fresh()->approved_base_qty)->toBe(240)
        ->and($item->fresh()->approved_unit_name)->toBe('Case')
        ->and($item->fresh()->approved_unit_ratio)->toBe(12)
        ->and($item->fresh()->approved_qty)->toBe(20);
});

it('rejecting a revision marks it as Rejected', function () {
    $item = TransferRequisitionItem::factory()->create();
    $revision = $this->service->propose($item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 5);

    $this->service->reject($revision);

    expect($revision->fresh()->status)->toBe(RevisionStatus::Rejected)
        ->and($revision->fresh()->responded_at)->not->toBeNull();
});

it('cannot accept an already-accepted revision', function () {
    $item = TransferRequisitionItem::factory()->create();
    $revision = $this->service->propose($item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 5);
    $this->service->accept($revision);

    expect(fn () => $this->service->accept($revision))->toThrow(Exception::class);
});

it('cannot reject an already-rejected revision', function () {
    $item = TransferRequisitionItem::factory()->create();
    $revision = $this->service->propose($item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 5);
    $this->service->reject($revision);

    expect(fn () => $this->service->reject($revision))->toThrow(Exception::class);
});

it('propose stores substitute_product_variant_id on the revision', function () {
    $item = TransferRequisitionItem::factory()->create();
    $substitute = ProductVariant::factory()->create();

    $revision = $this->service->propose(
        $item, $this->fulfiller, NegotiationSide::Fulfiller, 'Box', 24, 5,
        substituteProductVariantId: $substitute->id,
    );

    expect($revision->substitute_product_variant_id)->toBe($substitute->id);
});

it('propose stores reason as negotiation_reason on the revision', function () {
    $item = TransferRequisitionItem::factory()->create();

    $revision = $this->service->propose(
        $item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 5,
        reason: 'Need extra stock for Q4 promotion',
    );

    expect($revision->negotiation_reason)->toBe('Need extra stock for Q4 promotion');
});

it('counter stores reason as negotiation_reason on the counter revision', function () {
    $item = TransferRequisitionItem::factory()->create();
    $opening = $this->service->propose($item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 10);

    $counter = $this->service->counter($opening, $this->fulfiller, 'Box', 24, 8, reason: 'Limited availability');

    expect($counter->negotiation_reason)->toBe('Limited availability');
});

it('accepting a counter-revision syncs approved fields correctly', function () {
    $item = TransferRequisitionItem::factory()->create([
        'approved_base_qty' => null,
        'approved_unit_name' => null,
        'approved_unit_ratio' => null,
        'approved_qty' => null,
    ]);

    $opening = $this->service->propose($item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 10);
    $counter = $this->service->counter($opening, $this->fulfiller, 'Pallet', 480, 2);

    $this->service->accept($counter);

    expect($item->fresh()->approved_base_qty)->toBe(960)
        ->and($item->fresh()->approved_unit_name)->toBe('Pallet')
        ->and($item->fresh()->approved_unit_ratio)->toBe(480)
        ->and($item->fresh()->approved_qty)->toBe(2)
        ->and($counter->fresh()->status)->toBe(RevisionStatus::Accepted);
});
