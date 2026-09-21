<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SalesVsPurchasesWidget extends ChartWidget
{
    protected ?string $heading = 'Sales vs Purchases (Last 30 Days)';

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
        $cacheKey = 'sales_vs_purchases_'.$user->id.'_'.$firstWarehouseId;

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
                            return context.dataset.label + ": ₱" + context.parsed.y.toFixed(4);
                        }',
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Value (₱)',
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Last 30 Days',
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

        $days = 30;
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();

        $salesOrders = SalesOrder::whereIn('warehouse_id', $warehouseIds)
            ->whereIn('status', ['dispatched', 'completed'])
            ->where('dispatched_at', '>=', $startDate)
            ->with('items')
            ->get();

        $purchaseOrders = PurchaseOrder::whereIn('warehouse_id', $warehouseIds)
            ->whereIn('status', ['completed', 'partially_received'])
            ->where('received_at', '>=', $startDate)
            ->with('items')
            ->get();

        $salesBuckets = [];
        $purchaseBuckets = [];
        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::now()->subDays($days - 1 - $i)->startOfDay();
            $key = $date->format('Y-m-d');
            $salesBuckets[$key] = '0.0000';
            $purchaseBuckets[$key] = '0.0000';
        }

        foreach ($salesOrders as $order) {
            $dayKey = Carbon::parse($order->dispatched_at)->format('Y-m-d');
            if (! isset($salesBuckets[$dayKey])) {
                continue;
            }

            $dailyRevenue = '0.0000';
            foreach ($order->items as $item) {
                if ($item->dispatched_base_qty > 0 && $item->unit_sale_price_snapshot) {
                    $lineTotal = bcmul((string) $item->dispatched_base_qty, (string) $item->unit_sale_price_snapshot, 4);
                    $dailyRevenue = bcadd($dailyRevenue, $lineTotal, 4);
                }
            }
            $salesBuckets[$dayKey] = bcadd($salesBuckets[$dayKey], $dailyRevenue, 4);
        }

        foreach ($purchaseOrders as $order) {
            $dayKey = Carbon::parse($order->received_at)->format('Y-m-d');
            if (! isset($purchaseBuckets[$dayKey])) {
                continue;
            }

            $dailyCost = '0.0000';
            foreach ($order->items as $item) {
                if ($item->received_base_qty > 0 && $item->unit_cost_price) {
                    $lineTotal = bcmul((string) $item->received_base_qty, (string) $item->unit_cost_price, 4);
                    $dailyCost = bcadd($dailyCost, $lineTotal, 4);
                }
            }
            $purchaseBuckets[$dayKey] = bcadd($purchaseBuckets[$dayKey], $dailyCost, 4);
        }

        $labels = array_map(function ($dateStr) {
            return Carbon::parse($dateStr)->format('M j');
        }, array_keys($salesBuckets));

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Sales Revenue',
                    'data' => array_values($salesBuckets),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Purchase Cost',
                    'data' => array_values($purchaseBuckets),
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                    'borderColor' => 'rgb(239, 68, 68)',
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
                    'label' => 'Sales Revenue',
                    'data' => [],
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Purchase Cost',
                    'data' => [],
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'borderWidth' => 1,
                ],
            ],
        ];
    }
}
