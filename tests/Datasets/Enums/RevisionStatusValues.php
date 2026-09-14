<?php

declare(strict_types=1);

namespace Tests\Datasets\Enums;

use App\Enums\RevisionStatus;

class RevisionStatusValues
{
    public static function all(): array
    {
        return RevisionStatus::cases();
    }

    public static function pending(): array
    {
        return [RevisionStatus::Pending];
    }

    public static function resolved(): array
    {
        return [
            RevisionStatus::Accepted,
            RevisionStatus::Rejected,
        ];
    }
}
