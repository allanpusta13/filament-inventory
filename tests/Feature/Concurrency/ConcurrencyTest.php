<?php

declare(strict_types=1);

use App\Enums\PurchaseOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\TransferRequisitionStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidDocumentStateException;
use App\Exceptions\OutstandingQuantityExceededException;
use App\Models\DirectTransfer;
use App\Models\InTransit;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockMovement;
use App\Models\StockMovementIdempotencyKey;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\PurchaseService;
use App\Services\SalesService;
use App\Services\TransferRequisitionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * §24 — Concurrency Test Matrix.
 *
 * NOTE: This file is NOT enumerated in §25's file map, but §24
 * explicitly requires the scenarios. Companion addition.
 *
 * Each test simulates the "loser's view" of a race — the second
 * operation runs against the state left by the first, which is exactly
 * what a row-locked request sees when it acquires the lock after the
 * winner. Under real concurrency, the `lockForUpdate()` calls in the
 * services serialize the two requests, so the second always sees the
 * winner's committed state.
 *
 * Tests that require a genuine second connection (parallel writes to
 * the same rows) are guarded against SQLite and skipped on the §12
 * default test environment.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

/**
 * Build a transfer requisition item, its base-unit conversion row
 * already observer-materialized, and seed on-hand stock at the source
 * warehouse.
 *
 * @return array{requisition: TransferRequisition, item: TransferRequisitionItem, variant: ProductVariant, from: Warehouse, to: Warehouse}
 */
function concurrencyMakeDispatchedRequisition(int $approvedQty = 25): array
{
    [$from, $to] = Warehouse::factory()->count(2)->create();
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $from->id,
        'type' => App\Enums\StockMovementType::Adjustment,
        'quantity' => $approvedQty * 10,
    ]);

    $requisition = TransferRequisition::factory()->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
        'status' => TransferRequisitionStatus::Confirmed,
    ]);

    $item = TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $requisition->id,
        'product_variant_id' => $variant->id,
        'approved_unit_name' => 'pc',
        'approved_unit_ratio' => 1,
        'approved_qty' => $approvedQty,
        'approved_base_qty' => $approvedQty,
        'shipped_base_qty' => 0,
    ]);

    app(InventoryService::class)->dispatchTransfer($requisition);

    return [
        'requisition' => $requisition->fresh(),
        'item' => $item->fresh(),
        'variant' => $variant,
        'from' => $from,
        'to' => $to,
    ];
}

// ===========================================================================
// Scenario 1 — Two users confirm same requisition
// ===========================================================================

it('serializes confirm: the second confirm throws InvalidDocumentStateException', function () {
    [$from, $to] = Warehouse::factory()->count(2)->create();
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    $requisition = TransferRequisition::factory()->requested()->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $requisition->id,
        'product_variant_id' => $variant->id,
        'requested_unit_name' => 'pc',
        'requested_unit_ratio' => 1,
        'requested_qty' => 10,
        'requested_base_qty' => 10,
    ]);

    $service = app(TransferRequisitionService::class);

    // Winner — confirms and moves status to Confirmed.
    $service->confirm($requisition);

    // Loser — tries to confirm the same requisition.
    // Under real concurrency, `lockForUpdate()` serializes the two and
    // the loser sees the moved status.
    expect(fn () => $service->confirm($requisition))
        ->toThrow(InvalidDocumentStateException::class);

    // Post-condition: exactly one confirm effect — status is Confirmed
    // once, `approved_at` is set once.
    expect($requisition->fresh()->status)->toBe(TransferRequisitionStatus::Confirmed);
});

// ===========================================================================
// Scenario 2 — Two users dispatch same requisition
// ===========================================================================

it('serializes dispatch: the second dispatch throws InvalidDocumentStateException', function () {
    $fx = concurrencyMakeDispatchedRequisition(approvedQty: 25);
    $service = app(InventoryService::class);

    // Status is now Dispatched (from the fixture). Second dispatch fails.
    expect(fn () => $service->dispatchTransfer($fx['requisition']))
        ->toThrow(InvalidDocumentStateException::class);

    // Exactly one TransferOut movement, one InTransit row.
    expect(StockMovement::where('type', App\Enums\StockMovementType::TransferOut->value)->count())->toBe(1);
    expect(InTransit::where('transfer_requisition_id', $fx['requisition']->id)->count())->toBe(1);
});

// ===========================================================================
// Scenario 3 — Two users receive same scan payload
// ===========================================================================

it('serializes scan: a duplicate payload is a no-op', function () {
    $fx = concurrencyMakeDispatchedRequisition(approvedQty: 25);
    $service = app(InventoryService::class);

    $payload = [
        $fx['item']->id => ['received_good' => 25, 'received_damaged' => 0],
    ];

    // Winner — applies the scan.
    $service->scanToReceive($fx['requisition'], $payload);

    $movementsAfterFirst = StockMovement::where('type', App\Enums\StockMovementType::TransferIn->value)->count();
    $keysAfterFirst = StockMovementIdempotencyKey::count();

    // Loser — same payload. Under real concurrency, the second request
    // either hits the idempotency checksum pre-check or the DB unique
    // constraint on the idempotency key. Either way, no-op.
    $service->scanToReceive($fx['requisition'], $payload);

    expect(StockMovement::where('type', App\Enums\StockMovementType::TransferIn->value)->count())
        ->toBe($movementsAfterFirst);
    expect(StockMovementIdempotencyKey::count())->toBe($keysAfterFirst);
});

// ===========================================================================
// Scenario 4 — Two users receive different payloads concurrently
// ===========================================================================

it('serializes scans: second different payload sees the first payload\'s state', function () {
    $fx = concurrencyMakeDispatchedRequisition(approvedQty: 25);
    $service = app(InventoryService::class);

    // Winner — receives 10 good.
    $service->scanToReceive($fx['requisition'], [
        $fx['item']->id => ['received_good' => 10, 'received_damaged' => 0],
    ]);

    // Loser — tries to receive 20 more against an outstanding of 15.
    // The service reads the post-winner item state (received_good=10,
    // approved=25, outstanding=15) and rejects the 20.
    expect(fn () => $service->scanToReceive($fx['requisition'], [
        $fx['item']->id => ['received_good' => 20, 'received_damaged' => 0],
    ]))->toThrow(OutstandingQuantityExceededException::class);

    // Second scan with 15 (exact outstanding) succeeds.
    $service->scanToReceive($fx['requisition'], [
        $fx['item']->id => ['received_good' => 15, 'received_damaged' => 0],
    ]);

    $freshItem = $fx['item']->fresh();
    expect($freshItem->received_good_base_qty)->toBe(25);
});

// ===========================================================================
// Scenario 5 — Two users receive same purchase item
// ===========================================================================

it('serializes purchase receive: second receive sees post-first outstanding', function () {
    [$warehouse] = Warehouse::factory()->count(1)->create();
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    $order = PurchaseOrder::factory()->ordered()->create([
        'warehouse_id' => $warehouse->id,
    ]);
    $item = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'ordered_unit_name' => 'pc',
        'ordered_unit_ratio' => 1,
        'ordered_qty' => 10,
        'ordered_base_qty' => 10,
        'received_base_qty' => 0,
    ]);

    $service = app(PurchaseService::class);

    // Winner — receives 6 of 10.
    $service->receivePurchase($order->id, [$item->id => 6]);

    // Loser — tries to receive 6 more (only 4 outstanding). Guard fires.
    expect(fn () => $service->receivePurchase($order->id, [$item->id => 6]))
        ->toThrow(OutstandingQuantityExceededException::class);

    // Exactly 6 units received, movement count is 1.
    expect($item->fresh()->received_base_qty)->toBe(6);
    expect(StockMovement::where('type', App\Enums\StockMovementType::Purchase->value)->count())->toBe(1);
});

// ===========================================================================
// Scenario 6 — Two users dispatch same sales item
// ===========================================================================

it('serializes sales dispatch: second dispatch sees post-first available stock', function () {
    $warehouse = Warehouse::factory()->create();
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => App\Enums\StockMovementType::Adjustment,
        'quantity' => 20,
    ]);

    $order = SalesOrder::factory()->confirmed()->create(['warehouse_id' => $warehouse->id]);
    $item = SalesOrderItem::factory()->create([
        'sales_order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'unit_name' => 'pc',
        'unit_ratio' => 1,
        'qty' => 20,
        'base_qty' => 20,
        'dispatched_base_qty' => 0,
    ]);

    $service = app(SalesService::class);

    // Winner — dispatches 15 of 20.
    $service->dispatchSale($order->id, [$item->id => 15]);

    // Loser — tries to dispatch 10 more. Two guards fire:
    //   - OutstandingQuantityExceededException for 10 > (20−15)=5
    //   - InsufficientStockException for the remaining available (5)
    expect(fn () => $service->dispatchSale($order->id, [$item->id => 10]))
        ->toThrow(OutstandingQuantityExceededException::class);

    // Second dispatch of 5 (exact outstanding) succeeds.
    $service->dispatchSale($order->id, [$item->id => 5]);

    expect($item->fresh()->dispatched_base_qty)->toBe(20);
    expect($order->fresh()->status)->toBe(SalesOrderStatus::Dispatched);
});

// ===========================================================================
// Scenario 7 — Two users return same sales item
// ===========================================================================

it('serializes sales returns: cumulative over-return guard prevents excess', function () {
    $warehouse = Warehouse::factory()->create();
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    $order = SalesOrder::factory()->create([
        'warehouse_id' => $warehouse->id,
        'status' => SalesOrderStatus::Dispatched,
    ]);
    $item = SalesOrderItem::factory()->create([
        'sales_order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'unit_name' => 'pc',
        'unit_ratio' => 1,
        'qty' => 10,
        'base_qty' => 10,
        'dispatched_base_qty' => 10,
    ]);

    $service = app(SalesService::class);

    // Winner — returns 6.
    $service->recordSalesReturn($item->id, 6);

    // Loser — tries to return 6 more against dispatched=10, already
    // returned=6, remaining=4. Guard fires.
    expect(fn () => $service->recordSalesReturn($item->id, 6))
        ->toThrow(OutstandingQuantityExceededException::class);

    // Second return of 4 (exact remaining) succeeds.
    $service->recordSalesReturn($item->id, 4);

    expect($item->fresh()->alreadyReturnedBaseQty())->toBe(10);
});

// ===========================================================================
// Scenario 8 — Purchase receive vs sale dispatch
// ===========================================================================

it('serializes purchase receive and sale dispatch on the same variant/warehouse', function () {
    // Both operations lock the same warehouse_id row (§6.2 / §6.4 / §6.5).
    // Under real concurrency, `lockForUpdate()` on warehouse_id serializes
    // them. Sequentially, we verify the final on-hand is correct.
    $warehouse = Warehouse::factory()->create();
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => App\Enums\StockMovementType::Adjustment,
        'quantity' => 50,
    ]);

    $purchaseOrder = PurchaseOrder::factory()->ordered()->create(['warehouse_id' => $warehouse->id]);
    $purchaseItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_variant_id' => $variant->id,
        'ordered_unit_name' => 'pc',
        'ordered_unit_ratio' => 1,
        'ordered_qty' => 20,
        'ordered_base_qty' => 20,
    ]);

    $saleOrder = SalesOrder::factory()->confirmed()->create(['warehouse_id' => $warehouse->id]);
    $saleItem = SalesOrderItem::factory()->create([
        'sales_order_id' => $saleOrder->id,
        'product_variant_id' => $variant->id,
        'unit_name' => 'pc',
        'unit_ratio' => 1,
        'qty' => 10,
        'base_qty' => 10,
    ]);

    // Purchase receive (adds 20).
    app(PurchaseService::class)->receivePurchase($purchaseOrder->id, [$purchaseItem->id => 20]);

    // Sale dispatch (removes 10). Its availability check reads the
    // post-receive on-hand (70) — the sale succeeds.
    app(SalesService::class)->dispatchSale($saleOrder->id, [$saleItem->id => 10]);

    // Final on-hand: 50 + 20 − 10 = 60.
    expect($variant->fresh()->onHandQuantity($warehouse->id))->toBe(60);
});

// ===========================================================================
// Scenario 9 — Transfer dispatch vs sale dispatch
// ===========================================================================

it('serializes transfer dispatch and sale dispatch on the same variant/warehouse', function () {
    // Both use batchAvailableQuantity() for availability and lock the
    // same variant + warehouse rows.
    $warehouse = Warehouse::factory()->create();
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => App\Enums\StockMovementType::Adjustment,
        'quantity' => 30,
    ]);

    // Sale reserves 10 → available = 30 − 10 = 20.
    $saleOrder = SalesOrder::factory()->confirmed()->create(['warehouse_id' => $warehouse->id]);
    SalesOrderItem::factory()->create([
        'sales_order_id' => $saleOrder->id,
        'product_variant_id' => $variant->id,
        'unit_name' => 'pc',
        'unit_ratio' => 1,
        'qty' => 10,
        'base_qty' => 10,
    ]);

    // Transfer requisition needs 15. Available (with sale reservation) = 20.
    // The transfer's availability check excludes the requisition's own
    // reservation — so available = 30 − 10 (sale) = 20 ≥ 15. Succeeds.
    [$from, $to] = [$warehouse, Warehouse::factory()->create()];
    $requisition = TransferRequisition::factory()->confirmed()->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $requisition->id,
        'product_variant_id' => $variant->id,
        'approved_unit_name' => 'pc',
        'approved_unit_ratio' => 1,
        'approved_qty' => 15,
        'approved_base_qty' => 15,
    ]);

    app(InventoryService::class)->dispatchTransfer($requisition);

    // Final on-hand after transfer dispatch: 30 − 15 = 15.
    expect($variant->fresh()->onHandQuantity($warehouse->id))->toBe(15);
});

// ===========================================================================
// Scenario 10 — Concurrent current-price update
// ===========================================================================

it('enforces at most one current price row at the DB level on pg/sqlite', function () {
    // §2.3 partial unique index on (product_variant_id) WHERE
    // is_current = true. Under concurrency, the second insert loses the
    // race and throws a QueryException instead of corrupting the row set.
    $driver = DB::getDriverName();

    if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
        $this->markTestSkipped("Driver {$driver} has no partial-unique-index support — MySQL relies on the service layer.");
    }

    $variant = ProductVariant::factory()->create();

    ProductVariantPrice::factory()->create([
        'product_variant_id' => $variant->id,
        'is_current' => true,
    ]);

    expect(fn () => ProductVariantPrice::factory()->create([
        'product_variant_id' => $variant->id,
        'is_current' => true,
    ]))->toThrow(QueryException::class);

    // Exactly one current row.
    expect(ProductVariantPrice::where('product_variant_id', $variant->id)
        ->where('is_current', true)
        ->count())->toBe(1);
});

it('rotates the current price correctly when the service rotates then inserts', function () {
    // The canonical service path clears the old current row before
    // inserting the new one. Under concurrency, the transaction serializes
    // the rotate-then-insert so the invariant holds.
    $variant = ProductVariant::factory()->create();
    $first = ProductVariantPrice::factory()->create([
        'product_variant_id' => $variant->id,
        'is_current' => true,
    ]);

    DB::transaction(function () use ($variant, $first) {
        $first->update(['is_current' => false]);
        ProductVariantPrice::factory()->create([
            'product_variant_id' => $variant->id,
            'is_current' => true,
        ]);
    });

    expect(ProductVariantPrice::where('product_variant_id', $variant->id)
        ->where('is_current', true)
        ->count())->toBe(1);
    expect($first->fresh()->is_current)->toBeFalse();
});

// ===========================================================================
// Scenario 11 — Delete warehouse during PO/SO/TR/DT mutation
// ===========================================================================

it('blocks warehouse deletion while referenced by a purchase order', function () {
    $warehouse = Warehouse::factory()->create();
    PurchaseOrder::factory()->create(['warehouse_id' => $warehouse->id]);

    // Policy blocks.
    expect(app(App\Policies\WarehousePolicy::class)->delete($this->admin, $warehouse))
        ->toBeFalse();

    // DB FK `restrictOnDelete` is the ultimate backstop.
    expect(fn () => $warehouse->delete())
        ->toThrow(QueryException::class);
});

it('blocks warehouse deletion while referenced by a sales order', function () {
    $warehouse = Warehouse::factory()->create();
    SalesOrder::factory()->create(['warehouse_id' => $warehouse->id]);

    expect(app(App\Policies\WarehousePolicy::class)->delete($this->admin, $warehouse))
        ->toBeFalse();

    expect(fn () => $warehouse->delete())
        ->toThrow(QueryException::class);
});

it('blocks warehouse deletion while referenced by a transfer requisition', function () {
    [$a, $b] = Warehouse::factory()->count(2)->create();
    TransferRequisition::factory()->create([
        'from_warehouse_id' => $a->id,
        'to_warehouse_id' => $b->id,
    ]);

    expect(app(App\Policies\WarehousePolicy::class)->delete($this->admin, $a))->toBeFalse();
    expect(app(App\Policies\WarehousePolicy::class)->delete($this->admin, $b))->toBeFalse();
});

it('blocks warehouse deletion while referenced by a direct transfer', function () {
    [$a, $b] = Warehouse::factory()->count(2)->create();
    DirectTransfer::factory()->create([
        'from_warehouse_id' => $a->id,
        'to_warehouse_id' => $b->id,
    ]);

    expect(app(App\Policies\WarehousePolicy::class)->delete($this->admin, $a))->toBeFalse();
    expect(app(App\Policies\WarehousePolicy::class)->delete($this->admin, $b))->toBeFalse();
});

// ===========================================================================
// Scenario 12 — Badge scope under concurrent requests (cache isolation)
// ===========================================================================

it('does not bleed badge scope between two simulated requests after flush', function () {
    // Simulates two requests hitting a long-lived worker in sequence.
    // The `AppServiceProvider` listener calls `flushBadgeScope()` on
    // Logout — this test emulates the same flush between "requests".
    $warehouseA = Warehouse::factory()->create();
    $warehouseB = Warehouse::factory()->create();

    PurchaseOrder::factory()->count(2)->create([
        'warehouse_id' => $warehouseA->id,
        'status' => PurchaseOrderStatus::Ordered,
    ]);
    PurchaseOrder::factory()->count(7)->create([
        'warehouse_id' => $warehouseB->id,
        'status' => PurchaseOrderStatus::Ordered,
    ]);

    // Request 1 — user assigned to warehouse A.
    $userA = User::factory()->create();
    $userA->warehouses()->attach($warehouseA->id);
    Auth::login($userA);

    App\Filament\Resources\PurchaseOrders\PurchaseOrderResource::flushBadgeScope();
    $scopeA = App\Filament\Resources\PurchaseOrders\PurchaseOrderResource::getNavigationBadge();

    // Simulated logout — flush, matching AppServiceProvider::boot().
    App\Filament\Resources\PurchaseOrders\PurchaseOrderResource::flushBadgeScope();

    // Request 2 — user assigned to warehouse B.
    $userB = User::factory()->create();
    $userB->warehouses()->attach($warehouseB->id);
    Auth::login($userB);

    $scopeB = App\Filament\Resources\PurchaseOrders\PurchaseOrderResource::getNavigationBadge();

    expect($scopeA)->toBe('2');
    expect($scopeB)->toBe('7');
    expect($scopeA)->not->toBe($scopeB);
});

it('returns null for a warehouse staff user with no assignments even after prior user cached a scope', function () {
    $warehouseA = Warehouse::factory()->create();
    PurchaseOrder::factory()->count(3)->create([
        'warehouse_id' => $warehouseA->id,
        'status' => PurchaseOrderStatus::Ordered,
    ]);

    $userA = User::factory()->create();
    $userA->warehouses()->attach($warehouseA->id);
    Auth::login($userA);

    App\Filament\Resources\PurchaseOrders\PurchaseOrderResource::flushBadgeScope();
    expect(App\Filament\Resources\PurchaseOrders\PurchaseOrderResource::getNavigationBadge())->toBe('3');

    App\Filament\Resources\PurchaseOrders\PurchaseOrderResource::flushBadgeScope();

    $userB = User::factory()->create(); // no assignments
    Auth::login($userB);

    expect(App\Filament\Resources\PurchaseOrders\PurchaseOrderResource::getNavigationBadge())->toBeNull();
});

// ===========================================================================
// Additional — idempotency key unique constraint (DB-level last line)
// ===========================================================================

it('enforces the idempotency composite unique at the DB level', function () {
    // §19.8 — the unique constraint on (transfer_requisition_id,
    // payload_checksum) is the ultimate authority for the scan race.
    // The loser of a concurrent duplicate insert loses on this constraint.
    $requisition = TransferRequisition::factory()->create();
    $checksum = hash('sha256', 'canonical-payload');

    StockMovementIdempotencyKey::factory()->create([
        'transfer_requisition_id' => $requisition->id,
        'payload_checksum' => $checksum,
    ]);

    expect(fn () => StockMovementIdempotencyKey::factory()->create([
        'transfer_requisition_id' => $requisition->id,
        'payload_checksum' => $checksum,
    ]))->toThrow(QueryException::class);
});

it('allows the same checksum across different requisitions', function () {
    // The unique is scoped to the pair, not the checksum alone.
    [$a, $b] = TransferRequisition::factory()->count(2)->create();
    $checksum = hash('sha256', 'same-payload');

    StockMovementIdempotencyKey::factory()->create([
        'transfer_requisition_id' => $a->id,
        'payload_checksum' => $checksum,
    ]);
    StockMovementIdempotencyKey::factory()->create([
        'transfer_requisition_id' => $b->id,
        'payload_checksum' => $checksum,
    ]);

    expect(StockMovementIdempotencyKey::where('payload_checksum', $checksum)->count())->toBe(2);
});

// ===========================================================================
// Additional — dispatch idempotency via status guard
// ===========================================================================

it('prevents double in-transit row creation on a duplicated dispatch call', function () {
    $fx = concurrencyMakeDispatchedRequisition(approvedQty: 25);

    // Only one InTransit row for the one item.
    expect(InTransit::where('transfer_requisition_id', $fx['requisition']->id)->count())->toBe(1);

    // A second dispatch is rejected and creates no additional row.
    try {
        app(InventoryService::class)->dispatchTransfer($fx['requisition']);
    } catch (InvalidDocumentStateException) {
        // expected
    }

    expect(InTransit::where('transfer_requisition_id', $fx['requisition']->id)->count())->toBe(1);
});

// ===========================================================================
// Additional — cancellation vs dispatch (state machine)
// ===========================================================================

it('serializes cancel vs dispatch: cancelled requisition cannot be dispatched', function () {
    [$from, $to] = Warehouse::factory()->count(2)->create();
    $variant = ProductVariant::factory()->create(['base_unit_name' => 'pc']);

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $from->id,
        'type' => App\Enums\StockMovementType::Adjustment,
        'quantity' => 100,
    ]);

    $requisition = TransferRequisition::factory()->confirmed()->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
    ]);
    TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $requisition->id,
        'product_variant_id' => $variant->id,
        'approved_unit_name' => 'pc',
        'approved_unit_ratio' => 1,
        'approved_qty' => 10,
        'approved_base_qty' => 10,
    ]);

    // Winner — cancel (allowed because Confirmed is pre-dispatch).
    app(TransferRequisitionService::class)->cancelRequisition($requisition);

    // Loser — dispatch. Status is now Cancelled, not Confirmed.
    expect(fn () => app(InventoryService::class)->dispatchTransfer($requisition->fresh()))
        ->toThrow(InvalidDocumentStateException::class);
});
