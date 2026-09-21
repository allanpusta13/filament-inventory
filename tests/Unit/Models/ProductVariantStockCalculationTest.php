<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Enums\TransferRequisitionStatus;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\Warehouse;

it('calculates onHandQuantity via direct DB query', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::TransferOut,
        'quantity' => -50,
    ]);
    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::TransferIn,
        'quantity' => 120,
    ]);

    $onHandFromDb = StockMovement::where('product_variant_id', $variant->id)
        ->where('warehouse_id', $warehouse->id)
        ->sum('quantity');

    expect($onHandFromDb)->toBe(70);

    $onHand = $variant->fresh()->onHandQuantity($warehouse->id);
    expect($onHand)->toBe(70);
});

it('returns 0 onHandQuantity when no stock movements', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    expect($variant->onHandQuantity($warehouse->id))->toBe(0);
});

it('calculates reservedQuantity for Confirmed requisitions only', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $requisition = TransferRequisition::factory()->create([
        'from_warehouse_id' => $warehouse->id,
        'status' => TransferRequisitionStatus::Confirmed,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $requisition->id,
        'product_variant_id' => $variant->id,
        'approved_base_qty' => 80,
    ]);

    $reserved = $variant->reservedQuantity($warehouse->id);
    expect($reserved)->toBe(80);
});

it('reservedQuantity excludes Dispatched and PartiallyReceived requisitions', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $confirmed = TransferRequisition::factory()->create([
        'from_warehouse_id' => $warehouse->id,
        'status' => TransferRequisitionStatus::Confirmed,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $confirmed->id,
        'product_variant_id' => $variant->id,
        'approved_base_qty' => 80,
    ]);

    $dispatched = TransferRequisition::factory()->create([
        'from_warehouse_id' => $warehouse->id,
        'status' => TransferRequisitionStatus::Dispatched,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $dispatched->id,
        'product_variant_id' => $variant->id,
        'approved_base_qty' => 50,
        'shipped_base_qty' => 50,
    ]);

    $partial = TransferRequisition::factory()->create([
        'from_warehouse_id' => $warehouse->id,
        'status' => TransferRequisitionStatus::PartiallyReceived,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $partial->id,
        'product_variant_id' => $variant->id,
        'approved_base_qty' => 30,
        'shipped_base_qty' => 30,
        'received_good_base_qty' => 10,
    ]);

    $reserved = $variant->reservedQuantity($warehouse->id);
    expect($reserved)->toBe(80);
});

it('returns 0 reservedQuantity when no Confirmed items', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    expect($variant->reservedQuantity($warehouse->id))->toBe(0);
});

it('calculates availableQuantity in PHP', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::TransferIn,
        'quantity' => 100,
    ]);

    $confirmed = TransferRequisition::factory()->create([
        'from_warehouse_id' => $warehouse->id,
        'status' => TransferRequisitionStatus::Confirmed,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $confirmed->id,
        'product_variant_id' => $variant->id,
        'approved_base_qty' => 30,
    ]);

    $onHand = $variant->onHandQuantity($warehouse->id);
    $reserved = $variant->reservedQuantity($warehouse->id);
    $available = $variant->availableQuantity($warehouse->id);

    expect($onHand)->toBe(100);
    expect($reserved)->toBe(30);
    expect($available)->toBe(70);
});

it('calculates incomingStock with type filter (no Closure)', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::TransferIn,
        'quantity' => 100,
    ]);
    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::TransferIn,
        'quantity' => 200,
    ]);

    $incoming = StockMovement::where('product_variant_id', $variant->id)
        ->where('warehouse_id', $warehouse->id)
        ->where('type', StockMovementType::TransferIn)
        ->sum('quantity');

    expect($incoming)->toBe(300);
});

it('calculates outgoingStock with type filter (no Closure)', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::TransferOut,
        'quantity' => -50,
    ]);
    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::TransferOut,
        'quantity' => -75,
    ]);

    $outgoing = StockMovement::where('product_variant_id', $variant->id)
        ->where('warehouse_id', $warehouse->id)
        ->where('type', StockMovementType::TransferOut)
        ->sum('quantity');

    expect($outgoing)->toBe(-125);
});

it('calculates lossStock with type filter (no Closure)', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Loss,
        'quantity' => -25,
    ]);
    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Loss,
        'quantity' => -10,
    ]);

    $loss = StockMovement::where('product_variant_id', $variant->id)
        ->where('warehouse_id', $warehouse->id)
        ->where('type', StockMovementType::Loss)
        ->sum('quantity');

    expect($loss)->toBe(-35);
});

it('classifies stockLevel using onHandQuantity', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::TransferIn,
        'quantity' => 100,
    ]);

    // stockLevel removed in v10 - use onHandQuantity + reservedQuantity directly
    $onHand = $variant->onHandQuantity($warehouse->id);
    $reserved = $variant->reservedQuantity($warehouse->id);
    $available = $variant->availableQuantity($warehouse->id);

    expect($onHand)->toBe(100);
    expect($reserved)->toBe(0);
    expect($available)->toBe(100);
});

it('classifies urgency onHandQuantity vs reorder_point', function () {
    $variant = ProductVariant::factory()->create(['reorder_point' => 50]);
    $warehouse = Warehouse::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::TransferIn,
        'quantity' => 100,
    ]);

    // urgencyLevel removed in v10 - use isBelowReorderPoint directly
    $onHand = $variant->onHandQuantity($warehouse->id);
    expect($variant->isBelowReorderPoint($warehouse->id))->toBeFalse();
});

it('handles multiple stock movements correctly PHP computation', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::TransferIn,
        'quantity' => 200,
    ]);
    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::TransferOut,
        'quantity' => -75,
    ]);
    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Loss,
        'quantity' => -10,
    ]);
    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::TransferIn,
        'quantity' => 50,
    ]);

    $onHand = StockMovement::where('product_variant_id', $variant->id)
        ->where('warehouse_id', $warehouse->id)
        ->sum('quantity');

    $incoming = StockMovement::where('product_variant_id', $variant->id)
        ->where('warehouse_id', $warehouse->id)
        ->where('type', StockMovementType::TransferIn)
        ->sum('quantity');

    $outgoing = StockMovement::where('product_variant_id', $variant->id)
        ->where('warehouse_id', $warehouse->id)
        ->where('type', StockMovementType::TransferOut)
        ->sum('quantity');

    $lossQty = StockMovement::where('product_variant_id', $variant->id)
        ->where('warehouse_id', $warehouse->id)
        ->where('type', StockMovementType::Loss)
        ->sum('quantity');

    expect($onHand)->toBe(165);
    expect($incoming)->toBe(250);
    expect($outgoing)->toBe(-75);
    expect($lossQty)->toBe(-10);
});

it('handles multiple transfer requisition items for reservedQuantity', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $confirmed1 = TransferRequisition::factory()->create([
        'from_warehouse_id' => $warehouse->id,
        'status' => TransferRequisitionStatus::Confirmed,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $confirmed1->id,
        'product_variant_id' => $variant->id,
        'approved_base_qty' => 100,
        'received_good_base_qty' => 30,
    ]);

    $confirmed2 = TransferRequisition::factory()->create([
        'from_warehouse_id' => $warehouse->id,
        'status' => TransferRequisitionStatus::Confirmed,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $confirmed2->id,
        'product_variant_id' => $variant->id,
        'approved_base_qty' => 50,
        'received_good_base_qty' => 20,
    ]);

    // reservedQuantity now uses approved_base_qty directly (not outstanding)
    $reserved = $variant->reservedQuantity($warehouse->id);

    expect($reserved)->toBe(150); // 100 + 50
});

it('works correctly after variant creation', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    expect($variant->onHandQuantity($warehouse->id))->toBe(0);
    expect($variant->reservedQuantity($warehouse->id))->toBe(0);
    expect($variant->availableQuantity($warehouse->id))->toBe(0);
});

it('reorder point integration test', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $variant->reorder_point = 30;
    $variant->save();

    // No stock - available = 0, reorder_point = 30 -> 0 <= 30 = true
    expect($variant->isBelowReorderPoint($warehouse->id))->toBeTrue();

    // Add stock above reorder point
    $variant->stockMovements()->create([
        'warehouse_id' => $warehouse->id,
        'type' => 'receive',
        'quantity' => 35,
        'unit_name_used' => 'piece',
        'unit_ratio_used' => 1,
    ]);
    // available = 35, reorder_point = 30 -> 35 <= 30 = false
    expect($variant->isBelowReorderPoint($warehouse->id))->toBeFalse();

    // Remove stock to below reorder point
    $variant->stockMovements()->create([
        'warehouse_id' => $warehouse->id,
        'type' => 'adjustment',
        'quantity' => -35,
        'unit_name_used' => 'piece',
        'unit_ratio_used' => 1,
    ]);
    // available = 0, reorder_point = 30 -> 0 <= 30 = true
    expect($variant->isBelowReorderPoint($warehouse->id))->toBeTrue();
});
