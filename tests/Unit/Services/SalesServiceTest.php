<?php

declare(strict_types=1);

use App\Enums\SalesOrderStatus;
use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SalesService;
use Illuminate\Validation\ValidationException;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->service = new SalesService();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('SalesService', function () {

    it('confirm_snapshots_sale_price_at_confirm_time_not_dispatch_time', function () {
        $variant = ProductVariant::factory()->create();
        ProductVariantPrice::factory()->create([
            'product_variant_id' => $variant->id,
            'sale_price' => '100.0000',
            'is_current' => true,
        ]);

        $order = SalesOrder::factory()->create();
        $item = SalesOrderItem::factory()->state([
            'product_variant_id' => $variant->id,
            'qty' => 10,
            'unit_ratio' => 10,
            'base_qty' => 100,
        ])->create(['sales_order_id' => $order->id]);

        // Add stock for confirmation check (need more than base_qty to pass available check)
        StockMovement::factory()->create([
            'product_variant_id' => $variant->id,
            'warehouse_id' => $order->warehouse_id,
            'type' => StockMovementType::Purchase,
            'quantity' => 200,
        ]);

        $this->service->confirmSalesOrder($order);

        ProductVariantPrice::where('product_variant_id', $variant->id)
            ->where('is_current', true)
            ->update(['is_current' => false]);
        ProductVariantPrice::create([
            'product_variant_id' => $variant->id,
            'cost_price' => '50.0000',
            'sale_price' => '200.0000',
            'effective_from' => now(),
            'is_current' => true,
            'set_by' => $this->user->id,
        ]);

        $this->service->dispatchSale($order, [
            [
                'item_id' => $item->id,
                'dispatched_base_qty' => 50,
                'unit_name' => $item->unit_name,
                'unit_ratio' => $item->unit_ratio,
            ],
        ]);

        $item->refresh();
        expect($item->unit_sale_price_snapshot)->toBe('100.0000');
    });

    it('confirm_throws_when_not_draft', function () {
        $order = SalesOrder::factory()->confirmed()->create();

        expect(fn () => $this->service->confirmSalesOrder($order))
            ->toThrow(ValidationException::class, 'Only draft sales orders can be confirmed.');
    });

    it('confirm_throws_when_no_items', function () {
        $order = SalesOrder::factory()->create();

        expect(fn () => $this->service->confirmSalesOrder($order))
            ->toThrow(ValidationException::class, 'Cannot confirm sales order with no line items.');
    });

    it('dispatch_supports_partial_batches', function () {
        $variant = ProductVariant::factory()->create();
        ProductVariantPrice::factory()->create([
            'product_variant_id' => $variant->id,
            'sale_price' => '100.0000',
            'is_current' => true,
        ]);

        $order = SalesOrder::factory()->confirmed()->create();
        $items = SalesOrderItem::factory()->count(2)->state([
            'product_variant_id' => $variant->id,
            'qty' => 10,
            'unit_ratio' => 10,
            'base_qty' => 100,
            'dispatched_base_qty' => 0,
        ])->create(['sales_order_id' => $order->id]);

        $item1 = $items->first();
        $item2 = $items->last();

        // Add stock for both items (total 200 base units) + transfer reservation 50
        StockMovement::factory()->create([
            'product_variant_id' => $variant->id,
            'warehouse_id' => $order->warehouse_id,
            'type' => StockMovementType::Purchase,
            'quantity' => 250,
        ]);

        $this->service->dispatchSale($order, [
            [
                'item_id' => $item1->id,
                'dispatched_base_qty' => 30,
                'unit_name' => $item1->unit_name,
                'unit_ratio' => $item1->unit_ratio,
            ],
            [
                'item_id' => $item2->id,
                'dispatched_base_qty' => 50,
                'unit_name' => $item2->unit_name,
                'unit_ratio' => $item2->unit_ratio,
            ],
        ]);

        expect($item1->fresh()->dispatched_base_qty)->toBe(30)
            ->and($item2->fresh()->dispatched_base_qty)->toBe(50)
            ->and($order->fresh()->status)->toBe(SalesOrderStatus::PartiallyDispatched);

        $this->service->dispatchSale($order, [
            [
                'item_id' => $item1->id,
                'dispatched_base_qty' => 70,
                'unit_name' => $item1->unit_name,
                'unit_ratio' => $item1->unit_ratio,
            ],
            [
                'item_id' => $item2->id,
                'dispatched_base_qty' => 50,
                'unit_name' => $item2->unit_name,
                'unit_ratio' => $item2->unit_ratio,
            ],
        ]);

        expect($item1->fresh()->dispatched_base_qty)->toBe(100)
            ->and($item2->fresh()->dispatched_base_qty)->toBe(100)
            ->and($order->fresh()->status)->toBe(SalesOrderStatus::Dispatched);
    });

    it('dispatch_rejects_over_dispatch_beyond_ordered_qty', function () {
        $variant = ProductVariant::factory()->create();
        ProductVariantPrice::factory()->create([
            'product_variant_id' => $variant->id,
            'sale_price' => '100.0000',
            'is_current' => true,
        ]);

        $order = SalesOrder::factory()->confirmed()->create();
        $item = SalesOrderItem::factory()->state([
            'product_variant_id' => $variant->id,
            'base_qty' => 100,
            'dispatched_base_qty' => 0,
        ])->create(['sales_order_id' => $order->id]);

        // Add stock so available >= 101 to let "exceeds ordered qty" check run first
        // on_hand - reserved_sales >= 101 => on_hand - 100 >= 101 => on_hand >= 201
        StockMovement::factory()->create([
            'product_variant_id' => $variant->id,
            'warehouse_id' => $order->warehouse_id,
            'type' => StockMovementType::Purchase,
            'quantity' => 250,
        ]);

        expect(fn () => $this->service->dispatchSale($order, [
            [
                'item_id' => $item->id,
                'dispatched_base_qty' => 101,
                'unit_name' => $item->unit_name,
                'unit_ratio' => $item->unit_ratio,
            ],
        ]))->toThrow(ValidationException::class, 'Dispatched quantity cannot exceed ordered quantity');
    });

    it('dispatch_rejects_when_on_hand_insufficient', function () {
        $variant = ProductVariant::factory()->create();
        ProductVariantPrice::factory()->create([
            'product_variant_id' => $variant->id,
            'sale_price' => '100.0000',
            'is_current' => true,
        ]);

        $order = SalesOrder::factory()->confirmed()->create();
        $item = SalesOrderItem::factory()->state([
            'product_variant_id' => $variant->id,
            'base_qty' => 100,
            'dispatched_base_qty' => 0,
        ])->create(['sales_order_id' => $order->id]);

        // No stock - should fail
        expect(fn () => $this->service->dispatchSale($order, [
            [
                'item_id' => $item->id,
                'dispatched_base_qty' => 10,
                'unit_name' => $item->unit_name,
                'unit_ratio' => $item->unit_ratio,
            ],
        ]))->toThrow(ValidationException::class, 'Insufficient available stock');
    });

    it('dispatch_does_not_touch_reservedQuantity_transfers_scope', function () {
        $variant = ProductVariant::factory()->create();
        ProductVariantPrice::factory()->create([
            'product_variant_id' => $variant->id,
            'sale_price' => '100.0000',
            'is_current' => true,
        ]);

        $warehouse = Warehouse::factory()->create();

        $transfer = App\Models\TransferRequisition::factory()->approved()->create([
            'from_warehouse_id' => $warehouse->id,
        ]);
        App\Models\TransferRequisitionItem::factory()->create([
            'transfer_requisition_id' => $transfer->id,
            'product_variant_id' => $variant->id,
            'approved_base_qty' => 50,
        ]);

        StockMovement::factory()->create([
            'product_variant_id' => $variant->id,
            'warehouse_id' => $warehouse->id,
            'type' => StockMovementType::Purchase,
            'quantity' => 150,
        ]);

        $order = SalesOrder::factory()->confirmed()->create([
            'warehouse_id' => $warehouse->id,
        ]);
        $item = SalesOrderItem::factory()->state([
            'product_variant_id' => $variant->id,
            'qty' => 5,
            'unit_ratio' => 10,
            'base_qty' => 50,
        ])->create(['sales_order_id' => $order->id]);

        $this->service->dispatchSale($order, [
            [
                'item_id' => $item->id,
                'dispatched_base_qty' => 50,
                'unit_name' => $item->unit_name,
                'unit_ratio' => $item->unit_ratio,
            ],
        ]);

        expect($item->fresh()->dispatched_base_qty)->toBe(50);
    });

    it('dispatch_sets_completed_when_fully_dispatched', function () {
        $variant = ProductVariant::factory()->create();
        ProductVariantPrice::factory()->create([
            'product_variant_id' => $variant->id,
            'sale_price' => '100.0000',
            'is_current' => true,
        ]);

        $order = SalesOrder::factory()->confirmed()->create();
        $item = SalesOrderItem::factory()->state([
            'product_variant_id' => $variant->id,
            'qty' => 5,
            'unit_ratio' => 10,
            'base_qty' => 50,
            'dispatched_base_qty' => 0,
        ])->create(['sales_order_id' => $order->id]);

        StockMovement::factory()->create([
            'product_variant_id' => $variant->id,
            'warehouse_id' => $order->warehouse_id,
            'type' => StockMovementType::Purchase,
            'quantity' => 100, // More than needed
        ]);

        $this->service->dispatchSale($order, [
            [
                'item_id' => $item->id,
                'dispatched_base_qty' => 50,
                'unit_name' => $item->unit_name,
                'unit_ratio' => $item->unit_ratio,
            ],
        ]);

        expect($order->fresh()->status)->toBe(SalesOrderStatus::Dispatched)
            ->and($order->fresh()->dispatched_at)->not->toBeNull();
    });

    it('dispatch_sets_partially_dispatched_when_incomplete', function () {
        $variant = ProductVariant::factory()->create();
        ProductVariantPrice::factory()->create([
            'product_variant_id' => $variant->id,
            'sale_price' => '100.0000',
            'is_current' => true,
        ]);

        $order = SalesOrder::factory()->confirmed()->create();
        $items = SalesOrderItem::factory()->count(2)->state([
            'product_variant_id' => $variant->id,
            'qty' => 5,
            'unit_ratio' => 10,
            'base_qty' => 50,
            'dispatched_base_qty' => 0,
        ])->create(['sales_order_id' => $order->id]);

        $item1 = $items->first();
        $item2 = $items->last();

        StockMovement::factory()->create([
            'product_variant_id' => $variant->id,
            'warehouse_id' => $order->warehouse_id,
            'type' => StockMovementType::Purchase,
            'quantity' => 150, // Enough for reserved 100 + dispatch 50
        ]);

        $this->service->dispatchSale($order, [
            [
                'item_id' => $item1->id,
                'dispatched_base_qty' => 50,
                'unit_name' => $item1->unit_name,
                'unit_ratio' => $item1->unit_ratio,
            ],
        ]);

        expect($order->fresh()->status)->toBe(SalesOrderStatus::PartiallyDispatched);
    });

    it('cancel_rejected_once_dispatch_has_begun', function () {
        $order = SalesOrder::factory()->partiallyDispatched()->create();
        $item = SalesOrderItem::factory()->state([
            'dispatched_base_qty' => 10,
        ])->create(['sales_order_id' => $order->id]);

        expect(fn () => $this->service->cancelSalesOrder($order))
            ->toThrow(ValidationException::class, 'Cannot cancel sales order dispatched items. Use returns instead.');

        $order2 = SalesOrder::factory()->dispatched()->create();
        expect(fn () => $this->service->cancelSalesOrder($order2))
            ->toThrow(ValidationException::class, 'This sales order cannot cancelled.');

        $order3 = SalesOrder::factory()->completed()->create();
        expect(fn () => $this->service->cancelSalesOrder($order3))
            ->toThrow(ValidationException::class, 'This sales order cannot cancelled.');
    });

    it('cancel_succeeds_while_draft_or_confirmed', function () {
        $draftOrder = SalesOrder::factory()->create();
        $this->service->cancelSalesOrder($draftOrder);
        expect($draftOrder->fresh()->status)->toBe(SalesOrderStatus::Cancelled)
            ->and($draftOrder->fresh()->cancelled_at)->not->toBeNull();

        $confirmedOrder = SalesOrder::factory()->confirmed()->create();
        $confirmedOrder->items()->delete();
        $this->service->cancelSalesOrder($confirmedOrder);
        expect($confirmedOrder->fresh()->status)->toBe(SalesOrderStatus::Cancelled)
            ->and($confirmedOrder->fresh()->cancelled_at)->not->toBeNull();
    });

    it('sales_return_rejected_beyond_dispatched_qty', function () {
        $order = SalesOrder::factory()->dispatched()->create();
        $item = SalesOrderItem::factory()->state([
            'dispatched_base_qty' => 50,
        ])->create(['sales_order_id' => $order->id]);

        expect(fn () => $this->service->recordReturn($order, [
            [
                'item_id' => $item->id,
                'return_base_qty' => 51,
                'unit_name' => $item->unit_name,
                'unit_ratio' => $item->unit_ratio,
            ],
        ]))->toThrow(ValidationException::class, 'Return quantity cannot exceed dispatched quantity');
    });

    it('sales_return_creates_positive_sale_return_movement', function () {
        $order = SalesOrder::factory()->dispatched()->create();
        $item = SalesOrderItem::factory()->state([
            'dispatched_base_qty' => 50,
        ])->create(['sales_order_id' => $order->id]);

        $this->service->recordReturn($order, [
            [
                'item_id' => $item->id,
                'return_base_qty' => 20,
                'unit_name' => $item->unit_name,
                'unit_ratio' => $item->unit_ratio,
            ],
        ]);

        $movement = StockMovement::where('type', StockMovementType::SaleReturn)
            ->where('product_variant_id', $item->product_variant_id)
            ->first();

        expect($movement)->not->toBeNull()
            ->and($movement->quantity)->toBe(20)
            ->and($movement->reference_type)->toBe(SalesOrder::class);
    });
});
