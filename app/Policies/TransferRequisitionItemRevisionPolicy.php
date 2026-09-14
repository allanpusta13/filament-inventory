<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TransferRequisitionItemRevision;
use App\Models\User;

class TransferRequisitionItemRevisionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TransferRequisitionItemRevision $revision): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TransferRequisitionItemRevision $revision): bool
    {
        return true;
    }

    public function delete(User $user, TransferRequisitionItemRevision $revision): bool
    {
        return true;
    }

    public function restore(User $user, TransferRequisitionItemRevision $revision): bool
    {
        return true;
    }

    public function forceDelete(User $user, TransferRequisitionItemRevision $revision): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return true;
    }

    public function restoreAny(User $user): bool
    {
        return true;
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
