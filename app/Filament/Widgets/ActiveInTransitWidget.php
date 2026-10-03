<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\InTransitStatus;
use App\Models\InTransit;
use App\Models\Warehouse;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Cache;

/**
 * ActiveInTransitWidget — table of in-flight cargo rows.
 *
 * Visibility: Admin, Auditor, WarehouseStaff.
 * Cache: 300s (ID set only; table rehydrates each render).
 * Column span: `['default' => 1, 'md' => 2, 'xl' => 2]`.
 */
class ActiveInTransitWidget extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = ['default' => 1, 'md' => 2, 'xl' => 2];

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->isAdmin() || $user->isAuditor() || $user->isWarehouseStaff());
    }

    public static function cacheTtl(): int
    {
        return 300;
    }

    public static function cacheKey(int $userId, array $warehouseIds): string
    {
        sort($warehouseIds);

        return 'active_in_transit_'.$userId.'_'.md5(implode(',', $warehouseIds));
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $warehouseIds = $user->isAdmin() || $user->isAuditor()
            ? Warehouse::query()->pluck('id')->all()
            : $user->warehouses()->pluck('warehouses.id')->all();

        // Rows: active in-transit only (`status = in_transit`, §4.7) — warehouse-scoped through the parent requisition.
        // `Cleared` and `Lost` are terminal states and never appear here.
        // The ID set is read through Cache::remember (300s); the table query re-hydrates
        // from the cached IDs so sorting and eager-loads still apply on every render.
        $ids = Cache::remember(
            static::cacheKey($user->id, $warehouseIds),
            static::cacheTtl(),
            fn () => InTransit::query()
                ->where('status', InTransitStatus::InTransit->value)
                ->whereHas('transferRequisition', fn ($q) => $q
                    ->whereIn('from_warehouse_id', $warehouseIds)
                    ->orWhereIn('to_warehouse_id', $warehouseIds))
                ->latest('dispatched_at')
                ->pluck('id')
                ->all(),
        );

        return $table
            ->query(
                InTransit::query()
                    ->whereIn('id', $ids)
                    ->with(['transferRequisition', 'productVariant'])
                    ->latest('dispatched_at')
            )
            ->columns([
                TextColumn::make('transferRequisition.reference_code')
                    ->label(__('dashboard.active_in_transit.requisition'))
                    ->fontFamily('mono'),

                TextColumn::make('productVariant.sku')
                    ->label(__('dashboard.active_in_transit.sku'))
                    ->fontFamily('mono'),

                TextColumn::make('dispatched_base_qty')
                    ->label(__('dashboard.active_in_transit.qty'))
                    ->numeric(),

                TextColumn::make('status')
                    ->label(__('dashboard.active_in_transit.status'))
                    ->badge(),
            ])
            ->paginated([5, 10]);
    }
}
