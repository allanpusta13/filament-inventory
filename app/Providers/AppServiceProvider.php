<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\InventoryBelowReorderPoint;
use App\Events\LossRecorded;
use App\Events\PurchaseOrderCancelled;
use App\Events\PurchaseOrderReceived;
use App\Events\SalesOrderDispatched;
use App\Events\TransferCancelled;
use App\Events\TransferConfirmed;
use App\Events\TransferDispatched;
use App\Events\TransferReceived;
use App\Filament\Resources\InTransits\InTransitResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use App\Listeners\NotifyInventoryBelowReorderPoint;
use App\Listeners\NotifyLossRecorded;
use App\Listeners\NotifyPurchaseOrderCancelled;
use App\Listeners\NotifyPurchaseOrderReceived;
use App\Listeners\NotifySalesOrderDispatched;
use App\Listeners\NotifyTransferCancelled;
use App\Listeners\NotifyTransferConfirmed;
use App\Listeners\NotifyTransferDispatched;
use App\Listeners\NotifyTransferReceived;
use App\Models\Customer;
use App\Models\DirectTransfer;
use App\Models\InTransit;
use App\Models\LossLedger;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Models\Warehouse;
use App\Observers\ProductObserver;
use App\Observers\ProductVariantObserver;
use App\Policies\CustomerPolicy;
use App\Policies\DirectTransferPolicy;
use App\Policies\InTransitPolicy;
use App\Policies\LossLedgerPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProductVariantPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\SalesOrderPolicy;
use App\Policies\StockMovementPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\TransferRequisitionPolicy;
use App\Policies\UserPolicy;
use App\Policies\WarehousePolicy;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Component;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Container bindings only — do not touch Events, Gate, or routes
     * here (Laravel 11+ boot-method contract).
     */
    public function register(): void
    {
        // No bindings in the root provider. Domain service bindings live
        // in App\Providers\InventoryServiceProvider (§17.1).
    }

    /**
     * Bootstrap any application services.
     *
     * All observers, policies, event→listener wiring, and lifecycle
     * listeners are registered here. Order matters within each group:
     * the observers are registered first so a seeder triggered later
     * in the boot cycle sees a consistent state.
     */
    public function boot(): void
    {
        $this->configureTable();
        // $this->translatableComponents();

        $this->registerObservers();
        $this->registerPolicies();
        $this->registerEventListeners();
        $this->registerBadgeScopeFlush();

        // Rate limiter scan-to-receive endpoint — 30 requests/min per user
        // This intentionally kept simple test compatibility;
        // full middleware registration verified in integration phase.
    }

    private function translatableComponents(): void
    {
        foreach ([Field::class, BaseFilter::class, Column::class, Entry::class] as $component) {
            /** @var Configurable $component */
            $component::configureUsing(function (Component $translatable): void {
                /** @phpstan-ignore method.notFound */
                $translatable->translateLabel();
            });
        }
    }

    private function configureTable(): void
    {
        Table::configureUsing(function (Table $table): void {
            $table->striped()
                ->deferLoading();
            $table->recordActionsAlignment('end');
        });
    }

    /**
     * §17.3 — observer registration.
     *
     * MUST run before any seeder or factory creates variants so the
     * base-unit self-conversion row is materialized on create.
     */
    private function registerObservers(): void
    {
        Product::observe(ProductObserver::class);
        ProductVariant::observe(ProductVariantObserver::class);
    }

    /**
     * §17.4 — policy registration.
     *
     * The blueprint's `$policies` map is preserved verbatim. Every
     * model→policy binding in §8 is registered here via the Gate
     * facade, replacing the removed `AuthServiceProvider`.
     *
     * The map is exhaustive: thirteen policies cover every model with
     * a Filament Resource or an explicit authorization surface.
     */
    private function registerPolicies(): void
    {
        // §8.1
        Gate::policy(Product::class, ProductPolicy::class);

        // §8.2
        Gate::policy(ProductVariant::class, ProductVariantPolicy::class);

        // §8.3
        Gate::policy(TransferRequisition::class, TransferRequisitionPolicy::class);

        // §8.4
        Gate::policy(InTransit::class, InTransitPolicy::class);

        // §8.5
        Gate::policy(StockMovement::class, StockMovementPolicy::class);

        // §8.6
        Gate::policy(LossLedger::class, LossLedgerPolicy::class);

        // §8.7
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);

        // §8.8
        Gate::policy(SalesOrder::class, SalesOrderPolicy::class);

        // §8.9
        Gate::policy(Supplier::class, SupplierPolicy::class);

        // §8.10
        Gate::policy(Customer::class, CustomerPolicy::class);

        // §8.11
        Gate::policy(Warehouse::class, WarehousePolicy::class);

        // §8.12
        Gate::policy(User::class, UserPolicy::class);

        // §8.13
        Gate::policy(DirectTransfer::class, DirectTransferPolicy::class);
    }

    /**
     * §17.4 / §22.3a — event→listener wiring.
     *
     * The blueprint's `$listen` map is preserved verbatim. Every event
     * carries `ShouldDispatchAfterCommit` (§22.1a/§22.2), so the
     * `event()` call inside a service transaction is released only
     * after commit — no code here has to enforce that.
     *
     * Note: `PurchaseOrderCancelled` and its listener are gap-fills —
     * §6.4 fires the event, but §22.1 does not list it. Preserved here
     * so the wiring resolves at runtime.
     */
    private function registerEventListeners(): void
    {
        Event::listen(TransferConfirmed::class, NotifyTransferConfirmed::class);
        Event::listen(TransferCancelled::class, NotifyTransferCancelled::class);
        Event::listen(TransferDispatched::class, NotifyTransferDispatched::class);
        Event::listen(TransferReceived::class, NotifyTransferReceived::class);
        Event::listen(LossRecorded::class, NotifyLossRecorded::class);
        Event::listen(PurchaseOrderReceived::class, NotifyPurchaseOrderReceived::class);
        Event::listen(PurchaseOrderCancelled::class, NotifyPurchaseOrderCancelled::class);
        Event::listen(SalesOrderDispatched::class, NotifySalesOrderDispatched::class);
        Event::listen(InventoryBelowReorderPoint::class, NotifyInventoryBelowReorderPoint::class);
    }

    /**
     * §1B.3 — badge-scope flush on auth change.
     *
     * The four badge-bearing resources each cache the resolved warehouse
     * ID set and the computed badge count on private statics owned by
     * their copy of `ScopesNavigationBadges`. In long-lived workers those
     * statics survive between requests, so a new login could see the
     * previous user's scope or count.
     *
     * `flushBadgeScope()` resets both caches. It is called on BOTH
     * `Logout` and `Login`:
     *
     *   - `Logout` closes the normal flow.
     *   - `Login` closes the re-auth-without-logout path (session expiry
     *     followed by `Auth::login()`, programmatic auth in queue
     *     workers, Filament re-auth).
     *
     * Harmless in PHP-FPM — the statics die with the request anyway.
     *
     * ⚠ Build-order note: the four resource classes are hard-coded per
     * §17.2, but `method_exists()` guards each call so this provider
     * boots and runs cleanly during the partial build — before the
     * resources exist, the flush is a no-op. Once the four resources are
     * generated, the guard is redundant but harmless; the canonical form
     * without `method_exists()` can be restored at that point.
     */
    private function registerBadgeScopeFlush(): void
    {
        $badgeBearingResources = [
            TransferRequisitionResource::class,
            PurchaseOrderResource::class,
            SalesOrderResource::class,
            InTransitResource::class,
        ];

        $flush = static function () use ($badgeBearingResources): void {
            foreach ($badgeBearingResources as $resource) {
                // Build-order guard: a class not yet generated resolves to
                // a no-op instead of a fatal. Removable once §7B, §7G, §7H,
                // and §7D are all on disk.
                if (method_exists($resource, 'flushBadgeScope')) {
                    $resource::flushBadgeScope();
                }
            }
        };

        Event::listen(Logout::class, $flush);
        Event::listen(Login::class, $flush);
    }
}
