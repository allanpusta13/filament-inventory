<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\SalesOrder;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class TopSellingVariantsWidget extends ChartWidget
{
    protected ?string $heading = 'Top Selling Variants (Last 30 Days)';

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $user = auth()->user();

        if (! ($user?->isAdmin() ?? false) && ! ($user?->isAuditor() ?? false)) {
            return $this->emptyData();
        }

        $firstWarehouseId = optional($user->warehouses->first())?->id;
        $cacheKey = 'top_selling_variants_'.$user->id.'_'.$firstWarehouseId;

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
                            return "Qty Sold: " + context.parsed.y;
                        }',
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Quantity Sold (Base Units)',
                    ],
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Variant SKU',
                    ],
                ],
            ],
        ];
    }

    private function computeChartData($user): array
    {
        $warehouseIds = $user->warehouses->pluck('id')->toArray();

        if (empty($warehouseIds)) {
            return $this->emptyData();
        }

        $startDate = now()->subDays(30)->startOfDay();

        $salesOrders = SalesOrder::whereIn('warehouse_id', $warehouseIds)
            ->whereIn('status', ['dispatched', 'completed'])
            ->where('dispatched_at', '>=', $startDate)
            ->with('items.productVariant')
            ->get();

        $variantSales = [];

        foreach ($salesOrders as $order) {
            foreach ($order->items as $item) {
                if ($item->dispatched_base_qty > 0) {
                    $sku = $item->productVariant->sku ?? 'Unknown';
                    $variantSales[$sku] = ($variantSales[$sku] ?? 0) + $item->dispatched_base_qty;
                }
            }
        }

        arsort($variantSales);
        $topVariants = array_slice($variantSales, 0, 10, true);

        if (empty($topVariants)) {
            return $this->emptyData();
        }

        return [
            'labels' => array_keys($topVariants),
            'datasets' => [
                [
                    'label' => 'Total Qty Sold (Base Units)',
                    'data' => array_values($topVariants),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 1,
                ],
            ],
        ];
    }

    private function emptyData(): array
    {
        return [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Total Qty Sold (Base Units)',
                    'data' => [],
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 1,
                ],
            ],
        ];
    }
}
