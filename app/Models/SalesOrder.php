<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SalesOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SalesOrder — the customer order document.
 *
 * Blueprint §3.16. Sales are symmetric to purchases (A2): a sales
 * order lives in one warehouse, is placed by one customer, and follows
 * a lightweight lifecycle:
 *
 *   draft → confirmed → partially_dispatched → dispatched / cancelled
 *
 * The `confirmed` transition is where stock is reserved against the
 * order (§6.5 `SalesService::confirmSalesOrder()`), and where each
 * item's `unit_sale_price_snapshot` is captured from the variant's
 * locked current price (§6.5, §19.5). Over-dispatch and over-return
 * are guarded by `SalesOrderItem::outstandingBaseQty()` and
 * `alreadyReturnedBaseQty()`, re-checked inside
 * `SalesService::dispatchSale()` and `SalesService::recordSalesReturn()`
 * under parent/item/variant locks (§6.5).
 *
 * Cancellation boundary (§3.16): `canBeCancelled()` permits `Draft` or
 * `Confirmed` only. Once any stock has moved (PartiallyDispatched /
 * Dispatched), cancellation is denied — reversing a shipped sale
 * requires a sales return (§6.5 `recordSalesReturn()`), not a cancel.
 *
 * Soft-delete guard is policy-side (`SalesOrderPolicy::delete()` §8.8,
 * admin-only, `Draft` / `Cancelled` states only). There is no
 * model-level observer for this model.
 *
 * Relations (§3.16):
 *   - customer()     — BelongsTo
 *   - warehouse()    — BelongsTo
 *   - orderedBy()    — BelongsTo on `ordered_by`
 *   - dispatchedBy() — BelongsTo on `dispatched_by`
 *   - items()        — HasMany SalesOrderItem
 *
 * Factory: `App\Database\Factories\SalesOrderFactory` (§5.15).
 * Policy:  `App\Policies\SalesOrderPolicy` (§8.8).
 */
class SalesOrder extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Mass-assignable attributes (§2.18).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'reference_code', 'customer_id', 'warehouse_id', 'status',
        'ordered_by', 'dispatched_by',
        'ordered_at', 'confirmed_at', 'dispatched_at', 'cancelled_at', 'notes',
    ];

    /**
     * Attribute casts (§3.16).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => SalesOrderStatus::class,
        'ordered_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    // ---------------------------------------------------------------------
    // Lifecycle guards
    // ---------------------------------------------------------------------

    /**
     * Whether this sales order may be cancelled.
     *
     * §3.16 / §6.5 cancellation boundary — pre-dispatch states only
     * (`Draft`, `Confirmed`). Once stock has moved
     * (`PartiallyDispatched` / `Dispatched`), cancellation is denied;
     * a shipped sale is reversed by a sales return, not a cancel.
     * `Cancelled` is terminal.
     *
     * Enforced at two layers:
     *   - `SalesOrderPolicy::cancelSalesOrder()` (§8.8) — authorization.
     *   - `SalesService::cancelSalesOrder()` (§6.5) — re-checks this
     *     method inside the transaction under a parent lock before
     *     mutating status.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            SalesOrderStatus::Draft,
            SalesOrderStatus::Confirmed,
        ], true);
    }
}
