<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProductVariantUnitConversion — one unit-of-measure row for a variant.
 *
 * Blueprint §3.4. Each row declares a unit name and its integer ratio
 * to the variant's base unit (`base_unit_name`). Every variant carries
 * at least one row: the base-unit self-conversion row where
 * `unit_name = product_variant.base_unit_name` and
 * `base_unit_ratio = 1` (F19), auto-created by
 * `ProductVariantObserver::created()` (§3.19, registered §17.3).
 *
 * Uniqueness: `(product_variant_id, unit_name)` — one row per unit per
 * variant (§2.4).
 *
 * No `SoftDeletes` — the §2.4 schema declares no `deleted_at`. Unit
 * conversions are managed in place: the base-unit self-conversion row
 * is undeletable via the UI (`ManageUnitConversionsAction`, §7A.4.4),
 * and non-base rows are deleted/recreated as the operator edits them.
 *
 * Factory: `App\Database\Factories\ProductVariantUnitConversionFactory`
 *          (§5.4) — includes a `->baseUnit()` state that produces the
 *          self-conversion row for tests that bypass the observer.
 */
class ProductVariantUnitConversion extends Model
{
    use HasFactory;

    /**
     * Mass-assignable attributes (§2.4).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_variant_id', 'unit_name', 'base_unit_ratio',
        'is_default_purchase', 'is_default_transfer',
    ];

    /**
     * Attribute casts (§3.4).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'base_unit_ratio' => 'integer',
        'is_default_purchase' => 'boolean',
        'is_default_transfer' => 'boolean',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Whether this row is the variant's base-unit self-conversion row
     * (F19).
     *
     * Callers iterating conversions MUST eager-load `productVariant`
     * (e.g. `with('productVariant')`). When the relation is not already
     * loaded it is resolved with one explicit query instead of an
     * implicit lazy load, and a missing parent resolves to `false`
     * instead of fataling on a null property read.
     */
    public function isBaseUnitRow(): bool
    {
        $variant = $this->relationLoaded('productVariant')
            ? $this->productVariant
            : $this->productVariant()->first();

        return $variant !== null
            && $this->unit_name === $variant->base_unit_name
            && $this->base_unit_ratio === 1;
    }
}
