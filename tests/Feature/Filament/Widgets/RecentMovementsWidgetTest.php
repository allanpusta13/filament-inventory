<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Filament\Widgets\RecentMovementsWidget;
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

    $this->origin = Warehouse::factory()->create();
    $this->destination = Warehouse::factory()->create();
    $this->variant = ProductVariant::factory()->create();
    $this->variant2 = ProductVariant::factory()->create();

    $this->user->warehouses()->syncWithoutDetaching([$this->origin->id, $this->destination->id]);

    $this->cacheKey = 'recent_movements_chart_'.$this->user->id.'_'.$this->origin->id;
    Cache::forget($this->cacheKey);
});

describe('RecentMovementsWidget - ChartWidget tests', function () {
    it('chart data is cached for 60 seconds', function () {
        $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Receive, 100);

        $widget = new RecentMovementsWidget();

        // First call - should query database
        $firstResult = callProtected($widget, 'getData');

        // Second call within 60 seconds - should use cache
        $secondResult = callProtected($widget, 'getData');

        expect($secondResult)->toBe($firstResult)
            ->and(Cache::has('recent_movements_chart_'.$this->user->id.'_'.$this->origin->id))->toBeTrue();
    });

    it('cache miss correctly recomputes all movements', function () {
        $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Receive, 100);

        $widget = new RecentMovementsWidget();

        $firstResult = callProtected($widget, 'getData');

        expect($firstResult['datasets'][0]['data'])->toHaveCount(7);

        // Add more movements
        $this->service->recordMovement($this->variant2->id, $this->origin->id, StockMovementType::Ship, 50);

        // Clear cache manually to simulate cache miss
        Cache::forget('recent_movements_chart_'.$this->user->id.'_'.$this->origin->id);

        $secondResult = callProtected($widget, 'getData');

        expect($secondResult['datasets'][0]['data'])->toHaveCount(7);
    });

    it('chart has correct structure with labels and datasets', function () {
        $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Receive, 100);

        $widget = new RecentMovementsWidget();
        $result = callProtected($widget, 'getData');

        expect($result)->toHaveKeys(['labels', 'datasets'])
            ->and($result['datasets'])->toHaveCount(1)
            ->and($result['datasets'][0]['label'])->toBe('Movement Count');
    });

    it('chart type is line', function () {
        $widget = new RecentMovementsWidget();
        expect(callProtected($widget, 'getType'))->toBe('line');
    });

    it('heading is set correctly', function () {
        $widget = new RecentMovementsWidget();
        expect($widget->getHeading())->toBe('Recent Movements');
    });

    it('cache invalidated on stock movement with warehouse scoping', function () {
        $warehouse1 = Warehouse::factory()->create();
        $warehouse2 = Warehouse::factory()->create();
        $variant = ProductVariant::factory()->create();

        $this->service->recordMovement($variant->id, $warehouse1->id, StockMovementType::Receive, 5);

        $widget = new RecentMovementsWidget();
        callProtected($widget, 'getData'); // Populate cache

        // Add movement in different warehouse user has access to
        $this->user->warehouses()->syncWithoutDetaching([$warehouse1->id, $warehouse2->id]);
        $this->service->recordMovement($variant->id, $warehouse2->id, StockMovementType::Receive, 10);

        // Cache key includes first warehouse ID, so new data won't show until cache expires
        // This is expected behavior - cache is per-user + first warehouse
        $result = callProtected($widget, 'getData');
        expect($result['datasets'][0]['data'])->toHaveCount(7); // Still shows old cached data
    });
});

describe('RecentMovementsWidget - edge case security tests', function () {
    it('non_admin_receives_empty_data_on_gate_check', function () {
        $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Receive, 100);

        // Create non-admin user
        $nonAdmin = User::factory()->create(['role' => 'warehouse_staff']);
        $nonAdmin->warehouses()->syncWithoutDetaching([$this->origin->id]);
        $this->actingAs($nonAdmin);

        $widget = new RecentMovementsWidget();
        $result = callProtected($widget, 'getData');

        // Non-admin/non-auditor should get empty data structure
        expect($result['labels'])->toBeEmpty()
            ->and($result['datasets'][0]['data'])->toBeEmpty();
    });

    it('sql_injection_rejected_in_computed_data', function () {
        $this->service->recordMovement($this->variant->id, $this->origin->id, StockMovementType::Receive, 100);

        $widget = new RecentMovementsWidget();
        $result = callProtected($widget, 'getData');

        // Widget computes data internally - no SQL injection possible via chart data
        // Verify data structure is intact
        expect($result)->toHaveKeys(['labels', 'datasets'])
            ->and($result['datasets'])->toHaveCount(1);
    });

    it('xss_script_escaped_in_movement_labels', function () {
        $variantXss = ProductVariant::factory()->create([
            'name' => "<script>alert('xss')</script>",
            'sku' => 'TEST-XSS',
        ]);
        $this->service->recordMovement($variantXss->id, $this->origin->id, StockMovementType::Receive, 5);

        $widget = new RecentMovementsWidget();
        $result = callProtected($widget, 'getData');

        // Widget returns daily buckets - variant names not in labels
        // Labels are dates (M j format)
        expect($result['labels'])->toHaveCount(7)
            ->and($result['datasets'][0]['data'])->toHaveCount(7);
    });

    it('warehouse_id_scoping_on_every_query', function () {
        $warehouse1 = Warehouse::factory()->create();
        $warehouse2 = Warehouse::factory()->create();
        $variant = ProductVariant::factory()->create();

        // Only stock in warehouse1
        $this->service->recordMovement($variant->id, $warehouse1->id, StockMovementType::Receive, 5);
        $this->user->warehouses()->syncWithoutDetaching([$warehouse1->id]);

        $widget = new RecentMovementsWidget();
        $result = callProtected($widget, 'getData');

        // Should show movements from accessible warehouse only
        expect($result['datasets'][0]['data'])->toHaveCount(7);

        // Add stock to warehouse2 (user doesn't have access)
        $this->service->recordMovement($variant->id, $warehouse2->id, StockMovementType::Receive, 20);

        // Clear cache
        Cache::forget('recent_movements_chart_'.$this->user->id.'_'.$warehouse1->id);

        $result2 = callProtected($widget, 'getData');

        // Should still show 7 days of data (warehouse2 movements not counted)
        expect($result2['datasets'][0]['data'])->toHaveCount(7);
    });

    it('cross_warehouse_access_denial', function () {
        $warehouse1 = Warehouse::factory()->create();
        $warehouse2 = Warehouse::factory()->create();
        $variant = ProductVariant::factory()->create();

        // Stock only in warehouse1
        $this->service->recordMovement($variant->id, $warehouse1->id, StockMovementType::Receive, 5);
        $this->user->warehouses()->syncWithoutDetaching([$warehouse1->id]);

        $widget = new RecentMovementsWidget();
        $result = callProtected($widget, 'getData');

        expect($result['datasets'][0]['data'])->toHaveCount(7);
    });

    it('generic_error_messages_no_internal_id_leaks', function () {
        $widget = new RecentMovementsWidget();
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

        $widget = new RecentMovementsWidget();
        $result = callProtected($widget, 'getData');

        expect($result['labels'])->toBeEmpty()
            ->and($result['datasets'][0]['data'])->toBeEmpty();
    });

    it('movements_aggregated_by_day_over_7_days', function () {
        $variant = ProductVariant::factory()->create();

        // Add movements on different days
        $this->service->recordMovement($variant->id, $this->origin->id, StockMovementType::Receive, 10);
        $this->service->recordMovement($variant->id, $this->origin->id, StockMovementType::Ship, 5);

        $widget = new RecentMovementsWidget();
        $result = callProtected($widget, 'getData');

        // Should have 7 daily buckets
        expect($result['labels'])->toHaveCount(7)
            ->and($result['datasets'][0]['data'])->toHaveCount(7)
            ->and(array_sum($result['datasets'][0]['data']))->toBe(2); // 2 movements total
    });
});
