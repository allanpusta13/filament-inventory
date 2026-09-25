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

class WarehousesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('code')
                            ->fontFamily('mono')
                            ->weight(FontWeight::Bold)
                            ->searchable()->sortable()->copyable()->copyMessage(__('common.copied')),

                        TextColumn::make('is_active')
                            ->label(__('resources.warehouses.table.status'))
                            ->badge()->alignEnd()
                            ->formatStateUsing(fn (bool $state) => $state ? __('common.active') : __('common.inactive'))
                            ->color(fn (bool $state) => $state ? 'success' : 'danger'),
                    ])->from('md'),

                    TextColumn::make('name')
                        ->searchable()->sortable()->weight(FontWeight::SemiBold),

                    TextColumn::make('location')
                        ->icon(Heroicon::MapPin)->iconColor('gray')
                        ->searchable()->limit(60)->placeholder('—'),

                    Split::make([
                        TextColumn::make('users_count')
                            ->label(__('resources.warehouses.table.staff'))
                            ->counts('users')
                            ->badge()->color('primary')->numeric(),

                        TextColumn::make('stock_movements_count')
                            ->label(__('resources.warehouses.table.ledger_entries'))
                            ->counts('stockMovements')
                            ->badge()->color('gray')->numeric(),
                    ])->from('md'),
                ])->space(3),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->filters([
                TernaryFilter::make('is_active'),
            ])
            ->defaultSort('code')
            ->defaultPaginationPageOption(12)
            ->paginated([12, 24, 48])
            ->recordUrl(fn ($record) => WarehouseResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                EditAction::make()
                    ->icon(Heroicon::PencilSquare)
                    ->modalWidth(Width::Large),

                DeleteAction::make()
                    ->icon(Heroicon::Trash)
                    ->authorize('delete')
                    ->requiresConfirmation()
                    ->modalDescription(__('resources.warehouses.delete_blocked_description')),
            ]);
    }
}
