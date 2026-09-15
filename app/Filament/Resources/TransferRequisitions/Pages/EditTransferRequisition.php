<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Pages;

use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use App\Services\NegotiationService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

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
            Action::make('confirm')
                ->label('CONFIRM')
                ->icon(Heroicon::CheckBadge)
                ->color('primary')
                ->authorize('confirm')
                ->visible(fn ($record) => in_array($record->status, [
                    'requested',
                    'under_review_fulfiller',
                    'under_review_requestor',
                ], true))
                ->action(function ($record) {
                    app(NegotiationService::class)
                        ->materializeRequestedAsApproved($record);
                    $record->update([
                        'status' => 'confirmed',
                        'approved_at' => now(),
                        'approved_by' => auth()->id(),
                    ]);
                })
                ->requiresConfirmation()
                ->modalWidth(Width::Large),

            DeleteAction::make(),
        ];
    }
}
