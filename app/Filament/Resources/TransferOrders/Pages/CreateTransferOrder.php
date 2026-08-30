<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferOrders\Pages;

use App\Filament\Resources\TransferOrders\TransferOrderResource;
use App\Models\TransferOrder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard\Step;

final class CreateTransferOrder extends CreateRecord
{
    use HasWizard;

    protected static string $resource = TransferOrderResource::class;

    /**
     * @return array<int, Step>
     */
    protected function getSteps(): array
    {
        return [
            Step::make('Order Details')
                ->description('Select branches and add notes')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('sender_branch_id')
                            ->label('From Branch')
                            ->relationship('sender', 'name')
                            ->searchable()
                            ->required()
                            ->columnSpan(1),
                        Select::make('receiver_branch_id')
                            ->label('To Branch')
                            ->relationship('receiver', 'name')
                            ->searchable()
                            ->required()
                            ->columnSpan(1),
                    ]),
                    Textarea::make('notes')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
            Step::make('Line Items')
                ->description('Add products and quantities')
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
                        ])
                        ->columns(2)
                        ->defaultItems(1)
                        ->addActionLabel('Add Item')
                        ->columnSpanFull(),
                ]),
            Step::make('Review')
                ->description('Review your transfer order')
                ->schema([
                    Section::make('Order Summary')
                        ->schema([
                            TextInput::make('reference_number')
                                ->label('Reference Number')
                                ->disabled()
                                ->dehydrated()
                                ->default(fn () => TransferOrder::generateReferenceNumber()),
                            Select::make('sender_branch_id')
                                ->label('From Branch')
                                ->relationship('sender', 'name')
                                ->disabled(),
                            Select::make('receiver_branch_id')
                                ->label('To Branch')
                                ->relationship('receiver', 'name')
                                ->disabled(),
                            Textarea::make('notes')
                                ->disabled(),
                        ]),
                ]),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reference_number'] = TransferOrder::generateReferenceNumber();

        return $data;
    }
}
