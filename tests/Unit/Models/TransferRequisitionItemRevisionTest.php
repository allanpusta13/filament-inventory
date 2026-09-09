<?php

declare(strict_types=1);

use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use App\Models\TransferRequisitionItem;
use App\Models\TransferRequisitionItemRevision;

it('belongs to the item, the user, and the proposed variant', function () {
    $revision = TransferRequisitionItemRevision::factory()->create();

    expect($revision->item)->not->toBeNull()
        ->and($revision->user)->not->toBeNull()
        ->and($revision->variant)->not->toBeNull();
});

it('cascades deletion when the parent item is deleted', function () {
    $revision = TransferRequisitionItemRevision::factory()->create();
    $item = $revision->item;

    $item->delete();

    expect(TransferRequisitionItemRevision::find($revision->id))->toBeNull();
});

it('allows a null substitute variant', function () {
    $revision = TransferRequisitionItemRevision::factory()->create(['substitute_product_variant_id' => null]);

    expect($revision->substituteVariant)->toBeNull();
});

it('casts side and status to their enums', function () {
    $revision = TransferRequisitionItemRevision::factory()->create([
        'side' => NegotiationSide::Requestor,
        'status' => RevisionStatus::Pending,
    ]);

    expect($revision->fresh()->side)->toBe(NegotiationSide::Requestor)
        ->and($revision->fresh()->status)->toBe(RevisionStatus::Pending);
});

it('defaults status to pending', function () {
    $revision = TransferRequisitionItemRevision::factory()->create();

    expect($revision->status)->toBe(RevisionStatus::Pending);
});

it('starts a negotiation thread with no responds_to_revision_id', function () {
    $opening = TransferRequisitionItemRevision::factory()->fromRequestor()->create();

    expect($opening->responds_to_revision_id)->toBeNull()
        ->and($opening->respondsTo)->toBeNull()
        ->and($opening->threadRoot()->is($opening))->toBeTrue();
});

it('accepts a revision and stamps responded_at', function () {
    $revision = TransferRequisitionItemRevision::factory()->create();

    $revision->accept();

    expect($revision->fresh()->status)->toBe(RevisionStatus::Accepted)
        ->and($revision->fresh()->responded_at)->not->toBeNull();
});

it('rejects a revision and stamps responded_at', function () {
    $revision = TransferRequisitionItemRevision::factory()->create();

    $revision->reject();

    expect($revision->fresh()->status)->toBe(RevisionStatus::Rejected)
        ->and($revision->fresh()->responded_at)->not->toBeNull();
});

it('countering a revision marks it superseded and threads the new one', function () {
    $item = TransferRequisitionItem::factory()->create();

    $opening = TransferRequisitionItemRevision::factory()
        ->fromRequestor()
        ->for($item, 'item')
        ->create(['proposed_qty' => 10]);

    $counter = $opening->counterWith([
        'user_id' => $opening->user_id,
        'product_variant_id' => $opening->product_variant_id,
        'proposed_unit_name' => 'Box',
        'proposed_qty' => 8,
        'proposed_base_qty' => 192,
        'side' => NegotiationSide::Fulfiller,
    ]);

    expect($opening->fresh()->status)->toBe(RevisionStatus::Superseded)
        ->and($opening->fresh()->responded_at)->not->toBeNull()
        ->and($counter->status)->toBe(RevisionStatus::Pending)
        ->and($counter->responds_to_revision_id)->toBe($opening->id)
        ->and($counter->side)->toBe(NegotiationSide::Fulfiller);
});

it('walks a multi-hop thread back to its root', function () {
    $item = TransferRequisitionItem::factory()->create();

    $opening = TransferRequisitionItemRevision::factory()->fromRequestor()->for($item, 'item')->create();
    $counter1 = $opening->counterWith([
        'user_id' => $opening->user_id,
        'product_variant_id' => $opening->product_variant_id,
        'proposed_unit_name' => 'Box',
        'proposed_qty' => 5,
        'proposed_base_qty' => 120,
        'side' => NegotiationSide::Fulfiller,
    ]);
    $counter2 = $counter1->counterWith([
        'user_id' => $opening->user_id,
        'product_variant_id' => $opening->product_variant_id,
        'proposed_unit_name' => 'Box',
        'proposed_qty' => 7,
        'proposed_base_qty' => 168,
        'side' => NegotiationSide::Requestor,
    ]);

    expect($counter2->threadRoot()->is($opening))->toBeTrue()
        ->and($opening->counters()->first()->is($counter1))->toBeTrue()
        ->and($counter1->counters()->first()->is($counter2))->toBeTrue();
});

it('nulls responds_to_revision_id when the countered revision is deleted', function () {
    $opening = TransferRequisitionItemRevision::factory()->create();
    $counter = $opening->counterWith([
        'user_id' => $opening->user_id,
        'product_variant_id' => $opening->product_variant_id,
        'proposed_unit_name' => 'Box',
        'proposed_qty' => 1,
        'proposed_base_qty' => 24,
        'side' => NegotiationSide::Fulfiller,
    ]);

    $opening->delete();

    expect($counter->fresh()->responds_to_revision_id)->toBeNull();
});

describe('NegotiationSide enum', function () {
    it('returns the opposite side', function () {
        expect(NegotiationSide::Fulfiller->opposite())->toBe(NegotiationSide::Requestor)
            ->and(NegotiationSide::Requestor->opposite())->toBe(NegotiationSide::Fulfiller);
    });
});

describe('RevisionStatus enum', function () {
    it('treats only pending as unresolved', function () {
        expect(RevisionStatus::Pending->isResolved())->toBeFalse()
            ->and(RevisionStatus::Accepted->isResolved())->toBeTrue()
            ->and(RevisionStatus::Rejected->isResolved())->toBeTrue()
            ->and(RevisionStatus::Superseded->isResolved())->toBeTrue();
    });
});
