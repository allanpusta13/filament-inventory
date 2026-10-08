<?php

declare(strict_types=1);

use App\Enums\SalesOrderStatus;
use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * SalesOrder model contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §2.18 sales_orders schema: columns, defaults, soft deletes,
 *     FK actions, indexes.
 *   - §3.16 model shape: fillable, casts, five relations,
 *     canBeCancelled() two-state pre-dispatch boundary.
 *   - §0 core principle 5 (sales lifecycle) and A2 (symmetric
 *     purchase/sales single-entity flows).
 *   - §5.15 SalesOrderFactory defaults and confirmed() state.
 *   - §6.5 SalesService::cancelSalesOrder() re-check.
 *   - §8.8 SalesOrderPolicy delete boundary (Draft/Cancelled only).
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Traits, fillable, casts
// ---------------------------------------------------------------------------

it('uses HasFactory and SoftDeletes traits', function () {
    $traits = class_uses_recursive(SalesOrder::class);

    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\Factories\HasFactory::class);
    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\SoftDeletes::class);
});

it('declares exactly the §3.16 fillable set', function () {
    $order = new SalesOrder();

    expect($order->getFillable())->toBe([
        'reference_code', 'customer_id', 'warehouse_id', 'status',
        'ordered_by', 'dispatched_by',
        'ordered_at', 'confirmed_at', 'dispatched_at', 'cancelled_at', 'notes',
    ]);
});

it('casts status to the SalesOrderStatus enum', function () {
    $order = SalesOrder::factory()->create([
        'status' => SalesOrderStatus::Draft->value,
    ]);

    expect($order->fresh()->status)->toBe(SalesOrderStatus::Draft);
    expect($order->fresh()->status)->toBeInstanceOf(SalesOrderStatus::class);
});

it('casts the four lifecycle timestamps to datetime', function () {
    $order = SalesOrder::factory()->create([
        'ordered_at' => '2026-01-15 10:00:00',
        'confirmed_at' => '2026-01-16 10:00:00',
        'dispatched_at' => '2026-01-17 10:00:00',
        'cancelled_at' => '2026-01-18 10:00:00',
    ]);

    $fresh = $order->fresh();

    expect($fresh->ordered_at)->toBeInstanceOf(Carbon\CarbonImmutable::class);
    expect($fresh->confirmed_at)->toBeInstanceOf(Carbon\CarbonImmutable::class);
    expect($fresh->dispatched_at)->toBeInstanceOf(Carbon\CarbonImmutable::class);
    expect($fresh->cancelled_at)->toBeInstanceOf(Carbon\CarbonImmutable::class);
});

// ---------------------------------------------------------------------------
// §2.18 schema defaults
// ---------------------------------------------------------------------------

it('defaults status to draft via the §2.18 schema', function () {
    // §2.18: status column default = 'draft' (pinned literal). Bypass
    // the factory to prove the schema default.
    $customer = Customer::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $user = User::factory()->create();

    DB::table('sales_orders')->insert([
        'reference_code' => 'SO-TEST-0001',
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'ordered_by' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $order = SalesOrder::where('reference_code', 'SO-TEST-0001')->first();

    expect($order->status)->toBe(SalesOrderStatus::Draft);
    expect($order->ordered_at)->toBeNull();
    expect($order->confirmed_at)->toBeNull();
    expect($order->dispatched_at)->toBeNull();
    expect($order->cancelled_at)->toBeNull();
    expect($order->dispatched_by)->toBeNull();
});

// ---------------------------------------------------------------------------
// §5.15 factory defaults and confirmed() state
// ---------------------------------------------------------------------------

it('produces a sales order with the §5.15 factory defaults', function () {
    // §5.15: status = Draft, reference_code matches 'SO-*',
    // ordered_by is a factory-made user, ordered_at = null.
    $order = SalesOrder::factory()->create();

    expect($order->status)->toBe(SalesOrderStatus::Draft);
    expect($order->reference_code)->toStartWith('SO-');
    expect($order->ordered_by)->not->toBeNull();
    expect($order->ordered_at)->toBeNull();
});

it('supports the §5.15 confirmed() state', function () {
    $order = SalesOrder::factory()->confirmed()->create();

    expect($order->status)->toBe(SalesOrderStatus::Confirmed);
    expect($order->confirmed_at)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Relations
// ---------------------------------------------------------------------------

it('exposes all five §3.16 relations with correct types and FKs', function () {
    $order = new SalesOrder();

    expect($order->customer())->toBeInstanceOf(BelongsTo::class);
    expect($order->customer()->getRelated())->toBeInstanceOf(Customer::class);

    expect($order->warehouse())->toBeInstanceOf(BelongsTo::class);
    expect($order->warehouse()->getRelated())->toBeInstanceOf(Warehouse::class);

    expect($order->orderedBy())->toBeInstanceOf(BelongsTo::class);
    expect($order->orderedBy()->getForeignKeyName())->toBe('ordered_by');
    expect($order->orderedBy()->getRelated())->toBeInstanceOf(User::class);

    expect($order->dispatchedBy())->toBeInstanceOf(BelongsTo::class);
    expect($order->dispatchedBy()->getForeignKeyName())->toBe('dispatched_by');
    expect($order->dispatchedBy()->getRelated())->toBeInstanceOf(User::class);

    expect($order->items())->toBeInstanceOf(HasMany::class);
    expect($order->items()->getRelated())->toBeInstanceOf(SalesOrderItem::class);
});

it('resolves the customer / warehouse / actor relations end-to-end', function () {
    $customer = Customer::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $orderer = User::factory()->create();
    $dispatcher = User::factory()->create();

    $order = SalesOrder::factory()->create([
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'ordered_by' => $orderer->id,
        'dispatched_by' => $dispatcher->id,
    ]);

    expect($order->customer->id)->toBe($customer->id);
    expect($order->warehouse->id)->toBe($warehouse->id);
    expect($order->orderedBy->id)->toBe($orderer->id);
    expect($order->dispatchedBy->id)->toBe($dispatcher->id);
});

it('allows dispatched_by to be null', function () {
    $order = SalesOrder::factory()->create(['dispatched_by' => null]);

    expect($order->dispatched_by)->toBeNull();
    expect($order->dispatchedBy)->toBeNull();
});

it('resolves items end-to-end', function () {
    $order = SalesOrder::factory()->create();
    SalesOrderItem::factory()->count(3)->create(['sales_order_id' => $order->id]);

    expect($order->items()->count())->toBe(3);
});

// ---------------------------------------------------------------------------
// §3.16 canBeCancelled() — two-state pre-dispatch boundary
// ---------------------------------------------------------------------------

it('allows cancellation of Draft', function () {
    $order = SalesOrder::factory()->create(['status' => SalesOrderStatus::Draft]);

    expect($order->canBeCancelled())->toBeTrue();
});

it('allows cancellation of Confirmed', function () {
    // The pre-dispatch boundary extends through Confirmed — the order
    // has been confirmed (stock reserved) but nothing has shipped.
    $order = SalesOrder::factory()->confirmed()->create();

    expect($order->canBeCancelled())->toBeTrue();
});

it('blocks cancellation of PartiallyDispatched', function () {
    $order = SalesOrder::factory()->create(['status' => SalesOrderStatus::PartiallyDispatched]);

    expect($order->canBeCancelled())->toBeFalse();
});

it('blocks cancellation of Dispatched', function () {
    $order = SalesOrder::factory()->create(['status' => SalesOrderStatus::Dispatched]);

    expect($order->canBeCancelled())->toBeFalse();
});

it('blocks cancellation of Cancelled', function () {
    $order = SalesOrder::factory()->create(['status' => SalesOrderStatus::Cancelled]);

    expect($order->canBeCancelled())->toBeFalse();
});

it('partitions all five statuses into two cancellable and three terminal', function () {
    // §3.16: Draft and Confirmed are cancellable; PartiallyDispatched,
    // Dispatched, and Cancelled are not. The full 5-case set is
    // partitioned.
    $cancellable = [
        SalesOrderStatus::Draft,
        SalesOrderStatus::Confirmed,
    ];
    $terminal = [
        SalesOrderStatus::PartiallyDispatched,
        SalesOrderStatus::Dispatched,
        SalesOrderStatus::Cancelled,
    ];

    expect($cancellable)->toHaveCount(2);
    expect($terminal)->toHaveCount(3);
    expect(array_merge($cancellable, $terminal))
        ->toHaveCount(count(SalesOrderStatus::cases()));

    foreach ($cancellable as $status) {
        $order = SalesOrder::factory()->create(['status' => $status]);
        expect($order->canBeCancelled())->toBeTrue();
    }

    foreach ($terminal as $status) {
        $order = SalesOrder::factory()->create(['status' => $status]);
        expect($order->canBeCancelled())->toBeFalse();
    }
});

it('does not consult order items when deciding cancellation', function () {
    // §3.16 canBeCancelled() is status-only — no item-level guard like
    // PurchaseOrder::canBeCancelled() has (§3.14). A Draft sales order
    // with dispatched items is unusual but its cancellability is purely
    // status-driven; the item-level guard lives in the dispatch and
    // return services, not in cancellation.
    $order = SalesOrder::factory()->create(['status' => SalesOrderStatus::Draft]);
    SalesOrderItem::factory()->create([
        'sales_order_id' => $order->id,
        'base_qty' => 10,
        'dispatched_base_qty' => 5,
    ]);

    expect($order->canBeCancelled())->toBeTrue();
});

// ---------------------------------------------------------------------------
// §2.18 FK discipline
// ---------------------------------------------------------------------------

it('rejects a duplicate reference_code via the §2.18 unique constraint', function () {
    SalesOrder::factory()->create(['reference_code' => 'SO-20260101120000-100']);

    expect(fn () => SalesOrder::factory()->create(['reference_code' => 'SO-20260101120000-100']))
        ->toThrow(QueryException::class);
});

it('blocks deletion of a customer still referenced by a sales order (restrictOnDelete, §2.18)', function () {
    $customer = Customer::factory()->create();
    SalesOrder::factory()->create(['customer_id' => $customer->id]);

    expect(fn () => $customer->forceDelete())->toThrow(QueryException::class);
});

it('blocks deletion of a warehouse still referenced by a sales order (restrictOnDelete, §2.18)', function () {
    $warehouse = Warehouse::factory()->create();
    SalesOrder::factory()->create(['warehouse_id' => $warehouse->id]);

    expect(fn () => $warehouse->delete())->toThrow(QueryException::class);
});

it('cascades items on order force delete (cascadeOnDelete, §2.19)', function () {
    $order = SalesOrder::factory()->create();
    SalesOrderItem::factory()->count(2)->create(['sales_order_id' => $order->id]);

    $order->forceDelete();

    expect(SalesOrderItem::where('sales_order_id', $order->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// §2.18 soft-delete semantics
// ---------------------------------------------------------------------------

it('soft-deletes a sales order', function () {
    $order = SalesOrder::factory()->create();

    $order->delete();

    expect(SalesOrder::find($order->id))->toBeNull();
    expect(SalesOrder::withTrashed()->find($order->id))->not->toBeNull();
    expect($order->fresh()->deleted_at)->not->toBeNull();
});

it('restores a soft-deleted sales order', function () {
    $order = SalesOrder::factory()->create();
    $order->delete();

    $order->restore();

    expect(SalesOrder::find($order->id))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// §0 principle 1 sanity — a sales order participates in the ledger
// ---------------------------------------------------------------------------

it('participates in the derived stock ledger via reference_type / reference_id', function () {
    // §6.5 dispatchSale() writes Sale movements tagged with
    // reference_type = SalesOrder::class and reference_id =
    // (string) $order->id. This test asserts the linkage shape.
    $order = SalesOrder::factory()->create();
    $variant = ProductVariant::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $order->warehouse_id,
        'type' => StockMovementType::Sale,
        'quantity' => -10,
        'reference_type' => SalesOrder::class,
        'reference_id' => (string) $order->id,
        'reference_code' => $order->reference_code,
    ]);

    $movements = StockMovement::where('reference_type', SalesOrder::class)
        ->where('reference_id', (string) $order->id)
        ->get();

    expect($movements)->toHaveCount(1);
    expect($movements->first()->reference_code)->toBe($order->reference_code);
});
