<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TransferOrder;
use App\Models\TransferOrderAudit;
use App\Models\User;

final class AuditService
{
    /**
     * Record an audit trail entry for a transfer order mutation.
     * Must be called within the same DB::transaction as the mutation.
     *
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     */
    public function record(TransferOrder $order, User $user, string $action, array $changes): void
    {
        TransferOrderAudit::create([
            'transfer_order_id' => $order->id,
            'user_id' => $user->id,
            'action' => $action,
            'changes_payload' => $changes,
        ]);
    }
}
