<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PurchaseOrderItem — a single line on a purchase order.
 *
 * Blueprint §3.15. Each item declares what was ordered (in the ordered
 * unit), how many base units that represents, the agreed unit cost, and
 * the running received base qty. Line-level over-receive is guarded by
 * `outstandingBaseQty()` and
 * `GuardsOutstandingQuantity::assertPurchaseNotOverReceived()` (§6.1),
 * re-checked inside `PurchaseService::receivePurchase()` (§6.4) under
 * parent/item/variant locks.
 *
 * `unit_cost_price` is the agreed price for this line and is
 * **snapshotted at order time** — it does not follow the variant's
 * current `cost_price`. Whether that snapshot later replaces the
 * variant's current cost depends on the parent order's
 * `update_cost_price` flag (§6.4, A4).
 *
 * A10: `substitute_product_variant_id` is intentionally absent —
 * substitution is a transfer/requisition feature only. Purchases
 * operate on the exact variant ordered.
 *
 * No `SoftDeletes` — the §2.17 schema declares no `deleted_at`. Items
 * cascade with their parent order (§2.17 `cascadeOnDelete`).
 *
 * Factory: `App\Database\Factories\PurchaseOrderItemFactory` (§5.14).
 */
class PurchaseOrderItem extends Model
{
    use HasFactory;

    /**
     * Mass-assignable attributes (§2.17).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'purchase_order_id', 'product_variant_id', 'ordered_unit_name',
        'ordered_unit_ratio', 'ordered_qty', 'ordered_base_qty',
        'unit_cost_price', 'received_base_qty', 'notes',
    ];

    /**
     * Attribute casts (§3.15).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'ordered_unit_ratio' => 'integer',
        'ordered_qty' => 'integer',
        'ordered_base_qty' => 'integer',
        'unit_cost_price' => 'decimal:4',
        'received_base_qty' => 'integer',
    ];

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    // ---------------------------------------------------------------------
    // Outstanding helper
    // ---------------------------------------------------------------------

    /**
     * The outstanding base quantity for this line.
     *
     * `ordered_base_qty − received_base_qty`, floored at 0. Used by
     * `GuardsOutstandingQuantity::assertPurchaseNotOverReceived()`
     * (§6.1) and re-checked inside
     * `PurchaseService::receivePurchase()` (§6.4).
     *
     * The floor is defensive — the guard should never allow an item to
     * be over-received — but a hard floor here means a stale or
     * concurrent write can never surface a negative outstanding value
     * to a caller.
     */
    public function outstandingBaseQty(): int
    {
        return max(0, (int) $this->ordered_base_qty - (int) $this->received_base_qty);
    }
}
