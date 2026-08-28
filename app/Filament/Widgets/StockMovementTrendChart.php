<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\MovementType;
use App\Models\StockMovement;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

final class StockMovementTrendChart extends ChartWidget
{
    protected ?string $heading = 'Stock Movement Trend';

    protected static ?int $sort = 15;

    protected ?string $description = 'Daily stock inflow vs outflow — last 30 days';

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

        if (! $isAdmin) {
            $warehouseIds = $user->warehouses()->pluck('warehouses.id');
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
                    'label' => 'Stock In',
                    'data' => $inflowValues,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.15)',
                    'borderColor' => '#10b981',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4,
                ],
                [
                    'label' => 'Stock Out',
                    'data' => $outflowValues,
                    'backgroundColor' => 'rgba(244, 63, 94, 0.15)',
                    'borderColor' => '#f43f5e',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                    'ticks' => [
                        'maxTicksLimit' => 10,
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'grid' => [
                        'color' => 'rgba(0, 0, 0, 0.05)',
                    ],
                ],
            ],
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
        ];
    }
}
