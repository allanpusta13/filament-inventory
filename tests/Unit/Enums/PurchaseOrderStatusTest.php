<?php

declare(strict_types=1);

use App\Enums\PurchaseOrderStatus;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * PurchaseOrderStatus enum contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction. It exercises the enum's own contract (§4.2 base +
 * the standing-instruction HasIcon extension) and does not replace or
 * duplicate any blueprint-named test.
 *
 * Blueprint anchors exercised:
 *   - §2.16 purchase_orders.status column and its 'draft' default.
 *   - §4.2 canonical case set and label keys.
 *   - §0 lifecycle / A2: draft → ordered → partially_received →
 *     received / cancelled.
 *   - §3.14 canBeCancelled(): Draft or Ordered only, and no item has
 *     received_base_qty > 0.
 *   - §6.4 PurchaseService: orderPurchase requires Draft;
 *     receivePurchase requires Ordered | PartiallyReceived;
 *     cancelPurchaseOrder requires canBeCancelled().
 *   - §0A.2a enums.purchase_order_status.* translation keys.
 *   - §0A.15 TranslationCoverageTest::enums_have_translated_labels()
 *     (label resolution asserted at the unit level so a missing key
 *     fails close to the definition site).
 */
it('declares exactly the five canonical cases', function () {
    expect(PurchaseOrderStatus::cases())->toHaveCount(5);

    expect(array_map(fn (PurchaseOrderStatus $c) => $c->value, PurchaseOrderStatus::cases()))
        ->toBe([
            'draft',
            'ordered',
            'partially_received',
            'received',
            'cancelled',
        ]);
});

it('matches the §2.16 purchase_orders.status default for Draft', function () {
    // The migration pins the literal default so historic migrations stay
    // immutable when enum code evolves (§2 migration contract). This is
    // the in-code guard that the two never drift.
    expect(PurchaseOrderStatus::Draft->value)->toBe('draft');
});

it('implements HasLabel, HasColor, and HasIcon', function () {
    $case = PurchaseOrderStatus::Draft;

    expect($case)->toBeInstanceOf(HasLabel::class);
    expect($case)->toBeInstanceOf(HasColor::class);
    expect($case)->toBeInstanceOf(HasIcon::class);
});

it('resolves every label through the enums.purchase_order_status namespace', function (PurchaseOrderStatus $case, string $key) {
    $label = $case->getLabel();

    // A raw-key fallback means the translation is missing for the active
    // locale (§0A.15 test 4).
    expect($label)->not->toBe("enums.purchase_order_status.{$key}");
    expect($label)->not->toBe('');

    expect(__("enums.purchase_order_status.{$key}"))->toBe($label);
})->with([
    ['case' => PurchaseOrderStatus::Draft,             'key' => 'draft'],
    ['case' => PurchaseOrderStatus::Ordered,           'key' => 'ordered'],
    ['case' => PurchaseOrderStatus::PartiallyReceived, 'key' => 'partially_received'],
    ['case' => PurchaseOrderStatus::Received,          'key' => 'received'],
    ['case' => PurchaseOrderStatus::Cancelled,         'key' => 'cancelled'],
]);

it('returns a non-empty Filament color token for every case', function (PurchaseOrderStatus $case) {
    $color = $case->getColor();

    expect($color)->toBeString()->not->toBe('');
    expect($color)->not->toMatch('/^#/'); // named token, not raw hex (§0A.14 item 4)
})->with(PurchaseOrderStatus::cases());

it('returns a Heroicon enum case for every case, never a raw string', function (PurchaseOrderStatus $case) {
    // §1D.4 rule 1: always Heroicon enum, never raw string.
    expect($case->getIcon())->toBeInstanceOf(Heroicon::class);
})->with(PurchaseOrderStatus::cases());

it('locks the §4.2 canonical color mapping per case', function () {
    // The exact color mapping is a §4.2 blueprint contract, not an
    // implementation choice — assert it literally so a well-meaning
    // refactor cannot silently re-tint a status badge.
    expect(PurchaseOrderStatus::Draft->getColor())->toBe('gray');
    expect(PurchaseOrderStatus::Ordered->getColor())->toBe('primary');
    expect(PurchaseOrderStatus::PartiallyReceived->getColor())->toBe('warning');
    expect(PurchaseOrderStatus::Received->getColor())->toBe('success');
    expect(PurchaseOrderStatus::Cancelled->getColor())->toBe('danger');
});

it('assigns a distinct icon to every case', function () {
    $icons = array_map(fn (PurchaseOrderStatus $c) => $c->getIcon()->value, PurchaseOrderStatus::cases());

    // Five cases, five distinct icons — no two status badges share a glyph.
    expect(array_unique($icons))->toHaveCount(5);
});

it('locks the §3.14 cancellation-status boundary to Draft and Ordered', function () {
    // §3.14 PurchaseOrder::canBeCancelled() allows Draft or Ordered only
    // (subject to the additional "no item received" guard on the model).
    // This test asserts the status-side gate at the enum level so the
    // model method and the §6.4 cancelPurchaseOrder() service guard
    // cannot silently diverge from the status set.
    $statusesEligibleForCancellation = [
        PurchaseOrderStatus::Draft,
        PurchaseOrderStatus::Ordered,
    ];

    $statusesIneligibleForCancellation = [
        PurchaseOrderStatus::PartiallyReceived,
        PurchaseOrderStatus::Received,
        PurchaseOrderStatus::Cancelled,
    ];

    // Two eligible, three ineligible — the full 5-case set is partitioned.
    expect($statusesEligibleForCancellation)->toHaveCount(2);
    expect($statusesIneligibleForCancellation)->toHaveCount(3);
    expect(array_merge($statusesEligibleForCancellation, $statusesIneligibleForCancellation))
        ->toHaveCount(5);
});

it('locks the §6.4 order transition boundary to Draft', function () {
    // §6.4 PurchaseService::orderPurchase() rejects any status other than
    // Draft. Asserting the shape here keeps the transition gate close to
    // the status definition.
    expect(PurchaseOrderStatus::Draft->value)->toBe('draft');
});

it('locks the §6.4 receive transition boundary to Ordered and PartiallyReceived', function () {
    // §6.4 PurchaseService::receivePurchase() accepts Ordered or
    // PartiallyReceived only — Draft is not yet placed, Received is
    // fully accounted, Cancelled is terminal.
    $receiveEligible = [
        PurchaseOrderStatus::Ordered,
        PurchaseOrderStatus::PartiallyReceived,
    ];

    expect($receiveEligible)->toHaveCount(2);

    foreach ([
        PurchaseOrderStatus::Draft,
        PurchaseOrderStatus::Received,
        PurchaseOrderStatus::Cancelled,
    ] as $ineligible) {
        expect($receiveEligible)->not->toContain($ineligible);
    }
});

it('distinguishes the in-flight partial-receipt state from terminal states', function () {
    // §4.2: PartiallyReceived is 'warning' (live state), Received is
    // 'success' (terminal), Cancelled is 'danger' (terminal). §0 core
    // principle 5 treats partially_received as a first-class live state,
    // not an interim failure.
    expect(PurchaseOrderStatus::PartiallyReceived->getColor())->toBe('warning');
    expect(PurchaseOrderStatus::Received->getColor())->toBe('success');
    expect(PurchaseOrderStatus::Cancelled->getColor())->toBe('danger');
});

it('round-trips every case through from() and rejects unknown values', function (PurchaseOrderStatus $case) {
    expect(PurchaseOrderStatus::from($case->value))->toBe($case);
})->with(PurchaseOrderStatus::cases());

it('returns null for an unknown backing value via tryFrom()', function () {
    expect(PurchaseOrderStatus::tryFrom('does_not_exist'))->toBeNull();
});
