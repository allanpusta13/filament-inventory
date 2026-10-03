<?php

declare(strict_types=1);

use App\Enums\InTransitStatus;
use App\Enums\LossCategory;
use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use App\Enums\StockMovementType;
use App\Enums\TransferRequisitionStatus;
use App\Models\DirectTransfer;
use App\Models\DirectTransferItem;
use App\Models\InTransit;
use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\StockMovementIdempotencyKey;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\TransferRequisitionItemRevision;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Operations factory tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §5.9 TransferRequisitionFactory (+ requested/confirmed/dispatched
 *     states); §5.10 TransferRequisitionItemFactory;
 *     §5.20 TransferRequisitionItemRevisionFactory;
 *     §5.11 InTransitFactory; §5.12 LossLedgerFactory;
 *     §5.17 DirectTransferFactory; §5.18 DirectTransferItemFactory;
 *     §5.19 StockMovementFactory; §5.21
 *     StockMovementIdempotencyKeyFactory.
 *   - §3.11 LossLedger::calculateTotalFinancialLoss() — the factory
 *     uses `bcmul` at 4dp, not float multiplication.
 */
uses(RefreshDatabase::class);

// ===========================================================================
// TransferRequisitionFactory (§5.9)
// ===========================================================================

describe('TransferRequisitionFactory', function () {
    it('produces the default Draft state', function () {
        $req = TransferRequisition::factory()->create();

        expect($req->status)->toBe(TransferRequisitionStatus::Draft);
        expect($req->from_warehouse_id)->not->toBe($req->to_warehouse_id);
    });

    it('auto-creates both warehouses and the requester', function () {
        $req = TransferRequisition::factory()->create();

        expect($req->fromWarehouse)->toBeInstanceOf(Warehouse::class);
        expect($req->toWarehouse)->toBeInstanceOf(Warehouse::class);
        expect($req->requested_by)->not->toBeNull();
    });

    it('produces the requested() state', function () {
        $req = TransferRequisition::factory()->requested()->create();

        expect($req->status)->toBe(TransferRequisitionStatus::Requested);
        expect($req->requested_at)->not->toBeNull();
    });

    it('produces the confirmed() state', function () {
        $req = TransferRequisition::factory()->confirmed()->create();

        expect($req->status)->toBe(TransferRequisitionStatus::Confirmed);
        expect($req->approved_at)->not->toBeNull();
        expect($req->approved_by)->not->toBeNull();
    });

    it('produces the dispatched() state', function () {
        $req = TransferRequisition::factory()->dispatched()->create();

        expect($req->status)->toBe(TransferRequisitionStatus::Dispatched);
        expect($req->dispatched_at)->not->toBeNull();
        expect($req->dispatched_by)->not->toBeNull();
    });
});

// ===========================================================================
// TransferRequisitionItemFactory (§5.10)
// ===========================================================================

describe('TransferRequisitionItemFactory', function () {
    it('produces a requested leg with pc unit and matching base qty', function () {
        $item = TransferRequisitionItem::factory()->create();

        expect($item->requested_unit_name)->toBe('pc');
        expect($item->requested_unit_ratio)->toBe(1);
        expect($item->requested_qty)->toBeGreaterThanOrEqual(1);
        expect($item->requested_qty)->toBeLessThanOrEqual(20);
        expect($item->requested_base_qty)->toBe($item->requested_qty);
    });

    it('leaves the approved leg null — materialized at confirm time', function () {
        // §6.3: NegotiationService::materializeRequestedAsApproved()
        // fills this at confirm. The factory does not pre-populate it.
        $item = TransferRequisitionItem::factory()->create();

        expect($item->approved_unit_name)->toBeNull();
        expect($item->approved_unit_ratio)->toBeNull();
        expect($item->approved_qty)->toBeNull();
        expect($item->approved_base_qty)->toBeNull();
    });

    it('leaves substitute_product_variant_id null by default', function () {
        expect(TransferRequisitionItem::factory()->create()->substitute_product_variant_id)->toBeNull();
    });

    it('auto-creates the parent requisition and the variant', function () {
        $item = TransferRequisitionItem::factory()->create();

        expect($item->transferRequisition)->toBeInstanceOf(TransferRequisition::class);
        expect($item->productVariant)->toBeInstanceOf(ProductVariant::class);
    });
});

// ===========================================================================
// TransferRequisitionItemRevisionFactory (§5.20)
// ===========================================================================

describe('TransferRequisitionItemRevisionFactory', function () {
    it('produces the default Fulfiller + Pending state', function () {
        $revision = TransferRequisitionItemRevision::factory()->create();

        expect($revision->side)->toBe(NegotiationSide::Fulfiller);
        expect($revision->status)->toBe(RevisionStatus::Pending);
    });

    it('produces a proposed leg with pc unit and matching base qty', function () {
        $revision = TransferRequisitionItemRevision::factory()->create();

        expect($revision->proposed_unit_name)->toBe('pc');
        expect($revision->proposed_unit_ratio)->toBe(1);
        expect($revision->proposed_qty)->toBeGreaterThanOrEqual(1);
        expect($revision->proposed_base_qty)->toBe($revision->proposed_qty);
    });

    it('leaves substitute and responds_to nullable', function () {
        $revision = TransferRequisitionItemRevision::factory()->create();

        expect($revision->substitute_product_variant_id)->toBeNull();
        expect($revision->responds_to_revision_id)->toBeNull();
        expect($revision->negotiation_reason)->toBeNull();
    });
});

// ===========================================================================
// InTransitFactory (§5.11)
// ===========================================================================

describe('InTransitFactory', function () {
    it('produces the default InTransit state', function () {
        $inTransit = InTransit::factory()->create();

        expect($inTransit->status)->toBe(InTransitStatus::InTransit);
        expect($inTransit->dispatched_base_qty)->toBeGreaterThanOrEqual(1);
        expect($inTransit->dispatched_base_qty)->toBeLessThanOrEqual(50);
        expect($inTransit->dispatched_at)->not->toBeNull();
        expect($inTransit->cleared_at)->toBeNull();
    });

    it('auto-creates the parent requisition, item, and variant', function () {
        $inTransit = InTransit::factory()->create();

        expect($inTransit->transferRequisition)->toBeInstanceOf(TransferRequisition::class);
        expect($inTransit->transferRequisitionItem)->toBeInstanceOf(TransferRequisitionItem::class);
        expect($inTransit->productVariant)->toBeInstanceOf(ProductVariant::class);
    });
});

// ===========================================================================
// LossLedgerFactory (§5.12)
// ===========================================================================

describe('LossLedgerFactory', function () {
    it('produces the §5.12 quantity ranges', function () {
        $loss = LossLedger::factory()->create();

        expect($loss->lost_base_qty)->toBeGreaterThanOrEqual(0);
        expect($loss->lost_base_qty)->toBeLessThanOrEqual(10);
        expect($loss->damaged_base_qty)->toBeGreaterThanOrEqual(0);
        expect($loss->damaged_base_qty)->toBeLessThanOrEqual(5);
    });

    it('produces a loss_category from the LossCategory set', function () {
        $loss = LossLedger::factory()->create();

        expect($loss->loss_category)->toBeInstanceOf(LossCategory::class);
    });

    it('computes total_financial_loss with bcmul at 4dp', function () {
        // §3.11 / §5.12: total = bcmul(cost, lost + damaged, 4). This
        // is the load-bearing assertion — a float multiplication would
        // drift on some inputs and the model would be out of sync with
        // the service-written row shape.
        $loss = LossLedger::factory()->create([
            'lost_base_qty' => 3,
            'damaged_base_qty' => 2,
            'unit_cost_price' => '12.3456',
        ]);

        $expected = bcmul('12.3456', '5', 4);
        expect($loss->total_financial_loss)->toBe($expected);
    });

    it('produces a 4dp string for total_financial_loss', function () {
        $loss = LossLedger::factory()->create();
        expect($loss->total_financial_loss)->toBeString();
        expect($loss->total_financial_loss)->toMatch('/^\d+\.\d{4}$/');
    });

    it('auto-creates the requisition, variant, and warehouse', function () {
        $loss = LossLedger::factory()->create();

        expect($loss->transferRequisition)->toBeInstanceOf(TransferRequisition::class);
        expect($loss->productVariant)->toBeInstanceOf(ProductVariant::class);
        expect($loss->warehouse)->toBeInstanceOf(Warehouse::class);
    });
});

// ===========================================================================
// DirectTransferFactory (§5.17) + DirectTransferItemFactory (§5.18)
// ===========================================================================

describe('DirectTransferFactory', function () {
    it('produces distinct from/to warehouses and transferred_at', function () {
        $dt = DirectTransfer::factory()->create();

        expect($dt->from_warehouse_id)->not->toBe($dt->to_warehouse_id);
        expect($dt->transferred_at)->not->toBeNull();
        expect($dt->transferred_by)->not->toBeNull();
    });

    it('produces a non-empty notes string', function () {
        expect(DirectTransfer::factory()->create()->notes)->toBeString()->not->toBe('');
    });

    it('auto-creates both warehouses and the transferred-by user', function () {
        $dt = DirectTransfer::factory()->create();

        expect($dt->fromWarehouse)->toBeInstanceOf(Warehouse::class);
        expect($dt->toWarehouse)->toBeInstanceOf(Warehouse::class);
        expect($dt->transferredBy)->not->toBeNull();
    });
});

describe('DirectTransferItemFactory', function () {
    it('produces a pc unit with matching base qty', function () {
        $item = DirectTransferItem::factory()->create();

        expect($item->unit_name)->toBe('pc');
        expect($item->unit_ratio)->toBe(1);
        expect($item->qty)->toBeGreaterThanOrEqual(1);
        expect($item->qty)->toBeLessThanOrEqual(20);
        expect($item->base_qty)->toBe($item->qty);
    });

    it('auto-creates the parent transfer and the variant', function () {
        $item = DirectTransferItem::factory()->create();

        expect($item->directTransfer)->toBeInstanceOf(DirectTransfer::class);
        expect($item->productVariant)->toBeInstanceOf(ProductVariant::class);
    });
});

// ===========================================================================
// StockMovementFactory (§5.19)
// ===========================================================================

describe('StockMovementFactory', function () {
    it('produces the §5.19 Adjustment default with positive qty', function () {
        $movement = StockMovement::factory()->create();

        expect($movement->type)->toBe(StockMovementType::Adjustment);
        expect($movement->quantity)->toBeGreaterThanOrEqual(1);
        expect($movement->quantity)->toBeLessThanOrEqual(50);
        expect($movement->unit_name_used)->toBe('pc');
        expect($movement->unit_ratio_used)->toBe(1);
    });

    it('auto-creates the variant, warehouse, and creator', function () {
        $movement = StockMovement::factory()->create();

        expect($movement->productVariant)->toBeInstanceOf(ProductVariant::class);
        expect($movement->warehouse)->toBeInstanceOf(Warehouse::class);
        expect($movement->created_by)->not->toBeNull();
    });
});

// ===========================================================================
// StockMovementIdempotencyKeyFactory (§5.21)
// ===========================================================================

describe('StockMovementIdempotencyKeyFactory', function () {
    it('produces a 64-character hex sha256 checksum', function () {
        $key = StockMovementIdempotencyKey::factory()->create();

        expect($key->payload_checksum)->toMatch('/^[a-f0-9]{64}$/');
    });

    it('produces an empty resulting_item_states array', function () {
        expect(StockMovementIdempotencyKey::factory()->create()->resulting_item_states)->toBe([]);
    });

    it('sets created_at explicitly because $timestamps = false', function () {
        // §3.22: `$timestamps = false` — Eloquent does not auto-write
        // `created_at`, so the factory sets it.
        $key = StockMovementIdempotencyKey::factory()->create();

        expect($key->created_at)->not->toBeNull();
    });

    it('produces unique checksums across a batch', function () {
        $keys = StockMovementIdempotencyKey::factory()->count(20)->create();
        expect($keys->pluck('payload_checksum')->unique())->toHaveCount(20);
    });

    it('auto-creates the parent requisition', function () {
        $key = StockMovementIdempotencyKey::factory()->create();
        expect($key->transferRequisition)->toBeInstanceOf(TransferRequisition::class);
    });
});
