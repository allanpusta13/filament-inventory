<?php

declare(strict_types=1);

use App\Exceptions\DomainErrorException;
use App\Exceptions\DomainRuleViolationException;

/**
 * DomainRuleViolationException contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §6.3: "class DomainRuleViolationException extends DomainErrorException {}".
 *   - §6.3 key catalogue — the complete set of keys this class carries.
 *   - §0A.2a: every errors.* entry referenced by these keys must
 *     resolve its .title and .body.
 *   - Call sites across §6.2 / §6.3 / §7 actions / §18.2a / §19.x.
 */
it('extends DomainErrorException', function () {
    expect(is_subclass_of(DomainRuleViolationException::class, DomainErrorException::class))->toBeTrue();
});

it('has no overridden constructor — uses the base __construct directly', function () {
    // §6.3: bare subclass with an empty body. If a future change adds a
    // constructor, this test forces a call-site review.
    $reflection = new ReflectionClass(DomainRuleViolationException::class);

    expect($reflection->getConstructor()->getDeclaringClass()->getName())
        ->toBe(DomainErrorException::class);
});

it('accepts a caller-supplied translation key and context array', function () {
    $exception = new DomainRuleViolationException(
        'errors.same_warehouse_transfer',
        ['warehouse' => 7],
    );

    expect($exception->translationKey())->toBe('errors.same_warehouse_transfer');
    expect($exception->context())->toBe(['warehouse' => 7]);
});

it('defaults context to an empty array when omitted', function () {
    $exception = new DomainRuleViolationException('errors.empty_transfer_items');

    expect($exception->context())->toBe([]);
});

// ---------------------------------------------------------------------------
// §6.3 key catalogue — every key this class can carry must resolve.
// ---------------------------------------------------------------------------

it('resolves .title and .body for every §6.3 key catalogue entry it carries', function (string $key) {
    $exception = new DomainRuleViolationException($key, []);

    $title = __($exception->translationKey().'.title');
    $body = __($exception->translationKey().'.body');

    // §0A.15 test 4: no raw-key fallback for any configured locale.
    expect($title)->not->toBe($key.'.title');
    expect($title)->not->toBe('');
    expect($body)->not->toBe($key.'.body');
    expect($body)->not->toBe('');
})->with([
    // Movement / unit preconditions (§6.2)
    'errors.invalid_movement_type',
    'errors.invalid_unit_ratio',
    'errors.same_warehouse_transfer',

    // Transfer / purchase / sales payload shape
    'errors.empty_transfer_items',
    'errors.duplicate_transfer_variant',
    'errors.empty_purchase_items',
    'errors.empty_purchase_receipt',
    'errors.empty_sales_items',
    'errors.empty_sales_dispatch',
    'errors.empty_requisition_items',
    'errors.invalid_return_quantity',
    'errors.missing_item_field',
    'errors.invalid_item_quantity',

    // Reference resolution
    'errors.unknown_variant',
    'errors.undefined_unit',
    'errors.unit_ratio_mismatch',
    'errors.unknown_requisition_item',
    'errors.cross_item_revision',

    // Scan payload / loss / negotiation preconditions
    'errors.non_integer_payload',
    'errors.negative_payload',
    'errors.empty_loss',
    'errors.invalid_proposed_quantity',
    'errors.missing_approved_quantity',

    // Operational scope / derived identifiers
    'errors.warehouse_out_of_scope',
    'errors.warehouse_code_exhausted',
]);

it('supports the per-line context shape used by directTransfer()', function () {
    // §6.2 directTransfer() adds `index` to keys that are header-scoped
    // elsewhere (invalid_unit_ratio, undefined_unit).
    $exception = new DomainRuleViolationException(
        'errors.invalid_unit_ratio',
        ['index' => 2, 'ratio' => 0],
    );

    expect($exception->context())->toBe(['index' => 2, 'ratio' => 0]);
});

it('supports the two context shapes for errors.missing_approved_quantity', function (array $context) {
    // §6.3 key catalogue: { requisition } OR { item } depending on
    // whether the check is at requisition or item level.
    $exception = new DomainRuleViolationException('errors.missing_approved_quantity', $context);

    expect($exception->context())->toBe($context);
})->with([
    'requisition-level' => [['requisition' => 7]],
    'item-level' => [['item' => 42]],
]);

it('supports the two context shapes for errors.undefined_unit', function (array $context) {
    $exception = new DomainRuleViolationException('errors.undefined_unit', $context);

    expect($exception->context())->toBe($context);
})->with([
    'negotiation-level' => [['unit' => 'box', 'variant' => 7]],
    'transfer-level' => [['unit' => 'box', 'variant' => 7, 'index' => 2]],
]);

it('renders a body template for errors.warehouse_out_of_scope with both endpoints', function () {
    // §0A.2a: 'Warehouses :from / :to are outside your assignment.'
    $exception = new DomainRuleViolationException(
        'errors.warehouse_out_of_scope',
        ['from' => 1, 'to' => 2],
    );

    $body = __('errors.warehouse_out_of_scope.body', $exception->context());

    expect($body)->toContain('1');
    expect($body)->toContain('2');
    expect($body)->not->toContain(':from');
    expect($body)->not->toContain(':to');
});

it('is caught by a catch(\\DomainErrorException) block', function () {
    $caught = false;

    try {
        throw new DomainRuleViolationException('errors.empty_transfer_items');
    } catch (DomainErrorException $e) {
        $caught = true;
    }

    expect($caught)->toBeTrue();
});

it('is caught by a catch(\\DomainException) block', function () {
    // §6.3 compatibility guarantee — the Livewire ScanForm catch block
    // (§21.1) falls back to the domain-exception branch.
    $caught = false;

    try {
        throw new DomainRuleViolationException('errors.empty_transfer_items');
    } catch (DomainException $e) {
        $caught = true;
    }

    expect($caught)->toBeTrue();
});
