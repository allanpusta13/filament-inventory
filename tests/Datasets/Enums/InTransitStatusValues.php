<?php

declare(strict_types=1);

namespace Tests\Datasets\Enums;

use App\Enums\InTransitStatus;

class InTransitStatusValues
{
    public static function all(): array
    {
        return InTransitStatus::cases();
    }

    public static function active(): array
    {
        return [
            InTransitStatus::InTransit,
            InTransitStatus::PartiallyReceived,
        ];
    }

    public static function completed(): array
    {
        return [InTransitStatus::Cleared];
    }
}