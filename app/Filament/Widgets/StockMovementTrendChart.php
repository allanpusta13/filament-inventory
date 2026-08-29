<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\MovementType;
use App\Models\StockMovement;
use App\Traits\DashboardFilterable;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

final class StockMovementTrendChart extends ChartWidget
{
    use DashboardFilterable;

    protected ?string $heading = 'Inbound vs Outbound Shipments';

    protected static ?int $sort = 31;

    protected ?string $description = 'Supplier shipments (bars) and fulfillment (line) — last 30 days';

    protected int|string|array $columnSpan = [
        'sm' => 'full',
        'md' => 1,
        'lg' => 7,
    ];

    protected ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() ?? false;
    }

    public function getPollingInterval(): ?string
    {
        return '30s';
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $startDate = Carbon::now()->subDays(29)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $inflowQuery = StockMovement::query()
            ->selectRaw('DATE(created_at) as date, SUM(quantity) as total')
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->whereIn('type', [MovementType::Receive, MovementType::TransferIn])
            ->groupBy('date')
            ->orderBy('date');

        $outflowQuery = StockMovement::query()
            ->selectRaw('DATE(created_at) as date, ABS(SUM(quantity)) as total')
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->whereIn('type', [MovementType::Ship, MovementType::TransferOut])
            ->groupBy('date')
            ->orderBy('date');

        $warehouseIds = $this->getFilterWarehouseIds($user);
        if ($warehouseIds !== null) {
            $inflowQuery->whereIn('warehouse_id', $warehouseIds);
            $outflowQuery->whereIn('warehouse_id', $warehouseIds);
        }

        $inflowData = $inflowQuery->pluck('total', 'date')->toArray();
        $outflowData = $outflowQuery->pluck('total', 'date')->toArray();

        $labels = [];
        $inflowValues = [];
        $outflowValues = [];

        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $key = $date->format('Y-m-d');
            $labels[] = $date->format('M j');
            $inflowValues[] = (int) ($inflowData[$key] ?? 0);
            $outflowValues[] = (int) ($outflowData[$key] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Inbound Shipments',
                    'data' => $inflowValues,
                    'type' => 'bar',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.6)',
                    'borderColor' => '#10b981',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                    'yAxisID' => 'y',
                    'order' => 2,
                ],
                [
                    'label' => 'Outbound Fulfillment',
                    'data' => $outflowValues,
                    'type' => 'line',
                    'borderColor' => '#f43f5e',
                    'backgroundColor' => 'rgba(244, 63, 94, 0.1)',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4,
                    'yAxisID' => 'y1',
                    'order' => 1,
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
                    'mode' => 'index',
                    'intersect' => false,
                    'backgroundColor' => 'rgba(15, 23, 42, 0.95)',
                    'titleColor' => '#f1f5f9',
                    'bodyColor' => '#e2e8f0',
                    'borderColor' => 'rgba(148, 163, 184, 0.3)',
                    'borderWidth' => 1,
                    'padding' => 12,
                    'cornerRadius' => 8,
                    'displayColors' => true,
                ],
                'title' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                    'ticks' => [
                        'maxTicksLimit' => 10,
                        'color' => '#64748b',
                        'font' => [
                            'size' => 11,
                            'family' => 'Instrument Sans',
                        ],
                    ],
                ],
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Inbound',
                        'color' => '#10b981',
                        'font' => [
                            'size' => 11,
                            'family' => 'Instrument Sans',
                        ],
                    ],
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
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Outbound',
                        'color' => '#f43f5e',
                        'font' => [
                            'size' => 11,
                            'family' => 'Instrument Sans',
                        ],
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
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
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
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
