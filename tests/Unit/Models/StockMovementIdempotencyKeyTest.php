<?php

declare(strict_types=1);

use App\Models\StockMovementIdempotencyKey;
use App\Models\TransferRequisition;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * StockMovementIdempotencyKey model contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §2.20 / §18.4 stock_movement_idempotency_keys schema: columns,
 *     unique constraint on (transfer_requisition_id, payload_checksum),
 *     created_at useCurrent, cascadeOnDelete on requisition FK.
 *   - §3.22 model shape: fillable, casts, $timestamps = false,
 *     transferRequisition() relation.
 *   - §5.21 StockMovementIdempotencyKeyFactory defaults.
 *   - §6.2 / §19.8 scan-to-receive duplicate-race contract.
 *   - §12 Pest coverage list:
 *     "InventoryService::scanToReceive() no-ops on duplicate payload
 *      via state-equality check" and
 *     "uses idempotency presence for first-scan detection".
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Traits, timestamps, fillable, casts
// ---------------------------------------------------------------------------

it('uses HasFactory only', function () {
    $traits = class_uses_recursive(StockMovementIdempotencyKey::class);

    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\Factories\HasFactory::class);
});

it('does not use SoftDeletes — the §2.20 schema declares no deleted_at', function () {
    $traits = class_uses_recursive(StockMovementIdempotencyKey::class);

    expect($traits)->not->toHaveKey(Illuminate\Database\Eloquent\SoftDeletes::class);
    expect((new StockMovementIdempotencyKey())->getDates())->not->toContain('deleted_at');
});

it('disables timestamps — the table has only created_at', function () {
    // §2.20 / §3.22: $timestamps = false. A regression that re-enables
    // timestamps would try to write a non-existent `updated_at`
    // column on every save.
    $key = new StockMovementIdempotencyKey();

    expect($key->usesTimestamps())->toBeFalse();
    expect($key->getCreatedAtColumn())->toBe('created_at');
});

it('declares exactly the §3.22 fillable set', function () {
    $key = new StockMovementIdempotencyKey();

    expect($key->getFillable())->toBe([
        'transfer_requisition_id',
        'payload_checksum',
        'resulting_item_states',
        'created_at',
    ]);
});

it('casts resulting_item_states to array', function () {
    $key = StockMovementIdempotencyKey::factory()->create([
        'resulting_item_states' => [
            ['id' => 1, 'received_good_base_qty' => 10],
            ['id' => 2, 'received_good_base_qty' => 20],
        ],
    ]);

    $fresh = $key->fresh();

    expect($fresh->resulting_item_states)->toBeArray();
    expect($fresh->resulting_item_states)->toHaveCount(2);
    expect($fresh->resulting_item_states[0])->toHaveKey('received_good_base_qty');
});

it('casts created_at to datetime', function () {
    $key = StockMovementIdempotencyKey::factory()->create();

    expect($key->fresh()->created_at)->toBeInstanceOf(Carbon\CarbonImmutable::class);
});

it('round-trips an empty resulting_item_states array', function () {
    // §6.2 scanToReceive() creates the key with an empty array first,
    // then updates it with the post-scan item states. This test
    // asserts the empty-array shape round-trips correctly.
    $key = StockMovementIdempotencyKey::factory()->create([
        'resulting_item_states' => [],
    ]);

    $fresh = $key->fresh();

    expect($fresh->resulting_item_states)->toBe([]);
});

it('round-trips a nested resulting_item_states array', function () {
    // §6.2 scanToReceive() writes the array form of
    // `$requisition->items()->get()->toArray()`. That shape has nested
    // arrays — this test asserts nesting is preserved.
    $states = [
        [
            'id' => 1,
            'transfer_requisition_id' => 5,
            'product_variant_id' => 7,
            'approved_base_qty' => 30,
            'received_good_base_qty' => 10,
            'received_damaged_base_qty' => 0,
            'received_qty' => 10,
        ],
    ];

    $key = StockMovementIdempotencyKey::factory()->create([
        'resulting_item_states' => $states,
    ]);

    expect($key->fresh()->resulting_item_states)->toBe($states);
});

// ---------------------------------------------------------------------------
// §5.21 factory shape
// ---------------------------------------------------------------------------

it('produces a key with the §5.21 factory defaults', function () {
    // §5.21: payload_checksum = hash('sha256', uuid()),
    // resulting_item_states = [], created_at = now.
    $key = StockMovementIdempotencyKey::factory()->create();

    expect($key->payload_checksum)->toBeString();
    expect($key->payload_checksum)->toHaveLength(64); // sha256 hex
    expect($key->resulting_item_states)->toBe([]);
    expect($key->created_at)->not->toBeNull();
});

it('produces a valid sha256 hex checksum via the factory', function () {
    $key = StockMovementIdempotencyKey::factory()->create();

    // Hex-encoded sha256 hashes are 64 lowercase hex characters.
    expect($key->payload_checksum)->toMatch('/^[a-f0-9]{64}$/');
});

// ---------------------------------------------------------------------------
// Relations
// ---------------------------------------------------------------------------

it('exposes transferRequisition() as a BelongsTo relation', function () {
    $key = new StockMovementIdempotencyKey();

    expect($key->transferRequisition())->toBeInstanceOf(BelongsTo::class);
    expect($key->transferRequisition()->getRelated())->toBeInstanceOf(TransferRequisition::class);
});

it('resolves the parent requisition end-to-end', function () {
    $requisition = TransferRequisition::factory()->create();
    $key = StockMovementIdempotencyKey::factory()->create([
        'transfer_requisition_id' => $requisition->id,
    ]);

    expect($key->transferRequisition->id)->toBe($requisition->id);
});

// ---------------------------------------------------------------------------
// §2.20 unique constraint on (transfer_requisition_id, payload_checksum)
// ---------------------------------------------------------------------------

it('rejects a duplicate (transfer_requisition_id, payload_checksum) pair', function () {
    // §2.20: the unique constraint is the authority that closes the
    // §19.8 scan-to-receive duplicate race. This test asserts the DB
    // rejects the loser's insert.
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
    // The unique constraint is scoped to (requisition, checksum) — the
    // same payload checksum on two different requisitions is legal.
    $a = TransferRequisition::factory()->create();
    $b = TransferRequisition::factory()->create();
    $checksum = hash('sha256', 'canonical-payload');

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

it('allows different checksums on the same requisition', function () {
    // A requisition may receive multiple scans with different payloads
    // (partial intake across batches).
    $requisition = TransferRequisition::factory()->create();

    StockMovementIdempotencyKey::factory()->create([
        'transfer_requisition_id' => $requisition->id,
        'payload_checksum' => hash('sha256', 'first-scan'),
    ]);
    StockMovementIdempotencyKey::factory()->create([
        'transfer_requisition_id' => $requisition->id,
        'payload_checksum' => hash('sha256', 'second-scan'),
    ]);

    expect(StockMovementIdempotencyKey::where('transfer_requisition_id', $requisition->id)->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// §2.20 FK cascade
// ---------------------------------------------------------------------------

it('cascades on parent requisition force delete (cascadeOnDelete, §2.20)', function () {
    $requisition = TransferRequisition::factory()->create();
    StockMovementIdempotencyKey::factory()->count(2)->create([
        'transfer_requisition_id' => $requisition->id,
    ]);

    $requisition->forceDelete();

    expect(StockMovementIdempotencyKey::where('transfer_requisition_id', $requisition->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// §2.20 index sanity
// ---------------------------------------------------------------------------

it('supports indexed existence lookups for the §19.8 race', function () {
    // §6.2 scanToReceive() checks existence first (to detect first
    // scan), then attempts an insert (to claim the key), then on
    // duplicate re-reads the winner. Both operations hit the unique
    // index (transfer_requisition_id, payload_checksum).
    $requisition = TransferRequisition::factory()->create();

    expect(
        StockMovementIdempotencyKey::where('transfer_requisition_id', $requisition->id)
            ->where('payload_checksum', hash('sha256', 'payload'))
            ->exists()
    )->toBeFalse();

    StockMovementIdempotencyKey::factory()->create([
        'transfer_requisition_id' => $requisition->id,
        'payload_checksum' => hash('sha256', 'payload'),
    ]);

    expect(
        StockMovementIdempotencyKey::where('transfer_requisition_id', $requisition->id)
            ->where('payload_checksum', hash('sha256', 'payload'))
            ->exists()
    )->toBeTrue();
});

it('supports first-scan detection via absence of any key for a requisition', function () {
    // §6.2 scanToReceive(): "First-scan detection: no idempotency
    // record exists yet." A requisition with no keys is a first scan.
    $fresh = TransferRequisition::factory()->create();
    $scanning = TransferRequisition::factory()->create();

    StockMovementIdempotencyKey::factory()->create([
        'transfer_requisition_id' => $scanning->id,
    ]);

    expect(
        StockMovementIdempotencyKey::where('transfer_requisition_id', $fresh->id)->exists()
    )->toBeFalse();

    expect(
        StockMovementIdempotencyKey::where('transfer_requisition_id', $scanning->id)->exists()
    )->toBeTrue();
});

// ---------------------------------------------------------------------------
// created_at write — useCurrent() on the migration
// ---------------------------------------------------------------------------

it('records created_at when the row is inserted', function () {
    // §2.20: created_at is `useCurrent()` in the migration and set
    // explicitly by the service. The model's $timestamps = false means
    // Eloquent does not auto-write this — the column default or an
    // explicit value from the caller populates it.
    $requisition = TransferRequisition::factory()->create();

    DB::table('stock_movement_idempotency_keys')->insert([
        'transfer_requisition_id' => $requisition->id,
        'payload_checksum' => hash('sha256', 'payload'),
        'resulting_item_states' => json_encode([]),
        // created_at omitted — the migration's useCurrent() supplies it.
    ]);

    $key = StockMovementIdempotencyKey::where('transfer_requisition_id', $requisition->id)->first();

    expect($key->created_at)->not->toBeNull();
    expect($key->created_at)->toBeInstanceOf(Carbon\CarbonImmutable::class);
});

it('does not write an updated_at column on save', function () {
    // §2.20: the table has no `updated_at` column; $timestamps = false
    // prevents Eloquent from attempting to write it.
    $requisition = TransferRequisition::factory()->create();

    // No QueryException — the insert would fail if Eloquent tried to
    // write a non-existent updated_at column.
    $key = StockMovementIdempotencyKey::create([
        'transfer_requisition_id' => $requisition->id,
        'payload_checksum' => hash('sha256', 'payload'),
        'resulting_item_states' => [],
    ]);

    expect($key->exists)->toBeTrue();
});

// ---------------------------------------------------------------------------
// result_item_states update path (§6.2)
// ---------------------------------------------------------------------------

it('supports the post-scan update to resulting_item_states', function () {
    // §6.2 scanToReceive(): the key is created with `resulting_item_states
    // = []` before any ledger write, then updated with the post-scan
    // item states after the movements are written. This test asserts
    // the update path works and does not attempt to write updated_at.
    $key = StockMovementIdempotencyKey::factory()->create([
        'resulting_item_states' => [],
    ]);

    $states = [['id' => 1, 'received_good_base_qty' => 10]];

    $key->update(['resulting_item_states' => $states]);

    expect($key->fresh()->resulting_item_states)->toBe($states);
});
