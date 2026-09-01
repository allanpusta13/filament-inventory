<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockMovements\Pages;

use App\Enums\MovementType;
use App\Exceptions\InsufficientStockException;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

final class ListStockMovements extends ListRecords
{
    protected static string $resource = StockMovementResource::class;

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->receiveStockAction(),
            $this->shipStockAction(),
            $this->transferStockAction(),
            $this->adjustmentAction(),
        ];
    }

    private function receiveStockAction(): Action
    {
        return Action::make('receiveStock')
            ->label('Receive Stock')
            ->icon('heroicon-o-arrow-down-tray')
            ->form([
                Select::make('product_id')
                    ->label('Product')
                    ->options(Product::pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('warehouse_id')
                    ->label('Warehouse')
                    ->options(fn (): \Illuminate\Support\Collection => $this->getWarehouseOptions())
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
                $variant = \App\Models\ProductVariant::where('product_id', $data['product_id'])->first();

                if ($variant === null) {
                    Notification::make()
                        ->title('No variant found for selected product')
                        ->danger()
                        ->send();

                    return;
                }

                app(InventoryService::class)->recordMovement(
                    variantId: $variant->id,
                    warehouseId: (int) $data['warehouse_id'],
                    type: MovementType::Receive,
                    baseQuantity: (int) $data['quantity'],
                    referenceCode: $data['reference'] ?? null,
                );

                Notification::make()
                    ->title('Stock received')
                    ->success()
                    ->send();
            });
    }

    private function shipStockAction(): Action
    {
        return Action::make('shipStock')
            ->label('Ship Stock')
            ->icon('heroicon-o-arrow-up-tray')
            ->form([
                Select::make('product_id')
                    ->label('Product')
                    ->options(Product::pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('warehouse_id')
                    ->label('Warehouse')
                    ->options(fn (): \Illuminate\Support\Collection => $this->getWarehouseOptions())
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
                    $variant = \App\Models\ProductVariant::where('product_id', $data['product_id'])->first();

                    if ($variant === null) {
                        Notification::make()
                            ->title('No variant found for selected product')
                            ->danger()
                            ->send();

                        return;
                    }

                    app(InventoryService::class)->recordMovement(
                        variantId: $variant->id,
                        warehouseId: (int) $data['warehouse_id'],
                        type: MovementType::Ship,
                        baseQuantity: -(int) $data['quantity'],
                        referenceCode: $data['reference'] ?? null,
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

    private function transferStockAction(): Action
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
                    ->options(fn (): \Illuminate\Support\Collection => $this->getWarehouseOptions())
                    ->searchable()
                    ->required(),
                Select::make('to_warehouse_id')
                    ->label('To Warehouse')
                    ->options(fn (): \Illuminate\Support\Collection => Warehouse::where('is_active', true)->pluck('name', 'id'))
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
                $variant = \App\Models\ProductVariant::where('product_id', $data['product_id'])->first();

                if ($variant === null) {
                    Notification::make()
                        ->title('No variant found for selected product')
                        ->danger()
                        ->send();

                    return;
                }

                // Record transfer out from source warehouse
                app(InventoryService::class)->recordMovement(
                    variantId: $variant->id,
                    warehouseId: (int) $data['from_warehouse_id'],
                    type: MovementType::TransferOut,
                    baseQuantity: -(int) $data['quantity'],
                    referenceCode: $data['reference'] ?? null,
                );

                // Record transfer in to destination warehouse
                app(InventoryService::class)->recordMovement(
                    variantId: $variant->id,
                    warehouseId: (int) $data['to_warehouse_id'],
                    type: MovementType::TransferIn,
                    baseQuantity: (int) $data['quantity'],
                    referenceCode: $data['reference'] ?? null,
                );

                Notification::make()
                    ->title('Stock transferred')
                    ->success()
                    ->send();
            });
    }

    private function adjustmentAction(): Action
    {
        return Action::make('adjustment')
            ->label('Adjustment')
            ->icon('heroicon-o-calculator')
            ->form([
                Select::make('product_id')
                    ->label('Product')
                    ->options(Product::pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('warehouse_id')
                    ->label('Warehouse')
                    ->options(fn (): \Illuminate\Support\Collection => $this->getWarehouseOptions())
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
                $variant = \App\Models\ProductVariant::where('product_id', $data['product_id'])->first();

                if ($variant === null) {
                    Notification::make()
                        ->title('No variant found for selected product')
                        ->danger()
                        ->send();

                    return;
                }

                app(InventoryService::class)->recordMovement(
                    variantId: $variant->id,
                    warehouseId: (int) $data['warehouse_id'],
                    type: MovementType::Adjustment,
                    baseQuantity: (int) $data['quantity'],
                    referenceCode: $data['reference'],
                );

                Notification::make()
                    ->title('Stock adjusted')
                    ->success()
                    ->send();
            });
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function getWarehouseOptions(): \Illuminate\Support\Collection
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            return Warehouse::where('is_active', true)->pluck('name', 'id');
        }

        return $user->warehouses()->where('is_active', true)->pluck('name', 'id');
    }
}
