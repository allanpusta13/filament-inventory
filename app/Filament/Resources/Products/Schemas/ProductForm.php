<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Product Variant')
                ->persistTabInQueryString()
                ->columnSpanFull()
                ->tabs([
                    Tab::make('Identity')
                        ->icon(Heroicon::Identification)
                        ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                        ->schema([
                            Select::make('product_id')
                                ->relationship('product', 'name')
                                ->prefixIcon(Heroicon::FolderOpen)
                                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                                ->required()
                                ->createOptionForm(fn (Schema $schema) => $schema->components([
                                    TextInput::make('name')
                                        ->prefixIcon(Heroicon::Identification)
                                        ->columnSpanFull()
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('category')
                                        ->prefixIcon(Heroicon::Tag)
                                        ->columnSpanFull(),
                                ])),

                            TextInput::make('sku')
                                ->prefixIcon(Heroicon::Tag)
                                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                                ->required()
                                ->unique(ignoreRecord: true),

                            TextInput::make('barcode')
                                ->prefixIcon(Heroicon::QrCode)
                                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                                ->nullable()
                                ->unique(ignoreRecord: true),

                            TextInput::make('name')
                                ->prefixIcon(Heroicon::Identification)
                                ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                                ->required(),
                        ]),

                    Tab::make('Stock & Pricing')
                        ->icon(Heroicon::CurrencyDollar)
                        ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                        ->schema([
                            TextInput::make('base_unit_name')
                                ->prefixIcon(Heroicon::Scale)
                                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                                ->required(),

                            TextInput::make('reorder_point')
                                ->prefixIcon(Heroicon::ExclamationTriangle)
                                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                                ->numeric()
                                ->default(0)
                                ->required(),

                            KeyValue::make('attributes')
                                ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2]),
                        ]),

                    Tab::make('Status')
                        ->icon(Heroicon::CheckCircle)
                        ->schema([
                            Toggle::make('is_active')
                                ->onIcon(Heroicon::CheckCircle)
                                ->offIcon(Heroicon::XCircle)
                                ->columnSpanFull()
                                ->default(true),
                        ]),
                ]),
        ]);
    }
}
