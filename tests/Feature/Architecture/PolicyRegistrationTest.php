<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;

/**
 * §23.3 — Policy Registration.
 *
 * Every model→policy binding must be registered EXPLICITLY by the map in
 * AppServiceProvider::registerPolicies().
 *
 * NOTE: `Gate::getPolicyFor()` alone is a weak oracle. `Gate::resolvePolicy()`
 * falls back to `guessPolicyName()`, which auto-discovers
 * `App\Models\X` → `App\Policies\XPolicy` for every pair here — so a suite
 * asserting only `getPolicyFor()` passes even with `registerPolicies()`
 * deleted. The primary assertions below read the explicit `Gate::$policies`
 * map instead.
 */
$policyMap = [
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
];

/** The explicit model→policy map held by the Gate (not auto-discovery). */
$explicitPolicyMap = function (): array {
    $gate = Gate::getFacadeRoot();
    $property = new ReflectionProperty($gate, 'policies');
    $property->setAccessible(true);

    return $property->getValue($gate);
};

it('registers every model→policy pair explicitly in the Gate map', function (string $model, string $policy) use ($explicitPolicyMap) {
    expect($explicitPolicyMap()[$model] ?? null)->toBe($policy);
})->with($policyMap);

it('resolves every model to its policy through the Gate', function (string $model, string $policy) {
    expect(Gate::getPolicyFor($model))->toBeInstanceOf($policy);
})->with($policyMap);

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

it('registers a policy for every §8 model in the explicit Gate map', function () use ($explicitPolicyMap) {
    // §17.4: canonical registration site is AppServiceProvider::registerPolicies()
    // (Laravel 11+ slim skeleton). Assert the EXPLICIT map, not auto-discovery.
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

    $explicit = $explicitPolicyMap();

    foreach ($allModels as $model) {
        expect($explicit[$model] ?? null)->not->toBeNull(
            "No policy explicitly registered for {$model}."
        );
    }
});
