<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\InTransitStatus;
use App\Enums\TransferRequisitionStatus;
use App\Models\InTransit;
use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class StatsOverview extends BaseWidget
{
    protected ?string $heading = 'Stats Overview';

    protected int|string|array $columnSpan = 'full';

    protected int|string|array $columnSpanFull = 'full';

    public function getStats(): array
    {
        $user = auth()->user();

        // Hide sensitive stats from non-admin/auditor roles
        if (! ($user?->can('viewAdminReview', TransferRequisition::class) ?? false)) {
            return [
                Stat::make('Total On-Hand Base Stock', '0.0000'),
                Stat::make('Pending Requisitions', '0.0000'),
                Stat::make('Active In-Transit Cargo', '0.0000'),
                Stat::make('Total Write-Off Value', '0.0000'),
            ];
        }

        $firstWarehouseId = optional($user->warehouses->first())?->id;
        $cacheKey = 'stats_overview_'.$user->id.'_'.$firstWarehouseId;

        $stats = Cache::remember($cacheKey, 300, function () use ($user) {
            return $this->computeStats($user);
        });

        return $stats;
    }

    private function computeStats($user): array
    {
        $warehouseIds = $user->warehouses->pluck('id')->toArray();

        if (empty($warehouseIds)) {
            return [
                Stat::make('Total On-Hand Base Stock', '0'),
                Stat::make('Pending Requisitions', '0'),
                Stat::make('Active In-Transit Cargo', '0'),
                Stat::make('Total Write-Off Value', '0.0000'),
            ];
        }

        // 1. Total On-Hand Base Stock
        $totalOnHand = ProductVariant::whereHas('stockMovements', function ($query) use ($warehouseIds) {
            $query->whereIn('warehouse_id', $warehouseIds);
        })->get()->sum(function ($variant) use ($warehouseIds) {
            $sum = 0;
            foreach ($warehouseIds as $warehouseId) {
                $sum += $variant->onHandQuantity($warehouseId);
            }

            return $sum;
        });

        // 2. Pending Requisitions (pre-dispatch states)
        $pendingStatuses = [
            TransferRequisitionStatus::Draft,
            TransferRequisitionStatus::Requested,
            TransferRequisitionStatus::UnderReviewFulfiller,
            TransferRequisitionStatus::UnderReviewRequestor,
            TransferRequisitionStatus::Confirmed,
        ];

        $pendingRequisitions = TransferRequisition::whereIn('from_warehouse_id', $warehouseIds)
            ->whereIn('status', $pendingStatuses)
            ->count();

        // 3. Active In-Transit Cargo
        $activeInTransit = InTransit::whereIn('transfer_requisition_id', function ($query) use ($warehouseIds) {
            $query->from('transfer_requisitions')
                ->select('id')
                ->whereIn('from_warehouse_id', $warehouseIds)
                ->orWhereIn('to_warehouse_id', $warehouseIds);
        })
            ->where('status', '!=', InTransitStatus::Cleared->value)
            ->count();

        // 4. Total Write-Off Value
        $totalWriteOff = LossLedger::whereIn('warehouse_id', $warehouseIds)
            ->sum('total_financial_loss');

        // Format write-off value with 4 decimal places using bcmath
        $formattedWriteOff = $totalWriteOff
            ? bcadd((string) $totalWriteOff, '0', 4)
            : '0.0000';

        return [
            Stat::make('Total On-Hand Base Stock', (string) $totalOnHand)
                ->description('Sum of available stock across all accessible warehouses')
                ->descriptionIcon('heroicon-m-cube'),

            Stat::make('Pending Requisitions', (string) $pendingRequisitions)
                ->description('Requisitions awaiting dispatch')
                ->descriptionIcon('heroicon-m-document-text'),

            Stat::make('Active In-Transit Cargo', (string) $activeInTransit)
                ->description('Items currently in transit')
                ->descriptionIcon('heroicon-m-truck'),

            Stat::make('Total Write-Off Value', $formattedWriteOff)
                ->description('Financial loss from damaged/lost stock')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
