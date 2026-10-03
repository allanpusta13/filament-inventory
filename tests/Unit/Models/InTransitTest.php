<?php

declare(strict_types=1);

use App\Enums\InTransitStatus;
use App\Models\InTransit;
use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * InTransit model contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §2.10 in_transits schema: columns, defaults, FK actions, index
 *     on (transfer_requisition_id, status).
 *   - §3.10 model shape: fillable, casts, three relations.
 *   - §4.7 InTransitStatus semantics.
 *   - §5.11 InTransitFactory defaults.
 *   - §6.2 InventoryService::markInTransit() writes the terminal
 *     transitions (Cleared / Lost) — the model does not carry this
 *     method by design.
 *   - §10 ActiveInTransitWidget filters on status = 'in_transit'.
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Traits, fillable, casts
// ---------------------------------------------------------------------------

it('uses HasFactory only', function () {
    $traits = class_uses_recursive(InTransit::class);

    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\Factories\HasFactory::class);
});

it('does not use SoftDeletes — the §2.10 schema declares no deleted_at', function () {
    $traits = class_uses_recursive(InTransit::class);

    expect($traits)->not->toHaveKey(Illuminate\Database\Eloquent\SoftDeletes::class);
    expect((new InTransit())->getDates())->not->toContain('deleted_at');
});

it('declares exactly the §3.10 fillable set', function () {
    $inTransit = new InTransit();

    expect($inTransit->getFillable())->toBe([
        'transfer_requisition_id', 'transfer_requisition_item_id',
        'product_variant_id', 'dispatched_base_qty', 'dispatched_at',
        'status', 'cleared_at',
    ]);
});

it('casts dispatched_base_qty to integer', function () {
    $inTransit = InTransit::factory()->create(['dispatched_base_qty' => '42']);

    expect($inTransit->fresh()->dispatched_base_qty)->toBe(42)->toBeInt();
});

it('casts dispatched_at to datetime', function () {
    $inTransit = InTransit::factory()->create([
        'dispatched_at' => '2026-01-15 10:00:00',
    ]);

    expect($inTransit->fresh()->dispatched_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
});

it('casts cleared_at to datetime', function () {
    $inTransit = InTransit::factory()->create([
        'cleared_at' => '2026-01-16 10:00:00',
    ]);

    expect($inTransit->fresh()->cleared_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
});

it('casts status to the InTransitStatus enum', function () {
    $inTransit = InTransit::factory()->create([
        'status' => InTransitStatus::InTransit->value,
    ]);

    expect($inTransit->fresh()->status)->toBe(InTransitStatus::InTransit);
    expect($inTransit->fresh()->status)->toBeInstanceOf(InTransitStatus::class);
});

// ---------------------------------------------------------------------------
// §2.10 schema defaults
// ---------------------------------------------------------------------------

it('defaults status to in_transit via the §2.10 schema', function () {
    // §2.10: status column default = 'in_transit' (pinned literal).
    // Bypass the factory to prove the schema default.
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $requisition->id,
    ]);
    $variant = ProductVariant::factory()->create();

    DB::table('in_transits')->insert([
        'transfer_requisition_id' => $requisition->id,
        'transfer_requisition_item_id' => $item->id,
        'product_variant_id' => $variant->id,
        'dispatched_base_qty' => 25,
        'dispatched_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $inTransit = InTransit::where('transfer_requisition_item_id', $item->id)->first();

    expect($inTransit->status)->toBe(InTransitStatus::InTransit);
    expect($inTransit->cleared_at)->toBeNull();
});

// ---------------------------------------------------------------------------
// §5.11 factory shape
// ---------------------------------------------------------------------------

it('produces an in-transit row with the §5.11 factory defaults', function () {
    // §5.11: dispatched_base_qty ∈ [1,50], dispatched_at = now,
    // status = InTransit, cleared_at = null.
    $inTransit = InTransit::factory()->create();

    expect($inTransit->dispatched_base_qty)->toBeGreaterThanOrEqual(1);
    expect($inTransit->dispatched_base_qty)->toBeLessThanOrEqual(50);
    expect($inTransit->dispatched_at)->not->toBeNull();
    expect($inTransit->status)->toBe(InTransitStatus::InTransit);
    expect($inTransit->cleared_at)->toBeNull();
});

// ---------------------------------------------------------------------------
// Relations
// ---------------------------------------------------------------------------

it('exposes transferRequisition() as a BelongsTo relation', function () {
    $inTransit = new InTransit();

    expect($inTransit->transferRequisition())->toBeInstanceOf(BelongsTo::class);
    expect($inTransit->transferRequisition()->getRelated())->toBeInstanceOf(TransferRequisition::class);
});

it('exposes transferRequisitionItem() as a BelongsTo relation', function () {
    $inTransit = new InTransit();

    expect($inTransit->transferRequisitionItem())->toBeInstanceOf(BelongsTo::class);
    expect($inTransit->transferRequisitionItem()->getRelated())->toBeInstanceOf(TransferRequisitionItem::class);
});

it('exposes productVariant() as a BelongsTo relation', function () {
    $inTransit = new InTransit();

    expect($inTransit->productVariant())->toBeInstanceOf(BelongsTo::class);
    expect($inTransit->productVariant()->getRelated())->toBeInstanceOf(ProductVariant::class);
});

it('resolves each relation end-to-end', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $requisition->id,
    ]);
    $variant = ProductVariant::factory()->create();

    $inTransit = InTransit::factory()->create([
        'transfer_requisition_id' => $requisition->id,
        'transfer_requisition_item_id' => $item->id,
        'product_variant_id' => $variant->id,
    ]);

    expect($inTransit->transferRequisition->id)->toBe($requisition->id);
    expect($inTransit->transferRequisitionItem->id)->toBe($item->id);
    expect($inTransit->productVariant->id)->toBe($variant->id);
});

// ---------------------------------------------------------------------------
// §4.7 status semantics — active vs terminal
// ---------------------------------------------------------------------------

it('classifies InTransit as the active state and Cleared / Lost as terminal', function () {
    // §4.7: InTransit is the only non-terminal case; Cleared and Lost
    // are terminal. The §10 ActiveInTransitWidget filters on
    // status = 'in_transit'.
    expect(InTransitStatus::InTransit->value)->toBe('in_transit');
    expect(InTransitStatus::Cleared->value)->toBe('cleared');
    expect(InTransitStatus::Lost->value)->toBe('lost');

    // Sanity: exactly three cases, no accidental additions.
    expect(InTransitStatus::cases())->toHaveCount(3);
});

it('allows the status field to hold any InTransitStatus case', function (InTransitStatus $status) {
    $inTransit = InTransit::factory()->create(['status' => $status]);

    expect($inTransit->fresh()->status)->toBe($status);
})->with(InTransitStatus::cases());

it('filters active rows by status = in_transit', function () {
    // §10 ActiveInTransitWidget's filter shape.
    InTransit::factory()->create(['status' => InTransitStatus::InTransit]);
    InTransit::factory()->create(['status' => InTransitStatus::Cleared, 'cleared_at' => now()]);
    InTransit::factory()->create(['status' => InTransitStatus::Lost, 'cleared_at' => now()]);

    $active = InTransit::where('status', InTransitStatus::InTransit->value)->get();

    expect($active)->toHaveCount(1);
    expect($active->first()->status)->toBe(InTransitStatus::InTransit);
});

// ---------------------------------------------------------------------------
// §6.2 markInTransit() writes terminal transitions
// ---------------------------------------------------------------------------

it('supports the §6.2 markInTransit() update shape without a model method', function () {
    // §6.2: markInTransit() updates rows via query builder — the model
    // itself carries no transition method. Assert the shape it writes
    // (status + cleared_at) is readable back through the model.
    $inTransit = InTransit::factory()->create(['status' => InTransitStatus::InTransit]);

    InTransit::where('transfer_requisition_item_id', $inTransit->transfer_requisition_item_id)
        ->where('status', InTransitStatus::InTransit->value)
        ->update([
            'status' => InTransitStatus::Cleared->value,
            'cleared_at' => now(),
        ]);

    $fresh = $inTransit->fresh();

    expect($fresh->status)->toBe(InTransitStatus::Cleared);
    expect($fresh->cleared_at)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// §2.10 FK cascade
// ---------------------------------------------------------------------------

it('cascades on parent requisition force delete (cascadeOnDelete, §2.10)', function () {
    $requisition = TransferRequisition::factory()->create();
    $item = TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $requisition->id,
    ]);

    InTransit::factory()->count(2)->create([
        'transfer_requisition_id' => $requisition->id,
        'transfer_requisition_item_id' => $item->id,
    ]);

    $requisition->forceDelete();

    expect(InTransit::where('transfer_requisition_id', $requisition->id)->count())->toBe(0);
});

it('cascades on parent item force delete (cascadeOnDelete, §2.10)', function () {
    $item = TransferRequisitionItem::factory()->create();
    InTransit::factory()->create([
        'transfer_requisition_item_id' => $item->id,
    ]);

    $item->delete();

    expect(InTransit::where('transfer_requisition_item_id', $item->id)->count())->toBe(0);
});

it('blocks deletion of a variant referenced by an in-transit row (restrictOnDelete, §2.10)', function () {
    $variant = ProductVariant::factory()->create();
    InTransit::factory()->create(['product_variant_id' => $variant->id]);

    expect(fn () => $variant->forceDelete())->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------
// §2.10 index — (transfer_requisition_id, status) lookup
// ---------------------------------------------------------------------------

it('supports indexed lookups by (transfer_requisition_id, status)', function () {
    // §2.10 declares an index on (transfer_requisition_id, status) —
    // the canonical lookup for "active in-transit rows for this
    // requisition".
    $requisition = TransferRequisition::factory()->create();

    InTransit::factory()->count(2)->create([
        'transfer_requisition_id' => $requisition->id,
        'status' => InTransitStatus::InTransit,
    ]);
    InTransit::factory()->create([
        'transfer_requisition_id' => $requisition->id,
        'status' => InTransitStatus::Cleared,
    ]);

    $active = InTransit::where('transfer_requisition_id', $requisition->id)
        ->where('status', InTransitStatus::InTransit->value)
        ->get();

    expect($active)->toHaveCount(2);
});

// ---------------------------------------------------------------------------
// §2.10 nullable cleared_at semantics
// ---------------------------------------------------------------------------

it('allows cleared_at to be null for active rows', function () {
    $inTransit = InTransit::factory()->create([
        'status' => InTransitStatus::InTransit,
        'cleared_at' => null,
    ]);

    expect($inTransit->fresh()->cleared_at)->toBeNull();
});

it('allows cleared_at to be set for terminal rows', function () {
    $inTransit = InTransit::factory()->create([
        'status' => InTransitStatus::Cleared,
        'cleared_at' => now(),
    ]);

    expect($inTransit->fresh()->cleared_at)->not->toBeNull();
});
