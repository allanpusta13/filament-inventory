<?php

declare(strict_types=1);

/**
 * §23.2 — Resource Completeness.
 *
 * Every Resource and Page class named in §18.2 must be autoloadable.
 * Missing classes are the failure mode that hides as "route not found"
 * in production.
 */

// ---------------------------------------------------------------------------
// Resources — 12 classes
// ---------------------------------------------------------------------------

it('autoloads every Filament Resource class', function (string $class) {
    expect(class_exists($class))->toBeTrue(
        "Resource class {$class} does not exist."
    );
    expect(is_subclass_of($class, Filament\Resources\Resource::class))->toBeTrue();
})->with([
    App\Filament\Resources\Products\ProductResource::class,
    App\Filament\Resources\TransferRequisitions\TransferRequisitionResource::class,
    App\Filament\Resources\DirectTransfers\DirectTransferResource::class,
    App\Filament\Resources\InTransits\InTransitResource::class,
    App\Filament\Resources\StockMovements\StockMovementResource::class,
    App\Filament\Resources\LossLedgers\LossLedgerResource::class,
    App\Filament\Resources\Warehouses\WarehouseResource::class,
    App\Filament\Resources\Users\UserResource::class,
    App\Filament\Resources\PurchaseOrders\PurchaseOrderResource::class,
    App\Filament\Resources\SalesOrders\SalesOrderResource::class,
    App\Filament\Resources\Suppliers\SupplierResource::class,
    App\Filament\Resources\Customers\CustomerResource::class,
]);

// ---------------------------------------------------------------------------
// Pages — every Page referenced by a Resource's getPages()
// ---------------------------------------------------------------------------

it('autoloads every Filament Page referenced by a resource', function (string $resourceClass) {
    $pages = $resourceClass::getPages();

    foreach ($pages as $key => $registration) {
        $pageClass = $registration->getPage();

        expect(class_exists($pageClass))->toBeTrue(
            "Page '{$key}' for {$resourceClass} does not exist: {$pageClass}"
        );

        expect(is_subclass_of($pageClass, Filament\Resources\Pages\Page::class))
            ->toBeTrue("{$pageClass} is not a Filament Page.");
    }
})->with([
    App\Filament\Resources\Products\ProductResource::class,
    App\Filament\Resources\TransferRequisitions\TransferRequisitionResource::class,
    App\Filament\Resources\DirectTransfers\DirectTransferResource::class,
    App\Filament\Resources\InTransits\InTransitResource::class,
    App\Filament\Resources\StockMovements\StockMovementResource::class,
    App\Filament\Resources\LossLedgers\LossLedgerResource::class,
    App\Filament\Resources\Warehouses\WarehouseResource::class,
    App\Filament\Resources\Users\UserResource::class,
    App\Filament\Resources\PurchaseOrders\PurchaseOrderResource::class,
    App\Filament\Resources\SalesOrders\SalesOrderResource::class,
    App\Filament\Resources\Suppliers\SupplierResource::class,
    App\Filament\Resources\Customers\CustomerResource::class,
]);

// ---------------------------------------------------------------------------
// Widgets — 9 classes
// ---------------------------------------------------------------------------

it('autoloads every dashboard widget', function (string $class) {
    expect(class_exists($class))->toBeTrue(
        "Widget {$class} does not exist."
    );
    expect(is_subclass_of($class, Filament\Widgets\Widget::class))->toBeTrue();
})->with([
    App\Filament\Widgets\StatsOverviewWidget::class,
    App\Filament\Widgets\LowStockAlertsWidget::class,
    App\Filament\Widgets\RecentMovementsWidget::class,
    App\Filament\Widgets\SalesRevenueTrendWidget::class,
    App\Filament\Widgets\ActiveInTransitWidget::class,
    App\Filament\Widgets\SalesVsPurchasesWidget::class,
    App\Filament\Widgets\TopSellingVariantsWidget::class,
    App\Filament\Widgets\PendingFulfillmentWidget::class,
    App\Filament\Widgets\QuickActionsWidget::class,
]);

// ---------------------------------------------------------------------------
// Support classes
// ---------------------------------------------------------------------------

it('autoloads the shared Filament support classes', function (string $class) {
    expect(class_exists($class) || trait_exists($class))->toBeTrue();
})->with([
    App\Filament\Support\Concerns\ScopesNavigationBadges::class,
    App\Filament\Support\Filters\AdminReviewFilters::class,
    // App\Filament\Support\Wizards\WizardReviewStep::class,
]);

// ---------------------------------------------------------------------------
// Blade views referenced by widgets and wizards
// ---------------------------------------------------------------------------

it('declares the quick-actions blade view referenced by QuickActionsWidget', function () {
    expect(view()->exists('filament.widgets.quick-actions'))->toBeTrue();
});

it('declares every wizard review blade view', function (string $view) {
    expect(view()->exists($view))->toBeTrue("Blade view {$view} does not exist.");
})->with([
    'filament.wizards.transfer-review',
    'filament.wizards.purchase-order-review',
    'filament.wizards.sales-order-review',
    'filament.wizards.direct-transfer-review',
]);

// ---------------------------------------------------------------------------
// STN views
// ---------------------------------------------------------------------------

it('declares the STN print and scan blade views', function (string $view) {
    expect(view()->exists($view))->toBeTrue();
})->with([
    'stn.print',
    'stn.scan',
    'livewire.stn.scan-form',
]);
