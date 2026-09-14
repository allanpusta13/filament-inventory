<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\ProductVariant;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Cache;

class LowStockAlertsWidget extends TableWidget
{
    protected static ?string $heading = 'Low Stock Alerts';

    protected int|string|array $columnSpan = 'full';

    protected int|string|array $columnSpanFull = 'full';

    public function getLowStockAlerts()
    {
        $cacheKey = 'low_stock_alerts_'.auth()->id().'_'.optional(auth()->user()->warehouses->first())?->id;

        return Cache::remember($cacheKey, 300, function () {
            $user = auth()->user();
            $warehouseIds = $user->warehouses->pluck('id')->toArray();

            return ProductVariant::whereHas('stockMovements', function ($query) use ($warehouseIds) {
                $query->whereIn('warehouse_id', $warehouseIds);
            })
                ->with(['stockMovements' => function ($query) use ($warehouseIds) {
                    $query->whereIn('warehouse_id', $warehouseIds);
                }])
                ->get()
                ->filter(function ($variant) use ($warehouseIds) {
                    $totalStock = $variant->stockMovements
                        ->whereIn('warehouse_id', $warehouseIds)
                        ->sum('quantity');

                    return $totalStock <= $variant->reorder_point;
                })
                ->map(function ($variant) use ($warehouseIds) {
                    $totalStock = $variant->stockMovements
                        ->whereIn('warehouse_id', $warehouseIds)
                        ->sum('quantity');

                    return [
                        'variant_id' => $variant->id,
                        'sku' => $variant->sku,
                        'name' => $variant->name,
                        'reorder_point' => $variant->reorder_point,
                        'current_stock' => $variant->stockMovements
                            ->whereIn('warehouse_id', $warehouseIds)
                            ->sum('quantity'),
                        'shortfall' => $variant->reorder_point - $variant->stockMovements
                            ->whereIn('warehouse_id', $warehouseIds)
                            ->sum('quantity'),
                    ];
                })
                ->values();
        });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ProductVariant::whereHas('stockMovements', function ($query) {
                    $query->whereIn('warehouse_id', auth()->user()->warehouses->pluck('id'));
                })
            )
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('name')
                    ->label('NAME')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('reorder_point')
                    ->label('REORDER POINT')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('current_stock')
                    ->label('CURRENT STOCK')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($state, $record) => $state <= $record->reorder_point ? 'danger' : 'success'),

                TextColumn::make('shortfall')
                    ->label('SHORTFALL')
                    ->numeric()
                    ->sortable()
                    ->color('danger'),
            ])
            ->paginated(false)
            ->defaultSort('shortfall', 'desc');
    }
}
