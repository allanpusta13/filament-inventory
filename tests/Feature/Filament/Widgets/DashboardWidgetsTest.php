<?php

declare(strict_types=1);

use App\Filament\Widgets\ActiveInTransitWidget;
use App\Filament\Widgets\LowStockAlertsWidget;
use App\Filament\Widgets\PendingFulfillmentWidget;
use App\Filament\Widgets\QuickActionsWidget;
use App\Filament\Widgets\RecentMovementsWidget;
use App\Filament\Widgets\SalesRevenueTrendWidget;
use App\Filament\Widgets\SalesVsPurchasesWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\TopSellingVariantsWidget;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\StatsOverviewWidget as FilamentStatsOverviewWidget;
use Filament\Widgets\TableWidget;
use Filament\Widgets\Widget;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Dashboard widget contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * ⚠ Blueprint correction: §10's Widget Definitions table types
 * StatsOverviewWidget as `TableWidget`. Filament v5's canonical
 * StatsOverviewWidget extends `BaseWidget` (the abstract stat-card
 * widget), returns `Stat` instances from `getStats()`, and renders a
 * grid of stat cards — not a table. The tests below assert the
 * Filament v5 contract.
 */
uses(RefreshDatabase::class);

// ===========================================================================
// The nine widget classes
// ===========================================================================

it('declares exactly nine dashboard widgets', function () {
    $expected = [
        StatsOverviewWidget::class,
        LowStockAlertsWidget::class,
        RecentMovementsWidget::class,
        SalesRevenueTrendWidget::class,
        ActiveInTransitWidget::class,
        SalesVsPurchasesWidget::class,
        TopSellingVariantsWidget::class,
        PendingFulfillmentWidget::class,
        QuickActionsWidget::class,
    ];

    foreach ($expected as $class) {
        expect(class_exists($class))->toBeTrue();
    }

    expect($expected)->toHaveCount(9);
});

it('declares the §10 sort order for every widget', function (string $class, int $sort) {
    $reflection = new ReflectionClass($class);
    expect($reflection->getStaticPropertyValue('sort'))->toBe($sort);
})->with([
    [StatsOverviewWidget::class, 1],
    [LowStockAlertsWidget::class, 2],
    [RecentMovementsWidget::class, 3],
    [SalesRevenueTrendWidget::class, 4],
    [ActiveInTransitWidget::class, 5],
    [SalesVsPurchasesWidget::class, 6],
    [TopSellingVariantsWidget::class, 7],
    [PendingFulfillmentWidget::class, 8],
    [QuickActionsWidget::class, 9],
]);

it('declares the §10 column-span shape for every widget', function (string $class, array $span) {
    $instance = new $class();

    $reflection = new ReflectionProperty($class, 'columnSpan');
    $value = $reflection->getValue($instance);

    expect($value)->toBe($span);
})->with([
    [StatsOverviewWidget::class, ['default' => 1, 'md' => 2, 'xl' => 2]],
    [LowStockAlertsWidget::class, ['default' => 1, 'md' => 1, 'xl' => 1]],
    [RecentMovementsWidget::class, ['default' => 1, 'md' => 1, 'xl' => 1]],
    [SalesRevenueTrendWidget::class, ['default' => 1, 'md' => 2, 'xl' => 2]],
    [ActiveInTransitWidget::class, ['default' => 1, 'md' => 2, 'xl' => 2]],
    [SalesVsPurchasesWidget::class, ['default' => 1, 'md' => 2, 'xl' => 2]],
    [TopSellingVariantsWidget::class, ['default' => 1, 'md' => 1, 'xl' => 1]],
    [PendingFulfillmentWidget::class, ['default' => 1, 'md' => 1, 'xl' => 1]],
    [QuickActionsWidget::class, ['default' => 1, 'md' => 2, 'xl' => 4]],
]);

// ===========================================================================
// Base classes
// ===========================================================================

it('extends the correct Filament widget base', function (string $class, string $base) {
    $reflection = new ReflectionClass($class);
    expect($reflection->isSubclassOf($base))->toBeTrue();
})->with([
    // StatsOverviewWidget extends Filament\Widgets\StatsOverviewWidget
    // (aliased as BaseWidget) — NOT TableWidget.
    [StatsOverviewWidget::class, FilamentStatsOverviewWidget::class],
    [LowStockAlertsWidget::class, ChartWidget::class],
    [RecentMovementsWidget::class, ChartWidget::class],
    [SalesRevenueTrendWidget::class, ChartWidget::class],
    [ActiveInTransitWidget::class, TableWidget::class],
    [SalesVsPurchasesWidget::class, ChartWidget::class],
    [TopSellingVariantsWidget::class, ChartWidget::class],
    [PendingFulfillmentWidget::class, TableWidget::class],
    [QuickActionsWidget::class, Widget::class],
]);

it('does not declare StatsOverviewWidget as a TableWidget subclass', function () {
    // Regression guard: a future refactor that switched the widget to
    // a table would fail here. §10's Type column was mistyped; the
    // Filament v5 contract is stat cards.
    $reflection = new ReflectionClass(StatsOverviewWidget::class);

    expect($reflection->isSubclassOf(TableWidget::class))->toBeFalse();
});

it('declares getStats() on StatsOverviewWidget, not table()', function () {
    $reflection = new ReflectionClass(StatsOverviewWidget::class);

    expect($reflection->hasMethod('getStats'))->toBeTrue();
    expect($reflection->hasMethod('table'))->toBeFalse();
});

it('exposes getStats() as protected', function () {
    $method = new ReflectionMethod(StatsOverviewWidget::class, 'getStats');

    expect($method->isProtected())->toBeTrue();
    expect($method->isStatic())->toBeFalse();
});

// ===========================================================================
// Role-based visibility (§10 Role-Based Widget Visibility table)
// ===========================================================================

it('grants canView() to all authenticated users for globally-visible widgets', function (string $class) {
    $this->actingAs(User::factory()->create());

    expect($class::canView())->toBeTrue();
})->with([
    [StatsOverviewWidget::class],
    [RecentMovementsWidget::class],
    [PendingFulfillmentWidget::class],
    [QuickActionsWidget::class],
]);

it('grants canView() to admin, auditor, and warehouse staff for operational widgets', function (string $class) {
    foreach ([
        User::factory()->admin()->create(),
        User::factory()->auditor()->create(),
        User::factory()->create(),
    ] as $user) {
        $this->actingAs($user);
        expect($class::canView())->toBeTrue();
    }
})->with([
    [LowStockAlertsWidget::class],
    [ActiveInTransitWidget::class],
]);

it('denies canView() to warehouse staff for admin-only widgets', function (string $class) {
    $this->actingAs(User::factory()->create());
    expect($class::canView())->toBeFalse();

    $this->actingAs(User::factory()->auditor()->create());
    expect($class::canView())->toBeTrue();

    $this->actingAs(User::factory()->admin()->create());
    expect($class::canView())->toBeTrue();
})->with([
    [SalesRevenueTrendWidget::class],
    [SalesVsPurchasesWidget::class],
    [TopSellingVariantsWidget::class],
]);

it('denies canView() to the guest user for every widget', function (string $class) {
    auth()->logout();

    expect($class::canView())->toBeFalse();
})->with([
    [StatsOverviewWidget::class],
    [LowStockAlertsWidget::class],
    [RecentMovementsWidget::class],
    [SalesRevenueTrendWidget::class],
    [ActiveInTransitWidget::class],
    [SalesVsPurchasesWidget::class],
    [TopSellingVariantsWidget::class],
    [PendingFulfillmentWidget::class],
    [QuickActionsWidget::class],
]);

// ===========================================================================
// Cache contract — data widgets declare cacheKey + cacheTtl
// ===========================================================================

it('declares cacheTtl and cacheKey for every data widget', function (string $class, int $ttl, string $prefix) {
    $reflection = new ReflectionClass($class);

    expect($reflection->hasMethod('cacheTtl'))->toBeTrue();
    expect($reflection->hasMethod('cacheKey'))->toBeTrue();
    expect($class::cacheTtl())->toBe($ttl);

    $key = $class::cacheKey(42, [3, 1, 2]);

    expect($key)->toStartWith($prefix);
    expect($key)->toContain('42');
})->with([
    [StatsOverviewWidget::class, 300, 'stats_overview_'],
    [LowStockAlertsWidget::class, 300, 'low_stock_alerts_chart_'],
    [RecentMovementsWidget::class, 60, 'recent_movements_chart_'],
    [SalesRevenueTrendWidget::class, 300, 'sales_revenue_trend_'],
    [ActiveInTransitWidget::class, 300, 'active_in_transit_'],
    [SalesVsPurchasesWidget::class, 300, 'sales_vs_purchases_'],
    [PendingFulfillmentWidget::class, 60, 'pending_fulfillment_'],
]);

it('embeds a scopeHash of the sorted warehouse IDs in the cache key', function () {
    $a = StatsOverviewWidget::cacheKey(1, [3, 1, 2]);
    $b = StatsOverviewWidget::cacheKey(1, [2, 3, 1]);

    expect($a)->toBe($b);

    $c = StatsOverviewWidget::cacheKey(1, [3, 1, 2, 4]);

    expect($c)->not->toBe($a);
});

it('does not declare cacheTtl or cacheKey on the static QuickActionsWidget', function () {
    $reflection = new ReflectionClass(QuickActionsWidget::class);

    expect($reflection->hasMethod('cacheTtl'))->toBeFalse();
    expect($reflection->hasMethod('cacheKey'))->toBeFalse();
});

// ===========================================================================
// Chart type — ChartWidget subclasses declare getType
// ===========================================================================

it('declares the correct chart type', function (string $class, string $type) {
    $instance = new $class();
    $method = new ReflectionMethod($class, 'getType');

    expect($method->invoke($instance))->toBe($type);
})->with([
    [LowStockAlertsWidget::class, 'bar'],
    [RecentMovementsWidget::class, 'line'],
    [SalesRevenueTrendWidget::class, 'line'],
    [SalesVsPurchasesWidget::class, 'bar'],
    [TopSellingVariantsWidget::class, 'bar'],
]);

it('declares horizontal bar options for the TopSellingVariantsWidget', function () {
    $instance = new TopSellingVariantsWidget();
    $method = new ReflectionMethod($instance, 'getOptions');

    expect($method->invoke($instance))->toBe(['indexAxis' => 'y']);
});

// ===========================================================================
// QuickActionsWidget — action list shape
// ===========================================================================

it('returns exactly four quick actions', function () {
    expect(QuickActionsWidget::actions())->toHaveCount(4);
});

it('uses Heroicon enum cases for every quick action icon', function () {
    foreach (QuickActionsWidget::actions() as $action) {
        expect($action['icon'])->toBeInstanceOf(Heroicon::class);
    }
});

it('resolves every quick-action label through the translation catalogue', function () {
    foreach (QuickActionsWidget::actions() as $action) {
        expect($action['label'])->toBeString()->not->toBe('');
    }

    foreach ([
        'dashboard.quick_actions.new_transfer',
        'dashboard.quick_actions.new_purchase',
        'dashboard.quick_actions.new_sale',
        'dashboard.quick_actions.new_direct_transfer',
    ] as $key) {
        $label = __($key);
        expect($label)->not->toBe($key);
    }
});

it('declares the quick-actions blade view', function () {
    // Filament v5 `Widget::$view` is a NON-static property
    // (vendor/filament/widgets/src/Widget.php), so it is read per-instance.
    $widget = new QuickActionsWidget();
    $property = new ReflectionProperty(QuickActionsWidget::class, 'view');

    expect($property->getValue($widget))->toBe('filament.widgets.quick-actions');
    expect(view()->exists('filament.widgets.quick-actions'))->toBeTrue();
});

// ===========================================================================
// Translation coverage
// ===========================================================================

it('resolves every dashboard translation key the widgets render', function () {
    $keys = [
        // Stats overview — four labels + four descriptions
        'dashboard.stats.warehouse',
        'dashboard.stats.on_hand',
        'dashboard.stats.on_hand_description',
        'dashboard.stats.pending',
        'dashboard.stats.pending_description',
        'dashboard.stats.in_transit',
        'dashboard.stats.in_transit_description',
        'dashboard.stats.write_off',
        'dashboard.stats.write_off_description',

        // Charts
        'dashboard.charts.available',
        'dashboard.charts.dispatched',
        'dashboard.charts.net_movement',
        'dashboard.charts.purchases',
        'dashboard.charts.revenue',
        'dashboard.charts.sales',

        // Pending table widget
        'dashboard.pending.warehouse',
        'dashboard.pending.sales',
        'dashboard.pending.purchases',

        // Quick actions
        'dashboard.quick_actions.new_transfer',
        'dashboard.quick_actions.new_purchase',
        'dashboard.quick_actions.new_sale',
        'dashboard.quick_actions.new_direct_transfer',

        // Active in-transit table widget
        'dashboard.active_in_transit.qty',
        'dashboard.active_in_transit.requisition',
        'dashboard.active_in_transit.sku',
        'dashboard.active_in_transit.status',
    ];

    foreach ($keys as $key) {
        $label = __($key);
        expect($label)->toBeString()->not->toBe('');
        expect($label)->not->toBe($key);
    }
});
