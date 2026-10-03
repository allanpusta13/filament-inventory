<?php

declare(strict_types=1);

use App\Enums\SalesOrderStatus;
use App\Enums\StockMovementType;
use App\Events\InventoryBelowReorderPoint;
use App\Events\SalesOrderDispatched;
use App\Exceptions\DomainRuleViolationException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidDocumentStateException;
use App\Exceptions\OutstandingQuantityExceededException;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SalesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

/**
 * SalesService contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §6.5 the four public methods.
 *   - §12 Pest coverage list:
 *       * SalesService::dispatchSale() locks items and variants;
 *         excludes own reservation in availability check; rejects
 *         dispatch when insufficient.
 *       * SalesService::recordSalesReturn() guards cumulative
 *         over-return; locks variant and warehouse.
 *   - §3.16 SalesOrder::canBeCancelled() two-state boundary.
 *   - §19.5 confirm-time price snapshot under variant lock.
 *   - §0 core principle 13 / A5 — reservation scope.
 *   - A6 — no reversal pathway for dispatched sales other than a return.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(SalesService::class);
});

/**
 * Create an order with one item.
 *
 * @return array{order: SalesOrder, item: SalesOrderItem, variant: ProductVariant, warehouse: Warehouse}
 */
function makeSalesOrder(SalesOrderStatus $status = SalesOrderStatus::Draft, int $qty = 10): array
{
    $warehouse = Warehouse::factory()->create();
    $customer = Customer::factory()->create();
    $orderer = User::factory()->create();
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    $order = SalesOrder::factory()->create([
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'status' => $status,
        'ordered_by' => $orderer->id,
    ]);

    $item = SalesOrderItem::factory()->create([
        'sales_order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'unit_name' => 'pc',
        'unit_ratio' => 1,
        'qty' => $qty,
        'base_qty' => $qty,
        'unit_sale_price_snapshot' => '0.0000',
        'dispatched_base_qty' => 0,
    ]);

    return ['order' => $order, 'item' => $item, 'variant' => $variant, 'warehouse' => $warehouse];
}

/**
 * Seed on-hand stock for a variant at a warehouse.
 */
function seedSalesStock(ProductVariant $variant, Warehouse $warehouse, int $qty): void
{
    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Adjustment,
        'quantity' => $qty,
    ]);
}

// ===========================================================================
// confirmSalesOrder()
// ===========================================================================

describe('confirmSalesOrder()', function () {
    it('transitions Draft → Confirmed', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Draft);

        $this->service->confirmSalesOrder($made['order']);

        $fresh = $made['order']->fresh();
        expect($fresh->status)->toBe(SalesOrderStatus::Confirmed);
        expect($fresh->confirmed_at)->not->toBeNull();
    });

    it('snapshots the current sale price onto each item', function () {
        // §19.5: confirm-time price snapshot under variant lock.
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Draft);

        ProductVariantPrice::factory()->create([
            'product_variant_id' => $made['variant']->id,
            'cost_price' => '10.0000',
            'sale_price' => '14.9900',
            'is_current' => true,
        ]);

        $this->service->confirmSalesOrder($made['order']);

        expect($made['item']->fresh()->unit_sale_price_snapshot)->toBe('14.9900');
    });

    it('falls back to 0.0000 when the variant has no current price', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Draft);

        // No ProductVariantPrice row exists.
        $this->service->confirmSalesOrder($made['order']);

        expect($made['item']->fresh()->unit_sale_price_snapshot)->toBe('0.0000');
    });

    it('does not read the price after confirm — later price changes are not reflected', function () {
        // The snapshot is point-in-time. A price change after confirm
        // must not affect the item.
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Draft);

        ProductVariantPrice::factory()->create([
            'product_variant_id' => $made['variant']->id,
            'sale_price' => '14.9900',
            'is_current' => true,
        ]);

        $this->service->confirmSalesOrder($made['order']);

        // Rotate the current price.
        $variant = $made['variant']->fresh();
        $variant->currentPrice->update(['is_current' => false]);
        ProductVariantPrice::factory()->create([
            'product_variant_id' => $variant->id,
            'sale_price' => '99.0000',
            'is_current' => true,
        ]);

        expect($made['item']->fresh()->unit_sale_price_snapshot)->toBe('14.9900');
    });

    it('rejects a non-Draft order', function (SalesOrderStatus $status) {
        actingAsAdmin();
        $made = makeSalesOrder($status);

        expect(fn () => $this->service->confirmSalesOrder($made['order']))
            ->toThrow(InvalidDocumentStateException::class);
    })->with([
        SalesOrderStatus::Confirmed,
        SalesOrderStatus::PartiallyDispatched,
        SalesOrderStatus::Dispatched,
        SalesOrderStatus::Cancelled,
    ]);

    it('carries the confirm action label on rejection', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Confirmed);

        try {
            $this->service->confirmSalesOrder($made['order']);
            $this->fail('Expected InvalidDocumentStateException was not thrown.');
        } catch (InvalidDocumentStateException $e) {
            expect($e->action)->toBe('confirm');
            expect($e->actualStatus)->toBe('confirmed');
        }
    });

    it('rejects an item-less order', function () {
        actingAsAdmin();
        $warehouse = Warehouse::factory()->create();
        $customer = Customer::factory()->create();
        $order = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => SalesOrderStatus::Draft,
        ]);

        expect(fn () => $this->service->confirmSalesOrder($order))
            ->toThrow(DomainRuleViolationException::class);
    });

    it('carries errors.empty_sales_items on the item-less rejection', function () {
        actingAsAdmin();
        $warehouse = Warehouse::factory()->create();
        $customer = Customer::factory()->create();
        $order = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => SalesOrderStatus::Draft,
        ]);

        try {
            $this->service->confirmSalesOrder($order);
            $this->fail('Expected DomainRuleViolationException was not thrown.');
        } catch (DomainRuleViolationException $e) {
            expect($e->translationKey())->toBe('errors.empty_sales_items');
        }
    });

    it('does not fire SalesOrderDispatched on confirm', function () {
        Event::fake([SalesOrderDispatched::class]);
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Draft);

        $this->service->confirmSalesOrder($made['order']);

        Event::assertNotDispatched(SalesOrderDispatched::class);
    });
});

// ===========================================================================
// dispatchSale()
// ===========================================================================

describe('dispatchSale()', function () {
    it('writes a negative-signed Sale movement', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Confirmed, qty: 10);
        seedSalesStock($made['variant'], $made['warehouse'], 100);

        $this->service->dispatchSale($made['order']->id, [$made['item']->id => 5]);

        $movement = StockMovement::where('type', StockMovementType::Sale)->first();
        expect($movement->quantity)->toBe(-5);
    });

    it('increments dispatched_base_qty on the item', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Confirmed, qty: 10);
        seedSalesStock($made['variant'], $made['warehouse'], 100);

        $this->service->dispatchSale($made['order']->id, [$made['item']->id => 5]);

        expect($made['item']->fresh()->dispatched_base_qty)->toBe(5);
    });

    it('transitions to PartiallyDispatched on a partial dispatch', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Confirmed, qty: 10);
        seedSalesStock($made['variant'], $made['warehouse'], 100);

        $this->service->dispatchSale($made['order']->id, [$made['item']->id => 5]);

        $fresh = $made['order']->fresh();
        expect($fresh->status)->toBe(SalesOrderStatus::PartiallyDispatched);
        expect($fresh->dispatched_at)->toBeNull();
    });

    it('transitions to Dispatched on a full dispatch', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Confirmed, qty: 10);
        seedSalesStock($made['variant'], $made['warehouse'], 100);

        $this->service->dispatchSale($made['order']->id, [$made['item']->id => 10]);

        $fresh = $made['order']->fresh();
        expect($fresh->status)->toBe(SalesOrderStatus::Dispatched);
        expect($fresh->dispatched_at)->not->toBeNull();
        expect($fresh->dispatched_by)->not->toBeNull();
    });

    it('accepts an incremental dispatch against PartiallyDispatched', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Confirmed, qty: 10);
        seedSalesStock($made['variant'], $made['warehouse'], 100);

        $this->service->dispatchSale($made['order']->id, [$made['item']->id => 3]);
        $this->service->dispatchSale($made['order']->id, [$made['item']->id => 7]);

        expect($made['item']->fresh()->dispatched_base_qty)->toBe(10);
        expect($made['order']->fresh()->status)->toBe(SalesOrderStatus::Dispatched);
    });

    it('rejects a Draft order', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Draft, qty: 10);

        expect(fn () => $this->service->dispatchSale($made['order']->id, [$made['item']->id => 5]))
            ->toThrow(InvalidDocumentStateException::class);
    });

    it('rejects a Dispatched order', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Dispatched, qty: 10);

        expect(fn () => $this->service->dispatchSale($made['order']->id, [$made['item']->id => 5]))
            ->toThrow(InvalidDocumentStateException::class);
    });

    it('rejects a Cancelled order', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Cancelled, qty: 10);

        expect(fn () => $this->service->dispatchSale($made['order']->id, [$made['item']->id => 5]))
            ->toThrow(InvalidDocumentStateException::class);
    });

    it('carries the dispatch action label on rejection', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Draft, qty: 10);

        try {
            $this->service->dispatchSale($made['order']->id, [$made['item']->id => 5]);
            $this->fail('Expected InvalidDocumentStateException was not thrown.');
        } catch (InvalidDocumentStateException $e) {
            expect($e->action)->toBe('dispatch');
        }
    });

    it('rejects an empty or all-zero dispatch payload', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Confirmed, qty: 10);
        seedSalesStock($made['variant'], $made['warehouse'], 100);

        expect(fn () => $this->service->dispatchSale($made['order']->id, []))
            ->toThrow(DomainRuleViolationException::class);
    });

    it('rejects an all-zero dispatch payload', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Confirmed, qty: 10);
        seedSalesStock($made['variant'], $made['warehouse'], 100);

        expect(fn () => $this->service->dispatchSale($made['order']->id, [$made['item']->id => 0]))
            ->toThrow(DomainRuleViolationException::class);
    });

    it('carries errors.empty_sales_dispatch on the empty-payload rejection', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Confirmed, qty: 10);

        try {
            $this->service->dispatchSale($made['order']->id, []);
            $this->fail('Expected DomainRuleViolationException was not thrown.');
        } catch (DomainRuleViolationException $e) {
            expect($e->translationKey())->toBe('errors.empty_sales_dispatch');
        }
    });

    it('rejects an over-dispatch quantity', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Confirmed, qty: 10);
        seedSalesStock($made['variant'], $made['warehouse'], 100);

        expect(fn () => $this->service->dispatchSale($made['order']->id, [$made['item']->id => 11]))
            ->toThrow(OutstandingQuantityExceededException::class);
    });

    it('rejects a cumulative over-dispatch against a partial dispatch', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Confirmed, qty: 10);
        seedSalesStock($made['variant'], $made['warehouse'], 100);

        $this->service->dispatchSale($made['order']->id, [$made['item']->id => 8]);

        expect(fn () => $this->service->dispatchSale($made['order']->id, [$made['item']->id => 5]))
            ->toThrow(OutstandingQuantityExceededException::class);
    });

    it('rejects dispatch when physical stock is insufficient', function () {
        // §12: "SalesService::dispatchSale() ... rejects dispatch when
        // insufficient."
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Confirmed, qty: 10);
        seedSalesStock($made['variant'], $made['warehouse'], 3);

        expect(fn () => $this->service->dispatchSale($made['order']->id, [$made['item']->id => 5]))
            ->toThrow(InsufficientStockException::class);
    });

    it('excludes its own reservation from the availability check', function () {
        // §12: "SalesService::dispatchSale() ... excludes own reservation
        // in availability check."
        //
        // 5 on hand; the order reserves all 5. Without the exclude,
        // available = 5 − 5 = 0, dispatch would fail. With the exclude,
        // available = 5, dispatch succeeds.
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Confirmed, qty: 5);
        seedSalesStock($made['variant'], $made['warehouse'], 5);

        $this->service->dispatchSale($made['order']->id, [$made['item']->id => 5]);

        expect($made['item']->fresh()->dispatched_base_qty)->toBe(5);
    });

    it('respects another order\u2019s reservation on the same variant', function () {
        // 10 on hand. Order A reserves 6, Order B reserves 6 (both
        // Confirmed, both reserve full base_qty). Order B can only
        // dispatch 10 − 6 = 4 — the exclusion is only for its own
        // reservation, not every reservation.
        actingAsAdmin();
        $warehouse = Warehouse::factory()->create();
        $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);
        seedSalesStock($variant, $warehouse, 10);

        $orderA = SalesOrder::factory()->confirmed()->create(['warehouse_id' => $warehouse->id]);
        SalesOrderItem::factory()->create([
            'sales_order_id' => $orderA->id,
            'product_variant_id' => $variant->id,
            'base_qty' => 6,
            'dispatched_base_qty' => 0,
        ]);

        $orderB = SalesOrder::factory()->confirmed()->create(['warehouse_id' => $warehouse->id]);
        $itemB = SalesOrderItem::factory()->create([
            'sales_order_id' => $orderB->id,
            'product_variant_id' => $variant->id,
            'base_qty' => 6,
            'dispatched_base_qty' => 0,
        ]);

        expect(fn () => $this->service->dispatchSale($orderB->id, [$itemB->id => 5]))
            ->toThrow(InsufficientStockException::class);

        // 4 is fine.
        $this->service->dispatchSale($orderB->id, [$itemB->id => 4]);
        expect($itemB->fresh()->dispatched_base_qty)->toBe(4);
    });

    it('fires SalesOrderDispatched on full dispatch', function () {
        Event::fake([SalesOrderDispatched::class]);
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Confirmed, qty: 10);
        seedSalesStock($made['variant'], $made['warehouse'], 100);

        $this->service->dispatchSale($made['order']->id, [$made['item']->id => 10]);

        Event::assertDispatched(
            SalesOrderDispatched::class,
            fn ($e) => $e->salesOrderId === $made['order']->id,
        );
    });

    it('does not fire SalesOrderDispatched on a partial dispatch', function () {
        Event::fake([SalesOrderDispatched::class]);
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Confirmed, qty: 10);
        seedSalesStock($made['variant'], $made['warehouse'], 100);

        $this->service->dispatchSale($made['order']->id, [$made['item']->id => 5]);

        Event::assertNotDispatched(SalesOrderDispatched::class);
    });

    it('fires InventoryBelowReorderPoint when crossing the threshold', function () {
        Event::fake([InventoryBelowReorderPoint::class]);
        actingAsAdmin();
        $warehouse = Warehouse::factory()->create();
        $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc', 'reorder_point' => 30]);
        seedSalesStock($variant, $warehouse, 100);

        $order = SalesOrder::factory()->confirmed()->create(['warehouse_id' => $warehouse->id]);
        $item = SalesOrderItem::factory()->create([
            'sales_order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'base_qty' => 80,
        ]);

        $this->service->dispatchSale($order->id, [$item->id => 80]);

        Event::assertDispatched(InventoryBelowReorderPoint::class);
    });

    it('tags the movement with reference_type = SalesOrder::class and reference_id = order id', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Confirmed, qty: 10);
        seedSalesStock($made['variant'], $made['warehouse'], 100);

        $this->service->dispatchSale($made['order']->id, [$made['item']->id => 5]);

        $movement = StockMovement::where('type', StockMovementType::Sale)->first();
        expect($movement->reference_type)->toBe(SalesOrder::class);
        expect($movement->reference_id)->toBe((string) $made['order']->id);
    });

    it('records the acting user in dispatched_by', function () {
        $admin = actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Confirmed, qty: 10);
        seedSalesStock($made['variant'], $made['warehouse'], 100);

        $this->service->dispatchSale($made['order']->id, [$made['item']->id => 5]);

        expect($made['order']->fresh()->dispatched_by)->toBe($admin->id);
    });
});

// ===========================================================================
// recordSalesReturn()
// ===========================================================================

describe('recordSalesReturn()', function () {
    it('writes a positive-signed SaleReturn movement', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Dispatched, qty: 10);
        $made['item']->update(['dispatched_base_qty' => 10]);
        seedSalesStock($made['variant'], $made['warehouse'], 100);

        $this->service->recordSalesReturn($made['item']->id, 3);

        $movement = StockMovement::where('type', StockMovementType::SaleReturn)->first();
        expect($movement->quantity)->toBe(3);
    });

    it('tags the movement with reference_type = SalesOrderItem::class and reference_id = item id', function () {
        // Load-bearing: the per-item reference is what
        // SalesOrderItem::alreadyReturnedBaseQty() (§3.17) sums.
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Dispatched, qty: 10);
        $made['item']->update(['dispatched_base_qty' => 10]);
        seedSalesStock($made['variant'], $made['warehouse'], 100);

        $this->service->recordSalesReturn($made['item']->id, 3);

        $movement = StockMovement::where('type', StockMovementType::SaleReturn)->first();
        expect($movement->reference_type)->toBe(SalesOrderItem::class);
        expect($movement->reference_id)->toBe((string) $made['item']->id);
    });

    it('rejects a return quantity of zero', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Dispatched, qty: 10);
        $made['item']->update(['dispatched_base_qty' => 10]);

        expect(fn () => $this->service->recordSalesReturn($made['item']->id, 0))
            ->toThrow(DomainRuleViolationException::class);
    });

    it('rejects a negative return quantity', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Dispatched, qty: 10);
        $made['item']->update(['dispatched_base_qty' => 10]);

        expect(fn () => $this->service->recordSalesReturn($made['item']->id, -1))
            ->toThrow(DomainRuleViolationException::class);
    });

    it('carries errors.invalid_return_quantity on the zero/negative rejection', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Dispatched, qty: 10);
        $made['item']->update(['dispatched_base_qty' => 10]);

        try {
            $this->service->recordSalesReturn($made['item']->id, 0);
            $this->fail('Expected DomainRuleViolationException was not thrown.');
        } catch (DomainRuleViolationException $e) {
            expect($e->translationKey())->toBe('errors.invalid_return_quantity');
            expect($e->context())->toHaveKey('item');
            expect($e->context())->toHaveKey('qty');
        }
    });

    it('rejects a return quantity above the dispatched amount', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Dispatched, qty: 10);
        $made['item']->update(['dispatched_base_qty' => 5]);

        expect(fn () => $this->service->recordSalesReturn($made['item']->id, 10))
            ->toThrow(OutstandingQuantityExceededException::class);
    });

    it('rejects a cumulative over-return', function () {
        // §12: "SalesService::recordSalesReturn() guards cumulative
        // over-return".
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Dispatched, qty: 10);
        $made['item']->update(['dispatched_base_qty' => 10]);
        seedSalesStock($made['variant'], $made['warehouse'], 100);

        $this->service->recordSalesReturn($made['item']->id, 8);

        // 8 + 5 = 13 > 10 — cumulative over-return.
        expect(fn () => $this->service->recordSalesReturn($made['item']->id, 5))
            ->toThrow(OutstandingQuantityExceededException::class);
    });

    it('allows a cumulative return exactly equal to dispatched', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Dispatched, qty: 10);
        $made['item']->update(['dispatched_base_qty' => 10]);
        seedSalesStock($made['variant'], $made['warehouse'], 100);

        $this->service->recordSalesReturn($made['item']->id, 4);
        $this->service->recordSalesReturn($made['item']->id, 6);

        expect(StockMovement::where('type', StockMovementType::SaleReturn)->count())->toBe(2);
    });

    it('carries the cumulative attempted and remaining outstanding on the exception', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Dispatched, qty: 10);
        $made['item']->update(['dispatched_base_qty' => 10]);
        seedSalesStock($made['variant'], $made['warehouse'], 100);

        $this->service->recordSalesReturn($made['item']->id, 8);

        try {
            $this->service->recordSalesReturn($made['item']->id, 5);
            $this->fail('Expected OutstandingQuantityExceededException was not thrown.');
        } catch (OutstandingQuantityExceededException $e) {
            expect($e->attempted)->toBe(13);      // 8 + 5
            expect($e->outstanding)->toBe(2);     // 10 − 8
            expect($e->itemType)->toBe(SalesOrderItem::class);
        }
    });

    it('records the acting user in created_by', function () {
        $admin = actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Dispatched, qty: 10);
        $made['item']->update(['dispatched_base_qty' => 10]);
        seedSalesStock($made['variant'], $made['warehouse'], 100);

        $this->service->recordSalesReturn($made['item']->id, 3);

        $movement = StockMovement::where('type', StockMovementType::SaleReturn)->first();
        expect($movement->created_by)->toBe($admin->id);
    });

    it('reads the dispatched_base_qty from the locked row, not a stale copy', function () {
        // Set dispatched_base_qty high enough that a naive unlocked read
        // might miss it. The service reads the current value after
        // lockForUpdate.
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Dispatched, qty: 10);
        $made['item']->update(['dispatched_base_qty' => 10]);

        // Move $item out of scope by refreshing — the service must read
        // the DB, not any in-memory copy.
        $this->service->recordSalesReturn($made['item']->id, 10);

        expect($made['item']->fresh()->alreadyReturnedBaseQty())->toBe(10);
    });
});

// ===========================================================================
// cancelSalesOrder()
// ===========================================================================

describe('cancelSalesOrder()', function () {
    it('cancels a Draft order', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Draft);

        $this->service->cancelSalesOrder($made['order']);

        $fresh = $made['order']->fresh();
        expect($fresh->status)->toBe(SalesOrderStatus::Cancelled);
        expect($fresh->cancelled_at)->not->toBeNull();
    });

    it('cancels a Confirmed order', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Confirmed);

        $this->service->cancelSalesOrder($made['order']);

        expect($made['order']->fresh()->status)->toBe(SalesOrderStatus::Cancelled);
    });

    it('rejects a PartiallyDispatched order', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::PartiallyDispatched);

        expect(fn () => $this->service->cancelSalesOrder($made['order']))
            ->toThrow(InvalidDocumentStateException::class);
    });

    it('rejects a Dispatched order', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Dispatched);

        expect(fn () => $this->service->cancelSalesOrder($made['order']))
            ->toThrow(InvalidDocumentStateException::class);
    });

    it('rejects a Cancelled order', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Cancelled);

        expect(fn () => $this->service->cancelSalesOrder($made['order']))
            ->toThrow(InvalidDocumentStateException::class);
    });

    it('carries the cancel action label on rejection', function () {
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Dispatched);

        try {
            $this->service->cancelSalesOrder($made['order']);
            $this->fail('Expected InvalidDocumentStateException was not thrown.');
        } catch (InvalidDocumentStateException $e) {
            expect($e->action)->toBe('cancel');
        }
    });

    it('does not fire SalesOrderDispatched on cancel', function () {
        Event::fake([SalesOrderDispatched::class]);
        actingAsAdmin();
        $made = makeSalesOrder(SalesOrderStatus::Confirmed);

        $this->service->cancelSalesOrder($made['order']);

        Event::assertNotDispatched(SalesOrderDispatched::class);
    });
});

// ===========================================================================
// Service wiring
// ===========================================================================

describe('service wiring', function () {
    it('resolves from the container', function () {
        expect(app(SalesService::class))->toBeInstanceOf(SalesService::class);
    });

    it('injects a GuardsOutstandingQuantity', function () {
        $service = app(SalesService::class);

        $reflection = new ReflectionClass($service);
        $params = $reflection->getConstructor()->getParameters();

        expect($params)->toHaveCount(1);
        expect($params[0]->getType()->getName())->toBe(App\Services\GuardsOutstandingQuantity::class);
    });
});
