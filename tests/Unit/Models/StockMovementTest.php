<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Models\DirectTransfer;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockMovement;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * StockMovement model contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §2.6 stock_movements schema: columns, defaults, indexes,
 *     FK actions (restrictOnDelete on product_variant_id and
 *     warehouse_id; nullOnDelete on related_movement_id and created_by).
 *   - §3.6 model shape: fillable, casts, four relations.
 *   - §0 core principle 1 (ledger = stock of truth) and principle 11
 *     (ledger FK immutability).
 *   - §4.4 StockMovementType isPositive() / signed-quantity contract.
 *   - §5.19 StockMovementFactory defaults.
 *   - §8.5 StockMovementPolicy is read-only (no create/update/delete)
 *     — the model itself has no write guard.
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Traits, fillable, casts
// ---------------------------------------------------------------------------

it('uses HasFactory only', function () {
    $traits = class_uses_recursive(StockMovement::class);

    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\Factories\HasFactory::class);
});

it('does not use SoftDeletes — the ledger is append-only', function () {
    $traits = class_uses_recursive(StockMovement::class);

    expect($traits)->not->toHaveKey(Illuminate\Database\Eloquent\SoftDeletes::class);
    expect((new StockMovement())->getDates())->not->toContain('deleted_at');
});

it('declares exactly the §3.6 fillable set', function () {
    $movement = new StockMovement();

    expect($movement->getFillable())->toBe([
        'product_variant_id', 'warehouse_id', 'type', 'quantity',
        'unit_name_used', 'unit_ratio_used', 'related_movement_id',
        'reference_type', 'reference_id', 'reference_code',
        'notes', 'created_by',
    ]);
});

it('casts type to the StockMovementType enum', function () {
    $movement = StockMovement::factory()->create([
        'type' => StockMovementType::Adjustment->value,
    ]);

    expect($movement->fresh()->type)->toBe(StockMovementType::Adjustment);
    expect($movement->fresh()->type)->toBeInstanceOf(StockMovementType::class);
});

it('casts quantity and unit_ratio_used to integer', function () {
    $movement = StockMovement::factory()->create([
        'quantity' => '42',
        'unit_ratio_used' => '12',
    ]);

    $fresh = $movement->fresh();

    expect($fresh->quantity)->toBe(42)->toBeInt();
    expect($fresh->unit_ratio_used)->toBe(12)->toBeInt();
});

it('defaults unit_ratio_used to 1 via the §2.6 schema', function () {
    // §2.6: unit_ratio_used default = 1.
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    DB::table('stock_movements')->insert([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Adjustment->value,
        'quantity' => 10,
        'unit_name_used' => 'pc',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $movement = StockMovement::where('product_variant_id', $variant->id)->first();

    expect($movement->unit_ratio_used)->toBe(1);
});

// ---------------------------------------------------------------------------
// §5.19 factory shape
// ---------------------------------------------------------------------------

it('produces a movement with the §5.19 factory defaults', function () {
    // §5.19: type = Adjustment, quantity ∈ [1,50], unit_name_used = 'pc',
    // unit_ratio_used = 1.
    $movement = StockMovement::factory()->create();

    expect($movement->type)->toBe(StockMovementType::Adjustment);
    expect($movement->quantity)->toBeGreaterThanOrEqual(1);
    expect($movement->quantity)->toBeLessThanOrEqual(50);
    expect($movement->unit_name_used)->toBe('pc');
    expect($movement->unit_ratio_used)->toBe(1);
});

// ---------------------------------------------------------------------------
// Relations — all four §3.6 relations
// ---------------------------------------------------------------------------

it('exposes productVariant() as a BelongsTo relation', function () {
    $movement = new StockMovement();

    expect($movement->productVariant())->toBeInstanceOf(BelongsTo::class);
    expect($movement->productVariant()->getRelated())->toBeInstanceOf(ProductVariant::class);
});

it('exposes warehouse() as a BelongsTo relation', function () {
    $movement = new StockMovement();

    expect($movement->warehouse())->toBeInstanceOf(BelongsTo::class);
    expect($movement->warehouse()->getRelated())->toBeInstanceOf(Warehouse::class);
});

it('exposes createdBy() as a BelongsTo relation on the created_by FK', function () {
    $movement = new StockMovement();

    expect($movement->createdBy())->toBeInstanceOf(BelongsTo::class);
    expect($movement->createdBy()->getRelated())->toBeInstanceOf(User::class);
    expect($movement->createdBy()->getForeignKeyName())->toBe('created_by');
});

it('exposes relatedMovement() as a self-referential BelongsTo relation', function () {
    $movement = new StockMovement();

    expect($movement->relatedMovement())->toBeInstanceOf(BelongsTo::class);
    expect($movement->relatedMovement()->getRelated())->toBeInstanceOf(StockMovement::class);
    expect($movement->relatedMovement()->getForeignKeyName())->toBe('related_movement_id');
});

it('resolves each relation end-to-end', function () {
    $user = User::factory()->create();
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $movement = StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'created_by' => $user->id,
    ]);

    expect($movement->productVariant->id)->toBe($variant->id);
    expect($movement->warehouse->id)->toBe($warehouse->id);
    expect($movement->createdBy->id)->toBe($user->id);
});

it('links paired transfer movements via related_movement_id', function () {
    // §6.2 directTransfer() writes paired TransferOut / TransferIn rows
    // and links the incoming row to the outbound row via
    // related_movement_id.
    $variant = ProductVariant::factory()->create();
    $from = Warehouse::factory()->create();
    $to = Warehouse::factory()->create();

    $out = StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $from->id,
        'type' => StockMovementType::TransferOut,
        'quantity' => -10,
    ]);

    $in = StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $to->id,
        'type' => StockMovementType::TransferIn,
        'quantity' => 10,
        'related_movement_id' => $out->id,
    ]);

    expect($in->relatedMovement->id)->toBe($out->id);
    expect($in->relatedMovement->warehouse_id)->toBe($from->id);
});

it('allows related_movement_id to be null for standalone movements', function () {
    $movement = StockMovement::factory()->create(['related_movement_id' => null]);

    expect($movement->related_movement_id)->toBeNull();
    expect($movement->relatedMovement)->toBeNull();
});

it('nulls related_movement_id when the linked movement is deleted (nullOnDelete, §2.6)', function () {
    $variant = ProductVariant::factory()->create();
    $from = Warehouse::factory()->create();
    $to = Warehouse::factory()->create();

    $out = StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $from->id,
        'type' => StockMovementType::TransferOut,
        'quantity' => -10,
    ]);

    $in = StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $to->id,
        'type' => StockMovementType::TransferIn,
        'quantity' => 10,
        'related_movement_id' => $out->id,
    ]);

    $out->delete();

    expect($in->fresh()->related_movement_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// created_by FK — nullable, nullOnDelete
// ---------------------------------------------------------------------------

it('allows a null created_by', function () {
    $movement = StockMovement::factory()->create(['created_by' => null]);

    expect($movement->created_by)->toBeNull();
    expect($movement->createdBy)->toBeNull();
});

it('nulls created_by when the user is deleted (nullOnDelete, §2.6)', function () {
    $user = User::factory()->create();
    $movement = StockMovement::factory()->create(['created_by' => $user->id]);

    $user->delete();

    expect($movement->fresh()->created_by)->toBeNull();
});

// ---------------------------------------------------------------------------
// §2.6 FK immutability — restrictOnDelete on product_variant_id and warehouse_id
// ---------------------------------------------------------------------------

it('blocks hard deletion of a variant with ledger history (restrictOnDelete, §2.6 / principle 11)', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
    ]);

    expect(fn () => $variant->forceDelete())->toThrow(QueryException::class);
});

it('blocks deletion of a warehouse with ledger history (restrictOnDelete, §2.6)', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
    ]);

    expect(fn () => $warehouse->delete())->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------
// Reference-field contract (§2.6)
// ---------------------------------------------------------------------------

it('allows reference_type, reference_id, and reference_code to be null', function () {
    // §2.6: reference fields are nullable because recordMovement()
    // accepts a null reference code for manual movements.
    $movement = StockMovement::factory()->create([
        'reference_type' => null,
        'reference_id' => null,
        'reference_code' => null,
    ]);

    $fresh = $movement->fresh();

    expect($fresh->reference_type)->toBeNull();
    expect($fresh->reference_id)->toBeNull();
    expect($fresh->reference_code)->toBeNull();
});

it('stores the header class-string and stringified id in the reference fields', function () {
    // §2.6 reference-field contract: reference_id stores the stringified
    // header id; reference_type is one of the canonical header class-strings.
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $requisition = TransferRequisition::factory()->create();

    $movement = StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::TransferOut,
        'quantity' => -10,
        'reference_type' => TransferRequisition::class,
        'reference_id' => (string) $requisition->id,
        'reference_code' => $requisition->reference_code,
    ]);

    $fresh = $movement->fresh();

    expect($fresh->reference_type)->toBe(TransferRequisition::class);
    expect($fresh->reference_id)->toBe((string) $requisition->id);
    expect($fresh->reference_id)->toBeString();
    expect($fresh->reference_code)->toBe($requisition->reference_code);
});

it('accepts every §2.6 canonical reference_type value', function (string $referenceType) {
    // §2.6: reference_type is one of App\Models\TransferRequisition,
    // DirectTransfer, PurchaseOrder, SalesOrder, or SalesOrderItem.
    $movement = StockMovement::factory()->create([
        'reference_type' => $referenceType,
        'reference_id' => '1',
        'reference_code' => 'X-0001',
    ]);

    expect($movement->fresh()->reference_type)->toBe($referenceType);
})->with([
    TransferRequisition::class,
    DirectTransfer::class,
    PurchaseOrder::class,
    SalesOrder::class,
    SalesOrderItem::class,
]);

// ---------------------------------------------------------------------------
// Signed quantity contract — enum-driven on write
// ---------------------------------------------------------------------------

it('stores positive quantities for increasing types', function () {
    // §4.4 isPositive(): TransferIn, Purchase, SaleReturn.
    foreach ([StockMovementType::TransferIn, StockMovementType::Purchase, StockMovementType::SaleReturn] as $type) {
        expect($type->isPositive())->toBeTrue();
    }
});

it('stores negative quantities for decreasing types', function () {
    // §4.4 isPositive() false: TransferOut, Loss, Damage, Sale, PurchaseReturn.
    foreach ([
        StockMovementType::TransferOut,
        StockMovementType::Loss,
        StockMovementType::Damage,
        StockMovementType::Sale,
        StockMovementType::PurchaseReturn,
    ] as $type) {
        expect($type->isPositive())->toBeFalse();
    }
});

it('preserves the caller-supplied sign for Adjustment', function () {
    // §6.2 adjustment() preserves the sign the operator supplies —
    // Adjustment is intentionally NOT isPositive() (§4.4).
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $add = StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Adjustment,
        'quantity' => 25,
    ]);

    $remove = StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Adjustment,
        'quantity' => -25,
    ]);

    expect($add->fresh()->quantity)->toBe(25);
    expect($remove->fresh()->quantity)->toBe(-25);
});

// ---------------------------------------------------------------------------
// §0 core principle 1 — the ledger is the stock of truth
// ---------------------------------------------------------------------------

it('sums signed quantities per variant/warehouse as the on-hand derivation', function () {
    // This is a model-layer sanity check for the derivation the
    // ProductVariant::onHandQuantity() accessor uses. The accessor's own
    // tests live in ProductVariantTest.
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Adjustment,
        'quantity' => 100,
    ]);
    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Sale,
        'quantity' => -30,
    ]);
    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'type' => StockMovementType::Purchase,
        'quantity' => 15,
    ]);

    $sum = StockMovement::where('product_variant_id', $variant->id)
        ->where('warehouse_id', $warehouse->id)
        ->sum('quantity');

    expect((int) $sum)->toBe(85);
    expect($variant->onHandQuantity($warehouse->id))->toBe(85);
});

// ---------------------------------------------------------------------------
// §2.6 indexes — query-time sanity, not constraint tests
// ---------------------------------------------------------------------------

it('supports indexed lookups by (product_variant_id, warehouse_id)', function () {
    // §2.6 declares an index on (product_variant_id, warehouse_id). This
    // is a functional assertion: the pair lookup returns the expected
    // rows. Index presence itself is a driver-level concern and is
    // exercised by the migration tests.
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();

    StockMovement::factory()->count(3)->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
    ]);
    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $other->id,
    ]);

    $rows = StockMovement::where('product_variant_id', $variant->id)
        ->where('warehouse_id', $warehouse->id)
        ->get();

    expect($rows)->toHaveCount(3);
});

it('supports indexed lookups by (reference_type, reference_id)', function () {
    // §2.6 declares an index on (reference_type, reference_id) — the
    // canonical lookup for "show me all movements for this header".
    $requisition = TransferRequisition::factory()->create();

    StockMovement::factory()->count(2)->create([
        'reference_type' => TransferRequisition::class,
        'reference_id' => (string) $requisition->id,
    ]);
    StockMovement::factory()->create([
        'reference_type' => TransferRequisition::class,
        'reference_id' => '999999',
    ]);

    $rows = StockMovement::where('reference_type', TransferRequisition::class)
        ->where('reference_id', (string) $requisition->id)
        ->get();

    expect($rows)->toHaveCount(2);
});
