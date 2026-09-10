<?php

declare(strict_types=1);

use App\Models\ProductVariant;
use App\Models\ProductVariantUnitConversion;

it('converts a packaged quantity to base units', function () {
    $variant = ProductVariant::factory()->create();
    $box = ProductVariantUnitConversion::factory()->forVariant($variant)->create([
        'unit_name' => 'Box',
        'base_unit_ratio' => 24,
    ]);

    expect($box->toBaseUnits(3))->toBe(72);
});

it('rejects a duplicate unit_name for the same variant', function () {
    $variant = ProductVariant::factory()->create();
    ProductVariantUnitConversion::factory()->forVariant($variant)->create(['unit_name' => 'Box']);

    expect(fn () => ProductVariantUnitConversion::factory()->forVariant($variant)->create(['unit_name' => 'Box']))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('allows the same unit_name across different variants', function () {
    $variantA = ProductVariant::factory()->create();
    $variantB = ProductVariant::factory()->create();

    ProductVariantUnitConversion::factory()->forVariant($variantA)->create(['unit_name' => 'Box']);
    $second = ProductVariantUnitConversion::factory()->forVariant($variantB)->create(['unit_name' => 'Box']);

    expect($second->exists)->toBeTrue();
});
