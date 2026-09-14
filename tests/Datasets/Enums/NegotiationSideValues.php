<?php

declare(strict_types=1);

namespace Tests\Datasets\Enums;

use App\Enums\NegotiationSide;

class NegotiationSideValues
{
    public static function all(): array
    {
        return NegotiationSide::cases();
    }

    public static function requestor(): array
    {
        return [NegotiationSide::Requestor];
    }

    public static function fulfiller(): array
    {
        return [NegotiationSide::Fulfiller];
    }
}
