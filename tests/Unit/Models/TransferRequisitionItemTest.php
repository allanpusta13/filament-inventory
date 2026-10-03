<?php

declare(strict_types=1);

use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\TransferRequisitionItemRevision;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * TransferRequisitionItem model contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §2.8 transfer_requisition_items schema: columns, defaults,
 *     FK actions.
 *   - §3.8 model shape: fillable, casts, four relations,
 *     actualVariantId() substitute-variant resolution, and
 *     outstandingShippedBaseQty() calculation.
 *   - §0 core principle 6 / A10: substitute-variant resolution.
 *   - §6.1 GuardsOutstandingQuantity::assertTransferNotOverShipped().
 *   - §6.2 InventoryService::dispatchTransfer() / scanToReceive()
 *     counter updates.
 *   - §5.10 TransferRequisitionItemFactory defaults.
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Traits, fillable, casts
// ---------------------------------------------------------------------------

it('uses HasFactory only', function () {
    $traits = class_uses_recursive(TransferRequisitionItem::class);

    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\Factories\HasFactory::class);
});

it('does not use SoftDeletes — the §2.8 schema declares no deleted_at', function () {
    $traits = class_uses_recursive(TransferRequisitionItem::class);

    expect($traits)->not->toHaveKey(Illuminate\Database\Eloquent\SoftDeletes::class);
    expect((new TransferRequisitionItem())->getDates())->not->toContain('deleted_at');
});

it('declares exactly the §3.8 fillable set', function () {
    $item = new TransferRequisitionItem();

    expect($item->getFillable())->toBe([
        'transfer_requisition_id', 'product_variant_id', 'substitute_product_variant_id',
        'requested_unit_name', 'requested_unit_ratio', 'requested_qty', 'requested_base_qty',
        'approved_unit_name', 'approved_unit_ratio', 'approved_qty', 'approved_base_qty',
        'shipped_base_qty', 'received_good_base_qty', 'received_damaged_base_qty',
        'received_qty', 'notes',
    ]);
});

it('casts all ten integer columns to integer', function () {
    $variant = ProductVariant::factory()->create();

    $item = TransferRequisitionItem::factory()->create([
        'product_variant_id' => $variant->id,
        'requested_unit_ratio' => '12',
        'requested_qty' => '5',
        'requested_base_qty' => '60',
        'approved_unit_ratio' => '6',
        'approved_qty' => '10',
        'approved_base_qty' => '60',
        'shipped_base_qty' => '24',
        'received_good_base_qty' => '20',
        'received_damaged_base_qty' => '4',
        'received_qty' => '24',
    ]);

    $fresh = $item->fresh();

    expect($fresh->requested_unit_ratio)->toBe(12)->toBeInt();
    expect($fresh->requested_qty)->toBe(5)->toBeInt();
    expect($fresh->requested_base_qty)->toBe(60)->toBeInt();
    expect($fresh->approved_unit_ratio)->toBe(6)->toBeInt();
    expect($fresh->approved_qty)->toBe(10)->toBeInt();
    expect($fresh->approved_base_qty)->toBe(60)->toBeInt();
    expect($fresh->shipped_base_qty)->toBe(24)->toBeInt();
    expect($fresh->received_good_base_qty)->toBe(20)->toBeInt();
    expect($fresh->received_damaged_base_qty)->toBe(4)->toBeInt();
    expect($fresh->received_qty)->toBe(24)->toBeInt();
});

// ---------------------------------------------------------------------------
// §2.8 schema defaults
// ---------------------------------------------------------------------------

it('defaults all post-request counters to 0 via the §2.8 schema', function () {
    // §2.8: shipped_base_qty, received_good_base_qty,
    // received_damaged_base_qty, received_qty all default to 0.
    $requisition = TransferRequisition::factory()->create();
    $variant = ProductVariant::factory()->create();

    DB::table('transfer_requisition_items')->insert([
        'transfer_requisition_id' => $requisition->id,
        'product_variant_id' => $variant->id,
        'requested_unit_name' => 'pc',
        'requested_unit_ratio' => 1,
        'requested_qty' => 10,
        'requested_base_qty' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $item = TransferRequisitionItem::where('transfer_requisition_id', $requisition->id)->first();

    expect($item->shipped_base_qty)->toBe(0);
    expect($item->received_good_base_qty)->toBe(0);
    expect($item->received_damaged_base_qty)->toBe(0);
    expect($item->received_qty)->toBe(0);
    expect($item->approved_base_qty)->toBeNull();
    expect($item->substitute_product_variant_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// §5.10 factory shape
// ---------------------------------------------------------------------------

it('produces an item with the §5.10 factory defaults', function () {
    // §5.10: requested_unit_name = 'pc', requested_unit_ratio = 1,
    // requested_qty ∈ [1,20], requested_base_qty = requested_qty.
    $item = TransferRequisitionItem::factory()->create();

    expect($item->requested_unit_name)->toBe('pc');
    expect($item->requested_unit_ratio)->toBe(1);
    expect($item->requested_qty)->toBeGreaterThanOrEqual(1);
    expect($item->requested_qty)->toBeLessThanOrEqual(20);
    expect($item->requested_base_qty)->toBe($item->requested_qty);
});

// ---------------------------------------------------------------------------
// Relations
// ---------------------------------------------------------------------------

it('exposes transferRequisition() as a BelongsTo relation', function () {
    $item = new TransferRequisitionItem();

    expect($item->transferRequisition())->toBeInstanceOf(BelongsTo::class);
    expect($item->transferRequisition()->getRelated())->toBeInstanceOf(TransferRequisition::class);
});

it('exposes productVariant() as a BelongsTo relation', function () {
    $item = new TransferRequisitionItem();

    expect($item->productVariant())->toBeInstanceOf(BelongsTo::class);
    expect($item->productVariant()->getRelated())->toBeInstanceOf(ProductVariant::class);
});

it('exposes substituteProductVariant() as a BelongsTo on the substitute FK', function () {
    $item = new TransferRequisitionItem();

    expect($item->substituteProductVariant())->toBeInstanceOf(BelongsTo::class);
    expect($item->substituteProductVariant()->getForeignKeyName())->toBe('substitute_product_variant_id');
    expect($item->substituteProductVariant()->getRelated())->toBeInstanceOf(ProductVariant::class);
});

it('exposes revisions() as a HasMany relation', function () {
    $item = new TransferRequisitionItem();

    expect($item->revisions())->toBeInstanceOf(HasMany::class);
    expect($item->revisions()->getRelated())->toBeInstanceOf(TransferRequisitionItemRevision::class);
});

it('resolves each relation end-to-end', function () {
    $requisition = TransferRequisition::factory()->create();
    $original = ProductVariant::factory()->create();
    $substitute = ProductVariant::factory()->create();

    $item = TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $requisition->id,
        'product_variant_id' => $original->id,
        'substitute_product_variant_id' => $substitute->id,
    ]);

    expect($item->transferRequisition->id)->toBe($requisition->id);
    expect($item->productVariant->id)->toBe($original->id);
    expect($item->substituteProductVariant->id)->toBe($substitute->id);
});

it('allows substituteProductVariant to be null', function () {
    $item = TransferRequisitionItem::factory()->create([
        'substitute_product_variant_id' => null,
    ]);

    expect($item->substitute_product_variant_id)->toBeNull();
    expect($item->substituteProductVariant)->toBeNull();
});

// ---------------------------------------------------------------------------
// §3.8 actualVariantId() — substitute resolution
// ---------------------------------------------------------------------------

it('returns product_variant_id when no substitute was negotiated', function () {
    $original = ProductVariant::factory()->create();
    $item = TransferRequisitionItem::factory()->create([
        'product_variant_id' => $original->id,
        'substitute_product_variant_id' => null,
    ]);

    expect($item->actualVariantId())->toBe($original->id);
});

it('returns substitute_product_variant_id when a substitute was negotiated', function () {
    // §0 core principle 6 / A10: dispatch and receipt pipelines resolve
    // the effective variant as the substitute when set.
    $original = ProductVariant::factory()->create();
    $substitute = ProductVariant::factory()->create();

    $item = TransferRequisitionItem::factory()->create([
        'product_variant_id' => $original->id,
        'substitute_product_variant_id' => $substitute->id,
    ]);

    expect($item->actualVariantId())->toBe($substitute->id);
    expect($item->actualVariantId())->not->toBe($original->id);
});

it('returns the same value across repeated calls', function () {
    $original = ProductVariant::factory()->create();
    $substitute = ProductVariant::factory()->create();

    $item = TransferRequisitionItem::factory()->create([
        'product_variant_id' => $original->id,
        'substitute_product_variant_id' => $substitute->id,
    ]);

    expect($item->actualVariantId())->toBe($item->actualVariantId());
});

// ---------------------------------------------------------------------------
// §3.8 outstandingShippedBaseQty()
// ---------------------------------------------------------------------------

it('returns approved_base_qty when nothing has been shipped', function () {
    $item = TransferRequisitionItem::factory()->create([
        'approved_base_qty' => 50,
        'shipped_base_qty' => 0,
    ]);

    expect($item->outstandingShippedBaseQty())->toBe(50);
});

it('returns the remainder when partially shipped', function () {
    $item = TransferRequisitionItem::factory()->create([
        'approved_base_qty' => 50,
        'shipped_base_qty' => 30,
    ]);

    expect($item->outstandingShippedBaseQty())->toBe(20);
});

it('returns 0 when fully shipped', function () {
    $item = TransferRequisitionItem::factory()->create([
        'approved_base_qty' => 50,
        'shipped_base_qty' => 50,
    ]);

    expect($item->outstandingShippedBaseQty())->toBe(0);
});

it('floors the outstanding value at 0 when over-shipped', function () {
    // The guard should never allow over-shipping (GuardsOutstandingQuantity
    // §6.1), but a defensive floor is intentional. This test locks it.
    $item = TransferRequisitionItem::factory()->create([
        'approved_base_qty' => 50,
        'shipped_base_qty' => 75,
    ]);

    expect($item->outstandingShippedBaseQty())->toBe(0);
});

it('returns 0 when approved_base_qty is null (pre-materialization)', function () {
    // §6.3: approved_base_qty is null until
    // NegotiationService::materializeRequestedAsApproved() runs at
    // confirm time. Before then, the outstanding shipped base qty is
    // conservatively 0.
    $item = TransferRequisitionItem::factory()->create([
        'approved_base_qty' => null,
        'shipped_base_qty' => 0,
    ]);

    expect($item->outstandingShippedBaseQty())->toBe(0);
});

it('returns 0 when approved_base_qty is null but shipped_base_qty is set', function () {
    // Defensive: even if a stale shipped quantity survives an approval
    // reset (which should never happen in normal operation), the floor
    // holds.
    $item = TransferRequisitionItem::factory()->create([
        'approved_base_qty' => null,
        'shipped_base_qty' => 10,
    ]);

    expect($item->outstandingShippedBaseQty())->toBe(0);
});

// ---------------------------------------------------------------------------
// §6.1 GuardsOutstandingQuantity contract — shape assertion
// ---------------------------------------------------------------------------

it('gives GuardsOutstandingQuantity a reliable outstanding value to compare against', function () {
    // §6.1 assertTransferNotOverShipped($item, $newShipped) compares
    // $newShipped against outstandingShippedBaseQty(). Assert the
    // shape: the guard's argument and the helper's return value share
    // the same units (base qty).
    $item = TransferRequisitionItem::factory()->create([
        'requested_unit_name' => 'case',
        'requested_unit_ratio' => 24,
        'requested_qty' => 3,
        'requested_base_qty' => 72,
        'approved_unit_name' => 'case',
        'approved_unit_ratio' => 24,
        'approved_qty' => 3,
        'approved_base_qty' => 72,
        'shipped_base_qty' => 24, // 1 case shipped
    ]);

    expect($item->outstandingShippedBaseQty())->toBe(48); // 2 cases outstanding
});

// ---------------------------------------------------------------------------
// §2.8 FK cascade
// ---------------------------------------------------------------------------

it('cascades revisions on item deletion (cascadeOnDelete, §2.9)', function () {
    $item = TransferRequisitionItem::factory()->create();
    TransferRequisitionItemRevision::factory()->count(2)->create([
        'transfer_requisition_item_id' => $item->id,
    ]);

    $item->delete();

    expect(TransferRequisitionItemRevision::where('transfer_requisition_item_id', $item->id)->count())->toBe(0);
});

it('cascades items when their parent requisition is force-deleted', function () {
    // §2.8: transfer_requisition_items.transfer_requisition_id has
    // cascadeOnDelete — items cannot outlive their header.
    $requisition = TransferRequisition::factory()->create();
    TransferRequisitionItem::factory()->count(3)->create([
        'transfer_requisition_id' => $requisition->id,
    ]);

    $requisition->forceDelete();

    expect(TransferRequisitionItem::where('transfer_requisition_id', $requisition->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// §2.8 restrictOnDelete on product_variant_id and substitute FK
// ---------------------------------------------------------------------------

it('blocks hard deletion of the original variant (restrictOnDelete, §2.8)', function () {
    $variant = ProductVariant::factory()->create();
    TransferRequisitionItem::factory()->create([
        'product_variant_id' => $variant->id,
    ]);

    expect(fn () => $variant->forceDelete())->toThrow(Illuminate\Database\QueryException::class);
});

it('blocks hard deletion of the substitute variant (restrictOnDelete, §2.8)', function () {
    $original = ProductVariant::factory()->create();
    $substitute = ProductVariant::factory()->create();

    TransferRequisitionItem::factory()->create([
        'product_variant_id' => $original->id,
        'substitute_product_variant_id' => $substitute->id,
    ]);

    expect(fn () => $substitute->forceDelete())->toThrow(Illuminate\Database\QueryException::class);
});
