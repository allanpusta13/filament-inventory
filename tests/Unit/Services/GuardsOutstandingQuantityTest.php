<?php

declare(strict_types=1);

use App\Exceptions\OutstandingQuantityExceededException;
use App\Models\PurchaseOrderItem;
use App\Models\SalesOrderItem;
use App\Models\TransferRequisitionItem;
use App\Services\GuardsOutstandingQuantity;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * GuardsOutstandingQuantity contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §6.1 the three guard methods and their boundary semantics.
 *   - §3.8 TransferRequisitionItem::outstandingShippedBaseQty().
 *   - §3.15 PurchaseOrderItem::outstandingBaseQty().
 *   - §3.17 SalesOrderItem::outstandingBaseQty().
 *   - §6.3 OutstandingQuantityExceededException payload contract.
 *   - §24 concurrency matrix: "Two users receive same purchase item"
 *     and "Two users dispatch same sales item" rely on this guard.
 *
 * Deliberately NOT tested here (exercised by the caller-service tests
 * once those files exist):
 *   - The transactional wrapping of the guard (belongs to the service
 *     under test).
 *   - The row-locking discipline (belongs to the service under test).
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->guard = new GuardsOutstandingQuantity();
});

// ---------------------------------------------------------------------------
// Construction / statelessness
// ---------------------------------------------------------------------------

it('can be instantiated without arguments', function () {
    expect(new GuardsOutstandingQuantity())->toBeInstanceOf(GuardsOutstandingQuantity::class);
});

it('is stateless — no properties declared', function () {
    $reflection = new ReflectionClass(GuardsOutstandingQuantity::class);

    expect($reflection->getProperties())->toHaveCount(0);
});

it('defines exactly the three guard methods', function () {
    // §6.1 defines exactly three. A regression that adds a fourth
    // (e.g. a generic `assertNotOver(...)`) would change the guard
    // surface every caller depends on.
    $methods = collect((new ReflectionClass(GuardsOutstandingQuantity::class))->getMethods())
        ->filter(fn ($m) => $m->class === GuardsOutstandingQuantity::class)
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    expect($methods)->toBe([
        'assertPurchaseNotOverReceived',
        'assertSaleNotOverDispatched',
        'assertTransferNotOverShipped',
    ]);
});

// ---------------------------------------------------------------------------
// assertTransferNotOverShipped()
// ---------------------------------------------------------------------------

it('allows a transfer ship quantity under the outstanding balance', function () {
    $item = TransferRequisitionItem::factory()->create([
        'approved_base_qty' => 50,
        'shipped_base_qty' => 10,
    ]);

    // Outstanding = 40; attempting 30 is fine.
    $this->guard->assertTransferNotOverShipped($item, 30);

    expect(true)->toBeTrue(); // no exception
});

it('allows a transfer ship quantity exactly equal to the outstanding balance', function () {
    // Boundary: `==` is legal — a fully-completing operation.
    $item = TransferRequisitionItem::factory()->create([
        'approved_base_qty' => 50,
        'shipped_base_qty' => 10,
    ]);

    // Outstanding = 40; attempting exactly 40 is legal.
    $this->guard->assertTransferNotOverShipped($item, 40);

    expect(true)->toBeTrue();
});

it('rejects a transfer ship quantity above the outstanding balance', function () {
    $item = TransferRequisitionItem::factory()->create([
        'approved_base_qty' => 50,
        'shipped_base_qty' => 10,
    ]);

    // Outstanding = 40; attempting 41 must throw.
    expect(fn () => $this->guard->assertTransferNotOverShipped($item, 41))
        ->toThrow(OutstandingQuantityExceededException::class);
});

it('carries the correct payload when a transfer guard throws', function () {
    $item = TransferRequisitionItem::factory()->create([
        'approved_base_qty' => 50,
        'shipped_base_qty' => 10,
    ]);

    try {
        $this->guard->assertTransferNotOverShipped($item, 99);
        $this->fail('Expected OutstandingQuantityExceededException was not thrown.');
    } catch (OutstandingQuantityExceededException $e) {
        expect($e->itemType)->toBe(TransferRequisitionItem::class);
        expect($e->itemId)->toBe((int) $item->id);
        expect($e->attempted)->toBe(99);
        expect($e->outstanding)->toBe(40);
        expect($e->translationKey())->toBe('errors.outstanding_quantity_exceeded');
        expect($e->context())->toBe([
            'item' => (int) $item->id,
            'attempted' => 99,
            'outstanding' => 40,
        ]);
    }
});

it('rejects a transfer ship against a zero outstanding balance', function () {
    // A fully-shipped item has outstanding = 0; any positive attempt
    // throws.
    $item = TransferRequisitionItem::factory()->create([
        'approved_base_qty' => 50,
        'shipped_base_qty' => 50,
    ]);

    expect(fn () => $this->guard->assertTransferNotOverShipped($item, 1))
        ->toThrow(OutstandingQuantityExceededException::class);
});

it('allows a zero transfer ship quantity against a zero outstanding balance', function () {
    // `0 > 0` is false — a no-op ship is legal (semantically pointless
    // but contractually allowed; the caller is responsible for
    // rejecting empty payloads before reaching the guard).
    $item = TransferRequisitionItem::factory()->create([
        'approved_base_qty' => 50,
        'shipped_base_qty' => 50,
    ]);

    $this->guard->assertTransferNotOverShipped($item, 0);

    expect(true)->toBeTrue();
});

it('treats a null approved_base_qty as zero outstanding', function () {
    // §3.8: outstandingShippedBaseQty() casts null approved to 0.
    // Any positive ship against an un-materialized item throws.
    $item = TransferRequisitionItem::factory()->create([
        'approved_base_qty' => null,
        'shipped_base_qty' => 0,
    ]);

    expect(fn () => $this->guard->assertTransferNotOverShipped($item, 1))
        ->toThrow(OutstandingQuantityExceededException::class);
});

it('preserves base-unit semantics when the item was ordered in a non-base unit', function () {
    // §3.8: outstandingShippedBaseQty() returns base units. A case × 24
    // item with 1 case shipped has outstanding = 48 base units.
    $item = TransferRequisitionItem::factory()->create([
        'approved_unit_ratio' => 24,
        'approved_qty' => 3,
        'approved_base_qty' => 72,
        'shipped_base_qty' => 24,
    ]);

    // Attempting 48 is legal, 49 is not.
    $this->guard->assertTransferNotOverShipped($item, 48);
    expect(true)->toBeTrue();

    expect(fn () => $this->guard->assertTransferNotOverShipped($item, 49))
        ->toThrow(OutstandingQuantityExceededException::class);
});

// ---------------------------------------------------------------------------
// assertPurchaseNotOverReceived()
// ---------------------------------------------------------------------------

it('allows a purchase receive quantity under the outstanding balance', function () {
    $item = PurchaseOrderItem::factory()->create([
        'ordered_base_qty' => 100,
        'received_base_qty' => 30,
    ]);

    $this->guard->assertPurchaseNotOverReceived($item, 60);

    expect(true)->toBeTrue();
});

it('allows a purchase receive quantity exactly equal to the outstanding balance', function () {
    $item = PurchaseOrderItem::factory()->create([
        'ordered_base_qty' => 100,
        'received_base_qty' => 30,
    ]);

    $this->guard->assertPurchaseNotOverReceived($item, 70);

    expect(true)->toBeTrue();
});

it('rejects a purchase receive quantity above the outstanding balance', function () {
    $item = PurchaseOrderItem::factory()->create([
        'ordered_base_qty' => 100,
        'received_base_qty' => 30,
    ]);

    expect(fn () => $this->guard->assertPurchaseNotOverReceived($item, 71))
        ->toThrow(OutstandingQuantityExceededException::class);
});

it('carries the correct payload when a purchase guard throws', function () {
    $item = PurchaseOrderItem::factory()->create([
        'ordered_base_qty' => 100,
        'received_base_qty' => 30,
    ]);

    try {
        $this->guard->assertPurchaseNotOverReceived($item, 999);
        $this->fail('Expected OutstandingQuantityExceededException was not thrown.');
    } catch (OutstandingQuantityExceededException $e) {
        expect($e->itemType)->toBe(PurchaseOrderItem::class);
        expect($e->itemId)->toBe((int) $item->id);
        expect($e->attempted)->toBe(999);
        expect($e->outstanding)->toBe(70);
    }
});

it('rejects a purchase receive against a fully-received item', function () {
    $item = PurchaseOrderItem::factory()->create([
        'ordered_base_qty' => 100,
        'received_base_qty' => 100,
    ]);

    expect(fn () => $this->guard->assertPurchaseNotOverReceived($item, 1))
        ->toThrow(OutstandingQuantityExceededException::class);
});

it('allows a zero purchase receive against a fully-received item', function () {
    $item = PurchaseOrderItem::factory()->create([
        'ordered_base_qty' => 100,
        'received_base_qty' => 100,
    ]);

    $this->guard->assertPurchaseNotOverReceived($item, 0);

    expect(true)->toBeTrue();
});

it('preserves base-unit semantics for purchase receive in a non-base unit', function () {
    $item = PurchaseOrderItem::factory()->create([
        'ordered_unit_ratio' => 24,
        'ordered_qty' => 3,
        'ordered_base_qty' => 72,
        'received_base_qty' => 24,
    ]);

    $this->guard->assertPurchaseNotOverReceived($item, 48);
    expect(true)->toBeTrue();

    expect(fn () => $this->guard->assertPurchaseNotOverReceived($item, 49))
        ->toThrow(OutstandingQuantityExceededException::class);
});

// ---------------------------------------------------------------------------
// assertSaleNotOverDispatched()
// ---------------------------------------------------------------------------

it('allows a sales dispatch quantity under the outstanding balance', function () {
    $item = SalesOrderItem::factory()->create([
        'base_qty' => 60,
        'dispatched_base_qty' => 20,
    ]);

    $this->guard->assertSaleNotOverDispatched($item, 30);

    expect(true)->toBeTrue();
});

it('allows a sales dispatch quantity exactly equal to the outstanding balance', function () {
    $item = SalesOrderItem::factory()->create([
        'base_qty' => 60,
        'dispatched_base_qty' => 20,
    ]);

    $this->guard->assertSaleNotOverDispatched($item, 40);

    expect(true)->toBeTrue();
});

it('rejects a sales dispatch quantity above the outstanding balance', function () {
    $item = SalesOrderItem::factory()->create([
        'base_qty' => 60,
        'dispatched_base_qty' => 20,
    ]);

    expect(fn () => $this->guard->assertSaleNotOverDispatched($item, 41))
        ->toThrow(OutstandingQuantityExceededException::class);
});

it('carries the correct payload when a sales guard throws', function () {
    $item = SalesOrderItem::factory()->create([
        'base_qty' => 60,
        'dispatched_base_qty' => 20,
    ]);

    try {
        $this->guard->assertSaleNotOverDispatched($item, 999);
        $this->fail('Expected OutstandingQuantityExceededException was not thrown.');
    } catch (OutstandingQuantityExceededException $e) {
        expect($e->itemType)->toBe(SalesOrderItem::class);
        expect($e->itemId)->toBe((int) $item->id);
        expect($e->attempted)->toBe(999);
        expect($e->outstanding)->toBe(40);
    }
});

it('rejects a sales dispatch against a fully-dispatched item', function () {
    $item = SalesOrderItem::factory()->create([
        'base_qty' => 60,
        'dispatched_base_qty' => 60,
    ]);

    expect(fn () => $this->guard->assertSaleNotOverDispatched($item, 1))
        ->toThrow(OutstandingQuantityExceededException::class);
});

it('allows a zero sales dispatch against a fully-dispatched item', function () {
    $item = SalesOrderItem::factory()->create([
        'base_qty' => 60,
        'dispatched_base_qty' => 60,
    ]);

    $this->guard->assertSaleNotOverDispatched($item, 0);

    expect(true)->toBeTrue();
});

it('preserves base-unit semantics for sales dispatch in a non-base unit', function () {
    $item = SalesOrderItem::factory()->create([
        'unit_ratio' => 24,
        'qty' => 3,
        'base_qty' => 72,
        'dispatched_base_qty' => 24,
    ]);

    $this->guard->assertSaleNotOverDispatched($item, 48);
    expect(true)->toBeTrue();

    expect(fn () => $this->guard->assertSaleNotOverDispatched($item, 49))
        ->toThrow(OutstandingQuantityExceededException::class);
});

// ---------------------------------------------------------------------------
// Cross-method consistency
// ---------------------------------------------------------------------------

it('uses the item\'s own outstanding helper, not a shared computation', function () {
    // The three guards must delegate to their respective item methods:
    //   TransferRequisitionItem::outstandingShippedBaseQty()
    //   PurchaseOrderItem::outstandingBaseQty()
    //   SalesOrderItem::outstandingBaseQty()
    // This test proves the guard honors each item's own accounting by
    // constructing the same numbers on each type and asserting the same
    // outstanding value is reflected in the exception.
    $transfer = TransferRequisitionItem::factory()->create([
        'approved_base_qty' => 100,
        'shipped_base_qty' => 30,
    ]);
    $purchase = PurchaseOrderItem::factory()->create([
        'ordered_base_qty' => 100,
        'received_base_qty' => 30,
    ]);
    $sale = SalesOrderItem::factory()->create([
        'base_qty' => 100,
        'dispatched_base_qty' => 30,
    ]);

    foreach ([
        [$transfer, fn () => $this->guard->assertTransferNotOverShipped($transfer, 999)],
        [$purchase, fn () => $this->guard->assertPurchaseNotOverReceived($purchase, 999)],
        [$sale,     fn () => $this->guard->assertSaleNotOverDispatched($sale, 999)],
    ] as [$item, $attempt]) {
        try {
            $attempt();
            $this->fail('Expected OutstandingQuantityExceededException was not thrown.');
        } catch (OutstandingQuantityExceededException $e) {
            expect($e->outstanding)->toBe(70);
        }
    }
});

it('does not mutate the item or perform any database writes', function () {
    // The guard is a pure read — it must not touch the item's state or
    // issue writes. Asserting the item's attributes are unchanged and
    // the DB row is unchanged.
    $item = PurchaseOrderItem::factory()->create([
        'ordered_base_qty' => 100,
        'received_base_qty' => 30,
    ]);

    $attributesBefore = $item->getAttributes();
    $updatedAtBefore = $item->fresh()->updated_at;

    try {
        $this->guard->assertPurchaseNotOverReceived($item, 999);
    } catch (OutstandingQuantityExceededException) {
        // expected
    }

    expect($item->getAttributes())->toBe($attributesBefore);
    expect($item->fresh()->updated_at->equalTo($updatedAtBefore))->toBeTrue();
});

it('produces an exception catchable through the DomainErrorException base', function () {
    // §6.3: every typed domain exception extends DomainErrorException.
    // The Livewire ScanForm (§21.1) catch block relies on this.
    $item = PurchaseOrderItem::factory()->create([
        'ordered_base_qty' => 10,
        'received_base_qty' => 10,
    ]);

    $caught = false;

    try {
        $this->guard->assertPurchaseNotOverReceived($item, 1);
    } catch (App\Exceptions\DomainErrorException $e) {
        $caught = true;
        expect($e)->toBeInstanceOf(OutstandingQuantityExceededException::class);
    }

    expect($caught)->toBeTrue();
});
