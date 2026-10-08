<?php

declare(strict_types=1);

use App\Enums\TransferRequisitionStatus;
use App\Events\TransferCancelled;
use App\Events\TransferConfirmed;
use App\Exceptions\DomainRuleViolationException;
use App\Exceptions\InvalidDocumentStateException;
use App\Models\ProductVariant;
use App\Models\ProductVariantUnitConversion;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\Warehouse;
use App\Services\TransferRequisitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

/**
 * TransferRequisitionService contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §6.6 the two public methods (confirm, cancelRequisition).
 *   - §19.1 confirm atomicity.
 *   - §19.2 cancellation boundary via canBeCancelled().
 *   - §3.7 the five pre-dispatch cancellable states.
 *   - §22.1a / §22.2 the event timing contract (dispatched inside the
 *     transaction, delivered after commit via ShouldDispatchAfterCommit).
 *   - §6.3 the delegated materializeRequestedAsApproved() call.
 *
 * Removed oracles (D-1): the two tests asserting that a null-`requested_base_qty`
 * item makes the delegated materializer throw. That state is unreachable — the
 * materializer copies `requested_base_qty` (NOT NULL) onto `approved_base_qty`,
 * so the guard cannot fire on any schema-valid insert. See
 * tests/Feature/Architecture/DIAGNOSIS.md.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(TransferRequisitionService::class);
});

/**
 * Create a requisition with a single item and its base-unit conversion row.
 *
 * @return array{requisition: TransferRequisition, item: TransferRequisitionItem, variant: ProductVariant}
 */
function makeRequisitionForConfirm(TransferRequisitionStatus $status): array
{
    [$from, $to] = Warehouse::factory()->count(2)->create();
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

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
// confirm()
// ===========================================================================

describe('confirm()', function () {
    it('transitions Requested → Confirmed', function () {
        actingAsAdmin();
        $made = makeRequisitionForConfirm(TransferRequisitionStatus::Requested);

        $this->service->confirm($made['requisition']);

        $fresh = $made['requisition']->fresh();
        expect($fresh->status)->toBe(TransferRequisitionStatus::Confirmed);
        expect($fresh->approved_at)->not->toBeNull();
        expect($fresh->approved_by)->not->toBeNull();
    });

    it('transitions UnderReviewFulfiller → Confirmed', function () {
        actingAsAdmin();
        $made = makeRequisitionForConfirm(TransferRequisitionStatus::UnderReviewFulfiller);

        $this->service->confirm($made['requisition']);

        expect($made['requisition']->fresh()->status)->toBe(TransferRequisitionStatus::Confirmed);
    });

    it('transitions UnderReviewRequestor → Confirmed', function () {
        actingAsAdmin();
        $made = makeRequisitionForConfirm(TransferRequisitionStatus::UnderReviewRequestor);

        $this->service->confirm($made['requisition']);

        expect($made['requisition']->fresh()->status)->toBe(TransferRequisitionStatus::Confirmed);
    });

    it('materializes requested items onto the approved leg', function () {
        // §19.1: confirm() calls materializeRequestedAsApproved() and the
        // approved leg is populated from the requested leg.
        actingAsAdmin();
        $made = makeRequisitionForConfirm(TransferRequisitionStatus::Requested);

        $this->service->confirm($made['requisition']);

        $item = $made['item']->fresh();
        expect($item->approved_unit_name)->toBe('pc');
        expect($item->approved_unit_ratio)->toBe(1);
        expect($item->approved_qty)->toBe(10);
        expect($item->approved_base_qty)->toBe(10);
    });

    it('preserves an already-set approved leg without overwriting', function () {
        actingAsAdmin();
        $made = makeRequisitionForConfirm(TransferRequisitionStatus::Requested);
        $made['item']->update([
            'approved_unit_name' => 'pc',
            'approved_unit_ratio' => 1,
            'approved_qty' => 7,
            'approved_base_qty' => 7,
        ]);

        $this->service->confirm($made['requisition']);

        expect($made['item']->fresh()->approved_base_qty)->toBe(7);
    });

    it('rejects a Draft requisition', function () {
        actingAsAdmin();
        $made = makeRequisitionForConfirm(TransferRequisitionStatus::Draft);

        expect(fn () => $this->service->confirm($made['requisition']))
            ->toThrow(InvalidDocumentStateException::class);
    });

    it('rejects a Confirmed requisition', function () {
        actingAsAdmin();
        $made = makeRequisitionForConfirm(TransferRequisitionStatus::Confirmed);

        expect(fn () => $this->service->confirm($made['requisition']))
            ->toThrow(InvalidDocumentStateException::class);
    });

    it('rejects a Dispatched requisition', function () {
        actingAsAdmin();
        $made = makeRequisitionForConfirm(TransferRequisitionStatus::Dispatched);

        expect(fn () => $this->service->confirm($made['requisition']))
            ->toThrow(InvalidDocumentStateException::class);
    });

    it('rejects a Cancelled requisition', function () {
        actingAsAdmin();
        $made = makeRequisitionForConfirm(TransferRequisitionStatus::Cancelled);

        expect(fn () => $this->service->confirm($made['requisition']))
            ->toThrow(InvalidDocumentStateException::class);
    });

    it('carries the confirm action label and actual status on rejection', function () {
        actingAsAdmin();
        $made = makeRequisitionForConfirm(TransferRequisitionStatus::Draft);

        try {
            $this->service->confirm($made['requisition']);
            $this->fail('Expected InvalidDocumentStateException was not thrown.');
        } catch (InvalidDocumentStateException $e) {
            expect($e->action)->toBe('confirm');
            expect($e->actualStatus)->toBe('draft');
            expect($e->documentType)->toBe(TransferRequisition::class);
            expect($e->documentId)->toBe((int) $made['requisition']->id);
        }
    });

    it('rejects a requisition with no manifest items', function () {
        // §7B.1 requires at least one manifest item; §6.6's guard fires
        // before any materialization.
        actingAsAdmin();
        [$from, $to] = Warehouse::factory()->count(2)->create();
        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $to->id,
            'status' => TransferRequisitionStatus::Requested,
        ]);

        expect(fn () => $this->service->confirm($requisition))
            ->toThrow(DomainRuleViolationException::class);
    });

    it('carries errors.empty_requisition_items on the empty-manifest rejection', function () {
        actingAsAdmin();
        [$from, $to] = Warehouse::factory()->count(2)->create();
        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $to->id,
            'status' => TransferRequisitionStatus::Requested,
        ]);

        try {
            $this->service->confirm($requisition);
            $this->fail('Expected DomainRuleViolationException was not thrown.');
        } catch (DomainRuleViolationException $e) {
            expect($e->translationKey())->toBe('errors.empty_requisition_items');
        }
    });

    it('fires TransferConfirmed', function () {
        Event::fake([TransferConfirmed::class]);
        actingAsAdmin();
        $made = makeRequisitionForConfirm(TransferRequisitionStatus::Requested);

        $this->service->confirm($made['requisition']);

        Event::assertDispatched(
            TransferConfirmed::class,
            fn ($e) => $e->requisitionId === $made['requisition']->id,
        );
    });

    it('does not fire TransferConfirmed when the confirm is rejected', function () {
        Event::fake([TransferConfirmed::class]);
        actingAsAdmin();
        $made = makeRequisitionForConfirm(TransferRequisitionStatus::Draft);

        try {
            $this->service->confirm($made['requisition']);
        } catch (InvalidDocumentStateException) {
            // expected
        }

        Event::assertNotDispatched(TransferConfirmed::class);
    });

    it('records the acting user in approved_by', function () {
        $admin = actingAsAdmin();
        $made = makeRequisitionForConfirm(TransferRequisitionStatus::Requested);

        $this->service->confirm($made['requisition']);

        expect($made['requisition']->fresh()->approved_by)->toBe($admin->id);
    });
});

// ===========================================================================
// cancelRequisition()
// ===========================================================================

describe('cancelRequisition()', function () {
    it('cancels Draft', function () {
        actingAsAdmin();
        $made = makeRequisitionForConfirm(TransferRequisitionStatus::Draft);

        $this->service->cancelRequisition($made['requisition']);

        expect($made['requisition']->fresh()->status)->toBe(TransferRequisitionStatus::Cancelled);
    });

    it('cancels Requested', function () {
        actingAsAdmin();
        $made = makeRequisitionForConfirm(TransferRequisitionStatus::Requested);

        $this->service->cancelRequisition($made['requisition']);

        expect($made['requisition']->fresh()->status)->toBe(TransferRequisitionStatus::Cancelled);
    });

    it('cancels UnderReviewFulfiller', function () {
        actingAsAdmin();
        $made = makeRequisitionForConfirm(TransferRequisitionStatus::UnderReviewFulfiller);

        $this->service->cancelRequisition($made['requisition']);

        expect($made['requisition']->fresh()->status)->toBe(TransferRequisitionStatus::Cancelled);
    });

    it('cancels UnderReviewRequestor', function () {
        actingAsAdmin();
        $made = makeRequisitionForConfirm(TransferRequisitionStatus::UnderReviewRequestor);

        $this->service->cancelRequisition($made['requisition']);

        expect($made['requisition']->fresh()->status)->toBe(TransferRequisitionStatus::Cancelled);
    });

    it('cancels Confirmed', function () {
        actingAsAdmin();
        $made = makeRequisitionForConfirm(TransferRequisitionStatus::Confirmed);

        $this->service->cancelRequisition($made['requisition']);

        expect($made['requisition']->fresh()->status)->toBe(TransferRequisitionStatus::Cancelled);
    });

    it('rejects every post-dispatch status', function (TransferRequisitionStatus $status) {
        // §3.7: the five terminal / post-dispatch states cannot be
        // cancelled.
        actingAsAdmin();
        $made = makeRequisitionForConfirm($status);

        expect(fn () => $this->service->cancelRequisition($made['requisition']))
            ->toThrow(InvalidDocumentStateException::class);
    })->with([
        TransferRequisitionStatus::Dispatched,
        TransferRequisitionStatus::PartiallyReceived,
        TransferRequisitionStatus::Completed,
        TransferRequisitionStatus::ClosedWithLoss,
        TransferRequisitionStatus::Cancelled,
    ]);

    it('carries the cancel action label and actual status on rejection', function () {
        actingAsAdmin();
        $made = makeRequisitionForConfirm(TransferRequisitionStatus::Dispatched);

        try {
            $this->service->cancelRequisition($made['requisition']);
            $this->fail('Expected InvalidDocumentStateException was not thrown.');
        } catch (InvalidDocumentStateException $e) {
            expect($e->action)->toBe('cancel');
            expect($e->actualStatus)->toBe('dispatched');
        }
    });

    it('does not set a cancelled_at column', function () {
        // §2.7 does not define a cancelled_at column on transfer_requisitions.
        // §19.2 explicitly forbids silently inventing columns.
        actingAsAdmin();
        $made = makeRequisitionForConfirm(TransferRequisitionStatus::Requested);

        $this->service->cancelRequisition($made['requisition']);

        expect($made['requisition']->fresh()->getAttributes())->not->toHaveKey('cancelled_at');
    });

    it('fires TransferCancelled', function () {
        Event::fake([TransferCancelled::class]);
        actingAsAdmin();
        $made = makeRequisitionForConfirm(TransferRequisitionStatus::Requested);

        $this->service->cancelRequisition($made['requisition']);

        Event::assertDispatched(
            TransferCancelled::class,
            fn ($e) => $e->requisitionId === $made['requisition']->id,
        );
    });

    it('does not fire TransferCancelled when the cancel is rejected', function () {
        Event::fake([TransferCancelled::class]);
        actingAsAdmin();
        $made = makeRequisitionForConfirm(TransferRequisitionStatus::Dispatched);

        try {
            $this->service->cancelRequisition($made['requisition']);
        } catch (InvalidDocumentStateException) {
            // expected
        }

        Event::assertNotDispatched(TransferCancelled::class);
    });

    it('does not change the status when the cancel is rejected', function () {
        actingAsAdmin();
        $made = makeRequisitionForConfirm(TransferRequisitionStatus::Completed);

        try {
            $this->service->cancelRequisition($made['requisition']);
        } catch (InvalidDocumentStateException) {
            // expected
        }

        expect($made['requisition']->fresh()->status)->toBe(TransferRequisitionStatus::Completed);
    });
});

// ===========================================================================
// Constructor / DI
// ===========================================================================

describe('service wiring', function () {
    it('resolves from the container', function () {
        expect(app(TransferRequisitionService::class))
            ->toBeInstanceOf(TransferRequisitionService::class);
    });

    it('injects a NegotiationService', function () {
        $service = app(TransferRequisitionService::class);

        $reflection = new ReflectionClass($service);
        $constructor = $reflection->getConstructor();
        $params = $constructor->getParameters();

        expect($params)->toHaveCount(1);
        expect($params[0]->getType()->getName())->toBe(App\Services\NegotiationService::class);
    });
});
