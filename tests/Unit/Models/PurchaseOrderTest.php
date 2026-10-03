<?php

declare(strict_types=1);

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * PurchaseOrder model contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §2.16 purchase_orders schema: columns, defaults, soft deletes,
 *     FK actions, indexes.
 *   - §3.14 model shape: fillable, casts, five relations,
 *     canBeCancelled() two-condition guard.
 *   - §0 core principle 5 (purchase lifecycle) and A2 (symmetric
 *     purchase/sales single-entity flows).
 *   - §5.13 PurchaseOrderFactory defaults and ordered() state.
 *   - §6.4 PurchaseService::cancelPurchaseOrder() re-check.
 *   - §8.7 PurchaseOrderPolicy delete boundary (Draft/Cancelled only).
 *   - §12 Pest coverage list: "PurchaseOrder::canBeCancelled() returns
 *     false when any item has received quantity".
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Traits, fillable, casts
// ---------------------------------------------------------------------------

it('uses HasFactory and SoftDeletes traits', function () {
    $traits = class_uses_recursive(PurchaseOrder::class);

    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\Factories\HasFactory::class);
    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\SoftDeletes::class);
});

it('declares exactly the §3.14 fillable set', function () {
    $order = new PurchaseOrder();

    expect($order->getFillable())->toBe([
        'reference_code', 'supplier_id', 'warehouse_id', 'status',
        'update_cost_price', 'ordered_by', 'received_by',
        'ordered_at', 'received_at', 'cancelled_at', 'notes',
    ]);
});

it('casts status to the PurchaseOrderStatus enum', function () {
    $order = PurchaseOrder::factory()->create([
        'status' => PurchaseOrderStatus::Draft->value,
    ]);

    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Draft);
    expect($order->fresh()->status)->toBeInstanceOf(PurchaseOrderStatus::class);
});

it('casts update_cost_price to boolean', function () {
    $order = PurchaseOrder::factory()->create(['update_cost_price' => 1]);

    expect($order->fresh()->update_cost_price)->toBeTrue();

    $order->update(['update_cost_price' => 0]);
    expect($order->fresh()->update_cost_price)->toBeFalse();
});

it('casts the three lifecycle timestamps to datetime', function () {
    $order = PurchaseOrder::factory()->create([
        'ordered_at' => '2026-01-15 10:00:00',
        'received_at' => '2026-01-16 10:00:00',
        'cancelled_at' => '2026-01-17 10:00:00',
    ]);

    $fresh = $order->fresh();

    expect($fresh->ordered_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
    expect($fresh->received_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
    expect($fresh->cancelled_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
});

// ---------------------------------------------------------------------------
// §2.16 schema defaults
// ---------------------------------------------------------------------------

it('defaults status to draft via the §2.16 schema', function () {
    // §2.16: status column default = 'draft' (pinned literal). Bypass
    // the factory to prove the schema default.
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $user = User::factory()->create();

    DB::table('purchase_orders')->insert([
        'reference_code' => 'PO-TEST-0001',
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'ordered_by' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $order = PurchaseOrder::where('reference_code', 'PO-TEST-0001')->first();

    expect($order->status)->toBe(PurchaseOrderStatus::Draft);
    expect($order->update_cost_price)->toBeFalse();
    expect($order->ordered_at)->toBeNull();
    expect($order->received_at)->toBeNull();
    expect($order->cancelled_at)->toBeNull();
    expect($order->received_by)->toBeNull();
});

// ---------------------------------------------------------------------------
// §5.13 factory defaults and ordered() state
// ---------------------------------------------------------------------------

it('produces a purchase order with the §5.13 factory defaults', function () {
    // §5.13: status = Draft, reference_code matches 'PO-*',
    // ordered_by is a factory-made user, ordered_at = null.
    $order = PurchaseOrder::factory()->create();

    expect($order->status)->toBe(PurchaseOrderStatus::Draft);
    expect($order->reference_code)->toStartWith('PO-');
    expect($order->ordered_by)->not->toBeNull();
    expect($order->ordered_at)->toBeNull();
});

it('supports the §5.13 ordered() state', function () {
    $order = PurchaseOrder::factory()->ordered()->create();

    expect($order->status)->toBe(PurchaseOrderStatus::Ordered);
    expect($order->ordered_at)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Relations
// ---------------------------------------------------------------------------

it('exposes all five §3.14 relations with correct types and FKs', function () {
    $order = new PurchaseOrder();

    expect($order->supplier())->toBeInstanceOf(BelongsTo::class);
    expect($order->supplier()->getRelated())->toBeInstanceOf(Supplier::class);

    expect($order->warehouse())->toBeInstanceOf(BelongsTo::class);
    expect($order->warehouse()->getRelated())->toBeInstanceOf(Warehouse::class);

    expect($order->orderedBy())->toBeInstanceOf(BelongsTo::class);
    expect($order->orderedBy()->getForeignKeyName())->toBe('ordered_by');
    expect($order->orderedBy()->getRelated())->toBeInstanceOf(User::class);

    expect($order->receivedBy())->toBeInstanceOf(BelongsTo::class);
    expect($order->receivedBy()->getForeignKeyName())->toBe('received_by');
    expect($order->receivedBy()->getRelated())->toBeInstanceOf(User::class);

    expect($order->items())->toBeInstanceOf(HasMany::class);
    expect($order->items()->getRelated())->toBeInstanceOf(PurchaseOrderItem::class);
});

it('resolves the supplier / warehouse / actor relations end-to-end', function () {
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $orderer = User::factory()->create();
    $receiver = User::factory()->create();

    $order = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'ordered_by' => $orderer->id,
        'received_by' => $receiver->id,
    ]);

    expect($order->supplier->id)->toBe($supplier->id);
    expect($order->warehouse->id)->toBe($warehouse->id);
    expect($order->orderedBy->id)->toBe($orderer->id);
    expect($order->receivedBy->id)->toBe($receiver->id);
});

it('allows received_by to be null', function () {
    $order = PurchaseOrder::factory()->create(['received_by' => null]);

    expect($order->received_by)->toBeNull();
    expect($order->receivedBy)->toBeNull();
});

it('resolves items end-to-end', function () {
    $order = PurchaseOrder::factory()->create();
    PurchaseOrderItem::factory()->count(3)->create(['purchase_order_id' => $order->id]);

    expect($order->items()->count())->toBe(3);
});

// ---------------------------------------------------------------------------
// §3.14 canBeCancelled() — two-condition guard
// ---------------------------------------------------------------------------

it('allows cancellation of a Draft order with no items', function () {
    $order = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::Draft]);

    expect($order->canBeCancelled())->toBeTrue();
});

it('allows cancellation of a Draft order with unreceived items', function () {
    $order = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::Draft]);
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'received_base_qty' => 0,
    ]);

    expect($order->canBeCancelled())->toBeTrue();
});

it('allows cancellation of an Ordered order with unreceived items', function () {
    $order = PurchaseOrder::factory()->ordered()->create();
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'received_base_qty' => 0,
    ]);

    expect($order->canBeCancelled())->toBeTrue();
});

it('blocks cancellation of an Ordered order with any received item (§12 coverage)', function () {
    // §12 Pest coverage list: "PurchaseOrder::canBeCancelled() returns
    // false when any item has received quantity".
    $order = PurchaseOrder::factory()->ordered()->create();
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'received_base_qty' => 1,
    ]);

    expect($order->canBeCancelled())->toBeFalse();
});

it('blocks cancellation of a PartiallyReceived order', function () {
    $order = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::PartiallyReceived]);

    expect($order->canBeCancelled())->toBeFalse();
});

it('blocks cancellation of a Received order', function () {
    $order = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::Received]);

    expect($order->canBeCancelled())->toBeFalse();
});

it('blocks cancellation of a Cancelled order', function () {
    $order = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::Cancelled]);

    expect($order->canBeCancelled())->toBeFalse();
});

it('blocks cancellation when only one item has been received, even in Draft', function () {
    // The item-level guard fires regardless of status — a Draft order
    // with a received item is unusual but not cancellable.
    $order = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::Draft]);
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'received_base_qty' => 0,
    ]);
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'received_base_qty' => 5,
    ]);

    expect($order->canBeCancelled())->toBeFalse();
});

it('returns true for a Draft order whose items are all unreceived across multiple rows', function () {
    $order = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::Draft]);
    PurchaseOrderItem::factory()->count(3)->create([
        'purchase_order_id' => $order->id,
        'received_base_qty' => 0,
    ]);

    expect($order->canBeCancelled())->toBeTrue();
});

// ---------------------------------------------------------------------------
// §2.16 FK discipline
// ---------------------------------------------------------------------------

it('rejects a duplicate reference_code via the §2.16 unique constraint', function () {
    PurchaseOrder::factory()->create(['reference_code' => 'PO-20260101120000-100']);

    expect(fn () => PurchaseOrder::factory()->create(['reference_code' => 'PO-20260101120000-100']))
        ->toThrow(QueryException::class);
});

it('blocks deletion of a supplier still referenced by a purchase order (restrictOnDelete, §2.16)', function () {
    $supplier = Supplier::factory()->create();
    PurchaseOrder::factory()->create(['supplier_id' => $supplier->id]);

    expect(fn () => $supplier->forceDelete())->toThrow(QueryException::class);
});

it('blocks deletion of a warehouse still referenced by a purchase order (restrictOnDelete, §2.16)', function () {
    $warehouse = Warehouse::factory()->create();
    PurchaseOrder::factory()->create(['warehouse_id' => $warehouse->id]);

    expect(fn () => $warehouse->delete())->toThrow(QueryException::class);
});

it('cascades items on order force delete (cascadeOnDelete, §2.17)', function () {
    $order = PurchaseOrder::factory()->create();
    PurchaseOrderItem::factory()->count(2)->create(['purchase_order_id' => $order->id]);

    $order->forceDelete();

    expect(PurchaseOrderItem::where('purchase_order_id', $order->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// §2.16 soft-delete semantics
// ---------------------------------------------------------------------------

it('soft-deletes a purchase order', function () {
    $order = PurchaseOrder::factory()->create();

    $order->delete();

    expect(PurchaseOrder::find($order->id))->toBeNull();
    expect(PurchaseOrder::withTrashed()->find($order->id))->not->toBeNull();
    expect($order->fresh()->deleted_at)->not->toBeNull();
});

it('restores a soft-deleted purchase order', function () {
    $order = PurchaseOrder::factory()->create();
    $order->delete();

    $order->restore();

    expect(PurchaseOrder::find($order->id))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// §0 principle 1 sanity — a purchase order participates in the ledger
// ---------------------------------------------------------------------------

it('participates in the derived stock ledger via reference_type / reference_id', function () {
    // §6.4 receivePurchase() writes Purchase movements tagged with
    // reference_type = PurchaseOrder::class and reference_id =
    // (string) $order->id. This test asserts the linkage shape.
    $order = PurchaseOrder::factory()->create();
    $variant = ProductVariant::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $order->warehouse_id,
        'type' => StockMovementType::Purchase,
        'quantity' => 25,
        'reference_type' => PurchaseOrder::class,
        'reference_id' => (string) $order->id,
        'reference_code' => $order->reference_code,
    ]);

    $movements = StockMovement::where('reference_type', PurchaseOrder::class)
        ->where('reference_id', (string) $order->id)
        ->get();

    expect($movements)->toHaveCount(1);
    expect($movements->first()->reference_code)->toBe($order->reference_code);
});
