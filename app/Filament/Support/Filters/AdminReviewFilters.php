<?php

declare(strict_types=1);

namespace App\Filament\Support\Filters;

use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * [Added v11.1] Shared, System-Admin-only filters — warehouse and period —
 * reused across PurchaseOrdersTable, SalesOrdersTable, StockMovementsTable,
 * and LossLedgersTable. Built once here instead of four times, per the
 * DRY principle. Both factory methods return the exact filter definitions
 * to splice into a resource's ->filters([...]) array; they do not include
 * the ->visible() gate themselves — apply that at the call site, since
 * Filament v5's ->filters() array-level visibility gating differs
 * slightly by context and each resource should make its own admin-only
 * intent explicit rather than trusting a shared helper to have done it.
 */
class AdminReviewFilters
{
    /**
     * Warehouse filter — a plain SelectFilter listing ALL warehouses
     * (not auth()->user()->warehouses(), which is the staff-scoped list
     * used elsewhere in the parent blueprint's wizards). This is
     * deliberate: an admin reviewing cross-warehouse activity needs to
     * see and filter by warehouses they may not be personally assigned
     * to, which is exactly why this filter is admin-gated at the call
     * site rather than using the staff-scoped relationship.
     */
    public static function warehouse(string $relationshipName = 'warehouse'): SelectFilter
    {
        return SelectFilter::make('warehouse_id')
            ->label(__('Warehouse'))
            ->relationship($relationshipName, 'name')
            ->searchable()
            ->preload();
    }

    /**
     * Period filter — a single dropdown of common presets (Today, This
     * Week, This Month, This Year, Specific Date, Custom Range), with
     * conditional fields that only appear for the presets that need them
     * (a DatePicker for "Specific Date", two DatePickers for "Custom
     * Range"). "This Week" and "This Month" and "This Year" need no
     * extra input at all — they resolve relative to now() at query time.
     *
     * $dateColumn: the column to filter on — differs per resource
     * (created_at for stock_movements, recorded_at for loss_ledgers,
     * ordered_at for purchase_orders, confirmed_at or ordered_at for
     * sales_orders — pass whichever is the resource's primary date of
     * record).
     */
    public static function period(string $dateColumn): Filter
    {
        return Filter::make('period')
            ->label(__('Period'))
            ->schema([
                Select::make('preset')
                    ->label(__('Period'))
                    ->options([
                        'today' => __('Today'),
                        'this_week' => __('This Week'),
                        'this_month' => __('This Month'),
                        'this_year' => __('This Year'),
                        'specific_date' => __('Specific Date'),
                        'custom_range' => __('Custom Range'),
                    ])
                    ->default(null)
                    ->native(false)
                    ->live(),

                DatePicker::make('specific_date')
                    ->label(__('Date'))
                    ->visible(fn (Get $get) => $get('preset') === 'specific_date'),

                DatePicker::make('range_from')
                    ->label(__('From'))
                    ->visible(fn (Get $get) => $get('preset') === 'custom_range'),

                DatePicker::make('range_until')
                    ->label(__('Until'))
                    ->visible(fn (Get $get) => $get('preset') === 'custom_range'),
            ])
            ->query(function (Builder $query, array $data) use ($dateColumn): Builder {
                return match ($data['preset'] ?? null) {
                    'today' => $query->whereDate($dateColumn, now()->toDateString()),
                    'this_week' => $query->whereBetween($dateColumn, [now()->startOfWeek(), now()->endOfWeek()]),
                    'this_month' => $query->whereBetween($dateColumn, [now()->startOfMonth(), now()->endOfMonth()]),
                    'this_year' => $query->whereBetween($dateColumn, [now()->startOfYear(), now()->endOfYear()]),
                    'specific_date' => $query->when(
                        $data['specific_date'] ?? null,
                        fn (Builder $q, $date) => $q->whereDate($dateColumn, $date),
                    ),
                    'custom_range' => $query
                        ->when($data['range_from'] ?? null, fn (Builder $q, $date) => $q->whereDate($dateColumn, '>=', $date))
                        ->when($data['range_until'] ?? null, fn (Builder $q, $date) => $q->whereDate($dateColumn, '<=', $date)),
                    default => $query,
                };
            })
            ->indicateUsing(function (array $data): string|array|null {
                return match ($data['preset'] ?? null) {
                    'today' => __('Today'),
                    'this_week' => __('This week'),
                    'this_month' => __('This month'),
                    'this_year' => __('This year'),
                    'specific_date' => isset($data['specific_date'])
                        ? __('On :date', ['date' => Carbon::parse($data['specific_date'])->toFormattedDateString()])
                        : null,
                    'custom_range' => array_filter([
                        isset($data['range_from'])
                            ? Indicator::make(__('From :date', ['date' => Carbon::parse($data['range_from'])->toFormattedDateString()]))
                                ->removeField('range_from')
                            : null,
                        isset($data['range_until'])
                            ? Indicator::make(__('Until :date', ['date' => Carbon::parse($data['range_until'])->toFormattedDateString()]))
                                ->removeField('range_until')
                            : null,
                    ]),
                    default => null,
                };
            });
    }
}
