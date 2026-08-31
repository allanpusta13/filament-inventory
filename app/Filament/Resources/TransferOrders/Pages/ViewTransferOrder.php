<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferOrders\Pages;

use App\Actions\CancelTransferAction;
use App\Actions\ConfirmTransferAction;
use App\Actions\DispatchTransferAction;
use App\Actions\ReceiveTransferAction;
use App\Actions\ReviewTransferAction;
use App\Actions\SubmitTransferAction;
use App\Enums\TransferOrderStatus;
use App\Filament\Resources\TransferOrders\TransferOrderResource;
use App\Models\TransferOrder;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Throwable;

final class ViewTransferOrder extends ViewRecord
{
    protected static string $resource = TransferOrderResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Transfer Details')->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('reference_number'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('sender.name')->label('Source Warehouse'),
                        TextEntry::make('receiver.name')->label('Destination Warehouse'),
                        TextEntry::make('driver_name'),
                        TextEntry::make('vehicle_plate'),
                        TextEntry::make('dispatched_at')->dateTime(),
                        TextEntry::make('received_at')->dateTime(),
                        TextEntry::make('notes')->columnSpanFull(),
                    ]),
                ]),
                Section::make('Line Items')->schema([
                    TextEntry::make('items.product.name')
                        ->label('Product')
                        ->listWithLineBreaks(),
                    TextEntry::make('items.requested_quantity')
                        ->label('Requested'),
                    TextEntry::make('items.approved_quantity')
                        ->label('Approved'),
                    TextEntry::make('items.item_status')
                        ->label('Status')
                        ->badge(),
                ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submit')
                ->label('Submit Order')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn (TransferOrder $record): bool => $record->status === TransferOrderStatus::Draft)
                ->requiresConfirmation()
                ->action(function (TransferOrder $record): void {
                    try {
                        app(SubmitTransferAction::class)->submit($record, auth()->user());

                        Notification::make()
                            ->title('Order Submitted')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Submission Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('review')
                ->label('Review Order')
                ->icon('heroicon-o-magnifying-glass')
                ->color('warning')
                ->visible(fn (TransferOrder $record): bool => $record->status->canBeReviewed())
                ->schema([
                    Repeater::make('review_items')
                        ->label('Review Items')
                        ->schema([
                            TextInput::make('transfer_order_item_id')
                                ->readOnly()
                                ->hidden(),
                            TextInput::make('product_name')
                                ->label('Product')
                                ->readOnly(),
                            TextInput::make('requested_quantity')
                                ->label('Requested')
                                ->readOnly(),
                            TextInput::make('approved_quantity')
                                ->label('Approved Qty')
                                ->numeric()
                                ->required()
                                ->minValue(0),
                        ])
                        ->columns(4)
                        ->default(fn (TransferOrder $record): array => $record->items->map(fn ($item) => [
                            'transfer_order_item_id' => $item->id,
                            'product_name' => $item->product?->name ?? 'Unknown',
                            'requested_quantity' => $item->requested_quantity,
                            'approved_quantity' => $item->approved_quantity ?? $item->requested_quantity,
                        ])->toArray()),
                    Textarea::make('review_notes')
                        ->label('Review Notes')
                        ->rows(2),
                ])
                ->action(function (TransferOrder $record, array $data): void {
                    try {
                        app(ReviewTransferAction::class)->review(
                            order: $record,
                            user: auth()->user(),
                            items: $data['review_items'],
                            notes: $data['review_notes'] ?? null,
                        );

                        Notification::make()
                            ->title('Review Submitted')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Review Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('confirm')
                ->label('Confirm Order')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (TransferOrder $record): bool => $record->status->canBeConfirmed())
                ->requiresConfirmation()
                ->action(function (TransferOrder $record): void {
                    try {
                        app(ConfirmTransferAction::class)->confirm($record, auth()->user());

                        Notification::make()
                            ->title('Order Confirmed')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Confirmation Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('dispatch')
                ->label('Dispatch Order')
                ->icon('heroicon-o-truck')
                ->color('primary')
                ->visible(fn (TransferOrder $record): bool => $record->status === TransferOrderStatus::Confirmed)
                ->schema([
                    TextInput::make('driver_name')
                        ->label('Driver Name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('vehicle_plate')
                        ->label('Vehicle Plate')
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (TransferOrder $record, array $data): void {
                    try {
                        $record->update([
                            'driver_name' => $data['driver_name'],
                            'vehicle_plate' => $data['vehicle_plate'],
                        ]);

                        app(DispatchTransferAction::class)->dispatch($record, auth()->user());

                        Notification::make()
                            ->title('Order Dispatched')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Dispatch Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('receive')
                ->label('Receive Order')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('success')
                ->visible(fn (TransferOrder $record): bool => $record->status === TransferOrderStatus::Dispatched)
                ->schema([
                    Repeater::make('receive_items')
                        ->label('Receive Items')
                        ->schema([
                            TextInput::make('transfer_order_item_id')
                                ->readOnly()
                                ->hidden(),
                            TextInput::make('product_name')
                                ->label('Product')
                                ->readOnly(),
                            TextInput::make('approved_quantity')
                                ->label('Approved Qty')
                                ->readOnly(),
                            TextInput::make('quantity_received')
                                ->label('Received Qty')
                                ->numeric()
                                ->required()
                                ->minValue(0),
                            TextInput::make('damaged_quantity')
                                ->label('Damaged Qty')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->default(0),
                            Textarea::make('variance_reason')
                                ->label('Reason')
                                ->rows(2),
                        ])
                        ->columns(4)
                        ->default(fn (TransferOrder $record): array => $record->items
                            ->filter(fn ($item) => $item->item_status->value !== 'removed')
                            ->map(fn ($item) => [
                                'transfer_order_item_id' => $item->id,
                                'product_name' => $item->product?->name ?? 'Unknown',
                                'approved_quantity' => $item->approved_quantity ?? $item->requested_quantity,
                                'quantity_received' => $item->approved_quantity ?? $item->requested_quantity,
                                'damaged_quantity' => 0,
                            ])->toArray()),
                ])
                ->action(function (TransferOrder $record, array $data): void {
                    try {
                        app(ReceiveTransferAction::class)->receive(
                            order: $record,
                            user: auth()->user(),
                            items: $data['receive_items'],
                        );

                        Notification::make()
                            ->title('Order Received')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Receiving Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('cancel')
                ->label('Cancel Order')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (TransferOrder $record): bool => $record->status->canBeCancelled())
                ->schema([
                    Textarea::make('cancel_reason')
                        ->label('Cancellation Reason')
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->action(function (TransferOrder $record, array $data): void {
                    try {
                        app(CancelTransferAction::class)->cancel(
                            order: $record,
                            user: auth()->user(),
                            reason: $data['cancel_reason'] ?? null,
                        );

                        Notification::make()
                            ->title('Order Cancelled')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Cancellation Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            DeleteAction::make()
                ->visible(fn (TransferOrder $record): bool => $record->status === TransferOrderStatus::Draft),
        ];
    }
}
