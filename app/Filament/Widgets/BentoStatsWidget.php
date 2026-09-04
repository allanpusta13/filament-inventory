<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\InTransit;
use App\Models\LossLedger;
use App\Models\TransferRequisition;
use App\Models\Warehouse;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

class BentoStatsWidget extends BaseWidget
{
    protected ?string $heading = 'Bento Stats';

    protected function getStats(): array
    {
        return Cache::remember('bento.stats', 300, function () {
            return [
                Stat::make('Active In-Transit Cargo', $this->getInTransitCount())
                    ->description('Currently in transit between warehouses')
                    ->icon('heroicon-o-truck')
                    ->color('info'),

                Stat::make('Total Discrepancy Write-offs', $this->getTotalWriteOffs())
                    ->description('Value of lost/damaged goods')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger'),

                Stat::make('Pending Requisitions', $this->getPendingRequisitionsCount())
                    ->description('Awaiting review or approval')
                    ->icon('heroicon-o-clipboard-list')
                    ->color('warning'),

                Stat::make('Active Warehouses', $this->getActiveWarehousesCount())
                    ->description('Warehouses with recent activity')
                    ->icon('heroicon-o-building-office2')
                    ->color('success'),
            ];
        });
    }

    protected function getInTransitCount(): int
    {
        return InTransit::where('status', 'in_transit')->count();
    }

    protected function getTotalWriteOffs(): string
    {
        $total = LossLedger::sum('total_financial_loss');

        return number_format($total, 2);
    }

    protected function getPendingRequisitionsCount(): int
    {
        return TransferRequisition::whereIn('status', [
            'draft',
            'requested',
            'under_review_fulfiller',
            'under_review_requestor',
        ])->count();
    }

    protected function getActiveWarehousesCount(): int
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        return Warehouse::whereHas('stockMovements', function ($query) use ($thirtyDaysAgo) {
            $query->where('created_at', '>=', $thirtyDaysAgo);
        })->count();
    }
}