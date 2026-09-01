<?php

declare(strict_types=1);

use App\Enums\MovementType;
use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new InventoryService();
    $this->wh1 = Warehouse::factory()->create();
    $this->wh2 = Warehouse::factory()->create();
    $this->variant = ProductVariant::factory()->create(['base_unit_name' => 'piece']);

    WarehouseStock::create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->wh1->id,
        'on_hand_quantity' => 5000,
        'reserved_quantity' => 0,
    ]);

    WarehouseStock::create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->wh2->id,
        'on_hand_quantity' => 0,
        'reserved_quantity' => 0,
    ]);
});

it('recordMovement creates a stock movement and updates warehouse stock', function () {
    $movement = $this->service->recordMovement(
        variantId: $this->variant->id,
        warehouseId: $this->wh1->id,
        type: MovementType::Receive,
        baseQuantity: 100,
    );

    expect($movement->variant_id)->toBe($this->variant->id);
    expect($movement->quantity)->toBe(100);

    $this->assertDatabaseHas('warehouse_stock', [
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->wh1->id,
        'on_hand_quantity' => 5100,
    ]);
});

it('recordMovement reduces stock on ship', function () {
    $this->service->recordMovement(
        variantId: $this->variant->id,
        warehouseId: $this->wh1->id,
        type: MovementType::Ship,
        baseQuantity: -500,
    );

    $this->assertDatabaseHas('warehouse_stock', [
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->wh1->id,
        'on_hand_quantity' => 4500,
    ]);
});

it('recordMovement throws exception for insufficient stock', function () {
    $this->service->recordMovement(
        variantId: $this->variant->id,
        warehouseId: $this->wh1->id,
        type: MovementType::Ship,
        baseQuantity: -6000,
    );
})->throws(Exception::class, 'Insufficient stock for product');

it('recordMovement creates warehouse stock if not exists', function () {
    $wh3 = Warehouse::factory()->create();

    $this->service->recordMovement(
        variantId: $this->variant->id,
        warehouseId: $wh3->id,
        type: MovementType::Receive,
        baseQuantity: 100,
    );

    $this->assertDatabaseHas('warehouse_stock', [
        'variant_id' => $this->variant->id,
        'warehouse_id' => $wh3->id,
        'on_hand_quantity' => 100,
    ]);
});

it('lockStockForRequisition locks the correct amount of stock', function () {
    $requisition = TransferRequisition::create([
        'reference_code' => 'TRQ-LOCK-0001',
        'from_warehouse_id' => $this->wh1->id,
        'to_warehouse_id' => $this->wh2->id,
        'status' => 'requested',
        'requested_by' => 1,
        'requested_at' => now(),
    ]);

    TransferRequisitionItem::create([
        'requisition_id' => $requisition->id,
        'variant_id' => $this->variant->id,
        'requested_unit_name' => 'piece',
        'requested_unit_ratio' => 1,
        'requested_qty' => 1000,
        'requested_base_qty' => 1000,
        'approved_unit_name' => 'piece',
        'approved_unit_ratio' => 1,
        'approved_qty' => 1000,
        'approved_base_qty' => 1000,
    ]);

    $this->service->lockStockForRequisition($requisition->id);

    $this->assertDatabaseHas('warehouse_stock', [
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->wh1->id,
        'reserved_quantity' => 1000,
    ]);

    expect($requisition->fresh()->status->value)->toBe('confirmed');
});

it('lockStockForRequisition throws on insufficient unreserved stock', function () {
    $requisition = TransferRequisition::create([
        'reference_code' => 'TRQ-LOCK-0002',
        'from_warehouse_id' => $this->wh1->id,
        'to_warehouse_id' => $this->wh2->id,
        'status' => 'requested',
        'requested_by' => 1,
        'requested_at' => now(),
    ]);

    TransferRequisitionItem::create([
        'requisition_id' => $requisition->id,
        'variant_id' => $this->variant->id,
        'requested_unit_name' => 'piece',
        'requested_unit_ratio' => 1,
        'requested_qty' => 6000,
        'requested_base_qty' => 6000,
        'approved_unit_name' => 'piece',
        'approved_unit_ratio' => 1,
        'approved_qty' => 6000,
        'approved_base_qty' => 6000,
    ]);

    $this->service->lockStockForRequisition($requisition->id);
})->throws(Exception::class, 'Insufficient unreserved stock');

it('dispatchTransfer creates transit out movements and InTransit records', function () {
    $requisition = TransferRequisition::create([
        'reference_code' => 'TRQ-DISP-0001',
        'from_warehouse_id' => $this->wh1->id,
        'to_warehouse_id' => $this->wh2->id,
        'status' => 'confirmed',
        'requested_by' => 1,
        'requested_at' => now(),
    ]);

    TransferRequisitionItem::create([
        'requisition_id' => $requisition->id,
        'variant_id' => $this->variant->id,
        'requested_unit_name' => 'piece',
        'requested_unit_ratio' => 1,
        'requested_qty' => 500,
        'requested_base_qty' => 500,
        'approved_unit_name' => 'piece',
        'approved_unit_ratio' => 1,
        'approved_qty' => 500,
        'approved_base_qty' => 500,
    ]);

    $this->service->lockStockForRequisition($requisition->id);
    $this->service->dispatchTransfer($requisition->id);

    expect($requisition->fresh()->status->value)->toBe('dispatched');

    $this->assertDatabaseHas('warehouse_stock', [
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->wh1->id,
        'on_hand_quantity' => 4500,
        'reserved_quantity' => 0,
    ]);

    $this->assertDatabaseHas('in_transit', [
        'requisition_id' => $requisition->id,
        'variant_id' => $this->variant->id,
        'dispatched_base_qty' => 500,
        'status' => 'in_transit',
    ]);
});

it('dispatchTransfer throws if not confirmed', function () {
    $requisition = TransferRequisition::create([
        'reference_code' => 'TRQ-DISP-0002',
        'from_warehouse_id' => $this->wh1->id,
        'to_warehouse_id' => $this->wh2->id,
        'status' => 'requested',
        'requested_by' => 1,
        'requested_at' => now(),
    ]);

    TransferRequisitionItem::create([
        'requisition_id' => $requisition->id,
        'variant_id' => $this->variant->id,
        'requested_unit_name' => 'piece',
        'requested_unit_ratio' => 1,
        'requested_qty' => 100,
        'requested_base_qty' => 100,
    ]);

    $this->service->dispatchTransfer($requisition->id);
})->throws(Exception::class, 'Requisition must be confirmed before dispatch');

it('scanToReceive completes transfer without loss', function () {
    $requisition = TransferRequisition::create([
        'reference_code' => 'TRQ-RCV-0001',
        'from_warehouse_id' => $this->wh1->id,
        'to_warehouse_id' => $this->wh2->id,
        'status' => 'confirmed',
        'requested_by' => 1,
        'requested_at' => now(),
    ]);

    $item = TransferRequisitionItem::create([
        'requisition_id' => $requisition->id,
        'variant_id' => $this->variant->id,
        'requested_unit_name' => 'piece',
        'requested_unit_ratio' => 1,
        'requested_qty' => 300,
        'requested_base_qty' => 300,
        'approved_unit_name' => 'piece',
        'approved_unit_ratio' => 1,
        'approved_qty' => 300,
        'approved_base_qty' => 300,
    ]);

    $this->service->lockStockForRequisition($requisition->id);
    $this->service->dispatchTransfer($requisition->id);

    $this->service->scanToReceive($requisition->id, [
        $item->id => [
            'good_qty' => 300,
            'damaged_qty' => 0,
        ],
    ]);

    expect($requisition->fresh()->status->value)->toBe('completed');

    $destStock = WarehouseStock::where('variant_id', $this->variant->id)
        ->where('warehouse_id', $this->wh2->id)
        ->first();
    expect($destStock->on_hand_quantity)->toBe(300);
});

it('scanToReceive handles partial loss and creates loss ledger entry', function () {
    $requisition = TransferRequisition::create([
        'reference_code' => 'TRQ-RCV-0002',
        'from_warehouse_id' => $this->wh1->id,
        'to_warehouse_id' => $this->wh2->id,
        'status' => 'confirmed',
        'requested_by' => 1,
        'requested_at' => now(),
    ]);

    $item = TransferRequisitionItem::create([
        'requisition_id' => $requisition->id,
        'variant_id' => $this->variant->id,
        'requested_unit_name' => 'piece',
        'requested_unit_ratio' => 1,
        'requested_qty' => 200,
        'requested_base_qty' => 200,
        'approved_unit_name' => 'piece',
        'approved_unit_ratio' => 1,
        'approved_qty' => 200,
        'approved_base_qty' => 200,
    ]);

    $this->service->lockStockForRequisition($requisition->id);
    $this->service->dispatchTransfer($requisition->id);

    $this->service->scanToReceive($requisition->id, [
        $item->id => [
            'good_qty' => 180,
            'damaged_qty' => 10,
            'loss_category' => 'Damaged in Transit',
        ],
    ]);

    expect($requisition->fresh()->status->value)->toBe('closed_with_loss');

    $this->assertDatabaseHas('loss_ledgers', [
        'variant_id' => $this->variant->id,
        'damaged_base_qty' => 10,
        'lost_base_qty' => 10,
        'loss_category' => 'Damaged in Transit',
    ]);

    $destStock = WarehouseStock::where('variant_id', $this->variant->id)
        ->where('warehouse_id', $this->wh2->id)
        ->first();
    expect($destStock->on_hand_quantity)->toBe(180);
});
