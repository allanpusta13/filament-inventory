<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Pages;

use App\Enums\TransferRequisitionStatus;
use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use App\Services\InventoryService;
use App\Services\NegotiationService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class ViewTransferRequisition extends ViewRecord
{
    protected static string $resource = TransferRequisitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->modalWidth(Width::Large),

            Action::make('confirm')
                ->label('CONFIRM')
                ->icon(Heroicon::CheckBadge)
                ->color('primary')
                ->authorize('confirm')
                ->visible(fn ($record) => in_array($record->status?->value, [
                    'requested',
                    'under_review_fulfiller',
                    'under_review_requestor',
                ], true))
                ->action(function ($record) {
                    app(NegotiationService::class)
                        ->materializeRequestedAsApproved($record);
                    $record->update([
                        'status' => TransferRequisitionStatus::Confirmed->value,
                        'approved_at' => now(),
                        'approved_by' => auth()->id(),
                    ]);
                })
                ->requiresConfirmation()
                ->modalWidth(Width::Large),

            Action::make('dispatch')
                ->label('DISPATCH')
                ->icon(Heroicon::Truck)
                ->color('primary')
                ->authorize('dispatch')
                ->visible(fn ($record) => $record->status?->value === TransferRequisitionStatus::Confirmed->value)
                ->action(function ($record) {
                    app(InventoryService::class)
                        ->dispatchTransfer($record);
                    $record->update([
                        'status' => TransferRequisitionStatus::Dispatched->value,
                        'dispatched_at' => now(),
                        'dispatched_by' => auth()->id(),
                    ]);
                })
                ->requiresConfirmation()
                ->modalWidth(Width::Large),

            Action::make('scanToReceive')
                ->label('SCAN TO RECEIVE')
                ->name('scanToReceive')
                ->icon(Heroicon::QrCode)
                ->color('success')
                ->authorize('receive')
                ->visible(fn ($record) => in_array($record->status?->value, [
                    'dispatched',
                    'partially_received',
                ], true))
                ->url(fn ($record) => route('stn.scan', ['transferRequisition' => $record->id])),

            Action::make('recordLoss')
                ->label('RECORD LOSS')
                ->name('recordLoss')
                ->icon(Heroicon::ExclamationTriangle)
                ->color('danger')
                ->authorize('recordLoss')
                ->visible(fn ($record) => in_array($record->status?->value, [
                    'dispatched',
                    'partially_received',
                ], true))
                ->modalWidth(Width::Large)
                ->schema([
                    \Filament\Forms\Components\Select::make('product_variant_id')
                        ->label('Product Variant')
                        ->options(fn ($record) => $record->items->pluck('productVariant.name', 'product_variant_id')->toArray())
                        ->required()
                        ->searchable(),
                    \Filament\Forms\Components\TextInput::make('lost_base_qty')
                        ->label('Lost Quantity (Base Units)')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                    \Filament\Forms\Components\TextInput::make('damaged_base_qty')
                        ->label('Damaged Quantity (Base Units)')
                        ->numeric()
                        ->default(0)
                        ->minValue(0),
                    \Filament\Forms\Components\TextInput::make('total_financial_loss')
                        ->label('Total Financial Loss')
                        ->numeric()
                        ->minValue(0)
                        ->placeholder('Auto-calculated')
                        ->dehydrated(false),
                ])
                ->action(function (array $data, $record) {
                    $variant = \App\Models\ProductVariant::with('currentPrice')->find($data['product_variant_id']);
                    $unitCost = \App\Models\LossLedger::snapshotUnitCostFrom($variant);
                    $data['total_financial_loss'] = \App\Models\LossLedger::calculateTotalFinancialLoss($unitCost, $data['lost_base_qty'], $data['damaged_base_qty']);
                    \App\Models\LossLedger::create([
                        'transfer_requisition_id' => $record->id,
                        'product_variant_id' => $data['product_variant_id'],
                        'lost_base_qty' => $data['lost_base_qty'],
                        'damaged_base_qty' => $data['damaged_base_qty'],
                        'total_financial_loss' => $data['total_financial_loss'],
                    ]);
                })
                ->requiresConfirmation(),

            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
