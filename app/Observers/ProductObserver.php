<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Product;
use Exception;

class ProductObserver
{
    public function deleting(Product $product): void
    {
        if ($product->isForceDeleting()) {
            return;
        }

        $activeVariants = $product->variants()->whereNull('deleted_at')->count();

        if ($activeVariants > 0) {
            throw new Exception(
                "Cannot soft-delete Product #{$product->id}: {$activeVariants} ".
                'active variant(s) must be trashed or reassigned first.'
            );
        }
    }
}
