<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProductVariantPrice — a versioned price row for a single variant.
 *
 * Blueprint §3.3. Prices are decoupled from the variant (§0 core
 * principle 2) and versioned: every row carries both `cost_price` and
 * `sale_price` together, with an `effective_from` timestamp and an
 * `is_current` flag. Historical rows remain queryable; exactly one row
 * per variant carries `is_current = true`.
 *
 * The "at most one current row per variant" invariant (§2.3) is
 * enforced by two independent guards:
 *   1. The canonical price writer (SetCurrentPriceAction §7A.4.1,
 *      PurchaseService::updateCurrentCostPrice() §6.4) clears the
 *      previous current row inside the same transaction before
 *      inserting the new one.
 *   2. A partial unique index on `(product_variant_id) WHERE
 *      is_current = true` created by the §2.3 migration on drivers
 *      with filtered-index support (PostgreSQL, SQLite). MySQL relies
 *      on the service layer alone.
 *
 * No `booted()` observer is defined on this model. The invariant is
 * owned by the two guards above, not by an `updating`/`creating`
 * closure — a third guard at the model layer would silently interact
 * with both and drift from §2.3 / §6.4.
 *
 * Factory: `App\Database\Factories\ProductVariantPriceFactory` (§5.3).
 * Casts (§3.3):
 *   - cost_price / sale_price: 'decimal:4' (15,4 per §2.3)
 *   - effective_from: datetime
 *   - is_current: boolean
 */
class ProductVariantPrice extends Model
{
    use HasFactory;

    /**
     * Mass-assignable attributes (§2.3).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_variant_id', 'cost_price', 'sale_price',
        'effective_from', 'is_current', 'set_by', 'notes',
    ];

    /**
     * Attribute casts (§3.3).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'cost_price' => 'decimal:4',
        'sale_price' => 'decimal:4',
        'effective_from' => 'datetime',
        'is_current' => 'boolean',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * The user who set this price. Nullable — a price row may outlive
     * the user who created it (`set_by` FK is nullOnDelete, §2.3).
     */
    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
