<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SalesOrderItem — a single line on a sales order.
 *
 * Blueprint §3.17. Each item declares what was sold (in the sold unit),
 * how many base units that represents, the sale price **snapshotted at
 * confirm time** (`unit_sale_price_snapshot`), and the running
 * dispatched base qty. Line-level over-dispatch and over-return are
 * guarded by `outstandingBaseQty()` and `alreadyReturnedBaseQty()`,
 * re-checked inside `SalesService::dispatchSale()` and
 * `SalesService::recordSalesReturn()` under parent/item/variant locks
 * (§6.5).
 *
 * Sale price snapshot (§6.5, §19.5):
 *   `SalesService::confirmSalesOrder()` locks the variant and copies
 *   `currentPrice.sale_price` into `unit_sale_price_snapshot`. The
 *   column stays at its `0.0000` default until confirm time. Sales
 *   dispatch never writes back to the variant's current price — the
 *   snapshot is the sold-at value for reporting and returns.
 *
 * Sales returns (§6.5, A6):
 *   `alreadyReturnedBaseQty()` sums `SaleReturn` movements tagged with
 *   `reference_type = SalesOrderItem::class` and
 *   `reference_id = (string) $this->id`. This is the cumulative
 *   return count that the over-return guard subtracts from
 *   `dispatched_base_qty`. There is no reversal pathway for a
 *   dispatched sale other than a return (A6).
 *
 * A10: `substitute_product_variant_id` is intentionally absent —
 * substitution is a transfer/requisition feature only. Sales operate
 * on the exact variant sold.
 *
 * No `SoftDeletes` — the §2.19 schema declares no `deleted_at`. Items
 * cascade with their parent order (§2.19 `cascadeOnDelete`).
 *
 * Factory: `App\Database\Factories\SalesOrderItemFactory` (§5.16).
 */
class SalesOrderItem extends Model
{
    use HasFactory;

    /**
     * Mass-assignable attributes (§2.19).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sales_order_id', 'product_variant_id', 'unit_name', 'unit_ratio',
        'qty', 'base_qty', 'unit_sale_price_snapshot', 'dispatched_base_qty', 'notes',
    ];

    /**
     * Attribute casts (§3.17).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'unit_ratio' => 'integer',
        'qty' => 'integer',
        'base_qty' => 'integer',
        'unit_sale_price_snapshot' => 'decimal:4',
        'dispatched_base_qty' => 'integer',
    ];

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    // ---------------------------------------------------------------------
    // Outstanding + return helpers
    // ---------------------------------------------------------------------

    /**
     * The outstanding base quantity for this line.
     *
     * `base_qty − dispatched_base_qty`, floored at 0. Used by
     * `GuardsOutstandingQuantity::assertSaleNotOverDispatched()` (§6.1)
     * and re-checked inside `SalesService::dispatchSale()` (§6.5).
     *
     * The floor is defensive — the guard should never allow
     * over-dispatch — but the hard floor means a stale or concurrent
     * write never surfaces a negative value to a caller.
     */
    public function outstandingBaseQty(): int
    {
        return max(0, (int) $this->base_qty - (int) $this->dispatched_base_qty);
    }

    /**
     * The cumulative base quantity already returned for this line.
     *
     * §6.5 `recordSalesReturn()` writes `SaleReturn` movements tagged
     * with `reference_type = SalesOrderItem::class` and
     * `reference_id = (string) $this->id`. This method sums them to
     * obtain the cumulative returned base qty, which the over-return
     * guard subtracts from `dispatched_base_qty`.
     *
     * A6: there is no reversal pathway for a dispatched sale other than
     * a return; this is the accounting source of truth for how much has
     * come back.
     */
    public function alreadyReturnedBaseQty(): int
    {
        return (int) StockMovement::query()
            ->where('type', StockMovementType::SaleReturn->value)
            ->where('reference_type', self::class)
            ->where('reference_id', (string) $this->id)
            ->sum('quantity');
    }
}
