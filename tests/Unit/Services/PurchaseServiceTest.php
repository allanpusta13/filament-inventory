<?php

declare(strict_types=1);

use App\Enums\PurchaseOrderStatus;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Services\PurchaseService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->service = new PurchaseService();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('PurchaseService', function () {

    it('order_throws_when_not_draft', function () {
        $po = PurchaseOrder::factory()->ordered()->create();

        expect(fn () => $this->service->orderPurchase($po))
            ->toThrow(Exception::class, 'must be in draft');
    });

    it('order_throws_when_no_items', function () {
        $po = PurchaseOrder::factory()->create();

        expect(fn () => $this->service->orderPurchase($po))
            ->toThrow(Exception::class, 'must have at least one line item');
    });

    it('order_succeeds_and_sets_status_ordered', function () {
        $po = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->count(3)->create(['purchase_order_id' => $po->id]);

        $this->service->orderPurchase($po);

        expect($po->fresh()->status)->toBe(PurchaseOrderStatus::Ordered)
            ->and($po->fresh()->ordered_at)->not->toBeNull();
    });

    it('receive_purchase_supports_partial_batches', function () {
        $po = PurchaseOrder::factory()->ordered()->create();
        $items = PurchaseOrderItem::factory()->count(2)->state([
            'ordered_base_qty' => 100,
            'received_base_qty' => 0,
        ])->create(['purchase_order_id' => $po->id]);

        $item1 = $items->first();
        $item2 = $items->last();

        $this->service->receivePurchase($po->id, [
            $item1->id => 30,
            $item2->id => 50,
        ]);

        expect($item1->fresh()->received_base_qty)->toBe(30)
            ->and($item2->fresh()->received_base_qty)->toBe(50)
            ->and($po->fresh()->status)->toBe(PurchaseOrderStatus::PartiallyReceived);

        $this->service->receivePurchase($po->id, [
            $item1->id => 70,
            $item2->id => 50,
        ]);

        expect($item1->fresh()->received_base_qty)->toBe(100)
            ->and($item2->fresh()->received_base_qty)->toBe(100)
            ->and($po->fresh()->status)->toBe(PurchaseOrderStatus::Completed);
    });

    it('receive_purchase_rejects_over_receipt_beyond_ordered_qty', function () {
        $po = PurchaseOrder::factory()->ordered()->create();
        $item = PurchaseOrderItem::factory()->state([
            'ordered_base_qty' => 100,
            'received_base_qty' => 0,
        ])->create(['purchase_order_id' => $po->id]);

        expect(fn () => $this->service->receivePurchase($po->id, [
            $item->id => 101,
        ]))->toThrow(Exception::class, 'Cannot receive');
    });

    it('receive_purchase_updates_cost_price_when_flag_set', function () {
        $variant = ProductVariant::factory()->create();
        ProductVariantPrice::factory()->create([
            'product_variant_id' => $variant->id,
            'cost_price' => '10.0000',
            'is_current' => true,
        ]);

        $po = PurchaseOrder::factory()
            ->ordered()
            ->withCostUpdate()
            ->create();
        $item = PurchaseOrderItem::factory()->state([
            'product_variant_id' => $variant->id,
            'ordered_base_qty' => 100,
            'unit_cost_price' => '12.0000',
        ])->create(['purchase_order_id' => $po->id]);

        $this->service->receivePurchase($po->id, [$item->id => 100]);

        $newPrice = ProductVariantPrice::where('product_variant_id', $variant->id)
            ->where('is_current', true)
            ->first();

        expect($newPrice->cost_price)->toBe('12.0000');
    });

    it('receive_purchase_does_not_update_cost_price_when_flag_unset', function () {
        $variant = ProductVariant::factory()->create();
        ProductVariantPrice::factory()->create([
            'product_variant_id' => $variant->id,
            'cost_price' => '10.0000',
            'is_current' => true,
        ]);

        $po = PurchaseOrder::factory()->ordered()->create();
        $item = PurchaseOrderItem::factory()->state([
            'product_variant_id' => $variant->id,
            'ordered_base_qty' => 100,
            'unit_cost_price' => '12.0000',
        ])->create(['purchase_order_id' => $po->id]);

        $this->service->receivePurchase($po->id, [$item->id => 100]);

        $newPrice = ProductVariantPrice::where('product_variant_id', $variant->id)
            ->where('is_current', true)
            ->first();

        expect($newPrice->cost_price)->toBe('10.0000');
    });

    it('receive_purchase_skips_price_update_when_cost_unchanged', function () {
        $variant = ProductVariant::factory()->create();
        ProductVariantPrice::factory()->create([
            'product_variant_id' => $variant->id,
            'cost_price' => '10.0000',
            'is_current' => true,
        ]);

        $po = PurchaseOrder::factory()
            ->ordered()
            ->withCostUpdate()
            ->create();
        $item = PurchaseOrderItem::factory()->state([
            'product_variant_id' => $variant->id,
            'ordered_base_qty' => 100,
            'unit_cost_price' => '10.0000',
        ])->create(['purchase_order_id' => $po->id]);

        $this->service->receivePurchase($po->id, [$item->id => 100]);

        $currentCount = ProductVariantPrice::where('product_variant_id', $variant->id)
            ->where('is_current', true)
            ->count();

        expect($currentCount)->toBe(1);
    });

    it('receive_purchase_sets_completed_when_fully_received', function () {
        $po = PurchaseOrder::factory()->ordered()->create();
        $item = PurchaseOrderItem::factory()->state([
            'ordered_base_qty' => 50,
            'received_base_qty' => 0,
        ])->create(['purchase_order_id' => $po->id]);

        $this->service->receivePurchase($po->id, [$item->id => 50]);

        expect($po->fresh()->status)->toBe(PurchaseOrderStatus::Completed)
            ->and($po->fresh()->received_at)->not->toBeNull();
    });

    it('receive_purchase_sets_partially_received_when_incomplete', function () {
        $po = PurchaseOrder::factory()->ordered()->create();
        $items = PurchaseOrderItem::factory()->count(2)->state([
            'ordered_base_qty' => 50,
            'received_base_qty' => 0,
        ])->create(['purchase_order_id' => $po->id]);

        $item1 = $items->first();
        $item2 = $items->last();

        $this->service->receivePurchase($po->id, [$item1->id => 50]);

        expect($po->fresh()->status)->toBe(PurchaseOrderStatus::PartiallyReceived);
    });

    it('cancel_rejected_once_any_stock_received', function () {
        $po = PurchaseOrder::factory()->partiallyReceived()->create();
        $item = PurchaseOrderItem::factory()->state([
            'ordered_base_qty' => 100,
            'received_base_qty' => 10,
        ])->create(['purchase_order_id' => $po->id]);

        expect(fn () => $this->service->cancelPurchaseOrder($po))
            ->toThrow(Exception::class, 'Cannot cancel a purchase order that has already received stock');
    });

    it('cancel_succeeds_while_fully_unreceived', function () {
        $po = PurchaseOrder::factory()->ordered()->create();

        $this->service->cancelPurchaseOrder($po);

        expect($po->fresh()->status)->toBe(PurchaseOrderStatus::Cancelled)
            ->and($po->fresh()->cancelled_at)->not->toBeNull();
    });
});
