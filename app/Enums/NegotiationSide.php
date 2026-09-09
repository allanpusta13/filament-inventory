<?php

declare(strict_types=1);

namespace App\Enums;

enum NegotiationSide: string
{
    case Fulfiller = 'fulfiller';
    case Requestor = 'requestor';

    public function label(): string
    {
        return match ($this) {
            self::Fulfiller => 'Fulfiller',
            self::Requestor => 'Requestor',
        };
    }

    public function opposite(): self
    {
        return match ($this) {
            self::Fulfiller => self::Requestor,
            self::Requestor => self::Fulfiller,
        };
    }
}
