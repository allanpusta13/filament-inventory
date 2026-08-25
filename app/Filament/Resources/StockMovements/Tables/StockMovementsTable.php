<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockMovements\Tables;

use App\Enums\MovementType;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->deferFilters(false)
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (MovementType $state): string => $state->getColor())
                    ->sortable(),
                TextColumn::make('quantity')
                    ->sortable()
                    ->color(function (int $state): string {
                        return $state > 0 ? 'success' : 'danger';
                    }),
                TextColumn::make('reference')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('counterpart_warehouse')
                    ->label('From/To')
                    ->toggleable()
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('From')
                            ->native(false),
                        DatePicker::make('created_until')
                            ->label('Until')
                            ->native(false),
                    ])
                    ->query(function ($query, array $data): mixed {
                        return $query
                            ->when($data['created_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
                SelectFilter::make('direction')
                    ->options([
                        'incoming' => 'Incoming',
                        'outgoing' => 'Outgoing',
                    ])
                    ->query(function ($query, array $data): mixed {
                        return $query
                            ->when(($data['value'] ?? null) === 'incoming', fn ($q) => $q->whereIn('type', [
                                MovementType::Receive,
                                MovementType::TransferIn,
                            ]))
                            ->when(($data['value'] ?? null) === 'outgoing', fn ($q) => $q->whereIn('type', [
                                MovementType::Ship,
                                MovementType::TransferOut,
                            ]));
                    }),
                SelectFilter::make('type')
                    ->options(MovementType::class),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
