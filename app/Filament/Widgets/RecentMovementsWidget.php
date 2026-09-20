<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\StockMovement;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Cache;

class RecentMovementsWidget extends TableWidget
{
    protected static ?string $heading = 'Recent Movements';

    protected int|string|array $columnSpan = 'full';

    protected int|string|array $columnSpanFull = 'full';

    public function getRecentMovements(bool $bypassCache = false)
    {
        $user = auth()->user();

        // Hide sensitive movement data from non-admin/auditor roles
        if (! ($user?->isAdmin() ?? false) && ! ($user?->isAuditor() ?? false)) {
            return collect();
        }

        $firstWarehouseId = optional($user->warehouses->first())?->id;
        $cacheKey = 'recent_movements_'.$user->id.'_'.$firstWarehouseId;

        if ($bypassCache) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 60, function () use ($user) {
            $warehouseIds = $user->warehouses->pluck('id')->toArray();

            $query = StockMovement::whereIn('warehouse_id', $warehouseIds)
                ->with(['productVariant.product', 'warehouse', 'createdBy'])
                ->where('created_at', '>=', now()->subDays(365))
                ->latest('created_at')
                ->limit(20);

            $results = $query->get();

            return $results->map(function ($movement) {
                return [
                    'id' => $movement->id,
                    'variant_sku' => $movement->productVariant?->sku ?? 'N/A',
                    'variant_name' => $movement->productVariant?->name ?? 'N/A',
                    'warehouse_id' => $movement->warehouse_id,
                    'warehouse_name' => $movement->warehouse?->name ?? 'N/A',
                    'type' => $movement->type,
                    'quantity' => $movement->quantity,
                    'unit_name' => $movement->unit_name_used,
                    'created_at' => $movement->created_at,
                    'created_by_name' => $movement->createdBy?->name ?? 'System',
                    'reference_code' => $movement->reference_code,
                ];
            });
        });
    }

    public function table(Table $table): Table
    {
        $movements = $this->getRecentMovements();

        return $table
            ->query(
                StockMovement::whereIn('id', $movements->pluck('id'))
                    ->with(['productVariant.product', 'warehouse', 'createdBy'])
            )
            ->columns([
                TextColumn::make('productVariant.sku')
                    ->label('SKU')
                    ->fontFamily('mono')
                    ->copyable(),

                TextColumn::make('productVariant.name')
                    ->label('VARIANT')
                    ->limit(30),

                TextColumn::make('warehouse.name')
                    ->label('WAREHOUSE'),

                TextColumn::make('type')
                    ->label('TYPE')
                    ->badge(),

                TextColumn::make('quantity')
                    ->label('QTY (BASE)')
                    ->numeric()
                    ->color(fn (int $state): string => $state < 0 ? 'danger' : 'success'),

                TextColumn::make('unit_name')
                    ->label('UNIT'),

                TextColumn::make('created_at')
                    ->label('TIME')
                    ->since()
                    ->sortable(),

                TextColumn::make('reference_code')
                    ->label('REF')
                    ->limit(20),
            ])
            ->paginated(false)
            ->defaultSort('created_at', 'desc');
    }
}
