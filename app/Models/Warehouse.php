<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Warehouse — a physical stocking location.
 *
 * Blueprint §3.5. Warehouses are the second axis of stock identity:
 * every `StockMovement` pairs one `product_variant_id` with one
 * `warehouse_id` (§2.6), and every operational document either lives
 * in a single warehouse (purchases, sales) or spans two
 * (transfer requisitions, direct transfers, in-transits).
 *
 * The `code` column is unique and may be entered manually or derived
 * from `name` at the Create page (§7K.1 / §18.2a `CreateWarehouse::
 * deriveCode()`); the §2.5 `WH-####` pattern governs the manual /
 * factory path only.
 *
 * No `SoftDeletes` — the §2.5 schema declares no `deleted_at`.
 * Warehouses are protected from deletion by `WarehousePolicy::delete()`
 * (§8.11), which blocks deletion when the warehouse has any ledger
 * history or operational reference (stock movements, POs, SOs, TRs,
 * direct transfers, loss ledgers). The policy is the *only* delete
 * guard; there is no model-level observer.
 *
 * Relations (§3.5):
 *   - users()                      — `user_warehouse` pivot
 *   - stockMovements()             — HasMany
 *   - transferRequisitionsFrom()   — HasMany on from_warehouse_id
 *   - transferRequisitionsTo()     — HasMany on to_warehouse_id
 *   - purchaseOrders()             — HasMany
 *   - salesOrders()                — HasMany
 *   - lossLedgers()                — HasMany
 *   - directTransfersFrom()        — HasMany on from_warehouse_id
 *   - directTransfersTo()          — HasMany on to_warehouse_id
 *
 * Factory: `App\Database\Factories\WarehouseFactory` (§5.5).
 * Policy:  `App\Policies\WarehousePolicy` (§8.11).
 */
class Warehouse extends Model
{
    use HasFactory;

    /**
     * Mass-assignable attributes (§2.5).
     *
     * @var array<int, string>
     */
    protected $fillable = ['code', 'name', 'location', 'is_active'];

    /**
     * Attribute casts (§3.5).
     *
     * @var array<string, string>
     */
    protected $casts = ['is_active' => 'boolean'];

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

    /**
     * Users assigned to this warehouse via the `user_warehouse` pivot.
     *
     * §2.13: the pivot is edited from `UserResource` only; `WarehouseForm`
     * presents assignments read-only (§7K.1) to avoid last-write-wins
     * conflicts.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_warehouse');
    }

    /**
     * Every ledger row that names this warehouse (both directions:
     * source and destination, signed quantity).
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Requisitions where this warehouse is the source (shipping) side.
     */
    public function transferRequisitionsFrom(): HasMany
    {
        return $this->hasMany(TransferRequisition::class, 'from_warehouse_id');
    }

    /**
     * Requisitions where this warehouse is the destination (receiving) side.
     */
    public function transferRequisitionsTo(): HasMany
    {
        return $this->hasMany(TransferRequisition::class, 'to_warehouse_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function lossLedgers(): HasMany
    {
        return $this->hasMany(LossLedger::class);
    }

    /**
     * Direct transfers where this warehouse is the source (shipping) side.
     */
    public function directTransfersFrom(): HasMany
    {
        return $this->hasMany(DirectTransfer::class, 'from_warehouse_id');
    }

    /**
     * Direct transfers where this warehouse is the destination (receiving) side.
     */
    public function directTransfersTo(): HasMany
    {
        return $this->hasMany(DirectTransfer::class, 'to_warehouse_id');
    }
}
