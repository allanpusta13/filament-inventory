<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectTransfers\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
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
        return $schema->components([
            Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])->schema([
                Section::make(__('resources.direct_transfers.infolist.profile'))
                    ->icon(Heroicon::ArrowPath)
                    ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->schema([
                        TextEntry::make('reference_code')
                            ->label(__('resources.direct_transfers.fields.reference_code'))
                            ->weight(FontWeight::Bold)
                            ->size('lg')
                            ->copyable()
                            ->color('primary'),

                        TextEntry::make('transferred_at')
                            ->label(__('resources.direct_transfers.fields.transferred_at'))
                            ->dateTime('M j, Y H:i'),

                        TextEntry::make('fromWarehouse.name')
                            ->label(__('resources.direct_transfers.fields.from_warehouse'))
                            ->icon(Heroicon::BuildingOffice),

                        TextEntry::make('toWarehouse.name')
                            ->label(__('resources.direct_transfers.fields.to_warehouse'))
                            ->icon(Heroicon::BuildingOffice2),

                        TextEntry::make('notes')
                            ->label(__('resources.direct_transfers.fields.notes'))
                            ->columnSpanFull()
                            ->placeholder(__('common.empty')),
                    ]),

                Section::make(__('resources.direct_transfers.infolist.authorization'))
                    ->icon(Heroicon::ShieldCheck)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->schema([
                        TextEntry::make('transferredBy.name')
                            ->label(__('resources.direct_transfers.fields.transferred_by'))
                            ->icon(Heroicon::User)
                            ->placeholder(__('common.empty')),
                    ]),

                Section::make(__('resources.direct_transfers.infolist.manifest'))
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->table([
                                TableColumn::make(__('resources.direct_transfers.fields.sku')),
                                TableColumn::make(__('resources.direct_transfers.fields.qty')),
                                TableColumn::make(__('resources.direct_transfers.fields.ratio')),
                                TableColumn::make(__('resources.direct_transfers.fields.base_qty')),
                                TableColumn::make(__('resources.direct_transfers.fields.notes')),
                            ])
                            ->schema([
                                TextEntry::make('productVariant.sku')
                                    ->weight(FontWeight::Bold),

                                TextEntry::make('qty')
                                    ->state(fn ($record) => "{$record->qty} {$record->unit_name}"),

                                TextEntry::make('unit_ratio')
                                    ->numeric()
                                    ->alignEnd(),

                                TextEntry::make('base_qty')
                                    ->numeric()
                                    ->alignEnd(),

                                TextEntry::make('notes')
                                    ->placeholder(__('common.empty')),
                            ]),
                    ]),
            ]),
        ]);
    }
}
