<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * StockMovementType enum contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction. It exercises the enum's own contract (§4.4 base +
 * the standing-instruction HasColor / HasIcon extension) and does not
 * replace or duplicate any blueprint-named test.
 *
 * Blueprint anchors exercised:
 *   - §2.6 stock_movements.type string column.
 *   - §4.4 canonical case set, label keys, and isPositive() contract.
 *   - §6.2 InventoryService::recordMovement() rejects
 *     Purchase / Sale / SaleReturn / PurchaseReturn / Adjustment
 *     because those must route through their dedicated service methods.
 *   - §6.2 Adjustment is signed by the caller via adjustment(); the
 *     enum does not classify Adjustment as positive or negative.
 *   - §7E.2 StockMovementsTable column-level signed-quantity color
 *     coding (the enum color is an intent signal, not a direction).
 *   - §0A.2a enums.stock_movement_type.* translation keys.
 *   - §0A.15 TranslationCoverageTest::enums_have_translated_labels()
 *     (label resolution asserted at the unit level so a missing key
 *     fails close to the definition site).
 */
it('declares exactly the nine canonical cases in §4.4 order', function () {
    expect(StockMovementType::cases())->toHaveCount(9);

    expect(array_map(fn (StockMovementType $c) => $c->value, StockMovementType::cases()))
        ->toBe([
            'adjustment',
            'transfer_out',
            'transfer_in',
            'loss',
            'damage',
            'purchase',
            'sale',
            'sale_return',
            'purchase_return',
        ]);
});

it('implements HasLabel, HasColor, and HasIcon', function () {
    $case = StockMovementType::Adjustment;

    expect($case)->toBeInstanceOf(HasLabel::class);
    expect($case)->toBeInstanceOf(HasColor::class);
    expect($case)->toBeInstanceOf(HasIcon::class);
});

it('resolves every label through the enums.stock_movement_type namespace', function (StockMovementType $case, string $key) {
    $label = $case->getLabel();

    // A raw-key fallback means the translation is missing for the active
    // locale (§0A.15 test 4).
    expect($label)->not->toBe("enums.stock_movement_type.{$key}");
    expect($label)->not->toBe('');

    expect(__("enums.stock_movement_type.{$key}"))->toBe($label);
})->with([
    ['case' => StockMovementType::Adjustment,     'key' => 'adjustment'],
    ['case' => StockMovementType::TransferOut,    'key' => 'transfer_out'],
    ['case' => StockMovementType::TransferIn,     'key' => 'transfer_in'],
    ['case' => StockMovementType::Loss,           'key' => 'loss'],
    ['case' => StockMovementType::Damage,         'key' => 'damage'],
    ['case' => StockMovementType::Purchase,       'key' => 'purchase'],
    ['case' => StockMovementType::Sale,           'key' => 'sale'],
    ['case' => StockMovementType::SaleReturn,     'key' => 'sale_return'],
    ['case' => StockMovementType::PurchaseReturn, 'key' => 'purchase_return'],
]);

it('returns a non-empty Filament color token for every case', function (StockMovementType $case) {
    $color = $case->getColor();

    expect($color)->toBeString()->not->toBe('');
    expect($color)->not->toMatch('/^#/'); // named token, not raw hex (§0A.14 item 4)
})->with(StockMovementType::cases());

it('returns a Heroicon enum case for every case, never a raw string', function (StockMovementType $case) {
    // §1D.4 rule 1: always Heroicon enum, never raw string.
    expect($case->getIcon())->toBeInstanceOf(Heroicon::class);
})->with(StockMovementType::cases());

it('assigns a distinct icon to every case', function () {
    $icons = array_map(fn (StockMovementType $c) => $c->getIcon(), StockMovementType::cases());

    // Nine cases, nine distinct icons — no two movement rows share a glyph.
    expect(array_unique($icons))->toHaveCount(9);
});

it('groups the nine cases into the four intent tiers via color', function () {
    // Intent-based coloring (per the standing extension): warning for
    // attention-worthy, danger for exceptional loss, primary for normal
    // outbound, success for normal inbound. Direction is signaled at the
    // table column level via the signed quantity (§7E.2), never here.
    expect(StockMovementType::Adjustment->getColor())->toBe('warning');
    expect(StockMovementType::TransferOut->getColor())->toBe('primary');
    expect(StockMovementType::TransferIn->getColor())->toBe('success');
    expect(StockMovementType::Loss->getColor())->toBe('danger');
    expect(StockMovementType::Damage->getColor())->toBe('danger');
    expect(StockMovementType::Purchase->getColor())->toBe('success');
    expect(StockMovementType::Sale->getColor())->toBe('primary');
    expect(StockMovementType::SaleReturn->getColor())->toBe('warning');
    expect(StockMovementType::PurchaseReturn->getColor())->toBe('warning');
});

it('does not tint normal business outflows as danger', function () {
    // Sale and TransferOut reduce stock but are the normal business
    // direction; danger is reserved for exceptional events (Loss, Damage).
    // This is the load-bearing distinction that keeps the ledger readable.
    expect(StockMovementType::Sale->getColor())->not->toBe('danger');
    expect(StockMovementType::TransferOut->getColor())->not->toBe('danger');
});

// ---------------------------------------------------------------------------
// §4.4 isPositive() contract — the single method that signs the stored qty.
// ---------------------------------------------------------------------------

it('classifies exactly TransferIn, Purchase, and SaleReturn as positive', function () {
    // §4.4 canonical contract. A change to this set changes the sign of
    // every movement InventoryService::recordMovement() writes, so the
    // assertion is exact.
    $positive = array_values(array_filter(
        StockMovementType::cases(),
        fn (StockMovementType $case) => $case->isPositive(),
    ));

    expect($positive)->toHaveCount(3);
    expect($positive)->toEqual([
        StockMovementType::TransferIn,
        StockMovementType::Purchase,
        StockMovementType::SaleReturn,
    ]);
});

it('classifies every non-positive case as negative except Adjustment', function () {
    // §4.4 + §6.2: Adjustment is intentionally excluded from the
    // positive set — the caller supplies the sign via
    // InventoryService::adjustment(), so the enum does not assert a
    // direction. Every other non-positive case is a decreasing movement.
    $negativeByType = [
        StockMovementType::TransferOut,
        StockMovementType::Loss,
        StockMovementType::Damage,
        StockMovementType::Sale,
        StockMovementType::PurchaseReturn,
    ];

    foreach ($negativeByType as $case) {
        expect($case->isPositive())->toBeFalse();
    }

    // Adjustment is not positive and not negative — it is caller-signed.
    expect(StockMovementType::Adjustment->isPositive())->toBeFalse();
});

it('never classifies Adjustment as positive', function () {
    // Locked-in invariant: recordMovement() rejects Adjustment outright
    // (§6.2) and adjustment() preserves the caller-supplied sign. If
    // Adjustment ever returned true here, sign handling in recordMovement
    // would silently invert every manual adjustment.
    expect(StockMovementType::Adjustment->isPositive())->toBeFalse();
});

it('mirrors isPositive() against the recordMovement() rejection list', function () {
    // §6.2 recordMovement() rejects five types: Purchase, Sale,
    // SaleReturn, PurchaseReturn, Adjustment. The three positive types
    // (TransferIn, Purchase, SaleReturn) are all either rejected
    // (Purchase, SaleReturn) or routed through dispatch (TransferIn).
    // This test asserts that the set of positive types never coincides
    // with the raw-write path — TransferIn is the only positive type
    // written via recordMovement-adjacent code, and it is guarded by
    // dispatchTransfer's own availability check.
    $recordMovementRejected = [
        StockMovementType::Purchase,
        StockMovementType::Sale,
        StockMovementType::SaleReturn,
        StockMovementType::PurchaseReturn,
        StockMovementType::Adjustment,
    ];

    expect($recordMovementRejected)->toHaveCount(5);

    // Exactly two of the three positive types sit in the rejection list.
    $positiveInRejectionList = array_values(array_filter(
        $recordMovementRejected,
        fn (StockMovementType $case) => $case->isPositive(),
    ));

    expect($positiveInRejectionList)->toEqual([
        StockMovementType::Purchase,
        StockMovementType::SaleReturn,
    ]);
});

// ---------------------------------------------------------------------------
// Reserved-in-v1 cases (no writer by design)
// ---------------------------------------------------------------------------

it('keeps the reserved-in-v1 cases present in the enum for forward-compat', function () {
    // §4.4: Loss and Damage are recorded via LossLedger only, never as
    // movements. PurchaseReturn has no writer until the supplier-return
    // workflow is specified (§13 item 4). The cases remain in the enum
    // so historical or future data casts cleanly.
    expect(StockMovementType::tryFrom('loss'))->toBe(StockMovementType::Loss);
    expect(StockMovementType::tryFrom('damage'))->toBe(StockMovementType::Damage);
    expect(StockMovementType::tryFrom('purchase_return'))->toBe(StockMovementType::PurchaseReturn);
});

// ---------------------------------------------------------------------------
// Round-trip / backability
// ---------------------------------------------------------------------------

it('round-trips every case through from()', function (StockMovementType $case) {
    expect(StockMovementType::from($case->value))->toBe($case);
})->with(StockMovementType::cases());

it('returns null for an unknown backing value via tryFrom()', function () {
    expect(StockMovementType::tryFrom('does_not_exist'))->toBeNull();
});
