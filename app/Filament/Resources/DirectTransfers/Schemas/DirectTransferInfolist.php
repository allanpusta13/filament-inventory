<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectTransfers\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
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
                Section::make('DIRECT TRANSFER PROFILE')
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
                            ->columnSpanFull()
                            ->placeholder('—'),
                    ]),

                Section::make('AUTHORIZATION')
                    ->icon(Heroicon::ShieldCheck)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->schema([
                        TextEntry::make('transferredBy.name')
                            ->label(__('resources.direct_transfers.fields.transferred_by'))
                            ->icon(Heroicon::User)
                            ->placeholder('—'),
                    ]),

                Section::make('MATERIAL MANIFEST')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 3, 'xl' => 5])->schema([
                                    TextEntry::make('productVariant.sku')
                                        ->label(__('resources.direct_transfers.fields.sku'))
                                        ->weight(FontWeight::Bold),

                                    TextEntry::make('qty')
                                        ->label(__('resources.direct_transfers.fields.qty'))
                                        ->state(fn ($record) => "{$record->qty} {$record->unit_name}"),

                                    TextEntry::make('unit_ratio')
                                        ->label(__('resources.direct_transfers.fields.ratio')),

                                    TextEntry::make('base_qty')
                                        ->label(__('resources.direct_transfers.fields.base_qty'))
                                        ->numeric(),

                                    TextEntry::make('notes')
                                        ->placeholder('—'),
                                ]),
                            ]),
                    ]),
            ]),
        ]);
    }
}
