<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InTransitStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * InTransit — a dispatched requisition line still in motion.
 *
 * Blueprint §3.10. One row per requisition item dispatched from the
 * source warehouse and not yet accounted at the destination. Created
 * by `InventoryService::dispatchTransfer()` (§6.2) at dispatch time,
 * updated by `scanToReceive()` / `writeOffOmittedItem()` /
 * `recordLoss()` and transitioned to `Cleared` or `Lost` via
 * `markInTransit()` (§6.2).
 *
 * Status semantics (§4.7, §6.2):
 *   - `in_transit` — dispatched, not yet accounted. Terminal states
 *                    are reachable only from this state.
 *   - `cleared`    — good + damaged receipts cover the approved qty.
 *   - `lost`       — the shortfall write-off covers the remainder.
 *
 * The `dispatched_base_qty` snapshot is fixed at dispatch time and
 * never mutated; the item's own `received_good_base_qty`,
 * `received_damaged_base_qty`, and `received_qty` counters (§3.8)
 * track the intake progress against it.
 *
 * No `SoftDeletes` — the §2.10 schema declares no `deleted_at`. Rows
 * are cascade-deleted with their parent requisition (§2.10
 * `cascadeOnDelete` on both the requisition and the item FKs).
 *
 * Factory: `App\Database\Factories\InTransitFactory` (§5.11).
 */
class InTransit extends Model
{
    use HasFactory;

    /**
     * Mass-assignable attributes (§2.10).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transfer_requisition_id', 'transfer_requisition_item_id',
        'product_variant_id', 'dispatched_base_qty', 'dispatched_at',
        'status', 'cleared_at',
    ];

    /**
     * Attribute casts (§3.10).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'dispatched_base_qty' => 'integer',
        'dispatched_at' => 'datetime',
        'status' => InTransitStatus::class,
        'cleared_at' => 'datetime',
    ];

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

    public function transferRequisition(): BelongsTo
    {
        return $this->belongsTo(TransferRequisition::class);
    }

    public function transferRequisitionItem(): BelongsTo
    {
        return $this->belongsTo(TransferRequisitionItem::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
