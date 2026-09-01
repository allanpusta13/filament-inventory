<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TransferRequisitionStatus;
use App\Models\TransferRequisition;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class TransferRequisitionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        // All roles can view at least some transfer requisitions (see view method for restrictions)
        return true;
    }

    public function view(User $user, TransferRequisition $requisition): bool
    {
        if ($user->isAdmin() || $user->isAuditor()) {
            return true;
        }

        // For branch_manager and warehouse_staff, check if they are assigned to either warehouse
        $assignedWarehouseIds = $user->warehouses()->pluck('id')->toArray();

        return in_array($requisition->from_warehouse_id, $assignedWarehouseIds) ||
               in_array($requisition->to_warehouse_id, $assignedWarehouseIds);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() ||
               $user->isBranchManager() ||
               $user->isWarehouseStaff();
    }

    public function update(User $user, TransferRequisition $requisition): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isBranchManager()) {
            // Allow update if the requisition involves their warehouse and status allows review
            $assignedWarehouseIds = $user->warehouses()->pluck('id')->toArray();
            $involvesWarehouse = in_array($requisition->from_warehouse_id, $assignedWarehouseIds) ||
                                 in_array($requisition->to_warehouse_id, $assignedWarehouseIds);

            $allowedStatuses = [TransferRequisitionStatus::UnderReviewFulfiller, TransferRequisitionStatus::UnderReviewRequestor];

            return $involvesWarehouse && in_array($requisition->status, $allowedStatuses);
        }

        if ($user->isWarehouseStaff()) {
            // Allow update only if the requisition is draft, they created it, and involves their warehouse
            $assignedWarehouseIds = $user->warehouses()->pluck('id')->toArray();
            $isCreator = $requisition->requested_by === $user->id;
            $involvesWarehouse = in_array($requisition->from_warehouse_id, $assignedWarehouseIds) ||
                                 in_array($requisition->to_warehouse_id, $assignedWarehouseIds);

            return $requisition->status === TransferRequisitionStatus::Draft && $isCreator && $involvesWarehouse;
        }

        return false;
    }

    public function delete(User $user, TransferRequisition $requisition): bool
    {
        if ($user->isAdmin()) {
            // Admin can delete only if they created it
            return $requisition->requested_by === $user->id;
        }

        if ($user->isBranchManager()) {
            // Allow delete only if the requisition is a draft, they created it, and involves their warehouse
            $assignedWarehouseIds = $user->warehouses()->pluck('id')->toArray();
            $isCreator = $requisition->requested_by === $user->id;
            $involvesWarehouse = in_array($requisition->from_warehouse_id, $assignedWarehouseIds) ||
                                 in_array($requisition->to_warehouse_id, $assignedWarehouseIds);

            return $requisition->status === TransferRequisitionStatus::Draft && $isCreator && $involvesWarehouse;
        }

        if ($user->isWarehouseStaff()) {
            // Allow delete only if the requisition is a draft, they created it
            return $requisition->status === TransferRequisitionStatus::Draft && $requisition->requested_by === $user->id;
        }

        return false;
    }
}
