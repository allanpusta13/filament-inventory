<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TransferRequisition;
use App\Models\User;

class TransferRequisitionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TransferRequisition $r): bool
    {
        return $user->isAdmin()
            || $user->warehouses->contains($r->from_warehouse_id)
            || $user->warehouses->contains($r->to_warehouse_id);
    }

    public function create(User $user): bool
    {
        return $user->warehouses()->exists();
    }

    public function update(User $user, TransferRequisition $r): bool
    {
        return $r->status === \App\Enums\TransferRequisitionStatus::Draft
            && $user->warehouses->contains($r->from_warehouse_id);
    }

    public function delete(User $user, TransferRequisition $r): bool
    {
        return $user->isAdmin()
            && in_array($r->status, [
                \App\Enums\TransferRequisitionStatus::Draft,
                \App\Enums\TransferRequisitionStatus::Cancelled,
            ], true);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, TransferRequisition $r): bool
    {
        return $user->isAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, TransferRequisition $r): bool
    {
        return $user->isAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function submitRequest(User $user, TransferRequisition $r): bool
    {
        return $r->status === \App\Enums\TransferRequisitionStatus::Draft
            && $user->warehouses->contains($r->from_warehouse_id);
    }

    public function confirm(User $user, TransferRequisition $r): bool
    {
        return $user->isAdmin()
            || $user->warehouses->contains($r->to_warehouse_id);
    }

    public function dispatch(User $user, TransferRequisition $r): bool
    {
        return $user->warehouses->contains($r->from_warehouse_id);
    }

    public function receive(User $user, TransferRequisition $r): bool
    {
        return $user->warehouses->contains($r->to_warehouse_id);
    }

    public function recordLoss(User $user, TransferRequisition $r): bool
    {
        return $user->warehouses->contains($r->to_warehouse_id);
    }

    public function cancel(User $user, TransferRequisition $r): bool
    {
        return $r->canBeCancelled()
            && ($user->isAdmin() || $user->warehouses->contains($r->from_warehouse_id));
    }

    public function negotiate(User $user, TransferRequisition $r): bool
    {
        return in_array($r->status, [
            \App\Enums\TransferRequisitionStatus::Requested,
            \App\Enums\TransferRequisitionStatus::UnderReviewFulfiller,
            \App\Enums\TransferRequisitionStatus::UnderReviewRequestor,
        ], true) && (
            $user->warehouses->contains($r->from_warehouse_id)
            || $user->warehouses->contains($r->to_warehouse_id)
        );
    }

    public function viewAuditFilters(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor();
    }
}
