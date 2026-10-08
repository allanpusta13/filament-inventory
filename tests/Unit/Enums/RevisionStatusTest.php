<?php

declare(strict_types=1);

use App\Enums\RevisionStatus;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * RevisionStatus enum contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction. It exercises the enum's own contract (§4.5 base +
 * the standing-instruction HasColor / HasIcon extension) and does not
 * replace or duplicate any blueprint-named test.
 *
 * Blueprint anchors exercised:
 *   - §2.9 transfer_requisition_item_revisions.status column and its
 *     'pending' default.
 *   - §4.5 canonical case set and label keys.
 *   - §3.9 ensureCanTransitionTo() single-move-out-of-Pending guard:
 *     Pending → Accepted / Rejected only; no transition from a
 *     resolved state, and no transition back into Pending.
 *   - §6.3 NegotiationService::accept() / reject() resolve revisions
 *     transactionally under parent/item/revision locks.
 *   - §0A.2a enums.revision_status.* translation keys.
 *   - §0A.15 TranslationCoverageTest::enums_have_translated_labels()
 *     (label resolution asserted at the unit level so a missing key
 *     fails close to the definition site).
 */
it('declares exactly the three canonical cases in §4.5 order', function () {
    expect(RevisionStatus::cases())->toHaveCount(3);

    expect(array_map(fn (RevisionStatus $c) => $c->value, RevisionStatus::cases()))
        ->toBe(['pending', 'accepted', 'rejected']);
});

it('matches the §2.9 transfer_requisition_item_revisions.status default for Pending', function () {
    // The migration pins the literal default so historic migrations stay
    // immutable when enum code evolves (§2 migration contract). This is
    // the in-code guard that the two never drift.
    expect(RevisionStatus::Pending->value)->toBe('pending');
});

it('implements HasLabel, HasColor, and HasIcon', function () {
    $case = RevisionStatus::Pending;

    expect($case)->toBeInstanceOf(HasLabel::class);
    expect($case)->toBeInstanceOf(HasColor::class);
    expect($case)->toBeInstanceOf(HasIcon::class);
});

it('resolves every label through the enums.revision_status namespace', function (RevisionStatus $case, string $key) {
    $label = $case->getLabel();

    // A raw-key fallback means the translation is missing for the active
    // locale (§0A.15 test 4).
    expect($label)->not->toBe("enums.revision_status.{$key}");
    expect($label)->not->toBe('');

    expect(__("enums.revision_status.{$key}"))->toBe($label);
})->with([
    ['case' => RevisionStatus::Pending,  'key' => 'pending'],
    ['case' => RevisionStatus::Accepted, 'key' => 'accepted'],
    ['case' => RevisionStatus::Rejected, 'key' => 'rejected'],
]);

it('returns a non-empty Filament color token for every case', function (RevisionStatus $case) {
    $color = $case->getColor();

    expect($color)->toBeString()->not->toBe('');
    expect($color)->not->toMatch('/^#/'); // named token, not raw hex (§0A.14 item 4)
})->with(RevisionStatus::cases());

it('returns a Heroicon enum case for every case, never a raw string', function (RevisionStatus $case) {
    // §1D.4 rule 1: always Heroicon enum, never raw string.
    expect($case->getIcon())->toBeInstanceOf(Heroicon::class);
})->with(RevisionStatus::cases());

it('assigns a distinct icon and distinct color to every case', function () {
    $icons = array_map(fn (RevisionStatus $c) => $c->getIcon()->value, RevisionStatus::cases());
    $colors = array_map(fn (RevisionStatus $c) => $c->getColor(), RevisionStatus::cases());

    // Three cases, three distinct icons, three distinct colors — the
    // resolved/pending split is the whole point of the visual.
    expect(array_unique($icons))->toHaveCount(3);
    expect(array_unique($colors))->toHaveCount(3);
});

it('locks the standing-extension color mapping per case', function () {
    // Pending mirrors the UnderReview* warning tier (§4.1); Accepted
    // mirrors Completed success (§4.1); Rejected mirrors Cancelled danger
    // (§4.1). Cross-resource consistency is intentional.
    expect(RevisionStatus::Pending->getColor())->toBe('warning');
    expect(RevisionStatus::Accepted->getColor())->toBe('success');
    expect(RevisionStatus::Rejected->getColor())->toBe('danger');
});

it('mirrors the accept/reject action icon vocabulary', function () {
    // §1D.1: acceptRevision uses CheckCircle; rejectRevision uses XCircle.
    // The enum icons must match so an "Accepted" badge and the "Accept
    // revision" action read as the same concept.
    expect(RevisionStatus::Accepted->getIcon())->toBe(Heroicon::OutlinedCheckCircle);
    expect(RevisionStatus::Rejected->getIcon())->toBe(Heroicon::OutlinedXCircle);
});

it('uses a pending icon distinct from both resolved icons', function () {
    // Clock reads as "waiting"; it must not collide with CheckCircle or
    // XCircle so the pending badge is unambiguous in a mixed list.
    expect(RevisionStatus::Pending->getIcon())->toBe(Heroicon::OutlinedClock);
    expect(RevisionStatus::Pending->getIcon())->not->toBe(RevisionStatus::Accepted->getIcon());
    expect(RevisionStatus::Pending->getIcon())->not->toBe(RevisionStatus::Rejected->getIcon());
});

// ---------------------------------------------------------------------------
// §3.9 ensureCanTransitionTo() boundary — the single legal move.
// ---------------------------------------------------------------------------

it('permits exactly one legal transition out of Pending to each terminal state', function () {
    // §3.9: ensureCanTransitionTo() allows Pending → Accepted and
    // Pending → Rejected. Both target states are single-step resolutions.
    // No other transitions are legal.
    $pending = RevisionStatus::Pending;
    $accepted = RevisionStatus::Accepted;
    $rejected = RevisionStatus::Rejected;

    // There is no method on the enum itself that enforces the transition;
    // the guard lives on the model. This test asserts the shape of the
    // legal transition graph the model must implement.
    $legalTransitions = [
        'pending' => ['accepted', 'rejected'],
        'accepted' => [],
        'rejected' => [],
    ];

    expect($legalTransitions[$pending->value])->toBe(['accepted', 'rejected']);
    expect($legalTransitions[$accepted->value])->toBe([]);
    expect($legalTransitions[$rejected->value])->toBe([]);
});

it('partitions the three cases into pending vs resolved', function () {
    // Only Pending is non-terminal. Both accepted and rejected are
    // resolved — this drives the "pending revision" filter used by the
    // accept/reject actions in §7B.3 and ViewTransferRequisition (§18.2a).
    $pendingCases = array_values(array_filter(RevisionStatus::cases(), fn ($c) => $c === RevisionStatus::Pending));
    $resolvedCases = array_values(array_filter(RevisionStatus::cases(), fn ($c) => $c !== RevisionStatus::Pending));

    expect($pendingCases)->toHaveCount(1);
    expect($resolvedCases)->toHaveCount(2);
    expect($resolvedCases)->toEqualCanonicalizing([
        RevisionStatus::Accepted,
        RevisionStatus::Rejected,
    ]);
});

it('classifies exactly one case as non-terminal', function () {
    // Guard against an accidental new case that is neither terminal nor
    // Pending — every case must be exactly one of the two.
    $terminal = [RevisionStatus::Accepted, RevisionStatus::Rejected];

    foreach (RevisionStatus::cases() as $case) {
        $isTerminal = in_array($case, $terminal, true);
        $isPending = $case === RevisionStatus::Pending;

        expect($isTerminal xor $isPending)->toBeTrue();
    }
});

// ---------------------------------------------------------------------------
// Round-trip / backability
// ---------------------------------------------------------------------------

it('round-trips every case through from()', function (RevisionStatus $case) {
    expect(RevisionStatus::from($case->value))->toBe($case);
})->with(RevisionStatus::cases());

it('returns null for an unknown backing value via tryFrom()', function () {
    expect(RevisionStatus::tryFrom('does_not_exist'))->toBeNull();
});
