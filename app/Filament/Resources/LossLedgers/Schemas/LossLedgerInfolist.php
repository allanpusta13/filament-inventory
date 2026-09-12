<?php

declare(strict_types=1);

namespace App\Filament\Resources\LossLedgers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class LossLedgerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Grid::make(3)->schema([
                Section::make('LOSS IDENTITY')
                    ->icon(Heroicon::ExclamationTriangle)
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('id')
                                ->label('LOSS ID')
                                ->weight(FontWeight::Bold)
                                ->color('primary'),

                            TextEntry::make('loss_category')
                                ->label('LOSS CATEGORY')
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'shortfall' => 'danger',
                                    'damage' => 'warning',
                                    'spoilage' => 'gray',
                                    'theft' => 'danger',
                                    'other' => 'info',
                                    default => 'gray',
                                }),

                            TextEntry::make('transferRequisition.reference_code')
                                ->label('REQUISITION REF')
                                ->weight(FontWeight::Bold)
                                ->color('primary')
                                ->copyable()
                                ->icon(Heroicon::Hashtag),

                            TextEntry::make('productVariant.sku')
                                ->label('SKU')
                                ->fontFamily('mono')
                                ->copyable(),

                            TextEntry::make('productVariant.name')
                                ->label('VARIANT NAME'),

                            TextEntry::make('warehouse.name')
                                ->label('WAREHOUSE')
                                ->weight(FontWeight::Bold)
                                ->icon(Heroicon::BuildingOffice),
                        ]),
                    ])->columnSpan(2),

                Section::make('QUANTITY BREAKDOWN')
                    ->icon(Heroicon::ArrowPath)
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('lost_base_qty')
                                ->label('LOST (BASE UNITS)')
                                ->weight(FontWeight::Bold)
                                ->color('danger'),

                            TextEntry::make('damaged_base_qty')
                                ->label('DAMAGED (BASE UNITS)')
                                ->weight(FontWeight::Bold)
                                ->color('warning')
                                ->placeholder('0'),

                            TextEntry::make('total_financial_loss')
                                ->label('TOTAL FINANCIAL LOSS')
                                ->money('PHP')
                                ->weight(FontWeight::Bold)
                                ->color('danger'),
                        ]),
                    ])->columnSpan(1),
            ]),

            Grid::make(2)->schema([
                Section::make('COST DETAILS')
                    ->icon(Heroicon::CurrencyDollar)
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('unit_cost_price')
                                ->label('UNIT COST PRICE (SNAPSHOT)')
                                ->money('PHP')
                                ->weight(FontWeight::Bold)
                                ->color('success'),

                            TextEntry::make('recorded_at')
                                ->label('RECORDED AT')
                                ->dateTime(),
                        ]),
                    ]),

                Section::make('AUDIT TRAIL')
                    ->icon(Heroicon::User)
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('recordedBy.name')
                                ->label('RECORDED BY')
                                ->placeholder('Unknown')
                                ->icon(Heroicon::User),

                            TextEntry::make('transferRequisitionItem.id')
                                ->label('REQUISITION ITEM ID')
                                ->placeholder('N/A'),
                        ]),
                    ]),
            ]),
        ]);
    }
}
