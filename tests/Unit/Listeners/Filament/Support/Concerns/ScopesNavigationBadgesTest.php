<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Support\Concerns\ScopesNavigationBadges;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * ScopesNavigationBadges contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * The trait is consumed by four Filament resources that do not exist
 * yet in this build. To test the resolver in isolation, the file
 * defines a small harness class that uses the trait and exposes the
 * protected resolver through public wrappers.
 *
 * Blueprint anchors exercised:
 *   - §1B.1a scope tiers, extended to five by owner direction for
 *     BranchManager.
 *   - §1B.3 / §18.5 canonical trait contract.
 *   - §1B.5 invariants (single resolver, cached per request, flushed
 *     on auth change in long-lived workers).
 *   - §23.6 badge scope test plan.
 *   - A12 badge scope is role + warehouse determined, not document
 *     determined.
 *
 * BranchManager treatment (owner direction):
 *   BranchManager follows the same warehouse-assignment tier as
 *   WarehouseStaff. The resolver handles it via an explicit branch
 *   (`isBranchManager()` alongside `isWarehouseStaff()`), NOT a
 *   fall-through, so a future role addition cannot silently inherit
 *   BranchManager's scope.
 */
uses(RefreshDatabase::class);

// ===========================================================================
// Test harness — a class that uses the trait and exposes its surface
// ===========================================================================

/**
 * The trait's resolver is `protected static`. This harness is the
 * single class through which the tests exercise the resolver, mirroring
 * how the four real resources consume the trait.
 */
class ScopesNavigationBadgesHarness
{
    use ScopesNavigationBadges;

    /** @return array<int> */
    public static function resolve(): array
    {
        return self::badgeScopedWarehouseIds();
    }

    public static function hasScope(): bool
    {
        return self::hasBadgeScope();
    }
}

beforeEach(function () {
    ScopesNavigationBadgesHarness::flushBadgeScope();
});

// ===========================================================================
// Unauthenticated
// ===========================================================================

it('returns an empty array when no user is authenticated', function () {
    expect(ScopesNavigationBadgesHarness::resolve())->toBe([]);
    expect(ScopesNavigationBadgesHarness::hasScope())->toBeFalse();
});

// ===========================================================================
// Admin / Auditor — full system scope
// ===========================================================================

it('returns all warehouses for an admin', function () {
    $warehouses = Warehouse::factory()->count(3)->create();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $resolved = ScopesNavigationBadgesHarness::resolve();

    expect($resolved)->toEqualCanonicalizing($warehouses->pluck('id')->all());
});

it('returns all warehouses for an auditor', function () {
    $warehouses = Warehouse::factory()->count(3)->create();
    $auditor = User::factory()->auditor()->create();
    $this->actingAs($auditor);

    $resolved = ScopesNavigationBadgesHarness::resolve();

    expect($resolved)->toEqualCanonicalizing($warehouses->pluck('id')->all());
});

it('returns all warehouses for an admin even with an empty user_warehouse pivot', function () {
    // §1B.1a: Admin and Auditor do NOT need warehouse assignments —
    // their badge authority is global. This is the load-bearing
    // difference vs. the prohibited ad-hoc resolver.
    $warehouses = Warehouse::factory()->count(2)->create();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    expect($admin->warehouses()->count())->toBe(0);
    expect(ScopesNavigationBadgesHarness::resolve())
        ->toEqualCanonicalizing($warehouses->pluck('id')->all());
});

it('returns all warehouses for an auditor even with an empty pivot', function () {
    $warehouses = Warehouse::factory()->count(2)->create();
    $auditor = User::factory()->auditor()->create();
    $this->actingAs($auditor);

    expect(ScopesNavigationBadgesHarness::resolve())
        ->toEqualCanonicalizing($warehouses->pluck('id')->all());
});

// ===========================================================================
// WarehouseStaff — cardinality-driven tiers
// ===========================================================================

it('returns the union of assigned warehouses for warehouse staff with N >= 2', function () {
    $warehouses = Warehouse::factory()->count(3)->create();
    $unassigned = Warehouse::factory()->create();

    $staff = User::factory()->create();
    $staff->warehouses()->attach($warehouses->pluck('id'));
    $this->actingAs($staff);

    $resolved = ScopesNavigationBadgesHarness::resolve();

    expect($resolved)->toEqualCanonicalizing($warehouses->pluck('id')->all());
    expect($resolved)->not->toContain($unassigned->id);
});

it('returns exactly the single assigned warehouse for warehouse staff with N == 1', function () {
    // §1B.1a / A12: N == 1 is exact — no widening, no fallback, no
    // counterpart union.
    $assigned = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();

    $staff = User::factory()->create();
    $staff->warehouses()->attach($assigned->id);
    $this->actingAs($staff);

    $resolved = ScopesNavigationBadgesHarness::resolve();

    expect($resolved)->toBe([$assigned->id]);
    expect($resolved)->not->toContain($other->id);
});

it('returns an empty array for warehouse staff with N == 0', function () {
    Warehouse::factory()->count(3)->create();
    $staff = User::factory()->create();
    $this->actingAs($staff);

    expect(ScopesNavigationBadgesHarness::resolve())->toBe([]);
    expect(ScopesNavigationBadgesHarness::hasScope())->toBeFalse();
});

// ===========================================================================
// BranchManager — owner-direction tier (same warehouse-assignment tier
// as WarehouseStaff)
// ===========================================================================

it('returns the union of assigned warehouses for branch manager with N >= 2', function () {
    $warehouses = Warehouse::factory()->count(3)->create();
    $unassigned = Warehouse::factory()->create();

    $bm = User::factory()->branchManager()->create();
    $bm->warehouses()->attach($warehouses->pluck('id'));
    $this->actingAs($bm);

    $resolved = ScopesNavigationBadgesHarness::resolve();

    expect($resolved)->toEqualCanonicalizing($warehouses->pluck('id')->all());
    expect($resolved)->not->toContain($unassigned->id);
});

it('returns exactly the single assigned warehouse for branch manager with N == 1', function () {
    // Same hard bound as WarehouseStaff N == 1 — counterpart warehouse
    // of a transfer does not widen the badge scope (A12).
    $assigned = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();

    $bm = User::factory()->branchManager()->create();
    $bm->warehouses()->attach($assigned->id);
    $this->actingAs($bm);

    $resolved = ScopesNavigationBadgesHarness::resolve();

    expect($resolved)->toBe([$assigned->id]);
    expect($resolved)->not->toContain($other->id);
});

it('returns an empty array for branch manager with N == 0', function () {
    Warehouse::factory()->count(3)->create();
    $bm = User::factory()->branchManager()->create();
    $this->actingAs($bm);

    expect(ScopesNavigationBadgesHarness::resolve())->toBe([]);
    expect(ScopesNavigationBadgesHarness::hasScope())->toBeFalse();
});

it('does not widen branch manager to all warehouses even when assigned to one', function () {
    // Explicit counter-test: BranchManager is NOT Admin-equivalent. A
    // single assignment yields exactly one warehouse, not every
    // warehouse in the system.
    $warehouses = Warehouse::factory()->count(3)->create();
    $bm = User::factory()->branchManager()->create();
    $bm->warehouses()->attach($warehouses->first()->id);
    $this->actingAs($bm);

    $resolved = ScopesNavigationBadgesHarness::resolve();

    expect($resolved)->toHaveCount(1);
    expect($resolved)->toBe([$warehouses->first()->id]);
});

// ===========================================================================
// Defensive fall-through — unknown roles
// ===========================================================================

it('fails closed for an out-of-domain role rather than widening', function () {
    // §1B.1a: the resolver must never widen to another role's tier for a
    // role it does not recognise. `UserRole` has exactly four cases
    // (Admin/Auditor/WarehouseStaff/BranchManager), all wired into the
    // branches above, and `users.role` carries the enum cast — so an
    // unknown value cannot be persisted through the model. The reachable
    // fail-closed behaviour is the cast rejecting the value on read: the
    // resolver reads `role` inside its comparison chain, so an
    // out-of-domain value throws instead of silently resolving wider.
    Warehouse::factory()->count(3)->create();

    $user = User::factory()->create();
    // Raw attribute assignment bypasses the set mutator — the same
    // out-of-domain value a drift in the backing column would produce.
    $user->setRawAttributes([...$user->getAttributes(), 'role' => 'unregistered_role']);
    $this->actingAs($user);

    expect(fn () => ScopesNavigationBadgesHarness::resolve())
        ->toThrow(ValueError::class);
});

// ===========================================================================
// hasBadgeScope()
// ===========================================================================

it('reports hasBadgeScope true whenever the resolver yields a non-empty set', function (string $setup) {
    [$user, $createWarehouses] = match ($setup) {
        'admin' => [User::factory()->admin()->create(), 2],
        'auditor' => [User::factory()->auditor()->create(), 2],
        'staff_n1' => [User::factory()->create(), 1],
        'staff_n2' => [User::factory()->create(), 2],
        'branch_n1' => [User::factory()->branchManager()->create(), 1],
        'branch_n2' => [User::factory()->branchManager()->create(), 2],
    };

    $warehouses = Warehouse::factory()->count($createWarehouses)->create();
    if (! $user->isAdmin() && ! $user->isAuditor()) {
        $user->warehouses()->attach($warehouses->pluck('id'));
    }
    $this->actingAs($user);

    expect(ScopesNavigationBadgesHarness::hasScope())->toBeTrue();
})->with([
    'admin',
    'auditor',
    'staff_n1',
    'staff_n2',
    'branch_n1',
    'branch_n2',
]);

it('reports hasBadgeScope false for a branch manager with no assignments', function () {
    $bm = User::factory()->branchManager()->create();
    $this->actingAs($bm);

    expect(ScopesNavigationBadgesHarness::hasScope())->toBeFalse();
});

it('hasBadgeScope fails closed for an out-of-domain role', function () {
    // Same fail-closed contract as the resolve() case above: an
    // out-of-domain role throws on the `role` cast rather than reporting
    // a widened or falsely-empty scope.
    $user = User::factory()->create();
    $user->setRawAttributes([...$user->getAttributes(), 'role' => 'unregistered_role']);
    $this->actingAs($user);

    expect(fn () => ScopesNavigationBadgesHarness::hasScope())
        ->toThrow(ValueError::class);
});

// ===========================================================================
// Per-request caching
// ===========================================================================

it('caches the resolved scope within the request', function () {
    Warehouse::factory()->count(2)->create();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    ScopesNavigationBadgesHarness::resolve();

    DB::enableQueryLog();
    ScopesNavigationBadgesHarness::resolve();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toHaveCount(0);
});

it('returns the same array instance on repeated calls', function () {
    Warehouse::factory()->count(2)->create();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $first = ScopesNavigationBadgesHarness::resolve();
    $second = ScopesNavigationBadgesHarness::resolve();

    expect($first)->toBe($second);
});

// ===========================================================================
// flushBadgeScope()
// ===========================================================================

it('flushBadgeScope clears the cached warehouse set', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Warehouse::factory()->create();
    ScopesNavigationBadgesHarness::resolve();

    $newWarehouse = Warehouse::factory()->create();
    expect(ScopesNavigationBadgesHarness::resolve())->not->toContain($newWarehouse->id);

    ScopesNavigationBadgesHarness::flushBadgeScope();

    expect(ScopesNavigationBadgesHarness::resolve())->toContain($newWarehouse->id);
});

it('flushBadgeScope is a public static method — callable from AppServiceProvider', function () {
    $reflection = new ReflectionMethod(ScopesNavigationBadgesHarness::class, 'flushBadgeScope');

    expect($reflection->isPublic())->toBeTrue();
    expect($reflection->isStatic())->toBeTrue();
});

// ===========================================================================
// Cross-resource independence — trait semantics
// ===========================================================================

it('gives each consumer class its own copy of the static caches', function () {
    $second = new class
    {
        use ScopesNavigationBadges;

        /** @return array<int> */
        public static function resolve(): array
        {
            return self::badgeScopedWarehouseIds();
        }
    };

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Warehouse::factory()->create();

    ScopesNavigationBadgesHarness::resolve();

    DB::enableQueryLog();
    $second::resolve();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->not->toHaveCount(0);
});

// ===========================================================================
// Structural contract
// ===========================================================================

it('declares exactly the three methods from the canonical contract', function () {
    // §1B.3: one resolver, one scope check, one flush. No other public
    // surface — a regression that added a `scope()` alias or similar
    // would silently create a second resolution path and violate
    // §1B.5's "single sanctioned resolver".
    $methodNames = collect((new ReflectionClass(ScopesNavigationBadges::class))->getMethods())
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    expect($methodNames)->toBe([
        'badgeScopedWarehouseIds',
        'flushBadgeScope',
        'hasBadgeScope',
    ]);
});

it('does not expose an ad-hoc auth()->user()->warehouses() shortcut', function () {
    // §1B.5 invariant 4: the scope resolver is the ONLY sanctioned way
    // to compute badge warehouse IDs.
    $reflection = new ReflectionClass(ScopesNavigationBadges::class);
    $methodNames = collect($reflection->getMethods())->pluck('name')->all();

    expect($methodNames)->not->toContain('warehousesForBadge');
    expect($methodNames)->not->toContain('badgeWarehouses');
    expect($methodNames)->not->toContain('scope');
    expect($methodNames)->not->toContain('userWarehouses');
});
