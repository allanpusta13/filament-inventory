<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when a product family is being soft-deleted while it still
 * has one or more variants referencing it.
 *
 * Blueprint §6.3 (exception contract).
 *
 * Translation key: `errors.product_family_has_variants`
 * Context: product
 *
 * Guarded by `App\Observers\ProductObserver::deleting()` (§3.19),
 * registered in `AppServiceProvider::boot()` (§17.3). The observer
 * fires on soft delete only — a force delete (`isForceDeleting()`)
 * bypasses the guard, matching the §3.19 contract.
 *
 * Presentation layer (§0A.10):
 *   Notification::make()
 *       ->danger()
 *       ->title(__('errors.product_family_has_variants.title'))
 *       ->body(__('errors.product_family_has_variants.body', $e->context()))
 *       ->send();
 *
 * The context key intentionally mirrors the §6.3 key-catalogue row
 * `errors.product_family_has_variants → product` so that
 * `lang/{locale}/errors.php` `errors.product_family_has_variants.body`
 * can interpolate `:product` directly, per the §0A.2a canonical
 * template:
 *     'Product :product still has variants and cannot be deleted.'
 *
 * `productId` is stored as a readonly promoted property so callers
 * that want to branch on the concrete family id can do so without
 * re-parsing the string context. The context array carries the
 * integer id, matching how `ProductObserver` (§3.19) constructs the
 * exception:
 *
 *     throw new ProductFamilyHasVariantsException((int) $product->id);
 *
 * Extends DomainErrorException (§6.3) so the type is both a
 * \DomainException (existing catch blocks keep working) and carries
 * the stable translation key + machine context for the presentation
 * layer to resolve.
 */
class ProductFamilyHasVariantsException extends DomainErrorException
{
    public function __construct(public readonly int $productId)
    {
        parent::__construct('errors.product_family_has_variants', [
            'product' => $productId,
        ]);
    }
}
