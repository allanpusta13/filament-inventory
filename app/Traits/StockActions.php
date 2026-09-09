<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\MovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

trait StockActions
{
    protected function receiveStockAction(): Action
    {
        return Action::make('receiveStock')
            ->label('Receive Stock')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->form([
                Select::make('product_id')
                    ->label('Product')
                    ->options(Product::pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('warehouse_id')
                    ->label('Warehouse')
                    ->options(fn (): Collection => $this->getWarehouseOptions())
                    ->searchable()
                    ->required(),
                TextInput::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->required()
                    ->minValue(1),
                TextInput::make('reference')
                    ->label('Reference')
                    ->maxLength(255),
            ])
            ->action(function (array $data): void {
                app(InventoryService::class)->recordMovement(
                    productId: (int) $data['product_id'],
                    warehouseId: (int) $data['warehouse_id'],
                    type: MovementType::Receive,
                    quantity: (int) $data['quantity'],
                    reference: $data['reference'] ?? null,
                );

                Notification::make()
                    ->title('Stock received')
                    ->success()
                    ->send();
            });
    }

    protected function shipStockAction(): Action
    {
        return Action::make('shipStock')
            ->label('Ship Stock')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('danger')
            ->form([
                Select::make('product_id')
                    ->label('Product')
                    ->options(Product::pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('warehouse_id')
                    ->label('Warehouse')
                    ->options(fn (): Collection => $this->getWarehouseOptions())
                    ->searchable()
                    ->required(),
                TextInput::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->required()
                    ->minValue(1),
                TextInput::make('reference')
                    ->label('Reference')
                    ->maxLength(255),
            ])
            ->action(function (array $data): void {
                try {
                    app(InventoryService::class)->ship(
                        productId: (int) $data['product_id'],
                        warehouseId: (int) $data['warehouse_id'],
                        quantity: (int) $data['quantity'],
                        reference: $data['reference'] ?? null,
                    );

                    Notification::make()
                        ->title('Stock shipped')
                        ->success()
                        ->send();
                } catch (InsufficientStockException $e) {
                    Notification::make()
                        ->title('Insufficient stock')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    protected function transferStockAction(): Action
    {
        return Action::make('transferStock')
            ->label('Transfer Stock')
            ->icon('heroicon-o-arrows-right-left')
            ->form([
                Select::make('product_id')
                    ->label('Product')
                    ->options(Product::pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('from_warehouse_id')
                    ->label('From Warehouse')
                    ->options(fn (): Collection => $this->getWarehouseOptions())
                    ->searchable()
                    ->required(),
                Select::make('to_warehouse_id')
                    ->label('To Warehouse')
                    ->options(fn (): Collection => Warehouse::where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                TextInput::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->required()
                    ->minValue(1),
                TextInput::make('reference')
                    ->label('Reference')
                    ->maxLength(255),
            ])
            ->action(function (array $data): void {
                app(InventoryService::class)->executeDirectTransfer(
                    productId: (int) $data['product_id'],
                    fromWarehouseId: (int) $data['from_warehouse_id'],
                    toWarehouseId: (int) $data['to_warehouse_id'],
                    quantity: (int) $data['quantity'],
                    reference: $data['reference'] ?? null,
                );

                Notification::make()
                    ->title('Stock transferred')
                    ->success()
                    ->send();
            });
    }

    protected function adjustmentAction(): Action
    {
        return Action::make('adjustment')
            ->label('Adjustment')
            ->icon('heroicon-o-calculator')
            ->color('warning')
            ->form([
                Select::make('product_id')
                    ->label('Product')
                    ->options(Product::pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('warehouse_id')
                    ->label('Warehouse')
                    ->options(fn (): Collection => $this->getWarehouseOptions())
                    ->searchable()
                    ->required(),
                TextInput::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->required()
                    ->helperText('Positive to add, negative to remove'),
                TextInput::make('reference')
                    ->label('Reason')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data): void {
                app(InventoryService::class)->recordMovement(
                    productId: (int) $data['product_id'],
                    warehouseId: (int) $data['warehouse_id'],
                    type: MovementType::Adjustment,
                    quantity: (int) $data['quantity'],
                    reference: $data['reference'],
                );

                Notification::make()
                    ->title('Stock adjusted')
                    ->success()
                    ->send();
            });
    }

    protected function quickReceiveAction(): Action
    {
        return Action::make('quickReceive')
            ->label('Quick Receive')
            ->icon('heroicon-o-plus')
            ->color('success')
            ->form([
                Select::make('warehouse_id')
                    ->label('Warehouse')
                    ->options(fn (): Collection => $this->getWarehouseOptions())
                    ->searchable()
                    ->required(),
                TextInput::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->required()
                    ->minValue(1),
                TextInput::make('reference')
                    ->label('Reference')
                    ->maxLength(255),
            ])
            ->action(function (array $data, $record): void {
                app(InventoryService::class)->recordMovement(
                    productId: $record->id,
                    warehouseId: (int) $data['warehouse_id'],
                    type: MovementType::Receive,
                    quantity: (int) $data['quantity'],
                    reference: $data['reference'] ?? null,
                );

                Notification::make()
                    ->title("Stock received for {$record->name}")
                    ->success()
                    ->send();
            });
    }

    /**
     * @return Collection<int, string>
     */
    protected function getWarehouseOptions(): Collection
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            return Warehouse::where('is_active', true)->pluck('name', 'id');
        }

        return $user->warehouses()->where('is_active', true)->pluck('name', 'id');
    }
}
