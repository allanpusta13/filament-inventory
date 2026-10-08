<?php

declare(strict_types=1);

use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use App\Enums\TransferRequisitionStatus;
use App\Exceptions\DomainRuleViolationException;
use App\Exceptions\InvalidDocumentStateException;
use App\Exceptions\NegotiationNotAllowedException;
use App\Models\ProductVariant;
use App\Models\ProductVariantUnitConversion;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\TransferRequisitionItemRevision;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\NegotiationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * NegotiationService contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §6.3 the six public methods.
 *   - §12 Pest coverage list:
 *       * submitRequest() transitions Draft → Requested only.
 *       * assertNegotiable() rejects non-negotiable parent statuses.
 *
 * Removed oracle (D-1): "materializeRequestedAsApproved() throws if any item
 * has null approved qty" — unreachable. The service copies `requested_base_qty`
 * (NOT NULL) onto `approved_base_qty`, so the trailing guard cannot fire on any
 * schema-valid insert. See tests/Feature/Architecture/DIAGNOSIS.md.
 *   - §3.9 ensureCanTransitionTo() single-move rule.
 *   - §0 core principle 6 / A10 — substitute-variant unit sourcing.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(NegotiationService::class);
});

/**
 * Create a requisition + item with a base-unit conversion row present.
 */
function makeRequisition(TransferRequisitionStatus $status = TransferRequisitionStatus::Draft): array
{
    [$from, $to] = Warehouse::factory()->count(2)->create();
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    // Ensure base-unit conversion exists (observer normally creates it).
    ProductVariantUnitConversion::firstOrCreate(
        ['product_variant_id' => $variant->id, 'unit_name' => 'pc'],
        ['base_unit_ratio' => 1, 'is_default_purchase' => false, 'is_default_transfer' => false],
    );

    $requisition = TransferRequisition::factory()->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
        'status' => $status,
    ]);

    $item = TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $requisition->id,
        'product_variant_id' => $variant->id,
        'requested_unit_name' => 'pc',
        'requested_unit_ratio' => 1,
        'requested_qty' => 10,
        'requested_base_qty' => 10,
        'approved_base_qty' => null,
    ]);

    return ['requisition' => $requisition, 'item' => $item, 'variant' => $variant];
}

// ===========================================================================
// submitRequest()
// ===========================================================================

describe('submitRequest()', function () {
    it('transitions Draft → Requested', function () {
        // §12: "submitRequest() transitions Draft → Requested only".
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Draft);

        $this->service->submitRequest($made['requisition']);

        $fresh = $made['requisition']->fresh();
        expect($fresh->status)->toBe(TransferRequisitionStatus::Requested);
        expect($fresh->requested_at)->not->toBeNull();
        expect($fresh->requested_by)->not->toBeNull();
    });

    it('rejects a non-Draft requisition with InvalidDocumentStateException', function (TransferRequisitionStatus $status) {
        actingAsAdmin();
        $made = makeRequisition($status);

        expect(fn () => $this->service->submitRequest($made['requisition']))
            ->toThrow(InvalidDocumentStateException::class);
    })->with([
        TransferRequisitionStatus::Requested,
        TransferRequisitionStatus::UnderReviewFulfiller,
        TransferRequisitionStatus::UnderReviewRequestor,
        TransferRequisitionStatus::Confirmed,
        TransferRequisitionStatus::Dispatched,
        TransferRequisitionStatus::Completed,
        TransferRequisitionStatus::ClosedWithLoss,
        TransferRequisitionStatus::Cancelled,
    ]);

    it('carries the correct action label on the rejection', function () {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Confirmed);

        try {
            $this->service->submitRequest($made['requisition']);
            $this->fail('Expected InvalidDocumentStateException was not thrown.');
        } catch (InvalidDocumentStateException $e) {
            expect($e->action)->toBe('submit');
            expect($e->actualStatus)->toBe('confirmed');
            expect($e->documentType)->toBe(TransferRequisition::class);
        }
    });

    it('preserves a pre-set requested_by', function () {
        $admin = actingAsAdmin();
        $original = User::factory()->create();
        $made = makeRequisition(TransferRequisitionStatus::Draft);
        $made['requisition']->update(['requested_by' => $original->id]);

        $this->service->submitRequest($made['requisition']);

        // `requested_by` is preserved — the service uses `??` fallback.
        expect($made['requisition']->fresh()->requested_by)->toBe($original->id);
    });
});

// ===========================================================================
// materializeRequestedAsApproved()
// ===========================================================================

describe('materializeRequestedAsApproved()', function () {
    it('copies requested_unit_name / ratio / qty / base_qty onto the approved leg', function () {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Requested);

        $this->service->materializeRequestedAsApproved($made['requisition']);

        $item = $made['item']->fresh();
        expect($item->approved_unit_name)->toBe('pc');
        expect($item->approved_unit_ratio)->toBe(1);
        expect($item->approved_qty)->toBe(10);
        expect($item->approved_base_qty)->toBe(10);
    });

    it('does not overwrite an item that already has approved_base_qty set', function () {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Requested);
        $made['item']->update([
            'approved_unit_name' => 'pc',
            'approved_unit_ratio' => 1,
            'approved_qty' => 7,
            'approved_base_qty' => 7,
        ]);

        $this->service->materializeRequestedAsApproved($made['requisition']->fresh());

        // Preserved — the "already approved" item is skipped.
        expect($made['item']->fresh()->approved_base_qty)->toBe(7);
    });

    it('is a no-op when every item already has approved_base_qty', function () {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Requested);
        $made['item']->update([
            'approved_unit_name' => 'pc',
            'approved_unit_ratio' => 1,
            'approved_qty' => 10,
            'approved_base_qty' => 10,
        ]);

        // Should not throw and should not change the approved values.
        $this->service->materializeRequestedAsApproved($made['requisition']->fresh());

        expect($made['item']->fresh()->approved_base_qty)->toBe(10);
    });
});

// ===========================================================================
// assertNegotiable()
// ===========================================================================

describe('assertNegotiable()', function () {
    it('passes when the parent is Requested and the revision is Pending', function () {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Requested);
        $revision = TransferRequisitionItemRevision::factory()->create([
            'transfer_requisition_item_id' => $made['item']->id,
            'status' => RevisionStatus::Pending,
        ]);

        // No exception thrown.
        $this->service->assertNegotiable($revision->fresh()->load('item.transferRequisition'));

        expect(true)->toBeTrue();
    });

    it('passes when the parent is UnderReviewFulfiller', function () {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::UnderReviewFulfiller);
        $revision = TransferRequisitionItemRevision::factory()->create([
            'transfer_requisition_item_id' => $made['item']->id,
            'status' => RevisionStatus::Pending,
        ]);

        $this->service->assertNegotiable($revision->fresh()->load('item.transferRequisition'));
        expect(true)->toBeTrue();
    });

    it('passes when the parent is UnderReviewRequestor', function () {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::UnderReviewRequestor);
        $revision = TransferRequisitionItemRevision::factory()->create([
            'transfer_requisition_item_id' => $made['item']->id,
            'status' => RevisionStatus::Pending,
        ]);

        $this->service->assertNegotiable($revision->fresh()->load('item.transferRequisition'));
        expect(true)->toBeTrue();
    });

    it('rejects a non-negotiable parent status', function (TransferRequisitionStatus $status) {
        // §12: "assertNegotiable() rejects non-negotiable parent statuses".
        actingAsAdmin();
        $made = makeRequisition($status);
        $revision = TransferRequisitionItemRevision::factory()->create([
            'transfer_requisition_item_id' => $made['item']->id,
            'status' => RevisionStatus::Pending,
        ]);

        expect(fn () => $this->service->assertNegotiable($revision->fresh()->load('item.transferRequisition')))
            ->toThrow(NegotiationNotAllowedException::class);
    })->with([
        TransferRequisitionStatus::Draft,
        TransferRequisitionStatus::Confirmed,
        TransferRequisitionStatus::Dispatched,
        TransferRequisitionStatus::PartiallyReceived,
        TransferRequisitionStatus::Completed,
        TransferRequisitionStatus::ClosedWithLoss,
        TransferRequisitionStatus::Cancelled,
    ]);

    it('rejects a resolved revision with the revision_already_resolved key', function (RevisionStatus $resolvedStatus) {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Requested);
        $revision = TransferRequisitionItemRevision::factory()->create([
            'transfer_requisition_item_id' => $made['item']->id,
            'status' => $resolvedStatus,
        ]);

        try {
            $this->service->assertNegotiable($revision->fresh()->load('item.transferRequisition'));
            $this->fail('Expected NegotiationNotAllowedException was not thrown.');
        } catch (NegotiationNotAllowedException $e) {
            expect($e->translationKey())->toBe('errors.revision_already_resolved');
            expect($e->context())->toBe(['revision' => (int) $revision->id]);
        }
    })->with([
        RevisionStatus::Accepted,
        RevisionStatus::Rejected,
    ]);

    it('reports negotiation_not_allowed for a bad parent status', function () {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Confirmed);
        $revision = TransferRequisitionItemRevision::factory()->create([
            'transfer_requisition_item_id' => $made['item']->id,
        ]);

        try {
            $this->service->assertNegotiable($revision->fresh()->load('item.transferRequisition'));
            $this->fail('Expected NegotiationNotAllowedException was not thrown.');
        } catch (NegotiationNotAllowedException $e) {
            expect($e->translationKey())->toBe('errors.negotiation_not_allowed');
            expect($e->context())->toHaveKey('requisition');
            expect($e->context())->toHaveKey('status');
        }
    });
});

// ===========================================================================
// accept()
// ===========================================================================

describe('accept()', function () {
    it('transitions a Pending revision to Accepted', function () {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Requested);
        $revision = TransferRequisitionItemRevision::factory()->create([
            'transfer_requisition_item_id' => $made['item']->id,
            'status' => RevisionStatus::Pending,
        ]);

        $this->service->accept($revision);

        $fresh = $revision->fresh();
        expect($fresh->status)->toBe(RevisionStatus::Accepted);
        expect($fresh->responded_at)->not->toBeNull();
    });

    it('rejects an already-resolved revision', function () {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Requested);
        $revision = TransferRequisitionItemRevision::factory()->create([
            'transfer_requisition_item_id' => $made['item']->id,
            'status' => RevisionStatus::Accepted,
        ]);

        expect(fn () => $this->service->accept($revision))
            ->toThrow(NegotiationNotAllowedException::class);
    });

    it('rejects a revision on a non-negotiable parent', function () {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Confirmed);
        $revision = TransferRequisitionItemRevision::factory()->create([
            'transfer_requisition_item_id' => $made['item']->id,
        ]);

        expect(fn () => $this->service->accept($revision))
            ->toThrow(NegotiationNotAllowedException::class);
    });
});

// ===========================================================================
// reject()
// ===========================================================================

describe('reject()', function () {
    it('transitions a Pending revision to Rejected', function () {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Requested);
        $revision = TransferRequisitionItemRevision::factory()->create([
            'transfer_requisition_item_id' => $made['item']->id,
            'status' => RevisionStatus::Pending,
        ]);

        $this->service->reject($revision);

        $fresh = $revision->fresh();
        expect($fresh->status)->toBe(RevisionStatus::Rejected);
        expect($fresh->responded_at)->not->toBeNull();
    });

    it('rejects an already-resolved revision', function () {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Requested);
        $revision = TransferRequisitionItemRevision::factory()->create([
            'transfer_requisition_item_id' => $made['item']->id,
            'status' => RevisionStatus::Rejected,
        ]);

        expect(fn () => $this->service->reject($revision))
            ->toThrow(NegotiationNotAllowedException::class);
    });

    it('cannot reject an Accepted revision', function () {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Requested);
        $revision = TransferRequisitionItemRevision::factory()->create([
            'transfer_requisition_item_id' => $made['item']->id,
            'status' => RevisionStatus::Accepted,
        ]);

        expect(fn () => $this->service->reject($revision))
            ->toThrow(NegotiationNotAllowedException::class);
    });
});

// ===========================================================================
// submitRevision()
// ===========================================================================

describe('submitRevision()', function () {
    it('creates a Pending revision for the item', function () {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Requested);

        $revision = $this->service->submitRevision(
            item: $made['item'],
            substituteVariantId: null,
            side: NegotiationSide::Fulfiller,
            proposedUnitName: 'pc',
            proposedQty: 15,
            negotiationReason: 'Only 15 in stock',
        );

        expect($revision->status)->toBe(RevisionStatus::Pending);
        expect($revision->side)->toBe(NegotiationSide::Fulfiller);
        expect($revision->proposed_qty)->toBe(15);
        expect($revision->proposed_base_qty)->toBe(15);
    });

    it('transitions the parent from Requested to UnderReviewRequestor when the fulfiller submits', function () {
        // §6.3 ping-pong: fulfiller submits → parent moves to UnderReviewRequestor.
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Requested);

        $this->service->submitRevision(
            item: $made['item'],
            substituteVariantId: null,
            side: NegotiationSide::Fulfiller,
            proposedUnitName: 'pc',
            proposedQty: 15,
        );

        expect($made['requisition']->fresh()->status)->toBe(TransferRequisitionStatus::UnderReviewRequestor);
    });

    it('transitions the parent from Requested to UnderReviewFulfiller when the requestor submits', function () {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Requested);

        $this->service->submitRevision(
            item: $made['item'],
            substituteVariantId: null,
            side: NegotiationSide::Requestor,
            proposedUnitName: 'pc',
            proposedQty: 15,
        );

        expect($made['requisition']->fresh()->status)->toBe(TransferRequisitionStatus::UnderReviewFulfiller);
    });

    it('rejects a non-negotiable parent status', function () {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Confirmed);

        expect(fn () => $this->service->submitRevision(
            item: $made['item'],
            substituteVariantId: null,
            side: NegotiationSide::Fulfiller,
            proposedUnitName: 'pc',
            proposedQty: 15,
        ))->toThrow(NegotiationNotAllowedException::class);
    });

    it('rejects a proposed_qty below 1', function () {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Requested);

        expect(fn () => $this->service->submitRevision(
            item: $made['item'],
            substituteVariantId: null,
            side: NegotiationSide::Fulfiller,
            proposedUnitName: 'pc',
            proposedQty: 0,
        ))->toThrow(DomainRuleViolationException::class);
    });

    it('rejects an undefined unit for the effective variant', function () {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Requested);

        expect(fn () => $this->service->submitRevision(
            item: $made['item'],
            substituteVariantId: null,
            side: NegotiationSide::Fulfiller,
            proposedUnitName: 'bogus-unit',
            proposedQty: 15,
        ))->toThrow(DomainRuleViolationException::class);
    });

    it('resolves the ratio server-side from the substitution variant when provided', function () {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Requested);

        // Give the substitute variant a 'case' unit with ratio 24.
        $substitute = ProductVariant::factory()->create(['base_unit_name' => 'pc']);
        ProductVariantUnitConversion::firstOrCreate(
            ['product_variant_id' => $substitute->id, 'unit_name' => 'case'],
            ['base_unit_ratio' => 24, 'is_default_purchase' => false, 'is_default_transfer' => false],
        );

        $revision = $this->service->submitRevision(
            item: $made['item'],
            substituteVariantId: $substitute->id,
            side: NegotiationSide::Fulfiller,
            proposedUnitName: 'case',
            proposedQty: 2,
        );

        expect($revision->proposed_unit_ratio)->toBe(24);
        expect($revision->proposed_base_qty)->toBe(48);
        expect($revision->substitute_product_variant_id)->toBe($substitute->id);
    });

    it('rejects a unit that exists on the original variant but not on the substitute', function () {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Requested);

        // Give the original variant a 'case' unit — the substitute has none.
        ProductVariantUnitConversion::firstOrCreate(
            ['product_variant_id' => $made['variant']->id, 'unit_name' => 'case'],
            ['base_unit_ratio' => 24, 'is_default_purchase' => false, 'is_default_transfer' => false],
        );

        $substitute = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

        expect(fn () => $this->service->submitRevision(
            item: $made['item'],
            substituteVariantId: $substitute->id,
            side: NegotiationSide::Fulfiller,
            proposedUnitName: 'case',
            proposedQty: 2,
        ))->toThrow(DomainRuleViolationException::class);
    });

    it('rejects a responds_to_revision_id from a different item', function () {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Requested);
        $other = makeRequisition(TransferRequisitionStatus::Requested);

        $foreignRevision = TransferRequisitionItemRevision::factory()->create([
            'transfer_requisition_item_id' => $other['item']->id,
        ]);

        expect(fn () => $this->service->submitRevision(
            item: $made['item'],
            substituteVariantId: null,
            side: NegotiationSide::Fulfiller,
            proposedUnitName: 'pc',
            proposedQty: 15,
            respondsToRevisionId: $foreignRevision->id,
        ))->toThrow(DomainRuleViolationException::class);
    });

    it('accepts a responds_to_revision_id from the same item', function () {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::UnderReviewFulfiller);

        $firstRevision = TransferRequisitionItemRevision::factory()->create([
            'transfer_requisition_item_id' => $made['item']->id,
            'status' => RevisionStatus::Pending,
        ]);

        $reply = $this->service->submitRevision(
            item: $made['item'],
            substituteVariantId: null,
            side: NegotiationSide::Requestor,
            proposedUnitName: 'pc',
            proposedQty: 20,
            respondsToRevisionId: $firstRevision->id,
        );

        expect($reply->responds_to_revision_id)->toBe($firstRevision->id);
    });

    it('records the negotiating user id', function () {
        $admin = actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Requested);

        $revision = $this->service->submitRevision(
            item: $made['item'],
            substituteVariantId: null,
            side: NegotiationSide::Fulfiller,
            proposedUnitName: 'pc',
            proposedQty: 15,
        );

        expect($revision->user_id)->toBe($admin->id);
    });

    it('does not change the parent status when already at the target state', function () {
        // Idempotent ping-pong: the fulfiller submits again while already
        // in UnderReviewRequestor — parent status is untouched.
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::UnderReviewRequestor);

        $this->service->submitRevision(
            item: $made['item'],
            substituteVariantId: null,
            side: NegotiationSide::Fulfiller,
            proposedUnitName: 'pc',
            proposedQty: 15,
        );

        expect($made['requisition']->fresh()->status)->toBe(TransferRequisitionStatus::UnderReviewRequestor);
    });
});

// ===========================================================================
// Full ping-pong lifecycle
// ===========================================================================

describe('negotiation ping-pong', function () {
    it('alternates between the two under-review states as the two sides submit', function () {
        actingAsAdmin();
        $made = makeRequisition(TransferRequisitionStatus::Requested);

        // Round 1 — fulfiller submits → UnderReviewRequestor.
        $this->service->submitRevision(
            item: $made['item'],
            substituteVariantId: null,
            side: NegotiationSide::Fulfiller,
            proposedUnitName: 'pc',
            proposedQty: 20,
        );
        expect($made['requisition']->fresh()->status)->toBe(TransferRequisitionStatus::UnderReviewRequestor);

        // Round 2 — requestor submits → UnderReviewFulfiller.
        $this->service->submitRevision(
            item: $made['item'],
            substituteVariantId: null,
            side: NegotiationSide::Requestor,
            proposedUnitName: 'pc',
            proposedQty: 15,
        );
        expect($made['requisition']->fresh()->status)->toBe(TransferRequisitionStatus::UnderReviewFulfiller);

        // Round 3 — fulfiller submits again → UnderReviewRequestor.
        $this->service->submitRevision(
            item: $made['item'],
            substituteVariantId: null,
            side: NegotiationSide::Fulfiller,
            proposedUnitName: 'pc',
            proposedQty: 18,
        );
        expect($made['requisition']->fresh()->status)->toBe(TransferRequisitionStatus::UnderReviewRequestor);
    });
});
