<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DirectTransferItem — one line on a direct transfer header.
 *
 * Blueprint §3.21 / A11. Each item carries the variant moved, the unit
 * (and its ratio to the variant's base unit), the qty in that unit,
 * and the corresponding base qty. One paired `TransferOut` +
 * `TransferIn` `StockMovement` is written per item by
 * `InventoryService::directTransfer()` (§6.2) inside the header's
 * single transaction.
 *
 * Invariants (A11 / §2.22):
 *   - All items in a `DirectTransfer` share the header's
 *     `from_warehouse_id` and `to_warehouse_id`. This is enforced at
 *     the service layer (the item table has no warehouse columns);
 *     the model itself is warehouse-agnostic.
 *   - A transfer contains one or more **distinct** variants — no
 *     duplicate `product_variant_id` per header. Enforced at the
 *     service layer via `disableOptionsWhenSelectedInSiblingRepeaterItems()`
 *     (§7C.1) and the `errors.duplicate_transfer_variant` guard (§6.2).
 *   - `base_qty = qty × unit_ratio` — enforced at the service layer.
 *
 * A10: `substitute_product_variant_id` is intentionally absent —
 * substitution is a transfer/requisition feature only; direct transfers
 * operate on the exact variant moved.
 *
 * No `SoftDeletes` — the §2.22 schema declares no `deleted_at`. Items
 * cascade with their parent header (§2.22 `cascadeOnDelete`).
 *
 * Factory: `App\Database\Factories\DirectTransferItemFactory` (§5.18).
 */
class DirectTransferItem extends Model
{
    use HasFactory;

    /**
     * Mass-assignable attributes (§2.22).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'direct_transfer_id',
        'product_variant_id',
        'unit_name',
        'unit_ratio',
        'qty',
        'base_qty',
        'notes',
    ];

    /**
     * Attribute casts (§3.21).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'unit_ratio' => 'integer',
        'qty' => 'integer',
        'base_qty' => 'integer',
    ];

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

    public function directTransfer(): BelongsTo
    {
        return $this->belongsTo(DirectTransfer::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
