<?php

declare(strict_types=1);

use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use App\Enums\TransferRequisitionStatus;
use App\Exceptions\NegotiationNotAllowedException;
use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\User;
use App\Services\NegotiationService;

beforeEach(function () {
    $this->service = new NegotiationService();
    $this->requestor = User::factory()->create();
    $this->fulfiller = User::factory()->create();
});

function createNegotiableRequisition(TransferRequisitionStatus $status = TransferRequisitionStatus::Requested): TransferRequisition
{
    return TransferRequisition::factory()->create([
        'status' => $status,
    ]);
}

$negotiableStatuses = [
    'requested' => TransferRequisitionStatus::Requested,
    'under_review_fulfiller' => TransferRequisitionStatus::UnderReviewFulfiller,
    'under_review_requestor' => TransferRequisitionStatus::UnderReviewRequestor,
];

$nonNegotiableStatuses = [
    'draft' => TransferRequisitionStatus::Draft,
    'confirmed' => TransferRequisitionStatus::Confirmed,
    'dispatched' => TransferRequisitionStatus::Dispatched,
    'partially_received' => TransferRequisitionStatus::PartiallyReceived,
    'completed' => TransferRequisitionStatus::Completed,
    'closed_with_loss' => TransferRequisitionStatus::ClosedWithLoss,
    'cancelled' => TransferRequisitionStatus::Cancelled,
];

it('opens a new negotiation thread with no responds_to_revision_id', function () {
    $requisition = createNegotiableRequisition();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();

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
    $requisition = createNegotiableRequisition();
    $item = TransferRequisitionItem::factory()->for($requisition)->create([
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
    $requisition = createNegotiableRequisition();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $revision = $this->service->propose($item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 5);
    $this->service->accept($revision);

    expect(fn () => $this->service->accept($revision))->toThrow(NegotiationNotAllowedException::class);
});

it('rejects rejecting an already-resolved revision', function () {
    $requisition = createNegotiableRequisition();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $revision = $this->service->propose($item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 5);
    $this->service->reject($revision);

    expect(fn () => $this->service->reject($revision))->toThrow(NegotiationNotAllowedException::class);
});

it('counters a pending revision from the opposite side automatically', function () {
    $requisition = createNegotiableRequisition();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $opening = $this->service->propose($item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 10);

    $counter = $this->service->counter($opening, $this->fulfiller, 'Box', 24, 8);

    expect($counter->side)->toBe(NegotiationSide::Fulfiller)
        ->and($counter->responds_to_revision_id)->toBe($opening->id)
        ->and($opening->fresh()->status)->toBe(RevisionStatus::Superseded);
});

it('rejects countering an already-resolved revision', function () {
    $requisition = createNegotiableRequisition();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $opening = $this->service->propose($item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 10);
    $this->service->reject($opening);

    expect(fn () => $this->service->counter($opening, $this->fulfiller, 'Box', 24, 8))->toThrow(NegotiationNotAllowedException::class);
});

it('propagates a proposed substitute variant onto the item when accepted', function () {
    $requisition = createNegotiableRequisition();
    $item = TransferRequisitionItem::factory()->for($requisition)->create(['substitute_product_variant_id' => null]);
    $substitute = ProductVariant::factory()->create();

    $revision = $this->service->propose(
        $item, $this->fulfiller, NegotiationSide::Fulfiller, 'Box', 24, 5,
        substituteProductVariantId: $substitute->id,
    );

    $this->service->accept($revision);

    expect($item->fresh()->substitute_product_variant_id)->toBe($substitute->id);
});

it('threads a counter-of-a-counter correctly via propose with respondsTo', function () {
    $requisition = createNegotiableRequisition();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
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
    $requisition = createNegotiableRequisition();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
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
    $requisition = createNegotiableRequisition();
    $item = TransferRequisitionItem::factory()->for($requisition)->create([
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
    $requisition = createNegotiableRequisition();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $revision = $this->service->propose($item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 5);

    $this->service->reject($revision);

    expect($revision->fresh()->status)->toBe(RevisionStatus::Rejected)
        ->and($revision->fresh()->responded_at)->not->toBeNull();
});

it('cannot accept an already-accepted revision', function () {
    $requisition = createNegotiableRequisition();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $revision = $this->service->propose($item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 5);
    $this->service->accept($revision);

    expect(fn () => $this->service->accept($revision))->toThrow(NegotiationNotAllowedException::class);
});

it('cannot reject an already-rejected revision', function () {
    $requisition = createNegotiableRequisition();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $revision = $this->service->propose($item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 5);
    $this->service->reject($revision);

    expect(fn () => $this->service->reject($revision))->toThrow(NegotiationNotAllowedException::class);
});

it('propose stores substitute_product_variant_id on the revision', function () {
    $requisition = createNegotiableRequisition();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $substitute = ProductVariant::factory()->create();

    $revision = $this->service->propose(
        $item, $this->fulfiller, NegotiationSide::Fulfiller, 'Box', 24, 5,
        substituteProductVariantId: $substitute->id,
    );

    expect($revision->substitute_product_variant_id)->toBe($substitute->id);
});

it('propose stores reason as negotiation_reason on the revision', function () {
    $requisition = createNegotiableRequisition();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();

    $revision = $this->service->propose(
        $item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 5,
        reason: 'Need extra stock for Q4 promotion',
    );

    expect($revision->negotiation_reason)->toBe('Need extra stock for Q4 promotion');
});

it('counter stores reason as negotiation_reason on the counter revision', function () {
    $requisition = createNegotiableRequisition();
    $item = TransferRequisitionItem::factory()->for($requisition)->create();
    $opening = $this->service->propose($item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 10);

    $counter = $this->service->counter($opening, $this->fulfiller, 'Box', 24, 8, reason: 'Limited availability');

    expect($counter->negotiation_reason)->toBe('Limited availability');
});

it('accepting a counter-revision syncs approved fields correctly', function () {
    $requisition = createNegotiableRequisition();
    $item = TransferRequisitionItem::factory()->for($requisition)->create([
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

/**
 * STATUS-MATRIX TESTS (27 tests: 9 statuses × 3 methods)
 *
 * Per blueprint Section 10, v12 implementation spec:
 * - accept(), reject(), counter() throw NegotiationNotAllowedException
 * when requisition status ∉ {Requested, UnderReviewFulfiller, UnderReviewRequestor}
 * - Revision must also be Pending (not resolved/superseded)
 */

// accept() status-matrix tests
describe('accept() status guard', function () use ($negotiableStatuses, $nonNegotiableStatuses) {
    foreach ($negotiableStatuses as $name => $status) {
        it("accept_succeeds_on_{$name}", function () use ($status) {
            $requisition = createNegotiableRequisition($status);
            $item = TransferRequisitionItem::factory()->for($requisition)->create();
            // Use appropriate side based on status
            $side = match ($status) {
                TransferRequisitionStatus::UnderReviewFulfiller => NegotiationSide::Fulfiller,
                TransferRequisitionStatus::UnderReviewRequestor => NegotiationSide::Requestor,
                default => NegotiationSide::Fulfiller,
            };
            $revision = $this->service->propose($item, $this->fulfiller, $side, 'Box', 24, 5);

            $this->service->accept($revision);

            expect($revision->fresh()->status)->toBe(RevisionStatus::Accepted);
        });
    }

    foreach ($nonNegotiableStatuses as $name => $status) {
        it("accept_throws_on_{$name}", function () use ($status) {
            $requisition = TransferRequisition::factory()->create(['status' => $status]);
            $item = TransferRequisitionItem::factory()->for($requisition)->create();
            $revision = $this->service->propose($item, $this->fulfiller, NegotiationSide::Fulfiller, 'Box', 24, 5);

            expect(fn () => $this->service->accept($revision))
                ->toThrow(NegotiationNotAllowedException::class);
        });
    }
});

// reject() status-matrix tests
describe('reject() status guard', function () use ($negotiableStatuses, $nonNegotiableStatuses) {
    foreach ($negotiableStatuses as $name => $status) {
        it("reject_succeeds_on_{$name}", function () use ($status) {
            $requisition = createNegotiableRequisition($status);
            $item = TransferRequisitionItem::factory()->for($requisition)->create();
            $side = match ($status) {
                TransferRequisitionStatus::UnderReviewFulfiller => NegotiationSide::Fulfiller,
                TransferRequisitionStatus::UnderReviewRequestor => NegotiationSide::Requestor,
                default => NegotiationSide::Requestor,
            };
            $user = $side === NegotiationSide::Fulfiller ? $this->fulfiller : $this->requestor;
            $revision = $this->service->propose($item, $user, $side, 'Box', 24, 5);

            $this->service->reject($revision);

            expect($revision->fresh()->status)->toBe(RevisionStatus::Rejected);
        });
    }

    foreach ($nonNegotiableStatuses as $name => $status) {
        it("reject_throws_on_{$name}", function () use ($status) {
            $requisition = TransferRequisition::factory()->create(['status' => $status]);
            $item = TransferRequisitionItem::factory()->for($requisition)->create();
            $revision = $this->service->propose($item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 5);

            expect(fn () => $this->service->reject($revision))
                ->toThrow(NegotiationNotAllowedException::class);
        });
    }
});

// counter() status-matrix tests
describe('counter() status guard', function () use ($negotiableStatuses, $nonNegotiableStatuses) {
    foreach ($negotiableStatuses as $name => $status) {
        it("counter_succeeds_on_{$name}", function () use ($status) {
            $requisition = createNegotiableRequisition($status);
            $item = TransferRequisitionItem::factory()->for($requisition)->create();

            // Opening proposal side depends on status
            $openingSide = match ($status) {
                TransferRequisitionStatus::UnderReviewFulfiller => NegotiationSide::Fulfiller,
                TransferRequisitionStatus::UnderReviewRequestor => NegotiationSide::Requestor,
                default => NegotiationSide::Requestor,
            };
            // Counter side is opposite of opening
            $counterSide = $openingSide === NegotiationSide::Fulfiller
                ? NegotiationSide::Requestor
                : NegotiationSide::Fulfiller;
            $openingUser = $openingSide === NegotiationSide::Fulfiller ? $this->fulfiller : $this->requestor;
            $counterUser = $counterSide === NegotiationSide::Fulfiller ? $this->fulfiller : $this->requestor;

            $opening = $this->service->propose($item, $openingUser, $openingSide, 'Box', 24, 10);

            $counter = $this->service->counter($opening, $counterUser, 'Box', 24, 8);

            expect($counter->status)->toBe(RevisionStatus::Pending)
                ->and($counter->side)->toBe($counterSide);
        });
    }

    foreach ($nonNegotiableStatuses as $name => $status) {
        it("counter_throws_on_{$name}", function () use ($status) {
            $requisition = TransferRequisition::factory()->create(['status' => $status]);
            $item = TransferRequisitionItem::factory()->for($requisition)->create();
            // For non-negotiable statuses, side doesn't matter - should throw anyway
            $opening = $this->service->propose($item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 10);

            expect(fn () => $this->service->counter($opening, $this->fulfiller, 'Box', 24, 8))
                ->toThrow(NegotiationNotAllowedException::class);
        });
    }
});

// side-mismatch tests
describe('side mismatch guard', function () {
    it('counter_throws_when_wrong_user_counters_on_fulfiller_turn', function () {
        // Fulfiller's turn = UnderReviewFulfiller
        // Requestor proposed, then Requestor tries to counter again (wrong - should be fulfiller)
        $requisition = createNegotiableRequisition(TransferRequisitionStatus::UnderReviewFulfiller);
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $opening = $this->service->propose($item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 10);

        // Requestor tries to counter again on fulfiller's turn - should fail
        expect(fn () => $this->service->counter($opening, $this->requestor, 'Box', 24, 8))
            ->toThrow(NegotiationNotAllowedException::class);
    });

    it('counter_throws_when_wrong_user_counters_on_requestor_turn', function () {
        // Requestor's turn = UnderReviewRequestor
        // Fulfiller proposed, then Fulfiller tries to counter again (wrong - should be requestor)
        $requisition = createNegotiableRequisition(TransferRequisitionStatus::UnderReviewRequestor);
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $opening = $this->service->propose($item, $this->fulfiller, NegotiationSide::Fulfiller, 'Box', 24, 10);

        // Fulfiller tries to counter again on requestor's turn - should fail
        expect(fn () => $this->service->counter($opening, $this->fulfiller, 'Box', 24, 8))
            ->toThrow(NegotiationNotAllowedException::class);
    });

    it('accept_throws_on_non_pending_revision', function () {
        $requisition = createNegotiableRequisition();
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $revision = $this->service->propose($item, $this->fulfiller, NegotiationSide::Fulfiller, 'Box', 24, 5);
        $this->service->accept($revision); // Now it's Accepted

        expect(fn () => $this->service->accept($revision))
            ->toThrow(NegotiationNotAllowedException::class);
    });

    it('reject_throws_on_non_pending_revision', function () {
        $requisition = createNegotiableRequisition();
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $revision = $this->service->propose($item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 5);
        $this->service->reject($revision); // Now it's Rejected

        expect(fn () => $this->service->reject($revision))
            ->toThrow(NegotiationNotAllowedException::class);
    });

    it('counter_throws_on_non_pending_revision', function () {
        $requisition = createNegotiableRequisition();
        $item = TransferRequisitionItem::factory()->for($requisition)->create();
        $opening = $this->service->propose($item, $this->requestor, NegotiationSide::Requestor, 'Box', 24, 10);
        $this->service->accept($opening); // Now it's Accepted

        expect(fn () => $this->service->counter($opening, $this->fulfiller, 'Box', 24, 8))
            ->toThrow(NegotiationNotAllowedException::class);
    });
});
