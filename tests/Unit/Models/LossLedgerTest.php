<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->service = new InventoryService();
    $this->user = User::factory()->admin()->create();
    $this->actingAs($this->user);

    $this->origin = Warehouse::factory()->create();
    $this->destination = Warehouse::factory()->create();
    $this->variant = ProductVariant::factory()->create();

    // Stock the origin warehouse.
    $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Receive, 1000);
});

describe('LossLedger - v10 snapshot_unit_cost', function () {
    it('snapshot_unit_cost_falls_back_to_zero_when_no_current_price_exists', function () {
        $variantWithoutPrice = ProductVariant::factory()->withoutPrice()->create();
        $this->service->recordMovement($variantWithoutPrice->id, $this->origin->id, StockMovementType::Receive, 1000);

        $requisition = $this->makeConfirmedRequisition($this->service, $this->origin, $this->destination, $variantWithoutPrice);
        $this->service->dispatchTransfer($requisition->id);

        $this->service->scanToReceive($requisition->id, []);

        $ledger = LossLedger::where('transfer_requisition_id', $requisition->id)->first();

        expect((string) $ledger->unit_cost_price)->toBe('0.0000')
            ->and($ledger->total_financial_loss)->toBe('0.0000');
    });

    it('snapshot_unit_cost_reflects_call_time_price_not_dispatch_time_price', function () {
        // Use default 240 base qty (10 boxes * 24 ratio)
        $requisition = $this->makeConfirmedRequisition($this->service, $this->origin, $this->destination, $this->variant, 240);
        $this->service->dispatchTransfer($requisition->id);
        $item = $requisition->fresh('items')->items->first();

        // Set a known cost price with 4 decimals
        ProductVariantPrice::recordNewPrice($this->variant, 12.3456, 24.6912);

        // Ship 240 base units, receive 6 good + 2 damaged = (6+2)*24 = 192 base units received
        // Lost = 240 - (144 + 48) = 48
        // Damaged = 48
        // Total loss base = 96
        // Cost price = 12.3456
        // Expected total = 96 * 12.3456 = 1185.1776 (bcmul with scale 4)
        $this->service->scanToReceive($requisition->id, [
            $item->id => ['good_qty' => 6, 'damaged_qty' => 2],
        ]);

        $ledger = LossLedger::where('transfer_requisition_id', $requisition->id)->first();

        expect($ledger)->not->toBeNull();

        $expectedLoss = bcmul('96', '12.3456', 4);

        expect((string) $ledger->lost_base_qty)->toBe('48')
            ->and((string) $ledger->damaged_base_qty)->toBe('48')
            ->and((string) $ledger->unit_cost_price)->toBe('12.3456')
            ->and((string) $ledger->total_financial_loss)->toBe($expectedLoss);
    });

    it('snapshot_unit_cost_uses_existing_current_price_if_no_new_price_recorded', function () {
        // Create a fresh variant without auto-created price, then add one manually
        $variantWithPrice = ProductVariant::factory()->withoutPrice()->create();
        ProductVariantPrice::factory()->forVariant($variantWithPrice)->create(['cost_price' => '50.0000']);

        $this->service->recordMovement($variantWithPrice->id, $this->origin->id, StockMovementType::Receive, 1000);

        $requisition = $this->makeConfirmedRequisition($this->service, $this->origin, $this->destination, $variantWithPrice, 240);
        $this->service->dispatchTransfer($requisition->id);

        $this->service->scanToReceive($requisition->id, [
            $requisition->items->first()->id => [
                'good_qty' => 5,
                'damaged_qty' => 0,
                'loss_category' => 'shortfall',
            ],
        ]);

        $ledger = LossLedger::where('transfer_requisition_id', $requisition->id)->first();

        expect((string) $ledger->unit_cost_price)->toBe('50.0000');
    });
});

function makeConfirmedRequisitionForLossLedger(
    InventoryService $service,
    Warehouse $origin,
    Warehouse $destination,
    ProductVariant $variant,
    int $approvedBaseQty = 240
): App\Models\TransferRequisition {
    $requisition = App\Models\TransferRequisition::factory()->create([
        'from_warehouse_id' => $origin->id,
        'to_warehouse_id' => $destination->id,
        'status' => App\Enums\TransferRequisitionStatus::Confirmed,
    ]);

    App\Models\TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $requisition->id,
        'product_variant_id' => $variant->id,
        'requested_unit_name' => 'Box',
        'requested_unit_ratio' => 24,
        'requested_qty' => 10,
        'requested_base_qty' => 240,
        'approved_unit_name' => 'Box',
        'approved_unit_ratio' => 24,
        'approved_qty' => 10,
        'approved_base_qty' => 240,
    ]);

    $service->recordMovement($variant->id, $origin->id, StockMovementType::Receive, $approvedBaseQty);

    return $requisition;
}
