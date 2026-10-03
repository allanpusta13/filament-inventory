<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;

/**
 * §23.3 — Policy Registration.
 *
 * Every model→policy binding must be resolvable through the Gate.
 * This asserts the map in AppServiceProvider::registerPolicies() is
 * complete and correct for all 13 model/policy pairs.
 */
it('resolves every model to its policy through the Gate', function (string $model, string $policy) {
    expect(Gate::getPolicyFor($model))->toBe($policy);
})->with([
    [App\Models\Product::class,             App\Policies\ProductPolicy::class],
    [App\Models\ProductVariant::class,      App\Policies\ProductVariantPolicy::class],
    [App\Models\TransferRequisition::class, App\Policies\TransferRequisitionPolicy::class],
    [App\Models\InTransit::class,           App\Policies\InTransitPolicy::class],
    [App\Models\StockMovement::class,       App\Policies\StockMovementPolicy::class],
    [App\Models\LossLedger::class,          App\Policies\LossLedgerPolicy::class],
    [App\Models\PurchaseOrder::class,       App\Policies\PurchaseOrderPolicy::class],
    [App\Models\SalesOrder::class,          App\Policies\SalesOrderPolicy::class],
    [App\Models\Supplier::class,            App\Policies\SupplierPolicy::class],
    [App\Models\Customer::class,            App\Policies\CustomerPolicy::class],
    [App\Models\Warehouse::class,           App\Policies\WarehousePolicy::class],
    [App\Models\User::class,                App\Policies\UserPolicy::class],
    [App\Models\DirectTransfer::class,      App\Policies\DirectTransferPolicy::class],
]);

it('removes StockMovementPolicy::createDirectTransfer', function () {
    // §8.5: createDirectTransfer() moved to DirectTransferPolicy::create().
    // The old ability must not exist.
    $reflection = new ReflectionClass(App\Policies\StockMovementPolicy::class);

    expect($reflection->hasMethod('createDirectTransfer'))->toBeFalse();
});

it('declares DirectTransferPolicy::create instead', function () {
    $reflection = new ReflectionClass(App\Policies\DirectTransferPolicy::class);

    expect($reflection->hasMethod('create'))->toBeTrue();
});

it('registers every policy class in the AuthServiceProvider map or AppServiceProvider boot', function () {
    // §17.4: the canonical registration site is AppServiceProvider::boot()
    // (Laravel 11+ slim skeleton). Verify Gate resolves for a representative
    // model — the dataset above already proves every binding individually.
    $allModels = [
        App\Models\Product::class,
        App\Models\ProductVariant::class,
        App\Models\TransferRequisition::class,
        App\Models\InTransit::class,
        App\Models\StockMovement::class,
        App\Models\LossLedger::class,
        App\Models\PurchaseOrder::class,
        App\Models\SalesOrder::class,
        App\Models\Supplier::class,
        App\Models\Customer::class,
        App\Models\Warehouse::class,
        App\Models\User::class,
        App\Models\DirectTransfer::class,
    ];

    foreach ($allModels as $model) {
        expect(Gate::getPolicyFor($model))->not->toBeNull(
            "No policy registered for {$model}."
        );
    }
});
