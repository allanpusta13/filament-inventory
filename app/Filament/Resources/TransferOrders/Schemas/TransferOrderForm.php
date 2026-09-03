<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferOrders\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class TransferOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Transfer Details')->schema([
                    Grid::make(2)->schema([
                        Select::make('sender_branch_id')
                            ->label('Source Warehouse (Sender)')
                            ->relationship('sender', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit'),
                        Select::make('receiver_branch_id')
                            ->label('Destination Warehouse (Receiver)')
                            ->relationship('receiver', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit'),
                    ]),
                    Textarea::make('notes')
                        ->rows(3),
                ]),
                Section::make('Line Items')->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->schema([
                            Select::make('product_id')
                                ->label('Product')
                                ->relationship('product', 'name')
                                ->searchable()
                                ->required()
                                ->live(onBlur: true),
                            TextInput::make('requested_quantity')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->default(1),
                        ])
                        ->columns(2)
                        ->addActionLabel('Add Item')
                        ->defaultItems(1),
                ]),
            ]);
    }
}
