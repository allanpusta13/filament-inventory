<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\DirectTransferItem;

class DirectTransferItemObserver
{
    public function created(DirectTransferItem $item): void
    {
        // Direct transfer item created
    }

    public function deleted(DirectTransferItem $item): void
    {
        // Direct transfer item deleted
    }
}
