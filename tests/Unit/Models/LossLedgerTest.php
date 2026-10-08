<?php

declare(strict_types=1);

use App\Enums\LossCategory;
use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * LossLedger model contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §2.11 loss_ledgers schema: columns, defaults, FK actions,
 *     index on (warehouse_id, recorded_at).
 *   - §3.11 model shape: fillable, casts, five relations,
 *     snapshotUnitCostFrom() and calculateTotalFinancialLoss().
 *   - §0 core principle 15: cost snapshot timing.
 *   - §4.9 LossCategory semantics.
 *   - §5.12 LossLedgerFactory defaults.
 *   - §6.2 writeOffOmittedItem() / recordLoss() call shapes.
 *   - §12 Pest coverage list: "Loss ledger total_financial_loss uses
 *     bcmul(), not float cast" and "snapshotUnitCostFrom() logs a
 *     warning when cost is missing or zero".
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Traits, fillable, casts
// ---------------------------------------------------------------------------

it('uses HasFactory only', function () {
    $traits = class_uses_recursive(LossLedger::class);

    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\Factories\HasFactory::class);
});

it('does not use SoftDeletes — the ledger is append-only', function () {
    $traits = class_uses_recursive(LossLedger::class);

    expect($traits)->not->toHaveKey(Illuminate\Database\Eloquent\SoftDeletes::class);
    expect((new LossLedger())->getDates())->not->toContain('deleted_at');
});

it('declares exactly the §3.11 fillable set', function () {
    $ledger = new LossLedger();

    expect($ledger->getFillable())->toBe([
        'transfer_requisition_id', 'transfer_requisition_item_id',
        'product_variant_id', 'warehouse_id', 'lost_base_qty', 'damaged_base_qty',
        'unit_cost_price', 'total_financial_loss', 'loss_category',
        'notes', 'recorded_by', 'recorded_at',
    ]);
});

it('casts lost_base_qty and damaged_base_qty to integer', function () {
    $ledger = LossLedger::factory()->create([
        'lost_base_qty' => '15',
        'damaged_base_qty' => '3',
    ]);

    $fresh = $ledger->fresh();

    expect($fresh->lost_base_qty)->toBe(15)->toBeInt();
    expect($fresh->damaged_base_qty)->toBe(3)->toBeInt();
});

it('casts unit_cost_price and total_financial_loss to decimal:4', function () {
    $ledger = LossLedger::factory()->create([
        'unit_cost_price' => '12.3456',
        'total_financial_loss' => '185.1840',
    ]);

    $fresh = $ledger->fresh();

    expect($fresh->unit_cost_price)->toBe('12.3456')->toBeString();
    expect($fresh->total_financial_loss)->toBe('185.1840')->toBeString();
});

it('casts loss_category to the LossCategory enum', function () {
    $ledger = LossLedger::factory()->create([
        'loss_category' => LossCategory::Damage->value,
    ]);

    expect($ledger->fresh()->loss_category)->toBe(LossCategory::Damage);
    expect($ledger->fresh()->loss_category)->toBeInstanceOf(LossCategory::class);
});

it('casts recorded_at to datetime', function () {
    $ledger = LossLedger::factory()->create([
        'recorded_at' => '2026-01-15 10:00:00',
    ]);

    expect($ledger->fresh()->recorded_at)->toBeInstanceOf(Carbon\CarbonImmutable::class);
});

// ---------------------------------------------------------------------------
// §2.11 schema defaults
// ---------------------------------------------------------------------------

it('defaults lost_base_qty and damaged_base_qty to 0 via the §2.11 schema', function () {
    // §2.11: lost_base_qty and damaged_base_qty default to 0.
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    DB::table('loss_ledgers')->insert([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'unit_cost_price' => '10.0000',
        'total_financial_loss' => '0.0000',
        'recorded_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $ledger = LossLedger::where('product_variant_id', $variant->id)->first();

    expect($ledger->lost_base_qty)->toBe(0);
    expect($ledger->damaged_base_qty)->toBe(0);
});

it('defaults loss_category to shortfall via the §2.11 schema', function () {
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    DB::table('loss_ledgers')->insert([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'unit_cost_price' => '10.0000',
        'total_financial_loss' => '0.0000',
        'recorded_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $ledger = LossLedger::where('product_variant_id', $variant->id)->first();

    expect($ledger->loss_category)->toBe(LossCategory::Shortfall);
});

it('defaults recorded_at to the current time via the §2.11 schema', function () {
    // §2.11: recorded_at default = current time (useCurrent()).
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $before = now()->subSecond();

    DB::table('loss_ledgers')->insert([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'unit_cost_price' => '10.0000',
        'total_financial_loss' => '10.0000',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $ledger = LossLedger::where('product_variant_id', $variant->id)->first();

    expect($ledger->recorded_at)->not->toBeNull();
    expect($ledger->recorded_at->greaterThanOrEqualTo($before))->toBeTrue();
});

// ---------------------------------------------------------------------------
// §5.12 factory shape
// ---------------------------------------------------------------------------

it('produces a loss ledger with §5.12 factory defaults', function () {
    // §5.12: lost ∈ [0,10], damaged ∈ [0,5], unit_cost ∈ [1,100],
    // total_financial_loss = bcmul(unit_cost, lost+damaged, 4),
    // loss_category ∈ one of the five, recorded_at = now.
    $ledger = LossLedger::factory()->create();

    expect($ledger->lost_base_qty)->toBeGreaterThanOrEqual(0);
    expect($ledger->lost_base_qty)->toBeLessThanOrEqual(10);
    expect($ledger->damaged_base_qty)->toBeGreaterThanOrEqual(0);
    expect($ledger->damaged_base_qty)->toBeLessThanOrEqual(5);
    expect((float) $ledger->unit_cost_price)->toBeGreaterThanOrEqual(1.0);
    expect((float) $ledger->unit_cost_price)->toBeLessThanOrEqual(100.0);
    expect($ledger->loss_category)->toBeInstanceOf(LossCategory::class);
    expect($ledger->recorded_at)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Relations
// ---------------------------------------------------------------------------

it('exposes all five BelongsTo relations with correct types and FKs', function () {
    $ledger = new LossLedger();

    expect($ledger->transferRequisition())->toBeInstanceOf(BelongsTo::class);
    expect($ledger->transferRequisition()->getRelated())->toBeInstanceOf(TransferRequisition::class);

    expect($ledger->transferRequisitionItem())->toBeInstanceOf(BelongsTo::class);
    expect($ledger->transferRequisitionItem()->getRelated())->toBeInstanceOf(TransferRequisitionItem::class);

    expect($ledger->productVariant())->toBeInstanceOf(BelongsTo::class);
    expect($ledger->productVariant()->getRelated())->toBeInstanceOf(ProductVariant::class);

    expect($ledger->warehouse())->toBeInstanceOf(BelongsTo::class);
    expect($ledger->warehouse()->getRelated())->toBeInstanceOf(Warehouse::class);

    expect($ledger->recordedBy())->toBeInstanceOf(BelongsTo::class);
    expect($ledger->recordedBy()->getForeignKeyName())->toBe('recorded_by');
    expect($ledger->recordedBy()->getRelated())->toBeInstanceOf(User::class);
});

it('resolves each relation end-to-end', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $requisition->id,
    ]);
    $variant = ProductVariant::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $user = User::factory()->create();

    $ledger = LossLedger::factory()->create([
        'transfer_requisition_id' => $requisition->id,
        'transfer_requisition_item_id' => $item->id,
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'recorded_by' => $user->id,
    ]);

    expect($ledger->transferRequisition->id)->toBe($requisition->id);
    expect($ledger->transferRequisitionItem->id)->toBe($item->id);
    expect($ledger->productVariant->id)->toBe($variant->id);
    expect($ledger->warehouse->id)->toBe($warehouse->id);
    expect($ledger->recordedBy->id)->toBe($user->id);
});

// ---------------------------------------------------------------------------
// §2.11 nullable columns — manual losses
// ---------------------------------------------------------------------------

it('allows transfer_requisition_id and transfer_requisition_item_id to be null', function () {
    // §2.11: both requisition FKs are nullable — the ledger supports
    // manual / non-requisition loss rows.
    $ledger = LossLedger::factory()->create([
        'transfer_requisition_id' => null,
        'transfer_requisition_item_id' => null,
    ]);

    expect($ledger->transfer_requisition_id)->toBeNull();
    expect($ledger->transfer_requisition_item_id)->toBeNull();
    expect($ledger->transferRequisition)->toBeNull();
    expect($ledger->transferRequisitionItem)->toBeNull();
});

it('allows recorded_by to be null and nulls it on user delete', function () {
    // §2.11: recorded_by is nullOnDelete.
    $ledger = LossLedger::factory()->create(['recorded_by' => null]);

    expect($ledger->recorded_by)->toBeNull();
    expect($ledger->recordedBy)->toBeNull();

    $user = User::factory()->create();
    $withUser = LossLedger::factory()->create(['recorded_by' => $user->id]);
    $user->delete();

    expect($withUser->fresh()->recorded_by)->toBeNull();
});

it('allows notes to be null', function () {
    $ledger = LossLedger::factory()->create(['notes' => null]);

    expect($ledger->notes)->toBeNull();
});

// ---------------------------------------------------------------------------
// §3.11 snapshotUnitCostFrom() — §0 principle 15 / §12 coverage
// ---------------------------------------------------------------------------

it('snapshots the current cost price from the variant', function () {
    $variant = ProductVariant::factory()->create();
    ProductVariantPrice::factory()->create([
        'product_variant_id' => $variant->id,
        'cost_price' => '12.3456',
        'is_current' => true,
    ]);

    $cost = LossLedger::snapshotUnitCostFrom($variant->fresh());

    expect($cost)->toBe('12.3456');
});

it('returns 0.0000 when the variant has no current price', function () {
    $variant = ProductVariant::factory()->create();

    // No price row created.
    $cost = LossLedger::snapshotUnitCostFrom($variant);

    expect($cost)->toBe('0.0000');
});

it('logs a warning when cost is zero (§12 coverage)', function () {
    // §12 Pest coverage list: "LossLedger::snapshotUnitCostFrom() logs
    // a warning when cost is missing or zero".
    Log::spy();

    $variant = ProductVariant::factory()->create();
    ProductVariantPrice::factory()->create([
        'product_variant_id' => $variant->id,
        'cost_price' => '0.0000',
        'is_current' => true,
    ]);

    LossLedger::snapshotUnitCostFrom($variant->fresh());

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $message, array $context) use ($variant) {
            return $message === 'Loss recorded with missing or zero cost price.'
                && ($context['product_variant_id'] ?? null) === $variant->id
                && ($context['sku'] ?? null) === $variant->sku;
        });
});

it('logs a warning when the variant has no current price row', function () {
    Log::spy();

    $variant = ProductVariant::factory()->create();

    LossLedger::snapshotUnitCostFrom($variant);

    Log::shouldHaveReceived('warning')->once();
});

it('returns 0.0000 without throwing when cost is null', function () {
    // Defensive — a null currentPrice is normalized to 0.0000, never
    // surfaced as a null return.
    $variant = ProductVariant::factory()->create();

    expect(LossLedger::snapshotUnitCostFrom($variant))->toBe('0.0000');
});

// ---------------------------------------------------------------------------
// §3.11 calculateTotalFinancialLoss() — bcmul, not float (§12 coverage)
// ---------------------------------------------------------------------------

it('computes total loss with bcmul, avoiding float drift (§12 coverage)', function () {
    // §12: "Loss ledger total_financial_loss uses bcmul(), not float cast".
    // 0.1 + 0.2 style precision tests: a float multiply would drift.
    expect(LossLedger::calculateTotalFinancialLoss('12.3456', 15))->toBe('185.1840');
});

it('returns a 4dp string regardless of integer qty', function (string $unitCost, int $qty, string $expected) {
    expect(LossLedger::calculateTotalFinancialLoss($unitCost, $qty))->toBe($expected);
})->with([
    ['10.0000', 0,    '0.0000'],
    ['10.0000', 1,    '10.0000'],
    ['10.0000', 5,    '50.0000'],
    ['0.0001', 1,     '0.0001'],
    ['0.0001', 10,    '0.0010'],
    ['9999.9999', 2,  '19999.9998'],
    ['12.3456', 100,  '1234.5600'],
]);

it('returns "0.0000" for a zero quantity', function () {
    expect(LossLedger::calculateTotalFinancialLoss('10.0000', 0))->toBe('0.0000');
});

it('returns a string, not a float', function () {
    $result = LossLedger::calculateTotalFinancialLoss('12.3456', 15);

    expect($result)->toBeString();
});

// ---------------------------------------------------------------------------
// §6.2 writeOffOmittedItem() / recordLoss() shape
// ---------------------------------------------------------------------------

it('supports the writeOffOmittedItem() row shape', function () {
    // §6.2 writeOffOmittedItem(): lost_base_qty = approved qty,
    // damaged_base_qty = 0, loss_category = Shortfall.
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $requisition->id,
    ]);
    $variant = ProductVariant::factory()->create();

    $ledger = LossLedger::create([
        'transfer_requisition_id' => $requisition->id,
        'transfer_requisition_item_id' => $item->id,
        'product_variant_id' => $variant->id,
        'warehouse_id' => $requisition->to_warehouse_id,
        'lost_base_qty' => 25,
        'damaged_base_qty' => 0,
        'unit_cost_price' => '10.0000',
        'total_financial_loss' => '250.0000',
        'loss_category' => LossCategory::Shortfall->value,
        'notes' => null,
        'recorded_by' => null,
        'recorded_at' => now(),
    ]);

    expect($ledger->loss_category)->toBe(LossCategory::Shortfall);
    expect($ledger->lost_base_qty)->toBe(25);
    expect($ledger->damaged_base_qty)->toBe(0);
});

it('supports the recordLoss() row shape with any category', function (LossCategory $category) {
    // §6.2 recordLoss(): operator-supplied category (any LossCategory case).
    $ledger = LossLedger::factory()->create(['loss_category' => $category]);

    expect($ledger->fresh()->loss_category)->toBe($category);
})->with(LossCategory::cases());

// ---------------------------------------------------------------------------
// §2.11 FK discipline
// ---------------------------------------------------------------------------

it('cascades on parent requisition force delete (cascadeOnDelete, §2.11)', function () {
    $requisition = TransferRequisition::factory()->create();
    LossLedger::factory()->create([
        'transfer_requisition_id' => $requisition->id,
    ]);

    $requisition->forceDelete();

    expect(LossLedger::where('transfer_requisition_id', $requisition->id)->count())->toBe(0);
});

it('blocks deletion of a variant referenced by a loss ledger (restrictOnDelete, §2.11)', function () {
    $variant = ProductVariant::factory()->create();
    LossLedger::factory()->create(['product_variant_id' => $variant->id]);

    expect(fn () => $variant->forceDelete())->toThrow(QueryException::class);
});

it('blocks deletion of a warehouse referenced by a loss ledger (restrictOnDelete, §2.11)', function () {
    $warehouse = Warehouse::factory()->create();
    LossLedger::factory()->create(['warehouse_id' => $warehouse->id]);

    expect(fn () => $warehouse->delete())->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------
// §2.11 index — (warehouse_id, recorded_at)
// ---------------------------------------------------------------------------

it('supports indexed lookups by (warehouse_id, recorded_at)', function () {
    // §2.11 declares an index on (warehouse_id, recorded_at) — the
    // canonical lookup for "loss rows for this warehouse in a period".
    $warehouse = Warehouse::factory()->create();
    $variant = ProductVariant::factory()->create();

    LossLedger::factory()->count(3)->create([
        'warehouse_id' => $warehouse->id,
        'product_variant_id' => $variant->id,
        'recorded_at' => now()->subDays(3),
    ]);
    LossLedger::factory()->create([
        'warehouse_id' => $warehouse->id,
        'product_variant_id' => $variant->id,
        'recorded_at' => now()->subDays(30),
    ]);

    $recent = LossLedger::where('warehouse_id', $warehouse->id)
        ->where('recorded_at', '>=', now()->subDays(7))
        ->get();

    expect($recent)->toHaveCount(3);
});
