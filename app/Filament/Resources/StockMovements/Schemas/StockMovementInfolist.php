<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockMovements\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class StockMovementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('productVariant.sku')
                ->label('SKU')
                ->fontFamily('mono')
                ->copyable(),

            TextEntry::make('productVariant.name')
                ->label('Variant Name'),

            TextEntry::make('warehouse.name')
                ->label('Warehouse'),

            TextEntry::make('type')
                ->label('Movement Type')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'receive', 'transfer_in', 'transit_in' => 'success',
                    'ship', 'transfer_out', 'transit_out' => 'info',
                    'adjustment' => 'warning',
                    'loss' => 'danger',
                    default => 'gray',
                }),

            TextEntry::make('quantity')
                ->label('Quantity (Base Units)')
                ->numeric()
                ->color(fn (int $state): string => $state < 0 ? 'danger' : 'success'),

            TextEntry::make('unit_name_used')
                ->label('Unit Name Used'),

            TextEntry::make('unit_ratio_used')
                ->label('Unit Ratio Used')
                ->numeric(),

            TextEntry::make('reference_code')
                ->label('Reference Code')
                ->copyable(),

            TextEntry::make('reference_type')
                ->label('Reference Type'),

            TextEntry::make('reference_id')
                ->label('Reference ID'),

            TextEntry::make('relatedMovement.reference_code')
                ->label('Related Movement')
                ->placeholder('—'),

            TextEntry::make('notes')
                ->label('Notes')
                ->columnSpanFull(),

            TextEntry::make('createdBy.name')
                ->label('Created By'),

            TextEntry::make('created_at')
                ->label('Created At')
                ->dateTime('M d, Y H:i'),

            TextEntry::make('updated_at')
                ->label('Updated At')
                ->dateTime('M d, Y H:i'),

            IconEntry::make('createdBy.name')
                ->label('Created By')
                ->icon('heroicon-o-user-circle')
                ->iconColor('primary')
                ->size(IconEntry\IconEntrySize::Large)
                ->extraAttributes(['aria-label' => 'Created by user']),
        ]);
    }
}
