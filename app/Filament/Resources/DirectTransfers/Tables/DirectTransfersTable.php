<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectTransfers\Tables;

use App\Models\StockMovement;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DirectTransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_code')
                    ->label('REFERENCE CODE')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->color('primary'),

                TextColumn::make('productVariant.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('productVariant.name')
                    ->label('VARIANT NAME')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.name')
                    ->label('ORIGIN WAREHOUSE')
                    ->sortable(),

                TextColumn::make('type')
                    ->label('MOVEMENT TYPE')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'transfer_out' => 'danger',
                        'transfer_in' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('quantity')
                    ->label('BASE UNITS')
                    ->numeric()
                    ->sortable()
                    ->color(fn (int $state): string => $state < 0 ? 'danger' : 'success'),

                TextColumn::make('created_at')
                    ->label('EXECUTED AT')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options(\App\Enums\StockMovementType::class)
                    ->label('MOVEMENT TYPE'),

                SelectFilter::make('warehouse_id')
                    ->label('WAREHOUSE')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => auth()->user()?->can('viewAdminReview', StockMovement::class) ?? false),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }
}
