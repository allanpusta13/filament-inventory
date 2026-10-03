<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * §23.4 — Route Wiring.
 *
 * The two STN endpoints must exist, be named, and carry the correct
 * middleware — auth + signed for `stn.scan`, auth only for `stn.print`.
 */
it('registers the stn.print route', function () {
    expect(Route::has('stn.print'))->toBeTrue();
});

it('registers the stn.scan route', function () {
    expect(Route::has('stn.scan'))->toBeTrue();
});

it('protects stn.print with auth middleware', function () {
    $routes = collect(Route::getRoutes())->keyBy(fn ($r) => $r->getName());

    expect($routes)->toHaveKey('stn.print');
    expect($routes['stn.print']->gatherMiddleware())->toContain('auth');
});

it('protects stn.scan with both auth and signed middleware', function () {
    $routes = collect(Route::getRoutes())->keyBy(fn ($r) => $r->getName());

    expect($routes)->toHaveKey('stn.scan');
    expect($routes['stn.scan']->gatherMiddleware())->toContain('auth');
    expect($routes['stn.scan']->gatherMiddleware())->toContain('signed');
});

it('does not expose stn.print through the signed middleware', function () {
    // The print endpoint is not signed — only the scan endpoint is.
    $routes = collect(Route::getRoutes())->keyBy(fn ($r) => $r->getName());

    expect($routes['stn.print']->gatherMiddleware())->not->toContain('signed');
});

it('registers the Filament admin panel route', function () {
    // The Filament panel registers at /admin by default.
    $routes = Route::getRoutes();
    $filamentRoutes = collect($routes)
        ->filter(fn ($r) => str_starts_with($r->uri(), 'admin'))
        ->count();

    expect($filamentRoutes)->toBeGreaterThan(0);
});

it('generates a signed scan URL with a 7-day expiry', function () {
    $requisition = App\Models\TransferRequisition::factory()->create();

    $signedUrl = Illuminate\Support\Facades\URL::temporarySignedRoute(
        'stn.scan',
        now()->addDays(7),
        ['transferRequisition' => $requisition->getKey()],
    );

    expect($signedUrl)->toContain('signature=');
    expect($signedUrl)->toContain('expires=');

    // Extract the expires parameter and confirm it's ~7 days out.
    parse_str(parse_url($signedUrl, PHP_URL_QUERY) ?? '', $query);
    expect(isset($query['expires']))->toBeTrue();

    $expiresAt = Carbon\Carbon::createFromTimestamp((int) $query['expires']);
    $expected = now()->addDays(7);

    // Within a minute either way — accounts for test execution time.
    expect(abs($expiresAt->diffInSeconds($expected)))->toBeLessThan(60);
});
