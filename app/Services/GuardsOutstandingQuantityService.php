<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PurchaseOrderItem;
use App\Models\SalesOrderItem;
use DomainException;

class GuardsOutstandingQuantityService
{
    public function assertPurchaseNotOverReceived(PurchaseOrderItem $item, int $newReceived): void
    {
        $outstanding = $item->outstandingBaseQty();
        if ($newReceived > $outstanding) {
            throw new DomainException(
                "Cannot receive {$newReceived} base units; outstanding is {$outstanding}."
            );
        }
    }

    public function assertSaleNotOverDispatched(SalesOrderItem $item, int $newDispatch): void
    {
        $outstanding = $item->outstandingBaseQty();
        if ($newDispatch > $outstanding) {
            throw new DomainException(
                "Cannot dispatch {$newDispatch} base units; outstanding is {$outstanding}."
            );
        }
    }
}
