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
use App\Listeners\NotifyInventoryBelowReorderPoint;
use App\Listeners\NotifyLossRecorded;
use App\Listeners\NotifyPurchaseOrderCancelled;
use App\Listeners\NotifyPurchaseOrderReceived;
use App\Listeners\NotifySalesOrderDispatched;
use App\Listeners\NotifyTransferCancelled;
use App\Listeners\NotifyTransferConfirmed;
use App\Listeners\NotifyTransferDispatched;
use App\Listeners\NotifyTransferReceived;
use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\TransferRequisition;
use App\Models\User;
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
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

/**
 * Listener recipient-scope tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Consolidation rationale: all nine §22.3a listeners follow the same
 * shape — re-query the parent from the event, build a recipient query
 * (admin + warehouse-scoped staff, with auditors included only for
 * loss events), and dispatch a single notification class. Per-file
 * tests would be nine near-identical files. A single file with
 * `describe()` blocks keeps the recipient-scope contract readable in
 * one place, which is the load-bearing assertion the blueprint's A8
 * depends on.
 *
 * Blueprint anchors exercised:
 *   - §22.3a listener class contract: ShouldQueue, re-query the parent,
 *     scope recipients by role + warehouse assignment, never mutate
 *     stock.
 *   - §22.4: notification sends are queued.
 *   - §22.3a per-listener recipient sets:
 *       * transfer_confirmed    → admins + staff at either endpoint
 *       * transfer_cancelled    → admins + staff at source
 *       * transfer_dispatched   → admins + staff at destination
 *       * transfer_received     → admins + staff at either endpoint
 *       * loss_recorded         → admins + auditors + staff at loss warehouse
 *       * purchase_order_received  → admins + staff at order warehouse
 *       * purchase_order_cancelled → admins + staff at order warehouse (gap-fill)
 *       * sales_order_dispatched   → admins + staff at order warehouse
 *       * inventory_below_reorder_point → admins + staff at warehouse
 *   - BranchManager interim: not granted a distinct recipient tier;
 *     included only via the assigned-warehouse path (same as
 *     WarehouseStaff).
 *
 * Deliberately NOT tested here:
 *   - The queue contract for each listener (queued vs sync) is asserted
 *     as a dataset over all nine classes below.
 *   - "Does not mutate stock" — listeners only query; asserted
 *     structurally by their method signatures (handle() has no DB
 *     writes for stock). A behavioral assertion would test framework
 *     behavior, not the listener.
 */
uses(RefreshDatabase::class);

// ===========================================================================
// Fixture helpers
// ===========================================================================

/**
 * Build a transfer-requisition fixture with from/to warehouses and
 * four role representatives:
 *   - admin: any role, no warehouse assignment needed
 *   - fromStaff: WarehouseStaff assigned to the source warehouse
 *   - toStaff:   WarehouseStaff assigned to the destination warehouse
 *   - otherStaff: WarehouseStaff assigned to an unrelated warehouse
 *   - auditor: Auditor assigned to the source warehouse (should be
 *     excluded except for loss events)
 *
 * @return array{
 *     requisition: TransferRequisition,
 *     admin: User,
 *     fromStaff: User,
 *     toStaff: User,
 *     otherStaff: User,
 *     auditor: User,
 * }
 */
function makeRequisitionRecipients(): array
{
    $from = Warehouse::factory()->create();
    $to = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();

    $admin = User::factory()->admin()->create();
    $fromStaff = User::factory()->create();
    $fromStaff->warehouses()->attach($from->id);
    $toStaff = User::factory()->create();
    $toStaff->warehouses()->attach($to->id);
    $otherStaff = User::factory()->create();
    $otherStaff->warehouses()->attach($other->id);
    $auditor = User::factory()->auditor()->create();
    $auditor->warehouses()->attach($from->id);

    $requisition = TransferRequisition::factory()->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
    ]);

    return compact('requisition', 'admin', 'fromStaff', 'toStaff', 'otherStaff', 'auditor');
}

/**
 * Build a warehouse-owning fixture for PO/SO/reorder-point listeners.
 *
 * @return array{warehouse: Warehouse, admin: User, staff: User, otherStaff: User, auditor: User}
 */
function makeWarehouseRecipients(): array
{
    $warehouse = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();

    $admin = User::factory()->admin()->create();
    $staff = User::factory()->create();
    $staff->warehouses()->attach($warehouse->id);
    $otherStaff = User::factory()->create();
    $otherStaff->warehouses()->attach($other->id);
    $auditor = User::factory()->auditor()->create();
    $auditor->warehouses()->attach($warehouse->id);

    return compact('warehouse', 'admin', 'staff', 'otherStaff', 'auditor');
}

// ===========================================================================
// All nine listeners — shared contract
// ===========================================================================

it('implements ShouldQueue', function (string $listenerClass) {
    // §22.4: notification sends are queued; stock mutations remain
    // synchronous. Every listener in §22.3a is queued.
    expect(new $listenerClass())->toBeInstanceOf(ShouldQueue::class);
})->with([
    NotifyTransferConfirmed::class,
    NotifyTransferCancelled::class,
    NotifyTransferDispatched::class,
    NotifyTransferReceived::class,
    NotifyLossRecorded::class,
    NotifyPurchaseOrderReceived::class,
    NotifyPurchaseOrderCancelled::class,
    NotifySalesOrderDispatched::class,
    NotifyInventoryBelowReorderPoint::class,
]);

it('declares exactly one handle() method', function (string $listenerClass) {
    $methods = collect((new ReflectionClass($listenerClass))->getMethods())
        ->filter(fn ($m) => $m->class === $listenerClass)
        ->pluck('name')
        ->all();

    expect($methods)->toBe(['handle']);
})->with([
    NotifyTransferConfirmed::class,
    NotifyTransferCancelled::class,
    NotifyTransferDispatched::class,
    NotifyTransferReceived::class,
    NotifyLossRecorded::class,
    NotifyPurchaseOrderReceived::class,
    NotifyPurchaseOrderCancelled::class,
    NotifySalesOrderDispatched::class,
    NotifyInventoryBelowReorderPoint::class,
]);

// ===========================================================================
// NotifyTransferConfirmed
// ===========================================================================

describe('NotifyTransferConfirmed', function () {
    it('notifies admins and staff at either endpoint', function () {
        Notification::fake();
        $fx = makeRequisitionRecipients();

        (new NotifyTransferConfirmed())->handle(new TransferConfirmed($fx['requisition']->id));

        Notification::assertSentTo($fx['admin'], TransferConfirmedNotification::class);
        Notification::assertSentTo($fx['fromStaff'], TransferConfirmedNotification::class);
        Notification::assertSentTo($fx['toStaff'], TransferConfirmedNotification::class);
    });

    it('does not notify staff at unrelated warehouses', function () {
        Notification::fake();
        $fx = makeRequisitionRecipients();

        (new NotifyTransferConfirmed())->handle(new TransferConfirmed($fx['requisition']->id));

        Notification::assertNotSentTo($fx['otherStaff'], TransferConfirmedNotification::class);
    });

    it('does not notify auditors even when warehouse-assigned', function () {
        Notification::fake();
        $fx = makeRequisitionRecipients();

        (new NotifyTransferConfirmed())->handle(new TransferConfirmed($fx['requisition']->id));

        Notification::assertNotSentTo($fx['auditor'], TransferConfirmedNotification::class);
    });

    it('no-ops when the requisition is missing', function () {
        Notification::fake();

        (new NotifyTransferConfirmed())->handle(new TransferConfirmed(999999));

        Notification::assertNothingSent();
    });
});

// ===========================================================================
// NotifyTransferCancelled
// ===========================================================================

describe('NotifyTransferCancelled', function () {
    it('notifies admins and staff at the source warehouse only', function () {
        Notification::fake();
        $fx = makeRequisitionRecipients();

        (new NotifyTransferCancelled())->handle(new TransferCancelled($fx['requisition']->id));

        Notification::assertSentTo($fx['admin'], TransferCancelledNotification::class);
        Notification::assertSentTo($fx['fromStaff'], TransferCancelledNotification::class);
        Notification::assertNotSentTo($fx['toStaff'], TransferCancelledNotification::class);
        Notification::assertNotSentTo($fx['otherStaff'], TransferCancelledNotification::class);
        Notification::assertNotSentTo($fx['auditor'], TransferCancelledNotification::class);
    });

    it('no-ops when the requisition is missing', function () {
        Notification::fake();

        (new NotifyTransferCancelled())->handle(new TransferCancelled(999999));

        Notification::assertNothingSent();
    });
});

// ===========================================================================
// NotifyTransferDispatched
// ===========================================================================

describe('NotifyTransferDispatched', function () {
    it('notifies admins and staff at the destination warehouse only', function () {
        Notification::fake();
        $fx = makeRequisitionRecipients();

        (new NotifyTransferDispatched())->handle(new TransferDispatched($fx['requisition']->id));

        Notification::assertSentTo($fx['admin'], TransferDispatchedNotification::class);
        Notification::assertSentTo($fx['toStaff'], TransferDispatchedNotification::class);
        Notification::assertNotSentTo($fx['fromStaff'], TransferDispatchedNotification::class);
        Notification::assertNotSentTo($fx['otherStaff'], TransferDispatchedNotification::class);
        Notification::assertNotSentTo($fx['auditor'], TransferDispatchedNotification::class);
    });

    it('no-ops when the requisition is missing', function () {
        Notification::fake();

        (new NotifyTransferDispatched())->handle(new TransferDispatched(999999));

        Notification::assertNothingSent();
    });
});

// ===========================================================================
// NotifyTransferReceived
// ===========================================================================

describe('NotifyTransferReceived', function () {
    it('notifies admins and staff at either endpoint', function () {
        Notification::fake();
        $fx = makeRequisitionRecipients();

        (new NotifyTransferReceived())->handle(new TransferReceived($fx['requisition']->id));

        Notification::assertSentTo($fx['admin'], TransferReceivedNotification::class);
        Notification::assertSentTo($fx['fromStaff'], TransferReceivedNotification::class);
        Notification::assertSentTo($fx['toStaff'], TransferReceivedNotification::class);
        Notification::assertNotSentTo($fx['otherStaff'], TransferReceivedNotification::class);
        Notification::assertNotSentTo($fx['auditor'], TransferReceivedNotification::class);
    });

    it('no-ops when the requisition is missing', function () {
        Notification::fake();

        (new NotifyTransferReceived())->handle(new TransferReceived(999999));

        Notification::assertNothingSent();
    });
});

// ===========================================================================
// NotifyLossRecorded
// ===========================================================================

describe('NotifyLossRecorded', function () {
    it('notifies admins, auditors, and staff at the loss warehouse', function () {
        Notification::fake();
        $fx = makeWarehouseRecipients();

        $loss = LossLedger::factory()->create(['warehouse_id' => $fx['warehouse']->id]);

        (new NotifyLossRecorded())->handle(new LossRecorded($loss->id));

        Notification::assertSentTo($fx['admin'], LossRecordedNotification::class);
        Notification::assertSentTo($fx['auditor'], LossRecordedNotification::class); // auditors included
        Notification::assertSentTo($fx['staff'], LossRecordedNotification::class);
    });

    it('does not notify staff at unrelated warehouses', function () {
        Notification::fake();
        $fx = makeWarehouseRecipients();

        $loss = LossLedger::factory()->create(['warehouse_id' => $fx['warehouse']->id]);

        (new NotifyLossRecorded())->handle(new LossRecorded($loss->id));

        Notification::assertNotSentTo($fx['otherStaff'], LossRecordedNotification::class);
    });

    it('no-ops when the loss ledger is missing', function () {
        Notification::fake();

        (new NotifyLossRecorded())->handle(new LossRecorded(999999));

        Notification::assertNothingSent();
    });
});

// ===========================================================================
// NotifyPurchaseOrderReceived
// ===========================================================================

describe('NotifyPurchaseOrderReceived', function () {
    it('notifies admins and staff at the order warehouse', function () {
        Notification::fake();
        $fx = makeWarehouseRecipients();

        $order = PurchaseOrder::factory()->create(['warehouse_id' => $fx['warehouse']->id]);

        (new NotifyPurchaseOrderReceived())->handle(new PurchaseOrderReceived($order->id));

        Notification::assertSentTo($fx['admin'], PurchaseOrderReceivedNotification::class);
        Notification::assertSentTo($fx['staff'], PurchaseOrderReceivedNotification::class);
        Notification::assertNotSentTo($fx['otherStaff'], PurchaseOrderReceivedNotification::class);
        Notification::assertNotSentTo($fx['auditor'], PurchaseOrderReceivedNotification::class);
    });

    it('no-ops when the purchase order is missing', function () {
        Notification::fake();

        (new NotifyPurchaseOrderReceived())->handle(new PurchaseOrderReceived(999999));

        Notification::assertNothingSent();
    });
});

// ===========================================================================
// NotifyPurchaseOrderCancelled (gap-fill listener)
// ===========================================================================

describe('NotifyPurchaseOrderCancelled', function () {
    it('notifies admins and staff at the order warehouse', function () {
        Notification::fake();
        $fx = makeWarehouseRecipients();

        $order = PurchaseOrder::factory()->create(['warehouse_id' => $fx['warehouse']->id]);

        (new NotifyPurchaseOrderCancelled())->handle(new PurchaseOrderCancelled($order->id));

        Notification::assertSentTo($fx['admin'], PurchaseOrderCancelledNotification::class);
        Notification::assertSentTo($fx['staff'], PurchaseOrderCancelledNotification::class);
        Notification::assertNotSentTo($fx['otherStaff'], PurchaseOrderCancelledNotification::class);
        Notification::assertNotSentTo($fx['auditor'], PurchaseOrderCancelledNotification::class);
    });

    it('no-ops when the purchase order is missing', function () {
        Notification::fake();

        (new NotifyPurchaseOrderCancelled())->handle(new PurchaseOrderCancelled(999999));

        Notification::assertNothingSent();
    });
});

// ===========================================================================
// NotifySalesOrderDispatched
// ===========================================================================

describe('NotifySalesOrderDispatched', function () {
    it('notifies admins and staff at the order warehouse', function () {
        Notification::fake();
        $fx = makeWarehouseRecipients();

        $order = SalesOrder::factory()->create(['warehouse_id' => $fx['warehouse']->id]);

        (new NotifySalesOrderDispatched())->handle(new SalesOrderDispatched($order->id));

        Notification::assertSentTo($fx['admin'], SalesOrderDispatchedNotification::class);
        Notification::assertSentTo($fx['staff'], SalesOrderDispatchedNotification::class);
        Notification::assertNotSentTo($fx['otherStaff'], SalesOrderDispatchedNotification::class);
        Notification::assertNotSentTo($fx['auditor'], SalesOrderDispatchedNotification::class);
    });

    it('no-ops when the sales order is missing', function () {
        Notification::fake();

        (new NotifySalesOrderDispatched())->handle(new SalesOrderDispatched(999999));

        Notification::assertNothingSent();
    });
});

// ===========================================================================
// NotifyInventoryBelowReorderPoint
// ===========================================================================

describe('NotifyInventoryBelowReorderPoint', function () {
    it('notifies admins and staff at the warehouse', function () {
        Notification::fake();
        $fx = makeWarehouseRecipients();

        $variant = ProductVariant::factory()->create();

        (new NotifyInventoryBelowReorderPoint())->handle(
            new InventoryBelowReorderPoint($variant->id, $fx['warehouse']->id),
        );

        Notification::assertSentTo($fx['admin'], InventoryBelowReorderPointNotification::class);
        Notification::assertSentTo($fx['staff'], InventoryBelowReorderPointNotification::class);
        Notification::assertNotSentTo($fx['otherStaff'], InventoryBelowReorderPointNotification::class);
        Notification::assertNotSentTo($fx['auditor'], InventoryBelowReorderPointNotification::class);
    });

    it('does not depend on the variant row existing', function () {
        // The listener carries the pair from the event; a missing
        // variant row is not a reason to skip the alert.
        Notification::fake();
        $fx = makeWarehouseRecipients();

        (new NotifyInventoryBelowReorderPoint())->handle(
            new InventoryBelowReorderPoint(999999, $fx['warehouse']->id),
        );

        Notification::assertSentTo($fx['admin'], InventoryBelowReorderPointNotification::class);
    });
});

// ===========================================================================
// BranchManager interim treatment — no distinct recipient tier
// ===========================================================================

it('treats branch manager like warehouse staff — assigned-warehouse path only', function () {
    // ⚠ Interim: the listeners do not special-case BranchManager. A
    // BranchManager assigned to the affected warehouse receives the
    // notification; one assigned elsewhere does not.
    Notification::fake();

    $warehouse = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();

    $bmAssigned = User::factory()->branchManager()->create();
    $bmAssigned->warehouses()->attach($warehouse->id);

    $bmElsewhere = User::factory()->branchManager()->create();
    $bmElsewhere->warehouses()->attach($other->id);

    $variant = ProductVariant::factory()->create();

    (new NotifyInventoryBelowReorderPoint())->handle(
        new InventoryBelowReorderPoint($variant->id, $warehouse->id),
    );

    Notification::assertSentTo($bmAssigned, InventoryBelowReorderPointNotification::class);
    Notification::assertNotSentTo($bmElsewhere, InventoryBelowReorderPointNotification::class);
});

it('does not include a branch manager with no warehouse assignment', function () {
    Notification::fake();

    $warehouse = Warehouse::factory()->create();
    $bmUnassigned = User::factory()->branchManager()->create();

    $variant = ProductVariant::factory()->create();

    (new NotifyInventoryBelowReorderPoint())->handle(
        new InventoryBelowReorderPoint($variant->id, $warehouse->id),
    );

    Notification::assertNotSentTo($bmUnassigned, InventoryBelowReorderPointNotification::class);
});
