<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductVariantResource;
use App\Filament\Resources\TransferRequisitionResource;
use App\Models\InTransit;
use App\Models\ProductVariant;
use App\Models\WarehouseStock;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class WarehouseStockOverviewWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        $isAdmin = $user->isAdmin();
        $warehouseIds = $isAdmin ? [] : $user->warehouses()->pluck('warehouses.id')->toArray();

        $stockQuery = WarehouseStock::query();
        if (! $isAdmin) {
            $stockQuery->whereIn('warehouse_id', $warehouseIds);
        }

        $totalSkusOnHand = (clone $stockQuery)->where('on_hand_quantity', '>', 0)->count();

        $lowStockCount = 0;
        $variants = ProductVariant::with('product')->get();
        foreach ($variants as $variant) {
            $reorderPoint = $variant->product->reorder_point ?? 0;
            if ($reorderPoint <= 0) {
                continue;
            }
            $stockQuery2 = WarehouseStock::where('variant_id', $variant->id);
            if (! $isAdmin) {
                $stockQuery2->whereIn('warehouse_id', $warehouseIds);
            }
            $totalOnHand = $stockQuery2->sum('on_hand_quantity');
            if ($totalOnHand <= $reorderPoint) {
                $lowStockCount++;
            }
        }

        $transitQuery = InTransit::where('status', 'in_transit');
        if (! $isAdmin) {
            $transitQuery->whereIn('warehouse_id', $warehouseIds);
        }
        // InTransit doesn't have warehouse_id directly; check via requisition
        // For simplicity, count all active in_transit shipments for admin, or filter by user's warehouses
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
                ->descriptionIcon('heroicon-o-cube')
                ->color('success')
                ->url(ProductVariantResource::getUrl('index')),
            Stat::make('Low Stock Alerts', $lowStockCount)
                ->description('Variants at or below reorder point')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($lowStockCount > 0 ? 'danger' : 'success')
                ->url(ProductVariantResource::getUrl('index')),
            Stat::make('Active Shipments', $activeShipments)
                ->description('Currently in transit')
                ->descriptionIcon('heroicon-o-truck')
                ->color($activeShipments > 0 ? 'warning' : 'success')
                ->url(TransferRequisitionResource::getUrl('index')),
        ];
    }
}
