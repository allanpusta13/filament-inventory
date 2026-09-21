<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\ProductVariant;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class LowStockAlertsWidget extends ChartWidget
{
    protected ?string $heading = 'Low Stock Alerts';

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $user = auth()->user();

        if (! ($user?->isAdmin() ?? false) && ! ($user?->isAuditor() ?? false) && ! ($user?->isBranchManager() ?? false) && ! ($user?->isWarehouseStaff() ?? false)) {
            return [
                'labels' => [],
                'datasets' => [
                    [
                        'label' => 'Current Stock',
                        'data' => [],
                        'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                        'borderColor' => 'rgb(34, 197, 94)',
                        'borderWidth' => 1,
                    ],
                    [
                        'label' => 'Reorder Point',
                        'data' => [],
                        'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                        'borderColor' => 'rgb(239, 68, 68)',
                        'borderWidth' => 1,
                    ],
                ],
            ];
        }

        $firstWarehouseId = optional($user->warehouses->first())?->id;
        $cacheKey = 'low_stock_alerts_chart_'.$user->id.'_'.$firstWarehouseId;

        return Cache::remember($cacheKey, 300, function () use ($user) {
            return $this->computeChartData($user);
        });
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            return context.dataset.label + ": " + context.parsed.y;
                        }',
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Quantity (Base Units)',
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Product Variants (SKU - Name)',
                    ],
                    'ticks' => [
                        'maxRotation' => 45,
                        'minRotation' => 45,
                    ],
                ],
            ],
        ];
    }

    private function computeChartData($user): array
    {
        $warehouseIds = $user->warehouses->pluck('id')->toArray();

        if (empty($warehouseIds)) {
            return [
                'labels' => [],
                'datasets' => [
                    [
                        'label' => 'Current Stock',
                        'data' => [],
                        'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                        'borderColor' => 'rgb(34, 197, 94)',
                        'borderWidth' => 1,
                    ],
                    [
                        'label' => 'Reorder Point',
                        'data' => [],
                        'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                        'borderColor' => 'rgb(239, 68, 68)',
                        'borderWidth' => 1,
                    ],
                ],
            ];
        }

        // Single aggregate query: sum stock_movements across all accessible warehouses per variant
        // Compare against reorder_point, return only variants at or below reorder point
        $lowStockVariants = ProductVariant::where('reorder_point', '>', 0)
            ->whereHas('stockMovements', function ($query) use ($warehouseIds) {
                $query->whereIn('warehouse_id', $warehouseIds);
            })
            ->get()
            ->map(function ($variant) use ($warehouseIds) {
                $totalStock = 0;
                foreach ($warehouseIds as $warehouseId) {
                    $totalStock += $variant->onHandQuantity($warehouseId);
                }

                return [
                    'variant' => $variant,
                    'total_stock' => $totalStock,
                    'reorder_point' => $variant->reorder_point,
                ];
            })
            ->filter(fn ($item) => $item['total_stock'] <= $item['reorder_point'])
            ->sortBy('total_stock')
            ->values();

        if ($lowStockVariants->isEmpty()) {
            return [
                'labels' => [],
                'datasets' => [
                    [
                        'label' => 'Current Stock',
                        'data' => [],
                        'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                        'borderColor' => 'rgb(34, 197, 94)',
                        'borderWidth' => 1,
                    ],
                    [
                        'label' => 'Reorder Point',
                        'data' => [],
                        'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                        'borderColor' => 'rgb(239, 68, 68)',
                        'borderWidth' => 1,
                    ],
                ],
            ];
        }

        $labels = $lowStockVariants->pluck(function ($item) {
            return $item['variant']->sku.' - '.$item['variant']->name;
        })->toArray();

        $currentStockData = $lowStockVariants->pluck('total_stock')->toArray();
        $reorderPointData = $lowStockVariants->pluck('reorder_point')->toArray();

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Current Stock',
                    'data' => $currentStockData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Reorder Point',
                    'data' => $reorderPointData,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'borderWidth' => 1,
                ],
            ],
        ];
    }
}
