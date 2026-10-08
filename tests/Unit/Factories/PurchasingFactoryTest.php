<?php

declare(strict_types=1);

use App\Enums\PurchaseOrderStatus;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Purchasing factory tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §5.13 PurchaseOrderFactory (+ ordered() state).
 *   - §5.14 PurchaseOrderItemFactory.
 *   - §2.16 / §2.17 default columns.
 */
uses(RefreshDatabase::class);

// ===========================================================================
// PurchaseOrderFactory (§5.13)
// ===========================================================================

describe('PurchaseOrderFactory', function () {
    it('produces the §5.13 default Draft state', function () {
        $order = PurchaseOrder::factory()->create();

        expect($order->status)->toBe(PurchaseOrderStatus::Draft);
        expect($order->ordered_at)->toBeNull();
        expect($order->received_at)->toBeNull();
        expect($order->cancelled_at)->toBeNull();
    });

    it('defaults update_cost_price to false (A4)', function () {
        // A4: cost update is opt-in per order. The factory omits the column
        // (§5.13), so the value is the schema default (§2.16) — read it back
        // from the persisted row.
        expect(PurchaseOrder::factory()->create()->fresh()->update_cost_price)->toBeFalse();
    });

    it('auto-creates the supplier, warehouse, and orderer', function () {
        $order = PurchaseOrder::factory()->create();

        expect($order->supplier)->toBeInstanceOf(Supplier::class);
        expect($order->warehouse)->toBeInstanceOf(Warehouse::class);
        expect($order->orderedBy)->toBeInstanceOf(User::class);
    });

    it('produces the ordered() state', function () {
        $order = PurchaseOrder::factory()->ordered()->create();

        expect($order->status)->toBe(PurchaseOrderStatus::Ordered);
        expect($order->ordered_at)->not->toBeNull();
    });
});

// ===========================================================================
// PurchaseOrderItemFactory (§5.14)
// ===========================================================================

describe('PurchaseOrderItemFactory', function () {
    it('produces a pc unit with matching base qty', function () {
        $item = PurchaseOrderItem::factory()->create();

        expect($item->ordered_unit_name)->toBe('pc');
        expect($item->ordered_unit_ratio)->toBe(1);
        expect($item->ordered_qty)->toBeGreaterThanOrEqual(1);
        expect($item->ordered_qty)->toBeLessThanOrEqual(20);
        expect($item->ordered_base_qty)->toBe($item->ordered_qty);
    });

    it('produces a unit_cost_price in [1, 500]', function () {
        $item = PurchaseOrderItem::factory()->create();
        $cost = (float) $item->unit_cost_price;

        expect($cost)->toBeGreaterThanOrEqual(1.0);
        expect($cost)->toBeLessThanOrEqual(500.0);
    });

    it('defaults received_base_qty to 0', function () {
        // Factory omits the column (§5.14); the schema default (§2.17) is the
        // contract, so read it back from the persisted row.
        expect(PurchaseOrderItem::factory()->create()->fresh()->received_base_qty)->toBe(0);
    });

    it('auto-creates the parent order and the variant', function () {
        $item = PurchaseOrderItem::factory()->create();

        expect($item->purchaseOrder)->toBeInstanceOf(PurchaseOrder::class);
        expect($item->productVariant)->toBeInstanceOf(ProductVariant::class);
    });

    it('does not expose substitute_product_variant_id (A10)', function () {
        // A10: substitution is transfer-only; the purchase item has no
        // substitute column.
        $item = PurchaseOrderItem::factory()->create();
        expect($item->getAttributes())->not->toHaveKey('substitute_product_variant_id');
    });
});
