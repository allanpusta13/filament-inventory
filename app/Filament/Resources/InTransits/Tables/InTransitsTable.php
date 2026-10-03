<?php

declare(strict_types=1);

namespace App\Filament\Resources\InTransits\Tables;

use App\Enums\InTransitStatus;
use App\Filament\Resources\InTransits\InTransitResource;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * In-transits table — §7D.2 canonical contract.
 *
 * Standard table + `stackedOnMobile()` (F26). Read-only monitor.
 */
class InTransitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transferRequisition.reference_code')
                    ->label(__('resources.in_transits.table.requisition'))
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('productVariant.sku')
                    ->label(__('resources.in_transits.table.sku'))
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('dispatched_base_qty')
                    ->label(__('resources.in_transits.table.dispatched'))
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('dispatched_at')
                    ->label(__('resources.in_transits.table.dispatched_at'))
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->visibleFrom('md'),

                TextColumn::make('status')
                    ->label(__('resources.in_transits.table.status'))
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('resources.in_transits.filters.status'))
                    ->options(InTransitStatus::class),
            ])
            ->defaultSort('dispatched_at', 'desc')
            ->stackedOnMobile()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->recordUrl(fn ($record) => InTransitResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()->icon(Heroicon::Eye),
            ]);
    }
}
