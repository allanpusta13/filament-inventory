<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\GuardsOutstandingQuantity;
use App\Services\InventoryService;
use App\Services\NegotiationService;
use App\Services\PurchaseService;
use App\Services\SalesService;
use App\Services\TransferRequisitionService;
use Illuminate\Support\ServiceProvider;

/**
 * InventoryServiceProvider — domain service container bindings.
 *
 * Blueprint §17.1. Registers the six transactional domain services
 * that back the inventory, procurement, sales, and requisition
 * workflows. Each is bound as a plain `bind()` so a fresh instance is
 * resolved per request — none of the services carry request-scoped
 * state that would benefit from `singleton()`, and `InventoryService`
 * in particular reads `auth()->id()` inside every mutating method
 * (§6.2), which would be wrong under a long-lived singleton.
 *
 * No service may depend on a controller, Filament page, Livewire
 * component, or HTTP request object (§17.1). Each resolves its own
 * request actor via `auth()` when it needs one, and everything else
 * comes through constructor injection from the container.
 *
 * Registration order does not matter — none of these services is
 * resolved by another during `register()`. `PurchaseService` and
 * `SalesService` inject `GuardsOutstandingQuantity` at resolution
 * time, which the container auto-wires because the guard has no
 * constructor dependencies.
 */
class InventoryServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * The `bindings` property is the Laravel 11+ canonical way to
     * declare a flat list of `bind()` calls. Equivalent to calling
     * `$this->app->bind(...)` six times in a `register()` method.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        GuardsOutstandingQuantity::class => GuardsOutstandingQuantity::class,
        InventoryService::class => InventoryService::class,
        NegotiationService::class => NegotiationService::class,
        TransferRequisitionService::class => TransferRequisitionService::class,
        PurchaseService::class => PurchaseService::class,
        SalesService::class => SalesService::class,
    ];

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // No boot-time behavior. All six services are stateless w.r.t.
        // application boot — they are resolved on demand from actions,
        // policies, and tests.
    }
}
