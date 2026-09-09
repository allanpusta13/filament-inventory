<?php

declare(strict_types=1);

use App\Models\ProductVariant;
use App\Models\ProductVariantUnitConversion;

it('converts a packaged quantity to base units', function () {
    $variant = ProductVariant::factory()->create();
    $box = ProductVariantUnitConversion::factory()->for($variant, 'variant')->create([
        'unit_name' => 'Box',
        'base_unit_ratio' => 24,
    ]);

    expect($box->toBaseUnits(3))->toBe(72);
});

it('rejects a duplicate unit_name for the same variant', function () {
    $variant = ProductVariant::factory()->create();
    ProductVariantUnitConversion::factory()->for($variant, 'variant')->create(['unit_name' => 'Box']);

    expect(fn () => ProductVariantUnitConversion::factory()->for($variant, 'variant')->create(['unit_name' => 'Box']))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('allows the same unit_name across different variants', function () {
    $variantA = ProductVariant::factory()->create();
    $variantB = ProductVariant::factory()->create();

    ProductVariantUnitConversion::factory()->for($variantA, 'variant')->create(['unit_name' => 'Box']);
    $second = ProductVariantUnitConversion::factory()->for($variantB, 'variant')->create(['unit_name' => 'Box']);

    expect($second->exists)->toBeTrue();
});

it('casts is_default_purchase and is_default_transfer to boolean', function () {
    $conversion = ProductVariantUnitConversion::factory()->create([
        'is_default_purchase' => true,
        'is_default_transfer' => false,
    ]);

    expect($conversion->fresh()->is_default_purchase)->toBeTrue()
        ->and($conversion->fresh()->is_default_transfer)->toBeFalse();
});

it('defaults is_default_purchase and is_default_transfer to false', function () {
    $conversion = ProductVariantUnitConversion::factory()->create();

    expect($conversion->is_default_purchase)->toBeFalse()
        ->and($conversion->is_default_transfer)->toBeFalse();
});

it('belongs to a variant', function () {
    $variant = ProductVariant::factory()->create();
    $conversion = ProductVariantUnitConversion::factory()->for($variant, 'variant')->create();

    expect($conversion->variant->is($variant))->toBeTrue();
});
