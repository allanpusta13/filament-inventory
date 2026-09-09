<?php

use App\Models\ProductVariantPrice;
use App\Models\ProductVariant;

it('defaults cost_price and sale_price to 0.0000 at the DB level', function () {
    $variant = ProductVariant::factory()->create();

    // Bypass the model's fillable/casts by inserting directly, to prove the
    // default lives on the column itself, not just in application code.
    $id = \Illuminate\Support\Facades\DB::table('product_variant_prices')->insertGetId([
        'product_variant_id' => $variant->id,
        'effective_from' => now(),
        'is_current' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $row = \Illuminate\Support\Facades\DB::table('product_variant_prices')->find($id);

    expect((float) $row->cost_price)->toBe(0.0)
        ->and((float) $row->sale_price)->toBe(0.0);
});

it('casts cost_price and sale_price as decimal strings', function () {
    $price = ProductVariantPrice::factory()->create(['cost_price' => 12.3456, 'sale_price' => 99.99]);

    expect($price->fresh()->cost_price)->toBe('12.3456')
        ->and($price->fresh()->sale_price)->toBe('99.9900');
});

it('rejects a second is_current row for the same variant at the DB level', function () {
    $variant = ProductVariant::factory()->create();
    ProductVariantPrice::factory()->for($variant, 'variant')->create(['is_current' => true]);

    expect(fn () => ProductVariantPrice::factory()->for($variant, 'variant')->create(['is_current' => true]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('allows multiple non-current rows for the same variant', function () {
    $variant = ProductVariant::factory()->create();

    ProductVariantPrice::factory()->notCurrent()->for($variant, 'variant')->create();
    ProductVariantPrice::factory()->notCurrent()->for($variant, 'variant')->create();
    $current = ProductVariantPrice::factory()->for($variant, 'variant')->create();

    expect($variant->prices()->count())->toBe(3)
        ->and($variant->prices()->where('is_current', true)->count())->toBe(1)
        ->and($variant->currentPrice()->first()->id)->toBe($current->id);
});

it('flips the previous current row to false when recording a new price', function () {
    $variant = ProductVariant::factory()->create();
    $old = ProductVariantPrice::factory()->for($variant, 'variant')->create(['sale_price' => 100]);

    $new = ProductVariantPrice::recordNewPrice($variant, costPrice: 50, salePrice: 120);

    expect($old->fresh()->is_current)->toBeFalse()
        ->and($new->is_current)->toBeTrue()
        ->and($variant->currentPrice()->first()->id)->toBe($new->id);
});

it('allows a variant to have zero price rows', function () {
    $variant = ProductVariant::factory()->create();

    expect($variant->prices)->toHaveCount(0)
        ->and($variant->currentPrice()->first())->toBeNull();
});
