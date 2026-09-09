<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductVariant;

it('has many variants', function () {
    $product = Product::factory()->create();
    $variants = ProductVariant::factory()->count(3)->for($product)->create();

    expect($product->variants)->toHaveCount(3)
        ->and($product->variants->pluck('id')->sort()->values())
        ->toEqual($variants->pluck('id')->sort()->values());
});

it('soft deletes without removing the row', function () {
    $product = Product::factory()->create();

    $product->delete();

    $this->assertSoftDeleted($product);
    expect(Product::withTrashed()->find($product->id))->not->toBeNull();
});

it('is excluded from default queries once soft deleted', function () {
    $product = Product::factory()->create();
    $product->delete();

    expect(Product::find($product->id))->toBeNull();
});
