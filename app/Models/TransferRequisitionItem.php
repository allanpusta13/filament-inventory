<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TransferRequisitionItem — a single line on a transfer requisition.
 *
 * Blueprint §3.8. Each item carries both the *requested* leg (what the
 * requestor asked for) and the *approved* leg (what the fulfiller
 * committed to at confirm time, materialized by
 * `NegotiationService::materializeRequestedAsApproved()` §6.3). The
 * approved leg may also carry a substitute variant when a revision
 * was negotiated and accepted — see §0 core principle 6 and
 * `actualVariantId()` below.
 *
 * The item is the bridge between the requisition document and the
 * signed ledger: `dispatchTransfer()` (§6.2) writes one `TransferOut`
 * movement per item at dispatch time, keyed by `actualVariantId()`
 * (the substitute when one was negotiated, otherwise the requested
 * variant); `scanToReceive()` writes the matching `TransferIn`
 * movements and updates the three received-qty counters.
 *
 * Unit conversion (§0 core principle 2 / F18): each leg carries its
 * own `*_unit_name` and `*_unit_ratio`, so the requestor and the
 * fulfiller may negotiate on different units. Both `*_base_qty` are
 * stored alongside the unit-quantity so the ledger arithmetic is
 * integer-only.
 *
 * No `SoftDeletes` — the §2.8 schema declares no `deleted_at`. Items
 * are cascade-deleted with their parent requisition (§2.8
 * `cascadeOnDelete`).
 *
 * Factory: `App\Database\Factories\TransferRequisitionItemFactory`
 *          (§5.10).
 */
class TransferRequisitionItem extends Model
{
    use HasFactory;

    /**
     * Mass-assignable attributes (§2.8).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transfer_requisition_id', 'product_variant_id', 'substitute_product_variant_id',
        'requested_unit_name', 'requested_unit_ratio', 'requested_qty', 'requested_base_qty',
        'approved_unit_name', 'approved_unit_ratio', 'approved_qty', 'approved_base_qty',
        'shipped_base_qty', 'received_good_base_qty', 'received_damaged_base_qty',
        'received_qty', 'notes',
    ];

    /**
     * Attribute casts (§3.8).
     *
     * Every integer column is cast defensively — `sum()` returns mixed
     * on some drivers, and every arithmetic operation in the services
     * compares these values against `approved_base_qty`, so integer
     * normalization is load-bearing.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'requested_unit_ratio' => 'integer',
        'requested_qty' => 'integer',
        'requested_base_qty' => 'integer',
        'approved_unit_ratio' => 'integer',
        'approved_qty' => 'integer',
        'approved_base_qty' => 'integer',
        'shipped_base_qty' => 'integer',
        'received_good_base_qty' => 'integer',
        'received_damaged_base_qty' => 'integer',
        'received_qty' => 'integer',
    ];

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

    public function transferRequisition(): BelongsTo
    {
        return $this->belongsTo(TransferRequisition::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function substituteProductVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'substitute_product_variant_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(TransferRequisitionItemRevision::class);
    }

    // ---------------------------------------------------------------------
    // Effective-variant + outstanding helpers
    // ---------------------------------------------------------------------

    /**
     * The effective (actual) variant id this line moves.
     *
     * §0 core principle 6 / A10: dispatch and receipt pipelines resolve
     * `$actualVariantId = $item->substitute_product_variant_id ??
     * $item->product_variant_id`. Reservation is also booked against
     * this effective variant (`ProductVariant::reservedQuantity()` §3.2
     * and `batchAvailableQuantity()`).
     */
    public function actualVariantId(): int
    {
        return $this->substitute_product_variant_id ?? $this->product_variant_id;
    }

    /**
     * The outstanding shipped-base quantity for this line.
     *
     * `approved_base_qty − shipped_base_qty`, floored at 0. Used by
     * `GuardsOutstandingQuantity::assertTransferNotOverShipped()` (§6.1)
     * and re-checked inside `InventoryService::dispatchTransfer()` (§6.2).
     *
     * `approved_base_qty` may be null before confirm materialization —
     * the `(int)` casts on the operands normalize `null` to `0`, so the
     * result is `max(0, 0 − shipped)` = 0 on an un-materialized item,
     * which is the correct conservative answer.
     */
    public function outstandingShippedBaseQty(): int
    {
        return max(0, (int) $this->approved_base_qty - (int) $this->shipped_base_qty);
    }
}
