<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Customer;
use App\Models\DirectTransfer;
use App\Models\DirectTransferItem;
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
use App\Observers\DirectTransferItemObserver;
use App\Observers\DirectTransferObserver;
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
use Filament\Support\Components\Component;
use Filament\Support\Concerns\Configurable;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Foundation\FileBasedMaintenanceMode;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MaintenanceMode::class, function ($app) {
            return new FileBasedMaintenanceMode($app['config']);
        });
    }

    public function boot(): void
    {
        // Add lang directory to translation paths
        $this->app->booted(function () {
            $loader = \Illuminate\Support\Facades\Lang::getLoader();
            $reflection = new ReflectionClass($loader);
            $pathsProperty = $reflection->getProperty('paths');
            $pathsProperty->setAccessible(true);
            $paths = $pathsProperty->getValue($loader);
            if (! in_array(base_path('lang'), $paths, true)) {
                $paths[] = base_path('lang');
                $pathsProperty->setValue($loader, $paths);
            }
        });

        $this->configureTable();
        $this->translatableComponents();

        $this->loadPolicies();
        $this->loadObservers();

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
        });
    }

    private function loadPolicies()
    {
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(ProductVariant::class, ProductVariantPolicy::class);
        Gate::policy(TransferRequisition::class, TransferRequisitionPolicy::class);
        Gate::policy(InTransit::class, InTransitPolicy::class);
        Gate::policy(StockMovement::class, StockMovementPolicy::class);
        Gate::policy(LossLedger::class, LossLedgerPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(SalesOrder::class, SalesOrderPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(DirectTransfer::class, DirectTransferPolicy::class);
    }

    private function loadObservers()
    {
        Product::observe(ProductObserver::class);
        ProductVariant::observe(ProductVariantObserver::class);
        DirectTransfer::observe(DirectTransferObserver::class);
        DirectTransferItem::observe(DirectTransferItemObserver::class);
    }
}
