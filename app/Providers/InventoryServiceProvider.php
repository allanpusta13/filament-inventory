<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\GuardsOutstandingQuantityService;
use App\Services\InventoryService;
use App\Services\NegotiationService;
use App\Services\PurchaseService;
use App\Services\SalesService;
use App\Services\TransferRequisitionService;
use Illuminate\Support\ServiceProvider;

class InventoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(GuardsOutstandingQuantityService::class);
        $this->app->bind(InventoryService::class);
        $this->app->bind(NegotiationService::class);
        $this->app->bind(TransferRequisitionService::class);
        $this->app->bind(PurchaseService::class);
        $this->app->bind(SalesService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
