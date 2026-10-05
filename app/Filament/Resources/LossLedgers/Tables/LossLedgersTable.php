<?php

declare(strict_types=1);

namespace App\Filament\Resources\LossLedgers\Tables;

use App\Enums\LossCategory;
use App\Filament\Resources\LossLedgers\LossLedgerResource;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
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
                    ->formatStateUsing(fn ($state): string => format_money($state))
                    ->visibleFrom('lg'),

                TextColumn::make('total_financial_loss')
                    ->label(__('resources.loss_ledgers.table.total_loss'))
                    ->formatStateUsing(fn ($state): string => format_money($state))
                    ->weight('bold')
                    ->alignEnd()
                    ->summarize(
                        Sum::make()
                            ->formatStateUsing(fn ($state): string => format_money($state))
                    ),

                TextColumn::make('recordedBy.name')
                    ->label(__('resources.loss_ledgers.table.by'))
                    ->visibleFrom('xl'),
            ])
            ->filters([
                SelectFilter::make('loss_category')
                    ->label(__('resources.loss_ledgers.filters.loss_category'))
                    ->options(LossCategory::class),
                \App\Filament\Support\Filters\AdminReviewFilters::warehouse()
                    ->label(__('resources.loss_ledgers.filters.warehouse')),
                \App\Filament\Support\Filters\AdminReviewFilters::period('recorded_at')
                    ->visible(fn (): bool => auth()->user()?->can('viewAuditFilters', \App\Models\LossLedger::class) ?? false),
            ])
            ->defaultSort('recorded_at', 'desc')
            ->stackedOnMobile()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->recordUrl(fn ($record) => LossLedgerResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()->icon(Heroicon::Eye),
            ]);
    }
}
