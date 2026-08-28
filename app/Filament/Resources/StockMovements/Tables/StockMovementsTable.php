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
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (MovementType $state): string => $state->getColor())
                    ->formatStateUsing(fn (MovementType $state): string => match ($state) {
                        MovementType::Receive => 'Received',
                        MovementType::Ship => 'Shipped',
                        MovementType::TransferOut => 'Transfer Out',
                        MovementType::TransferIn => 'Transfer In',
                        MovementType::Adjustment => 'Adjustment',
                    })
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->sortable()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? '+'.number_format($state) : number_format($state))
                    ->weight('bold'),
                TextColumn::make('reference')
                    ->label('Reference')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->limit(20)
                    ->copyable(),
                TextColumn::make('counterpart_warehouse')
                    ->label('From/To')
                    ->toggleable()
                    ->placeholder('-')
                    ->icon('heroicon-m-arrows-right-left'),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M j, g:i A')
                    ->sortable(),
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
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
