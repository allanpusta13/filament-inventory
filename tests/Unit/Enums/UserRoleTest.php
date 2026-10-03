<?php

declare(strict_types=1);

use App\Enums\UserRole;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * UserRole enum contract tests.
 *
 * NOTE 1: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * NOTE 2 (structural deviation): Blueprint §4.8 originally defined
 * three cases (Admin, Auditor, WarehouseStaff). The project now
 * defines four — BranchManager was added at owner direction with
 * open semantics. The following are NOT decided and MUST NOT be
 * asserted here:
 *
 *   - Badge scope tier for BranchManager (§1B.1a four-tier table has
 *     no branch_manager tier).
 *   - Warehouse assignment behavior (does BranchManager participate in
 *     `user_warehouse`?).
 *   - Policy treatment across §8.1–§8.13.
 *   - Cancellation / force-delete authority (§8.3, §8.7, §8.8, §8.13).
 *
 * Until those decisions land, BranchManager is inert outside the enum
 * definition. This test exercises only the enum's own contract: case
 * set, labels, colors, icons, and backing values. Predicate and policy
 * behavior belongs in `tests/Unit/Models/UserTest.php` and the policy
 * tests, and MUST be extended when the BranchManager decisions are made.
 *
 * Blueprint anchors exercised:
 *   - §2.12 users.role column and its 'warehouse_staff' default.
 *   - §4.8 canonical case set (three originals + one extension).
 *   - §3.18 User::isAdmin() / isAuditor() / isWarehouseStaff() —
 *     predicate existence noted, not tested here (model test surface).
 *   - §0A.2a enums.user_role.* translation keys.
 *   - §0A.15 TranslationCoverageTest::enums_have_translated_labels()
 *     (label resolution asserted at the unit level so a missing key
 *     fails close to the definition site).
 */
it('declares exactly the four canonical cases', function () {
    expect(UserRole::cases())->toHaveCount(4);

    expect(array_map(fn (UserRole $c) => $c->value, UserRole::cases()))
        ->toBe(['admin', 'auditor', 'warehouse_staff', 'branch_manager']);
});

it('preserves the §4.8 original three cases unchanged', function () {
    // The extension is additive: the three cases the blueprint defined
    // must remain at their canonical backing values so every downstream
    // policy (§8) and the badge resolver (§1B.3) continue to match them.
    expect(UserRole::tryFrom('admin'))->toBe(UserRole::Admin);
    expect(UserRole::tryFrom('auditor'))->toBe(UserRole::Auditor);
    expect(UserRole::tryFrom('warehouse_staff'))->toBe(UserRole::WarehouseStaff);
});

it('matches the §2.12 users.role default for WarehouseStaff', function () {
    // The migration pins the literal default so historic migrations stay
    // immutable when enum code evolves (§2 migration contract). This is
    // the in-code guard that the two never drift, and it also matches
    // the §5.6 UserFactory default.
    expect(UserRole::WarehouseStaff->value)->toBe('warehouse_staff');
});

it('implements HasLabel, HasColor, and HasIcon', function () {
    $case = UserRole::Admin;

    expect($case)->toBeInstanceOf(HasLabel::class);
    expect($case)->toBeInstanceOf(HasColor::class);
    expect($case)->toBeInstanceOf(HasIcon::class);
});

it('resolves every label through the enums.user_role namespace', function (UserRole $case, string $key) {
    $label = $case->getLabel();

    // A raw-key fallback means the translation is missing for the active
    // locale (§0A.15 test 4). This is especially load-bearing for
    // BranchManager, whose key does not exist in the blueprint's §0A.2a
    // catalogue and must be added to every locale file before shipping.
    expect($label)->not->toBe("enums.user_role.{$key}");
    expect($label)->not->toBe('');

    expect(__("enums.user_role.{$key}"))->toBe($label);
})->with([
    ['case' => UserRole::Admin,          'key' => 'admin'],
    ['case' => UserRole::Auditor,        'key' => 'auditor'],
    ['case' => UserRole::WarehouseStaff, 'key' => 'warehouse_staff'],
    ['case' => UserRole::BranchManager,  'key' => 'branch_manager'],
]);

it('returns a non-empty Filament color token for every case', function (UserRole $case) {
    $color = $case->getColor();

    expect($color)->toBeString()->not->toBe('');
    expect($color)->not->toMatch('/^#/'); // named token, not raw hex (§0A.14 item 4)
})->with(UserRole::cases());

it('returns a Heroicon enum case for every case, never a raw string', function (UserRole $case) {
    // §1D.4 rule 1: always Heroicon enum, never raw string.
    expect($case->getIcon())->toBeInstanceOf(Heroicon::class);
})->with(UserRole::cases());

it('assigns a distinct icon and distinct color to every case', function () {
    $icons = array_map(fn (UserRole $c) => $c->getIcon(), UserRole::cases());
    $colors = array_map(fn (UserRole $c) => $c->getColor(), UserRole::cases());

    // Four roles, four distinct icons, four distinct colors — a role
    // badge in the §7L.2 UsersTable or the §7K.3 WarehouseInfolist must
    // read apart at a glance.
    expect(array_unique($icons))->toHaveCount(4);
    expect(array_unique($colors))->toHaveCount(4);
});

it('locks the standing-extension color mapping per case', function () {
    // Admin is 'danger' (highest authority); Auditor is 'info' (read-only
    // observer); WarehouseStaff is 'primary' (the operational baseline);
    // BranchManager is 'warning' (elevated supervisory, below admin).
    expect(UserRole::Admin->getColor())->toBe('danger');
    expect(UserRole::Auditor->getColor())->toBe('info');
    expect(UserRole::WarehouseStaff->getColor())->toBe('primary');
    expect(UserRole::BranchManager->getColor())->toBe('warning');
});

it('uses a distinct icon for each of the four roles', function () {
    // Admin: ShieldCheck (authorization); Auditor: Eye (read-only);
    // WarehouseStaff: Users (operational staff); BranchManager:
    // Briefcase (line management).
    expect(UserRole::Admin->getIcon())->toBe(Heroicon::OutlinedShieldCheck);
    expect(UserRole::Auditor->getIcon())->toBe(Heroicon::OutlinedEye);
    expect(UserRole::WarehouseStaff->getIcon())->toBe(Heroicon::OutlinedUsers);
    expect(UserRole::BranchManager->getIcon())->toBe(Heroicon::OutlinedBriefcase);
});

// ---------------------------------------------------------------------------
// Round-trip / backability
// ---------------------------------------------------------------------------

it('round-trips every case through from()', function (UserRole $case) {
    expect(UserRole::from($case->value))->toBe($case);
})->with(UserRole::cases());

it('returns null for an unknown backing value via tryFrom()', function () {
    expect(UserRole::tryFrom('does_not_exist'))->toBeNull();
});

it('rejects the legacy spelling "warehouse staff" (space) as a backing value', function () {
    // Backing values are snake_case identifiers; the space-separated
    // display form is only ever a translated label, never a stored value
    // (§0A.1: enum backing values are not translated).
    expect(UserRole::tryFrom('warehouse staff'))->toBeNull();
    expect(UserRole::tryFrom('WarehouseStaff'))->toBeNull();
});
