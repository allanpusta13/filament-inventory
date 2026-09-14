<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Filament\Widgets\LowStockAlertsWidget;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Cache;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->service = new InventoryService();
    $this->user = User::factory()->admin()->create();
    $this->actingAs($this->user);

    // Clear cache before each test
    Cache::flush();
});

function makeProductWithStock(InventoryService $service, Warehouse $warehouse, int $stock, ?User $user = null): ProductVariant
{
    $variant = ProductVariant::factory()->create(['reorder_point' => 10]);
    $service->recordMovement($variant->id, $warehouse->id, StockMovementType::Receive, $stock);
    if ($user) {
        $user->warehouses()->syncWithoutDetaching([$warehouse->id]);
    }

    return $variant;
}

describe('LowStockAlertsWidget - v10 cache tests', function () {
    it('cache_window_prevents_requery_within_300_seconds', function () {
        $warehouse = Warehouse::factory()->create();
        $variant = makeProductWithStock($this->service, $warehouse, 5, $this->user); // Below reorder point (10)

        $widget = new LowStockAlertsWidget();

        // First call - should query database
        $firstResult = $widget->getLowStockAlerts();

        // Second call within 300 seconds - should use cache
        $secondResult = $widget->getLowStockAlerts();

        expect($secondResult)->toBe($firstResult)
            ->and(Cache::has('low_stock_alerts_'.$this->user->id.'_'.$warehouse->id))->toBeTrue();
    });

    it('cache_miss_correctly_recomputes_all_variants', function () {
        $warehouse = Warehouse::factory()->create();
        $variant1 = makeProductWithStock($this->service, $warehouse, 5, $this->user); // Below reorder point
        $variant2 = makeProductWithStock($this->service, $warehouse, 20, $this->user); // Above reorder point

        $widget = new LowStockAlertsWidget();

        // First call - should find 1 low stock variant
        $firstResult = $widget->getLowStockAlerts();
        expect($firstResult->count())->toBe(1);

        // Add stock to variant1 to bring it above reorder point
        $this->service->recordMovement($variant1->id, $warehouse->id, StockMovementType::Receive, 10);
        // Now variant1 has 15, above reorder point of 10

        // Clear cache to force recomputation
        Cache::forget('low_stock_alerts_'.$this->user->id.'_'.$warehouse->id);

        // Second call - should recompute and find 0 low stock variants
        $secondResult = $widget->getLowStockAlerts();
        expect($secondResult->count())->toBe(0);
    });
});
