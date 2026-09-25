<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\DirectTransfer;

class DirectTransferObserver
{
    public function created(DirectTransfer $transfer): void
    {
        // Direct transfer created - could trigger notifications
    }

    public function deleted(DirectTransfer $transfer): void
    {
        // Direct transfer deleted - could trigger cleanup
    }
}
