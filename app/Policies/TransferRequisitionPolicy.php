<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TransferRequisitionStatus;
use App\Models\TransferRequisition;
use App\Models\User;

/**
 * TransferRequisition policy — §8.3.
 *
 * ⚠ BranchManager is treated as a non-auditor operational user with
 * warehouse scope — same tier as WarehouseStaff — for every operational
 * ability (create, update, submit, confirm, dispatch, receive, negotiate,
 * cancel). Admin-only abilities (delete, restore, forceDelete,
 * viewAuditFilters) remain admin-only.
 */
class TransferRequisitionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TransferRequisition $r): bool
    {
        return $user->isAdmin()
            || $user->isAuditor()
            || $user->warehouses->contains($r->from_warehouse_id)
            || $user->warehouses->contains($r->to_warehouse_id);
    }

    public function create(User $user): bool
    {
        // Both warehouse selects are scoped to the user's assigned
        // warehouses and must differ (§7B.1) — a single-warehouse user
        // could see the action but never submit a valid requisition,
        // so non-admin create requires at least two assignments.
        // Auditors are read-only and never create operational documents,
        // even when assigned to warehouses.
        return $user->isAdmin() || (! $user->isAuditor() && $user->warehouses()->count() >= 2);
    }

    public function update(User $user, TransferRequisition $r): bool
    {
        return ! $user->isAuditor()
            && $r->status === TransferRequisitionStatus::Draft
            && $user->warehouses->contains($r->from_warehouse_id);
    }

    public function delete(User $user, TransferRequisition $r): bool
    {
        return $user->isAdmin()
            && in_array($r->status, [
                TransferRequisitionStatus::Draft,
                TransferRequisitionStatus::Cancelled,
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
        return ! $user->isAuditor()
            && $r->status === TransferRequisitionStatus::Draft
            && $user->warehouses->contains($r->from_warehouse_id);
    }

    public function confirm(User $user, TransferRequisition $r): bool
    {
        return ! $user->isAuditor() && ($user->isAdmin()
            || $user->warehouses->contains($r->to_warehouse_id));
    }

    public function dispatch(User $user, TransferRequisition $r): bool
    {
        return ! $user->isAuditor() && ($user->isAdmin()
            || $user->warehouses->contains($r->from_warehouse_id));
    }

    public function receive(User $user, TransferRequisition $r): bool
    {
        return ! $user->isAuditor() && ($user->isAdmin()
            || $user->warehouses->contains($r->to_warehouse_id));
    }

    public function recordLoss(User $user, TransferRequisition $r): bool
    {
        return ! $user->isAuditor() && ($user->isAdmin()
            || $user->warehouses->contains($r->to_warehouse_id));
    }

    public function cancel(User $user, TransferRequisition $r): bool
    {
        return ! $user->isAuditor()
            && $r->canBeCancelled()
            && ($user->isAdmin() || $user->warehouses->contains($r->from_warehouse_id));
    }

    public function negotiate(User $user, TransferRequisition $r): bool
    {
        return ! $user->isAuditor() && in_array($r->status, [
            TransferRequisitionStatus::Requested,
            TransferRequisitionStatus::UnderReviewFulfiller,
            TransferRequisitionStatus::UnderReviewRequestor,
        ], true) && (
            $user->warehouses->contains($r->from_warehouse_id)
            || $user->warehouses->contains($r->to_warehouse_id)
        );
    }

    public function acceptRevision(User $user, TransferRequisition $r): bool
    {
        return ! $user->isAuditor() && in_array($r->status, [
            TransferRequisitionStatus::Requested,
            TransferRequisitionStatus::UnderReviewFulfiller,
            TransferRequisitionStatus::UnderReviewRequestor,
        ], true) && (
            $user->warehouses->contains($r->from_warehouse_id)
            || $user->warehouses->contains($r->to_warehouse_id)
        );
    }

    public function rejectRevision(User $user, TransferRequisition $r): bool
    {
        return ! $user->isAuditor() && in_array($r->status, [
            TransferRequisitionStatus::Requested,
            TransferRequisitionStatus::UnderReviewFulfiller,
            TransferRequisitionStatus::UnderReviewRequestor,
        ], true) && (
            $user->warehouses->contains($r->from_warehouse_id)
            || $user->warehouses->contains($r->to_warehouse_id)
        );
    }

    /**
     * `$modelClass` is the `Model::class` string forwarded by the call
     * site (`->can('viewAuditFilters', Model::class)`): Laravel resolves
     * the owning policy from that class-string and passes it through as
     * the second argument. The decision itself is role-only (A8).
     *
     * ⚠ BranchManager is NOT granted audit-filter authority — auditor
     * and admin only, per §8.
     */
    public function viewAuditFilters(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor();
    }
}
