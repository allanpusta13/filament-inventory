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

class AdminReviewFilters
{
    public static function warehouse(string $relationshipName = 'warehouse'): SelectFilter
    {
        return SelectFilter::make('warehouse_id')
            ->label(__('common.warehouse'))
            ->relationship($relationshipName, 'name')
            ->searchable()
            ->preload();
    }

    /**
     * Period filter. Must be policy-gated via ->authorize('viewAuditFilters') at call site.
     */
    public static function period(string $dateColumn): Filter
    {
        return Filter::make('period')
            ->label(__('common.period'))
            ->schema([
                Select::make('preset')
                    ->label(__('common.period'))
                    ->options([
                        'today' => __('common.periods.today'),
                        'this_week' => __('common.periods.this_week'),
                        'this_month' => __('common.periods.this_month'),
                        'this_year' => __('common.periods.this_year'),
                        'specific_date' => __('common.periods.specific_date'),
                        'custom_range' => __('common.periods.custom_range'),
                    ])
                    ->default(null)
                    ->native(false)
                    ->columnSpan(['default' => 1, 'md' => 1])
                    ->live(),

                DatePicker::make('specific_date')
                    ->label(__('common.date'))
                    ->columnSpan(['default' => 1, 'md' => 1])
                    ->visible(fn (Get $get) => $get('preset') === 'specific_date'),

                DatePicker::make('range_from')
                    ->label(__('common.from'))
                    ->columnSpan(['default' => 1, 'md' => 1])
                    ->visible(fn (Get $get) => $get('preset') === 'custom_range'),

                DatePicker::make('range_until')
                    ->label(__('common.until'))
                    ->columnSpan(['default' => 1, 'md' => 1])
                    ->visible(fn (Get $get) => $get('preset') === 'custom_range'),
            ])
            ->columns(['default' => 1, 'md' => 2])
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
                    'today' => __('common.periods.today'),
                    'this_week' => __('common.periods.this_week'),
                    'this_month' => __('common.periods.this_month'),
                    'this_year' => __('common.periods.this_year'),
                    'specific_date' => isset($data['specific_date'])
                        ? __('common.periods.on_date', ['date' => Carbon::parse($data['specific_date'])->toFormattedDateString()])
                        : null,
                    'custom_range' => array_filter([
                        isset($data['range_from'])
                            ? Indicator::make(__('common.periods.from_date', ['date' => Carbon::parse($data['range_from'])->toFormattedDateString()]))
                                ->removeField('range_from')
                            : Indicator::make(__('common.periods.no_lower_bound'))
                                ->removeField('range_from'),
                        isset($data['range_until'])
                            ? Indicator::make(__('common.periods.until_date', ['date' => Carbon::parse($data['range_until'])->toFormattedDateString()]))
                                ->removeField('range_until')
                            : Indicator::make(__('common.periods.no_upper_bound'))
                                ->removeField('range_until'),
                    ]),
                    default => null,
                };
            });
    }
}
