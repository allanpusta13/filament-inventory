<?php

declare(strict_types=1);

use App\Exceptions\DomainErrorException;
use App\Exceptions\InsufficientStockException;

/**
 * InsufficientStockException contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §0A.10: canonical example of a typed domain exception.
 *   - §6.3: key catalogue row
 *     errors.insufficient_stock → { variant, requested, available }.
 *   - §0A.2a: lang/en/errors.php entry
 *     errors.insufficient_stock.title / .body.
 *   - §6.2 call sites: InventoryService::directTransfer,
 *     dispatchTransfer; SalesService::dispatchSale (§6.5).
 */
it('extends DomainErrorException', function () {
    expect(is_subclass_of(InsufficientStockException::class, DomainErrorException::class))->toBeTrue();
});

it('carries the errors.insufficient_stock translation key', function () {
    $exception = new InsufficientStockException(
        variantId: 7,
        warehouseId: 3,
        requested: 100,
        available: 40,
    );

    expect($exception->translationKey())->toBe('errors.insufficient_stock');
});

it('exposes variant, requested, and available as readonly promoted properties', function () {
    $exception = new InsufficientStockException(
        variantId: 7,
        warehouseId: 3,
        requested: 100,
        available: 40,
    );

    expect($exception->variantId)->toBe(7);
    expect($exception->warehouseId)->toBe(3);
    expect($exception->requested)->toBe(100);
    expect($exception->available)->toBe(40);
});

it('populates context with exactly the §6.3 key-catalogue placeholders', function () {
    // §6.3: errors.insufficient_stock → variant, requested, available.
    $exception = new InsufficientStockException(
        variantId: 7,
        warehouseId: 3,
        requested: 100,
        available: 40,
    );

    expect($exception->context())->toBe([
        'variant' => 7,
        'requested' => 100,
        'available' => 40,
    ]);
    expect(array_keys($exception->context()))->toBe(['variant', 'requested', 'available']);
});

it('does not leak warehouseId into the user-facing context', function () {
    // §6.3 key catalogue excludes warehouse from the translation
    // placeholders — it is diagnostic-only and must not surface in the
    // rendered body (the §0A.2a template only references :variant,
    // :requested, :available).
    $exception = new InsufficientStockException(
        variantId: 7,
        warehouseId: 3,
        requested: 100,
        available: 40,
    );

    expect($exception->context())->not->toHaveKey('warehouse');
    expect($exception->context())->not->toHaveKey('warehouse_id');
});

it('renders the §0A.2a body template with all three placeholders', function () {
    // §0A.2a: 'Requested :requested, available :available for variant :variant.'
    $exception = new InsufficientStockException(
        variantId: 7,
        warehouseId: 3,
        requested: 100,
        available: 40,
    );

    $body = __('errors.insufficient_stock.body', $exception->context());

    expect($body)->toContain('100');
    expect($body)->toContain('40');
    expect($body)->toContain('7');
    expect($body)->not->toContain(':requested');
    expect($body)->not->toContain(':available');
    expect($body)->not->toContain(':variant');
});

it('resolves the .title translation key', function () {
    $title = __('errors.insufficient_stock.title');

    expect($title)->toBeString()->not->toBe('');
    expect($title)->not->toBe('errors.insufficient_stock.title');
});

it('stores the translation key as the exception message', function () {
    $exception = new InsufficientStockException(7, 3, 100, 40);

    expect($exception->getMessage())->toBe('errors.insufficient_stock');
});

it('is caught by a catch(\\DomainErrorException) block', function () {
    $caught = false;

    try {
        throw new InsufficientStockException(7, 3, 100, 40);
    } catch (DomainErrorException $e) {
        $caught = true;
    }

    expect($caught)->toBeTrue();
});
