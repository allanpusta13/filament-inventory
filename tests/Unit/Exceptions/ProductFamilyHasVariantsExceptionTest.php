<?php

declare(strict_types=1);

use App\Exceptions\DomainErrorException;
use App\Exceptions\ProductFamilyHasVariantsException;

/**
 * ProductFamilyHasVariantsException contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §6.3: key catalogue row
 *     errors.product_family_has_variants → { product }.
 *   - §0A.2a: lang/en/errors.php entry
 *     errors.product_family_has_variants.title / .body.
 *   - §3.19 ProductObserver::deleting() call site.
 */
it('extends DomainErrorException', function () {
    expect(is_subclass_of(ProductFamilyHasVariantsException::class, DomainErrorException::class))->toBeTrue();
});

it('carries the errors.product_family_has_variants translation key', function () {
    $exception = new ProductFamilyHasVariantsException(42);

    expect($exception->translationKey())->toBe('errors.product_family_has_variants');
});

it('exposes productId as a readonly promoted property', function () {
    $exception = new ProductFamilyHasVariantsException(42);

    expect($exception->productId)->toBe(42);
});

it('populates context with exactly the §6.3 key-catalogue placeholder', function () {
    // §6.3: errors.product_family_has_variants → product.
    $exception = new ProductFamilyHasVariantsException(42);

    expect($exception->context())->toBe(['product' => 42]);
    expect(array_keys($exception->context()))->toBe(['product']);
});

it('supports positional construction matching §3.19 call site', function () {
    // §3.19: new ProductFamilyHasVariantsException((int) $product->id);
    $exception = new ProductFamilyHasVariantsException(42);

    expect($exception->productId)->toBe(42);
    expect($exception->context()['product'])->toBe(42);
});

it('renders the §0A.2a body template with the placeholder', function () {
    // §0A.2a: 'Product :product still has variants and cannot be deleted.'
    $exception = new ProductFamilyHasVariantsException(42);

    $body = __('errors.product_family_has_variants.body', $exception->context());

    expect($body)->toContain('42');
    expect($body)->not->toContain(':product');
});

it('resolves the .title translation key', function () {
    $title = __('errors.product_family_has_variants.title');

    expect($title)->toBeString()->not->toBe('');
    expect($title)->not->toBe('errors.product_family_has_variants.title');
});

it('stores the translation key as the exception message', function () {
    $exception = new ProductFamilyHasVariantsException(42);

    expect($exception->getMessage())->toBe('errors.product_family_has_variants');
});

it('is caught by a catch(\\DomainErrorException) block', function () {
    $caught = false;

    try {
        throw new ProductFamilyHasVariantsException(42);
    } catch (DomainErrorException $e) {
        $caught = true;
    }

    expect($caught)->toBeTrue();
});
