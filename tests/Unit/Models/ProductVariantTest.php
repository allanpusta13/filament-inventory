<?php

declare(strict_types=1);

use App\Enums\TransferRequisitionStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\Warehouse;

it('belongs to a product', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();

    expect($variant->product->is($product))->toBeTrue();
});

it('casts attributes and images to arrays', function () {
    $variant = ProductVariant::factory()->create([
        'attributes' => ['roast' => 'Medium'],
        'images' => ['a.jpg', 'b.jpg'],
    ]);

    $fresh = $variant->fresh();

    expect($fresh->attributes)->toBeArray()->and($fresh->attributes)->toBe(['roast' => 'Medium'])
        ->and($fresh->images)->toBe(['a.jpg', 'b.jpg']);
});

it('reports below reorder point correctly', function () {
    $variant = ProductVariant::factory()->create(['reorder_point' => 10]);
    $warehouse = Warehouse::factory()->create();

    // No stock - available = 0, reorder_point = 10 -> 0 <= 10 = true
    expect($variant->isBelowReorderPoint($warehouse->id))->toBeTrue();

    // Add stock above reorder point
    $variant->stockMovements()->create([
        'warehouse_id' => $warehouse->id,
        'type' => 'receive',
        'quantity' => 15,
        'unit_name_used' => 'piece',
        'unit_ratio_used' => 1,
    ]);
    // available = 15, reorder_point = 10 -> 15 <= 10 = false
    expect($variant->isBelowReorderPoint($warehouse->id))->toBeFalse();

    // Remove stock to below reorder point
    $variant->stockMovements()->create([
        'warehouse_id' => $warehouse->id,
        'type' => 'adjustment',
        'quantity' => -15,
        'unit_name_used' => 'piece',
        'unit_ratio_used' => 1,
    ]);
    // available = 0, reorder_point = 10 -> 0 <= 10 = true
    expect($variant->isBelowReorderPoint($warehouse->id))->toBeTrue();
});

it('resolves currentPrice to only the is_current row', function () {
    $variant = ProductVariant::factory()->create();

    ProductVariantPrice::factory()->notCurrent()->forVariant($variant)->create(['sale_price' => 100]);
    $current = ProductVariantPrice::factory()->forVariant($variant)->create(['sale_price' => 150]);

    expect($variant->currentPrice()->first()->id)->toBe($current->id);
});

it('enforces unique sku', function () {
    $variant = ProductVariant::factory()->create(['sku' => 'DUPE-SKU']);

    expect(fn () => ProductVariant::factory()->create(['sku' => 'DUPE-SKU']))
        ->toThrow(Illuminate\Database\QueryException::class);
});
it('reserved_quantity_excludes_dispatched_and_partially_received', function () {
    $variant = ProductVariant::factory()->create(['reorder_point' => 100]);
    $origin = Warehouse::factory()->create();
    $dest = Warehouse::factory()->create();

    // Stock origin with 1000 base units
    $variant->stockMovements()->create([
        'warehouse_id' => $origin->id,
        'type' => 'receive',
        'quantity' => 1000,
        'unit_name_used' => 'piece',
        'unit_ratio_used' => 1,
    ]);

    // Requisition 1: Confirmed (should be counted in reserved)
    $req1 = TransferRequisition::factory()->create([
        'from_warehouse_id' => $origin->id,
        'to_warehouse_id' => $dest->id,
        'status' => TransferRequisitionStatus::Confirmed,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $req1->id,
        'product_variant_id' => $variant->id,
        'approved_base_qty' => 200,
    ]);

    // Requisition 2: Dispatched (should NOT be counted - already deducted via TransitOut)
    $req2 = TransferRequisition::factory()->create([
        'from_warehouse_id' => $origin->id,
        'to_warehouse_id' => $dest->id,
        'status' => TransferRequisitionStatus::Dispatched,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $req2->id,
        'product_variant_id' => $variant->id,
        'approved_base_qty' => 150,
    ]);

    // Requisition 3: PartiallyReceived (should NOT be counted - already deducted via TransitOut)
    $req3 = TransferRequisition::factory()->create([
        'from_warehouse_id' => $origin->id,
        'to_warehouse_id' => $dest->id,
        'status' => TransferRequisitionStatus::PartiallyReceived,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $req3->id,
        'product_variant_id' => $variant->id,
        'approved_base_qty' => 100,
    ]);

    // Requisition 4: Completed (should NOT be counted - terminal state)
    $req4 = TransferRequisition::factory()->create([
        'from_warehouse_id' => $origin->id,
        'to_warehouse_id' => $dest->id,
        'status' => TransferRequisitionStatus::Completed,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $req4->id,
        'product_variant_id' => $variant->id,
        'approved_base_qty' => 50,
    ]);

    // Requisition 5: Cancelled (should NOT be counted - terminal state)
    $req5 = TransferRequisition::factory()->create([
        'from_warehouse_id' => $origin->id,
        'to_warehouse_id' => $dest->id,
        'status' => TransferRequisitionStatus::Cancelled,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $req5->id,
        'product_variant_id' => $variant->id,
        'approved_base_qty' => 50,
    ]);

    // Requisition 6: ClosedWithLoss (should NOT be counted - terminal state)
    $req6 = TransferRequisition::factory()->create([
        'from_warehouse_id' => $origin->id,
        'to_warehouse_id' => $dest->id,
        'status' => TransferRequisitionStatus::ClosedWithLoss,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $req6->id,
        'product_variant_id' => $variant->id,
        'approved_base_qty' => 50,
    ]);

    // Requisition 7: Requested (should NOT be counted - not confirmed yet)
    $req7 = TransferRequisition::factory()->create([
        'from_warehouse_id' => $origin->id,
        'to_warehouse_id' => $dest->id,
        'status' => TransferRequisitionStatus::Requested,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $req7->id,
        'product_variant_id' => $variant->id,
        'approved_base_qty' => 50,
    ]);

    // Reserved = 200 (Confirmed ONLY per blueprint §4 FIX v11)
    expect($variant->reservedQuantity($origin->id))->toBe(200);
});
