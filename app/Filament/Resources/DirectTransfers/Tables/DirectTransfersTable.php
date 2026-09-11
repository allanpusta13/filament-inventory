<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectTransfers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class DirectTransfersTable
{
    public static function configure(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('reference_code')
                    ->label('REFERENCE CODE')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->color('primary'),

                \Filament\Tables\Columns\TextColumn::make('productVariant.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('productVariant.name')
                    ->label('VARIANT NAME')
                    ->searchable()
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('ORIGIN WAREHOUSE')
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('type')
                    ->label('MOVEMENT TYPE')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'transfer_out' => 'danger',
                        'transfer_in' => 'success',
                        default => 'gray',
                    }),

                \Filament\Tables\Columns\TextColumn::make('quantity')
                    ->label('BASE UNITS')
                    ->numeric()
                    ->sortable()
                    ->color(fn (int $state): string => $state < 0 ? 'danger' : 'success'),

                \Filament\Tables\Columns\TextColumn::make('created_at')
                    ->label('EXECUTED AT')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'transfer_out' => 'Transfer Out (Origin)',
                        'transfer_in' => 'Transfer In (Destination)',
                    ])
                    ->label('MOVEMENT TYPE'),

                \Filament\Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('WAREHOUSE')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),

                \Filament\Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
                \Filament\Actions\RestoreAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                    \Filament\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }
}