<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Pages;

use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditTransferRequisition extends EditRecord
{
    protected static string $resource = TransferRequisitionResource::class;

    public function getModalWidth(): string
    {
        return 'large';
    }

    public function getFormSchema(): Schema
    {
        return Schema::make()
            ->components([
                \Filament\Forms\Components\TextInput::make('reference_code')
                    ->label('REFERENCE CODE')
                    ->required()
                    ->maxLength(255)
                    ->disabled(),

                Select::make('from_warehouse_id')
                    ->label('ORIGIN WAREHOUSE')
                    ->relationship('fromWarehouse', 'name')
                    ->disabled(),

                Select::make('to_warehouse_id')
                    ->label('DESTINATION WAREHOUSE')
                    ->relationship('toWarehouse', 'name')
                    ->disabled(),

                Select::make('status')
                    ->label('STATUS')
                    ->options(TransferRequisitionStatus::class)
                    ->required(),

                Textarea::make('notes')
                    ->label('NOTES')
                    ->columnSpanFull()
                    ->rows(3),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
