<?php

declare(strict_types=1);

use App\Enums\InTransitStatus;
use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use App\Enums\StockMovementType;
use App\Enums\TransferRequisitionStatus;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

$enums = [
    TransferRequisitionStatus::class,
    InTransitStatus::class,
    StockMovementType::class,
    NegotiationSide::class,
    RevisionStatus::class,
];

it('implements HasLabel, HasColor, and HasIcon on every domain enum', function (string $enumClass) {
    expect(is_a($enumClass, HasLabel::class, true))->toBeTrue()
        ->and(is_a($enumClass, HasColor::class, true))->toBeTrue()
        ->and(is_a($enumClass, HasIcon::class, true))->toBeTrue();
})->with($enums);

it('returns a non-empty label for every case', function (string $enumClass) {
    foreach ($enumClass::cases() as $case) {
        expect($case->getLabel())->toBeString()->not->toBeEmpty();
    }
})->with($enums);

it('returns a valid Filament color for every case', function (string $enumClass) {
    $validColors = ['gray', 'info', 'warning', 'primary', 'success', 'danger'];

    foreach ($enumClass::cases() as $case) {
        $color = $case->getColor();
        expect($color)->not->toBeNull();

        if (is_string($color)) {
            expect($validColors)->toContain($color);
        }
    }
})->with($enums);

it('returns a non-null icon for every case', function (string $enumClass) {
    foreach ($enumClass::cases() as $case) {
        expect($case->getIcon())->not->toBeNull();
    }
})->with($enums);
