<?php

declare(strict_types=1);

use App\Exceptions\DomainErrorException;
use App\Exceptions\OutstandingQuantityExceededException;
use App\Models\SalesOrderItem;
use App\Models\TransferRequisitionItem;

/**
 * OutstandingQuantityExceededException contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §6.3: key catalogue row
 *     errors.outstanding_quantity_exceeded → { item, attempted, outstanding }.
 *   - §0A.2a: lang/en/errors.php entry
 *     errors.outstanding_quantity_exceeded.title / .body.
 *   - §6.1 GuardsOutstandingQuantity call sites.
 *   - §6.2 InventoryService::scanToReceive / recordLoss call sites.
 *   - §6.5 SalesService::recordSalesReturn call site.
 */
it('extends DomainErrorException', function () {
    expect(is_subclass_of(OutstandingQuantityExceededException::class, DomainErrorException::class))->toBeTrue();
});

it('carries the errors.outstanding_quantity_exceeded translation key', function () {
    $exception = new OutstandingQuantityExceededException(
        itemType: TransferRequisitionItem::class,
        itemId: 42,
        attempted: 60,
        outstanding: 50,
    );

    expect($exception->translationKey())->toBe('errors.outstanding_quantity_exceeded');
});

it('exposes itemType, itemId, attempted, and outstanding as readonly promoted properties', function () {
    $exception = new OutstandingQuantityExceededException(
        itemType: TransferRequisitionItem::class,
        itemId: 42,
        attempted: 60,
        outstanding: 50,
    );

    expect($exception->itemType)->toBe(TransferRequisitionItem::class);
    expect($exception->itemId)->toBe(42);
    expect($exception->attempted)->toBe(60);
    expect($exception->outstanding)->toBe(50);
});

it('populates context with exactly the §6.3 key-catalogue placeholders', function () {
    // §6.3: errors.outstanding_quantity_exceeded → item, attempted, outstanding.
    $exception = new OutstandingQuantityExceededException(
        itemType: TransferRequisitionItem::class,
        itemId: 42,
        attempted: 60,
        outstanding: 50,
    );

    expect($exception->context())->toBe([
        'item' => 42,
        'attempted' => 60,
        'outstanding' => 50,
    ]);
    expect(array_keys($exception->context()))->toBe(['item', 'attempted', 'outstanding']);
});

it('does not leak itemType into the user-facing context', function () {
    // §6.3 key catalogue excludes itemType from the translation
    // placeholders — diagnostic only.
    $exception = new OutstandingQuantityExceededException(
        itemType: SalesOrderItem::class,
        itemId: 42,
        attempted: 60,
        outstanding: 50,
    );

    expect($exception->context())->not->toHaveKey('item_type');
    expect($exception->context())->not->toHaveKey('type');
});

it('accepts every item model class the blueprint throws it with', function () {
    // §6.1 / §6.2 / §6.5 call sites construct this exception with
    // TransferRequisitionItem, PurchaseOrderItem, and SalesOrderItem.
    $types = [
        TransferRequisitionItem::class,
        App\Models\PurchaseOrderItem::class,
        SalesOrderItem::class,
    ];

    foreach ($types as $type) {
        $exception = new OutstandingQuantityExceededException($type, 1, 10, 5);
        expect($exception->itemType)->toBe($type);
    }
});

it('renders the §0A.2a body template with all three placeholders', function () {
    // §0A.2a: 'Attempted :attempted against outstanding :outstanding for item :item.'
    $exception = new OutstandingQuantityExceededException(
        itemType: TransferRequisitionItem::class,
        itemId: 42,
        attempted: 60,
        outstanding: 50,
    );

    $body = __('errors.outstanding_quantity_exceeded.body', $exception->context());

    expect($body)->toContain('60');
    expect($body)->toContain('50');
    expect($body)->toContain('42');
    expect($body)->not->toContain(':attempted');
    expect($body)->not->toContain(':outstanding');
    expect($body)->not->toContain(':item');
});

it('resolves the .title translation key', function () {
    $title = __('errors.outstanding_quantity_exceeded.title');

    expect($title)->toBeString()->not->toBe('');
    expect($title)->not->toBe('errors.outstanding_quantity_exceeded.title');
});

it('is caught by a catch(\\DomainErrorException) block', function () {
    $caught = false;

    try {
        throw new OutstandingQuantityExceededException(
            TransferRequisitionItem::class, 1, 10, 5,
        );
    } catch (DomainErrorException $e) {
        $caught = true;
    }

    expect($caught)->toBeTrue();
});
