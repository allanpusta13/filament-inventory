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
use App\Models\TransferOrderItem;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

final class ListTransferOrders extends ListRecords
{
    protected static string $resource = TransferOrderResource::class;

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->createTransferOrderAction(),
        ];
    }

    /**
     * @return list<Action>
     */
    protected function getRecordActions(): array
    {
        return [
            $this->submitOrderAction(),
            $this->reviewOrderAction(),
            $this->confirmOrderAction(),
            $this->dispatchOrderAction(),
            $this->receiveOrderAction(),
            $this->cancelOrderAction(),
        ];
    }

    private function createTransferOrderAction(): Action
    {
        return Action::make('createTransferOrder')
            ->label('New Transfer Order')
            ->icon('heroicon-o-plus')
            ->url(fn (): string => self::getResource()::getUrl('create'));
    }

    private function submitOrderAction(): Action
    {
        return Action::make('submitOrder')
            ->label('Submit')
            ->icon('heroicon-o-paper-airplane')
            ->color('warning')
            ->visible(fn (TransferOrder $record): bool => $record->status === TransferOrderStatus::Draft)
            ->requiresConfirmation()
            ->modalHeading('Submit Transfer Order')
            ->modalSubmitActionLabel('Submit')
            ->action(function (TransferOrder $record): void {
                try {
                    app(SubmitTransferAction::class)->submit($record, auth()->user());

                    Notification::make()
                        ->title('Transfer order submitted')
                        ->success()
                        ->send();
                } catch (Exception $e) {
                    Notification::make()
                        ->title('Submit failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    private function reviewOrderAction(): Action
    {
        return Action::make('reviewOrder')
            ->label('Review')
            ->icon('heroicon-o-magnifying-glass')
            ->color('info')
            ->visible(fn (TransferOrder $record): bool => $record->status->canBeReviewed())
            ->schema([
                Repeater::make('items')
                    ->label('Review Items')
                    ->schema([
                        TextInput::make('transfer_order_item_id')
                            ->hidden()
                            ->dehydrated(),
                        TextInput::make('product_name')
                            ->label('Product')
                            ->disabled(),
                        TextInput::make('requested_quantity')
                            ->label('Requested')
                            ->disabled(),
                        TextInput::make('approved_quantity')
                            ->label('Approved')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ])
                    ->columns(1)
                    ->deletable(false)
                    ->addable(false)
                    ->reorderable(false),
                Textarea::make('notes')
                    ->label('Review Notes')
                    ->rows(2),
            ])
            ->fillForm(function (TransferOrder $record): array {
                $record->load('items.product');

                return [
                    'items' => $record->items->map(fn (TransferOrderItem $item) => [
                        'transfer_order_item_id' => $item->id,
                        'product_name' => $item->product->name,
                        'requested_quantity' => $item->requested_quantity,
                        'approved_quantity' => $item->approved_quantity ?? $item->requested_quantity,
                    ])->toArray(),
                ];
            })
            ->action(function (array $data, TransferOrder $record): void {
                try {
                    app(ReviewTransferAction::class)->review(
                        $record,
                        auth()->user(),
                        $data['items'],
                        $data['notes'] ?? null,
                    );

                    Notification::make()
                        ->title('Transfer order reviewed')
                        ->success()
                        ->send();
                } catch (Exception $e) {
                    Notification::make()
                        ->title('Review failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    private function confirmOrderAction(): Action
    {
        return Action::make('confirmOrder')
            ->label('Confirm')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (TransferOrder $record): bool => $record->status->canBeConfirmed())
            ->requiresConfirmation()
            ->modalHeading('Confirm Transfer Order')
            ->modalSubmitActionLabel('Confirm')
            ->action(function (TransferOrder $record): void {
                try {
                    app(ConfirmTransferAction::class)->confirm($record, auth()->user());

                    Notification::make()
                        ->title('Transfer order confirmed')
                        ->success()
                        ->send();
                } catch (Exception $e) {
                    Notification::make()
                        ->title('Confirm failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    private function dispatchOrderAction(): Action
    {
        return Action::make('dispatchOrder')
            ->label('Dispatch')
            ->icon('heroicon-o-arrow-right')
            ->color('primary')
            ->visible(fn (TransferOrder $record): bool => $record->status === TransferOrderStatus::Confirmed)
            ->requiresConfirmation()
            ->modalHeading('Dispatch Transfer Order')
            ->modalSubmitActionLabel('Dispatch')
            ->action(function (TransferOrder $record): void {
                try {
                    app(DispatchTransferAction::class)->dispatch($record, auth()->user());

                    Notification::make()
                        ->title('Transfer order dispatched')
                        ->success()
                        ->send();
                } catch (Exception $e) {
                    Notification::make()
                        ->title('Dispatch failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    private function receiveOrderAction(): Action
    {
        return Action::make('receiveOrder')
            ->label('Receive')
            ->icon('heroicon-o-arrow-down')
            ->color('success')
            ->visible(fn (TransferOrder $record): bool => $record->status === TransferOrderStatus::Dispatched)
            ->schema([
                Repeater::make('items')
                    ->label('Received Items')
                    ->schema([
                        TextInput::make('transfer_order_item_id')
                            ->hidden()
                            ->dehydrated(),
                        TextInput::make('product_name')
                            ->label('Product')
                            ->disabled(),
                        TextInput::make('approved_quantity')
                            ->label('Approved')
                            ->disabled(),
                        TextInput::make('quantity_received')
                            ->label('Received')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        Textarea::make('variance_reason')
                            ->label('Reason for discrepancy')
                            ->rows(2)
                            ->visible(function (mixed $get): bool {
                                $approved = (int) ($get('approved_quantity') ?? 0);
                                $received = (int) ($get('quantity_received') ?? 0);

                                return $received < $approved && $received > 0;
                            }),
                    ])
                    ->columns(1)
                    ->deletable(false)
                    ->addable(false)
                    ->reorderable(false),
            ])
            ->fillForm(function (TransferOrder $record): array {
                $record->load('items.product');

                return [
                    'items' => $record->items->map(fn (TransferOrderItem $item) => [
                        'transfer_order_item_id' => $item->id,
                        'product_name' => $item->product->name,
                        'approved_quantity' => $item->approved_quantity ?? $item->requested_quantity,
                        'quantity_received' => $item->received_quantity ?? $item->approved_quantity ?? $item->requested_quantity,
                        'variance_reason' => $item->variance_reason,
                    ])->toArray(),
                ];
            })
            ->action(function (array $data, TransferOrder $record): void {
                try {
                    app(ReceiveTransferAction::class)->receive(
                        $record,
                        auth()->user(),
                        $data['items'],
                    );

                    Notification::make()
                        ->title('Transfer order received')
                        ->success()
                        ->send();
                } catch (Exception $e) {
                    Notification::make()
                        ->title('Receive failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    private function cancelOrderAction(): Action
    {
        return Action::make('cancelOrder')
            ->label('Cancel')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (TransferOrder $record): bool => $record->status->canBeCancelled())
            ->requiresConfirmation()
            ->modalHeading('Cancel Transfer Order')
            ->modalSubmitActionLabel('Cancel Order')
            ->schema([
                Textarea::make('reason')
                    ->label('Cancellation Reason')
                    ->rows(3),
            ])
            ->action(function (array $data, TransferOrder $record): void {
                try {
                    app(CancelTransferAction::class)->cancel(
                        $record,
                        auth()->user(),
                        $data['reason'] ?? null,
                    );

                    Notification::make()
                        ->title('Transfer order cancelled')
                        ->success()
                        ->send();
                } catch (Exception $e) {
                    Notification::make()
                        ->title('Cancel failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
