<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\StockMovement;
use Filament\Widgets\ChartWidget;

final class FastMovingStockChart extends ChartWidget
{
    protected ?string $heading = 'Fast-Moving Items';

    protected static ?int $sort = 26;

    protected ?string $description = 'Top 10 products by stock movement frequency';

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
                products.name as product_name,
                COUNT(stock_movements.id) as movement_count,
                SUM(CASE WHEN stock_movements.quantity > 0 THEN stock_movements.quantity ELSE 0 END) as total_in,
                ABS(SUM(CASE WHEN stock_movements.quantity < 0 THEN stock_movements.quantity ELSE 0 END)) as total_out
            ')
            ->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('movement_count')
            ->limit(10);

        if (! $isAdmin) {
            $warehouseIds = $user->warehouses()->pluck('warehouses.id');
            $query->whereIn('stock_movements.warehouse_id', $warehouseIds);
        }

        $results = $query->get();

        $labels = [];
        $inData = [];
        $outData = [];

        foreach ($results as $row) {
            $labels[] = mb_strlen($row->product_name) > 20
                ? mb_substr($row->product_name, 0, 20).'...'
                : $row->product_name;
            $inData[] = (int) $row->total_in;
            $outData[] = (int) $row->total_out;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Stock In',
                    'data' => $inData,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.8)',
                    'borderColor' => '#10b981',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Stock Out',
                    'data' => $outData,
                    'backgroundColor' => 'rgba(244, 63, 94, 0.8)',
                    'borderColor' => '#f43f5e',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'grid' => [
                        'color' => 'rgba(0, 0, 0, 0.05)',
                    ],
                ],
                'y' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
            ],
        ];
    }
}
