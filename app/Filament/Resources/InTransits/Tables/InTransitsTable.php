<?php

declare(strict_types=1);

namespace App\Filament\Resources\InTransits\Tables;

use App\Enums\InTransitStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InTransitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transferRequisition.reference_code')
                    ->label('REQUISITION REF')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->searchable(query: fn ($query, $search) => $query->whereHas('transferRequisition', fn ($q) => $q->where('reference_code', 'like', "%{$search}%")))
                    ->sortable()
                    ->copyable()
                    ->color('primary')
                    ->getStateUsing(fn ($record) => $record->transferRequisition?->reference_code ?? '—'),

                TextColumn::make('productVariant.sku')
                    ->label('SKU')
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('productVariant.name')
                    ->label('VARIANT NAME')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('TRANSIT STATUS')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'in_transit' => 'warning',
                        'partially_received' => 'info',
                        'cleared' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('dispatched_base_qty')
                    ->label('DISPATCHED (BASE)')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('dispatched_at')
                    ->label('DISPATCHED AT')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('CREATED')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('dispatched_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(InTransitStatus::class)
                    ->label('TRANSIT STATUS'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}