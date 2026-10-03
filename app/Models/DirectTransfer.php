<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DirectTransfer — the multi-line fire-and-forget transfer header.
 *
 * Blueprint §3.20 / A11. A direct transfer is a single atomic
 * transaction that moves one or more distinct variants between two
 * warehouses. It has NO lifecycle states and NO reversal pathway;
 * correction is achieved by a reverse direct transfer. The header
 * exists solely to group the paired ledger movements and provide an
 * audit surface.
 *
 * All line items share the same `from_warehouse_id` and
 * `to_warehouse_id`. The service-layer invariant
 * `from_warehouse_id ≠ to_warehouse_id` is enforced inside
 * `InventoryService::directTransfer()` (§6.2); the model itself does
 * not guard it.
 *
 * Ledger linkage (§6.2): every `DirectTransferItem` produces one
 * paired `TransferOut` + `TransferIn` movement, tagged with
 * `reference_type = DirectTransfer::class` and
 * `reference_id = (string) $header->id`. The `TransferIn` row links
 * to the `TransferOut` row via `related_movement_id`.
 *
 * No `SoftDeletes` — the §2.21 schema declares no `deleted_at`.
 * Deletion is policy-side (`DirectTransferPolicy::delete()` §8.13,
 * admin-only) and blocked by the `restrictOnDelete` FK on
 * `direct_transfer_items.direct_transfer_id`? — no: the item FK is
 * `cascadeOnDelete`, so a header force-delete cascades to items;
 * the ledger rows do NOT cascade (they are referenced by
 * `reference_type` / `reference_id`, not an FK).
 *
 * Relations (§3.20):
 *   - fromWarehouse()  — BelongsTo on `from_warehouse_id`
 *   - toWarehouse()    — BelongsTo on `to_warehouse_id`
 *   - transferredBy()  — BelongsTo on `transferred_by`
 *   - items()          — HasMany DirectTransferItem
 *
 * Factory: `App\Database\Factories\DirectTransferFactory` (§5.17).
 * Policy:  `App\Policies\DirectTransferPolicy` (§8.13).
 */
class DirectTransfer extends Model
{
    use HasFactory;

    /**
     * Mass-assignable attributes (§2.21).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'reference_code',
        'from_warehouse_id',
        'to_warehouse_id',
        'notes',
        'transferred_by',
        'transferred_at',
    ];

    /**
     * Attribute casts (§3.20).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'transferred_at' => 'datetime',
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

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DirectTransferItem::class);
    }
}
