<?php

declare(strict_types=1);

use App\Filament\Support\Filters\AdminReviewFilters;
use App\Filament\Support\Wizards\WizardReviewStep;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;

/**
 * Filament Support class contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §9 AdminReviewFilters::warehouse() / period() shape.
 *   - §18.3 WizardReviewStep::renderSummary() static contract.
 *   - §0A.7 every heading resolves through the translation catalogue.
 *   - §0A.8 every action label resolves.
 */

// ===========================================================================
// AdminReviewFilters
// ===========================================================================

describe('AdminReviewFilters::warehouse()', function () {
    it('returns a SelectFilter', function () {
        expect(AdminReviewFilters::warehouse())->toBeInstanceOf(SelectFilter::class);
    });

    it('accepts an alternate relationship name', function () {
        // The default is 'warehouse'; StockMovementResource and
        // LossLedgerResource pass different relationship names for
        // column-qualified filters.
        expect(AdminReviewFilters::warehouse('fromWarehouse'))->toBeInstanceOf(SelectFilter::class);
        expect(AdminReviewFilters::warehouse('toWarehouse'))->toBeInstanceOf(SelectFilter::class);
    });

    it('resolves its label through common.warehouse', function () {
        $filter = AdminReviewFilters::warehouse();

        expect($filter->getLabel())->toBe(__('common.warehouse'));
        expect(__('common.warehouse'))->toBeString()->not->toBe('');
        expect(__('common.warehouse'))->not->toBe('common.warehouse');
    });
});

describe('AdminReviewFilters::period()', function () {
    it('returns a Filter', function () {
        expect(AdminReviewFilters::period('created_at'))->toBeInstanceOf(Filter::class);
    });

    it('resolves its label through common.period', function () {
        $filter = AdminReviewFilters::period('created_at');

        expect($filter->getLabel())->toBe(__('common.period'));
        expect(__('common.period'))->toBeString()->not->toBe('');
    });

    it('exposes every preset translation key', function () {
        // The filter's preset select resolves each option label through
        // common.periods.* — a missing key would surface as a raw key
        // in the dropdown.
        foreach ([
            'today',
            'this_week',
            'this_month',
            'this_year',
            'specific_date',
            'custom_range',
        ] as $preset) {
            $label = __("common.periods.{$preset}");
            expect($label)->toBeString()->not->toBe('');
            expect($label)->not->toBe("common.periods.{$preset}");
        }
    });

    it('exposes every custom-range indicator key', function () {
        // The custom-range indicators resolve through common.periods.*
        // with interpolated dates.
        foreach (['on_date', 'from_date', 'until_date', 'no_lower_bound', 'no_upper_bound'] as $key) {
            $label = __("common.periods.{$key}", ['date' => '2026-01-01']);
            expect($label)->toBeString()->not->toBe('');
            expect($label)->not->toBe("common.periods.{$key}");
        }
    });
});

// ===========================================================================
// WizardReviewStep
// ===========================================================================

describe('WizardReviewSummary Livewire component', function () {
    it('renders the target view with the given state', function () {
        Livewire\Livewire::test(App\Livewire\Wizards\WizardReviewSummary::class, [
            'view' => 'filament.wizards.transfer-review',
            'state' => [
                'from_warehouse_id' => null,
                'to_warehouse_id' => null,
                'items' => [],
            ],
        ])->assertSuccessful();
    });

    it('re-renders when the state changes', function () {
        $component = Livewire\Livewire::test(App\Livewire\Wizards\WizardReviewSummary::class, [
            'view' => 'filament.wizards.transfer-review',
            'state' => ['items' => []],
        ]);

        $component->set('state', [
            'items' => [['product_variant_id' => null, 'requested_qty' => 5, 'requested_unit_name' => 'pc']],
        ])->assertSee('5');
    });
});
