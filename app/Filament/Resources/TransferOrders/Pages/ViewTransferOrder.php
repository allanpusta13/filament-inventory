<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferOrders\Pages;

use App\Filament\Resources\TransferOrders\TransferOrderResource;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;

final class ViewTransferOrder extends ViewRecord
{
    protected static string $resource = TransferOrderResource::class;

    public function infolist(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->components([
                Section::make('Order Details')
                    ->schema([
                        TextEntry::make('reference_number')
                            ->label('Reference Number'),
                        TextEntry::make('sender.name')
                            ->label('From Branch'),
                        TextEntry::make('receiver.name')
                            ->label('To Branch'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn ($state): string => $state->getColor()),
                        TextEntry::make('notes')
                            ->label('Notes')
                            ->columnSpanFull(),
                        TextEntry::make('created_at')
                            ->dateTime(),
                    ]),
                Section::make('Line Items')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->schema([
                                TextEntry::make('product.name')
                                    ->label('Product'),
                                TextEntry::make('requested_quantity')
                                    ->label('Requested'),
                                TextEntry::make('approved_quantity')
                                    ->label('Approved'),
                                TextEntry::make('received_quantity')
                                    ->label('Received'),
                                TextEntry::make('damaged_quantity')
                                    ->label('Damaged'),
                                TextEntry::make('item_status')
                                    ->label('Status'),
                            ])
                            ->columns(6),
                    ]),
            ]);
    }
}
