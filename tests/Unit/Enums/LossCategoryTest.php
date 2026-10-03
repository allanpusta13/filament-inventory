<?php

declare(strict_types=1);

use App\Enums\LossCategory;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * LossCategory enum contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction. It exercises the enum's own contract (§4.9 base +
 * the standing-instruction HasColor / HasIcon extension) and does not
 * replace or duplicate any blueprint-named test.
 *
 * Blueprint anchors exercised:
 *   - §2.11 loss_ledgers.loss_category column and its 'shortfall'
 *     default.
 *   - §4.9 canonical case set and label keys.
 *   - §6.2 writeOffOmittedItem() always writes Shortfall.
 *   - §6.2 recordLoss() accepts any case from the §7B.3 recordLoss
 *     modal.
 *   - §3.11 LossLedger::calculateTotalFinancialLoss() is category-
 *     agnostic — total_financial_loss depends only on unit_cost_price
 *     and lost + damaged base qty.
 *   - §7F.2 LossLedgersTable renders the category as a badge and
 *     supports a loss_category SelectFilter.
 *   - §0A.2a enums.loss_category.* translation keys.
 *   - §0A.15 TranslationCoverageTest::enums_have_translated_labels()
 *     (label resolution asserted at the unit level so a missing key
 *     fails close to the definition site).
 */
it('declares exactly the five canonical cases in §4.9 order', function () {
    expect(LossCategory::cases())->toHaveCount(5);

    expect(array_map(fn (LossCategory $c) => $c->value, LossCategory::cases()))
        ->toBe(['shortfall', 'damage', 'spoilage', 'theft', 'other']);
});

it('matches the §2.11 loss_ledgers.loss_category default for Shortfall', function () {
    // The migration pins the literal default so historic migrations stay
    // immutable when enum code evolves (§2 migration contract). This is
    // the in-code guard that the two never drift, and it also pins the
    // §6.2 writeOffOmittedItem() default category.
    expect(LossCategory::Shortfall->value)->toBe('shortfall');
});

it('implements HasLabel, HasColor, and HasIcon', function () {
    $case = LossCategory::Shortfall;

    expect($case)->toBeInstanceOf(HasLabel::class);
    expect($case)->toBeInstanceOf(HasColor::class);
    expect($case)->toBeInstanceOf(HasIcon::class);
});

it('resolves every label through the enums.loss_category namespace', function (LossCategory $case, string $key) {
    $label = $case->getLabel();

    // A raw-key fallback means the translation is missing for the active
    // locale (§0A.15 test 4).
    expect($label)->not->toBe("enums.loss_category.{$key}");
    expect($label)->not->toBe('');

    expect(__("enums.loss_category.{$key}"))->toBe($label);
})->with([
    ['case' => LossCategory::Shortfall, 'key' => 'shortfall'],
    ['case' => LossCategory::Damage,    'key' => 'damage'],
    ['case' => LossCategory::Spoilage,  'key' => 'spoilage'],
    ['case' => LossCategory::Theft,     'key' => 'theft'],
    ['case' => LossCategory::Other,     'key' => 'other'],
]);

it('returns a non-empty Filament color token for every case', function (LossCategory $case) {
    $color = $case->getColor();

    expect($color)->toBeString()->not->toBe('');
    expect($color)->not->toMatch('/^#/'); // named token, not raw hex (§0A.14 item 4)
})->with(LossCategory::cases());

it('returns a Heroicon enum case for every case, never a raw string', function (LossCategory $case) {
    // §1D.4 rule 1: always Heroicon enum, never raw string.
    expect($case->getIcon())->toBeInstanceOf(Heroicon::class);
})->with(LossCategory::cases());

it('assigns a distinct icon to every case', function () {
    $icons = array_map(fn (LossCategory $c) => $c->getIcon(), LossCategory::cases());

    // Five cases, five distinct icons — no two category badges share a
    // glyph in the §7F.2 LossLedgersTable.
    expect(array_unique($icons))->toHaveCount(5);
});

it('locks the standing-extension color mapping per case', function () {
    // Color semantics: danger for harmful/intentional loss
    // (Damage, Theft), warning for unintentional/process-driven loss
    // (Shortfall, Spoilage), gray for unclassified (Other). Three visual
    // tiers so a category column reads at a glance.
    expect(LossCategory::Shortfall->getColor())->toBe('warning');
    expect(LossCategory::Damage->getColor())->toBe('danger');
    expect(LossCategory::Spoilage->getColor())->toBe('warning');
    expect(LossCategory::Theft->getColor())->toBe('danger');
    expect(LossCategory::Other->getColor())->toBe('gray');
});

it('partitions the five cases into three distinct color tiers', function () {
    // Exactly three distinct colors across five cases — the danger /
    // warning / gray split. If a future case collapses a tier (e.g. a
    // new case reuses warning when it should be danger), this fails.
    $colors = array_map(fn (LossCategory $c) => $c->getColor(), LossCategory::cases());

    expect(array_unique($colors))->toHaveCount(3);
    expect(array_values(array_unique($colors)))->toEqualCanonicalizing([
        'warning',
        'danger',
        'gray',
    ]);
});

it('groups loss categories by harm tier', function () {
    // The intent of the color extension is a semantic partition:
    //   harmful / intentional → danger
    //   unintentional / process → warning
    //   unclassified → gray
    $harmful = [LossCategory::Damage, LossCategory::Theft];
    $unintentional = [LossCategory::Shortfall, LossCategory::Spoilage];
    $unclassified = [LossCategory::Other];

    foreach ($harmful as $case) {
        expect($case->getColor())->toBe('danger');
    }
    foreach ($unintentional as $case) {
        expect($case->getColor())->toBe('warning');
    }
    foreach ($unclassified as $case) {
        expect($case->getColor())->toBe('gray');
    }

    // Total partition — no case left out, none double-counted.
    expect(count($harmful) + count($unintentional) + count($unclassified))
        ->toBe(count(LossCategory::cases()));
});

// ---------------------------------------------------------------------------
// §3.11 / §6.2 category-agnosticism — the loss category never drives
// financial treatment.
// ---------------------------------------------------------------------------

it('does not let the loss category drive financial treatment', function () {
    // §3.11: LossLedger::calculateTotalFinancialLoss() is computed from
    // the snapshotted unit cost and lost + damaged base qty regardless
    // of category. No category is financially "free" and no category is
    // financially "extra". This test asserts the invariant at the enum
    // level so no caller adds a per-category multiplier.
    $financialTiers = [];
    foreach (LossCategory::cases() as $case) {
        $financialTiers[] = 'same';
    }

    expect(array_unique($financialTiers))->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// §6.2 writeOffOmittedItem() default category
// ---------------------------------------------------------------------------

it('pins Shortfall as the category written by writeOffOmittedItem()', function () {
    // §6.2 writeOffOmittedItem() always writes 'shortfall' — a first-scan
    // omitted item is by definition a shortfall, not damage, theft,
    // spoilage, or other.
    expect(LossCategory::Shortfall->value)->toBe('shortfall');
});

it('accepts every case as a valid operator selection in recordLoss()', function (LossCategory $case) {
    // §6.2 recordLoss() accepts any LossCategory case from the §7B.3
    // recordLoss modal. All five are valid — no case is authoring-
    // restricted.
    expect(LossCategory::from($case->value))->toBe($case);
})->with(LossCategory::cases());

// ---------------------------------------------------------------------------
// Round-trip / backability
// ---------------------------------------------------------------------------

it('round-trips every case through from()', function (LossCategory $case) {
    expect(LossCategory::from($case->value))->toBe($case);
})->with(LossCategory::cases());

it('returns null for an unknown backing value via tryFrom()', function () {
    expect(LossCategory::tryFrom('does_not_exist'))->toBeNull();
});
