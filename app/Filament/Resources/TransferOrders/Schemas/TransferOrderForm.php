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
            ->components([
                Section::make('Order Details')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('reference_number')
                                ->label('Reference Number')
                                ->disabled()
                                ->columnSpan(1),
                            Select::make('sender_branch_id')
                                ->label('From Branch')
                                ->relationship('sender', 'name')
                                ->searchable()
                                ->required()
                                ->disabled()
                                ->columnSpan(1),
                            Select::make('receiver_branch_id')
                                ->label('To Branch')
                                ->relationship('receiver', 'name')
                                ->searchable()
                                ->required()
                                ->disabled()
                                ->columnSpan(1),
                        ]),
                        Textarea::make('notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                Section::make('Line Items')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->required(),
                                TextInput::make('requested_quantity')
                                    ->label('Requested Quantity')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required(),
                                TextInput::make('approved_quantity')
                                    ->label('Approved Quantity')
                                    ->numeric()
                                    ->minValue(0),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel('Add Item')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
