<?php

declare(strict_types=1);

namespace Tests\Datasets\Enums;

use App\Enums\StockMovementType;

class StockMovementTypeValues
{
    public static function all(): array
    {
        return StockMovementType::cases();
    }

    public static function inbound(): array
    {
        return [
            StockMovementType::Receive,
            StockMovementType::TransferIn,
            StockMovementType::TransitIn,
        ];
    }

    public static function outbound(): array
    {
        return [
            StockMovementType::Ship,
            StockMovementType::TransferOut,
            StockMovementType::TransitOut,
        ];
    }

    public static function adjustment(): array
    {
        return [StockMovementType::Adjustment];
    }

    public static function transfer(): array
    {
        return [
            StockMovementType::TransferOut,
            StockMovementType::TransferIn,
        ];
    }

    public static function inTransit(): array
    {
        return [
            StockMovementType::TransitOut,
            StockMovementType::TransitIn,
        ];
    }
}