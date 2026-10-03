<?php

declare(strict_types=1);

use App\Services\GuardsOutstandingQuantity;
use App\Services\InventoryService;
use App\Services\NegotiationService;
use App\Services\PurchaseService;
use App\Services\SalesService;
use App\Services\TransferRequisitionService;

/**
 * §23.1 — Service Resolution.
 *
 * Every domain service must resolve from the container. This is the
 * contract the four `Create*` wizard pages and every Action depend on.
 */
it('resolves every domain service', function (string $service) {
    expect(app($service))->toBeInstanceOf($service);
})->with([
    GuardsOutstandingQuantity::class,
    InventoryService::class,
    NegotiationService::class,
    TransferRequisitionService::class,
    PurchaseService::class,
    SalesService::class,
]);

it('binds every service as non-singleton (fresh per resolution)', function (string $service) {
    // §17.1: bind(), not singleton(). A singleton InventoryService would
    // leak `auth()->id()` across requests in a long-lived worker.
    expect(app($service))->not->toBe(app($service));
})->with([
    InventoryService::class,
    NegotiationService::class,
    TransferRequisitionService::class,
    PurchaseService::class,
    SalesService::class,
]);

it('resolves GuardsOutstandingQuantity with no constructor dependencies', function () {
    $guard = app(GuardsOutstandingQuantity::class);

    $reflection = new ReflectionClass($guard);
    expect($reflection->getConstructor())->toBeNull();
});

it('injects GuardsOutstandingQuantity into PurchaseService and SalesService', function (string $service) {
    $instance = app($service);
    $constructor = (new ReflectionClass($instance))->getConstructor();

    expect($constructor)->not->toBeNull();

    $paramTypes = collect($constructor->getParameters())
        ->map(fn ($p) => $p->getType()?->getName())
        ->all();

    expect($paramTypes)->toContain(GuardsOutstandingQuantity::class);
})->with([
    PurchaseService::class,
    SalesService::class,
]);

it('injects NegotiationService into TransferRequisitionService', function () {
    $instance = app(TransferRequisitionService::class);
    $constructor = (new ReflectionClass($instance))->getConstructor();

    $paramTypes = collect($constructor->getParameters())
        ->map(fn ($p) => $p->getType()?->getName())
        ->all();

    expect($paramTypes)->toContain(NegotiationService::class);
});
