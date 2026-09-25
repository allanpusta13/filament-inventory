<?php

declare(strict_types=1);

namespace App\Filament\Resources\LossLedgers\Tables;

use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LossLedgersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('recorded_at')
                    ->label(__('resources.loss_ledgers.table.recorded'))
                    ->dateTime('M j, Y H:i')
                    ->sortable(),

                TextColumn::make('transferRequisition.reference_code')
                    ->label(__('resources.loss_ledgers.table.requisition'))
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('productVariant.sku')
                    ->label(__('resources.loss_ledgers.table.sku'))
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.code')
                    ->label(__('resources.loss_ledgers.table.warehouse'))
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->visibleFrom('md'),

                TextColumn::make('lost_base_qty')
                    ->label(__('resources.loss_ledgers.table.lost'))
                    ->numeric()->alignEnd()->color('warning'),

                TextColumn::make('damaged_base_qty')
                    ->label(__('resources.loss_ledgers.table.damaged'))
                    ->numeric()->alignEnd()->color('danger'),

                TextColumn::make('loss_category')
                    ->label(__('resources.loss_ledgers.table.category'))
                    ->badge()
                    ->visibleFrom('md'),

                TextColumn::make('unit_cost_price')
                    ->label(__('resources.loss_ledgers.table.unit_cost'))
                    ->money(config('app.currency'), decimals: 4)
                    ->visibleFrom('lg'),

                TextColumn::make('total_financial_loss')
                    ->label(__('resources.loss_ledgers.table.total_loss'))
                    ->money(config('app.currency'), decimals: 4)
                    ->weight('bold')
                    ->alignEnd()
                    ->summarize(Sum::make()->money(config('app.currency'), decimals: 4)),

                TextColumn::make('recordedBy.name')
                    ->label(__('resources.loss_ledgers.table.by'))
                    ->visibleFrom('xl'),
            ])
            ->filters([
                SelectFilter::make('loss_category')->options([
                    'shortfall' => __('enums.loss_category.shortfall'),
                    'damage' => __('enums.loss_category.damage'),
                    'spoilage' => __('enums.loss_category.spoilage'),
                    'theft' => __('enums.loss_category.theft'),
                    'other' => __('enums.loss_category.other'),
                ]),
                SelectFilter::make('warehouse_id')
                    ->relationship('warehouse', 'name')
                    ->label(__('resources.loss_ledgers.filters.warehouse'))
                    ->searchable(),
                \App\Filament\Support\Filters\AdminReviewFilters::period('recorded_at')
                    ->authorize('viewAuditFilters'),
            ])
            ->defaultSort('recorded_at', 'desc')
            ->stackedOnMobile()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50);
    }
}
