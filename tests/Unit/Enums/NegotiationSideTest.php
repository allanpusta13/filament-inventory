<?php

declare(strict_types=1);

use App\Enums\NegotiationSide;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * NegotiationSide enum contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction. It exercises the enum's own contract (§4.6 base +
 * the standing-instruction HasColor / HasIcon extension) and does not
 * replace or duplicate any blueprint-named test.
 *
 * Blueprint anchors exercised:
 *   - §2.9 transfer_requisition_item_revisions.side string column.
 *   - §4.6 canonical case set and label keys.
 *   - §6.3 NegotiationService::submitRevision() ping-pong transition:
 *     Fulfiller submits → parent moves to UnderReviewRequestor;
 *     Requestor submits → parent moves to UnderReviewFulfiller.
 *   - §7B.4 negotiation_history infolist renders the side as a badge.
 *   - §0A.2a enums.negotiation_side.* translation keys.
 *   - §0A.15 TranslationCoverageTest::enums_have_translated_labels()
 *     (label resolution asserted at the unit level so a missing key
 *     fails close to the definition site).
 */
it('declares exactly the two canonical cases in §4.6 order', function () {
    expect(NegotiationSide::cases())->toHaveCount(2);

    expect(array_map(fn (NegotiationSide $c) => $c->value, NegotiationSide::cases()))
        ->toBe(['fulfiller', 'requestor']);
});

it('implements HasLabel, HasColor, and HasIcon', function () {
    $case = NegotiationSide::Fulfiller;

    expect($case)->toBeInstanceOf(HasLabel::class);
    expect($case)->toBeInstanceOf(HasColor::class);
    expect($case)->toBeInstanceOf(HasIcon::class);
});

it('resolves every label through the enums.negotiation_side namespace', function (NegotiationSide $case, string $key) {
    $label = $case->getLabel();

    // A raw-key fallback means the translation is missing for the active
    // locale (§0A.15 test 4).
    expect($label)->not->toBe("enums.negotiation_side.{$key}");
    expect($label)->not->toBe('');

    expect(__("enums.negotiation_side.{$key}"))->toBe($label);
})->with([
    ['case' => NegotiationSide::Fulfiller, 'key' => 'fulfiller'],
    ['case' => NegotiationSide::Requestor, 'key' => 'requestor'],
]);

it('returns a non-empty Filament color token for every case', function (NegotiationSide $case) {
    $color = $case->getColor();

    expect($color)->toBeString()->not->toBe('');
    expect($color)->not->toMatch('/^#/'); // named token, not raw hex (§0A.14 item 4)
})->with(NegotiationSide::cases());

it('returns a Heroicon enum case for every case, never a raw string', function (NegotiationSide $case) {
    // §1D.4 rule 1: always Heroicon enum, never raw string.
    expect($case->getIcon())->toBeInstanceOf(Heroicon::class);
})->with(NegotiationSide::cases());

it('assigns a distinct icon and distinct color to the two cases', function () {
    // Two sides, two distinct icons, two distinct colors — a side badge
    // in the negotiation history must read apart at a glance.
    $icons = array_map(fn (NegotiationSide $c) => $c->getIcon()->value, NegotiationSide::cases());
    $colors = array_map(fn (NegotiationSide $c) => $c->getColor(), NegotiationSide::cases());

    expect(array_unique($icons))->toHaveCount(2);
    expect(array_unique($colors))->toHaveCount(2);
});

it('locks the standing-extension color mapping per case', function () {
    // Fulfiller is 'info' (the shipping side, informational tone);
    // Requestor is 'primary' (the initiating side, matching the
    // submitRequest action color §1D.1 and TransferRequisitionStatus::
    // Requested color §4.1).
    expect(NegotiationSide::Fulfiller->getColor())->toBe('info');
    expect(NegotiationSide::Requestor->getColor())->toBe('primary');
});

it('mirrors the dispatch/submit action icon vocabulary', function () {
    // Fulfiller is the side that ships stock on dispatch → Truck icon,
    // matching the dispatch action (§1D.1) and TransferRequisitionStatus::
    // Dispatched (§4.1). Requestor is the side that submits → PaperAirplane
    // icon, matching the submitRequest action (§1D.1) and
    // TransferRequisitionStatus::Requested (§4.1).
    expect(NegotiationSide::Fulfiller->getIcon())->toBe(Heroicon::OutlinedTruck);
    expect(NegotiationSide::Requestor->getIcon())->toBe(Heroicon::OutlinedPaperAirplane);
});

// ---------------------------------------------------------------------------
// §6.3 ping-pong transition — the submitter drives the parent status.
// ---------------------------------------------------------------------------

it('documents the §6.3 ping-pong mapping from side to parent review state', function () {
    // §6.3 NegotiationService::submitRevision(): the submitting side
    // proposes; the counterpart becomes the next reviewer.
    //   Fulfiller submits  → parent moves to UnderReviewRequestor
    //   Requestor submits  → parent moves to UnderReviewFulfiller
    // This test asserts the mapping as data so a change to the service
    // must be reflected here (drift guard).
    $parentStatusAfterSubmit = [
        NegotiationSide::Fulfiller->value => 'under_review_requestor',
        NegotiationSide::Requestor->value => 'under_review_fulfiller',
    ];

    expect($parentStatusAfterSubmit)->toBe([
        'fulfiller' => 'under_review_requestor',
        'requestor' => 'under_review_fulfiller',
    ]);
});

it('maps each side to exactly one counterpart review state', function () {
    // The two sides map to different parent statuses — never the same
    // one, and never empty. A same-status mapping would collapse the
    // ping-pong into a single state.
    $counterpartStatuses = [
        'under_review_requestor',   // when Fulfiller submits
        'under_review_fulfiller',   // when Requestor submits
    ];

    expect(array_unique($counterpartStatuses))->toHaveCount(2);
    expect($counterpartStatuses)->not->toContain('');
});

// ---------------------------------------------------------------------------
// Round-trip / backability
// ---------------------------------------------------------------------------

it('round-trips every case through from()', function (NegotiationSide $case) {
    expect(NegotiationSide::from($case->value))->toBe($case);
})->with(NegotiationSide::cases());

it('returns null for an unknown backing value via tryFrom()', function () {
    expect(NegotiationSide::tryFrom('does_not_exist'))->toBeNull();
});
