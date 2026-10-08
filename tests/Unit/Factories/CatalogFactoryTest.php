<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\ProductVariantUnitConversion;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Catalog factory tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §5.1 ProductFactory; §5.2 ProductVariantFactory;
 *     §5.3 ProductVariantPriceFactory;
 *     §5.4 ProductVariantUnitConversionFactory (+ baseUnit() state);
 *     §5.5 WarehouseFactory.
 *   - §2.2 SKU pattern `SKU-####-??`; EAN-13 barcode; `WH-####` code.
 *   - §3.19 ProductVariantObserver materializes the base-unit row.
 *   - §7A ProductResource — the sole consumer of these factories in
 *     production code paths (tests use them everywhere).
 */
uses(RefreshDatabase::class);

// ===========================================================================
// ProductFactory (§5.1)
// ===========================================================================

describe('ProductFactory', function () {
    it('produces a name and a category from the §5.1 fixed set', function () {
        $product = Product::factory()->create();

        expect($product->name)->toBeString()->not->toBe('');
        expect($product->category)->toBeIn(['Electronics', 'Hardware', 'Consumables']);
    });

    it('does not create variants', function () {
        // The factory seeds a family container only; variants are
        // created separately.
        $product = Product::factory()->create();
        expect($product->variants()->count())->toBe(0);
    });
});

// ===========================================================================
// ProductVariantFactory (§5.2)
// ===========================================================================

describe('ProductVariantFactory', function () {
    it('produces a sku matching the SKU-####-?? pattern', function () {
        $variant = ProductVariant::factory()->create();
        expect($variant->sku)->toMatch('/^SKU-\d{4}-[A-Z]{2}$/');
    });

    it('produces a unique sku across a batch', function () {
        $variants = ProductVariant::factory()->count(20)->create();
        expect($variants->pluck('sku')->unique())->toHaveCount(20);
    });

    it('produces an EAN-13 barcode', function () {
        $variant = ProductVariant::factory()->create();
        // EAN-13: 13 digits. The faker `ean13()` returns a numeric string.
        expect($variant->barcode)->toMatch('/^\d{13}$/');
    });

    it('produces a unique barcode across a batch', function () {
        $variants = ProductVariant::factory()->count(20)->create();
        expect($variants->pluck('barcode')->unique())->toHaveCount(20);
    });

    it('defaults base_unit_name to pc', function () {
        expect(ProductVariant::factory()->create()->base_unit_name)->toBe('pc');
    });

    it('defaults reorder_point to a value in [0, 50]', function () {
        $variant = ProductVariant::factory()->create();
        expect($variant->reorder_point)->toBeGreaterThanOrEqual(0);
        expect($variant->reorder_point)->toBeLessThanOrEqual(50);
    });

    it('defaults is_active to true', function () {
        expect(ProductVariant::factory()->create()->is_active)->toBeTrue();
    });

    it('auto-creates the parent Product', function () {
        // §5.2: `'product_id' => Product::factory()` — the FK is
        // auto-resolved.
        $variant = ProductVariant::factory()->create();
        expect($variant->product_id)->not->toBeNull();
        expect($variant->product)->toBeInstanceOf(Product::class);
    });

    it('triggers the ProductVariantObserver to materialize the base-unit row', function () {
        // §3.19 / F19: a variant created via the factory must have its
        // base-unit self-conversion row.
        $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

        expect(
            ProductVariantUnitConversion::where('product_variant_id', $variant->id)
                ->where('unit_name', 'pc')
                ->where('base_unit_ratio', 1)
                ->exists()
        )->toBeTrue();
    });
});

// ===========================================================================
// ProductVariantPriceFactory (§5.3)
// ===========================================================================

describe('ProductVariantPriceFactory', function () {
    it('produces a cost in [1, 500] at 4dp', function () {
        $price = ProductVariantPrice::factory()->create();
        $cost = (float) $price->cost_price;

        expect($cost)->toBeGreaterThanOrEqual(1.0);
        expect($cost)->toBeLessThanOrEqual(500.0);
    });

    it('produces a sale_price at 40% markup over cost', function () {
        // §5.3 / §2.3: sale = cost × 1.4, stored as decimal(15,4). The
        // expected value is rounded to 4dp to match the stored precision;
        // comparing against the unrounded product would fail whenever the
        // float product carries more than four decimals.
        $price = ProductVariantPrice::factory()->create();
        expect((float) $price->sale_price)->toBe(round((float) $price->cost_price * 1.4, 4));
    });

    it('defaults is_current to true', function () {
        expect(ProductVariantPrice::factory()->create()->is_current)->toBeTrue();
    });

    it('auto-creates the parent ProductVariant', function () {
        $price = ProductVariantPrice::factory()->create();
        expect($price->product_variant_id)->not->toBeNull();
        expect($price->productVariant)->toBeInstanceOf(ProductVariant::class);
    });
});

// ===========================================================================
// ProductVariantUnitConversionFactory (§5.4)
// ===========================================================================

describe('ProductVariantUnitConversionFactory', function () {
    it('produces a non-base unit from the §5.4 set', function () {
        $conversion = ProductVariantUnitConversion::factory()->create();

        expect($conversion->unit_name)->toBeIn(['box', 'case', 'pallet']);
    });

    it('produces a ratio from the §5.4 set', function () {
        $conversion = ProductVariantUnitConversion::factory()->create();
        expect($conversion->base_unit_ratio)->toBeIn([6, 12, 24, 48]);
    });

    it('defaults both default flags to false', function () {
        $conversion = ProductVariantUnitConversion::factory()->create();
        expect($conversion->is_default_purchase)->toBeFalse();
        expect($conversion->is_default_transfer)->toBeFalse();
    });

    it('produces the base-unit self-conversion row via baseUnit()', function () {
        // §5.4: `->baseUnit()` state produces unit_name = 'pc', ratio 1.
        // Variant created quietly so ProductVariantObserver (§3.19) does not
        // materialize its own pc row first and collide on the
        // (product_variant_id, unit_name) unique index (§2.4).
        $variant = ProductVariant::factory()->createQuietly();
        $conversion = ProductVariantUnitConversion::factory()->baseUnit()->create([
            'product_variant_id' => $variant->id,
        ]);

        expect($conversion->unit_name)->toBe('pc');
        expect($conversion->base_unit_ratio)->toBe(1);
    });

    it('auto-creates the parent ProductVariant', function () {
        $conversion = ProductVariantUnitConversion::factory()->create();
        expect($conversion->productVariant)->toBeInstanceOf(ProductVariant::class);
    });
});

// ===========================================================================
// WarehouseFactory (§5.5)
// ===========================================================================

describe('WarehouseFactory', function () {
    it('produces a code matching the WH-#### pattern', function () {
        expect(Warehouse::factory()->create()->code)->toMatch('/^WH-\d{4}$/');
    });

    it('produces a unique code across a batch', function () {
        $warehouses = Warehouse::factory()->count(20)->create();
        expect($warehouses->pluck('code')->unique())->toHaveCount(20);
    });

    it('produces a name ending in "Warehouse"', function () {
        expect(Warehouse::factory()->create()->name)->toEndWith(' Warehouse');
    });

    it('defaults is_active to true', function () {
        expect(Warehouse::factory()->create()->is_active)->toBeTrue();
    });

    it('produces a non-empty location', function () {
        expect(Warehouse::factory()->create()->location)->toBeString()->not->toBe('');
    });
});
