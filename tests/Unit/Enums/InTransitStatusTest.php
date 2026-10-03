<?php

declare(strict_types=1);

use App\Enums\InTransitStatus;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * InTransitStatus enum contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction. It exercises the enum's own contract (§4.7 base +
 * the standing-instruction HasColor / HasIcon extension) and does not
 * replace or duplicate any blueprint-named test.
 *
 * Blueprint anchors exercised:
 *   - §2.10 in_transits.status column and its 'in_transit' default.
 *   - §4.7 canonical case set and label keys.
 *   - §0A.2a enums.in_transit_status.* translation keys.
 *   - §0A.15 TranslationCoverageTest::enums_have_translated_labels()
 *     (label resolution is asserted here at the unit level so a
 *      missing key fails close to the definition site).
 */
it('declares exactly the three canonical cases', function () {
    expect(InTransitStatus::cases())->toHaveCount(3);

    expect(array_map(fn (InTransitStatus $case) => $case->value, InTransitStatus::cases()))
        ->toBe(['in_transit', 'cleared', 'lost']);
});

it('matches the §2.10 in_transits.status default for the active case', function () {
    // The migration pins the literal default so historic migrations stay
    // immutable when enum code evolves (§2 migration contract). This
    // assertion is the in-code guard that the two never drift.
    expect(InTransitStatus::InTransit->value)->toBe('in_transit');
});

it('implements HasLabel, HasColor, and HasIcon', function () {
    $case = InTransitStatus::InTransit;

    expect($case)->toBeInstanceOf(HasLabel::class);
    expect($case)->toBeInstanceOf(HasColor::class);
    expect($case)->toBeInstanceOf(HasIcon::class);
});

it('resolves every label through the enums.in_transit_status translation namespace', function (InTransitStatus $case, string $key) {
    $label = $case->getLabel();

    // A raw-key fallback means the translation is missing for the active
    // locale (§0A.15 test 4).
    expect($label)->not->toBe("enums.in_transit_status.{$key}");
    expect($label)->not->toBe('');

    // Keys are declared in §0A.2a; every case must resolve them.
    expect(__("enums.in_transit_status.{$key}"))->toBe($label);
})->with([
    ['case' => InTransitStatus::InTransit, 'key' => 'in_transit'],
    ['case' => InTransitStatus::Cleared,   'key' => 'cleared'],
    ['case' => InTransitStatus::Lost,      'key' => 'lost'],
]);

it('returns a non-empty Filament color token for every case', function (InTransitStatus $case) {
    $color = $case->getColor();

    expect($color)->toBeString()->not->toBe('');

    // Filament color tokens are the named palette entries, not raw hex
    // (§0A.14 item 4: do not concatenate or hard-code colors).
    expect($color)->not->toMatch('/^#/');
})->with(InTransitStatus::cases());

it('returns a Heroicon enum case for every case, never a raw string', function (InTransitStatus $case) {
    // §1D.4 rule 1: always Heroicon enum, never raw string.
    expect($case->getIcon())->toBeInstanceOf(Heroicon::class);
})->with(InTransitStatus::cases());

it('assigns distinct icons and distinct colors across the three cases', function () {
    // Terminal states must read apart from the active state at a glance;
    // the semantic split (info / success / danger) is the whole point of
    // the extension.
    $colors = array_map(fn (InTransitStatus $c) => $c->getColor(), InTransitStatus::cases());
    $icons = array_map(fn (InTransitStatus $c) => $c->getIcon(), InTransitStatus::cases());

    expect(array_unique($colors))->toHaveCount(3);
    expect(array_unique($icons))->toHaveCount(3);
});

it('uses the info color for the active state and terminal colors for the others', function () {
    // Locked-in semantics: InTransit is informational (mirrors
    // TransferRequisitionStatus::Dispatched); Cleared is success
    // (mirrors Completed / Received); Lost is danger (mirrors
    // ClosedWithLoss / Cancelled). See §4.1, §4.2, §4.7.
    expect(InTransitStatus::InTransit->getColor())->toBe('info');
    expect(InTransitStatus::Cleared->getColor())->toBe('success');
    expect(InTransitStatus::Lost->getColor())->toBe('danger');
});

it('returns the same instance for a given case across repeated calls', function () {
    // Enum singletons — a sanity check that no case is re-instantiated
    // somewhere downstream.
    expect(InTransitStatus::from('in_transit'))->toBe(InTransitStatus::InTransit);
    expect(InTransitStatus::tryFrom('does_not_exist'))->toBeNull();
});
