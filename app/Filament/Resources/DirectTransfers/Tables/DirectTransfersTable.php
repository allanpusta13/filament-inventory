<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectTransfers\Tables;

use App\Filament\Resources\DirectTransfers\DirectTransferResource;
use App\Models\DirectTransfer;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Direct transfers table — §7C.4 canonical contract.
 *
 * Card layout per §7N.4 (document-shaped record). No bulk actions
 * (F30). Pagination at 12 (F29).
 */
class DirectTransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('reference_code')
                            ->label(__('resources.direct_transfers.table.reference'))
                            ->fontFamily('mono')
                            ->weight(FontWeight::Bold)
                            ->searchable()
                            ->sortable()
                            ->copyable(),

                        TextColumn::make('transferred_at')
                            ->label(__('resources.direct_transfers.table.transferred_at'))
                            ->dateTime('M j, Y H:i')
                            ->sortable()
                            ->alignEnd(),
                    ])->from('md'),

                    Split::make([
                        TextColumn::make('fromWarehouse.name')
                            ->label(__('resources.direct_transfers.table.from'))
                            ->icon(Heroicon::BuildingOffice)
                            ->iconColor('gray'),

                        TextColumn::make('toWarehouse.name')
                            ->label(__('resources.direct_transfers.table.to'))
                            ->icon(Heroicon::BuildingOffice2)
                            ->iconColor('gray'),
                    ])->from('md'),

                    Split::make([
                        TextColumn::make('items_count')
                            ->label(__('resources.direct_transfers.table.items'))
                            ->counts('items')
                            ->badge()
                            ->color('gray')
                            ->numeric(),

                        TextColumn::make('transferredBy.name')
                            ->label(__('resources.direct_transfers.table.by'))
                            ->icon(Heroicon::User)
                            ->iconColor('gray')
                            ->placeholder(__('common.empty'))
                            ->alignEnd(),
                    ])->from('lg'),
                ])->space(3),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->filters([
                SelectFilter::make('from_warehouse_id')
                    ->label(__('resources.direct_transfers.filters.from_warehouse'))
                    ->relationship('fromWarehouse', 'name')
                    ->searchable(),

                SelectFilter::make('to_warehouse_id')
                    ->label(__('resources.direct_transfers.filters.to_warehouse'))
                    ->relationship('toWarehouse', 'name')
                    ->searchable(),

                \App\Filament\Support\Filters\AdminReviewFilters::period('transferred_at')
                    ->visible(fn (): bool => auth()->user()?->can('viewAuditFilters', DirectTransfer::class) ?? false),
            ])
            ->defaultSort('transferred_at', 'desc')
            ->defaultPaginationPageOption(12)
            ->paginated([12, 24, 48])
            ->recordUrl(fn (DirectTransfer $r) => DirectTransferResource::getUrl('view', ['record' => $r]))
            ->recordActions([
                ViewAction::make()->icon(Heroicon::Eye),
            ]);
    }
}
