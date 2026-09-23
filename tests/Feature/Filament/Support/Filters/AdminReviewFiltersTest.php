<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Support\Filters;

use App\Filament\Support\Filters\AdminReviewFilters;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

beforeEach(function () {
    Warehouse::truncate();
    PurchaseOrder::truncate();
});

describe('AdminReviewFilters::warehouse()', function () {
    it('lists all warehouses, not just staff-assigned ones', function () {
        $allWarehouse = Warehouse::factory()->create(['name' => 'Global Warehouse']);
        $staffWarehouse = Warehouse::factory()->create(['name' => 'Staff Warehouse']);

        $filter = AdminReviewFilters::warehouse();

        expect($filter)->toBeInstanceOf(SelectFilter::class);
        expect($filter->getName())->toBe('warehouse_id');
    });

    it('uses the relationship to populate options', function () {
        $filter = AdminReviewFilters::warehouse();

        expect($filter->getRelationshipName())->toBe('warehouse');
        expect($filter->getRelationshipTitleAttribute())->toBe('name');
    });
});

describe('AdminReviewFilters::period()', function () {
    $applyFilterQuery = function (Filter $filter, Builder $builder, array $data): Builder {
        $data['isActive'] = true;

        return $filter->apply($builder, $data);
    };

    it('returns a Filter instance', function () {
        $filter = AdminReviewFilters::period('ordered_at');

        expect($filter)->toBeInstanceOf(Filter::class);
        expect($filter->getName())->toBe('period');
    });

    it('period_filter_today_matches_only_todays_records', function () use ($applyFilterQuery) {
        $todayOrder = PurchaseOrder::factory()->ordered()->create();
        $yesterdayOrder = PurchaseOrder::factory()->ordered()
            ->state(fn () => ['ordered_at' => now()->subDay()])
            ->create();

        $filter = AdminReviewFilters::period('ordered_at');
        $builder = $applyFilterQuery($filter, $todayOrder->newQuery(), ['preset' => 'today']);

        $result = $builder->get();

        expect($result->contains($todayOrder))->toBeTrue();
        expect($result->contains($yesterdayOrder))->toBeFalse();
    });

    it('period_filter_this_week_matches_records_within_current_week_boundaries', function () use ($applyFilterQuery) {
        $thisWeekOrder = PurchaseOrder::factory()->ordered()->create();
        $lastWeekOrder = PurchaseOrder::factory()->ordered()
            ->state(fn () => ['ordered_at' => now()->subWeeks(1)])
            ->create();

        $filter = AdminReviewFilters::period('ordered_at');
        $builder = $applyFilterQuery($filter, $thisWeekOrder->newQuery(), ['preset' => 'this_week']);

        $result = $builder->get();

        expect($result->contains($thisWeekOrder))->toBeTrue();
        expect($result->contains($lastWeekOrder))->toBeFalse();
    });

    it('period_filter_this_month_matches_records_within_current_month_boundaries', function () use ($applyFilterQuery) {
        $thisMonthOrder = PurchaseOrder::factory()->ordered()->create();
        $lastMonthOrder = PurchaseOrder::factory()->ordered()
            ->state(fn () => ['ordered_at' => now()->subMonths(1)])
            ->create();

        $filter = AdminReviewFilters::period('ordered_at');
        $builder = $applyFilterQuery($filter, $thisMonthOrder->newQuery(), ['preset' => 'this_month']);

        $result = $builder->get();

        expect($result->contains($thisMonthOrder))->toBeTrue();
        expect($result->contains($lastMonthOrder))->toBeFalse();
    });

    it('period_filter_this_year_matches_records_within_current_year_boundaries', function () use ($applyFilterQuery) {
        $thisYearOrder = PurchaseOrder::factory()->ordered()->create();
        $lastYearOrder = PurchaseOrder::factory()->ordered()
            ->state(fn () => ['ordered_at' => now()->subYears(1)])
            ->create();

        $filter = AdminReviewFilters::period('ordered_at');
        $builder = $applyFilterQuery($filter, $thisYearOrder->newQuery(), ['preset' => 'this_year']);

        $result = $builder->get();

        expect($result->contains($thisYearOrder))->toBeTrue();
        expect($result->contains($lastYearOrder))->toBeFalse();
    });

    it('period_filter_specific_date_matches_only_that_date', function () use ($applyFilterQuery) {
        $specificOrder = PurchaseOrder::factory()->ordered()->create();
        $otherOrder = PurchaseOrder::factory()->ordered()
            ->state(fn () => ['ordered_at' => now()->subDay()])
            ->create();

        $filter = AdminReviewFilters::period('ordered_at');
        $builder = $applyFilterQuery($filter, $specificOrder->newQuery(), [
            'preset' => 'specific_date',
            'specific_date' => $specificOrder->ordered_at->toDateString(),
        ]);

        $result = $builder->get();

        expect($result->contains($specificOrder))->toBeTrue();
        expect($result->contains($otherOrder))->toBeFalse();
    });

    it('period_filter_custom_range_is_inclusive_of_both_boundary_dates', function () use ($applyFilterQuery) {
        $fromOrder = PurchaseOrder::factory()->ordered()
            ->state(fn () => ['ordered_at' => now()->subDays(5)])
            ->create();
        $toOrder = PurchaseOrder::factory()->ordered()
            ->state(fn () => ['ordered_at' => now()->subDays(2)])
            ->create();
        $outsideOrder = PurchaseOrder::factory()->ordered()
            ->state(fn () => ['ordered_at' => now()->subDays(10)])
            ->create();

        $filter = AdminReviewFilters::period('ordered_at');
        $builder = $applyFilterQuery($filter, $fromOrder->newQuery(), [
            'preset' => 'custom_range',
            'range_from' => now()->subDays(5)->toDateString(),
            'range_until' => now()->subDays(2)->toDateString(),
        ]);

        $result = $builder->get();

        expect($result->contains($fromOrder))->toBeTrue();
        expect($result->contains($toOrder))->toBeTrue();
        expect($result->contains($outsideOrder))->toBeFalse();
    });

    it('period_filter_custom_range_with_only_from_set_is_open_ended', function () use ($applyFilterQuery) {
        $recentOrder = PurchaseOrder::factory()->ordered()
            ->state(fn () => ['ordered_at' => now()->subDays(2)])
            ->create();
        $oldOrder = PurchaseOrder::factory()->ordered()
            ->state(fn () => ['ordered_at' => now()->subDays(10)])
            ->create();

        $filter = AdminReviewFilters::period('ordered_at');
        $builder = $applyFilterQuery($filter, $recentOrder->newQuery(), [
            'preset' => 'custom_range',
            'range_from' => now()->subDays(5)->toDateString(),
        ]);

        $result = $builder->get();

        expect($result->contains($recentOrder))->toBeTrue();
        expect($result->contains($oldOrder))->toBeFalse();
    });

    it('period_filter_custom_range_with_only_until_set_is_open_ended', function () use ($applyFilterQuery) {
        $recentOrder = PurchaseOrder::factory()->ordered()
            ->state(fn () => ['ordered_at' => now()->subDays(2)])
            ->create();
        $futureOrder = PurchaseOrder::factory()->ordered()
            ->state(fn () => ['ordered_at' => now()->addDays(5)])
            ->create();

        $filter = AdminReviewFilters::period('ordered_at');
        $builder = $applyFilterQuery($filter, $recentOrder->newQuery(), [
            'preset' => 'custom_range',
            'range_until' => now()->addDays(3)->toDateString(),
        ]);

        $result = $builder->get();

        expect($result->contains($recentOrder))->toBeTrue();
        expect($result->contains($futureOrder))->toBeFalse();
    });

    it('period_filter_with_no_preset_selected_returns_unfiltered_query', function () use ($applyFilterQuery) {
        $order1 = PurchaseOrder::factory()->ordered()->create();
        $order2 = PurchaseOrder::factory()->ordered()->create();

        $filter = AdminReviewFilters::period('ordered_at');
        $builder = $applyFilterQuery($filter, $order1->newQuery(), ['preset' => null]);

        $result = $builder->get();

        expect($result)->toHaveCount(2);
    });
});
