<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectTransfers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class DirectTransferInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Grid::make(3)->schema([
                Section::make('DIRECT TRANSFER IDENTITY')
                    ->icon(Heroicon::ArrowPath)
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('reference_code')
                                ->label('REFERENCE CODE')
                                ->weight(FontWeight::Bold)
                                ->color('primary')
                                ->copyable()
                                ->icon(Heroicon::Hashtag),

                            TextEntry::make('type')
                                ->label('MOVEMENT TYPE')
                                ->badge()
                                ->color(fn ($state): string => match ($state) {
                                    'transfer_out' => 'danger',
                                    'transfer_in' => 'success',
                                    default => 'gray',
                                }),
                            TextEntry::make('productVariant.sku')
                                ->label('SKU')
                                ->fontFamily('mono')
                                ->copyable(),

                            TextEntry::make('productVariant.name')
                                ->label('VARIANT NAME'),

                            TextEntry::make('warehouse.name')
                                ->label('ORIGIN WAREHOUSE')
                                ->weight(FontWeight::Bold)
                                ->icon(Heroicon::BuildingOffice),

                            TextEntry::make('relatedMovement.warehouse.name')
                                ->label('DESTINATION WAREHOUSE')
                                ->weight(FontWeight::Bold)
                                ->icon(Heroicon::BuildingOffice2),
                        ]),
                    ])->columnSpan(2),

                Section::make('QUANTITY DETAILS')
                    ->icon(Heroicon::Scale)
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('quantity')
                                ->label('BASE UNITS')
                                ->weight(FontWeight::Bold)
                                ->numeric()
                                ->color(fn (int $state): string => $state < 0 ? 'danger' : 'success'),

                            TextEntry::make('relatedMovement.quantity')
                                ->label('RELATED MOVEMENT UNITS')
                                ->numeric()
                                ->color(fn (int $state): string => $state < 0 ? 'danger' : 'success'),
                        ]),
                    ])->columnSpan(1),

                Section::make('AUDIT')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('notes')
                                ->label('NOTES')
                                ->placeholder('—')
                                ->columnSpanFull(),

                            TextEntry::make('created_at')
                                ->label('EXECUTED AT')
                                ->dateTime('M d, Y H:i')
                                ->weight(FontWeight::Bold),

                            TextEntry::make('createdBy.name')
                                ->label('EXECUTED BY')
                                ->placeholder('—'),
                        ]),
                    ])->columnSpanFull(),
            ]),
        ]);
    }
}
