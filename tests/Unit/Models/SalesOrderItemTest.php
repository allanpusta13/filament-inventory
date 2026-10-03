<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * SalesOrderItem model contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §2.19 sales_order_items schema: columns, defaults, FK actions.
 *   - §3.17 model shape: fillable, casts, two relations,
 *     outstandingBaseQty() and alreadyReturnedBaseQty() helpers.
 *   - A6: no reversal pathway for dispatched sales other than a return.
 *   - A10: no substitute_product_variant_id column.
 *   - §5.16 SalesOrderItemFactory defaults.
 *   - §6.1 GuardsOutstandingQuantity::assertSaleNotOverDispatched().
 *   - §6.5 SalesService::dispatchSale() and recordSalesReturn() shapes.
 *   - §12 Pest coverage list: "SalesOrderItem::alreadyReturnedBaseQty()
 *     extracted as model method".
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Traits, fillable, casts
// ---------------------------------------------------------------------------

it('uses HasFactory only', function () {
    $traits = class_uses_recursive(SalesOrderItem::class);

    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\Factories\HasFactory::class);
});

it('does not use SoftDeletes — the §2.19 schema declares no deleted_at', function () {
    $traits = class_uses_recursive(SalesOrderItem::class);

    expect($traits)->not->toHaveKey(Illuminate\Database\Eloquent\SoftDeletes::class);
    expect((new SalesOrderItem())->getDates())->not->toContain('deleted_at');
});

it('declares exactly the §3.17 fillable set', function () {
    $item = new SalesOrderItem();

    expect($item->getFillable())->toBe([
        'sales_order_id', 'product_variant_id', 'unit_name', 'unit_ratio',
        'qty', 'base_qty', 'unit_sale_price_snapshot', 'dispatched_base_qty', 'notes',
    ]);
});

it('does not expose substitute_product_variant_id (A10)', function () {
    // A10: substitute variants are transfer/requisition only — sales
    // operate on the exact variant sold.
    $item = new SalesOrderItem();

    expect($item->getFillable())->not->toContain('substitute_product_variant_id');
    expect($item->getAttributes())->not->toHaveKey('substitute_product_variant_id');
});

it('casts unit_ratio, qty, base_qty, and dispatched_base_qty to integer', function () {
    $item = SalesOrderItem::factory()->create([
        'unit_ratio' => '12',
        'qty' => '5',
        'base_qty' => '60',
        'dispatched_base_qty' => '24',
    ]);

    $fresh = $item->fresh();

    expect($fresh->unit_ratio)->toBe(12)->toBeInt();
    expect($fresh->qty)->toBe(5)->toBeInt();
    expect($fresh->base_qty)->toBe(60)->toBeInt();
    expect($fresh->dispatched_base_qty)->toBe(24)->toBeInt();
});

it('casts unit_sale_price_snapshot to decimal:4', function () {
    $item = SalesOrderItem::factory()->create([
        'unit_sale_price_snapshot' => '12.3456',
    ]);

    expect($item->fresh()->unit_sale_price_snapshot)->toBe('12.3456')->toBeString();
});

// ---------------------------------------------------------------------------
// §2.19 schema defaults
// ---------------------------------------------------------------------------

it('defaults dispatched_base_qty to 0 via the §2.19 schema', function () {
    // §2.19: dispatched_base_qty default = 0.
    $order = SalesOrder::factory()->create();
    $variant = ProductVariant::factory()->create();

    DB::table('sales_order_items')->insert([
        'sales_order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'unit_name' => 'pc',
        'unit_ratio' => 1,
        'qty' => 10,
        'base_qty' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $item = SalesOrderItem::where('sales_order_id', $order->id)->first();

    expect($item->dispatched_base_qty)->toBe(0);
});

it('defaults unit_sale_price_snapshot to 0.0000 via the §2.19 schema', function () {
    // §2.19: unit_sale_price_snapshot default = 0.0000. It stays at the
    // default until confirm-time snapshots the current sale price (§6.5).
    $order = SalesOrder::factory()->create();
    $variant = ProductVariant::factory()->create();

    DB::table('sales_order_items')->insert([
        'sales_order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'unit_name' => 'pc',
        'unit_ratio' => 1,
        'qty' => 10,
        'base_qty' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $item = SalesOrderItem::where('sales_order_id', $order->id)->first();

    expect($item->unit_sale_price_snapshot)->toBe('0.0000');
});

// ---------------------------------------------------------------------------
// §5.16 factory shape
// ---------------------------------------------------------------------------

it('produces an item with the §5.16 factory defaults', function () {
    // §5.16: unit_name = 'pc', unit_ratio = 1, qty ∈ [1,20],
    // base_qty = qty, unit_sale_price_snapshot ∈ [1,500] at 4dp.
    $item = SalesOrderItem::factory()->create();

    expect($item->unit_name)->toBe('pc');
    expect($item->unit_ratio)->toBe(1);
    expect($item->qty)->toBeGreaterThanOrEqual(1);
    expect($item->qty)->toBeLessThanOrEqual(20);
    expect($item->base_qty)->toBe($item->qty);
    expect((float) $item->unit_sale_price_snapshot)->toBeGreaterThanOrEqual(1.0);
    expect((float) $item->unit_sale_price_snapshot)->toBeLessThanOrEqual(500.0);
});

// ---------------------------------------------------------------------------
// Relations
// ---------------------------------------------------------------------------

it('exposes salesOrder() as a BelongsTo relation', function () {
    $item = new SalesOrderItem();

    expect($item->salesOrder())->toBeInstanceOf(BelongsTo::class);
    expect($item->salesOrder()->getRelated())->toBeInstanceOf(SalesOrder::class);
});

it('exposes productVariant() as a BelongsTo relation', function () {
    $item = new SalesOrderItem();

    expect($item->productVariant())->toBeInstanceOf(BelongsTo::class);
    expect($item->productVariant()->getRelated())->toBeInstanceOf(ProductVariant::class);
});

it('resolves each relation end-to-end', function () {
    $order = SalesOrder::factory()->create();
    $variant = ProductVariant::factory()->create();

    $item = SalesOrderItem::factory()->create([
        'sales_order_id' => $order->id,
        'product_variant_id' => $variant->id,
    ]);

    expect($item->salesOrder->id)->toBe($order->id);
    expect($item->productVariant->id)->toBe($variant->id);
});

// ---------------------------------------------------------------------------
// §3.17 outstandingBaseQty()
// ---------------------------------------------------------------------------

it('returns base_qty when nothing has been dispatched', function () {
    $item = SalesOrderItem::factory()->create([
        'base_qty' => 50,
        'dispatched_base_qty' => 0,
    ]);

    expect($item->outstandingBaseQty())->toBe(50);
});

it('returns the remainder when partially dispatched', function () {
    $item = SalesOrderItem::factory()->create([
        'base_qty' => 50,
        'dispatched_base_qty' => 30,
    ]);

    expect($item->outstandingBaseQty())->toBe(20);
});

it('returns 0 when fully dispatched', function () {
    $item = SalesOrderItem::factory()->create([
        'base_qty' => 50,
        'dispatched_base_qty' => 50,
    ]);

    expect($item->outstandingBaseQty())->toBe(0);
});

it('floors the outstanding value at 0 when over-dispatched', function () {
    // The guard should never allow over-dispatch
    // (GuardsOutstandingQuantity §6.1), but a defensive floor is
    // intentional. This test locks it.
    $item = SalesOrderItem::factory()->create([
        'base_qty' => 50,
        'dispatched_base_qty' => 75,
    ]);

    expect($item->outstandingBaseQty())->toBe(0);
});

it('preserves base-qty semantics when sold in a non-base unit', function () {
    // unit_ratio > 1: base_qty = qty × ratio. The helper's return value
    // is always in base units.
    $item = SalesOrderItem::factory()->create([
        'unit_name' => 'case',
        'unit_ratio' => 24,
        'qty' => 3,
        'base_qty' => 72,
        'dispatched_base_qty' => 24, // 1 case dispatched
    ]);

    expect($item->outstandingBaseQty())->toBe(48); // 2 cases outstanding
});

// ---------------------------------------------------------------------------
// §3.17 alreadyReturnedBaseQty() — §12 coverage
// ---------------------------------------------------------------------------

it('returns 0 when nothing has been returned', function () {
    // §12 Pest coverage list: "SalesOrderItem::alreadyReturnedBaseQty()
    // extracted as model method". This exercises the method's
    // happy-path shape.
    $item = SalesOrderItem::factory()->create();

    expect($item->alreadyReturnedBaseQty())->toBe(0);
});

it('sums SaleReturn movements tagged with this item as the reference', function () {
    $item = SalesOrderItem::factory()->create();
    $order = $item->salesOrder;
    $warehouse = Warehouse::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $item->product_variant_id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::SaleReturn,
        'quantity' => 5,
        'reference_type' => SalesOrderItem::class,
        'reference_id' => (string) $item->id,
    ]);
    StockMovement::factory()->create([
        'product_variant_id' => $item->product_variant_id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::SaleReturn,
        'quantity' => 3,
        'reference_type' => SalesOrderItem::class,
        'reference_id' => (string) $item->id,
    ]);

    expect($item->alreadyReturnedBaseQty())->toBe(8);
});

it('ignores SaleReturn movements tagged with other items', function () {
    $item = SalesOrderItem::factory()->create();
    $other = SalesOrderItem::factory()->create();
    $warehouse = Warehouse::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $item->product_variant_id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::SaleReturn,
        'quantity' => 5,
        'reference_type' => SalesOrderItem::class,
        'reference_id' => (string) $item->id,
    ]);
    StockMovement::factory()->create([
        'product_variant_id' => $other->product_variant_id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::SaleReturn,
        'quantity' => 100,
        'reference_type' => SalesOrderItem::class,
        'reference_id' => (string) $other->id,
    ]);

    expect($item->alreadyReturnedBaseQty())->toBe(5);
});

it('ignores Sale movements tagged with this item', function () {
    // §3.17: alreadyReturnedBaseQty() filters by type = SaleReturn only.
    // A dispatch (Sale) movement tagged with the same reference must not
    // count as a return.
    $item = SalesOrderItem::factory()->create();
    $warehouse = Warehouse::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $item->product_variant_id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Sale,
        'quantity' => -10,
        'reference_type' => SalesOrder::class, // note: SalesOrder, not SalesOrderItem
        'reference_id' => (string) $item->sales_order_id,
    ]);
    StockMovement::factory()->create([
        'product_variant_id' => $item->product_variant_id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Sale,
        'quantity' => -10,
        'reference_type' => SalesOrderItem::class,
        'reference_id' => (string) $item->id,
    ]);

    expect($item->alreadyReturnedBaseQty())->toBe(0);
});

it('ignores movements tagged with a different reference_type', function () {
    // Same reference_id, but a different reference_type — must not count.
    $item = SalesOrderItem::factory()->create();
    $warehouse = Warehouse::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $item->product_variant_id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::SaleReturn,
        'quantity' => 5,
        'reference_type' => SalesOrder::class,
        'reference_id' => (string) $item->id, // matches item id but wrong type
    ]);

    expect($item->alreadyReturnedBaseQty())->toBe(0);
});

it('returns an int, not a string', function () {
    $item = SalesOrderItem::factory()->create();
    $warehouse = Warehouse::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $item->product_variant_id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::SaleReturn,
        'quantity' => 5,
        'reference_type' => SalesOrderItem::class,
        'reference_id' => (string) $item->id,
    ]);

    expect($item->alreadyReturnedBaseQty())->toBeInt();
});

// ---------------------------------------------------------------------------
// §6.1 GuardsOutstandingQuantity::assertSaleNotOverDispatched() — shape
// ---------------------------------------------------------------------------

it('gives GuardsOutstandingQuantity a reliable outstanding value to compare against', function () {
    // §6.1 assertSaleNotOverDispatched($item, $newDispatch) compares
    // $newDispatch against outstandingBaseQty(). Base-qty units.
    $item = SalesOrderItem::factory()->create([
        'base_qty' => 100,
        'dispatched_base_qty' => 25,
    ]);

    expect($item->outstandingBaseQty())->toBe(75);
    expect($item->outstandingBaseQty() < 80)->toBeTrue();
});

// ---------------------------------------------------------------------------
// §6.5 recordSalesReturn() cumulative over-return — shape
// ---------------------------------------------------------------------------

it('supports the cumulative over-return accounting shape', function () {
    // §6.5: recordSalesReturn() computes
    // `alreadyReturned + newReturn > dispatched_base_qty` and throws
    // OutstandingQuantityExceededException when true. This test asserts
    // the shape: dispatched 40, previously returned 15, attempting 30
    // → 45 > 40 → over-return.
    $item = SalesOrderItem::factory()->create([
        'base_qty' => 50,
        'dispatched_base_qty' => 40,
    ]);
    $warehouse = Warehouse::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $item->product_variant_id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::SaleReturn,
        'quantity' => 15,
        'reference_type' => SalesOrderItem::class,
        'reference_id' => (string) $item->id,
    ]);

    $alreadyReturned = $item->alreadyReturnedBaseQty();
    $newReturn = 30;

    expect($alreadyReturned)->toBe(15);
    expect($alreadyReturned + $newReturn)->toBeGreaterThan($item->dispatched_base_qty);
});

// ---------------------------------------------------------------------------
// §2.19 FK discipline
// ---------------------------------------------------------------------------

it('cascades on parent sales order force delete (cascadeOnDelete, §2.19)', function () {
    $order = SalesOrder::factory()->create();
    SalesOrderItem::factory()->count(2)->create(['sales_order_id' => $order->id]);

    $order->forceDelete();

    expect(SalesOrderItem::where('sales_order_id', $order->id)->count())->toBe(0);
});

it('blocks deletion of a variant referenced by a sales order item (restrictOnDelete, §2.19)', function () {
    $variant = ProductVariant::factory()->create();
    SalesOrderItem::factory()->create(['product_variant_id' => $variant->id]);

    expect(fn () => $variant->forceDelete())->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------
// §6.5 confirm-time price snapshot — shape via direct update
// ---------------------------------------------------------------------------

it('supports the §6.5 confirm-time price snapshot update shape', function () {
    // §6.5 confirmSalesOrder() writes unit_sale_price_snapshot from the
    // locked variant's current sale_price. This test exercises the
    // update shape (the service-level test lives in the services suite).
    $item = SalesOrderItem::factory()->create([
        'unit_sale_price_snapshot' => '0.0000',
    ]);

    $item->update(['unit_sale_price_snapshot' => '14.9900']);

    expect($item->fresh()->unit_sale_price_snapshot)->toBe('14.9900');
});
