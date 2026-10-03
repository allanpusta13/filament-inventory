<?php

declare(strict_types=1);

use App\Models\DirectTransfer;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\TransferRequisition;
use App\Support\GeneratesReferenceCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Reference-code factory tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §2 reference-code generation contract: format is
 *     `PREFIX-YmdHis-random(100-999)`, hyphenated.
 *   - §2 collision-window note (≈1/900 same-second, same-type): the
 *     factory passes a deterministic `unique()->numberBetween(100, 999)`
 *     random part so seeds fail loudly instead of duplicating.
 *   - §5.9 / §5.13 / §5.15 / §5.17 factory definitions.
 *   - §7B.2 / §7C.2 / §7G.2 / §7H.2 the four reference-code call sites.
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Format contract — every code matches `PREFIX-YmdHis-NNN`
// ---------------------------------------------------------------------------

it('produces a code matching the §2 canonical format', function (string $factoryClass, string $prefix) {
    $record = $factoryClass::new()->create();

    $pattern = '/^'.preg_quote($prefix, '/').'-\d{14}-\d{3}$/';
    expect($record->reference_code)->toMatch($pattern);
})->with([
    'TransferRequisition (TR)' => [TransferRequisition::class, 'TR'],
    'PurchaseOrder (PO)' => [PurchaseOrder::class, 'PO'],
    'SalesOrder (SO)' => [SalesOrder::class, 'SO'],
    'DirectTransfer (DT)' => [DirectTransfer::class, 'DT'],
]);

it('produces a random part in [100, 999]', function (string $factoryClass) {
    $record = $factoryClass::new()->create();
    $parts = explode('-', $record->reference_code);
    $random = (int) end($parts);

    expect($random)->toBeGreaterThanOrEqual(100);
    expect($random)->toBeLessThanOrEqual(999);
})->with([
    TransferRequisition::class,
    PurchaseOrder::class,
    SalesOrder::class,
    DirectTransfer::class,
]);

it('uses the same helper the services use — GeneratesReferenceCodes', function (string $factoryClass, string $prefix) {
    // The factory's random part is deterministic; the helper's default
    // is `random_int(100, 999)`. Both go through the same helper, so
    // the shape is identical. This test asserts the helper itself
    // produces the same format when called without a random override.
    $fromHelper = GeneratesReferenceCodes::generateReferenceCode($prefix);

    expect($fromHelper)->toMatch('/^'.preg_quote($prefix, '/').'-\d{14}-\d{3}$/');
})->with([
    'TransferRequisition (TR)' => [TransferRequisition::class, 'TR'],
    'PurchaseOrder (PO)' => [PurchaseOrder::class, 'PO'],
    'SalesOrder (SO)' => [SalesOrder::class, 'SO'],
    'DirectTransfer (DT)' => [DirectTransfer::class, 'DT'],
]);

// ---------------------------------------------------------------------------
// Uniqueness across a batch — the factory's `unique()` faker call
// ---------------------------------------------------------------------------

it('produces unique codes across a single batch', function (string $factoryClass) {
    // The factory passes `$this->faker->unique()->numberBetween(100, 999)`,
    // so a batch of 20 within the same second must not collide.
    $records = $factoryClass::new()->count(20)->create();

    expect($records->pluck('reference_code')->unique())->toHaveCount(20);
})->with([
    TransferRequisition::class,
    PurchaseOrder::class,
    SalesOrder::class,
    DirectTransfer::class,
]);

it('produces distinct codes for the four document types running in the same second', function () {
    // Prefix disambiguates the type; the random part disambiguates
    // within a type. All four prefixes must coexist.
    $tr = TransferRequisition::factory()->create();
    $po = PurchaseOrder::factory()->create();
    $so = SalesOrder::factory()->create();
    $dt = DirectTransfer::factory()->create();

    $prefixes = collect([$tr, $po, $so, $dt])
        ->pluck('reference_code')
        ->map(fn (string $code) => explode('-', $code)[0])
        ->all();

    expect($prefixes)->toEqualCanonicalizing(['TR', 'PO', 'SO', 'DT']);
});

// ---------------------------------------------------------------------------
// Helper direct-call contract
// ---------------------------------------------------------------------------

it('honors an explicit random override on the helper', function () {
    // The factory relies on this override to make seeds deterministic.
    $code = GeneratesReferenceCodes::generateReferenceCode('TR', 427);

    expect($code)->toMatch('/^TR-\d{14}-427$/');
});

it('falls back to random_int when the override is null', function () {
    $code = GeneratesReferenceCodes::generateReferenceCode('PO', null);
    $parts = explode('-', $code);
    $random = (int) end($parts);

    expect($random)->toBeGreaterThanOrEqual(100);
    expect($random)->toBeLessThanOrEqual(999);
});
