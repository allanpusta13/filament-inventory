<?php

declare(strict_types=1);

namespace App\Support;

final class GeneratesReferenceCodes
{
    public static function generateReferenceCode(string $prefix, ?int $random = null): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.($random ?? random_int(100, 999));
    }
}
