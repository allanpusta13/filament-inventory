<?php

declare(strict_types=1);

use App\Models\ProductVariant;
use App\Models\ProductVariantUnitConversion;
use App\Observers\ProductVariantObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * ProductVariantObserver contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §3.19 ProductVariantObserver::created() base-unit self-conversion
 *     row materialization (F19).
 *   - §17.3 observer registration in AppServiceProvider::boot()
 *     (asserted here through the effect — the base row appearing).
 *   - §2.4 unique (product_variant_id, unit_name) — firstOrCreate never
 *     hits a duplicate.
 *   - §5.4 ProductVariantUnitConversionFactory::baseUnit() pre-seed
 *     path, exercised against the observer's firstOrCreate semantics.
 *
 * Deliberately NOT tested here (duplicated by the model suite):
 *   - isBaseUnitRow() classification (ProductVariantUnitConversionTest).
 *   - The full ProductVariantTest method sweep.
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Registration contract (§17.3)
// ---------------------------------------------------------------------------

it('is registered on the ProductVariant model — the base row appears on create', function () {
    // §17.3: ProductVariant::observe(ProductVariantObserver::class) in
    // AppServiceProvider::boot(). The practical assertion is the effect
    // — a lost registration would leave every future variant with no
    // selectable units.
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    expect(
        ProductVariantUnitConversion::where('product_variant_id', $variant->id)
            ->where('unit_name', 'pc')
            ->exists()
    )->toBeTrue();
});

it('is the sole owner of the base-unit row — ProductVariant has no booted() override', function () {
    // §3.2: no `booted()` closure for base-unit row creation. The
    // observer is the canonical materializer.
    $reflection = new ReflectionClass(ProductVariant::class);

    expect($reflection->getMethod('booted')->getDeclaringClass()->getName())
        ->not->toBe(ProductVariant::class);
});

// ---------------------------------------------------------------------------
// created() behavior — row materialization
// ---------------------------------------------------------------------------

it('materializes exactly one base-unit self-conversion row per variant', function () {
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    $rows = ProductVariantUnitConversion::where('product_variant_id', $variant->id)
        ->where('unit_name', 'pc')
        ->get();

    expect($rows)->toHaveCount(1);
});

it('sets base_unit_ratio to 1 on the materialized row', function () {
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    $row = ProductVariantUnitConversion::where('product_variant_id', $variant->id)
        ->where('unit_name', 'pc')
        ->first();

    expect($row->base_unit_ratio)->toBe(1);
});

it('sets both default flags to false on the materialized row', function () {
    // §3.19: is_default_purchase = false, is_default_transfer = false.
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    $row = ProductVariantUnitConversion::where('product_variant_id', $variant->id)
        ->where('unit_name', 'pc')
        ->first();

    expect($row->is_default_purchase)->toBeFalse();
    expect($row->is_default_transfer)->toBeFalse();
});

it('uses the variant\'s own base_unit_name, not a hard-coded unit', function () {
    // A variant with base_unit_name = 'kg' gets a base row keyed on
    // 'kg', not on 'pc'. The observer reads $variant->base_unit_name.
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'kg']);

    expect(
        ProductVariantUnitConversion::where('product_variant_id', $variant->id)
            ->where('unit_name', 'kg')
            ->exists()
    )->toBeTrue();

    expect(
        ProductVariantUnitConversion::where('product_variant_id', $variant->id)
            ->where('unit_name', 'pc')
            ->exists()
    )->toBeFalse();
});

it('creates a distinct base row per variant across multiple variants', function () {
    $a = ProductVariant::factory()->create(['base_unit_name' => 'pc']);
    $b = ProductVariant::factory()->create(['base_unit_name' => 'kg']);
    $c = ProductVariant::factory()->create(['base_unit_name' => 'liter']);

    foreach ([$a, $b, $c] as $variant) {
        expect(
            ProductVariantUnitConversion::where('product_variant_id', $variant->id)
                ->where('unit_name', $variant->base_unit_name)
                ->where('base_unit_ratio', 1)
                ->count()
        )->toBe(1);
    }
});

// ---------------------------------------------------------------------------
// firstOrCreate semantics — idempotency
// ---------------------------------------------------------------------------

it('does not duplicate the base-unit row when the observer runs a second time', function () {
    // §3.19 uses firstOrCreate, so re-invoking created() on the same
    // variant is a no-op. This test exercises the observer directly,
    // simulating a double-fire (which Eloquent does not normally do).
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    app(ProductVariantObserver::class)->created($variant);

    expect(
        ProductVariantUnitConversion::where('product_variant_id', $variant->id)
            ->where('unit_name', 'pc')
            ->count()
    )->toBe(1);
});

it('does not overwrite an existing base-unit row on a second fire', function () {
    // firstOrCreate preserves the existing row — including a manually
    // modified ratio — when the lookup columns already match.
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    // Mutate the existing base row's default flag out of the default.
    $base = ProductVariantUnitConversion::where('product_variant_id', $variant->id)
        ->where('unit_name', 'pc')
        ->first();

    $base->update(['is_default_purchase' => true]);

    // Simulate a second fire of the observer's created() hook.
    app(ProductVariantObserver::class)->created($variant);

    $base->refresh();

    expect($base->is_default_purchase)->toBeTrue(); // preserved
    expect(
        ProductVariantUnitConversion::where('product_variant_id', $variant->id)
            ->where('unit_name', 'pc')
            ->count()
    )->toBe(1);
});

// ---------------------------------------------------------------------------
// No other event hooks (§3.19)
// ---------------------------------------------------------------------------

it('does not fire on update', function () {
    // §3.19 defines only `created`. Updating base_unit_name does not
    // materialize a new row — the schema intentionally requires the
    // base row to reflect the variant's *original* base unit.
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    $variant->update(['base_unit_name' => 'kg']);

    // The base row from creation still exists — no new row was added.
    expect(
        ProductVariantUnitConversion::where('product_variant_id', $variant->id)
            ->count()
    )->toBe(1);

    expect(
        ProductVariantUnitConversion::where('product_variant_id', $variant->id)
            ->where('unit_name', 'pc')
            ->exists()
    )->toBeTrue();
});

it('does not fire on soft delete', function () {
    // §3.19 defines only `created`. Soft-deleting a variant does not
    // touch its unit conversions — the row remains for restore.
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    $variant->delete();

    expect(
        ProductVariantUnitConversion::where('product_variant_id', $variant->id)
            ->where('unit_name', 'pc')
            ->exists()
    )->toBeTrue();
});

it('does not fire on force delete — cascade removes the base row via §2.4 FK', function () {
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    $variant->forceDelete();

    // The FK cascade (§2.4 cascadeOnDelete) removes the conversion row.
    expect(
        ProductVariantUnitConversion::where('product_variant_id', $variant->id)
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------------------------
// Observer class shape
// ---------------------------------------------------------------------------

it('defines only the created() event method', function () {
    // §3.19 defines exactly one method. A regression that adds an
    // updated/deleted hook would silently add behavior the blueprint
    // does not authorize.
    $methods = collect((new ReflectionClass(ProductVariantObserver::class))->getMethods())
        ->filter(fn ($m) => $m->class === ProductVariantObserver::class)
        ->pluck('name')
        ->all();

    expect($methods)->toBe(['created']);
});
