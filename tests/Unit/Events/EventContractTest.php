<?php

declare(strict_types=1);

use App\Events\InventoryBelowReorderPoint;
use App\Events\LossRecorded;
use App\Events\PurchaseOrderCancelled;
use App\Events\PurchaseOrderReceived;
use App\Events\SalesOrderDispatched;
use App\Events\TransferCancelled;
use App\Events\TransferConfirmed;
use App\Events\TransferDispatched;
use App\Events\TransferReceived;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Domain event contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Consolidation rationale: the nine §22.1 event classes are extremely
 * thin — one or two readonly promoted properties plus
 * ShouldDispatchAfterCommit and three traits. Per-file tests would be
 * nine near-identical files. Pest datasets cover the same contract in
 * one place. The listener tests (which have nontrivial query logic) are
 * per-file and live in tests/Unit/Listeners/.
 *
 * Blueprint anchors exercised:
 *   - §22.1a event class contract: ShouldDispatchAfterCommit +
 *     Dispatchable / InteractsWithSockets / SerializesModels.
 *   - §22.2 event timing: after-commit delivery is guaranteed by the
 *     marker interface; listeners wired in §17.4 EventServiceProvider.
 *   - §22.1 minimal payload contract: header ids for lifecycle events,
 *     created ledger id for loss, variant + warehouse pair for the
 *     reorder-point alert.
 *
 * PurchaseOrderCancelled is a gap-fill: §22.1 does not list it, but
 * §6.4 fires it. See the class docblock.
 */

// ---------------------------------------------------------------------------
// Shared contract — every event implements ShouldDispatchAfterCommit
// ---------------------------------------------------------------------------

it('implements ShouldDispatchAfterCommit', function (string $eventClass, array $payload) {
    // §22.2: every domain event fires inside a transaction and is
    // released only after commit. The marker interface is what the
    // framework keys on — a regression that dropped it would silently
    // dispatch on rollback.
    $event = new $eventClass(...$payload);

    expect($event)->toBeInstanceOf(ShouldDispatchAfterCommit::class);
})->with([
    'TransferConfirmed' => [TransferConfirmed::class, ['requisitionId' => 1]],
    'TransferCancelled' => [TransferCancelled::class, ['requisitionId' => 2]],
    'TransferDispatched' => [TransferDispatched::class, ['requisitionId' => 3]],
    'TransferReceived' => [TransferReceived::class, ['requisitionId' => 4]],
    'LossRecorded' => [LossRecorded::class, ['lossLedgerId' => 5]],
    'PurchaseOrderReceived' => [PurchaseOrderReceived::class, ['purchaseOrderId' => 6]],
    'PurchaseOrderCancelled' => [PurchaseOrderCancelled::class, ['purchaseOrderId' => 7]],
    'SalesOrderDispatched' => [SalesOrderDispatched::class, ['salesOrderId' => 8]],
    'InventoryBelowReorderPoint' => [InventoryBelowReorderPoint::class, ['productVariantId' => 9, 'warehouseId' => 10]],
]);

// ---------------------------------------------------------------------------
// Shared contract — the three standard traits
// ---------------------------------------------------------------------------

it('uses Dispatchable, InteractsWithSockets, and SerializesModels', function (string $eventClass, array $payload) {
    // §22.1a: all three traits are required for the standard Laravel
    // event lifecycle — dispatch helper, broadcasting hooks, and queue
    // serialization.
    $traits = class_uses_recursive($eventClass);

    expect($traits)->toHaveKey(Dispatchable::class);
    expect($traits)->toHaveKey(Illuminate\Broadcasting\InteractsWithSockets::class);
    expect($traits)->toHaveKey(SerializesModels::class);
})->with([
    'TransferConfirmed' => [TransferConfirmed::class, ['requisitionId' => 1]],
    'TransferCancelled' => [TransferCancelled::class, ['requisitionId' => 2]],
    'TransferDispatched' => [TransferDispatched::class, ['requisitionId' => 3]],
    'TransferReceived' => [TransferReceived::class, ['requisitionId' => 4]],
    'LossRecorded' => [LossRecorded::class, ['lossLedgerId' => 5]],
    'PurchaseOrderReceived' => [PurchaseOrderReceived::class, ['purchaseOrderId' => 6]],
    'PurchaseOrderCancelled' => [PurchaseOrderCancelled::class, ['purchaseOrderId' => 7]],
    'SalesOrderDispatched' => [SalesOrderDispatched::class, ['salesOrderId' => 8]],
    'InventoryBelowReorderPoint' => [InventoryBelowReorderPoint::class, ['productVariantId' => 9, 'warehouseId' => 10]],
]);

// ---------------------------------------------------------------------------
// Payload contract — each event carries the ids §22.1a specifies
// ---------------------------------------------------------------------------

it('carries the requisition id', function (string $eventClass) {
    $event = new $eventClass(42);

    expect($event->requisitionId)->toBe(42);
})->with([
    TransferConfirmed::class,
    TransferCancelled::class,
    TransferDispatched::class,
    TransferReceived::class,
]);

it('carries the loss ledger id', function () {
    $event = new LossRecorded(42);
    expect($event->lossLedgerId)->toBe(42);
});

it('carries the purchase order id', function (string $eventClass) {
    $event = new $eventClass(42);
    expect($event->purchaseOrderId)->toBe(42);
})->with([
    PurchaseOrderReceived::class,
    PurchaseOrderCancelled::class,
]);

it('carries the sales order id', function () {
    $event = new SalesOrderDispatched(42);
    expect($event->salesOrderId)->toBe(42);
});

it('carries both variant and warehouse ids for the reorder-point alert', function () {
    $event = new InventoryBelowReorderPoint(7, 3);

    expect($event->productVariantId)->toBe(7);
    expect($event->warehouseId)->toBe(3);
});

// ---------------------------------------------------------------------------
// Payload properties are readonly
// ---------------------------------------------------------------------------

it('declares every payload property as readonly', function (string $eventClass, array $payload) {
    // §22.1a: payload properties are readonly, so an event cannot be
    // mutated mid-flight (after dispatch, after serialization, etc.).
    $reflection = new ReflectionClass($eventClass);
    $constructor = $reflection->getConstructor();

    foreach ($constructor->getParameters() as $param) {
        $property = $reflection->getProperty($param->getName());
        expect($property->isReadOnly())->toBeTrue();
    }
})->with([
    'TransferConfirmed' => [TransferConfirmed::class, ['requisitionId' => 1]],
    'TransferCancelled' => [TransferCancelled::class, ['requisitionId' => 2]],
    'TransferDispatched' => [TransferDispatched::class, ['requisitionId' => 3]],
    'TransferReceived' => [TransferReceived::class, ['requisitionId' => 4]],
    'LossRecorded' => [LossRecorded::class, ['lossLedgerId' => 5]],
    'PurchaseOrderReceived' => [PurchaseOrderReceived::class, ['purchaseOrderId' => 6]],
    'PurchaseOrderCancelled' => [PurchaseOrderCancelled::class, ['purchaseOrderId' => 7]],
    'SalesOrderDispatched' => [SalesOrderDispatched::class, ['salesOrderId' => 8]],
    'InventoryBelowReorderPoint' => [InventoryBelowReorderPoint::class, ['productVariantId' => 9, 'warehouseId' => 10]],
]);

// ---------------------------------------------------------------------------
// Event name surface — the class name is the stable identifier
// ---------------------------------------------------------------------------

it('declares exactly one constructor argument per single-id event', function (string $eventClass) {
    $reflection = new ReflectionClass($eventClass);
    expect($reflection->getConstructor()->getNumberOfParameters())->toBe(1);
})->with([
    TransferConfirmed::class,
    TransferCancelled::class,
    TransferDispatched::class,
    TransferReceived::class,
    LossRecorded::class,
    PurchaseOrderReceived::class,
    PurchaseOrderCancelled::class,
    SalesOrderDispatched::class,
]);

it('declares exactly two constructor arguments for the reorder-point alert', function () {
    $reflection = new ReflectionClass(InventoryBelowReorderPoint::class);
    expect($reflection->getConstructor()->getNumberOfParameters())->toBe(2);
});
