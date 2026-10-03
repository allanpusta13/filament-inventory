<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Product — the family/grouping container for product variants.
 *
 * Blueprint §3.1. A `Product` is a pure catalog family container:
 * it holds no SKU, no price, no unit, no reorder point. Every SKU-
 * bearing, priced, and stock-tracked unit lives on `ProductVariant`
 * (§3.2), which belongs to this family via `product_id`.
 *
 * Soft-delete guard lives SOLELY in `App\Observers\ProductObserver`
 * (§3.19, registered in `AppServiceProvider::boot()` per §17.3):
 * deleting a family that still has variants throws
 * `ProductFamilyHasVariantsException`. This model intentionally has NO
 * `booted()` override for that guard — a duplicated closure would
 * throw twice for the same violation and drift from the canonical
 * observer.
 *
 * Soft deletes: the `deleted_at` column (§2.1) is handled by the
 * `SoftDeletes` trait. Views / lookups that need to bypass the trait
 * use `withoutGlobalScopes([SoftDeletingScope::class])`, matching the
 * pattern in `ProductResource::getRecordRouteBindingEloquentQuery()`
 * (§1A / §18.1a).
 *
 * Factory: `App\Factories\ProductFactory` (§5.1).
 * Observer: `App\Observers\ProductObserver` (§3.19).
 * Policy:   `App\Policies\ProductPolicy` (§8.1).
 */
class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Mass-assignable attributes.
     *
     * §2.1 `products`: id, name, category, deleted_at, created_at,
     * updated_at. `id`, `deleted_at`, and the timestamps are managed by
     * Eloquent and are deliberately excluded from $fillable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['name', 'category'];

    /**
     * The variants that belong to this product family.
     *
     * §2.2: `product_variants.product_id` FK → `products.id`
     * (cascadeOnDelete). Deleting a family with variants is blocked at
     * the soft-delete layer by `ProductObserver::deleting()` (§3.19);
     * a force delete cascades to variants via the FK action.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
}
