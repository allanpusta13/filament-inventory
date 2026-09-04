<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\StockMovement;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

class RecentMovementsChartWidget extends ChartWidget
{
    protected ?string $heading = 'Recent Movements Trend';

    protected function getData(): array
    {
        return Cache::remember('recent.movements.chart', 300, function () {
            $now = Carbon::now();
            $start = $now->copy()->subDays(6); // last 7 days including today

            $dates = [];
            $values = [];

            for ($i = 0; $i <= 6; $i++) {
                $date = $start->copy()->addDays($i);
                $dateStr = $date->toDateString();
                $dates[] = $date->format('M d');

                $movements = StockMovement::whereDate('created_at', $date)->get();

                // Calculate throughput: sum of absolute quantities (both in and out)
                $total = $movements->sum(fn ($m) => abs($m->quantity));

                $values[] = (int) $total;
            }

            return [
                'datasets' => [
                    [
                        'label' => 'Stock Throughput (units)',
                        'data' => $values,
                        'borderColor' => 'rgb(75, 192, 192)',
                        'backgroundColor' => 'rgba(75, 192, 192, 0.5)',
                    ],
                ],
                'labels' => $dates,
            ];
        });
    }

    protected function getType(): string
    {
        return 'line';
    }
}