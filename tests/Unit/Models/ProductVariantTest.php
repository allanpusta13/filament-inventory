<?php

declare(strict_types=1);

use App\Enums\SalesOrderStatus;
use App\Enums\StockMovementType;
use App\Enums\TransferRequisitionStatus;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\ProductVariantUnitConversion;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockMovement;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * ProductVariant model contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction. It exercises the model's own contract (§3.2) and
 * does not replace or duplicate any blueprint-named test.
 *
 * Blueprint anchors exercised:
 *   - §2.2 product_variants schema; unique sku / barcode; the
 *     base_unit_name + reorder_point defaults; soft deletes.
 *   - §3.2 fillable, casts, barcode mutator, relations, and the
 *     derived-stock method contracts.
 *   - §0 core principle 1 (derived stock) and §0 core principle 13
 *     (reservation scope: Confirmed requisitions only).
 *   - §12 Pest coverage list: onHand / reserved / reservedForSales /
 *     available correctness; `$exclude*` honors; batch query counts.
 *   - §3.19 ProductVariantObserver base-unit self-conversion row.
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Traits, fillable, casts
// ---------------------------------------------------------------------------

it('uses HasFactory and SoftDeletes traits', function () {
    $traits = class_uses_recursive(ProductVariant::class);

    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\Factories\HasFactory::class);
    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\SoftDeletes::class);
});

it('declares exactly the §3.2 fillable set', function () {
    $variant = new ProductVariant();

    expect($variant->getFillable())->toBe([
        'product_id', 'sku', 'barcode', 'name', 'base_unit_name',
        'reorder_point', 'attributes', 'images', 'is_active',
    ]);
});

it('casts attributes and images to array, and is_active to boolean', function () {
    $variant = ProductVariant::factory()->create([
        'attributes' => ['color' => 'red'],
        'images' => ['a.jpg', 'b.jpg'],
        'is_active' => 1,
    ]);

    expect($variant->attributes)->toBe(['color' => 'red']);
    expect($variant->images)->toBe(['a.jpg', 'b.jpg']);
    expect($variant->is_active)->toBeTrue();
});

// ---------------------------------------------------------------------------
// Barcode mutator — blank → null (§2.2 owner decision)
// ---------------------------------------------------------------------------

it('normalizes an empty-string barcode to null', function () {
    $variant = ProductVariant::factory()->create(['barcode' => '']);

    expect($variant->barcode)->toBeNull();
});

it('normalizes a whitespace-only barcode to null', function () {
    $variant = ProductVariant::factory()->create(['barcode' => '   ']);

    expect($variant->barcode)->toBeNull();
});

it('preserves a legitimate barcode value', function () {
    $variant = ProductVariant::factory()->create(['barcode' => '1234567890128']);

    expect($variant->barcode)->toBe('1234567890128');
});

it('allows multiple variants with a null barcode (unique NULL semantics)', function () {
    // §2.2: "Unique NULL values are treated as distinct under the
    // project's PostgreSQL/MySQL semantics."
    ProductVariant::factory()->create(['barcode' => null]);
    ProductVariant::factory()->create(['barcode' => null]);

    expect(ProductVariant::whereNull('barcode')->count())->toBe(2);
});

it('rejects a duplicate non-null barcode via the §2.2 unique constraint', function () {
    ProductVariant::factory()->create(['barcode' => '1234567890128']);

    expect(fn () => ProductVariant::factory()->create(['barcode' => '1234567890128']))
        ->toThrow(QueryException::class);
});

it('rejects a duplicate sku via the §2.2 unique constraint', function () {
    $variant = ProductVariant::factory()->create(['sku' => 'SKU-0001-AA']);

    expect(fn () => ProductVariant::factory()->create(['sku' => 'SKU-0001-AA']))
        ->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------
// Relations
// ---------------------------------------------------------------------------

it('exposes the §3.2 relations with the correct types', function () {
    $variant = new ProductVariant();

    expect($variant->product())->toBeInstanceOf(BelongsTo::class);
    expect($variant->unitConversions())->toBeInstanceOf(HasMany::class);
    expect($variant->prices())->toBeInstanceOf(HasMany::class);
    expect($variant->currentPrice())->toBeInstanceOf(HasOne::class);
    expect($variant->stockMovements())->toBeInstanceOf(HasMany::class);
});

it('resolves currentPrice() to the single is_current = true row', function () {
    $variant = ProductVariant::factory()->create();

    // Historical row.
    ProductVariantPrice::factory()->create([
        'product_variant_id' => $variant->id,
        'is_current' => false,
        'sale_price' => '1.0000',
    ]);

    // Current row.
    ProductVariantPrice::factory()->create([
        'product_variant_id' => $variant->id,
        'is_current' => true,
        'sale_price' => '9.9900',
    ]);

    expect($variant->fresh()->currentPrice->sale_price)->toBe('9.9900');
});

// ---------------------------------------------------------------------------
// §3.19 ProductVariantObserver — base-unit self-conversion row
// ---------------------------------------------------------------------------

it('creates a base-unit self-conversion row on create (F19, §3.19)', function () {
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    $row = ProductVariantUnitConversion::where('product_variant_id', $variant->id)
        ->where('unit_name', 'pc')
        ->first();

    expect($row)->not->toBeNull();
    expect($row->base_unit_ratio)->toBe(1);
    expect($row->is_default_purchase)->toBeFalse();
    expect($row->is_default_transfer)->toBeFalse();
});

// ---------------------------------------------------------------------------
// onHandQuantity() — sum of signed stock_movements
// ---------------------------------------------------------------------------

it('returns 0 for a variant with no movements', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    expect($variant->onHandQuantity($warehouse->id))->toBe(0);
});

it('sums signed movements across warehouses when no filter is given', function () {
    $variant = ProductVariant::factory()->create();
    [$w1, $w2] = Warehouse::factory()->count(2)->create();

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $w1->id,
        'type' => StockMovementType::Adjustment,
        'quantity' => 50,
    ]);
    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $w2->id,
        'type' => StockMovementType::Purchase,
        'quantity' => 20,
    ]);
    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $w1->id,
        'type' => StockMovementType::Sale,
        'quantity' => -30,
    ]);

    expect($variant->onHandQuantity())->toBe(40);
});

it('scopes onHandQuantity() to a single warehouse', function () {
    $variant = ProductVariant::factory()->create();
    [$w1, $w2] = Warehouse::factory()->count(2)->create();

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $w1->id,
        'type' => StockMovementType::Adjustment,
        'quantity' => 50,
    ]);
    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $w2->id,
        'type' => StockMovementType::Adjustment,
        'quantity' => 20,
    ]);

    expect($variant->onHandQuantity($w1->id))->toBe(50);
    expect($variant->onHandQuantity($w2->id))->toBe(20);
});

// ---------------------------------------------------------------------------
// reservedQuantity() — Confirmed requisitions only (§0 principle 13)
// ---------------------------------------------------------------------------

it('reserves 0 when no requisition exists', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    expect($variant->reservedQuantity($warehouse->id))->toBe(0);
});

it('counts approved base qty of Confirmed requisitions only', function () {
    $variant = ProductVariant::factory()->create();
    $from = Warehouse::factory()->create();
    $to = Warehouse::factory()->create();

    // Draft (not counted)
    $draft = TransferRequisition::factory()->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
        'status' => TransferRequisitionStatus::Draft,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $draft->id,
        'product_variant_id' => $variant->id,
        'approved_base_qty' => 10,
    ]);

    // Confirmed (counted)
    $confirmed = TransferRequisition::factory()->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
        'status' => TransferRequisitionStatus::Confirmed,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $confirmed->id,
        'product_variant_id' => $variant->id,
        'approved_base_qty' => 25,
    ]);

    // Requested (not counted)
    $requested = TransferRequisition::factory()->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
        'status' => TransferRequisitionStatus::Requested,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $requested->id,
        'product_variant_id' => $variant->id,
        'approved_base_qty' => 100,
    ]);

    expect($variant->reservedQuantity($from->id))->toBe(25);
});

it('ignores items with null approved_base_qty', function () {
    $variant = ProductVariant::factory()->create();
    $from = Warehouse::factory()->create();
    $to = Warehouse::factory()->create();

    $confirmed = TransferRequisition::factory()->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
        'status' => TransferRequisitionStatus::Confirmed,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $confirmed->id,
        'product_variant_id' => $variant->id,
        'approved_base_qty' => null,
    ]);

    expect($variant->reservedQuantity($from->id))->toBe(0);
});

it('books the reservation against the substitute variant when negotiated', function () {
    $original = ProductVariant::factory()->create();
    $substitute = ProductVariant::factory()->create();
    $from = Warehouse::factory()->create();
    $to = Warehouse::factory()->create();

    $confirmed = TransferRequisition::factory()->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
        'status' => TransferRequisitionStatus::Confirmed,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $confirmed->id,
        'product_variant_id' => $original->id,
        'substitute_product_variant_id' => $substitute->id,
        'approved_base_qty' => 25,
    ]);

    // The substitute carries the reservation.
    expect($substitute->reservedQuantity($from->id))->toBe(25);

    // The original does NOT — its substitute-product-variant-id is set,
    // so it is excluded from the effective-variant predicate.
    expect($original->reservedQuantity($from->id))->toBe(0);
});

it('honors $excludeTransferRequisitionId', function () {
    $variant = ProductVariant::factory()->create();
    $from = Warehouse::factory()->create();
    $to = Warehouse::factory()->create();

    $a = TransferRequisition::factory()->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
        'status' => TransferRequisitionStatus::Confirmed,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $a->id,
        'product_variant_id' => $variant->id,
        'approved_base_qty' => 25,
    ]);

    $b = TransferRequisition::factory()->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
        'status' => TransferRequisitionStatus::Confirmed,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $b->id,
        'product_variant_id' => $variant->id,
        'approved_base_qty' => 10,
    ]);

    expect($variant->reservedQuantity($from->id))->toBe(35);
    expect($variant->reservedQuantity($from->id, $a->id))->toBe(10);
    expect($variant->reservedQuantity($from->id, $b->id))->toBe(25);
});

// ---------------------------------------------------------------------------
// reservedForSalesQuantity() — Confirmed full + PartiallyDispatched outstanding (A5)
// ---------------------------------------------------------------------------

it('reserves 0 when no sales order exists', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    expect($variant->reservedForSalesQuantity($warehouse->id))->toBe(0);
});

it('reserves full base_qty on Confirmed sales orders', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $order = SalesOrder::factory()->confirmed()->create(['warehouse_id' => $warehouse->id]);
    SalesOrderItem::factory()->create([
        'sales_order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'base_qty' => 30,
        'dispatched_base_qty' => 0,
    ]);

    expect($variant->reservedForSalesQuantity($warehouse->id))->toBe(30);
});

it('reserves only the outstanding remainder on PartiallyDispatched orders', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $order = SalesOrder::factory()->create([
        'warehouse_id' => $warehouse->id,
        'status' => SalesOrderStatus::PartiallyDispatched,
    ]);
    SalesOrderItem::factory()->create([
        'sales_order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'base_qty' => 30,
        'dispatched_base_qty' => 20,
    ]);

    expect($variant->reservedForSalesQuantity($warehouse->id))->toBe(10);
});

it('floors PartiallyDispatched remainder at 0', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $order = SalesOrder::factory()->create([
        'warehouse_id' => $warehouse->id,
        'status' => SalesOrderStatus::PartiallyDispatched,
    ]);
    SalesOrderItem::factory()->create([
        'sales_order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'base_qty' => 30,
        'dispatched_base_qty' => 30,
    ]);

    expect($variant->reservedForSalesQuantity($warehouse->id))->toBe(0);
});

it('sums Confirmed full and PartiallyDispatched outstanding', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $confirmed = SalesOrder::factory()->confirmed()->create(['warehouse_id' => $warehouse->id]);
    SalesOrderItem::factory()->create([
        'sales_order_id' => $confirmed->id,
        'product_variant_id' => $variant->id,
        'base_qty' => 20,
    ]);

    $partial = SalesOrder::factory()->create([
        'warehouse_id' => $warehouse->id,
        'status' => SalesOrderStatus::PartiallyDispatched,
    ]);
    SalesOrderItem::factory()->create([
        'sales_order_id' => $partial->id,
        'product_variant_id' => $variant->id,
        'base_qty' => 15,
        'dispatched_base_qty' => 5,
    ]);

    expect($variant->reservedForSalesQuantity($warehouse->id))->toBe(30);
});

it('honors $excludeSalesOrderId in reservedForSalesQuantity()', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $a = SalesOrder::factory()->confirmed()->create(['warehouse_id' => $warehouse->id]);
    SalesOrderItem::factory()->create([
        'sales_order_id' => $a->id,
        'product_variant_id' => $variant->id,
        'base_qty' => 20,
    ]);

    $b = SalesOrder::factory()->confirmed()->create(['warehouse_id' => $warehouse->id]);
    SalesOrderItem::factory()->create([
        'sales_order_id' => $b->id,
        'product_variant_id' => $variant->id,
        'base_qty' => 10,
    ]);

    expect($variant->reservedForSalesQuantity($warehouse->id))->toBe(30);
    expect($variant->reservedForSalesQuantity($warehouse->id, $a->id))->toBe(10);
});

// ---------------------------------------------------------------------------
// availableQuantity() — on hand − reserved − reserved for sales
// ---------------------------------------------------------------------------

it('returns 0 for a variant with no stock and no reservations', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    expect($variant->availableQuantity($warehouse->id))->toBe(0);
});

it('subtracts both transfer and sales reservations from on-hand', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Adjustment,
        'quantity' => 100,
    ]);

    $req = TransferRequisition::factory()->create([
        'from_warehouse_id' => $warehouse->id,
        'to_warehouse_id' => $other->id,
        'status' => TransferRequisitionStatus::Confirmed,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $req->id,
        'product_variant_id' => $variant->id,
        'approved_base_qty' => 20,
    ]);

    $so = SalesOrder::factory()->confirmed()->create(['warehouse_id' => $warehouse->id]);
    SalesOrderItem::factory()->create([
        'sales_order_id' => $so->id,
        'product_variant_id' => $variant->id,
        'base_qty' => 30,
    ]);

    // 100 − 20 − 30 = 50
    expect($variant->availableQuantity($warehouse->id))->toBe(50);
});

it('honors both $exclude parameters independently', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Adjustment,
        'quantity' => 100,
    ]);

    $req = TransferRequisition::factory()->create([
        'from_warehouse_id' => $warehouse->id,
        'to_warehouse_id' => $other->id,
        'status' => TransferRequisitionStatus::Confirmed,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $req->id,
        'product_variant_id' => $variant->id,
        'approved_base_qty' => 20,
    ]);

    $so = SalesOrder::factory()->confirmed()->create(['warehouse_id' => $warehouse->id]);
    SalesOrderItem::factory()->create([
        'sales_order_id' => $so->id,
        'product_variant_id' => $variant->id,
        'base_qty' => 30,
    ]);

    // Exclude requisition → 100 − 0 − 30 = 70
    expect($variant->availableQuantity($warehouse->id, null, $req->id))->toBe(70);

    // Exclude sales order → 100 − 20 − 0 = 80
    expect($variant->availableQuantity($warehouse->id, $so->id, null))->toBe(80);

    // Exclude both → 100 − 0 − 0 = 100
    expect($variant->availableQuantity($warehouse->id, $so->id, $req->id))->toBe(100);
});

// ---------------------------------------------------------------------------
// batchAvailableQuantity() — 3 queries, matches single-variant totals
// ---------------------------------------------------------------------------

it('batchAvailableQuantity() returns an empty array for empty input', function () {
    expect(ProductVariant::batchAvailableQuantity([], 1))->toBe([]);
});

it('returns 0 for variants with no stock or reservations', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $result = ProductVariant::batchAvailableQuantity([$variant->id], $warehouse->id);

    expect($result[$variant->id])->toBe(0);
});

it('matches availableQuantity() per variant on a mixed dataset', function () {
    $warehouse = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();

    // V1: 100 on-hand, 20 transfer-reserved, 30 sales-reserved
    // V2: 50 on-hand, no reservations
    // V3: no stock, 10 sales-reserved (negative available)
    $v1 = ProductVariant::factory()->create();
    $v2 = ProductVariant::factory()->create();
    $v3 = ProductVariant::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $v1->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Adjustment,
        'quantity' => 100,
    ]);
    StockMovement::factory()->create([
        'product_variant_id' => $v2->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Adjustment,
        'quantity' => 50,
    ]);

    $req = TransferRequisition::factory()->create([
        'from_warehouse_id' => $warehouse->id,
        'to_warehouse_id' => $other->id,
        'status' => TransferRequisitionStatus::Confirmed,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $req->id,
        'product_variant_id' => $v1->id,
        'approved_base_qty' => 20,
    ]);

    $so = SalesOrder::factory()->confirmed()->create(['warehouse_id' => $warehouse->id]);
    SalesOrderItem::factory()->create([
        'sales_order_id' => $so->id,
        'product_variant_id' => $v1->id,
        'base_qty' => 30,
    ]);
    SalesOrderItem::factory()->create([
        'sales_order_id' => $so->id,
        'product_variant_id' => $v3->id,
        'base_qty' => 10,
    ]);

    $batch = ProductVariant::batchAvailableQuantity([$v1->id, $v2->id, $v3->id], $warehouse->id);

    expect($batch[$v1->id])->toBe(50);   // 100 − 20 − 30
    expect($batch[$v2->id])->toBe(50);   // 50 − 0 − 0
    expect($batch[$v3->id])->toBe(-10);  // 0 − 0 − 10

    // Cross-check against the single-variant accessor.
    foreach ([$v1, $v2, $v3] as $v) {
        expect($batch[$v->id])->toBe($v->availableQuantity($warehouse->id));
    }
});

it('books batched transfer reservation against the substitute variant', function () {
    $original = ProductVariant::factory()->create();
    $substitute = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();

    $req = TransferRequisition::factory()->create([
        'from_warehouse_id' => $warehouse->id,
        'to_warehouse_id' => $other->id,
        'status' => TransferRequisitionStatus::Confirmed,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $req->id,
        'product_variant_id' => $original->id,
        'substitute_product_variant_id' => $substitute->id,
        'approved_base_qty' => 25,
    ]);

    $batch = ProductVariant::batchAvailableQuantity(
        [$original->id, $substitute->id],
        $warehouse->id,
    );

    // Substitute gets −25; original gets 0.
    expect($batch[$substitute->id])->toBe(-25);
    expect($batch[$original->id])->toBe(0);
});

it('honors $excludeSalesOrderId in batchAvailableQuantity()', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Adjustment,
        'quantity' => 100,
    ]);

    $a = SalesOrder::factory()->confirmed()->create(['warehouse_id' => $warehouse->id]);
    SalesOrderItem::factory()->create([
        'sales_order_id' => $a->id,
        'product_variant_id' => $variant->id,
        'base_qty' => 30,
    ]);

    $b = SalesOrder::factory()->confirmed()->create(['warehouse_id' => $warehouse->id]);
    SalesOrderItem::factory()->create([
        'sales_order_id' => $b->id,
        'product_variant_id' => $variant->id,
        'base_qty' => 10,
    ]);

    // All reservations: 100 − 0 − 40 = 60
    expect(ProductVariant::batchAvailableQuantity([$variant->id], $warehouse->id)[$variant->id])
        ->toBe(60);

    // Exclude order a: 100 − 0 − 10 = 90
    expect(ProductVariant::batchAvailableQuantity([$variant->id], $warehouse->id, $a->id)[$variant->id])
        ->toBe(90);
});

it('honors $excludeTransferRequisitionId in batchAvailableQuantity()', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Adjustment,
        'quantity' => 100,
    ]);

    $req = TransferRequisition::factory()->create([
        'from_warehouse_id' => $warehouse->id,
        'to_warehouse_id' => $other->id,
        'status' => TransferRequisitionStatus::Confirmed,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $req->id,
        'product_variant_id' => $variant->id,
        'approved_base_qty' => 25,
    ]);

    // Reserved: 100 − 25 − 0 = 75
    expect(ProductVariant::batchAvailableQuantity([$variant->id], $warehouse->id)[$variant->id])
        ->toBe(75);

    // Exclude req: 100 − 0 − 0 = 100
    expect(ProductVariant::batchAvailableQuantity([$variant->id], $warehouse->id, null, $req->id)[$variant->id])
        ->toBe(100);
});

it('issues exactly 3 queries regardless of variant count (§3.2 / §12)', function () {
    // Seed enough for all three aggregates to have real rows to
    // consider — the count is stable regardless, but a seeded dataset
    // also catches a driver-specific query split.
    $warehouse = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();

    $variantIds = [];
    for ($i = 0; $i < 5; $i++) {
        $variantIds[] = ProductVariant::factory()->create()->id;
    }

    foreach ($variantIds as $id) {
        StockMovement::factory()->create([
            'product_variant_id' => $id,
            'warehouse_id' => $warehouse->id,
            'type' => StockMovementType::Adjustment,
            'quantity' => 100,
        ]);
    }

    DB::enableQueryLog();
    ProductVariant::batchAvailableQuantity($variantIds, $warehouse->id);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toHaveCount(3);
});

// ---------------------------------------------------------------------------
// batchUnitConversions() — 1 query
// ---------------------------------------------------------------------------

it('batchUnitConversions() returns an empty array for empty input', function () {
    expect(ProductVariant::batchUnitConversions([]))->toBe([]);
});

it('groups unit conversions by variant id', function () {
    $v1 = ProductVariant::factory()->create();
    $v2 = ProductVariant::factory()->create();

    // ProductVariantObserver creates the base-unit row on create;
    // add one extra per variant.
    ProductVariantUnitConversion::factory()->create(['product_variant_id' => $v1->id]);
    ProductVariantUnitConversion::factory()->create(['product_variant_id' => $v2->id]);

    $result = ProductVariant::batchUnitConversions([$v1->id, $v2->id]);

    expect($result)->toHaveKey($v1->id);
    expect($result)->toHaveKey($v2->id);
    expect($result[$v1->id])->toBeInstanceOf(Illuminate\Support\Collection::class);
    expect($result[$v1->id]->count())->toBe(2); // base row + extra
    expect($result[$v2->id]->count())->toBe(2);
});

it('omits variant ids that have no conversions', function () {
    // The observer always creates a base row on create — the only way a
    // variant has no conversions is if the observer was bypassed. Use a
    // freshly made variant after deleting its base row for the test.
    $variant = ProductVariant::factory()->create();
    ProductVariantUnitConversion::where('product_variant_id', $variant->id)->delete();

    $result = ProductVariant::batchUnitConversions([$variant->id]);

    expect($result)->not->toHaveKey($variant->id);
});

it('issues exactly 1 query regardless of variant count (§3.2 / §12)', function () {
    $variantIds = [];
    for ($i = 0; $i < 10; $i++) {
        $variantIds[] = ProductVariant::factory()->create()->id;
    }

    DB::enableQueryLog();
    ProductVariant::batchUnitConversions($variantIds);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// FK / soft-delete interactions
// ---------------------------------------------------------------------------

it('blocks hard deletion of a variant referenced by stock_movements (restrictOnDelete, §2.6)', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
    ]);

    expect(fn () => $variant->forceDelete())
        ->toThrow(QueryException::class);

    expect(ProductVariant::withTrashed()->find($variant->id))->not->toBeNull();
});

it('soft-deletes a variant cleanly when no ledger references restrict it', function () {
    $variant = ProductVariant::factory()->create();

    $variant->delete();

    expect(ProductVariant::find($variant->id))->toBeNull();
    expect(ProductVariant::withTrashed()->find($variant->id))->not->toBeNull();
    expect($variant->fresh()->deleted_at)->not->toBeNull();
});

it('restores a soft-deleted variant', function () {
    $variant = ProductVariant::factory()->create();
    $variant->delete();

    $variant->restore();

    expect(ProductVariant::find($variant->id))->not->toBeNull();
    expect($variant->fresh()->deleted_at)->toBeNull();
});
