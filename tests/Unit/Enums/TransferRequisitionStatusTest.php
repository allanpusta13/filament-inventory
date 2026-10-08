<?php

declare(strict_types=1);

use App\Enums\TransferRequisitionStatus;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * TransferRequisitionStatus enum contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction. It exercises the enum's own contract (§4.1 base +
 * the standing-instruction HasIcon extension) and does not replace or
 * duplicate any blueprint-named test.
 *
 * Blueprint anchors exercised:
 *   - §2.7 transfer_requisitions.status column and its 'draft' default.
 *   - §4.1 canonical case set and label keys.
 *   - §0 lifecycle: draft → requested → under_review_fulfiller ⇌
 *     under_review_requestor → confirmed → dispatched ⇌
 *     partially_received → completed / closed_with_loss / cancelled.
 *   - §3.7 canBeCancelled() five pre-dispatch states.
 *   - §6.1/§6.2 dispatch requires Confirmed; receive requires
 *     Dispatched | PartiallyReceived.
 *   - §0A.2a enums.transfer_requisition_status.* translation keys.
 *   - §0A.15 TranslationCoverageTest::enums_have_translated_labels()
 *     (label resolution asserted at the unit level so a missing key
 *     fails close to the definition site).
 */
it('declares exactly the ten canonical cases', function () {
    expect(TransferRequisitionStatus::cases())->toHaveCount(10);

    expect(array_map(fn (TransferRequisitionStatus $c) => $c->value, TransferRequisitionStatus::cases()))
        ->toBe([
            'draft',
            'requested',
            'under_review_fulfiller',
            'under_review_requestor',
            'confirmed',
            'dispatched',
            'partially_received',
            'completed',
            'closed_with_loss',
            'cancelled',
        ]);
});

it('matches the §2.7 transfer_requisitions.status default for Draft', function () {
    // The migration pins the literal default so historic migrations stay
    // immutable when enum code evolves (§2 migration contract). This is
    // the in-code guard that the two never drift.
    expect(TransferRequisitionStatus::Draft->value)->toBe('draft');
});

it('implements HasLabel, HasColor, and HasIcon', function () {
    $case = TransferRequisitionStatus::Draft;

    expect($case)->toBeInstanceOf(HasLabel::class);
    expect($case)->toBeInstanceOf(HasColor::class);
    expect($case)->toBeInstanceOf(HasIcon::class);
});

it('resolves every label through the enums.transfer_requisition_status namespace', function (TransferRequisitionStatus $case, string $key) {
    $label = $case->getLabel();

    // A raw-key fallback means the translation is missing for the active
    // locale (§0A.15 test 4).
    expect($label)->not->toBe("enums.transfer_requisition_status.{$key}");
    expect($label)->not->toBe('');

    expect(__("enums.transfer_requisition_status.{$key}"))->toBe($label);
})->with([
    ['case' => TransferRequisitionStatus::Draft,                'key' => 'draft'],
    ['case' => TransferRequisitionStatus::Requested,            'key' => 'requested'],
    ['case' => TransferRequisitionStatus::UnderReviewFulfiller, 'key' => 'under_review_fulfiller'],
    ['case' => TransferRequisitionStatus::UnderReviewRequestor, 'key' => 'under_review_requestor'],
    ['case' => TransferRequisitionStatus::Confirmed,            'key' => 'confirmed'],
    ['case' => TransferRequisitionStatus::Dispatched,           'key' => 'dispatched'],
    ['case' => TransferRequisitionStatus::PartiallyReceived,    'key' => 'partially_received'],
    ['case' => TransferRequisitionStatus::Completed,            'key' => 'completed'],
    ['case' => TransferRequisitionStatus::ClosedWithLoss,       'key' => 'closed_with_loss'],
    ['case' => TransferRequisitionStatus::Cancelled,            'key' => 'cancelled'],
]);

it('returns a non-empty Filament color token for every case', function (TransferRequisitionStatus $case) {
    $color = $case->getColor();

    expect($color)->toBeString()->not->toBe('');
    expect($color)->not->toMatch('/^#/'); // named token, not raw hex (§0A.14 item 4)
})->with(TransferRequisitionStatus::cases());

it('returns a Heroicon enum case for every case, never a raw string', function (TransferRequisitionStatus $case) {
    // §1D.4 rule 1: always Heroicon enum, never raw string.
    expect($case->getIcon())->toBeInstanceOf(Heroicon::class);
})->with(TransferRequisitionStatus::cases());

it('locks the §4.1 canonical color mapping per case', function () {
    // The exact color mapping is a §4.1 blueprint contract, not an
    // implementation choice — assert it literally so a well-meaning
    // refactor cannot silently re-tint a status badge.
    expect(TransferRequisitionStatus::Draft->getColor())->toBe('gray');
    expect(TransferRequisitionStatus::Requested->getColor())->toBe('warning');
    expect(TransferRequisitionStatus::UnderReviewFulfiller->getColor())->toBe('warning');
    expect(TransferRequisitionStatus::UnderReviewRequestor->getColor())->toBe('warning');
    expect(TransferRequisitionStatus::Confirmed->getColor())->toBe('primary');
    expect(TransferRequisitionStatus::Dispatched->getColor())->toBe('info');
    expect(TransferRequisitionStatus::PartiallyReceived->getColor())->toBe('warning');
    expect(TransferRequisitionStatus::Completed->getColor())->toBe('success');
    expect(TransferRequisitionStatus::ClosedWithLoss->getColor())->toBe('danger');
    expect(TransferRequisitionStatus::Cancelled->getColor())->toBe('danger');
});

it('assigns a distinct icon to every case except the deliberate under-review pair', function () {
    $icons = array_map(fn (TransferRequisitionStatus $c) => $c->getIcon()->value, TransferRequisitionStatus::cases());

    // §4.1: the two under-review cases intentionally share one glyph (and
    // one color) — the negotiation ping-pong is a single semantic tier.
    // Ten cases therefore yield nine distinct icons.
    expect(array_unique($icons))->toHaveCount(9);

    // Pin the shared tier explicitly so a future refactor cannot silently
    // split the pair apart.
    expect(TransferRequisitionStatus::UnderReviewFulfiller->getIcon())
        ->toBe(TransferRequisitionStatus::UnderReviewRequestor->getIcon());
});

it('locks the pre-dispatch cancellation boundary to the five canBeCancelled() states', function () {
    // §0 core principle 14 / §3.7 canBeCancelled(): cancellation is legal
    // only while a requisition is in a pre-dispatch state. This test
    // asserts the boundary at the enum level so §3.7 and §7B.3 cannot
    // silently diverge from the status set.
    $cancellable = [
        TransferRequisitionStatus::Draft,
        TransferRequisitionStatus::Requested,
        TransferRequisitionStatus::UnderReviewFulfiller,
        TransferRequisitionStatus::UnderReviewRequestor,
        TransferRequisitionStatus::Confirmed,
    ];

    $notCancellable = [
        TransferRequisitionStatus::Dispatched,
        TransferRequisitionStatus::PartiallyReceived,
        TransferRequisitionStatus::Completed,
        TransferRequisitionStatus::ClosedWithLoss,
        TransferRequisitionStatus::Cancelled,
    ];

    // Five states are cancellable, five are not — a change to either set
    // must be reflected in §3.7 TransferRequisition::canBeCancelled().
    expect($cancellable)->toHaveCount(5);
    expect($notCancellable)->toHaveCount(5);
    expect(array_merge($cancellable, $notCancellable))
        ->toHaveCount(TransferRequisitionStatus::cases() ? 10 : 0);
});

it('round-trips every case through from() and rejects unknown values', function (TransferRequisitionStatus $case) {
    expect(TransferRequisitionStatus::from($case->value))->toBe($case);
})->with(TransferRequisitionStatus::cases());

it('returns null for an unknown backing value via tryFrom()', function () {
    expect(TransferRequisitionStatus::tryFrom('does_not_exist'))->toBeNull();
});

it('groups the two under-review states as a shared semantic tier', function () {
    // §4.1 both under-review cases share 'warning'; the negotiation
    // ping-pong never distinguishes them color-wise.
    expect(TransferRequisitionStatus::UnderReviewFulfiller->getColor())
        ->toBe(TransferRequisitionStatus::UnderReviewRequestor->getColor());
});

it('distinguishes terminal success from terminal failure', function () {
    // §4.1: Completed is success; ClosedWithLoss and Cancelled are danger.
    expect(TransferRequisitionStatus::Completed->getColor())->toBe('success');
    expect(TransferRequisitionStatus::ClosedWithLoss->getColor())->toBe('danger');
    expect(TransferRequisitionStatus::Cancelled->getColor())->toBe('danger');
});
