<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\StockMovement;
use App\Traits\DashboardFilterable;
use Filament\Widgets\ChartWidget;

final class CategoryStockChart extends ChartWidget
{
    use DashboardFilterable;

    private const COLORS = [
        '#6366f1', // indigo
        '#10b981', // emerald
        '#f59e0b', // amber
        '#f43f5e', // rose
        '#3b82f6', // blue
        '#8b5cf6', // violet
        '#ec4899', // pink
        '#14b8a6', // teal
        '#f97316', // orange
        '#06b6d4', // cyan
    ];

    protected ?string $heading = 'Inventory by Product Category';

    protected static ?int $sort = 21;

    protected ?string $description = '';

    protected int|string|array $columnSpan = [
        'sm' => 'full',
        'md' => 'full',
        'lg' => 5,
    ];

    protected ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user ? $user->isAdmin() : false;
    }

    public function getPollingInterval(): ?string
    {
        return '30s';
    }

    protected function getData(): array
    {
        $user = auth()->user();

        $query = StockMovement::query()
            ->selectRaw("
                COALESCE(products.category, 'Uncategorized') as category,
                SUM(stock_movements.quantity) as total_quantity
            ")
            ->join('product_variants', 'product_variants.id', '=', 'stock_movements.variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->groupBy('category')
            ->havingRaw('SUM(stock_movements.quantity) > 0')
            ->orderByDesc('total_quantity');

        $warehouseIds = $this->getFilterWarehouseIds($user);
        if ($warehouseIds !== null) {
            $query->whereIn('stock_movements.warehouse_id', $warehouseIds);
        }

        $results = $query->get();

        $labels = [];
        $data = [];
        $colors = [];

        foreach ($results as $index => $row) {
            $labels[] = $row->category;
            $data[] = (int) $row->total_quantity;
            $colors[] = self::COLORS[$index % count(self::COLORS)];
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderColor' => $colors,
                    'borderWidth' => 2,
                    'hoverOffset' => 8,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'right',
                    'labels' => [
                        'padding' => 16,
                        'usePointStyle' => true,
                        'pointStyle' => 'circle',
                        'font' => [
                            'size' => 12,
                            'family' => 'Instrument Sans',
                        ],
                    ],
                ],
                'tooltip' => [
                    'backgroundColor' => 'rgba(15, 23, 42, 0.95)',
                    'titleColor' => '#f1f5f9',
                    'bodyColor' => '#e2e8f0',
                    'borderColor' => 'rgba(148, 163, 184, 0.3)',
                    'borderWidth' => 1,
                    'padding' => 12,
                    'cornerRadius' => 8,
                    'callbacks' => [
                        'label' => 'function(context) {
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return context.label + ": " + value.toLocaleString() + " (" + percentage + "%)";
                        }',
                    ],
                ],
            ],
            'cutout' => '60%',
            'maintainAspectRatio' => false,
            'responsive' => true,
            'animation' => [
                'animateRotate' => true,
                'animateScale' => true,
                'duration' => 1000,
                'easing' => 'easeOutQuart',
            ],
        ];
    }
}
