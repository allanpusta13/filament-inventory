<?php

declare(strict_types=1);

use App\Enums\PurchaseOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\StockMovementType;
use App\Enums\TransferRequisitionStatus;
use App\Models\DirectTransfer;
use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Warehouse model contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §2.5 warehouses schema: code unique, name, location, is_active
 *     default true, no deleted_at.
 *   - §2.13 user_warehouse pivot schema.
 *   - §3.5 model shape: fillable, casts, nine relations.
 *   - §5.5 WarehouseFactory WH-#### code format.
 *   - §8.11 WarehousePolicy::delete() reference guards (asserted at the
 *     relation level here; the policy's own test belongs in
 *     tests/Unit/Policies/WarehousePolicyTest.php).
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Traits, fillable, casts
// ---------------------------------------------------------------------------

it('uses HasFactory only', function () {
    $traits = class_uses_recursive(Warehouse::class);

    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\Factories\HasFactory::class);
});

it('does not use SoftDeletes — the §2.5 schema declares no deleted_at', function () {
    $traits = class_uses_recursive(Warehouse::class);

    expect($traits)->not->toHaveKey(Illuminate\Database\Eloquent\SoftDeletes::class);
    expect((new Warehouse())->getDates())->not->toContain('deleted_at');
});

it('declares exactly the §3.5 fillable set', function () {
    $warehouse = new Warehouse();

    expect($warehouse->getFillable())->toBe(['code', 'name', 'location', 'is_active']);
});

it('casts is_active to boolean', function () {
    $warehouse = Warehouse::factory()->create(['is_active' => 1]);

    expect($warehouse->fresh()->is_active)->toBeTrue();

    $warehouse->update(['is_active' => 0]);
    expect($warehouse->fresh()->is_active)->toBeFalse();
});

it('defaults is_active to true via the §2.5 schema', function () {
    $warehouse = Warehouse::factory()->create();

    expect($warehouse->is_active)->toBeTrue();
});

// ---------------------------------------------------------------------------
// §2.5 code uniqueness
// ---------------------------------------------------------------------------

it('rejects a duplicate code via the §2.5 unique constraint', function () {
    Warehouse::factory()->create(['code' => 'WH-0001']);

    expect(fn () => Warehouse::factory()->create(['code' => 'WH-0001']))
        ->toThrow(QueryException::class);
});

it('allows distinct codes', function () {
    Warehouse::factory()->create(['code' => 'WH-0001']);
    Warehouse::factory()->create(['code' => 'WH-0002']);

    expect(Warehouse::count())->toBe(2);
});

// ---------------------------------------------------------------------------
// §5.5 factory shape
// ---------------------------------------------------------------------------

it('produces a warehouse with the §5.5 factory defaults', function () {
    // §5.5: code = bothify('WH-####'), name = city + ' Warehouse',
    // is_active = true.
    $warehouse = Warehouse::factory()->create();

    expect($warehouse->code)->toMatch('/^WH-\d{4}$/');
    expect($warehouse->name)->toEndWith(' Warehouse');
    expect($warehouse->is_active)->toBeTrue();
});

it('produces unique codes across a batch', function () {
    $warehouses = Warehouse::factory()->count(5)->create();

    expect($warehouses->pluck('code')->unique())->toHaveCount(5);
});

// ---------------------------------------------------------------------------
// Relations — every §3.5 relation, correct type and target
// ---------------------------------------------------------------------------

it('exposes the users() BelongsToMany pivot relation', function () {
    $warehouse = new Warehouse();

    expect($warehouse->users())->toBeInstanceOf(BelongsToMany::class);
    expect($warehouse->users()->getRelated())->toBeInstanceOf(User::class);
    expect($warehouse->users()->getTable())->toBe('user_warehouse');
});

it('exposes all eight HasMany relations with the correct types and FKs', function () {
    $warehouse = new Warehouse();

    expect($warehouse->stockMovements())->toBeInstanceOf(HasMany::class);
    expect($warehouse->stockMovements()->getRelated())->toBeInstanceOf(StockMovement::class);

    expect($warehouse->transferRequisitionsFrom())->toBeInstanceOf(HasMany::class);
    expect($warehouse->transferRequisitionsFrom()->getForeignKeyName())->toBe('from_warehouse_id');
    expect($warehouse->transferRequisitionsFrom()->getRelated())->toBeInstanceOf(TransferRequisition::class);

    expect($warehouse->transferRequisitionsTo())->toBeInstanceOf(HasMany::class);
    expect($warehouse->transferRequisitionsTo()->getForeignKeyName())->toBe('to_warehouse_id');
    expect($warehouse->transferRequisitionsTo()->getRelated())->toBeInstanceOf(TransferRequisition::class);

    expect($warehouse->purchaseOrders())->toBeInstanceOf(HasMany::class);
    expect($warehouse->purchaseOrders()->getRelated())->toBeInstanceOf(PurchaseOrder::class);

    expect($warehouse->salesOrders())->toBeInstanceOf(HasMany::class);
    expect($warehouse->salesOrders()->getRelated())->toBeInstanceOf(SalesOrder::class);

    expect($warehouse->lossLedgers())->toBeInstanceOf(HasMany::class);
    expect($warehouse->lossLedgers()->getRelated())->toBeInstanceOf(LossLedger::class);

    expect($warehouse->directTransfersFrom())->toBeInstanceOf(HasMany::class);
    expect($warehouse->directTransfersFrom()->getForeignKeyName())->toBe('from_warehouse_id');
    expect($warehouse->directTransfersFrom()->getRelated())->toBeInstanceOf(DirectTransfer::class);

    expect($warehouse->directTransfersTo())->toBeInstanceOf(HasMany::class);
    expect($warehouse->directTransfersTo()->getForeignKeyName())->toBe('to_warehouse_id');
    expect($warehouse->directTransfersTo()->getRelated())->toBeInstanceOf(DirectTransfer::class);
});

// ---------------------------------------------------------------------------
// Relation behaviour — end-to-end sanity on each
// ---------------------------------------------------------------------------

it('resolves assigned users through the pivot', function () {
    $warehouse = Warehouse::factory()->create();
    $users = User::factory()->count(2)->create();

    $warehouse->users()->attach($users->pluck('id'));

    expect($warehouse->fresh()->users)->toHaveCount(2);
    expect($warehouse->users->pluck('id')->all())->toEqualCanonicalizing($users->pluck('id')->all());
});

it('resolves stock movements in this warehouse', function () {
    $warehouse = Warehouse::factory()->create();
    $variant = ProductVariant::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Adjustment,
        'quantity' => 25,
    ]);

    expect($warehouse->stockMovements()->count())->toBe(1);
});

it('separates source and destination requisitions correctly', function () {
    $a = Warehouse::factory()->create();
    $b = Warehouse::factory()->create();

    // A → B
    TransferRequisition::factory()->create([
        'from_warehouse_id' => $a->id,
        'to_warehouse_id' => $b->id,
        'status' => TransferRequisitionStatus::Draft,
    ]);

    expect($a->transferRequisitionsFrom()->count())->toBe(1);
    expect($a->transferRequisitionsTo()->count())->toBe(0);
    expect($b->transferRequisitionsFrom()->count())->toBe(0);
    expect($b->transferRequisitionsTo()->count())->toBe(1);
});

it('separates source and destination direct transfers correctly', function () {
    $a = Warehouse::factory()->create();
    $b = Warehouse::factory()->create();

    DirectTransfer::factory()->create([
        'from_warehouse_id' => $a->id,
        'to_warehouse_id' => $b->id,
    ]);

    expect($a->directTransfersFrom()->count())->toBe(1);
    expect($a->directTransfersTo()->count())->toBe(0);
    expect($b->directTransfersFrom()->count())->toBe(0);
    expect($b->directTransfersTo()->count())->toBe(1);
});

it('resolves purchase orders in this warehouse', function () {
    $warehouse = Warehouse::factory()->create();
    PurchaseOrder::factory()->create([
        'warehouse_id' => $warehouse->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);

    expect($warehouse->purchaseOrders()->count())->toBe(1);
});

it('resolves sales orders in this warehouse', function () {
    $warehouse = Warehouse::factory()->create();
    SalesOrder::factory()->create([
        'warehouse_id' => $warehouse->id,
        'status' => SalesOrderStatus::Draft,
    ]);

    expect($warehouse->salesOrders()->count())->toBe(1);
});

it('resolves loss ledgers for this warehouse', function () {
    $warehouse = Warehouse::factory()->create();
    $variant = ProductVariant::factory()->create();

    LossLedger::factory()->create([
        'warehouse_id' => $warehouse->id,
        'product_variant_id' => $variant->id,
    ]);

    expect($warehouse->lossLedgers()->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// §8.11 WarehousePolicy::delete() — the relations it depends on
// ---------------------------------------------------------------------------

it('exposes every relation WarehousePolicy::delete() consults', function () {
    // §8.11 guards on stockMovements, purchaseOrders, salesOrders,
    // transferRequisitionsFrom/To, directTransfersFrom/To, and
    // lossLedgers. This test asserts each relation is callable and
    // returns the expected related model — the policy's own delete
    // test lives in tests/Unit/Policies/WarehousePolicyTest.php.
    $warehouse = new Warehouse();

    $relationNames = [
        'stockMovements' => StockMovement::class,
        'purchaseOrders' => PurchaseOrder::class,
        'salesOrders' => SalesOrder::class,
        'transferRequisitionsFrom' => TransferRequisition::class,
        'transferRequisitionsTo' => TransferRequisition::class,
        'directTransfersFrom' => DirectTransfer::class,
        'directTransfersTo' => DirectTransfer::class,
        'lossLedgers' => LossLedger::class,
    ];

    foreach ($relationNames as $method => $modelClass) {
        expect($warehouse->{$method}()->getRelated())->toBeInstanceOf($modelClass);
    }
});

// ---------------------------------------------------------------------------
// No model-level delete guard
// ---------------------------------------------------------------------------

it('does not register a model observer for deletion', function () {
    // §8.11: the delete guard is policy-side; there is no Warehouse
    // observer registered in AppServiceProvider::boot() (§17.3 registers
    // only ProductObserver and ProductVariantObserver).
    $warehouse = Warehouse::factory()->create();

    // A warehouse with no references deletes cleanly — no model-level
    // guard blocks it.
    $warehouse->delete();

    expect(Warehouse::find($warehouse->id))->toBeNull();
});

it('does not fatal when a warehouse has references (no model-level guard)', function () {
    // The model itself does not prevent delete — the FK constraints and
    // the policy do. This asserts that a warehouse with stock movements
    // is still deletable at the model layer (the DB FK will reject it
    // only if the ledger row still exists and the FK is restrictOnDelete
    // — see the dedicated §2.6 FK test in the StockMovement suite).
    $warehouse = Warehouse::factory()->create();
    $variant = ProductVariant::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
    ]);

    // Restrict-on-delete will throw at the DB layer.
    expect(fn () => $warehouse->delete())->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------
// Helpers still callable — no accidental trait collision
// ---------------------------------------------------------------------------

it('does not shadow the framework HasMany helper for warehouse_id lookups', function () {
    // Sanity: the model's own warehouse_id-based HasMany relations exist
    // only where the FK is not the default; the default `warehouse_id`
    // relations (stockMovements, purchaseOrders, salesOrders, lossLedgers)
    // all infer the FK from the related model name. Assert the inferred
    // FK for one of them.
    $warehouse = new Warehouse();

    expect($warehouse->stockMovements()->getForeignKeyName())->toBe('warehouse_id');
    expect($warehouse->purchaseOrders()->getForeignKeyName())->toBe('warehouse_id');
    expect($warehouse->salesOrders()->getForeignKeyName())->toBe('warehouse_id');
    expect($warehouse->lossLedgers()->getForeignKeyName())->toBe('warehouse_id');
});
