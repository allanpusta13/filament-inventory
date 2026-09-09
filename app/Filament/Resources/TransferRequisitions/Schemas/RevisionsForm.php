<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class RevisionsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('COUNTER-OFFER & SUBSTITUTE SWAP')
                ->icon(Heroicon::ArrowsRightLeft)
                ->columns(2)
                ->schema([
                    Select::make('substitute_product_variant_id')
                        ->label('PROPOSE SUBSTITUTE SKU')
                        ->relationship('substituteProductVariant', 'sku')
                        ->searchable()
                        ->preload()
                        ->placeholder('Original SKU Intact')
                        ->helperText('Swap out-of-stock items for an available alternative variant.'),

                    TextInput::make('proposed_qty')
                        ->label('PROPOSED QUANTITY')
                        ->numeric()
                        ->required()
                        ->minValue(1),

                    TextInput::make('proposed_unit_name')
                        ->label('PROPOSED PACKAGING FORMAT')
                        ->required(),

                    TextInput::make('proposed_unit_ratio')
                        ->label('UNIT CONVERSION RATIO')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->default(1),

                    Textarea::make('negotiation_reason')
                        ->label('REASON FOR COUNTER-OFFER')
                        ->required()
                        ->minLength(10)
                        ->placeholder('Detail stock availability or substitution reasoning...')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
