<?php

declare(strict_types=1);

use App\Enums\InTransitStatus;
use App\Enums\StockMovementType;
use App\Enums\TransferRequisitionStatus;
use App\Models\InTransit;
use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Exception;

beforeEach(function () {
    $this->service = new InventoryService();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->origin = Warehouse::factory()->create();
    $this->destination = Warehouse::factory()->create();
    $this->variant = ProductVariant::factory()->create();

    // Stock the origin warehouse.
    $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Receive, 1000);
});

function makeConfirmedRequisition(InventoryService $service, $origin, $destination, $variant, int $approvedBaseQty = 240): TransferRequisition
{
    $requisition = TransferRequisition::factory()->create([
        'from_warehouse_id' => $origin->id,
        'to_warehouse_id' => $destination->id,
        'status' => TransferRequisitionStatus::Confirmed,
    ]);

    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $requisition->id,
        'product_variant_id' => $variant->id,
        'requested_unit_name' => 'Box',
        'requested_unit_ratio' => 24,
        'requested_qty' => 10,
        'requested_base_qty' => 240,
        'approved_unit_name' => 'Box',
        'approved_unit_ratio' => 24,
        'approved_qty' => $approvedBaseQty / 24,
        'approved_base_qty' => $approvedBaseQty,
    ]);

    return $requisition->fresh('items');
}

describe('dispatchTransfer', function () {
    it('refuses to dispatch a requisition that is not confirmed', function () {
        $requisition = TransferRequisition::factory()->create(['status' => TransferRequisitionStatus::Draft]);

        expect(fn () => $this->service->dispatchTransfer($requisition->id))->toThrow(Exception::class);
    });

    it('debits the origin warehouse and opens an in_transit row per item', function () {
        $requisition = makeConfirmedRequisition($this->service, $this->origin, $this->destination, $this->variant);

        $this->service->dispatchTransfer($requisition->id);

        expect($this->variant->onHandQuantity($this->origin->id))->toBe(760) // 1000 - 240
            ->and(InTransit::where('transfer_requisition_id', $requisition->id)->count())->toBe(1)
            ->and(InTransit::where('transfer_requisition_id', $requisition->id)->first()->status)
            ->toBe(InTransitStatus::InTransit);
    });

    it('marks the requisition dispatched and stamps dispatcher/timestamp', function () {
        $requisition = makeConfirmedRequisition($this->service, $this->origin, $this->destination, $this->variant);

        $this->service->dispatchTransfer($requisition->id);

        expect($requisition->fresh()->status)->toBe(TransferRequisitionStatus::Dispatched)
            ->and($requisition->fresh()->dispatched_by)->toBe($this->user->id)
            ->and($requisition->fresh()->dispatched_at)->not->toBeNull();
    });

    it('records shipped_base_qty on the item matching approved_base_qty', function () {
        $requisition = makeConfirmedRequisition($this->service, $this->origin, $this->destination, $this->variant, approvedBaseQty: 96);

        $this->service->dispatchTransfer($requisition->id);

        expect($requisition->items->first()->fresh()->shipped_base_qty)->toBe(96);
    });

    it('throws when approved_base_qty is null (ConfirmAction must materialize)', function () {
        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $this->origin->id,
            'to_warehouse_id' => $this->destination->id,
            'status' => TransferRequisitionStatus::Confirmed,
        ]);
        TransferRequisitionItem::factory()->create([
            'transfer_requisition_id' => $requisition->id,
            'product_variant_id' => $this->variant->id,
            'requested_base_qty' => 120,
            'approved_base_qty' => null,
            'approved_unit_name' => null,
            'approved_unit_ratio' => null,
        ]);

        expect(fn () => $this->service->dispatchTransfer($requisition->fresh('items')->id))
            ->toThrow(Exception::class, 'has no approved_base_qty');

        expect($this->variant->onHandQuantity($this->origin->id))->toBe(1000);
    });

    it('dispatches against the substitute variant when one was negotiated, not the original', function () {
        $substitute = ProductVariant::factory()->create();
        $this->service->recordMovement($substitute->id, $this->origin->id, StockMovementType::Receive, 500);

        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $this->origin->id,
            'to_warehouse_id' => $this->destination->id,
            'status' => TransferRequisitionStatus::Confirmed,
        ]);
        TransferRequisitionItem::factory()->create([
            'transfer_requisition_id' => $requisition->id,
            'product_variant_id' => $this->variant->id,
            'substitute_product_variant_id' => $substitute->id,
            'requested_base_qty' => 100,
            'approved_base_qty' => 100,
            'approved_unit_name' => 'piece',
            'approved_unit_ratio' => 1,
        ]);

        $this->service->dispatchTransfer($requisition->fresh('items')->id);

        expect($substitute->onHandQuantity($this->origin->id))->toBe(400) // debited
            ->and($this->variant->onHandQuantity($this->origin->id))->toBe(1000) // untouched
            ->and(InTransit::where('transfer_requisition_id', $requisition->id)->first()->product_variant_id)
            ->toBe($substitute->id);
    });

    it('rejects dispatch when origin stock is insufficient', function () {
        $requisition = makeConfirmedRequisition($this->service, $this->origin, $this->destination, $this->variant, approvedBaseQty: 5000);

        expect(fn () => $this->service->dispatchTransfer($requisition->id))->toThrow(Exception::class);
        expect($this->variant->onHandQuantity($this->origin->id))->toBe(1000); // unchanged
    });

    it('rejects dispatch when origin warehouse has zero stock for the variant', function () {
        $emptyVariant = ProductVariant::factory()->create();
        $requisition = makeConfirmedRequisition($this->service, $this->origin, $this->destination, $emptyVariant, approvedBaseQty: 100);

        expect(fn () => $this->service->dispatchTransfer($requisition->id))->toThrow(Exception::class);
    });

    it('dispatch subtracts exact quantity and leaves origin with remaining stock', function () {
        $requisition = makeConfirmedRequisition($this->service, $this->origin, $this->destination, $this->variant, approvedBaseQty: 240);
        $this->service->dispatchTransfer($requisition->id);

        expect($this->variant->onHandQuantity($this->origin->id))->toBe(760);
    });

    it('scanToReceive updates received_good_base_qty on the item', function () {
        $requisition = makeConfirmedRequisition($this->service, $this->origin, $this->destination, $this->variant, approvedBaseQty: 240);
        $this->service->dispatchTransfer($requisition->id);
        $item = $requisition->fresh('items')->items->first();

        $this->service->scanToReceive($requisition->id, [
            $item->id => ['good_qty' => 10, 'damaged_qty' => 0],
        ]);

        expect($item->fresh()->received_good_base_qty)->toBe(240)
            ->and($item->fresh()->received_damaged_base_qty)->toBe(0)
            ->and($item->fresh()->received_qty)->toBe(240);
    });

    it('scanToReceive with mixed good and damaged records both quantities and triggers loss', function () {
        $requisition = makeConfirmedRequisition($this->service, $this->origin, $this->destination, $this->variant, approvedBaseQty: 240);
        $this->service->dispatchTransfer($requisition->id);
        $item = $requisition->fresh('items')->items->first();

        ProductVariantPrice::recordNewPrice($this->variant, costPrice: 3.00, salePrice: 6.00);

        $this->service->scanToReceive($requisition->id, [
            $item->id => ['good_qty' => 8, 'damaged_qty' => 2, 'loss_category' => 'damaged_in_transit'],
        ]);

        expect($item->fresh()->received_good_base_qty)->toBe(192)
            ->and($item->fresh()->received_damaged_base_qty)->toBe(48)
            ->and($item->fresh()->received_qty)->toBe(240)
            ->and(LossLedger::where('transfer_requisition_id', $requisition->id)->count())->toBe(1)
            ->and($requisition->fresh()->status)->toBe(TransferRequisitionStatus::ClosedWithLoss);
    });

    it('scanToReceive sets in_transit status to Cleared', function () {
        $requisition = makeConfirmedRequisition($this->service, $this->origin, $this->destination, $this->variant, approvedBaseQty: 240);
        $this->service->dispatchTransfer($requisition->id);
        $item = $requisition->fresh('items')->items->first();

        $this->service->scanToReceive($requisition->id, [
            $item->id => ['good_qty' => 10, 'damaged_qty' => 0],
        ]);

        expect(InTransit::where('transfer_requisition_id', $requisition->id)->first()->status)
            ->toBe(InTransitStatus::Cleared);
    });
});

describe('scanToReceive', function () {
    it('refuses to receive a requisition that is not dispatched', function () {
        $requisition = TransferRequisition::factory()->create(['status' => TransferRequisitionStatus::Confirmed]);

        expect(fn () => $this->service->scanToReceive($requisition->id, []))->toThrow(Exception::class);
    });

    it('credits the destination warehouse and completes the requisition on a clean receipt', function () {
        $requisition = makeConfirmedRequisition($this->service, $this->origin, $this->destination, $this->variant);
        $this->service->dispatchTransfer($requisition->id);
        $item = $requisition->fresh('items')->items->first();

        $this->service->scanToReceive($requisition->id, [
            $item->id => ['good_qty' => 10, 'damaged_qty' => 0],
        ]);

        expect($this->variant->onHandQuantity($this->destination->id))->toBe(240)
            ->and($requisition->fresh()->status)->toBe(TransferRequisitionStatus::Completed)
            ->and($requisition->fresh()->received_by)->toBe($this->user->id)
            ->and(LossLedger::where('transfer_requisition_id', $requisition->id)->count())->toBe(0)
            ->and(InTransit::where('transfer_requisition_id', $requisition->id)->first()->status)
            ->toBe(InTransitStatus::Cleared);
    });

    it('writes a loss ledger for partial damage but remains partially received until fully received', function () {
        $requisition = makeConfirmedRequisition($this->service, $this->origin, $this->destination, $this->variant);
        $this->service->dispatchTransfer($requisition->id);
        $item = $requisition->fresh('items')->items->first();

        ProductVariantPrice::recordNewPrice($this->variant, costPrice: 5.00, salePrice: 10.00);

        // Approved 240 base units (10 boxes of 24); receive 8 good, 1 damaged, 1 short.
        // Total received = 9 boxes = 216 < 240 shipped → PartiallyReceived
        $this->service->scanToReceive($requisition->id, [
            $item->id => ['good_qty' => 8, 'damaged_qty' => 1, 'loss_category' => 'damaged_in_transit'],
        ]);

        $ledger = LossLedger::where('transfer_requisition_id', $requisition->id)->first();

        expect($this->variant->onHandQuantity($this->destination->id))->toBe(192) // 8 * 24
            ->and($ledger)->not->toBeNull()
            ->and($ledger->damaged_base_qty)->toBe(24) // 1 box * 24
            ->and($ledger->lost_base_qty)->toBe(24) // 1 box short * 24
            ->and((string) $ledger->unit_cost_price)->toBe('5.0000')
            ->and($ledger->total_financial_loss)->toBe(bcmul('48', '5.0000', 4))
            ->and($requisition->fresh()->status)->toBe(TransferRequisitionStatus::PartiallyReceived);
    });

    it('treats an item entirely absent from the scan payload as a 100% write-off', function () {
        $requisition = makeConfirmedRequisition($this->service, $this->origin, $this->destination, $this->variant, approvedBaseQty: 240);
        $this->service->dispatchTransfer($requisition->id);
        $item = $requisition->fresh('items')->items->first();

        ProductVariantPrice::recordNewPrice($this->variant, costPrice: 2.50, salePrice: 5.00);

        // Omit the item entirely from the payload.
        $this->service->scanToReceive($requisition->id, []);

        $ledger = LossLedger::where('transfer_requisition_id', $requisition->id)->first();

        expect($this->variant->onHandQuantity($this->destination->id))->toBe(0)
            ->and($ledger)->not->toBeNull()
            ->and($ledger->lost_base_qty)->toBe(240)
            ->and($ledger->damaged_base_qty)->toBe(0)
            ->and($ledger->loss_category)->toBe('omitted_from_intake')
            ->and($ledger->total_financial_loss)->toBe(bcmul('240', '2.5000', 4))
            ->and($item->fresh()->received_good_base_qty)->toBe(0)
            ->and($requisition->fresh()->status)->toBe(TransferRequisitionStatus::PartiallyReceived);
    });

    it('falls back to a zero cost snapshot when the variant has no current price', function () {
        $requisition = makeConfirmedRequisition($this->service, $this->origin, $this->destination, $this->variant);
        $this->service->dispatchTransfer($requisition->id);

        // No ProductVariantPrice ever created for $this->variant.
        $this->service->scanToReceive($requisition->id, []);

        $ledger = LossLedger::where('transfer_requisition_id', $requisition->id)->first();

        expect((string) $ledger->unit_cost_price)->toBe('0.0000')
            ->and($ledger->total_financial_loss)->toBe('0.0000');
    });

    it('receives against the substitute variant when one was dispatched', function () {
        $substitute = ProductVariant::factory()->create();
        $this->service->recordMovement($substitute->id, $this->origin->id, StockMovementType::Receive, 500);

        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $this->origin->id,
            'to_warehouse_id' => $this->destination->id,
            'status' => TransferRequisitionStatus::Confirmed,
        ]);
        TransferRequisitionItem::factory()->create([
            'transfer_requisition_id' => $requisition->id,
            'product_variant_id' => $this->variant->id,
            'substitute_product_variant_id' => $substitute->id,
            'requested_base_qty' => 100,
            'approved_base_qty' => 100,
            'approved_unit_name' => 'piece',
            'approved_unit_ratio' => 1,
        ]);
        $requisition = $requisition->fresh('items');
        $this->service->dispatchTransfer($requisition->id);
        $item = $requisition->fresh('items')->items->first();

        $this->service->scanToReceive($requisition->id, [
            $item->id => ['good_qty' => 100, 'damaged_qty' => 0],
        ]);

        expect($substitute->onHandQuantity($this->destination->id))->toBe(100)
            ->and($this->variant->onHandQuantity($this->destination->id))->toBe(0);
    });

    it('dispatchTransfer handles multiple items in a single requisition', function () {
        $variant2 = ProductVariant::factory()->create();
        $this->service->recordMovement($variant2->id, $this->origin->id, StockMovementType::Receive, 500);

        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $this->origin->id,
            'to_warehouse_id' => $this->destination->id,
            'status' => TransferRequisitionStatus::Confirmed,
        ]);

        TransferRequisitionItem::factory()->create([
            'transfer_requisition_id' => $requisition->id,
            'product_variant_id' => $this->variant->id,
            'requested_base_qty' => 100,
            'approved_base_qty' => 100,
            'approved_unit_name' => 'Box',
            'approved_unit_ratio' => 24,
            'approved_qty' => 5,
        ]);

        TransferRequisitionItem::factory()->create([
            'transfer_requisition_id' => $requisition->id,
            'product_variant_id' => $variant2->id,
            'requested_base_qty' => 50,
            'approved_base_qty' => 50,
            'approved_unit_name' => 'piece',
            'approved_unit_ratio' => 1,
            'approved_qty' => 50,
        ]);

        $this->service->dispatchTransfer($requisition->fresh('items')->id);

        expect($this->variant->onHandQuantity($this->origin->id))->toBe(900) // 1000 - 100
            ->and($variant2->onHandQuantity($this->origin->id))->toBe(450) // 500 - 50
            ->and(InTransit::where('transfer_requisition_id', $requisition->id)->count())->toBe(2)
            ->and($requisition->fresh()->status)->toBe(TransferRequisitionStatus::Dispatched);
    });

    it('scanToReceive handles multiple items with mixed outcomes', function () {
        $variant2 = ProductVariant::factory()->create();
        $this->service->recordMovement($variant2->id, $this->origin->id, StockMovementType::Receive, 500);

        $requisition = TransferRequisition::factory()->create([
            'from_warehouse_id' => $this->origin->id,
            'to_warehouse_id' => $this->destination->id,
            'status' => TransferRequisitionStatus::Confirmed,
        ]);

        $item1 = TransferRequisitionItem::factory()->create([
            'transfer_requisition_id' => $requisition->id,
            'product_variant_id' => $this->variant->id,
            'requested_base_qty' => 100,
            'approved_base_qty' => 100,
            'approved_unit_name' => 'Box',
            'approved_unit_ratio' => 24,
            'approved_qty' => 5,
        ]);

        $item2 = TransferRequisitionItem::factory()->create([
            'transfer_requisition_id' => $requisition->id,
            'product_variant_id' => $variant2->id,
            'requested_base_qty' => 50,
            'approved_base_qty' => 50,
            'approved_unit_name' => 'piece',
            'approved_unit_ratio' => 1,
            'approved_qty' => 50,
        ]);

        $this->service->dispatchTransfer($requisition->fresh('items')->id);

        ProductVariantPrice::recordNewPrice($this->variant, costPrice: 3.00, salePrice: 6.00);
        ProductVariantPrice::recordNewPrice($variant2, costPrice: 2.00, salePrice: 4.00);

        // Item 1: all good, Item 2: partial damage (10 short = 20 received)
        // Total received = 120 + 20 = 140 < 150 shipped → PartiallyReceived
        $this->service->scanToReceive($requisition->id, [
            $item1->id => ['good_qty' => 5, 'damaged_qty' => 0],
            $item2->id => ['good_qty' => 40, 'damaged_qty' => 5, 'loss_category' => 'damaged_in_transit'],
        ]);

        expect($this->variant->onHandQuantity($this->destination->id))->toBe(120) // 5 boxes * 24 units
            ->and($variant2->onHandQuantity($this->destination->id))->toBe(40)
            ->and($requisition->fresh()->status)->toBe(TransferRequisitionStatus::PartiallyReceived)
            ->and(LossLedger::where('transfer_requisition_id', $requisition->id)->count())->toBe(1);
    });
});
