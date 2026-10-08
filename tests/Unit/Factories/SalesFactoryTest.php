<?php

declare(strict_types=1);

use App\Enums\SalesOrderStatus;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Sales factory tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §5.15 SalesOrderFactory (+ confirmed() state).
 *   - §5.16 SalesOrderItemFactory.
 *   - §6.5 confirm-time snapshot contract — the factory's
 *     `unit_sale_price_snapshot` default is a test convenience, NOT
 *     the production default of 0.0000.
 */
uses(RefreshDatabase::class);

// ===========================================================================
// SalesOrderFactory (§5.15)
// ===========================================================================

describe('SalesOrderFactory', function () {
    it('produces the §5.15 default Draft state', function () {
        $order = SalesOrder::factory()->create();

        expect($order->status)->toBe(SalesOrderStatus::Draft);
        expect($order->ordered_at)->toBeNull();
        expect($order->confirmed_at)->toBeNull();
        expect($order->dispatched_at)->toBeNull();
        expect($order->cancelled_at)->toBeNull();
    });

    it('auto-creates the customer, warehouse, and orderer', function () {
        $order = SalesOrder::factory()->create();

        expect($order->customer)->toBeInstanceOf(Customer::class);
        expect($order->warehouse)->toBeInstanceOf(Warehouse::class);
        expect($order->orderedBy)->toBeInstanceOf(User::class);
    });

    it('produces the confirmed() state', function () {
        $order = SalesOrder::factory()->confirmed()->create();

        expect($order->status)->toBe(SalesOrderStatus::Confirmed);
        expect($order->confirmed_at)->not->toBeNull();
    });
});

// ===========================================================================
// SalesOrderItemFactory (§5.16)
// ===========================================================================

describe('SalesOrderItemFactory', function () {
    it('produces a pc unit with matching base qty', function () {
        $item = SalesOrderItem::factory()->create();

        expect($item->unit_name)->toBe('pc');
        expect($item->unit_ratio)->toBe(1);
        expect($item->qty)->toBeGreaterThanOrEqual(1);
        expect($item->qty)->toBeLessThanOrEqual(20);
        expect($item->base_qty)->toBe($item->qty);
    });

    it('produces a unit_sale_price_snapshot in [1, 500]', function () {
        $item = SalesOrderItem::factory()->create();
        $price = (float) $item->unit_sale_price_snapshot;

        expect($price)->toBeGreaterThanOrEqual(1.0);
        expect($price)->toBeLessThanOrEqual(500.0);
    });

    it('defaults dispatched_base_qty to 0', function () {
        // Factory omits the column (§5.16); the schema default (§2.19) is the
        // contract, so read it back from the persisted row.
        expect(SalesOrderItem::factory()->create()->fresh()->dispatched_base_qty)->toBe(0);
    });

    it('auto-creates the parent order and the variant', function () {
        $item = SalesOrderItem::factory()->create();

        expect($item->salesOrder)->toBeInstanceOf(SalesOrder::class);
        expect($item->productVariant)->toBeInstanceOf(ProductVariant::class);
    });

    it('does not expose substitute_product_variant_id (A10)', function () {
        $item = SalesOrderItem::factory()->create();
        expect($item->getAttributes())->not->toHaveKey('substitute_product_variant_id');
    });

    it('mirrors the PurchaseOrderItem shape except for the price column name', function () {
        // §3.15 / §3.17: the two order-item models are structurally
        // symmetric, differing only in ordered_* vs plain names and in
        // the cost vs sale price column.
        $sales = SalesOrderItem::factory()->create();
        $purchase = App\Models\PurchaseOrderItem::factory()->create();

        expect(count($sales->getFillable()))->toBe(count($purchase->getFillable()));
    });
});
