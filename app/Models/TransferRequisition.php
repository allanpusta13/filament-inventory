<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransferRequisitionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * TransferRequisition — the primary inter-warehouse transfer document.
 *
 * Blueprint §3.7. The requisition is the document-shaped workflow that
 * moves stock between two warehouses across a negotiated lifecycle:
 *
 *   draft → requested → under_review_fulfiller ⇌ under_review_requestor
 *         → confirmed → dispatched ⇌ partially_received
 *         → completed / closed_with_loss / cancelled
 *
 * The document records its own lifecycle timestamps (`requested_at`,
 * `approved_at`, `dispatched_at`, `completed_at`) and four actor FKs
 * (`requested_by` / `approved_by` / `dispatched_by` / `received_by`),
 * so the sign-off chain is auditable end-to-end.
 *
 * Cancellation boundary (§0 core principle 14 / §3.7): `canBeCancelled()`
 * returns true only for the five pre-dispatch states. Every post-dispatch
 * state is terminal and cannot be cancelled. The boundary is enforced by
 * both the model method and the `cancel` policy ability (§8.3), and the
 * `TransferRequisitionService::cancelRequisition()` service (§6.6 / §19.2)
 * re-checks `canBeCancelled()` inside the transaction.
 *
 * Soft-delete guard lives in `TransferRequisitionPolicy::delete()` (§8.3),
 * which permits deletion only for `Draft` / `Cancelled` states, and only
 * by an admin. There is no model-level observer for this model.
 *
 * Relations (§3.7):
 *   - fromWarehouse()  — BelongsTo on `from_warehouse_id`
 *   - toWarehouse()    — BelongsTo on `to_warehouse_id`
 *   - requestedBy()    — BelongsTo on `requested_by`
 *   - approvedBy()     — BelongsTo on `approved_by`
 *   - dispatchedBy()   — BelongsTo on `dispatched_by`
 *   - receivedBy()     — BelongsTo on `received_by`
 *   - items()          — HasMany TransferRequisitionItem
 *   - inTransits()     — HasMany InTransit
 *   - lossLedgers()    — HasMany LossLedger
 *
 * Factory: `App\Database\Factories\TransferRequisitionFactory` (§5.9).
 * Policy:  `App\Policies\TransferRequisitionPolicy` (§8.3).
 */
class TransferRequisition extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Mass-assignable attributes (§2.7).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'reference_code', 'from_warehouse_id', 'to_warehouse_id', 'status',
        'requested_by', 'approved_by', 'dispatched_by', 'received_by',
        'requested_at', 'approved_at', 'dispatched_at', 'completed_at', 'notes',
    ];

    /**
     * Attribute casts (§3.7).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => TransferRequisitionStatus::class,
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransferRequisitionItem::class);
    }

    public function inTransits(): HasMany
    {
        return $this->hasMany(InTransit::class);
    }

    public function lossLedgers(): HasMany
    {
        return $this->hasMany(LossLedger::class);
    }

    // ---------------------------------------------------------------------
    // Lifecycle helpers
    // ---------------------------------------------------------------------

    /**
     * Pre-dispatch states only (Principle 14).
     *
     * Cancellation is legal exactly while the requisition is in one of
     * the five states before stock leaves the source warehouse:
     * `Draft`, `Requested`, `UnderReviewFulfiller`,
     * `UnderReviewRequestor`, `Confirmed`.
     *
     * Enforced at three layers:
     *   1. `TransferRequisitionPolicy::cancel()` (§8.3) — authorization.
     *   2. `TransferRequisitionService::cancelRequisition()` (§6.6 /
     *      §19.2) — re-checks this method inside the transaction.
     *   3. `TransferRequisitionsTable` `cancel` action — `->visible()`
     *      uses this method (UI hint only, not an authorization boundary).
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            TransferRequisitionStatus::Draft,
            TransferRequisitionStatus::Requested,
            TransferRequisitionStatus::UnderReviewFulfiller,
            TransferRequisitionStatus::UnderReviewRequestor,
            TransferRequisitionStatus::Confirmed,
        ], true);
    }
}
