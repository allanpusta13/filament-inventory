<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use App\Exceptions\InvalidRevisionTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TransferRequisitionItemRevision — a single negotiation revision on
 * a transfer requisition item.
 *
 * Blueprint §3.9. Revisions are the negotiation loop's persisted state:
 * each row records one proposed change (substitute variant and/or
 * quantity) submitted by one side (`fulfiller` or `requestor`), and its
 * resolution status (`pending`, `accepted`, `rejected`). The
 * `responds_to_revision_id` self-FK chains the ping-pong history.
 *
 * Lifecycle (§3.9 `ensureCanTransitionTo()`, §6.3 NegotiationService):
 *   - `pending`  — submitted, awaiting counterpart response.
 *   - `accepted` — resolved positively; a downstream confirm materializes
 *                  the approved leg from this revision.
 *   - `rejected` — resolved negatively.
 * Exactly one move out of `pending` is legal (→ `accepted` or →
 * `rejected`). No transition from a resolved state, no self-transition
 * back into `pending`. Violations throw
 * `InvalidRevisionTransitionException` (§6.3).
 *
 * Negotiation boundaries (§6.3 `assertNegotiable()`):
 *   - Parent requisition must be in one of: `Requested`,
 *     `UnderReviewFulfiller`, `UnderReviewRequestor`.
 *   - The revision itself must be `Pending`.
 * Violations throw `NegotiationNotAllowedException` (§6.3).
 *
 * No `SoftDeletes` — the §2.9 schema declares no `deleted_at`. Revisions
 * are cascade-deleted with their parent item (§2.9 `cascadeOnDelete`).
 *
 * Factory: `App\Database\Factories\
 *           TransferRequisitionItemRevisionFactory` (§5.20).
 */
class TransferRequisitionItemRevision extends Model
{
    use HasFactory;

    /**
     * Mass-assignable attributes (§2.9).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transfer_requisition_item_id', 'user_id', 'product_variant_id',
        'substitute_product_variant_id', 'proposed_unit_name', 'proposed_unit_ratio',
        'proposed_qty', 'proposed_base_qty', 'negotiation_reason', 'side',
        'status', 'responds_to_revision_id', 'responded_at',
    ];

    /**
     * Attribute casts (§3.9).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'proposed_unit_ratio' => 'integer',
        'proposed_qty' => 'integer',
        'proposed_base_qty' => 'integer',
        'side' => NegotiationSide::class,
        'status' => RevisionStatus::class,
        'responded_at' => 'datetime',
    ];

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

    public function item(): BelongsTo
    {
        return $this->belongsTo(TransferRequisitionItem::class, 'transfer_requisition_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function substituteProductVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'substitute_product_variant_id');
    }

    public function respondsTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'responds_to_revision_id');
    }

    // ---------------------------------------------------------------------
    // Lifecycle guards
    // ---------------------------------------------------------------------

    /**
     * Whether this revision has been resolved (accepted or rejected).
     *
     * Complement of the `Pending` state — a resolved revision is
     * terminal and cannot be transitioned again.
     */
    public function isResolved(): bool
    {
        return $this->status !== RevisionStatus::Pending;
    }

    /**
     * Assert that this revision may transition to the given status.
     *
     * §3.9 guard: the current status MUST be `Pending`, and the target
     * MUST NOT be `Pending`. Any other combination is illegal and
     * throws `InvalidRevisionTransitionException` (§6.3).
     *
     * Used by `NegotiationService::accept()` and
     * `NegotiationService::reject()` (§6.3), which call this method
     * inside the revision-lock transaction after
     * `assertNegotiable()` has confirmed the parent requisition status.
     */
    public function ensureCanTransitionTo(RevisionStatus $target): void
    {
        if ($this->status !== RevisionStatus::Pending) {
            throw new InvalidRevisionTransitionException($this->status->value, $target->value);
        }
        if ($target === RevisionStatus::Pending) {
            throw new InvalidRevisionTransitionException($this->status->value, $target->value);
        }
    }
}
