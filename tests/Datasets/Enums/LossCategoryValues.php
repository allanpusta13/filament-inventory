<?php

declare(strict_types=1);

namespace Tests\Datasets\Enums;

class LossCategoryValues
{
    public static function all(): array
    {
        return [
            'shortfall',
            'damage',
            'spoilage',
            'theft',
            'other',
            'omitted_from_intake',
        ];
    }

    public static function physicalLoss(): array
    {
        return ['shortfall', 'theft', 'omitted_from_intake'];
    }

    public static function damageRelated(): array
    {
        return ['damage', 'spoilage'];
    }
}