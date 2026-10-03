<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PurchaseOrder — the supplier order document.
 *
 * Blueprint §3.14. Purchases are a single-entity flow (A2): a purchase
 * order lives in one warehouse, is placed with one supplier, and
 * follows a lightweight lifecycle:
 *
 *   draft → ordered → partially_received → received / cancelled
 *
 * Unlike transfer requisitions, purchases have no negotiation loop and
 * no loss concept at intake (A7). Over-receive is guarded by
 * `PurchaseOrderItem::outstandingBaseQty()` and
 * `GuardsOutstandingQuantity::assertPurchaseNotOverReceived()` (§6.1),
 * re-checked inside `PurchaseService::receivePurchase()` (§6.4) under
 * parent/item/variant locks.
 *
 * Cost update is opt-in per order (§6.4, A4): when
 * `update_cost_price = true`, receiving replaces the variant's current
 * `cost_price` and preserves the existing `sale_price` in the new
 * `ProductVariantPrice` row.
 *
 * Cancellation boundary (§3.14): `canBeCancelled()` permits `Draft` or
 * `Ordered` states AND requires that no item has `received_base_qty > 0`.
 * Both the state and the item-level guard are re-checked inside
 * `PurchaseService::cancelPurchaseOrder()` (§6.4) under a parent lock.
 *
 * Soft-delete guard is policy-side (`PurchaseOrderPolicy::delete()`
 * §8.7, admin-only, `Draft` / `Cancelled` states only). There is no
 * model-level observer for this model.
 *
 * Relations (§3.14):
 *   - supplier()   — BelongsTo
 *   - warehouse()  — BelongsTo
 *   - orderedBy()  — BelongsTo on `ordered_by`
 *   - receivedBy() — BelongsTo on `received_by`
 *   - items()      — HasMany PurchaseOrderItem
 *
 * Factory: `App\Database\Factories\PurchaseOrderFactory` (§5.13).
 * Policy:  `App\Policies\PurchaseOrderPolicy` (§8.7).
 */
class PurchaseOrder extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Mass-assignable attributes (§2.16).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'reference_code', 'supplier_id', 'warehouse_id', 'status',
        'update_cost_price', 'ordered_by', 'received_by',
        'ordered_at', 'received_at', 'cancelled_at', 'notes',
    ];

    /**
     * Attribute casts (§3.14).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => PurchaseOrderStatus::class,
        'update_cost_price' => 'boolean',
        'ordered_at' => 'datetime',
        'received_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    // ---------------------------------------------------------------------
    // Lifecycle guards
    // ---------------------------------------------------------------------

    /**
     * Whether this purchase order may be cancelled.
     *
     * §3.14 / §6.4 cancellation boundary — two independent conditions:
     *
     *   1. Status must be `Draft` or `Ordered`. `PartiallyReceived` is
     *      already mid-intake; `Received` is fully accounted;
     *      `Cancelled` is terminal.
     *   2. No item may have `received_base_qty > 0`. Even an `Ordered`
     *      order whose items have all been received is not cancellable —
     *      reversing a partially-received purchase requires a supplier
     *      return (deferred, §13 item 4), not cancellation.
     *
     * Enforced at two layers:
     *   - `PurchaseOrderPolicy::cancelPurchase()` (§8.7) — authorization.
     *   - `PurchaseService::cancelPurchaseOrder()` (§6.4 / §19.4) —
     *     re-checks this method inside the transaction under a parent
     *     lock before mutating status.
     */
    public function canBeCancelled(): bool
    {
        if (! in_array($this->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Ordered], true)) {
            return false;
        }

        return ! $this->items()->where('received_base_qty', '>', 0)->exists();
    }
}
