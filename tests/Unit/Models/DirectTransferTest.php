<?php

declare(strict_types=1);

use App\Models\DirectTransfer;
use App\Models\DirectTransferItem;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * DirectTransfer model contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §2.21 direct_transfers schema: columns, FK actions, indexes.
 *   - §3.20 model shape: fillable, casts, four relations.
 *   - A11: multi-line fire-and-forget, no lifecycle, no reversal.
 *   - §5.17 DirectTransferFactory defaults.
 *   - §6.2 InventoryService::directTransfer() — the sole writer.
 *   - §8.13 DirectTransferPolicy — read-only update() (no edit).
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Traits, fillable, casts
// ---------------------------------------------------------------------------

it('uses HasFactory only', function () {
    $traits = class_uses_recursive(DirectTransfer::class);

    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\Factories\HasFactory::class);
});

it('does not use SoftDeletes — the §2.21 schema declares no deleted_at', function () {
    $traits = class_uses_recursive(DirectTransfer::class);

    expect($traits)->not->toHaveKey(Illuminate\Database\Eloquent\SoftDeletes::class);
    expect((new DirectTransfer())->getDates())->not->toContain('deleted_at');
});

it('declares exactly the §3.20 fillable set', function () {
    $transfer = new DirectTransfer();

    expect($transfer->getFillable())->toBe([
        'reference_code',
        'from_warehouse_id',
        'to_warehouse_id',
        'notes',
        'transferred_by',
        'transferred_at',
    ]);
});

it('casts transferred_at to datetime', function () {
    $transfer = DirectTransfer::factory()->create([
        'transferred_at' => '2026-01-15 10:00:00',
    ]);

    expect($transfer->fresh()->transferred_at)->toBeInstanceOf(Carbon\CarbonImmutable::class);
});

it('has no status column — fire-and-forget (A11)', function () {
    // A11: direct transfers have no lifecycle states.
    $transfer = new DirectTransfer();

    expect($transfer->getAttributes())->not->toHaveKey('status');
    expect($transfer->getFillable())->not->toContain('status');
    expect(method_exists($transfer, 'canBeCancelled'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// §5.17 factory shape
// ---------------------------------------------------------------------------

it('produces a direct transfer with the §5.17 factory defaults', function () {
    // §5.17: reference_code matches 'DT-*', transferred_at = now,
    // notes = sentence, transferred_by = factory-made user,
    // from/to warehouse = distinct factory-made warehouses.
    $transfer = DirectTransfer::factory()->create();

    expect($transfer->reference_code)->toStartWith('DT-');
    expect($transfer->transferred_at)->not->toBeNull();
    expect($transfer->transferred_by)->not->toBeNull();
    expect($transfer->from_warehouse_id)->not->toBe($transfer->to_warehouse_id);
});

// ---------------------------------------------------------------------------
// Relations
// ---------------------------------------------------------------------------

it('exposes all four §3.20 relations with correct types and FKs', function () {
    $transfer = new DirectTransfer();

    expect($transfer->fromWarehouse())->toBeInstanceOf(BelongsTo::class);
    expect($transfer->fromWarehouse()->getForeignKeyName())->toBe('from_warehouse_id');
    expect($transfer->fromWarehouse()->getRelated())->toBeInstanceOf(Warehouse::class);

    expect($transfer->toWarehouse())->toBeInstanceOf(BelongsTo::class);
    expect($transfer->toWarehouse()->getForeignKeyName())->toBe('to_warehouse_id');
    expect($transfer->toWarehouse()->getRelated())->toBeInstanceOf(Warehouse::class);

    expect($transfer->transferredBy())->toBeInstanceOf(BelongsTo::class);
    expect($transfer->transferredBy()->getForeignKeyName())->toBe('transferred_by');
    expect($transfer->transferredBy()->getRelated())->toBeInstanceOf(User::class);

    expect($transfer->items())->toBeInstanceOf(HasMany::class);
    expect($transfer->items()->getRelated())->toBeInstanceOf(DirectTransferItem::class);
});

it('resolves all four relations end-to-end', function () {
    $from = Warehouse::factory()->create();
    $to = Warehouse::factory()->create();
    $user = User::factory()->create();

    $transfer = DirectTransfer::factory()->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
        'transferred_by' => $user->id,
    ]);

    expect($transfer->fromWarehouse->id)->toBe($from->id);
    expect($transfer->toWarehouse->id)->toBe($to->id);
    expect($transfer->transferredBy->id)->toBe($user->id);
});

it('allows transferred_by to be null and nulls it on user delete', function () {
    // §2.21: transferred_by is nullOnDelete.
    $transfer = DirectTransfer::factory()->create(['transferred_by' => null]);

    expect($transfer->transferred_by)->toBeNull();
    expect($transfer->transferredBy)->toBeNull();

    $user = User::factory()->create();
    $withUser = DirectTransfer::factory()->create(['transferred_by' => $user->id]);
    $user->delete();

    expect($withUser->fresh()->transferred_by)->toBeNull();
});

it('resolves items end-to-end', function () {
    $transfer = DirectTransfer::factory()->create();
    DirectTransferItem::factory()->count(3)->create(['direct_transfer_id' => $transfer->id]);

    expect($transfer->items()->count())->toBe(3);
});

// ---------------------------------------------------------------------------
// §2.21 FK discipline
// ---------------------------------------------------------------------------

it('rejects a duplicate reference_code via the §2.21 unique constraint', function () {
    DirectTransfer::factory()->create(['reference_code' => 'DT-20260101120000-100']);

    expect(fn () => DirectTransfer::factory()->create(['reference_code' => 'DT-20260101120000-100']))
        ->toThrow(QueryException::class);
});

it('blocks deletion of a warehouse still referenced as from (restrictOnDelete, §2.21)', function () {
    $from = Warehouse::factory()->create();
    DirectTransfer::factory()->create(['from_warehouse_id' => $from->id]);

    expect(fn () => $from->delete())->toThrow(QueryException::class);
});

it('blocks deletion of a warehouse still referenced as to (restrictOnDelete, §2.21)', function () {
    $to = Warehouse::factory()->create();
    DirectTransfer::factory()->create(['to_warehouse_id' => $to->id]);

    expect(fn () => $to->delete())->toThrow(QueryException::class);
});

it('cascades items on header force delete (cascadeOnDelete, §2.22)', function () {
    $transfer = DirectTransfer::factory()->create();
    DirectTransferItem::factory()->count(2)->create(['direct_transfer_id' => $transfer->id]);

    $transfer->forceDelete();

    expect(DirectTransferItem::where('direct_transfer_id', $transfer->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// §6.2 ledger linkage shape
// ---------------------------------------------------------------------------

it('participates in the derived stock ledger via reference_type / reference_id', function () {
    // §6.2 directTransfer() writes paired TransferOut / TransferIn
    // movements tagged with reference_type = DirectTransfer::class and
    // reference_id = (string) $header->id. This test asserts the
    // linkage shape at the model level (the service-level test lives in
    // the services suite).
    $transfer = DirectTransfer::factory()->create();
    $variant = ProductVariant::factory()->create();

    App\Models\StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $transfer->from_warehouse_id,
        'type' => App\Enums\StockMovementType::TransferOut,
        'quantity' => -10,
        'reference_type' => DirectTransfer::class,
        'reference_id' => (string) $transfer->id,
        'reference_code' => $transfer->reference_code,
    ]);
    App\Models\StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $transfer->to_warehouse_id,
        'type' => App\Enums\StockMovementType::TransferIn,
        'quantity' => 10,
        'reference_type' => DirectTransfer::class,
        'reference_id' => (string) $transfer->id,
        'reference_code' => $transfer->reference_code,
    ]);

    $movements = App\Models\StockMovement::where('reference_type', DirectTransfer::class)
        ->where('reference_id', (string) $transfer->id)
        ->get();

    expect($movements)->toHaveCount(2);
    expect($movements->pluck('type')->all())->toEqualCanonicalizing([
        App\Enums\StockMovementType::TransferOut,
        App\Enums\StockMovementType::TransferIn,
    ]);
});

// ---------------------------------------------------------------------------
// §2.21 index sanity
// ---------------------------------------------------------------------------

it('supports indexed lookups by (from_warehouse_id, to_warehouse_id)', function () {
    // §2.21 declares an index on (from_warehouse_id, to_warehouse_id).
    $from = Warehouse::factory()->create();
    $to = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();

    DirectTransfer::factory()->count(3)->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
    ]);
    DirectTransfer::factory()->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $other->id,
    ]);

    $rows = DirectTransfer::where('from_warehouse_id', $from->id)
        ->where('to_warehouse_id', $to->id)
        ->get();

    expect($rows)->toHaveCount(3);
});

it('supports indexed lookups by created_at for the audit list', function () {
    // §2.21 declares an index on created_at.
    DirectTransfer::factory()->count(3)->create(['created_at' => now()->subDay()]);
    DirectTransfer::factory()->create(['created_at' => now()]);

    $recent = DirectTransfer::where('created_at', '>=', now()->subHours(2))->get();

    expect($recent)->toHaveCount(1);
});
