<?php

declare(strict_types=1);

use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * PurchaseOrderItem model contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §2.17 purchase_order_items schema: columns, defaults, FK
 *     actions.
 *   - §3.15 model shape: fillable, casts, two relations,
 *     outstandingBaseQty() helper.
 *   - A10: no substitute_product_variant_id column.
 *   - §5.14 PurchaseOrderItemFactory defaults.
 *   - §6.1 GuardsOutstandingQuantity::assertPurchaseNotOverReceived().
 *   - §6.4 PurchaseService::receivePurchase() shape.
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Traits, fillable, casts
// ---------------------------------------------------------------------------

it('uses HasFactory only', function () {
    $traits = class_uses_recursive(PurchaseOrderItem::class);

    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\Factories\HasFactory::class);
});

it('does not use SoftDeletes — the §2.17 schema declares no deleted_at', function () {
    $traits = class_uses_recursive(PurchaseOrderItem::class);

    expect($traits)->not->toHaveKey(Illuminate\Database\Eloquent\SoftDeletes::class);
    expect((new PurchaseOrderItem())->getDates())->not->toContain('deleted_at');
});

it('declares exactly the §3.15 fillable set', function () {
    $item = new PurchaseOrderItem();

    expect($item->getFillable())->toBe([
        'purchase_order_id', 'product_variant_id', 'ordered_unit_name',
        'ordered_unit_ratio', 'ordered_qty', 'ordered_base_qty',
        'unit_cost_price', 'received_base_qty', 'notes',
    ]);
});

it('does not expose substitute_product_variant_id (A10)', function () {
    // A10: substitute variants are transfer/requisition only — purchases
    // operate on the exact variant ordered.
    $item = new PurchaseOrderItem();

    expect($item->getFillable())->not->toContain('substitute_product_variant_id');
    expect($item->getAttributes())->not->toHaveKey('substitute_product_variant_id');
});

it('casts ordered_unit_ratio, ordered_qty, ordered_base_qty, and received_base_qty to integer', function () {
    $item = PurchaseOrderItem::factory()->create([
        'ordered_unit_ratio' => '12',
        'ordered_qty' => '5',
        'ordered_base_qty' => '60',
        'received_base_qty' => '24',
    ]);

    $fresh = $item->fresh();

    expect($fresh->ordered_unit_ratio)->toBe(12)->toBeInt();
    expect($fresh->ordered_qty)->toBe(5)->toBeInt();
    expect($fresh->ordered_base_qty)->toBe(60)->toBeInt();
    expect($fresh->received_base_qty)->toBe(24)->toBeInt();
});

it('casts unit_cost_price to decimal:4', function () {
    $item = PurchaseOrderItem::factory()->create([
        'unit_cost_price' => '12.3456',
    ]);

    expect($item->fresh()->unit_cost_price)->toBe('12.3456')->toBeString();
});

// ---------------------------------------------------------------------------
// §2.17 schema defaults
// ---------------------------------------------------------------------------

it('defaults received_base_qty to 0 via the §2.17 schema', function () {
    // §2.17: received_base_qty default = 0.
    $order = PurchaseOrder::factory()->create();
    $variant = ProductVariant::factory()->create();

    DB::table('purchase_order_items')->insert([
        'purchase_order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'ordered_unit_name' => 'pc',
        'ordered_unit_ratio' => 1,
        'ordered_qty' => 10,
        'ordered_base_qty' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $item = PurchaseOrderItem::where('purchase_order_id', $order->id)->first();

    expect($item->received_base_qty)->toBe(0);
});

it('defaults unit_cost_price to 0.0000 via the §2.17 schema', function () {
    // §2.17: unit_cost_price default = 0.0000.
    $order = PurchaseOrder::factory()->create();
    $variant = ProductVariant::factory()->create();

    DB::table('purchase_order_items')->insert([
        'purchase_order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'ordered_unit_name' => 'pc',
        'ordered_unit_ratio' => 1,
        'ordered_qty' => 10,
        'ordered_base_qty' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $item = PurchaseOrderItem::where('purchase_order_id', $order->id)->first();

    expect($item->unit_cost_price)->toBe('0.0000');
});

// ---------------------------------------------------------------------------
// §5.14 factory shape
// ---------------------------------------------------------------------------

it('produces an item with the §5.14 factory defaults', function () {
    // §5.14: ordered_unit_name = 'pc', ordered_unit_ratio = 1,
    // ordered_qty ∈ [1,20], ordered_base_qty = ordered_qty,
    // unit_cost_price ∈ [1,500] at 4dp.
    $item = PurchaseOrderItem::factory()->create();

    expect($item->ordered_unit_name)->toBe('pc');
    expect($item->ordered_unit_ratio)->toBe(1);
    expect($item->ordered_qty)->toBeGreaterThanOrEqual(1);
    expect($item->ordered_qty)->toBeLessThanOrEqual(20);
    expect($item->ordered_base_qty)->toBe($item->ordered_qty);
    expect((float) $item->unit_cost_price)->toBeGreaterThanOrEqual(1.0);
    expect((float) $item->unit_cost_price)->toBeLessThanOrEqual(500.0);
});

// ---------------------------------------------------------------------------
// Relations
// ---------------------------------------------------------------------------

it('exposes purchaseOrder() as a BelongsTo relation', function () {
    $item = new PurchaseOrderItem();

    expect($item->purchaseOrder())->toBeInstanceOf(BelongsTo::class);
    expect($item->purchaseOrder()->getRelated())->toBeInstanceOf(PurchaseOrder::class);
});

it('exposes productVariant() as a BelongsTo relation', function () {
    $item = new PurchaseOrderItem();

    expect($item->productVariant())->toBeInstanceOf(BelongsTo::class);
    expect($item->productVariant()->getRelated())->toBeInstanceOf(ProductVariant::class);
});

it('resolves each relation end-to-end', function () {
    $order = PurchaseOrder::factory()->create();
    $variant = ProductVariant::factory()->create();

    $item = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'product_variant_id' => $variant->id,
    ]);

    expect($item->purchaseOrder->id)->toBe($order->id);
    expect($item->productVariant->id)->toBe($variant->id);
});

// ---------------------------------------------------------------------------
// §3.15 outstandingBaseQty()
// ---------------------------------------------------------------------------

it('returns ordered_base_qty when nothing has been received', function () {
    $item = PurchaseOrderItem::factory()->create([
        'ordered_base_qty' => 50,
        'received_base_qty' => 0,
    ]);

    expect($item->outstandingBaseQty())->toBe(50);
});

it('returns the remainder when partially received', function () {
    $item = PurchaseOrderItem::factory()->create([
        'ordered_base_qty' => 50,
        'received_base_qty' => 30,
    ]);

    expect($item->outstandingBaseQty())->toBe(20);
});

it('returns 0 when fully received', function () {
    $item = PurchaseOrderItem::factory()->create([
        'ordered_base_qty' => 50,
        'received_base_qty' => 50,
    ]);

    expect($item->outstandingBaseQty())->toBe(0);
});

it('floors the outstanding value at 0 when over-received', function () {
    // The guard should never allow over-receiving
    // (GuardsOutstandingQuantity §6.1), but a defensive floor is
    // intentional. This test locks it.
    $item = PurchaseOrderItem::factory()->create([
        'ordered_base_qty' => 50,
        'received_base_qty' => 75,
    ]);

    expect($item->outstandingBaseQty())->toBe(0);
});

it('preserves base-qty semantics when ordered in a non-base unit', function () {
    // ordered_unit_ratio > 1: ordered_base_qty = ordered_qty × ratio.
    // The helper's argument and return value are always in base units.
    $item = PurchaseOrderItem::factory()->create([
        'ordered_unit_name' => 'case',
        'ordered_unit_ratio' => 24,
        'ordered_qty' => 3,
        'ordered_base_qty' => 72,
        'received_base_qty' => 24, // 1 case received
    ]);

    expect($item->outstandingBaseQty())->toBe(48); // 2 cases outstanding
});

// ---------------------------------------------------------------------------
// §6.1 GuardsOutstandingQuantity contract — shape assertion
// ---------------------------------------------------------------------------

it('gives GuardsOutstandingQuantity a reliable outstanding value to compare against', function () {
    // §6.1 assertPurchaseNotOverReceived($item, $newReceived) compares
    // $newReceived against outstandingBaseQty(). The service-level test
    // for that method lives in the services suite; here we assert the
    // model helper's shape matches the units the guard receives (base qty).
    $item = PurchaseOrderItem::factory()->create([
        'ordered_base_qty' => 100,
        'received_base_qty' => 25,
    ]);

    // Attempting to receive 80 against 75 outstanding must exceed.
    expect($item->outstandingBaseQty())->toBe(75);
    expect($item->outstandingBaseQty() < 80)->toBeTrue();
});

// ---------------------------------------------------------------------------
// §2.17 FK discipline
// ---------------------------------------------------------------------------

it('cascades on parent purchase order force delete (cascadeOnDelete, §2.17)', function () {
    $order = PurchaseOrder::factory()->create();
    PurchaseOrderItem::factory()->count(2)->create(['purchase_order_id' => $order->id]);

    $order->forceDelete();

    expect(PurchaseOrderItem::where('purchase_order_id', $order->id)->count())->toBe(0);
});

it('blocks deletion of a variant referenced by a purchase order item (restrictOnDelete, §2.17)', function () {
    $variant = ProductVariant::factory()->create();
    PurchaseOrderItem::factory()->create(['product_variant_id' => $variant->id]);

    expect(fn () => $variant->forceDelete())->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------
// §6.4 receivePurchase() shape — via direct update
// ---------------------------------------------------------------------------

it('supports the §6.4 receivePurchase() update shape', function () {
    // §6.4 receivePurchase() increments received_base_qty and updates
    // the parent order status. This test exercises the item-level shape
    // (the service-level test lives in the services suite).
    $item = PurchaseOrderItem::factory()->create([
        'ordered_base_qty' => 50,
        'received_base_qty' => 20,
    ]);

    $item->update([
        'received_base_qty' => $item->received_base_qty + 15,
    ]);

    expect($item->fresh()->received_base_qty)->toBe(35);
    expect($item->fresh()->outstandingBaseQty())->toBe(15);
});
