<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\InTransitStatus;
use App\Enums\TransferRequisitionStatus;
use App\Models\InTransit;
use App\Models\LossLedger;
use App\Models\StockMovement;
use App\Models\TransferRequisition;
use App\Models\Warehouse;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Number;

/**
 * StatsOverviewWidget — four aggregate stat cards (§10).
 *
 * ⚠ Blueprint correction: §10's Widget Definitions table types this
 * widget as `TableWidget`. That is incorrect. Filament's
 * `StatsOverviewWidget` is a stat-card widget (extends `BaseWidget`,
 * returns `Stat` instances from `getStats()`). The blueprint's Type
 * column should read `StatsOverviewWidget`. This implementation
 * follows the Filament v5 canonical contract, not the blueprint's
 * mistyped column.
 *
 * Four cards, one per total across the acting user's in-scope
 * warehouse set:
 *   - Total On-Hand      — SUM(stock_movements.quantity)
 *   - Pending Requisitions — COUNT of transfer requisitions in a
 *                            pre-dispatch state touching an in-scope
 *                            warehouse
 *   - Active In-Transit  — COUNT of in_transits where status =
 *                            in_transit and the parent requisition
 *                            touches an in-scope warehouse
 *   - Total Write-Off    — SUM(loss_ledgers.total_financial_loss)
 *
 * Visibility: all authenticated users (scoped by warehouse).
 * Cache: 300s — one entry per (userId, scopeHash).
 * Column span: `['default' => 1, 'md' => 2, 'xl' => 2]`.
 */
class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = ['default' => 1, 'md' => 2, 'xl' => 2];

    public static function canView(): bool
    {
        return auth()->user() !== null;
    }

    public static function cacheTtl(): int
    {
        return 300;
    }

    public static function cacheKey(int $userId, array $warehouseIds): string
    {
        sort($warehouseIds);

        return 'stats_overview_'.$userId.'_'.md5(implode(',', $warehouseIds));
    }

    /**
     * Return the four aggregate stat cards.
     *
     * `$warehouseIds` is the acting user's in-scope warehouse set.
     * Admin/Auditor resolve to every warehouse; warehouse staff resolve
     * to their assigned warehouses; BranchManager follows the same
     * warehouse-assignment tier as warehouse staff (owner direction).
     *
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $user = auth()->user();

        $warehouseIds = $user->isAdmin() || $user->isAuditor()
            ? Warehouse::query()->pluck('id')->all()
            : $user->warehouses()->pluck('warehouses.id')->all();

        $metrics = Cache::remember(
            static::cacheKey($user->id, $warehouseIds),
            static::cacheTtl(),
            function () use ($warehouseIds) {
                $pendingStatuses = [
                    TransferRequisitionStatus::Draft->value,
                    TransferRequisitionStatus::Requested->value,
                    TransferRequisitionStatus::UnderReviewFulfiller->value,
                    TransferRequisitionStatus::UnderReviewRequestor->value,
                    TransferRequisitionStatus::Confirmed->value,
                ];

                return [
                    'on_hand' => (int) StockMovement::query()
                        ->whereIn('warehouse_id', $warehouseIds)
                        ->sum('quantity'),

                    'pending' => TransferRequisition::query()
                        ->whereIn('status', $pendingStatuses)
                        ->where(function ($q) use ($warehouseIds) {
                            $q->whereIn('from_warehouse_id', $warehouseIds)
                                ->orWhereIn('to_warehouse_id', $warehouseIds);
                        })
                        ->count(),

                    'in_transit' => InTransit::query()
                        ->where('status', InTransitStatus::InTransit->value)
                        ->whereHas('transferRequisition', function ($q) use ($warehouseIds) {
                            $q->whereIn('from_warehouse_id', $warehouseIds)
                                ->orWhereIn('to_warehouse_id', $warehouseIds);
                        })
                        ->count(),

                    'write_off' => (float) LossLedger::query()
                        ->whereIn('warehouse_id', $warehouseIds)
                        ->sum('total_financial_loss'),
                ];
            },
        );

        return [
            Stat::make(
                __('dashboard.stats.on_hand'),
                Number::format($metrics['on_hand']),
            )
                ->description(__('dashboard.stats.on_hand_description'))
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),

            Stat::make(
                __('dashboard.stats.pending'),
                Number::format($metrics['pending']),
            )
                ->description(__('dashboard.stats.pending_description'))
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make(
                __('dashboard.stats.in_transit'),
                Number::format($metrics['in_transit']),
            )
                ->description(__('dashboard.stats.in_transit_description'))
                ->descriptionIcon('heroicon-m-truck')
                ->color('info'),

            Stat::make(
                __('dashboard.stats.write_off'),
                Number::currency($metrics['write_off'], config('app.currency')),
            )
                ->description(__('dashboard.stats.write_off_description'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
