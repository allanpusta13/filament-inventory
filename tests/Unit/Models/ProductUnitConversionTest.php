<?php

use App\Models\ProductUnitConversion;
use App\Models\ProductVariant;

it('converts a packaged quantity to base units', function () {
    $variant = ProductVariant::factory()->create();
    $box = ProductUnitConversion::factory()->for($variant, 'variant')->create([
        'unit_name' => 'Box',
        'base_unit_ratio' => 24,
    ]);

    expect($box->toBaseUnits(3))->toBe(72);
});

it('rejects a duplicate unit_name for the same variant', function () {
    $variant = ProductVariant::factory()->create();
    ProductUnitConversion::factory()->for($variant, 'variant')->create(['unit_name' => 'Box']);

    expect(fn () => ProductUnitConversion::factory()->for($variant, 'variant')->create(['unit_name' => 'Box']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('allows the same unit_name across different variants', function () {
    $variantA = ProductVariant::factory()->create();
    $variantB = ProductVariant::factory()->create();

    ProductUnitConversion::factory()->for($variantA, 'variant')->create(['unit_name' => 'Box']);
    $second = ProductUnitConversion::factory()->for($variantB, 'variant')->create(['unit_name' => 'Box']);

    expect($second->exists)->toBeTrue();
});
