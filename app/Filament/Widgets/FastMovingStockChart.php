<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\StockMovement;
use App\Traits\DashboardFilterable;
use Filament\Widgets\ChartWidget;

final class FastMovingStockChart extends ChartWidget
{
    use DashboardFilterable;

    protected ?string $heading = 'Top 10 High-Turnover Products';

    protected static ?int $sort = 41;

    protected ?string $description = 'Top 10 products by stock movement frequency';

    protected int|string|array $columnSpan = [
        'sm' => 'full',
        'md' => 'full',
        'lg' => 7,
    ];

    protected ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user ? true : false; // Visible to both admin and warehouse_staff
    }

    public function getPollingInterval(): ?string
    {
        return '60s';
    }

    protected function getData(): array
    {
        $user = auth()->user();

        $query = StockMovement::query()
            ->selectRaw('
                products.name as product_name,
                COUNT(stock_movements.id) as movement_count,
                SUM(CASE WHEN stock_movements.quantity > 0 THEN stock_movements.quantity ELSE 0 END) as total_in,
                ABS(SUM(CASE WHEN stock_movements.quantity < 0 THEN stock_movements.quantity ELSE 0 END)) as total_out
            ')
            ->join('product_variants', 'product_variants.id', '=', 'stock_movements.variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('movement_count')
            ->limit(10);

        $warehouseIds = $this->getFilterWarehouseIds($user);
        if ($warehouseIds !== null) {
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
                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 16,
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
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'grid' => [
                        'color' => 'rgba(148, 163, 184, 0.15)',
                    ],
                    'ticks' => [
                        'color' => '#64748b',
                        'font' => [
                            'size' => 11,
                            'family' => 'Instrument Sans',
                        ],
                    ],
                ],
                'y' => [
                    'grid' => [
                        'display' => false,
                    ],
                    'ticks' => [
                        'color' => '#64748b',
                        'font' => [
                            'size' => 11,
                            'family' => 'Instrument Sans',
                        ],
                    ],
                ],
            ],
            'maintainAspectRatio' => false,
            'responsive' => true,
            'animation' => [
                'duration' => 750,
                'easing' => 'easeOutQuart',
            ],
        ];
    }
}
