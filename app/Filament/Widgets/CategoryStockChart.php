<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\StockMovement;
use Filament\Widgets\ChartWidget;

final class CategoryStockChart extends ChartWidget
{
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

    protected ?string $heading = 'Stock by Category';

    protected static ?int $sort = 25;

    protected ?string $description = 'Total inventory quantity per product category';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    public function getPollingInterval(): ?string
    {
        return '60s';
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $isAdmin = $user->isAdmin();

        $query = StockMovement::query()
            ->selectRaw('
                COALESCE(products.category, \'Uncategorized\') as category,
                SUM(stock_movements.quantity) as total_quantity
            ')
            ->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->groupBy('category')
            ->havingRaw('SUM(stock_movements.quantity) > 0')
            ->orderByDesc('total_quantity');

        if (! $isAdmin) {
            $warehouseIds = $user->warehouses()->pluck('warehouses.id');
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
                        'padding' => 12,
                        'usePointStyle' => true,
                        'pointStyle' => 'circle',
                    ],
                ],
            ],
            'cutout' => '60%',
        ];
    }
}
