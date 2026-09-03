<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use App\Models\InTransit;
use App\Models\ProductVariant;
use App\Models\WarehouseStock;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class WarehouseStockOverviewWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        $isAdmin = $user?->isAdmin() ?? false;
        $warehouseIds = $isAdmin ? [] : $user->warehouses()->pluck('warehouses.id')->toArray();

        $stockQuery = WarehouseStock::query();
        if (! $isAdmin) {
            $stockQuery->whereIn('warehouse_id', $warehouseIds);
        }

        $totalSkusOnHand = (clone $stockQuery)->where('on_hand_quantity', '>', 0)->count();

        $lowStockQuery = ProductVariant::query()
            ->select('product_variants.id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('warehouse_stock', 'warehouse_stock.variant_id', '=', 'product_variants.id')
            ->where('products.reorder_point', '>', 0)
            ->groupBy('product_variants.id', 'products.reorder_point')
            ->havingRaw('SUM(warehouse_stock.on_hand_quantity) <= products.reorder_point');

        if (! $isAdmin) {
            $lowStockQuery->whereIn('warehouse_stock.warehouse_id', $warehouseIds);
        }

        $lowStockCount = $lowStockQuery->count();

        $activeShipments = InTransit::where('status', 'in_transit')
            ->whereHas('requisition', function ($q) use ($isAdmin, $warehouseIds): void {
                if (! $isAdmin) {
                    $q->whereIn('from_warehouse_id', $warehouseIds)
                        ->orWhereIn('to_warehouse_id', $warehouseIds);
                }
            })
            ->count();

        return [
            Stat::make('Total SKUs On Hand', $totalSkusOnHand)
                ->description('Unique variant-warehouse combinations')
                ->descriptionIcon(Heroicon::OutlinedCube)
                ->color('success')
                ->url(ProductResource::getUrl('index')),
            Stat::make('Low Stock Alerts', $lowStockCount)
                ->description('Variants at or below reorder point')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($lowStockCount > 0 ? 'danger' : 'success')
                ->url(ProductResource::getUrl('index')),
            Stat::make('Active Shipments', $activeShipments)
                ->description('Currently in transit')
                ->descriptionIcon(Heroicon::OutlinedTruck)
                ->color($activeShipments > 0 ? 'warning' : 'success')
                ->url(TransferRequisitionResource::getUrl('index')),
        ];
    }
}
