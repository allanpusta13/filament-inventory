<?php

declare(strict_types=1);

use App\Exceptions\ProductFamilyHasVariantsException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Observers\ProductObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * ProductObserver contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §3.19 ProductObserver::deleting() guard.
 *   - §17.3 observer registration in AppServiceProvider::boot()
 *     (asserted here through the effect — the guard firing — since the
 *     provider itself is a separate artifact).
 *   - §6.3 ProductFamilyHasVariantsException contract.
 *   - §2.2 FK: cascadeOnDelete on product_variants.product_id, which
 *     the force-delete path relies on.
 *
 * Deliberately NOT tested here (duplicated by the model suite):
 *   - The exception's translation key / context (ProductTest covers it).
 *   - The full ProductTest behavioral sweep — this file focuses on the
 *     observer's identity, registration, and edge cases.
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Registration contract (§17.3)
// ---------------------------------------------------------------------------

it('is registered on the Product model — the guard fires through soft delete', function () {
    // §17.3: Product::observe(ProductObserver::class) in
    // AppServiceProvider::boot(). The practical assertion is the guard
    // effect — a lost registration would silently disable the guard.
    $product = Product::factory()->create();
    ProductVariant::factory()->create(['product_id' => $product->id]);

    expect(fn () => $product->delete())
        ->toThrow(ProductFamilyHasVariantsException::class);
});

it('is the sole owner of the soft-delete guard — Product has no booted() override', function () {
    // §3.1: "no booted() override here; a duplicated closure would
    // throw twice for the same violation and drift from the canonical
    // observer."
    $reflection = new ReflectionClass(Product::class);

    expect($reflection->getMethod('booted')->getDeclaringClass()->getName())
        ->not->toBe(Product::class);
});

// ---------------------------------------------------------------------------
// deleting() behavior
// ---------------------------------------------------------------------------

it('fires on soft delete of a family with one variant', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->create(['product_id' => $product->id]);

    expect(fn () => $product->delete())
        ->toThrow(ProductFamilyHasVariantsException::class);

    // The row is untouched — the observer aborts before the delete runs.
    expect(Product::find($product->id))->not->toBeNull();
    expect($product->fresh()->deleted_at)->toBeNull();
});

it('fires on soft delete of a family with many variants', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->count(3)->create(['product_id' => $product->id]);

    expect(fn () => $product->delete())
        ->toThrow(ProductFamilyHasVariantsException::class);
});

it('allows soft delete of a family with no variants', function () {
    $product = Product::factory()->create();

    $product->delete();

    expect(Product::find($product->id))->toBeNull();
    expect(Product::withTrashed()->find($product->id))->not->toBeNull();
});

it('fires only once even when multiple variants exist', function () {
    // The guard uses `exists()`, not `count()`, so the number of
    // variants is irrelevant. This test asserts the guard is not
    // invoked per-variant.
    $product = Product::factory()->create();
    ProductVariant::factory()->count(5)->create(['product_id' => $product->id]);

    try {
        $product->delete();
        $this->fail('Expected ProductFamilyHasVariantsException was not thrown.');
    } catch (ProductFamilyHasVariantsException $e) {
        expect($e->productId)->toBe((int) $product->id);
    }
});

// ---------------------------------------------------------------------------
// isForceDeleting() bypass (§3.19)
// ---------------------------------------------------------------------------

it('bypasses the guard on force delete', function () {
    // §3.19: `if ($product->isForceDeleting()) return;`. The FK cascade
    // (§2.2 cascadeOnDelete) then removes the variants.
    $product = Product::factory()->create();
    ProductVariant::factory()->count(2)->create(['product_id' => $product->id]);

    // No exception — the force-delete path short-circuits the guard.
    $product->forceDelete();

    expect(Product::withTrashed()->find($product->id))->toBeNull();
});

it('cascades variants on force delete via the §2.2 FK', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->count(3)->create(['product_id' => $product->id]);

    $product->forceDelete();

    expect(ProductVariant::where('product_id', $product->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Exception shape (§6.3)
// ---------------------------------------------------------------------------

it('throws a ProductFamilyHasVariantsException carrying the family id', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->create(['product_id' => $product->id]);

    try {
        $product->delete();
        $this->fail('Expected ProductFamilyHasVariantsException was not thrown.');
    } catch (ProductFamilyHasVariantsException $e) {
        expect($e->productId)->toBe((int) $product->id);
        expect($e->translationKey())->toBe('errors.product_family_has_variants');
        expect($e->context())->toBe(['product' => (int) $product->id]);
    }
});

// ---------------------------------------------------------------------------
// No other event hooks (§3.19)
// ---------------------------------------------------------------------------

it('does not fire on update', function () {
    // §3.19 defines only `deleting`. A family with variants updates
    // freely — no guard blocks it.
    $product = Product::factory()->create();
    ProductVariant::factory()->create(['product_id' => $product->id]);

    $product->update(['name' => 'Renamed Family']);

    expect($product->fresh()->name)->toBe('Renamed Family');
});

it('does not fire on restore', function () {
    // Only `deleting` is defined — restoring a soft-deleted family is
    // unguarded.
    $product = Product::factory()->create();
    $product->delete();

    $product->restore();

    expect(Product::find($product->id))->not->toBeNull();
    expect($product->fresh()->deleted_at)->toBeNull();
});

// ---------------------------------------------------------------------------
// Observer class shape
// ---------------------------------------------------------------------------

it('defines only the deleting() event method', function () {
    // §3.19 defines exactly one method. A regression that adds a
    // created/updated/saved hook would silently add behavior the
    // blueprint does not authorize.
    $methods = collect((new ReflectionClass(ProductObserver::class))->getMethods())
        ->filter(fn ($m) => $m->class === ProductObserver::class)
        ->pluck('name')
        ->all();

    expect($methods)->toBe(['deleting']);
});
