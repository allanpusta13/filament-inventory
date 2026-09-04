<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Pages;

use App\Enums\TransferRequisitionStatus;
use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use App\Models\TransferRequisition;
use App\Services\AuditService;
use App\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class ViewTransferRequisition extends ViewRecord
{
    protected static string $resource = TransferRequisitionResource::class;

    public function mount(mixed $record): void
    {
        parent::mount($record);

        if (request()->query('scan') === '1' && in_array($this->record->status, [TransferRequisitionStatus::Dispatched, TransferRequisitionStatus::PartiallyReceived])) {
            $this->dispatch('open-modal', modal: 'scan_to_receive');
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Transfer Details')->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('reference_code'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('fromWarehouse.name')->label('Source Warehouse'),
                        TextEntry::make('toWarehouse.name')->label('Destination Warehouse'),
                        TextEntry::make('requestedBy.name')->label('Requested By'),
                        TextEntry::make('requested_at')->dateTime(),
                    ]),
                ]),
            ]);
    }

    public function getRecord(): TransferRequisition
    {
        return $this->record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submit')
                ->label('Submit Requisition')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('primary')
                ->visible(fn (TransferRequisition $record): bool => $record->status === TransferRequisitionStatus::Draft)
                ->action(function (TransferRequisition $record): void {
                    $oldStatus = $record->status->value;
                    $record->update([
                        'status' => TransferRequisitionStatus::Requested,
                    ]);

                    app(AuditService::class)->recordRequisition(
                        requisition: $record,
                        user: auth()->user(),
                        action: 'submitted',
                        changes: ['status' => ['old' => $oldStatus, 'new' => TransferRequisitionStatus::Requested->value]]
                    );
                }),

            Action::make('counter_offer')
                ->label('Counter-Offer')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->color('warning')
                ->visible(fn (TransferRequisition $record): bool => in_array($record->status, [TransferRequisitionStatus::UnderReviewFulfiller, TransferRequisitionStatus::UnderReviewRequestor, TransferRequisitionStatus::Requested]))
                ->schema([
                    \Filament\Forms\Components\Textarea::make('notes')
                        ->label('Negotiation Notes')
                        ->rows(3),
                ])
                ->action(function (TransferRequisition $record, array $data): void {
                    $oldStatus = $record->status;
                    $newStatus = $record->status === TransferRequisitionStatus::UnderReviewFulfiller
                        ? TransferRequisitionStatus::UnderReviewRequestor
                        : TransferRequisitionStatus::UnderReviewFulfiller;

                    $record->update([
                        'status' => $newStatus,
                        'notes' => $data['notes'] ?? $record->notes,
                    ]);

                    app(AuditService::class)->recordRequisition(
                        requisition: $record,
                        user: auth()->user(),
                        action: 'counter_offered',
                        changes: ['status' => ['old' => $oldStatus->value, 'new' => $newStatus->value]]
                    );
                }),

            Action::make('confirm')
                ->label('Confirm Requisition')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (TransferRequisition $record): bool => in_array($record->status, [TransferRequisitionStatus::Requested, TransferRequisitionStatus::UnderReviewFulfiller, TransferRequisitionStatus::UnderReviewRequestor]))
                ->requiresConfirmation()
                ->action(function (TransferRequisition $record): void {
                    app(InventoryService::class)->lockStockForRequisition($record->id);

                    Notification::make()
                        ->title('Requisition Confirmed')
                        ->success()
                        ->send();
                }),

            Action::make('dispatch')
                ->label('Dispatch')
                ->icon(Heroicon::OutlinedTruck)
                ->color('primary')
                ->visible(fn (TransferRequisition $record): bool => $record->status === TransferRequisitionStatus::Confirmed)
                ->requiresConfirmation()
                ->action(function (TransferRequisition $record): void {
                    app(InventoryService::class)->dispatchTransfer($record->id);

                    Notification::make()
                        ->title('Transfer Dispatched')
                        ->success()
                        ->send();
                }),

            DeleteAction::make()
                ->visible(fn (TransferRequisition $record): bool => $record->status === TransferRequisitionStatus::Draft && $record->requested_by === auth()->id()),

            Action::make('print_stn')
                ->label('Print STN Manifest')
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('gray')
                ->visible(fn (TransferRequisition $record): bool => in_array($record->status, [TransferRequisitionStatus::Dispatched, TransferRequisitionStatus::PartiallyReceived, TransferRequisitionStatus::Completed, TransferRequisitionStatus::ClosedWithLoss]))
                ->url(fn (TransferRequisition $record): string => route('stn.print', $record), shouldOpenInNewTab: true),

            Action::make('scan_to_receive')
                ->label('Scan to Receive')
                ->icon(Heroicon::OutlinedQrCode)
                ->color('success')
                ->visible(fn (TransferRequisition $record): bool => in_array($record->status, [TransferRequisitionStatus::Dispatched, TransferRequisitionStatus::PartiallyReceived]))
                ->schema([
                    Repeater::make('received_items')
                        ->label('Received Items')
                        ->schema([
                            TextInput::make('item_id')
                                ->dehydrated()
                                ->readOnly()
                                ->hidden(),
                            TextInput::make('variant_sku')
                                ->label('Variant')
                                ->readOnly(),
                            TextInput::make('expected_qty')
                                ->label('Expected (Base)')
                                ->readOnly(),
                            TextInput::make('good_qty')
                                ->label('Good Qty')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->default(fn (array $state): int => (int) ($state['expected_qty'] ?? 0)),
                            TextInput::make('damaged_qty')
                                ->label('Damaged Qty')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->default(0),
                            Select::make('loss_category')
                                ->label('Loss Category')
                                ->options([
                                    'Damaged in Transit' => 'Damaged in Transit',
                                    'Short Shipment' => 'Short Shipment',
                                    'Spoiled' => 'Spoiled',
                                    'Transit Variance' => 'Transit Variance',
                                ])
                                ->default('Transit Variance'),
                        ])
                        ->default(fn (TransferRequisition $record): array => $record->items->map(fn ($item) => [
                            'item_id' => $item->id,
                            'variant_sku' => $item->variant->sku.' - '.$item->variant->name,
                            'expected_qty' => $item->shipped_base_qty,
                        ])->toArray())
                        ->columns(1),
                ])
                ->action(function (TransferRequisition $record, array $data): void {
                    $receivedData = [];
                    foreach ($data['received_items'] as $item) {
                        $receivedData[$item['item_id']] = [
                            'good_qty' => (int) $item['good_qty'],
                            'damaged_qty' => (int) $item['damaged_qty'],
                            'loss_category' => $item['loss_category'] ?? 'Transit Variance',
                        ];
                    }

                    app(InventoryService::class)->scanToReceive($record->id, $receivedData);

                    Notification::make()
                        ->title('Receiving Complete')
                        ->body("Requisition {$record->reference_code} has been processed.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
