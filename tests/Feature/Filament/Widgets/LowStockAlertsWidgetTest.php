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

describe('LowStockAlertsWidget - edge case security tests', function () {
    it('non_admin_receives_403_on_gate_check', function () {
        $nonAdmin = User::factory()->create();
        $response = $this->actingAs($nonAdmin)
            ->get('/widgets/low-stock-alerts');

        $response->assertStatus(403);
    });

    it('sql_injection_rejected_in_warehouse_search', function () {
        $warehouse = Warehouse::factory()->create();
        $variant = ProductVariant::factory()->create(['reorder_point' => 10]);

        $response = $this->actingAs($this->user)
            ->getJson('/widgets/low-stock-alerts', [
                'search' => "'; DROP TABLE warehouses; --",
            ]);

        $response->assertOk();
        $this->assertDatabaseMissing('product_variants', ['id' => $variant->id]);
    });

    it('xss_script_rejected_in_variant_names', function () {
        $warehouse = Warehouse::factory()->create();
        $variant = ProductVariant::factory()->create([
            'reorder_point' => 10,
            'name' => "<script>alert('xss')</script>",
        ]);

        $this->service->recordMovement($variant->id, $warehouse->id, \App\Enums\StockMovementType::Receive, 5);

        $response = $this->actingAs($this->user)
            ->get('/widgets/low-stock-alerts');

        $response->assertOk();
        $this->assertDontSee('<script>');
    });

    it('warehouse_id_scoping_on_every_query', function () {
        $warehouse1 = Warehouse::factory()->create();
        $warehouse2 = Warehouse::factory()->create();
        $variant = ProductVariant::factory()->create(['reorder_point' => 10]);

        $this->service->recordMovement($variant->id, $warehouse1->id, \App\Enums\StockMovementType::Receive, 5);

        $response = $this->actingAs($this->user)
            ->get('/widgets/low-stock-alerts');

        $response->assertOk();
        $this->assertSee($warehouse1->name);
        $this->assertDontSee($warehouse2->name);
    });

    it('cross_warehouse_access_denial', function () {
        $warehouse1 = Warehouse::factory()->create();
        $warehouse2 = Warehouse::factory()->create();
        $variant = ProductVariant::factory()->create(['reorder_point' => 10]);

        $this->service->recordMovement($variant->id, $warehouse1->id, \App\Enums\StockMovementType::Receive, 5);

        $otherUser = User::factory()->create();
        $response = $this->actingAs($otherUser)
            ->get('/widgets/low-stock-alerts');

        $response->assertForbidden();
    });

    it('null_barcode_in_shortfall_display', function () {
        $warehouse = Warehouse::factory()->create();
        $variant = ProductVariant::factory()->create(['reorder_point' => 10, 'barcode' => null]);

        $this->service->recordMovement($variant->id, $warehouse->id, \App\Enums\StockMovementType::Receive, 5);

        $response = $this->actingAs($this->user)
            ->get('/widgets/low-stock-alerts');

        $response->assertOk();
        $this->assertSee('No barcode');
        $this->assertDontSee(null);
    });

    it('cache_invalidated_on_stock_movement_with_warehouse_scoping', function () {
        $warehouse = Warehouse::factory()->create();
        $variant = ProductVariant::factory()->create(['reorder_point' => 10]);

        $this->service->recordMovement($variant->id, $warehouse->id, \App\Enums\StockMovementType::Receive, 5);

        $widget = new LowStockAlertsWidget();
        $firstResult = $widget->getLowStockAlerts();

        $this->service->recordMovement($variant->id, $warehouse->id, \App\Enums\StockMovementType::Issue, 2);

        Cache::forget('low_stock_alerts_'.$this->user->id.'_'.$warehouse->id);

        $secondResult = $widget->getLowStockAlerts();

        expect($secondResult)->not->toBe($firstResult);
    });

    it('generic_error_messages_no_internal_id_leaks', function () {
        $warehouse = Warehouse::factory()->create();
        $variant = ProductVariant::factory()->create(['reorder_point' => 10]);

        $response = $this->actingAs($this->user)
            ->get('/widgets/low-stock-alerts', [
                'search' => "' OR 1=1 --",
            ]);

        $response->assertOk();
        $this->assertSee('Something went wrong');
        $this->assertDontSee($warehouse->id);
        $this->assertDontSee($variant->id);
    });
});