<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\ProductVariantUnitConversion;

it('belongs to a product', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();

    expect($variant->product->is($product))->toBeTrue();
});

it('casts attributes and images to arrays', function () {
    $variant = ProductVariant::factory()->create([
        'attributes' => ['roast' => 'Medium'],
        'images' => ['a.jpg', 'b.jpg'],
    ]);

    $fresh = $variant->fresh();

    expect($fresh->attributes)->toBeArray()->and($fresh->attributes)->toBe(['roast' => 'Medium'])
        ->and($fresh->images)->toBe(['a.jpg', 'b.jpg']);
});

it('reports below reorder point correctly', function () {
    $variant = ProductVariant::factory()->create(['reorder_point' => 10]);

    expect($variant->isBelowReorderPoint(10))->toBeTrue()
        ->and($variant->isBelowReorderPoint(11))->toBeFalse()
        ->and($variant->isBelowReorderPoint(0))->toBeTrue();
});

it('resolves currentPrice to only the is_current row', function () {
    $variant = ProductVariant::factory()->create();

    ProductVariantPrice::factory()->notCurrent()->for($variant, 'variant')->create(['sale_price' => 100]);
    $current = ProductVariantPrice::factory()->for($variant, 'variant')->create(['sale_price' => 150]);

    expect($variant->currentPrice()->first()->id)->toBe($current->id);
});

it('enforces unique sku', function () {
    $variant = ProductVariant::factory()->create(['sku' => 'DUPE-SKU']);

    expect(fn () => ProductVariant::factory()->create(['sku' => 'DUPE-SKU']))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('has many unit conversions', function () {
    $variant = ProductVariant::factory()->create();
    ProductVariantUnitConversion::factory()->for($variant, 'variant')->box()->create();
    ProductVariantUnitConversion::factory()->for($variant, 'variant')->case()->create();

    expect($variant->unitConversions)->toHaveCount(2);
});

it('has many prices', function () {
    $variant = ProductVariant::factory()->create();
    ProductVariantPrice::factory()->for($variant, 'variant')->create();
    ProductVariantPrice::factory()->notCurrent()->for($variant, 'variant')->create();
    ProductVariantPrice::factory()->notCurrent()->for($variant, 'variant')->create();

    expect($variant->prices)->toHaveCount(3);
});

it('casts images to array', function () {
    $variant = ProductVariant::factory()->create(['images' => ['img1.jpg', 'img2.png']]);

    expect($variant->fresh()->images)->toBeArray()
        ->and($variant->fresh()->images)->toBe(['img1.jpg', 'img2.png']);
});
