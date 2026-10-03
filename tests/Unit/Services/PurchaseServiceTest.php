<?php

declare(strict_types=1);

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Events\PurchaseOrderCancelled;
use App\Events\PurchaseOrderReceived;
use App\Exceptions\DomainRuleViolationException;
use App\Exceptions\InvalidDocumentStateException;
use App\Exceptions\OutstandingQuantityExceededException;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

/**
 * PurchaseService contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §6.4 the three public methods and the private cost-price writer.
 *   - §12 Pest coverage list: "PurchaseService::receivePurchase()
 *     locks items and variants; guards over-receive; updates cost
 *     price when update_cost_price = true".
 *   - §3.14 PurchaseOrder::canBeCancelled() two-condition boundary.
 *   - §6.1 GuardsOutstandingQuantity::assertPurchaseNotOverReceived().
 *   - §22.1a / §22.2 event timing.
 *   - A4 — cost update is opt-in via `update_cost_price`.
 *
 * ⚠ Blueprint-as-written oddity documented here: `orderPurchase()`
 * fires `PurchaseOrderReceived` at order time (§6.4) AND
 * `receivePurchase()` fires the same event on full receipt. The tests
 * below reflect this behavior. See the class docblock.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(PurchaseService::class);
});

// ===========================================================================
// Fixture helpers
// ===========================================================================

/**
 * Create an order with one item.
 *
 * @return array{order: PurchaseOrder, item: PurchaseOrderItem, variant: ProductVariant, warehouse: Warehouse}
 */
function makeOrder(PurchaseOrderStatus $status = PurchaseOrderStatus::Draft, int $qty = 10, string $unitCost = '10.0000', bool $updateCost = false): array
{
    $warehouse = Warehouse::factory()->create();
    $supplier = Supplier::factory()->create();
    $orderer = User::factory()->create();
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    $order = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'status' => $status,
        'ordered_by' => $orderer->id,
        'update_cost_price' => $updateCost,
    ]);

    $item = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'ordered_unit_name' => 'pc',
        'ordered_unit_ratio' => 1,
        'ordered_qty' => $qty,
        'ordered_base_qty' => $qty,
        'unit_cost_price' => $unitCost,
        'received_base_qty' => 0,
    ]);

    return ['order' => $order, 'item' => $item, 'variant' => $variant, 'warehouse' => $warehouse];
}

// ===========================================================================
// orderPurchase()
// ===========================================================================

describe('orderPurchase()', function () {
    it('transitions Draft → Ordered', function () {
        actingAsAdmin();
        $made = makeOrder();

        $this->service->orderPurchase($made['order']);

        $fresh = $made['order']->fresh();
        expect($fresh->status)->toBe(PurchaseOrderStatus::Ordered);
        expect($fresh->ordered_at)->not->toBeNull();
    });

    it('preserves a pre-set ordered_by', function () {
        actingAsAdmin();
        $original = User::factory()->create();
        $made = makeOrder();
        $made['order']->update(['ordered_by' => $original->id]);

        $this->service->orderPurchase($made['order']);

        expect($made['order']->fresh()->ordered_by)->toBe($original->id);
    });

    it('rejects a non-Draft order', function (PurchaseOrderStatus $status) {
        actingAsAdmin();
        $made = makeOrder($status);

        expect(fn () => $this->service->orderPurchase($made['order']))
            ->toThrow(InvalidDocumentStateException::class);
    })->with([
        PurchaseOrderStatus::Ordered,
        PurchaseOrderStatus::PartiallyReceived,
        PurchaseOrderStatus::Received,
        PurchaseOrderStatus::Cancelled,
    ]);

    it('carries the order action label on rejection', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered);

        try {
            $this->service->orderPurchase($made['order']);
            $this->fail('Expected InvalidDocumentStateException was not thrown.');
        } catch (InvalidDocumentStateException $e) {
            expect($e->action)->toBe('order');
            expect($e->actualStatus)->toBe('ordered');
        }
    });

    it('rejects an item-less order', function () {
        actingAsAdmin();
        $warehouse = Warehouse::factory()->create();
        $supplier = Supplier::factory()->create();
        $order = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseOrderStatus::Draft,
        ]);

        expect(fn () => $this->service->orderPurchase($order))
            ->toThrow(DomainRuleViolationException::class);
    });

    it('carries errors.empty_purchase_items on the item-less rejection', function () {
        actingAsAdmin();
        $warehouse = Warehouse::factory()->create();
        $supplier = Supplier::factory()->create();
        $order = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseOrderStatus::Draft,
        ]);

        try {
            $this->service->orderPurchase($order);
            $this->fail('Expected DomainRuleViolationException was not thrown.');
        } catch (DomainRuleViolationException $e) {
            expect($e->translationKey())->toBe('errors.empty_purchase_items');
        }
    });

    it('fires PurchaseOrderReceived on ordering (blueprint-as-written)', function () {
        // ⚠ §6.4 fires PurchaseOrderReceived here despite the event name
        // suggesting it should fire on receipt. See class docblock.
        Event::fake([PurchaseOrderReceived::class]);
        actingAsAdmin();
        $made = makeOrder();

        $this->service->orderPurchase($made['order']);

        Event::assertDispatched(
            PurchaseOrderReceived::class,
            fn ($e) => $e->purchaseOrderId === $made['order']->id,
        );
    });
});

// ===========================================================================
// receivePurchase()
// ===========================================================================

describe('receivePurchase()', function () {
    it('writes a Purchase movement and increments received_base_qty', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered, qty: 10);

        $this->service->receivePurchase($made['order']->id, [$made['item']->id => 5]);

        expect(StockMovement::where('type', StockMovementType::Purchase)->count())->toBe(1);
        expect($made['item']->fresh()->received_base_qty)->toBe(5);
    });

    it('transitions to PartiallyReceived on a partial receipt', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered, qty: 10);

        $this->service->receivePurchase($made['order']->id, [$made['item']->id => 5]);

        $fresh = $made['order']->fresh();
        expect($fresh->status)->toBe(PurchaseOrderStatus::PartiallyReceived);
        expect($fresh->received_at)->toBeNull();
        expect($fresh->received_by)->not->toBeNull();
    });

    it('transitions to Received on a full receipt', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered, qty: 10);

        $this->service->receivePurchase($made['order']->id, [$made['item']->id => 10]);

        $fresh = $made['order']->fresh();
        expect($fresh->status)->toBe(PurchaseOrderStatus::Received);
        expect($fresh->received_at)->not->toBeNull();
    });

    it('accepts an incremental receipt against PartiallyReceived', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered, qty: 10);

        $this->service->receivePurchase($made['order']->id, [$made['item']->id => 3]);
        $this->service->receivePurchase($made['order']->id, [$made['item']->id => 7]);

        expect($made['item']->fresh()->received_base_qty)->toBe(10);
        expect($made['order']->fresh()->status)->toBe(PurchaseOrderStatus::Received);
    });

    it('rejects a Draft order', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Draft);

        expect(fn () => $this->service->receivePurchase($made['order']->id, [$made['item']->id => 5]))
            ->toThrow(InvalidDocumentStateException::class);
    });

    it('rejects a Received order', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Received);

        expect(fn () => $this->service->receivePurchase($made['order']->id, [$made['item']->id => 1]))
            ->toThrow(InvalidDocumentStateException::class);
    });

    it('rejects a Cancelled order', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Cancelled);

        expect(fn () => $this->service->receivePurchase($made['order']->id, [$made['item']->id => 1]))
            ->toThrow(InvalidDocumentStateException::class);
    });

    it('carries the receive action label on rejection', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Draft);

        try {
            $this->service->receivePurchase($made['order']->id, [$made['item']->id => 5]);
            $this->fail('Expected InvalidDocumentStateException was not thrown.');
        } catch (InvalidDocumentStateException $e) {
            expect($e->action)->toBe('receive');
        }
    });

    it('rejects an empty or all-zero receipt payload', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered, qty: 10);

        expect(fn () => $this->service->receivePurchase($made['order']->id, []))
            ->toThrow(DomainRuleViolationException::class);
    });

    it('rejects an all-zero receipt payload', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered, qty: 10);

        expect(fn () => $this->service->receivePurchase($made['order']->id, [$made['item']->id => 0]))
            ->toThrow(DomainRuleViolationException::class);
    });

    it('carries errors.empty_purchase_receipt on the empty-payload rejection', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered, qty: 10);

        try {
            $this->service->receivePurchase($made['order']->id, []);
            $this->fail('Expected DomainRuleViolationException was not thrown.');
        } catch (DomainRuleViolationException $e) {
            expect($e->translationKey())->toBe('errors.empty_purchase_receipt');
        }
    });

    it('rejects an over-receive quantity', function () {
        // §12: "PurchaseService::receivePurchase() locks items and
        // variants; guards over-receive; ...".
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered, qty: 10);

        expect(fn () => $this->service->receivePurchase($made['order']->id, [$made['item']->id => 11]))
            ->toThrow(OutstandingQuantityExceededException::class);
    });

    it('rejects a cumulative over-receive against a partial receipt', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered, qty: 10);

        $this->service->receivePurchase($made['order']->id, [$made['item']->id => 8]);

        expect(fn () => $this->service->receivePurchase($made['order']->id, [$made['item']->id => 5]))
            ->toThrow(OutstandingQuantityExceededException::class);
    });

    it('allows a receipt exactly equal to the outstanding balance', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered, qty: 10);

        $this->service->receivePurchase($made['order']->id, [$made['item']->id => 10]);

        expect($made['item']->fresh()->received_base_qty)->toBe(10);
    });

    it('fires PurchaseOrderReceived on full receipt', function () {
        Event::fake([PurchaseOrderReceived::class]);
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered, qty: 10);

        $this->service->receivePurchase($made['order']->id, [$made['item']->id => 10]);

        Event::assertDispatched(
            PurchaseOrderReceived::class,
            fn ($e) => $e->purchaseOrderId === $made['order']->id,
        );
    });

    it('does not fire PurchaseOrderReceived on a partial receipt', function () {
        Event::fake([PurchaseOrderReceived::class]);
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered, qty: 10);

        $this->service->receivePurchase($made['order']->id, [$made['item']->id => 5]);

        Event::assertNotDispatched(PurchaseOrderReceived::class);
    });

    it('records the acting user in received_by', function () {
        $admin = actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered, qty: 10);

        $this->service->receivePurchase($made['order']->id, [$made['item']->id => 5]);

        expect($made['order']->fresh()->received_by)->toBe($admin->id);
    });

    it('tags the movement with reference_type = PurchaseOrder::class and reference_id = order id', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered, qty: 10);

        $this->service->receivePurchase($made['order']->id, [$made['item']->id => 5]);

        $movement = StockMovement::where('type', StockMovementType::Purchase)->first();
        expect($movement->reference_type)->toBe(PurchaseOrder::class);
        expect($movement->reference_id)->toBe((string) $made['order']->id);
        expect($movement->reference_code)->toBe($made['order']->reference_code);
    });

    it('writes a positive-signed Purchase movement', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered, qty: 10);

        $this->service->receivePurchase($made['order']->id, [$made['item']->id => 7]);

        $movement = StockMovement::where('type', StockMovementType::Purchase)->first();
        expect($movement->quantity)->toBe(7);
    });
});

// ===========================================================================
// receivePurchase() — cost price update (A4)
// ===========================================================================

describe('receivePurchase() — cost price update', function () {
    it('does not write a price row when update_cost_price is false', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered, qty: 10, unitCost: '15.0000', updateCost: false);

        $this->service->receivePurchase($made['order']->id, [$made['item']->id => 10]);

        expect(ProductVariantPrice::where('product_variant_id', $made['variant']->id)->count())->toBe(0);
    });

    it('writes a new current cost-price row when update_cost_price is true', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered, qty: 10, unitCost: '15.0000', updateCost: true);

        $this->service->receivePurchase($made['order']->id, [$made['item']->id => 5]);

        $current = ProductVariantPrice::where('product_variant_id', $made['variant']->id)
            ->where('is_current', true)
            ->first();

        expect($current)->not->toBeNull();
        expect($current->cost_price)->toBe('15.0000');
    });

    it('preserves the existing sale_price on the new row', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered, qty: 10, unitCost: '20.0000', updateCost: true);

        // Seed an existing current price row.
        ProductVariantPrice::factory()->create([
            'product_variant_id' => $made['variant']->id,
            'cost_price' => '10.0000',
            'sale_price' => '14.0000',
            'is_current' => true,
        ]);

        $this->service->receivePurchase($made['order']->id, [$made['item']->id => 5]);

        $current = ProductVariantPrice::where('product_variant_id', $made['variant']->id)
            ->where('is_current', true)
            ->first();

        expect($current->cost_price)->toBe('20.0000');
        expect($current->sale_price)->toBe('14.0000');
    });

    it('marks the prior current row as historical', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered, qty: 10, unitCost: '20.0000', updateCost: true);

        $prior = ProductVariantPrice::factory()->create([
            'product_variant_id' => $made['variant']->id,
            'cost_price' => '10.0000',
            'sale_price' => '14.0000',
            'is_current' => true,
        ]);

        $this->service->receivePurchase($made['order']->id, [$made['item']->id => 5]);

        expect($prior->fresh()->is_current)->toBeFalse();
    });

    it('leaves exactly one current row after the update', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered, qty: 10, unitCost: '20.0000', updateCost: true);

        ProductVariantPrice::factory()->create([
            'product_variant_id' => $made['variant']->id,
            'cost_price' => '10.0000',
            'is_current' => true,
        ]);

        $this->service->receivePurchase($made['order']->id, [$made['item']->id => 5]);

        expect(ProductVariantPrice::where('product_variant_id', $made['variant']->id)
            ->where('is_current', true)
            ->count())->toBe(1);
    });

    it('is a no-op when the new cost matches the current cost exactly', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered, qty: 10, unitCost: '10.0000', updateCost: true);

        ProductVariantPrice::factory()->create([
            'product_variant_id' => $made['variant']->id,
            'cost_price' => '10.0000',
            'sale_price' => '14.0000',
            'is_current' => true,
        ]);

        $this->service->receivePurchase($made['order']->id, [$made['item']->id => 5]);

        // Still one row — no new row was written.
        expect(ProductVariantPrice::where('product_variant_id', $made['variant']->id)->count())->toBe(1);
    });

    it('records the acting user in set_by', function () {
        $admin = actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered, qty: 10, unitCost: '15.0000', updateCost: true);

        $this->service->receivePurchase($made['order']->id, [$made['item']->id => 5]);

        $current = ProductVariantPrice::where('product_variant_id', $made['variant']->id)
            ->where('is_current', true)
            ->first();

        expect($current->set_by)->toBe($admin->id);
    });

    it('writes only one price row per variant when multiple lines share it', function () {
        actingAsAdmin();
        $warehouse = Warehouse::factory()->create();
        $supplier = Supplier::factory()->create();
        $orderer = User::factory()->create();
        $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

        $order = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseOrderStatus::Ordered,
            'ordered_by' => $orderer->id,
            'update_cost_price' => true,
        ]);

        $itemA = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'ordered_base_qty' => 10,
            'unit_cost_price' => '15.0000',
        ]);
        $itemB = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'ordered_base_qty' => 10,
            'unit_cost_price' => '25.0000',
        ]);

        $this->service->receivePurchase($order->id, [
            $itemA->id => 5,
            $itemB->id => 5,
        ]);

        // Exactly one current-price row for the shared variant.
        expect(ProductVariantPrice::where('product_variant_id', $variant->id)->count())->toBe(1);
        // First line's cost wins.
        $current = ProductVariantPrice::where('product_variant_id', $variant->id)->first();
        expect($current->cost_price)->toBe('15.0000');
    });
});

// ===========================================================================
// cancelPurchaseOrder()
// ===========================================================================

describe('cancelPurchaseOrder()', function () {
    it('cancels a Draft order with no received items', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Draft);

        $this->service->cancelPurchaseOrder($made['order']);

        $fresh = $made['order']->fresh();
        expect($fresh->status)->toBe(PurchaseOrderStatus::Cancelled);
        expect($fresh->cancelled_at)->not->toBeNull();
    });

    it('cancels an Ordered order with no received items', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered);

        $this->service->cancelPurchaseOrder($made['order']);

        expect($made['order']->fresh()->status)->toBe(PurchaseOrderStatus::Cancelled);
    });

    it('rejects an Ordered order with any received item', function () {
        // §3.14: item-level guard fires regardless of status.
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered);
        $made['item']->update(['received_base_qty' => 1]);

        expect(fn () => $this->service->cancelPurchaseOrder($made['order']))
            ->toThrow(InvalidDocumentStateException::class);
    });

    it('rejects a PartiallyReceived order', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::PartiallyReceived);

        expect(fn () => $this->service->cancelPurchaseOrder($made['order']))
            ->toThrow(InvalidDocumentStateException::class);
    });

    it('rejects a Received order', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Received);

        expect(fn () => $this->service->cancelPurchaseOrder($made['order']))
            ->toThrow(InvalidDocumentStateException::class);
    });

    it('rejects a Cancelled order', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Cancelled);

        expect(fn () => $this->service->cancelPurchaseOrder($made['order']))
            ->toThrow(InvalidDocumentStateException::class);
    });

    it('carries the cancel action label on rejection', function () {
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Received);

        try {
            $this->service->cancelPurchaseOrder($made['order']);
            $this->fail('Expected InvalidDocumentStateException was not thrown.');
        } catch (InvalidDocumentStateException $e) {
            expect($e->action)->toBe('cancel');
        }
    });

    it('fires PurchaseOrderCancelled', function () {
        Event::fake([PurchaseOrderCancelled::class]);
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Ordered);

        $this->service->cancelPurchaseOrder($made['order']);

        Event::assertDispatched(
            PurchaseOrderCancelled::class,
            fn ($e) => $e->purchaseOrderId === $made['order']->id,
        );
    });

    it('does not fire PurchaseOrderCancelled when the cancel is rejected', function () {
        Event::fake([PurchaseOrderCancelled::class]);
        actingAsAdmin();
        $made = makeOrder(PurchaseOrderStatus::Received);

        try {
            $this->service->cancelPurchaseOrder($made['order']);
        } catch (InvalidDocumentStateException) {
            // expected
        }

        Event::assertNotDispatched(PurchaseOrderCancelled::class);
    });
});

// ===========================================================================
// Service wiring
// ===========================================================================

describe('service wiring', function () {
    it('resolves from the container', function () {
        expect(app(PurchaseService::class))->toBeInstanceOf(PurchaseService::class);
    });

    it('injects a GuardsOutstandingQuantity', function () {
        $service = app(PurchaseService::class);

        $reflection = new ReflectionClass($service);
        $params = $reflection->getConstructor()->getParameters();

        expect($params)->toHaveCount(1);
        expect($params[0]->getType()->getName())->toBe(App\Services\GuardsOutstandingQuantity::class);
    });
});
