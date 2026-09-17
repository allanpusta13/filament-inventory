<?php

declare(strict_types=1);

namespace App\Filament\Resources\InTransits\Tables;

use App\Enums\InTransitStatus;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
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
                    ->dateTime('M d, Y')
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

                Action::make('scanToReceive')
                    ->label('RECEIVE INTAKE')
                    ->icon('heroicon-m-qr-code')
                    ->color('success')
                    ->authorize('receive')
                    ->visible(fn ($record) => in_array($record->status, [InTransitStatus::InTransit, InTransitStatus::PartiallyReceived]))
                    ->url(fn ($record) => route('stn.scan', ['transferRequisition' => $record->transfer_requisition_id])),
            ]);
    }
}
