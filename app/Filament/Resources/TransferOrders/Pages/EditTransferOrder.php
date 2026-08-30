<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferOrders\Pages;

use App\Filament\Resources\TransferOrders\TransferOrderResource;
use App\Models\TransferOrder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

final class EditTransferOrder extends EditRecord
{
    protected static string $resource = TransferOrderResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var TransferOrder $order */
        $order = $this->getRecord();

        if (! $order->isEditable()) {
            Notification::make()
                ->title('This transfer order cannot be edited in its current status.')
                ->danger()
                ->send();

            $this->redirect(self::getResource()::getUrl('index'));
        }
    }
}
