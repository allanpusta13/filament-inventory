<?php

declare(strict_types=1);

namespace App\Observers;

use App\Exceptions\ProductFamilyHasVariantsException;
use App\Models\Product;

/**
 * ProductObserver — soft-delete guard for product families.
 *
 * Blueprint §3.19. Registered in `AppServiceProvider::boot()` (§17.3)
 * via `Product::observe(ProductObserver::class)`.
 *
 * Contract: a product family with one or more variants cannot be soft-
 * deleted. The guard fires on the `deleting` event and throws
 * `ProductFamilyHasVariantsException` (§6.3), aborting the delete
 * before the row is touched. Force deletes bypass the guard — the FK
 * cascade (§2.2 `cascadeOnDelete` on `product_variants.product_id`)
 * then removes the variants.
 *
 * The guard lives SOLELY here. `Product` (§3.1) has no `booted()`
 * override for this — a duplicated closure would throw twice for the
 * same violation and drift from the canonical observer.
 */
class ProductObserver
{
    public function deleting(Product $product): void
    {
        // Force delete bypasses the guard (cascade handles variants).
        if ($product->isForceDeleting()) {
            return;
        }

        if ($product->variants()->exists()) {
            throw new ProductFamilyHasVariantsException((int) $product->id);
        }
    }
}
