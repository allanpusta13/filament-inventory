<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DirectTransfer;
use App\Models\User;

/**
 * DirectTransfer policy — §8.13.
 *
 * ⚠ BranchManager: same tier as WarehouseStaff — create requires at
 * least two warehouse assignments (both endpoints must be in scope),
 * view requires both endpoints assigned, update is denied (fire-and-forget),
 * delete is admin-only, audit filters are admin/auditor only.
 */
class DirectTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DirectTransfer $t): bool
    {
        return $user->isAdmin()
            || $user->isAuditor()
            || ($user->warehouses->contains($t->from_warehouse_id)
                && $user->warehouses->contains($t->to_warehouse_id));
    }

    public function create(User $user): bool
    {
        // Admins hold global operational scope with a possibly empty
        // `user_warehouse` pivot, so they must not be gated on assignments.
        // Both warehouse selects are scoped to the user's assigned
        // warehouses and must differ (§7C.1), and the service requires
        // BOTH endpoints in the actor's assigned set (§6.2) — a
        // single-warehouse user could see the action but never submit a
        // valid direct transfer, so non-admin create requires at least
        // two assignments (same precondition as
        // `TransferRequisitionPolicy::create()`).
        // Auditors are read-only and never create operational documents,
        // even when assigned to warehouses.
        return $user->isAdmin()
            || (! $user->isAuditor() && $user->warehouses()->count() >= 2);
    }

    public function update(User $user, DirectTransfer $t): bool
    {
        return false; // fire-and-forget: no edit
    }

    public function delete(User $user, DirectTransfer $t): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * `$modelClass` is the `Model::class` string forwarded by the call
     * site (`->can('viewAuditFilters', Model::class)`): Laravel resolves
     * the owning policy from that class-string and passes it through as
     * the second argument. The decision itself is role-only (A8).
     *
     * ⚠ BranchManager is NOT granted audit-filter authority.
     */
    public function viewAuditFilters(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor();
    }
}
