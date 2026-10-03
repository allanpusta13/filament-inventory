<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PurchaseOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Cache;

/**
 * PendingFulfillmentWidget — per-warehouse pending SO/PO counts.
 *
 * Visibility: all authenticated users (scoped).
 * Cache: 60s.
 * Column span: `['default' => 1, 'md' => 1, 'xl' => 1]`.
 */
class PendingFulfillmentWidget extends TableWidget
{
    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = ['default' => 1, 'md' => 1, 'xl' => 1];

    public static function canView(): bool
    {
        return auth()->user() !== null;
    }

    public static function cacheTtl(): int
    {
        return 60;
    }

    public static function cacheKey(int $userId, array $warehouseIds): string
    {
        sort($warehouseIds);

        return 'pending_fulfillment_'.$userId.'_'.md5(implode(',', $warehouseIds));
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $warehouseIds = $user->isAdmin() || $user->isAuditor()
            ? Warehouse::query()->pluck('id')->all()
            : $user->warehouses()->pluck('warehouses.id')->all();

        // Rows: one per in-scope warehouse with pending SO/PO counts (60s cache).
        $counts = Cache::remember(
            static::cacheKey($user->id, $warehouseIds),
            static::cacheTtl(),
            fn () => [
                'sales' => SalesOrder::query()
                    ->selectRaw('warehouse_id, COUNT(*) as total')
                    ->whereIn('warehouse_id', $warehouseIds)
                    ->whereIn('status', [
                        SalesOrderStatus::Confirmed->value,
                        SalesOrderStatus::PartiallyDispatched->value,
                    ])
                    ->groupBy('warehouse_id')
                    ->pluck('total', 'warehouse_id')
                    ->map(fn ($v) => (int) $v)
                    ->all(),
                'purchases' => PurchaseOrder::query()
                    ->selectRaw('warehouse_id, COUNT(*) as total')
                    ->whereIn('warehouse_id', $warehouseIds)
                    ->whereIn('status', [
                        PurchaseOrderStatus::Ordered->value,
                        PurchaseOrderStatus::PartiallyReceived->value,
                    ])
                    ->groupBy('warehouse_id')
                    ->pluck('total', 'warehouse_id')
                    ->map(fn ($v) => (int) $v)
                    ->all(),
            ],
        );

        return $table
            ->query(Warehouse::query()->whereIn('warehouses.id', $warehouseIds))
            ->columns([
                TextColumn::make('name')
                    ->label(__('dashboard.pending.warehouse')),

                TextColumn::make('pending_sales')
                    ->label(__('dashboard.pending.sales'))
                    ->state(fn (Warehouse $record) => $counts['sales'][$record->id] ?? 0)
                    ->numeric(),

                TextColumn::make('pending_purchases')
                    ->label(__('dashboard.pending.purchases'))
                    ->state(fn (Warehouse $record) => $counts['purchases'][$record->id] ?? 0)
                    ->numeric(),
            ])
            ->paginated(false);
    }
}
