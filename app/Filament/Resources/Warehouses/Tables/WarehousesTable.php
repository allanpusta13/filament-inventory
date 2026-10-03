<?php

declare(strict_types=1);

namespace App\Filament\Resources\Warehouses\Tables;

use App\Filament\Resources\Warehouses\WarehouseResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Warehouses table — §7K.2 canonical contract.
 *
 * Card layout (§7N.4). No bulk actions (F30). Pagination at 12 (F29).
 */
class WarehousesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('code')
                            ->label(__('resources.warehouses.table.code'))
                            ->fontFamily('mono')
                            ->weight(FontWeight::Bold)
                            ->searchable()
                            ->sortable()
                            ->copyable()
                            ->copyMessage(__('common.copied')),

                        TextColumn::make('is_active')
                            ->label(__('resources.warehouses.table.status'))
                            ->badge()
                            ->alignEnd()
                            ->formatStateUsing(fn (bool $state) => $state ? __('common.active') : __('common.inactive'))
                            ->color(fn (bool $state) => $state ? 'success' : 'danger'),
                    ])->from('md'),

                    TextColumn::make('name')
                        ->label(__('resources.warehouses.table.name'))
                        ->searchable()
                        ->sortable()
                        ->weight(FontWeight::SemiBold),

                    TextColumn::make('location')
                        ->label(__('resources.warehouses.table.location'))
                        ->icon(Heroicon::MapPin)
                        ->iconColor('gray')
                        ->searchable()
                        ->limit(60)
                        ->placeholder(__('common.empty')),

                    Split::make([
                        TextColumn::make('users_count')
                            ->label(__('resources.warehouses.table.staff'))
                            ->counts('users')
                            ->badge()
                            ->color('primary')
                            ->numeric(),

                        TextColumn::make('stock_movements_count')
                            ->label(__('resources.warehouses.table.ledger_entries'))
                            ->counts('stockMovements')
                            ->badge()
                            ->color('gray')
                            ->numeric(),
                    ])->from('md'),
                ])->space(3),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('resources.warehouses.filters.is_active')),
            ])
            ->defaultSort('code')
            ->defaultPaginationPageOption(12)
            ->paginated([12, 24, 48])
            ->recordUrl(fn ($record) => WarehouseResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                EditAction::make()
                    ->icon(Heroicon::PencilSquare)
                    ->authorize('update')
                    ->modalWidth(Width::Large),

                DeleteAction::make()
                    ->icon(Heroicon::Trash)
                    ->authorize('delete')
                    ->requiresConfirmation()
                    ->modalDescription(__('resources.warehouses.delete_confirm_description')),
            ]);
    }
}
