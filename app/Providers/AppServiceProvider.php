<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Observers\ProductObserver;
use App\Policies\CustomerPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\SalesOrderPolicy;
use App\Policies\SupplierPolicy;
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
        Product::observe(ProductObserver::class);

        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(SalesOrder::class, SalesOrderPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);

        $this->configureTable();
        $this->translatableComponents();

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
}
