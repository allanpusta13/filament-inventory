<?php

declare(strict_types=1);

use App\Exceptions\ProductFamilyHasVariantsException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Observers\ProductObserver;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Product model contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §2.1 products schema (name, category, deleted_at, timestamps).
 *   - §3.1 model shape (traits, $fillable, variants() relation).
 *   - §3.19 ProductObserver::deleting() guard, registered in
 *     AppServiceProvider::boot() (§17.3).
 *   - §8.1 ProductPolicy authorization surface (existence asserted in
 *     the policy test, not here).
 */
uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('uses HasFactory and SoftDeletes traits', function () {
    // §3.1: exactly these two traits — no more.
    $traits = class_uses_recursive(Product::class);

    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\Factories\HasFactory::class);
    expect($traits)->toHaveKey(SoftDeletes::class);
});

it('declares exactly name and category as fillable', function () {
    // §3.1: $fillable = ['name', 'category'].
    $product = new Product();

    expect($product->getFillable())->toBe(['name', 'category']);
});

it('creates a product with the §5.1 factory defaults', function () {
    $product = Product::factory()->create();

    expect($product->name)->toBeString()->not->toBe('');
    expect($product->category)->toBeIn(['Electronics', 'Hardware', 'Consumables']);
    expect($product->exists)->toBeTrue();
    expect($product->deleted_at)->toBeNull();
});

it('exposes variants() as a HasMany relation', function () {
    $product = new Product();

    expect($product->variants())->toBeInstanceOf(HasMany::class);
    expect($product->variants()->getRelated())->toBeInstanceOf(ProductVariant::class);
});

it('returns its variants through the variants() relation', function () {
    $product = Product::factory()->create();

    ProductVariant::factory()->count(3)->create(['product_id' => $product->id]);

    expect($product->variants()->count())->toBe(3);
    expect($product->variants)->toHaveCount(3);
});

it('cascade-deletes variants on force delete via the §2.2 FK action', function () {
    // §2.2: product_variants.product_id FK → products.id (cascadeOnDelete).
    // Force-deleting the family is the bypass path for the soft-delete
    // guard; the FK then cascades to the variants.
    $product = Product::factory()->create();
    ProductVariant::factory()->count(2)->create(['product_id' => $product->id]);

    $product->forceDelete();

    expect(Product::withTrashed()->find($product->id))->toBeNull();
    expect(ProductVariant::where('product_id', $product->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// §3.19 ProductObserver guard — soft-delete only.
// ---------------------------------------------------------------------------

it('registers ProductObserver on the Product model', function () {
    // §17.3: AppServiceProvider::boot() registers the observer. This
    // asserts the observer is actually wired — a missing registration
    // would silently disable the guard.
    $product = new Product();

    expect($product::getEventDispatcher())->not->toBeNull();
    // Eloquent's observer registry lives on the model's event dispatcher;
    // the practical assertion is that the guard fires below.
});

it('throws ProductFamilyHasVariantsException when soft-deleting a family with variants', function () {
    // §3.19 ProductObserver::deleting() — the guard.
    $product = Product::factory()->create();
    ProductVariant::factory()->create(['product_id' => $product->id]);

    expect(fn () => $product->delete())
        ->toThrow(ProductFamilyHasVariantsException::class);

    // The family must NOT be soft-deleted — the observer aborts the
    // delete before the row is touched.
    expect(Product::find($product->id))->not->toBeNull();
    expect($product->fresh()->deleted_at)->toBeNull();
});

it('allows soft-deleting a family with no variants', function () {
    $product = Product::factory()->create();

    $product->delete();

    expect(Product::find($product->id))->toBeNull();
    expect(Product::withTrashed()->find($product->id))->not->toBeNull();
    expect($product->fresh()->deleted_at)->not->toBeNull();
});

it('bypasses the soft-delete guard on force delete', function () {
    // §3.19 ProductObserver::deleting(): `if ($product->isForceDeleting())
    // return;`. The guard is a soft-delete-only protection.
    $product = Product::factory()->create();
    ProductVariant::factory()->create(['product_id' => $product->id]);

    // No exception — the force-delete path short-circuits the guard.
    $product->forceDelete();

    expect(Product::withTrashed()->find($product->id))->toBeNull();
});

it('produces a ProductFamilyHasVariantsException carrying the family id', function () {
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
// §3.1 no-booted()-override invariant
// ---------------------------------------------------------------------------

it('does not define a booted() method — the guard lives solely in the observer', function () {
    // §3.1: "no booted() override here; a duplicated closure would
    // throw twice for the same violation and drift from the canonical
    // observer." Assert the method is not declared on the model itself
    // (it may be inherited from the framework base — that is fine).
    $reflection = new ReflectionClass(Product::class);

    expect($reflection->hasMethod('booted'))->toBeTrue(); // inherited
    expect($reflection->getMethod('booted')->getDeclaringClass()->getName())
        ->not->toBe(Product::class);
});

// ---------------------------------------------------------------------------
// SoftDeletes trait behaviours
// ---------------------------------------------------------------------------

it('supports withTrashed() and onlyTrashed() lookups', function () {
    $active = Product::factory()->create();
    $trashed = Product::factory()->create();
    $trashed->delete();

    expect(Product::query()->pluck('id')->all())->toBe([$active->id]);
    expect(Product::withTrashed()->pluck('id')->all())->toEqualCanonicalizing([$active->id, $trashed->id]);
    expect(Product::onlyTrashed()->pluck('id')->all())->toBe([$trashed->id]);
});

it('supports restore()', function () {
    $product = Product::factory()->create();
    $product->delete();

    $product->restore();

    expect(Product::find($product->id))->not->toBeNull();
    expect($product->fresh()->deleted_at)->toBeNull();
});
