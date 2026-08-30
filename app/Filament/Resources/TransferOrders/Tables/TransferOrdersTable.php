<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferOrders\Tables;

use App\Enums\TransferOrderStatus;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class TransferOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_number')
                    ->label('Reference')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sender.name')
                    ->label('From')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('receiver.name')
                    ->label('To')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (TransferOrderStatus $state): string => $state->getColor())
                    ->sortable(),
                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(TransferOrderStatus::class),
            ])
            ->recordActions([
                Action::make('printTransferNote')
                    ->label('Print STN')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn ($record): string => route('transfer-notes.show', $record))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([]);
    }
}
