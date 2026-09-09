<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly int $productId,
        public readonly int $warehouseId,
        public readonly int|float $requested,
        public readonly int|float $available,
    ) {
        parent::__construct(
            "Insufficient stock for product {$productId} in warehouse {$warehouseId}: "
            ."requested {$requested}, available {$available}.",
        );
    }
}
