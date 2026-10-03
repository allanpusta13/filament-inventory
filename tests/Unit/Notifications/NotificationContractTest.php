<?php

declare(strict_types=1);

use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Notifications\InventoryBelowReorderPointNotification;
use App\Notifications\LossRecordedNotification;
use App\Notifications\PurchaseOrderCancelledNotification;
use App\Notifications\PurchaseOrderReceivedNotification;
use App\Notifications\SalesOrderDispatchedNotification;
use App\Notifications\TransferCancelledNotification;
use App\Notifications\TransferConfirmedNotification;
use App\Notifications\TransferDispatchedNotification;
use App\Notifications\TransferReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;

/**
 * Domain notification contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Consolidation rationale: eight of the nine notification classes are
 * thin — a constructor with readonly properties, a `via()` returning
 * `['database']`, and a `toDatabase()` that interpolates a translation
 * key. The two model-resolving notifications
 * (LossRecordedNotification, InventoryBelowReorderPointNotification)
 * carry their own dedicated tests. Everything else is covered by
 * datasets in one place.
 *
 * Blueprint anchors exercised:
 *   - §22.3b notification class contract: extends Notification,
 *     database-only channel, `toDatabase()` returns a serializable
 *     array carrying the event ids.
 *   - §0A.8: message bodies resolve through `__()`.
 *   - §0A.2a: every `notifications.<event>.body` key must exist for
 *     every configured locale.
 *   - §0A.15 test 4: message strings must not be raw-key fallbacks.
 *
 * ⚠ PurchaseOrderCancelledNotification is a gap-fill and resolves
 * `notifications.purchase_order_cancelled.body` — a translation key
 * that does NOT exist in the §0A.2a catalogue. The dedicated test for
 * this class will FAIL until that key is added to
 * `lang/{locale}/notifications.php` for every locale. That is the
 * intended forcing function.
 */
uses(RefreshDatabase::class);

// ===========================================================================
// Shared contract — channel, readonly, message resolution
// ===========================================================================

it('extends the base Notification class', function (string $class, array $args) {
    expect(new $class(...$args))->toBeInstanceOf(Notification::class);
})->with([
    'TransferConfirmed' => [TransferConfirmedNotification::class,          [1, 'TR-TEST']],
    'TransferCancelled' => [TransferCancelledNotification::class,          [2, 'TR-TEST']],
    'TransferDispatched' => [TransferDispatchedNotification::class,         [3, 'TR-TEST']],
    'TransferReceived' => [TransferReceivedNotification::class,           [4, 'TR-TEST']],
    'LossRecorded' => [LossRecordedNotification::class,               [5]],
    'PurchaseOrderReceived' => [PurchaseOrderReceivedNotification::class,      [6, 'PO-TEST']],
    'PurchaseOrderCancelled' => [PurchaseOrderCancelledNotification::class,     [7, 'PO-TEST']],
    'SalesOrderDispatched' => [SalesOrderDispatchedNotification::class,       [8, 'SO-TEST']],
    'InventoryBelowReorderPoint' => [InventoryBelowReorderPointNotification::class, [9, 10]],
]);

it('declares the database channel only', function (string $class, array $args) {
    // §22.3b: v1 is database-only. A regression that added mail or
    // broadcast without owner approval surfaces here.
    $notification = new $class(...$args);

    expect($notification->via(new stdClass()))->toBe(['database']);
})->with([
    'TransferConfirmed' => [TransferConfirmedNotification::class,          [1, 'TR-TEST']],
    'TransferCancelled' => [TransferCancelledNotification::class,          [2, 'TR-TEST']],
    'TransferDispatched' => [TransferDispatchedNotification::class,         [3, 'TR-TEST']],
    'TransferReceived' => [TransferReceivedNotification::class,           [4, 'TR-TEST']],
    'LossRecorded' => [LossRecordedNotification::class,               [5]],
    'PurchaseOrderReceived' => [PurchaseOrderReceivedNotification::class,      [6, 'PO-TEST']],
    'PurchaseOrderCancelled' => [PurchaseOrderCancelledNotification::class,     [7, 'PO-TEST']],
    'SalesOrderDispatched' => [SalesOrderDispatchedNotification::class,       [8, 'SO-TEST']],
    'InventoryBelowReorderPoint' => [InventoryBelowReorderPointNotification::class, [9, 10]],
]);

it('declares every constructor property as readonly', function (string $class) {
    // §22.3b: constructor properties are readonly so a queued payload
    // cannot be mutated after construction.
    $reflection = new ReflectionClass($class);

    foreach ($reflection->getConstructor()->getParameters() as $param) {
        $property = $reflection->getProperty($param->getName());
        expect($property->isReadOnly())->toBeTrue();
    }
})->with([
    TransferConfirmedNotification::class,
    TransferCancelledNotification::class,
    TransferDispatchedNotification::class,
    TransferReceivedNotification::class,
    LossRecordedNotification::class,
    PurchaseOrderReceivedNotification::class,
    PurchaseOrderCancelledNotification::class,
    SalesOrderDispatchedNotification::class,
    InventoryBelowReorderPointNotification::class,
]);

it('returns a toDatabase() payload carrying a message key', function (string $class, array $args) {
    // §22.3b: every notification serializes to an array with at least
    // a `message` key.
    $payload = (new $class(...$args))->toDatabase(new stdClass());

    expect($payload)->toBeArray();
    expect($payload)->toHaveKey('message');
    expect($payload['message'])->toBeString()->not->toBe('');
})->with([
    'TransferConfirmed' => [TransferConfirmedNotification::class,          [1, 'TR-TEST']],
    'TransferCancelled' => [TransferCancelledNotification::class,          [2, 'TR-TEST']],
    'TransferDispatched' => [TransferDispatchedNotification::class,         [3, 'TR-TEST']],
    'TransferReceived' => [TransferReceivedNotification::class,           [4, 'TR-TEST']],
    'LossRecorded' => [LossRecordedNotification::class,               [5]],
    'PurchaseOrderReceived' => [PurchaseOrderReceivedNotification::class,      [6, 'PO-TEST']],
    'SalesOrderDispatched' => [SalesOrderDispatchedNotification::class,       [8, 'SO-TEST']],
    'InventoryBelowReorderPoint' => [InventoryBelowReorderPointNotification::class, [9, 10]],
]);

it('resolves the message through __() — not a raw-key fallback', function (string $class, array $args, string $key) {
    // §0A.15 test 4: a translated string must not equal its key.
    $payload = (new $class(...$args))->toDatabase(new stdClass());

    expect($payload['message'])->not->toBe("notifications.{$key}.body");
    expect($payload['message'])->not->toBe('');
})->with([
    'TransferConfirmed' => [TransferConfirmedNotification::class,          [1, 'TR-TEST'], 'transfer_confirmed'],
    'TransferCancelled' => [TransferCancelledNotification::class,          [2, 'TR-TEST'], 'transfer_cancelled'],
    'TransferDispatched' => [TransferDispatchedNotification::class,         [3, 'TR-TEST'], 'transfer_dispatched'],
    'TransferReceived' => [TransferReceivedNotification::class,           [4, 'TR-TEST'], 'transfer_received'],
    'LossRecorded' => [LossRecordedNotification::class,               [5],            'loss_recorded'],
    'PurchaseOrderReceived' => [PurchaseOrderReceivedNotification::class,      [6, 'PO-TEST'], 'purchase_order_received'],
    'SalesOrderDispatched' => [SalesOrderDispatchedNotification::class,       [8, 'SO-TEST'], 'sales_order_dispatched'],
    'InventoryBelowReorderPoint' => [InventoryBelowReorderPointNotification::class, [9, 10],        'inventory_below_reorder_point'],
]);

// ===========================================================================
// Reference-code interpolation
// ===========================================================================

it('interpolates the reference code into the message body', function (string $class, string $key) {
    $payload = (new $class(42, 'TR-20260115120000-100'))->toDatabase(new stdClass());

    expect($payload['message'])->toContain('TR-20260115120000-100');
})->with([
    'TransferConfirmed' => [TransferConfirmedNotification::class,      'transfer_confirmed'],
    'TransferCancelled' => [TransferCancelledNotification::class,      'transfer_cancelled'],
    'TransferDispatched' => [TransferDispatchedNotification::class,     'transfer_dispatched'],
    'TransferReceived' => [TransferReceivedNotification::class,       'transfer_received'],
    'PurchaseOrderReceived' => [PurchaseOrderReceivedNotification::class,  'purchase_order_received'],
    'PurchaseOrderCancelled' => [PurchaseOrderCancelledNotification::class, 'purchase_order_cancelled'],
    'SalesOrderDispatched' => [SalesOrderDispatchedNotification::class,   'sales_order_dispatched'],
]);

it('falls back to the document id when no reference code is provided', function (string $class, int $id) {
    // The nullable $referenceCode defaults to the id, so the message is
    // never blank.
    $payload = (new $class($id))->toDatabase(new stdClass());

    expect($payload['message'])->toContain((string) $id);
})->with([
    'TransferConfirmed' => [TransferConfirmedNotification::class,      'TR-X'],
    'TransferCancelled' => [TransferCancelledNotification::class,      'TR-X'],
    'TransferDispatched' => [TransferDispatchedNotification::class,     'TR-X'],
    'TransferReceived' => [TransferReceivedNotification::class,       'TR-X'],
    'PurchaseOrderReceived' => [PurchaseOrderReceivedNotification::class,  'PO-X'],
    'PurchaseOrderCancelled' => [PurchaseOrderCancelledNotification::class, 'PO-X'],
    'SalesOrderDispatched' => [SalesOrderDispatchedNotification::class,   'SO-X'],
]);

// ===========================================================================
// Payload id keys — the events carry ids, the payload reflects them
// ===========================================================================

it('carries the source document id in the payload', function (string $class, int $id, string $idKey) {
    $payload = (new $class($id, null))->toDatabase(new stdClass());

    expect($payload)->toHaveKey($idKey);
    expect($payload[$idKey])->toBe($id);
})->with([
    'TransferConfirmed' => [TransferConfirmedNotification::class,      'requisition_id'],
    'TransferCancelled' => [TransferCancelledNotification::class,      'requisition_id'],
    'TransferDispatched' => [TransferDispatchedNotification::class,     'requisition_id'],
    'TransferReceived' => [TransferReceivedNotification::class,       'requisition_id'],
    'PurchaseOrderReceived' => [PurchaseOrderReceivedNotification::class,  'purchase_order_id'],
    'PurchaseOrderCancelled' => [PurchaseOrderCancelledNotification::class, 'purchase_order_id'],
    'SalesOrderDispatched' => [SalesOrderDispatchedNotification::class,   'sales_order_id'],
]);

// ===========================================================================
// LossRecordedNotification — model-resolving
// ===========================================================================

it('resolves qty from the loss ledger when computing the message', function () {
    $loss = LossLedger::factory()->create([
        'lost_base_qty' => 7,
        'damaged_base_qty' => 3,
    ]);

    $payload = (new LossRecordedNotification($loss->id))->toDatabase(new stdClass());

    // lost + damaged = 10
    expect($payload['message'])->toContain('10');
    expect($payload['message'])->toContain($loss->productVariant->sku);
});

it('degrades gracefully when the loss ledger is missing', function () {
    // Defensive: a queued payload that outlives the ledger row (force
    // delete) must not throw. The qty falls back to 0 and the sku
    // falls back to the ledger id.
    $payload = (new LossRecordedNotification(999999))->toDatabase(new stdClass());

    expect($payload)->toHaveKey('message');
    expect($payload['message'])->toBeString()->not->toBe('');
});

it('carries the loss ledger id in the payload', function () {
    $loss = LossLedger::factory()->create();

    $payload = (new LossRecordedNotification($loss->id))->toDatabase(new stdClass());

    expect($payload)->toHaveKey('loss_ledger_id');
    expect($payload['loss_ledger_id'])->toBe($loss->id);
});

// ===========================================================================
// InventoryBelowReorderPointNotification — model-resolving
// ===========================================================================

it('resolves the sku and warehouse name when computing the message', function () {
    $variant = ProductVariant::factory()->create(['sku' => 'SKU-0001-AA']);
    $warehouse = Warehouse::factory()->create(['name' => 'North Warehouse']);

    $payload = (new InventoryBelowReorderPointNotification($variant->id, $warehouse->id))
        ->toDatabase(new stdClass());

    expect($payload['message'])->toContain('SKU-0001-AA');
    expect($payload['message'])->toContain('North Warehouse');
});

it('degrades gracefully when the variant or warehouse is missing', function () {
    $payload = (new InventoryBelowReorderPointNotification(999999, 888888))
        ->toDatabase(new stdClass());

    expect($payload)->toHaveKey('message');
    expect($payload['message'])->toBeString()->not->toBe('');
});

it('carries the variant + warehouse pair in the payload', function () {
    $payload = (new InventoryBelowReorderPointNotification(7, 3))->toDatabase(new stdClass());

    expect($payload['product_variant_id'])->toBe(7);
    expect($payload['warehouse_id'])->toBe(3);
});

// ===========================================================================
// PurchaseOrderCancelledNotification — gap-fill flag
// ===========================================================================

it('resolves the purchase_order_cancelled.body translation key (gap-fill — will fail until the key is added)', function () {
    // ⚠ GAP-FILL: `notifications.purchase_order_cancelled.body` is NOT
    // in the §0A.2a catalogue. This test asserts the key resolves to a
    // translated string, not the raw key. Until the key is added to
    // `lang/{locale}/notifications.php` for every locale, this test
    // fails with `notifications.purchase_order_cancelled.body` as the
    // message — the intended forcing function.
    $payload = (new PurchaseOrderCancelledNotification(1, 'PO-TEST'))
        ->toDatabase(new stdClass());

    expect($payload['message'])->not->toBe('notifications.purchase_order_cancelled.body');
    expect($payload['message'])->toContain('PO-TEST');
});
