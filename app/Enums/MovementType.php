<?php

declare(strict_types=1);

namespace App\Enums;

enum MovementType: string
{
    case Receive = 'receive';
    case Ship = 'ship';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Adjustment = 'adjustment';
}
