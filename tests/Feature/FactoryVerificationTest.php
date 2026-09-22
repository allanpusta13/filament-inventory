<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\InTransit;
use App\Models\LossLedger;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\ProductVariantUnitConversion;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\TransferRequisitionItemRevision;
use App\Models\User;
use App\Models\Warehouse;

test('warehouse factory creates default', function () {
    $wh = Warehouse::factory()->create();
    expect($wh)->toBeInstanceOf(Warehouse::class)
        ->and($wh->code)->not->toBeEmpty()
        ->and($wh->name)->not->toBeEmpty();
});

test('user factory creates default', function () {
    $user = User::factory()->create();
    expect($user)->toBeInstanceOf(User::class)
        ->and($user->email)->not->toBeEmpty()
        ->and($user->name)->not->toBeEmpty();
});

test('user factory admin state', function () {
    $user = User::factory()->admin()->create();
    expect($user->isAdmin())->toBeTrue();
});

test('user factory warehouseStaff state', function () {
    $user = User::factory()->warehouseStaff()->create();
    expect($user)->toBeInstanceOf(User::class)
        ->and($user->name)->not->toBeEmpty();
});

test('product factory creates default', function () {
    $p = Product::factory()->create();
    expect($p)->toBeInstanceOf(Product::class)
        ->and($p->name)->not->toBeEmpty();
});

test('productVariant factory creates default', function () {
    $pv = ProductVariant::factory()->create();
    expect($pv)->toBeInstanceOf(ProductVariant::class)
        ->and($pv->sku)->not->toBeEmpty();
});

test('productVariantPrice factory creates default', function () {
    $pvp = ProductVariantPrice::factory()->create();
    expect($pvp)->toBeInstanceOf(ProductVariantPrice::class)
        ->and($pvp->cost_price)->not->toBeNull()
        ->and($pvp->sale_price)->not->toBeNull();
});

test('productVariantUnitConversion factory creates default', function () {
    $uc = ProductVariantUnitConversion::factory()->create();
    expect($uc)->toBeInstanceOf(ProductVariantUnitConversion::class);
});

test('stockMovement factory creates default', function () {
    $sm = StockMovement::factory()->create();
    expect($sm)->toBeInstanceOf(StockMovement::class)
        ->and($sm->quantity)->not->toBeNull();
});

test('inTransit factory creates default', function () {
    $it = InTransit::factory()->create();
    expect($it)->toBeInstanceOf(InTransit::class);
});

test('lossLedger factory creates default', function () {
    $ll = LossLedger::factory()->create();
    expect($ll)->toBeInstanceOf(LossLedger::class);
});

test('transferRequisition factory creates default', function () {
    $tr = TransferRequisition::factory()->create();
    expect($tr)->toBeInstanceOf(TransferRequisition::class);
});

test('transferRequisitionItem factory creates default', function () {
    $tri = TransferRequisitionItem::factory()->create();
    expect($tri)->toBeInstanceOf(TransferRequisitionItem::class);
});

test('transferRequisitionItemRevision factory creates default', function () {
    $tir = TransferRequisitionItemRevision::factory()->create();
    expect($tir)->toBeInstanceOf(TransferRequisitionItemRevision::class);
});

test('all factories create valid models without exceptions', function () {
    $factories = [
        Warehouse::class,
        User::class,
        Product::class,
        ProductVariant::class,
        ProductVariantPrice::class,
        ProductVariantUnitConversion::class,
        StockMovement::class,
        InTransit::class,
        LossLedger::class,
        TransferRequisition::class,
        TransferRequisitionItem::class,
        TransferRequisitionItemRevision::class,
        Supplier::class,
        Customer::class,
        PurchaseOrder::class,
        PurchaseOrderItem::class,
        SalesOrder::class,
        SalesOrderItem::class,
    ];

    foreach ($factories as $model) {
        $instance = $model::factory()->create();
        expect($instance->exists)->toBeTrue("Failed creating {$model}");
    }
});

test('supplier factory creates default', function () {
    $s = Supplier::factory()->create();
    expect($s)->toBeInstanceOf(Supplier::class)
        ->and($s->name)->not->toBeEmpty()
        ->and($s->is_active)->toBeTrue();
});

test('supplier factory inactive state', function () {
    $s = Supplier::factory()->inactive()->create();
    expect($s->is_active)->toBeFalse();
});

test('customer factory creates default', function () {
    $c = Customer::factory()->create();
    expect($c)->toBeInstanceOf(Customer::class)
        ->and($c->name)->not->toBeEmpty()
        ->and($c->is_active)->toBeTrue();
});

test('customer factory inactive state', function () {
    $c = Customer::factory()->inactive()->create();
    expect($c->is_active)->toBeFalse();
});

test('purchaseOrder factory creates default', function () {
    $po = PurchaseOrder::factory()->create();
    expect($po)->toBeInstanceOf(PurchaseOrder::class)
        ->and($po->reference_code)->not->toBeEmpty()
        ->and($po->status->value)->toBe('draft');
});

test('purchaseOrder factory ordered state', function () {
    $po = PurchaseOrder::factory()->ordered()->create();
    expect($po->status->value)->toBe('ordered')
        ->and($po->ordered_at)->not->toBeNull();
});

test('purchaseOrder factory completed state', function () {
    $po = PurchaseOrder::factory()->completed()->create();
    expect($po->status->value)->toBe('completed')
        ->and($po->received_at)->not->toBeNull();
});

test('purchaseOrderItem factory creates default', function () {
    $poi = PurchaseOrderItem::factory()->create();
    expect($poi)->toBeInstanceOf(PurchaseOrderItem::class)
        ->and($poi->ordered_base_qty)->toBe($poi->ordered_qty * $poi->ordered_unit_ratio)
        ->and($poi->received_base_qty)->toBe(0);
});

test('purchaseOrderItem factory fullyReceived state', function () {
    $poi = PurchaseOrderItem::factory()->fullyReceived()->create();
    expect($poi->received_base_qty)->toBe($poi->ordered_base_qty);
});

test('purchaseOrderItem factory partiallyReceived state', function () {
    $poi = PurchaseOrderItem::factory()->partiallyReceived()->create();
    expect($poi->received_base_qty)->toBeLessThan($poi->ordered_base_qty)
        ->and($poi->received_base_qty)->toBeGreaterThanOrEqual(0);
});

test('salesOrder factory creates default', function () {
    $so = SalesOrder::factory()->create();
    expect($so)->toBeInstanceOf(SalesOrder::class)
        ->and($so->reference_code)->not->toBeEmpty()
        ->and($so->status->value)->toBe('draft');
});

test('salesOrder factory confirmed state', function () {
    $so = SalesOrder::factory()->confirmed()->create();
    expect($so->status->value)->toBe('confirmed')
        ->and($so->confirmed_at)->not->toBeNull();
});

test('salesOrder factory dispatched state', function () {
    $so = SalesOrder::factory()->dispatched()->create();
    expect($so->status->value)->toBe('dispatched')
        ->and($so->dispatched_at)->not->toBeNull();
});

test('salesOrder factory completed state', function () {
    $so = SalesOrder::factory()->completed()->create();
    expect($so->status->value)->toBe('completed');
});

test('salesOrderItem factory creates default', function () {
    $soi = SalesOrderItem::factory()->create();
    expect($soi)->toBeInstanceOf(SalesOrderItem::class)
        ->and($soi->base_qty)->toBe($soi->qty * $soi->unit_ratio)
        ->and($soi->unit_sale_price_snapshot)->toBe('0.0000')
        ->and($soi->dispatched_base_qty)->toBe(0);
});

test('salesOrderItem factory fullyDispatched state', function () {
    $soi = SalesOrderItem::factory()->fullyDispatched()->create();
    expect($soi->dispatched_base_qty)->toBe($soi->base_qty);
});

test('salesOrderItem factory partiallyDispatched state', function () {
    $soi = SalesOrderItem::factory()->partiallyDispatched()->create();
    expect($soi->dispatched_base_qty)->toBeLessThan($soi->base_qty)
        ->and($soi->dispatched_base_qty)->toBeGreaterThanOrEqual(0);
});
