<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\StockMovement;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

final class RecentStockActivityWidget extends TableWidget
{
    protected static ?string $heading = 'Recent Stock Activity';

    protected static ?int $sort = 20;

    protected static ?string $description = 'Last 10 stock movements across all warehouses';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $query = StockMovement::query()
            ->with(['product', 'warehouse', 'createdBy'])
            ->latest();

        $user = auth()->user();

        if (! $user->isAdmin()) {
            $warehouseIds = $user->warehouses()->pluck('warehouses.id');
            $query->whereIn('warehouse_id', $warehouseIds);
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->limit(20),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (StockMovement $record): string => match ($record->type->value) {
                        'receive' => 'success',
                        'ship' => 'danger',
                        'transfer_out' => 'danger',
                        'transfer_in' => 'success',
                        'adjustment' => 'warning',
                    })
                    ->formatStateUsing(fn (StockMovement $record): string => match ($record->type->value) {
                        'receive' => 'Received',
                        'ship' => 'Shipped',
                        'transfer_out' => 'Transfer Out',
                        'transfer_in' => 'Transfer In',
                        'adjustment' => 'Adjustment',
                    }),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->sortable()
                    ->color(fn (StockMovement $record): string => $record->quantity > 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn (StockMovement $record): string => $record->quantity > 0 ? '+'.number_format($record->quantity) : number_format($record->quantity)),
                TextColumn::make('reference')
                    ->label('Reference')
                    ->limit(15)
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M j, g:i A')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10, 25])
            ->poll('60s');
    }
}
