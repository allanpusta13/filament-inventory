<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Event;

/**
 * §17 — Runtime Wiring.
 *
 * Verifies that AppServiceProvider::boot() has registered observers,
 * event→listener pairs, and the Logout/Login badge-scope flush.
 */
it('registers ProductObserver on the Product model', function () {
    // §17.3: the observer must be registered before any variant is
    // created so the base-unit self-conversion row (F19) exists.
    // Practical assertion: dispatch a Product deletion with variants and
    // confirm the guard fires — if the observer is missing, no exception.
    $product = Product::factory()->create();
    ProductVariant::factory()->create(['product_id' => $product->id]);

    expect(fn () => $product->delete())
        ->toThrow(App\Exceptions\ProductFamilyHasVariantsException::class);
});

it('registers ProductVariantObserver on the ProductVariant model', function () {
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    expect(App\Models\ProductVariantUnitConversion::where('product_variant_id', $variant->id)
        ->where('unit_name', 'pc')
        ->exists())->toBeTrue();
});

it('registers a listener for the TransferConfirmed event', function () {
    expect(Event::hasListeners(App\Events\TransferConfirmed::class))->toBeTrue();
});

it('registers a listener for the TransferCancelled event', function () {
    expect(Event::hasListeners(App\Events\TransferCancelled::class))->toBeTrue();
});

it('registers a listener for the TransferDispatched event', function () {
    expect(Event::hasListeners(App\Events\TransferDispatched::class))->toBeTrue();
});

it('registers a listener for the TransferReceived event', function () {
    expect(Event::hasListeners(App\Events\TransferReceived::class))->toBeTrue();
});

it('registers a listener for the LossRecorded event', function () {
    expect(Event::hasListeners(App\Events\LossRecorded::class))->toBeTrue();
});

it('registers a listener for the PurchaseOrderReceived event', function () {
    expect(Event::hasListeners(App\Events\PurchaseOrderReceived::class))->toBeTrue();
});

it('registers a listener for the PurchaseOrderCancelled event', function () {
    expect(Event::hasListeners(App\Events\PurchaseOrderCancelled::class))->toBeTrue();
});

it('registers a listener for the SalesOrderDispatched event', function () {
    expect(Event::hasListeners(App\Events\SalesOrderDispatched::class))->toBeTrue();
});

it('registers a listener for the InventoryBelowReorderPoint event', function () {
    expect(Event::hasListeners(App\Events\InventoryBelowReorderPoint::class))->toBeTrue();
});

it('registers a Logout listener for badge scope flush', function () {
    expect(Event::hasListeners(Illuminate\Auth\Events\Logout::class))->toBeTrue();
});

it('registers a Login listener for badge scope flush', function () {
    expect(Event::hasListeners(Illuminate\Auth\Events\Login::class))->toBeTrue();
});

it('every event implements ShouldDispatchAfterCommit', function (string $eventClass) {
    // §22.1a/§22.2 — after-commit delivery for events dispatched from
    // inside a service transaction.
    expect(is_subclass_of($eventClass, Illuminate\Contracts\Events\ShouldDispatchAfterCommit::class))
        ->toBeTrue("{$eventClass} does not implement ShouldDispatchAfterCommit.");
})->with([
    App\Events\TransferConfirmed::class,
    App\Events\TransferCancelled::class,
    App\Events\TransferDispatched::class,
    App\Events\TransferReceived::class,
    App\Events\LossRecorded::class,
    App\Events\PurchaseOrderReceived::class,
    App\Events\PurchaseOrderCancelled::class,
    App\Events\SalesOrderDispatched::class,
    App\Events\InventoryBelowReorderPoint::class,
]);

it('every listener implements ShouldQueue', function (string $listenerClass) {
    // §22.4 — notification sends are queued; stock mutations remain sync.
    expect(is_subclass_of($listenerClass, Illuminate\Contracts\Queue\ShouldQueue::class))
        ->toBeTrue("{$listenerClass} does not implement ShouldQueue.");
})->with([
    App\Listeners\NotifyTransferConfirmed::class,
    App\Listeners\NotifyTransferCancelled::class,
    App\Listeners\NotifyTransferDispatched::class,
    App\Listeners\NotifyTransferReceived::class,
    App\Listeners\NotifyLossRecorded::class,
    App\Listeners\NotifyPurchaseOrderReceived::class,
    App\Listeners\NotifyPurchaseOrderCancelled::class,
    App\Listeners\NotifySalesOrderDispatched::class,
    App\Listeners\NotifyInventoryBelowReorderPoint::class,
]);

it('every provider is registered in bootstrap/providers.php', function (string $providerClass) {
    $providers = require base_path('bootstrap/providers.php');

    expect($providers)->toContain($providerClass);
})->with([
    App\Providers\AppServiceProvider::class,
    App\Providers\InventoryServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
]);

it('does not register legacy providers (AuthServiceProvider, EventServiceProvider)', function () {
    // §17.2 (Laravel 13 slim skeleton): policy registration and event
    // wiring live in AppServiceProvider::boot(), not dedicated providers.
    $providers = require base_path('bootstrap/providers.php');

    expect($providers)->not->toContain(App\Providers\AuthServiceProvider::class);
    expect($providers)->not->toContain(App\Providers\EventServiceProvider::class);
});

it('registers the currency config key', function () {
    // Phase 00.6 — required for every ->money(config('app.currency')) call.
    expect(config('app.currency'))->toBeString()->not->toBe('');
});
