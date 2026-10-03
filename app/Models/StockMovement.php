<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * StockMovement — the signed ledger row.
 *
 * Blueprint §3.6. This is the physical "stock of truth" ledger (§0
 * core principle 1): physical on-hand for a variant at a warehouse is
 * the sum of all signed `quantity` values on rows pairing that
 * `product_variant_id` with that `warehouse_id`. There is no stored
 * balance column anywhere in the schema.
 *
 * The `quantity` column is a signed integer:
 *   - positive for `TransferIn`, `Purchase`, `SaleReturn` (see
 *     `StockMovementType::isPositive()` §4.4);
 *   - negative for `TransferOut`, `Sale`, `PurchaseReturn` (and any
 *     `Loss`/`Damage` rows, though those cases have no writer by
 *     design — §4.4);
 *   - caller-signed for `Adjustment` (via `InventoryService::adjustment()`
 *     §6.2, which preserves the sign the operator supplies).
 *
 * Reference fields (`reference_type`, `reference_id`, `reference_code`)
 * are nullable because `InventoryService::recordMovement()` accepts a
 * `?string $referenceCode = null` for manual movements (§2.6). Header-
 * created movements always copy the header code, and `reference_id`
 * stores the stringified header id (e.g. `(string) $header->id`).
 * `reference_type` is one of the following class-strings (the only
 * values written by the services):
 *   - App\Models\TransferRequisition
 *   - App\Models\DirectTransfer
 *   - App\Models\PurchaseOrder
 *   - App\Models\SalesOrder
 *   - App\Models\SalesOrderItem
 *
 * FK discipline (§0 core principle 11): `product_variant_id` is
 * `restrictOnDelete` — a variant with ledger history cannot be
 * hard-deleted. `warehouse_id` is also `restrictOnDelete`.
 *
 * No `SoftDeletes` — the §2.6 schema declares no `deleted_at`.
 * Movements are append-only; corrections are made by writing reverse
 * movements, never by mutating or deleting a row.
 *
 * Factory: `App\Database\Factories\StockMovementFactory` (§5.19).
 * Policy:  `App\Policies\StockMovementPolicy` (§8.5) — read-only; no
 *          create/update/delete.
 */
class StockMovement extends Model
{
    use HasFactory;

    /**
     * Mass-assignable attributes (§2.6).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_variant_id', 'warehouse_id', 'type', 'quantity',
        'unit_name_used', 'unit_ratio_used', 'related_movement_id',
        'reference_type', 'reference_id', 'reference_code',
        'notes', 'created_by',
    ];

    /**
     * Attribute casts (§3.6).
     *
     * `type` is cast to the backed `StockMovementType` enum so callers
     * get typed access (`$movement->type->isPositive()`, etc.) without
     * re-hydrating.
     *
     * `quantity` and `unit_ratio_used` are cast to integer — defensive
     * normalization; `sum()` returns mixed on some drivers, and both
     * columns are integer-typed in §2.6.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'type' => StockMovementType::class,
        'quantity' => 'integer',
        'unit_ratio_used' => 'integer',
    ];

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The opposite-direction row for a paired transfer movement.
     *
     * §6.2 `directTransfer()` writes paired `TransferOut` / `TransferIn`
     * rows and links them via `related_movement_id` on the incoming
     * row (pointing to the outbound row). Requisition dispatch and
     * scan-to-receive do the same for their respective legs.
     *
     * `related_movement_id` is `nullOnDelete` (§2.6): if the outbound
     * row is ever deleted, the incoming row's link becomes null rather
     * than cascading or throwing.
     */
    public function relatedMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_movement_id');
    }
}
