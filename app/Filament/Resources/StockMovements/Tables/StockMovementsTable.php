<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockMovements\Tables;

use App\Enums\StockMovementType;
use App\Filament\Resources\StockMovements\StockMovementResource;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Stock movements table — §7E.2 canonical contract.
 *
 * Dense standard table (F26), `stackedOnMobile()`, pagination ≥ 25.
 * Column-level signed-quantity color coding.
 */
class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('resources.stock_movements.table.timestamp'))
                    ->dateTime('M j, Y H:i')
                    ->sortable(),

                TextColumn::make('productVariant.sku')
                    ->label(__('resources.stock_movements.table.sku'))
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.code')
                    ->label(__('resources.stock_movements.table.warehouse'))
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->visibleFrom('md'),

                TextColumn::make('type')
                    ->label(__('resources.stock_movements.table.type'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('quantity')
                    ->label(__('resources.stock_movements.table.qty'))
                    ->numeric()
                    ->alignEnd()
                    ->sortable()
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->weight('bold'),

                TextColumn::make('unit_name_used')
                    ->label(__('resources.stock_movements.table.unit'))
                    ->state(fn ($record) => $record->unit_ratio_used > 1
                        ? "{$record->unit_name_used} (×{$record->unit_ratio_used})"
                        : $record->unit_name_used)
                    ->visibleFrom('lg'),

                TextColumn::make('reference_code')
                    ->label(__('resources.stock_movements.table.reference_code'))
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable()
                    ->visibleFrom('md'),

                TextColumn::make('createdBy.name')
                    ->label(__('resources.stock_movements.table.by'))
                    ->visibleFrom('xl'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('resources.stock_movements.filters.type'))
                    ->options(StockMovementType::class),

                \App\Filament\Support\Filters\AdminReviewFilters::warehouse()
                    ->label(__('resources.stock_movements.filters.warehouse')),

                SelectFilter::make('product_variant_id')
                    ->label(__('resources.stock_movements.filters.variant'))
                    ->relationship('productVariant', 'sku')
                    ->searchable(),

                \App\Filament\Support\Filters\AdminReviewFilters::period('created_at')
                    ->visible(fn (): bool => auth()->user()?->can('viewAuditFilters', \App\Models\StockMovement::class) ?? false),
            ])
            ->defaultSort('created_at', 'desc')
            ->stackedOnMobile()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->recordUrl(fn ($record) => StockMovementResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()->icon(Heroicon::Eye),
            ]);
    }
}
