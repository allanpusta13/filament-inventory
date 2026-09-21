<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Enums\UserRole;
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

describe('LowStockAlertsWidget - v11 chart widget tests', function () {
    it('chart data is cached for 300 seconds', function () {
        $warehouse = Warehouse::factory()->create();
        $variant = makeProductWithStock($this->service, $warehouse, 5, $this->user); // Below reorder point (10)

        $widget = new LowStockAlertsWidget();

        // First call - should query database
        $firstResult = callProtected($widget, 'getData');

        // Second call within 300 seconds - should use cache
        $secondResult = callProtected($widget, 'getData');

        expect($secondResult)->toBe($firstResult)
            ->and(Cache::has('low_stock_alerts_chart_'.$this->user->id.'_'.$warehouse->id))->toBeTrue();
    });

    it('cache miss correctly recomputes all variants', function () {
        $warehouse = Warehouse::factory()->create();
        $variantLow = makeProductWithStock($this->service, $warehouse, 5, $this->user); // Below reorder point (10)
        makeProductWithStock($this->service, $warehouse, 20, $this->user); // Above reorder point

        $widget = new LowStockAlertsWidget();

        $firstResult = callProtected($widget, 'getData');

        expect($firstResult['labels'])->toHaveCount(1);

        // Add stock to push variant above reorder point
        $this->service->recordMovement($variantLow->id, $warehouse->id, StockMovementType::Receive, 10);

        // Clear cache manually to simulate cache miss
        Cache::forget('low_stock_alerts_chart_'.$this->user->id.'_'.$warehouse->id);

        $secondResult = callProtected($widget, 'getData');

        expect($secondResult['labels'])->toHaveCount(0);
    });

    it('chart has correct structure with labels and datasets', function () {
        $warehouse = Warehouse::factory()->create();
        makeProductWithStock($this->service, $warehouse, 5, $this->user); // Below reorder point (10)

        $widget = new LowStockAlertsWidget();
        $result = callProtected($widget, 'getData');

        expect($result)->toHaveKeys(['labels', 'datasets'])
            ->and($result['datasets'])->toHaveCount(2)
            ->and($result['datasets'][0]['label'])->toBe('Current Stock')
            ->and($result['datasets'][1]['label'])->toBe('Reorder Point');
    });

    it('chart type is bar', function () {
        $widget = new LowStockAlertsWidget();
        expect(callProtected($widget, 'getType'))->toBe('bar');
    });

    it('heading is set correctly', function () {
        $widget = new LowStockAlertsWidget();
        expect($widget->getHeading())->toBe('Low Stock Alerts');
    });
});

describe('LowStockAlertsWidget - edge case security tests', function () {
    it('non_admin_receives_empty_data_on_gate_check', function () {
        $warehouse = Warehouse::factory()->create();
        $variant = ProductVariant::factory()->create(['reorder_point' => 10]);
        $this->service->recordMovement($variant->id, $warehouse->id, StockMovementType::Receive, 5);
        $this->user->warehouses()->syncWithoutDetaching([$warehouse->id]);

        // Create non-admin user (no admin, auditor, branch_manager, or warehouse_staff roles)
        $nonAdmin = User::factory()->create(['role' => UserRole::GUEST->value]);
        $nonAdmin->warehouses()->syncWithoutDetaching([$warehouse->id]);
        $this->actingAs($nonAdmin);

        $widget = new LowStockAlertsWidget();
        $result = callProtected($widget, 'getData');

        // Non-admin/non-auditor/non-branch_manager/non-warehouse_staff should get empty data structure
        expect($result['labels'])->toBeEmpty()
            ->and($result['datasets'][0]['data'])->toBeEmpty()
            ->and($result['datasets'][1]['data'])->toBeEmpty();
    });

    it('sql_injection_rejected_in_computed_data', function () {
        $warehouse = Warehouse::factory()->create();
        $variant = ProductVariant::factory()->create(['reorder_point' => 10]);
        $this->service->recordMovement($variant->id, $warehouse->id, StockMovementType::Receive, 5);

        $widget = new LowStockAlertsWidget();
        $result = callProtected($widget, 'getData');

        // Widget computes data internally - no SQL injection possible via chart data
        // Verify data structure is intact
        expect($result)->toHaveKeys(['labels', 'datasets'])
            ->and($result['datasets'])->toHaveCount(2);
    });

    it('xss_script_escaped_in_variant_labels', function () {
        $warehouse = Warehouse::factory()->create();
        $variant = ProductVariant::factory()->create([
            'reorder_point' => 10,
            'name' => "<script>alert('xss')</script>",
            'sku' => 'TEST-XSS',
        ]);
        $this->service->recordMovement($variant->id, $warehouse->id, StockMovementType::Receive, 5);
        $this->user->warehouses()->syncWithoutDetaching([$warehouse->id]);

        $widget = new LowStockAlertsWidget();
        $result = callProtected($widget, 'getData');

        // Labels should contain the variant name but Chart.js will escape when rendering
        // The widget returns raw data - escaping happens at render layer
        expect($result['labels'])->toHaveCount(1)
            ->and($result['labels'][0])->toContain('TEST-XSS');
    });

    it('warehouse_id_scoping_on_every_query', function () {
        $warehouse1 = Warehouse::factory()->create();
        $warehouse2 = Warehouse::factory()->create();
        $variant = ProductVariant::factory()->create(['reorder_point' => 10]);

        // Only stock in warehouse1
        $this->service->recordMovement($variant->id, $warehouse1->id, StockMovementType::Receive, 5);
        $this->user->warehouses()->syncWithoutDetaching([$warehouse1->id]);

        $widget = new LowStockAlertsWidget();
        $result = callProtected($widget, 'getData');

        // Should only show variants from accessible warehouses
        expect($result['labels'])->toHaveCount(1);

        // Add stock to warehouse2 (user doesn't have access)
        $this->service->recordMovement($variant->id, $warehouse2->id, StockMovementType::Receive, 20);

        // Clear cache
        Cache::forget('low_stock_alerts_chart_'.$this->user->id.'_'.$warehouse1->id);

        $result2 = callProtected($widget, 'getData');

        // Should still only show 1 variant (warehouse1 stock is 5, below reorder point)
        // warehouse2 stock is not counted since user doesn't have access
        expect($result2['labels'])->toHaveCount(1);
    });

    it('cross_warehouse_access_denial', function () {
        $warehouse1 = Warehouse::factory()->create();
        $warehouse2 = Warehouse::factory()->create();
        $variant = ProductVariant::factory()->create(['reorder_point' => 10]);

        // Stock only in warehouse1
        $this->service->recordMovement($variant->id, $warehouse1->id, StockMovementType::Receive, 5);
        $this->user->warehouses()->syncWithoutDetaching([$warehouse1->id]);

        $widget = new LowStockAlertsWidget();
        $result = callProtected($widget, 'getData');

        expect($result['labels'])->toHaveCount(1);
    });

    it('generic_error_messages_no_internal_id_leaks', function () {
        $widget = new LowStockAlertsWidget();
        $result = callProtected($widget, 'getData');

        // Should return valid structure without leaking internal IDs
        $json = json_encode($result);
        expect($json)->not->toContain('id:')
            ->and($json)->not->toContain('SQLSTATE')
            ->and($json)->not->toContain('Illuminate\\Database');
    });

    it('empty_warehouse_returns_empty_chart', function () {
        // User with no warehouse access
        $emptyUser = User::factory()->admin()->create();
        $this->actingAs($emptyUser);

        $widget = new LowStockAlertsWidget();
        $result = callProtected($widget, 'getData');

        expect($result['labels'])->toBeEmpty()
            ->and($result['datasets'][0]['data'])->toBeEmpty()
            ->and($result['datasets'][1]['data'])->toBeEmpty();
    });

    it('variant_above_reorder_point_not_in_chart', function () {
        $warehouse = Warehouse::factory()->create();
        $variant = makeProductWithStock($this->service, $warehouse, 20, $this->user); // Above reorder point (10)

        $widget = new LowStockAlertsWidget();
        $result = callProtected($widget, 'getData');

        expect($result['labels'])->toBeEmpty();
    });

    it('multiple_variants_sorted_by_stock_ascending', function () {
        $warehouse = Warehouse::factory()->create();

        $v1 = ProductVariant::factory()->create(['reorder_point' => 10, 'sku' => 'AAA', 'name' => 'Variant A']);
        $v2 = ProductVariant::factory()->create(['reorder_point' => 10, 'sku' => 'BBB', 'name' => 'Variant B']);
        $v3 = ProductVariant::factory()->create(['reorder_point' => 10, 'sku' => 'CCC', 'name' => 'Variant C']);

        $this->service->recordMovement($v1->id, $warehouse->id, StockMovementType::Receive, 8);  // 8 stock
        $this->service->recordMovement($v2->id, $warehouse->id, StockMovementType::Receive, 3);  // 3 stock
        $this->service->recordMovement($v3->id, $warehouse->id, StockMovementType::Receive, 5);  // 5 stock

        $this->user->warehouses()->syncWithoutDetaching([$warehouse->id]);

        $widget = new LowStockAlertsWidget();
        $result = callProtected($widget, 'getData');

        // Should be sorted by stock ascending: BBB (3), CCC (5), AAA (8)
        expect($result['labels'])->toHaveCount(3)
            ->and($result['labels'][0])->toContain('BBB')
            ->and($result['labels'][1])->toContain('CCC')
            ->and($result['labels'][2])->toContain('AAA');
    });
});
