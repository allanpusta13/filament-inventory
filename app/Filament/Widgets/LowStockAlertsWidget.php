<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\ProductVariant;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class LowStockAlertsWidget extends TableWidget
{
    protected static ?string $heading = 'Low Stock Alerts';

    protected int|string|array $columnSpan = 'full';

    protected int|string|array $columnSpanFull = 'full';

    public function getLowStockAlerts(): Collection
    {
        $user = auth()->user();

        // Hide sensitive low stock data from non-admin/auditor roles
        if (! ($user?->isAdmin() ?? false) && ! ($user?->isAuditor() ?? false)) {
            return collect();
        }

        $cacheKey = 'low_stock_alerts_'.auth()->id().'_'.optional(auth()->user()->warehouses->first())?->id;

        return Cache::remember($cacheKey, 300, function () use ($user) {
            $warehouseIds = $user->warehouses->pluck('id')->toArray();

            return ProductVariant::whereHas('stockMovements', function ($query) use ($warehouseIds) {
                $query->whereIn('warehouse_id', $warehouseIds);
            })
                ->get()
                ->filter(function ($variant) use ($warehouseIds) {
                    foreach ($warehouseIds as $warehouseId) {
                        if ($variant->availableQuantity($warehouseId) <= $variant->reorder_point) {
                            return true;
                        }
                    }

                    return false;
                })
                ->map(function ($variant) use ($warehouseIds) {
                    $warehouseData = [];
                    foreach ($warehouseIds as $warehouseId) {
                        $available = $variant->availableQuantity($warehouseId);
                        if ($available <= $variant->reorder_point) {
                            $warehouseData[] = [
                                'warehouse_id' => $warehouseId,
                                'current_stock' => $available,
                                'shortfall' => $variant->reorder_point - $available,
                            ];
                        }
                    }

                    return [
                        'variant_id' => $variant->id,
                        'sku' => $variant->sku,
                        'name' => $variant->name,
                        'reorder_point' => $variant->reorder_point,
                        'warehouses' => $warehouseData,
                    ];
                })
                ->values();
        });
    }

    public function table(Table $table): Table
    {
        $alerts = $this->getLowStockAlerts();

        return $table
            ->query(
                ProductVariant::whereIn('id', $alerts->pluck('variant_id'))
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
                    ->label('AVAILABLE STOCK')
                    ->getStateUsing(function ($record) use ($alerts) {
                        $alert = $this->getAlertForVariant($alerts, $record->id);

                        return $alert['warehouses'][0]['current_stock'] ?? 0;
                    })
                    ->numeric()
                    ->sortable()
                    ->color(fn ($state, $record) => $state <= $record->reorder_point ? 'danger' : 'success'),

                TextColumn::make('shortfall')
                    ->label('SHORTFALL')
                    ->getStateUsing(function ($record) use ($alerts) {
                        $alert = $this->getAlertForVariant($alerts, $record->id);

                        return $alert['warehouses'][0]['shortfall'] ?? 0;
                    })
                    ->numeric()
                    ->color('danger'),
            ])
            ->paginated(false)
            ->defaultSort('id', 'desc');
    }

    private function getAlertForVariant(Collection $alerts, int $variantId): ?array
    {
        return $alerts->firstWhere('variant_id', $variantId);
    }
}
