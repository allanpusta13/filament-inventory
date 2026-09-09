<?php

declare(strict_types=1);

use App\Models\InTransit;
use App\Models\LossLedger;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\ProductVariantUnitConversion;
use App\Models\StockMovement;
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
    ];

    foreach ($factories as $model) {
        $instance = $model::factory()->create();
        expect($instance->exists)->toBeTrue("Failed creating {$model}");
    }
});
