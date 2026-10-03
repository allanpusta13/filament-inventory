<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

/**
 * AppServiceProvider badge-scope flush tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §17.2 / §1B.3: flush called on both Logout and Login.
 *   - §1B.5 invariants 5: the scope resolver caches within the request
 *     but is flushed on logout / re-authentication in long-lived
 *     workers — the flush covers both the warehouse-ID scope and the
 *     cached badge count.
 *   - §24 concurrency matrix: "Badge scope resolution under concurrent
 *     requests — no cross-request cache bleed."
 *
 * Build-order note: the four badge-bearing Filament resources are not
 * yet on disk. The provider's `registerBadgeScopeFlush()` guards each
 * call with `method_exists()` so dispatch is a no-op during the
 * partial build. The full behavioral test — asserting each resource's
 * cache is cleared — is marked skipped until the resources exist.
 */
uses(RefreshDatabase::class);

// ===========================================================================
// Registration
// ===========================================================================

it('registers a listener for the Logout event', function () {
    expect(Event::hasListeners(Logout::class))->toBeTrue();
});

it('registers a listener for the Login event', function () {
    // The Login listener closes the re-auth-without-logout case
    // (session expiry followed by direct Auth::login()).
    expect(Event::hasListeners(Login::class))->toBeTrue();
});

// ===========================================================================
// Dispatch robustness — build-order guard
// ===========================================================================

it('does not crash when Logout is dispatched during the partial build', function () {
    // With the `method_exists()` guard, missing resource classes are a
    // no-op. If the guard is ever removed before the resources exist,
    // this test fails loudly instead of letting a user auth event
    // crash the panel in production.
    expect(fn () => Event::dispatch(new Logout('web')))
        ->not->toThrow(Throwable::class);
});

it('does not crash when Login is dispatched during the partial build', function () {
    $user = User::factory()->create();

    expect(fn () => Event::dispatch(new Login('web', $user, false)))
        ->not->toThrow(Throwable::class);
});

it('does not crash when the dispatcher has no authenticated user', function () {
    // The flush closure does not touch `auth()` directly — it calls
    // static methods on the resources. Guard against a future edit
    // that added an auth dependency.
    auth()->logout();

    expect(fn () => Event::dispatch(new Logout('web')))
        ->not->toThrow(Throwable::class);
});

// ===========================================================================
// Full behavioral test — pending the four resources
// ===========================================================================

it('flushes each badge-bearing resource\'s cached scope on Logout', function () {
    // Full behavior test: warms the cache on each of the four
    // resources, dispatches Logout, and asserts the cache is cleared.
    // Cannot run until §7B, §7G, §7H, and §7D are generated.
})->skip('Requires the four badge-bearing Filament resources (TransferRequisition, PurchaseOrder, SalesOrder, InTransit).');

it('flushes each badge-bearing resource\'s cached scope on Login', function () {
    // Same as above, dispatched via Login.
})->skip('Requires the four badge-bearing Filament resources (TransferRequisition, PurchaseOrder, SalesOrder, InTransit).');

it('flushes the cached badge count, not just the warehouse scope', function () {
    // The trait's `flushBadgeScope()` resets BOTH `$badgeWarehouseIds`
    // and `$badgeCount`. A regression that only cleared one would let
    // the previous user's count survive into a new session — the exact
    // bug the class docblock warns about.
    //
    // This is asserted through the trait's own test file
    // (`ScopesNavigationBadgesTest::flushBadgeScope clears the cached
    // warehouse set`), which cannot see `$badgeCount` directly either.
    // A true count-reset assertion requires the resources to exist so
    // `getNavigationBadge()` can be called.
})->skip('Requires the four badge-bearing Filament resources.');
