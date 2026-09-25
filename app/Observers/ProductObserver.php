<?php

declare(strict_types=1);

namespace App\Observers;

use App\Exceptions\ProductFamilyHasVariantsException;
use App\Models\Product;

class ProductObserver
{
    public function deleting(Product $product): void
    {
        if ($product->isForceDeleting()) {
            return;
        }
        if ($product->variants()->exists()) {
            throw new ProductFamilyHasVariantsException((int) $product->id);
        }
    }
}
