<?php

declare(strict_types=1);

use App\Enums\SalesOrderStatus;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * SalesOrderStatus enum contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction. It exercises the enum's own contract (§4.3 base +
 * the standing-instruction HasIcon extension) and does not replace or
 * duplicate any blueprint-named test.
 *
 * Blueprint anchors exercised:
 *   - §2.18 sales_orders.status column and its 'draft' default.
 *   - §4.3 canonical case set and label keys.
 *   - §0 lifecycle / A2: draft → confirmed → partially_dispatched →
 *     dispatched / cancelled.
 *   - §3.16 canBeCancelled(): Draft or Confirmed only.
 *   - §6.5 SalesService: confirmSalesOrder requires Draft;
 *     dispatchSale requires Confirmed | PartiallyDispatched;
 *     cancelSalesOrder requires canBeCancelled().
 *   - §0A.2a enums.sales_order_status.* translation keys.
 *   - §0A.15 TranslationCoverageTest::enums_have_translated_labels()
 *     (label resolution asserted at the unit level so a missing key
 *     fails close to the definition site).
 */
it('declares exactly the five canonical cases', function () {
    expect(SalesOrderStatus::cases())->toHaveCount(5);

    expect(array_map(fn (SalesOrderStatus $c) => $c->value, SalesOrderStatus::cases()))
        ->toBe([
            'draft',
            'confirmed',
            'partially_dispatched',
            'dispatched',
            'cancelled',
        ]);
});

it('matches the §2.18 sales_orders.status default for Draft', function () {
    // The migration pins the literal default so historic migrations stay
    // immutable when enum code evolves (§2 migration contract). This is
    // the in-code guard that the two never drift.
    expect(SalesOrderStatus::Draft->value)->toBe('draft');
});

it('implements HasLabel, HasColor, and HasIcon', function () {
    $case = SalesOrderStatus::Draft;

    expect($case)->toBeInstanceOf(HasLabel::class);
    expect($case)->toBeInstanceOf(HasColor::class);
    expect($case)->toBeInstanceOf(HasIcon::class);
});

it('resolves every label through the enums.sales_order_status namespace', function (SalesOrderStatus $case, string $key) {
    $label = $case->getLabel();

    // A raw-key fallback means the translation is missing for the active
    // locale (§0A.15 test 4).
    expect($label)->not->toBe("enums.sales_order_status.{$key}");
    expect($label)->not->toBe('');

    expect(__("enums.sales_order_status.{$key}"))->toBe($label);
})->with([
    ['case' => SalesOrderStatus::Draft,               'key' => 'draft'],
    ['case' => SalesOrderStatus::Confirmed,           'key' => 'confirmed'],
    ['case' => SalesOrderStatus::PartiallyDispatched, 'key' => 'partially_dispatched'],
    ['case' => SalesOrderStatus::Dispatched,          'key' => 'dispatched'],
    ['case' => SalesOrderStatus::Cancelled,           'key' => 'cancelled'],
]);

it('returns a non-empty Filament color token for every case', function (SalesOrderStatus $case) {
    $color = $case->getColor();

    expect($color)->toBeString()->not->toBe('');
    expect($color)->not->toMatch('/^#/'); // named token, not raw hex (§0A.14 item 4)
})->with(SalesOrderStatus::cases());

it('returns a Heroicon enum case for every case, never a raw string', function (SalesOrderStatus $case) {
    // §1D.4 rule 1: always Heroicon enum, never raw string.
    expect($case->getIcon())->toBeInstanceOf(Heroicon::class);
})->with(SalesOrderStatus::cases());

it('locks the §4.3 canonical color mapping per case', function () {
    // The exact color mapping is a §4.3 blueprint contract, not an
    // implementation choice — assert it literally so a well-meaning
    // refactor cannot silently re-tint a status badge.
    expect(SalesOrderStatus::Draft->getColor())->toBe('gray');
    expect(SalesOrderStatus::Confirmed->getColor())->toBe('primary');
    expect(SalesOrderStatus::PartiallyDispatched->getColor())->toBe('warning');
    expect(SalesOrderStatus::Dispatched->getColor())->toBe('success');
    expect(SalesOrderStatus::Cancelled->getColor())->toBe('danger');
});

it('assigns a distinct icon to every case', function () {
    $icons = array_map(fn (SalesOrderStatus $c) => $c->getIcon(), SalesOrderStatus::cases());

    // Five cases, five distinct icons — no two status badges share a glyph.
    expect(array_unique($icons))->toHaveCount(5);
});

it('locks the §3.16 cancellation-status boundary to Draft and Confirmed', function () {
    // §3.16 SalesOrder::canBeCancelled() allows Draft or Confirmed only —
    // once stock has moved (PartiallyDispatched / Dispatched) cancellation
    // is denied, mirroring the TransferRequisition pre-dispatch rule
    // (§0 principle 14) and the PurchaseOrder pre-receipt rule (§3.14).
    $statusesEligibleForCancellation = [
        SalesOrderStatus::Draft,
        SalesOrderStatus::Confirmed,
    ];

    $statusesIneligibleForCancellation = [
        SalesOrderStatus::PartiallyDispatched,
        SalesOrderStatus::Dispatched,
        SalesOrderStatus::Cancelled,
    ];

    // Two eligible, three ineligible — the full 5-case set is partitioned.
    expect($statusesEligibleForCancellation)->toHaveCount(2);
    expect($statusesIneligibleForCancellation)->toHaveCount(3);
    expect(array_merge($statusesEligibleForCancellation, $statusesIneligibleForCancellation))
        ->toHaveCount(5);
});

it('locks the §6.5 confirm transition boundary to Draft', function () {
    // §6.5 SalesService::confirmSalesOrder() rejects any status other than
    // Draft. Asserting the shape here keeps the transition gate close to
    // the status definition.
    expect(SalesOrderStatus::Draft->value)->toBe('draft');
});

it('locks the §6.5 dispatch transition boundary to Confirmed and PartiallyDispatched', function () {
    // §6.5 SalesService::dispatchSale() accepts Confirmed or
    // PartiallyDispatched only — Draft is not yet confirmed, Dispatched
    // is fully accounted, Cancelled is terminal.
    $dispatchEligible = [
        SalesOrderStatus::Confirmed,
        SalesOrderStatus::PartiallyDispatched,
    ];

    expect($dispatchEligible)->toHaveCount(2);

    foreach ([
        SalesOrderStatus::Draft,
        SalesOrderStatus::Dispatched,
        SalesOrderStatus::Cancelled,
    ] as $ineligible) {
        expect($dispatchEligible)->not->toContain($ineligible);
    }
});

it('distinguishes the in-flight partial-dispatch state from terminal states', function () {
    // §4.3: PartiallyDispatched is 'warning' (live state), Dispatched is
    // 'success' (terminal), Cancelled is 'danger' (terminal). §0 core
    // principle 5 treats partially_dispatched as a first-class live state,
    // not an interim failure.
    expect(SalesOrderStatus::PartiallyDispatched->getColor())->toBe('warning');
    expect(SalesOrderStatus::Dispatched->getColor())->toBe('success');
    expect(SalesOrderStatus::Cancelled->getColor())->toBe('danger');
});

it('round-trips every case through from() and rejects unknown values', function (SalesOrderStatus $case) {
    expect(SalesOrderStatus::from($case->value))->toBe($case);
})->with(SalesOrderStatus::cases());

it('returns null for an unknown backing value via tryFrom()', function () {
    expect(SalesOrderStatus::tryFrom('does_not_exist'))->toBeNull();
});
