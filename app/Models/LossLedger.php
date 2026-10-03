<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LossCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

/**
 * LossLedger — the append-only record of inventory shrinkage.
 *
 * Blueprint §3.11. One row per shortfall/damage event recorded against
 * a transfer requisition intake (or a manual loss when the requisition
 * FKs are null, per the §2.11 nullable columns). The ledger captures
 * the financial snapshot of the loss: the base quantities lost and
 * damaged, the snapshot unit cost, and the pre-computed total
 * financial loss.
 *
 * Authoring paths (§6.2):
 *   - `InventoryService::writeOffOmittedItem()` — first-scan omitted
 *     cargo. Always writes `lost_base_qty` = approved qty,
 *     `damaged_base_qty` = 0, `loss_category` = 'shortfall'.
 *   - `InventoryService::recordLoss()` — operator-supplied loss
 *     (any `LossCategory` case from the §7B.3 modal).
 *
 * Cost snapshot (§0 core principle 15):
 *   `snapshotUnitCostFrom()` captures `currentPrice.cost_price` at
 *   call time. If cost is missing or zero, it logs a warning and
 *   returns '0.0000' — the loss is recorded at zero financial impact
 *   but flagged for review. `calculateTotalFinancialLoss()` uses
 *   `bcmul()` at 4dp to avoid float drift.
 *
 * No `SoftDeletes` — the §2.11 schema declares no `deleted_at`. Loss
 * rows are append-only; correction is via a new offsetting row, never
 * by mutating or deleting an existing one.
 *
 * Factory: `App\Database\Factories\LossLedgerFactory` (§5.12).
 * Policy:  `App\Policies\LossLedgerPolicy` (§8.6) — read-only; no
 *          create/update/delete.
 */
class LossLedger extends Model
{
    use HasFactory;

    /**
     * Mass-assignable attributes (§2.11).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transfer_requisition_id', 'transfer_requisition_item_id',
        'product_variant_id', 'warehouse_id', 'lost_base_qty', 'damaged_base_qty',
        'unit_cost_price', 'total_financial_loss', 'loss_category',
        'notes', 'recorded_by', 'recorded_at',
    ];

    /**
     * Attribute casts (§3.11).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'lost_base_qty' => 'integer',
        'damaged_base_qty' => 'integer',
        'unit_cost_price' => 'decimal:4',
        'total_financial_loss' => 'decimal:4',
        'loss_category' => LossCategory::class,
        'recorded_at' => 'datetime',
    ];

    // ---------------------------------------------------------------------
    // Cost snapshot helpers
    // ---------------------------------------------------------------------

    /**
     * Snapshot the current cost price of the variant (Principle 15).
     *
     * If cost is missing or zero, log a warning — the loss will be
     * recorded at zero financial impact but flagged for review.
     *
     * The caller is responsible for resolving the *actual* variant
     * (substitute when negotiated) before calling this method — §6.2
     * `writeOffOmittedItem()` and `recordLoss()` both pass the variant
     * from `$item->actualVariantId()`.
     *
     * @return string The cost as a 4dp string ('0.0000' when missing).
     */
    public static function snapshotUnitCostFrom(ProductVariant $variant): string
    {
        $cost = $variant->currentPrice?->cost_price;

        if ($cost === null || bccomp((string) $cost, '0.0000', 4) === 0) {
            Log::warning('Loss recorded with missing or zero cost price.', [
                'product_variant_id' => $variant->id,
                'sku' => $variant->sku,
            ]);

            return '0.0000';
        }

        return (string) $cost;
    }

    /**
     * Compute total loss using BCMath to avoid float drift.
     *
     * @return string The total as a 4dp string.
     */
    public static function calculateTotalFinancialLoss(string $unitCost, int $totalQty): string
    {
        return bcmul($unitCost, (string) $totalQty, 4);
    }

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

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
