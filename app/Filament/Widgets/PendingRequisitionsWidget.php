<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\TransferRequisition;
use Filament\Widgets\Widget as BaseWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

class PendingRequisitionsWidget extends BaseWidget
{
    protected ?string $heading = 'Pending Requisitions';

    protected function getView(): string
    {
        return 'widgets.pending-requisitions';
    }

    protected function getViewData(): array
    {
        return [
            'requisitions' => Cache::remember('pending.requisitions', 300, function () {
                $user = auth()->user();

                // Base query for pending requisitions
                $query = TransferRequisition::whereIn('status', [
                    'draft',
                    'requested',
                    'under_review_fulfiller',
                    'under_review_requestor',
                ]);

                // Location-scoping: if not admin/auditor, only show requisitions involving user's warehouses
                if (! $user->isAdmin() && ! $user->isAuditor()) {
                    $warehouseIds = $user->warehouses()->pluck('warehouses.id')->toArray();

                    $query->where(function ($q) use ($warehouseIds) {
                        $q->whereIn('from_warehouse_id', $warehouseIds)
                            ->orWhereIn('to_warehouse_id', $warehouseIds);
                    });
                }

                return $query->with(['fromWarehouse', 'toWarehouse', 'requestedBy'])
                    ->orderBy('requested_at', 'desc')
                    ->get()
                    ->map(function ($req) {
                        return [
                            'reference_code' => $req->reference_code,
                            'from_warehouse_name' => $req->fromWarehouse->name ?? '',
                            'to_warehouse_name' => $req->toWarehouse->name ?? '',
                            'requested_by_name' => $req->requestedBy->name ?? '',
                            'requested_at' => $req->requested_at?->toDateTimeString(),
                            'status' => $req->status->value,
                        ];
                    })
                    ->toArray();
            })
        ];
    }
}