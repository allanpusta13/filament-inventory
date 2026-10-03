<?php

declare(strict_types=1);

use App\Models\DirectTransfer;
use App\Models\DirectTransferItem;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * DirectTransferItem model contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §2.22 direct_transfer_items schema: columns, FK actions, index.
 *   - §3.21 model shape: fillable, casts, two relations.
 *   - A10: no substitute_product_variant_id column.
 *   - A11: no per-item counters (fire-and-forget).
 *   - §5.18 DirectTransferItemFactory defaults.
 *   - §6.2 InventoryService::directTransfer() — the sole writer.
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Traits, fillable, casts
// ---------------------------------------------------------------------------

it('uses HasFactory only', function () {
    $traits = class_uses_recursive(DirectTransferItem::class);

    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\Factories\HasFactory::class);
});

it('does not use SoftDeletes — the §2.22 schema declares no deleted_at', function () {
    $traits = class_uses_recursive(DirectTransferItem::class);

    expect($traits)->not->toHaveKey(Illuminate\Database\Eloquent\SoftDeletes::class);
    expect((new DirectTransferItem())->getDates())->not->toContain('deleted_at');
});

it('declares exactly the §3.21 fillable set', function () {
    $item = new DirectTransferItem();

    expect($item->getFillable())->toBe([
        'direct_transfer_id',
        'product_variant_id',
        'unit_name',
        'unit_ratio',
        'qty',
        'base_qty',
        'notes',
    ]);
});

it('does not expose substitute_product_variant_id (A10)', function () {
    // A10: substitute variants are transfer/requisition only — direct
    // transfers operate on the exact variant moved.
    $item = new DirectTransferItem();

    expect($item->getFillable())->not->toContain('substitute_product_variant_id');
    expect($item->getAttributes())->not->toHaveKey('substitute_product_variant_id');
});

it('does not expose per-item received/dispatched counters (A11)', function () {
    // A11: direct transfers are fire-and-forget — no state counters.
    $item = new DirectTransferItem();

    expect($item->getFillable())->not->toContain('received_base_qty');
    expect($item->getFillable())->not->toContain('received_good_base_qty');
    expect($item->getFillable())->not->toContain('received_damaged_base_qty');
    expect($item->getFillable())->not->toContain('received_qty');
    expect($item->getFillable())->not->toContain('shipped_base_qty');
    expect($item->getFillable())->not->toContain('dispatched_base_qty');
});

it('casts unit_ratio, qty, and base_qty to integer', function () {
    $item = DirectTransferItem::factory()->create([
        'unit_ratio' => '12',
        'qty' => '5',
        'base_qty' => '60',
    ]);

    $fresh = $item->fresh();

    expect($fresh->unit_ratio)->toBe(12)->toBeInt();
    expect($fresh->qty)->toBe(5)->toBeInt();
    expect($fresh->base_qty)->toBe(60)->toBeInt();
});

it('allows notes to be null', function () {
    $item = DirectTransferItem::factory()->create(['notes' => null]);

    expect($item->notes)->toBeNull();
});

// ---------------------------------------------------------------------------
// §5.18 factory shape
// ---------------------------------------------------------------------------

it('produces an item with the §5.18 factory defaults', function () {
    // §5.18: unit_name = 'pc', unit_ratio = 1, qty ∈ [1,20],
    // base_qty = qty.
    $item = DirectTransferItem::factory()->create();

    expect($item->unit_name)->toBe('pc');
    expect($item->unit_ratio)->toBe(1);
    expect($item->qty)->toBeGreaterThanOrEqual(1);
    expect($item->qty)->toBeLessThanOrEqual(20);
    expect($item->base_qty)->toBe($item->qty);
});

// ---------------------------------------------------------------------------
// Relations
// ---------------------------------------------------------------------------

it('exposes directTransfer() as a BelongsTo relation', function () {
    $item = new DirectTransferItem();

    expect($item->directTransfer())->toBeInstanceOf(BelongsTo::class);
    expect($item->directTransfer()->getRelated())->toBeInstanceOf(DirectTransfer::class);
});

it('exposes productVariant() as a BelongsTo relation', function () {
    $item = new DirectTransferItem();

    expect($item->productVariant())->toBeInstanceOf(BelongsTo::class);
    expect($item->productVariant()->getRelated())->toBeInstanceOf(ProductVariant::class);
});

it('resolves both relations end-to-end', function () {
    $transfer = DirectTransfer::factory()->create();
    $variant = ProductVariant::factory()->create();

    $item = DirectTransferItem::factory()->create([
        'direct_transfer_id' => $transfer->id,
        'product_variant_id' => $variant->id,
    ]);

    expect($item->directTransfer->id)->toBe($transfer->id);
    expect($item->productVariant->id)->toBe($variant->id);
});

// ---------------------------------------------------------------------------
// §2.22 FK discipline
// ---------------------------------------------------------------------------

it('cascades on parent direct transfer force delete (cascadeOnDelete, §2.22)', function () {
    $transfer = DirectTransfer::factory()->create();
    DirectTransferItem::factory()->count(2)->create(['direct_transfer_id' => $transfer->id]);

    $transfer->forceDelete();

    expect(DirectTransferItem::where('direct_transfer_id', $transfer->id)->count())->toBe(0);
});

it('blocks deletion of a variant referenced by a direct transfer item (restrictOnDelete, §2.22)', function () {
    $variant = ProductVariant::factory()->create();
    DirectTransferItem::factory()->create(['product_variant_id' => $variant->id]);

    expect(fn () => $variant->forceDelete())->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------
// §2.22 index sanity
// ---------------------------------------------------------------------------

it('supports indexed lookups by direct_transfer_id', function () {
    // §2.22 declares an index on direct_transfer_id.
    $transfer = DirectTransfer::factory()->create();
    $other = DirectTransfer::factory()->create();

    DirectTransferItem::factory()->count(3)->create(['direct_transfer_id' => $transfer->id]);
    DirectTransferItem::factory()->create(['direct_transfer_id' => $other->id]);

    $rows = DirectTransferItem::where('direct_transfer_id', $transfer->id)->get();

    expect($rows)->toHaveCount(3);
});

// ---------------------------------------------------------------------------
// §6.2 directTransfer() row shape
// ---------------------------------------------------------------------------

it('supports the §6.2 directTransfer() row shape', function () {
    // §6.2 directTransfer() writes one item row per incoming line with
    // base_qty = qty × unit_ratio. This test constructs the shape at
    // the model layer (the service-level test lives in the services
    // suite) and asserts base-qty semantics are preserved.
    $transfer = DirectTransfer::factory()->create();
    $variant = ProductVariant::factory()->create();

    $item = DirectTransferItem::create([
        'direct_transfer_id' => $transfer->id,
        'product_variant_id' => $variant->id,
        'unit_name' => 'case',
        'unit_ratio' => 24,
        'qty' => 3,
        'base_qty' => 72,
        'notes' => null,
    ]);

    $fresh = $item->fresh();

    expect($fresh->unit_name)->toBe('case');
    expect($fresh->unit_ratio)->toBe(24);
    expect($fresh->qty)->toBe(3);
    expect($fresh->base_qty)->toBe(72);
    expect($fresh->base_qty)->toBe($fresh->qty * $fresh->unit_ratio);
});

// ---------------------------------------------------------------------------
// No invariant helper methods (A11)
// ---------------------------------------------------------------------------

it('does not define per-item state-helper methods (A11)', function () {
    // A11: direct transfers have no received/shipped/outstanding state.
    // A regression that adds `outstandingBaseQty()` or a similar helper
    // would be a sign the fire-and-forget model is being eroded.
    $item = new DirectTransferItem();

    expect(method_exists($item, 'outstandingBaseQty'))->toBeFalse();
    expect(method_exists($item, 'outstandingShippedBaseQty'))->toBeFalse();
    expect(method_exists($item, 'alreadyReturnedBaseQty'))->toBeFalse();
});
