<?php

declare(strict_types=1);

namespace App\Enums;

enum RevisionStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Superseded = 'superseded'; // Countered by a later revision before a decision was made

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::Superseded => 'Superseded',
        };
    }

    public function isResolved(): bool
    {
        return $this !== self::Pending;
    }
}
