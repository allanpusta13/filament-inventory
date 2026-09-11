<?php

declare(strict_types=1);

namespace App\Filament\Resources\InTransits\Tables;

use App\Enums\InTransitStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class InTransitsTable
{
    public static function configure(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('transferRequisition.reference_code')
                    ->label('REQUISITION REF')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->color('primary'),

                \Filament\Tables\Columns\TextColumn::make('productVariant.sku')
                    ->label('SKU')
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable()
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('productVariant.name')
                    ->label('VARIANT NAME')
                    ->searchable()
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('status')
                    ->label('TRANSIT STATUS')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'in_transit' => 'warning',
                        'partially_received' => 'info',
                        'cleared' => 'success',
                        default => 'gray',
                    }),

                \Filament\Tables\Columns\TextColumn::make('dispatched_base_qty')
                    ->label('DISPATCHED (BASE)')
                    ->numeric()
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('dispatched_at')
                    ->label('DISPATCHED AT')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('created_at')
                    ->label('CREATED')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('dispatched_at', 'desc')
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->options(\App\Enums\InTransitStatus::class)
                    ->label('TRANSIT STATUS'),

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