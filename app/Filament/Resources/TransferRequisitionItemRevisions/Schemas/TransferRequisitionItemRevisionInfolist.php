<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitionItemRevisions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class TransferRequisitionItemRevisionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Grid::make(2)->schema([
                Section::make('REVISION IDENTITY')
                    ->icon(Heroicon::PencilSquare)
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('id')
                                ->label('REVISION ID')
                                ->weight(FontWeight::Bold)
                                ->color('primary'),

                            TextEntry::make('status')
                                ->label('STATUS')
                                ->badge()
                                ->color(fn ($state): string => match ($state) {
                                    'pending' => 'warning',
                                    'accepted' => 'success',
                                    'rejected' => 'danger',
                                    'countered' => 'info',
                                    default => 'gray',
                                }),

                            TextEntry::make('side')
                                ->label('NEGOTIATION SIDE')
                                ->badge()
                                ->color(fn ($state): string => match ($state) {
                                    'fulfiller' => 'primary',
                                    'requestor' => 'success',
                                    default => 'gray',
                                }),

                            TextEntry::make('transferRequisitionItem.transferRequisition.reference_code')
                                ->label('REQUISITION REF')
                                ->weight(FontWeight::Bold)
                                ->color('primary')
                                ->copyable()
                                ->icon(Heroicon::Hashtag),

                            TextEntry::make('productVariant.sku')
                                ->label('VARIANT SKU')
                                ->fontFamily('mono')
                                ->copyable(),

                            TextEntry::make('productVariant.name')
                                ->label('VARIANT NAME'),
                        ]),
                    ])->columnSpan(1),

                Section::make('PROPOSED CHANGES')
                    ->icon(Heroicon::ArrowPath)
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('proposed_unit_name')
                                ->label('UNIT NAME')
                                ->placeholder('—'),

                            TextEntry::make('proposed_unit_ratio')
                                ->label('UNIT RATIO')
                                ->numeric()
                                ->placeholder('1'),

                            TextEntry::make('proposed_qty')
                                ->label('QTY')
                                ->numeric()
                                ->placeholder('0'),

                            TextEntry::make('proposed_base_qty')
                                ->label('BASE QTY')
                                ->weight(FontWeight::Bold)
                                ->numeric()
                                ->color('primary'),
                        ]),
                    ])->columnSpan(1),
            ]),

            Grid::make(2)->schema([
                Section::make('SUBSTITUTION')
                    ->icon(Heroicon::ArrowRightOnRectangle)
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('substituteProductVariant.sku')
                                ->label('SUBSTITUTE SKU')
                                ->fontFamily('mono')
                                ->copyable()
                                ->placeholder('None (original variant)'),

                            TextEntry::make('substituteProductVariant.name')
                                ->label('SUBSTITUTE NAME')
                                ->placeholder('None'),
                        ]),
                    ]),

                Section::make('AUDIT TRAIL')
                    ->icon(Heroicon::User)
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('user.name')
                                ->label('PROPOSED BY')
                                ->placeholder('Unknown')
                                ->icon(Heroicon::User),

                            TextEntry::make('negotiation_reason')
                                ->label('REASON')
                                ->columnSpanFull()
                                ->placeholder('—'),

                            TextEntry::make('responded_at')
                                ->label('RESPONDED AT')
                                ->dateTime()
                                ->placeholder('—'),

                            TextEntry::make('created_at')
                                ->label('CREATED AT')
                                ->dateTime(),
                        ]),
                    ]),
            ]),
        ]);
    }
}
