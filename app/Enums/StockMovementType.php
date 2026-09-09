<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Receive = 'receive';
    case Ship = 'ship';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case TransitOut = 'transit_out';
    case TransitIn = 'transit_in';
    case Adjustment = 'adjustment';
    case Loss = 'loss';

    public function isInbound(): bool
    {
        return in_array($this, [self::Receive, self::TransferIn, self::TransitIn], true);
    }

    public function isOutbound(): bool
    {
        return in_array($this, [self::Ship, self::TransferOut, self::TransitOut, self::Loss], true);
    }
}
