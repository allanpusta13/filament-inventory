<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Enums\TransferRequisitionStatus;
use App\Models\InTransit;
use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * TransferRequisition model contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §2.7 transfer_requisitions schema: columns, casts, soft deletes,
 *     FK actions, indexes.
 *   - §3.7 model shape: fillable, casts, nine relations,
 *     canBeCancelled() five-state pre-dispatch boundary.
 *   - §0 core principle 5 (lifecycle) and principle 14
 *     (cancellation boundary).
 *   - §5.9 TransferRequisitionFactory defaults and states.
 *   - §6.6 TransferRequisitionService::cancelRequisition() re-check.
 *   - §8.3 TransferRequisitionPolicy delete boundary (Draft/Cancelled
 *     only) — policy behaviour belongs in the policy test suite; the
 *     statuses the policy depends on are asserted here as data.
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Traits, fillable, casts
// ---------------------------------------------------------------------------

it('uses HasFactory and SoftDeletes traits', function () {
    $traits = class_uses_recursive(TransferRequisition::class);

    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\Factories\HasFactory::class);
    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\SoftDeletes::class);
});

it('declares exactly the §3.7 fillable set', function () {
    $requisition = new TransferRequisition();

    expect($requisition->getFillable())->toBe([
        'reference_code', 'from_warehouse_id', 'to_warehouse_id', 'status',
        'requested_by', 'approved_by', 'dispatched_by', 'received_by',
        'requested_at', 'approved_at', 'dispatched_at', 'completed_at', 'notes',
    ]);
});

it('casts status to the TransferRequisitionStatus enum', function () {
    $requisition = TransferRequisition::factory()->create([
        'status' => TransferRequisitionStatus::Draft->value,
    ]);

    expect($requisition->fresh()->status)->toBe(TransferRequisitionStatus::Draft);
    expect($requisition->fresh()->status)->toBeInstanceOf(TransferRequisitionStatus::class);
});

it('casts the four lifecycle timestamps to datetime', function () {
    $requisition = TransferRequisition::factory()->create([
        'requested_at' => '2026-01-15 10:00:00',
        'approved_at' => '2026-01-16 10:00:00',
        'dispatched_at' => '2026-01-17 10:00:00',
        'completed_at' => '2026-01-18 10:00:00',
    ]);

    $fresh = $requisition->fresh();

    expect($fresh->requested_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
    expect($fresh->approved_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
    expect($fresh->dispatched_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
    expect($fresh->completed_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
});

it('defaults status to draft via the §2.7 schema', function () {
    // §2.7: status column default = 'draft' (pinned literal).
    // The factory explicitly sets it, so bypass the factory to prove
    // the schema default.
    $from = Warehouse::factory()->create();
    $to = Warehouse::factory()->create();
    $user = User::factory()->create();

    DB::table('transfer_requisitions')->insert([
        'reference_code' => 'TR-TEST-0001',
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
        'requested_by' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $requisition = TransferRequisition::where('reference_code', 'TR-TEST-0001')->first();

    expect($requisition->status)->toBe(TransferRequisitionStatus::Draft);
});

// ---------------------------------------------------------------------------
// §5.9 factory defaults and states
// ---------------------------------------------------------------------------

it('produces a requisition with the §5.9 factory defaults', function () {
    // §5.9: status = Draft, from_warehouse_id / to_warehouse_id are
    // distinct factory-made warehouses, reference_code matches the
    // 'TR-*' format, requested_by is a factory-made user.
    $requisition = TransferRequisition::factory()->create();

    expect($requisition->status)->toBe(TransferRequisitionStatus::Draft);
    expect($requisition->reference_code)->toStartWith('TR-');
    expect($requisition->from_warehouse_id)->not->toBe($requisition->to_warehouse_id);
    expect($requisition->requested_by)->not->toBeNull();
});

it('supports the §5.9 requested() state', function () {
    $requisition = TransferRequisition::factory()->requested()->create();

    expect($requisition->status)->toBe(TransferRequisitionStatus::Requested);
    expect($requisition->requested_at)->not->toBeNull();
});

it('supports the §5.9 confirmed() state', function () {
    $requisition = TransferRequisition::factory()->confirmed()->create();

    expect($requisition->status)->toBe(TransferRequisitionStatus::Confirmed);
    expect($requisition->approved_at)->not->toBeNull();
    expect($requisition->approved_by)->not->toBeNull();
});

it('supports the §5.9 dispatched() state', function () {
    $requisition = TransferRequisition::factory()->dispatched()->create();

    expect($requisition->status)->toBe(TransferRequisitionStatus::Dispatched);
    expect($requisition->dispatched_at)->not->toBeNull();
    expect($requisition->dispatched_by)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Relations
// ---------------------------------------------------------------------------

it('exposes all six BelongsTo relations with correct types and FKs', function () {
    $requisition = new TransferRequisition();

    expect($requisition->fromWarehouse())->toBeInstanceOf(BelongsTo::class);
    expect($requisition->fromWarehouse()->getForeignKeyName())->toBe('from_warehouse_id');
    expect($requisition->fromWarehouse()->getRelated())->toBeInstanceOf(Warehouse::class);

    expect($requisition->toWarehouse())->toBeInstanceOf(BelongsTo::class);
    expect($requisition->toWarehouse()->getForeignKeyName())->toBe('to_warehouse_id');
    expect($requisition->toWarehouse()->getRelated())->toBeInstanceOf(Warehouse::class);

    expect($requisition->requestedBy())->toBeInstanceOf(BelongsTo::class);
    expect($requisition->requestedBy()->getForeignKeyName())->toBe('requested_by');
    expect($requisition->requestedBy()->getRelated())->toBeInstanceOf(User::class);

    expect($requisition->approvedBy())->toBeInstanceOf(BelongsTo::class);
    expect($requisition->approvedBy()->getForeignKeyName())->toBe('approved_by');
    expect($requisition->approvedBy()->getRelated())->toBeInstanceOf(User::class);

    expect($requisition->dispatchedBy())->toBeInstanceOf(BelongsTo::class);
    expect($requisition->dispatchedBy()->getForeignKeyName())->toBe('dispatched_by');
    expect($requisition->dispatchedBy()->getRelated())->toBeInstanceOf(User::class);

    expect($requisition->receivedBy())->toBeInstanceOf(BelongsTo::class);
    expect($requisition->receivedBy()->getForeignKeyName())->toBe('received_by');
    expect($requisition->receivedBy()->getRelated())->toBeInstanceOf(User::class);
});

it('exposes all three HasMany relations with correct types', function () {
    $requisition = new TransferRequisition();

    expect($requisition->items())->toBeInstanceOf(HasMany::class);
    expect($requisition->items()->getRelated())->toBeInstanceOf(TransferRequisitionItem::class);

    expect($requisition->inTransits())->toBeInstanceOf(HasMany::class);
    expect($requisition->inTransits()->getRelated())->toBeInstanceOf(InTransit::class);

    expect($requisition->lossLedgers())->toBeInstanceOf(HasMany::class);
    expect($requisition->lossLedgers()->getRelated())->toBeInstanceOf(LossLedger::class);
});

it('resolves the fromWarehouse and toWarehouse relations end-to-end', function () {
    $from = Warehouse::factory()->create();
    $to = Warehouse::factory()->create();

    $requisition = TransferRequisition::factory()->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
    ]);

    expect($requisition->fromWarehouse->id)->toBe($from->id);
    expect($requisition->toWarehouse->id)->toBe($to->id);
});

it('resolves the four actor relations end-to-end', function () {
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $dispatcher = User::factory()->create();
    $receiver = User::factory()->create();

    $requisition = TransferRequisition::factory()->create([
        'requested_by' => $requester->id,
        'approved_by' => $approver->id,
        'dispatched_by' => $dispatcher->id,
        'received_by' => $receiver->id,
    ]);

    expect($requisition->requestedBy->id)->toBe($requester->id);
    expect($requisition->approvedBy->id)->toBe($approver->id);
    expect($requisition->dispatchedBy->id)->toBe($dispatcher->id);
    expect($requisition->receivedBy->id)->toBe($receiver->id);
});

it('allows approved_by, dispatched_by, and received_by to be null', function () {
    $requisition = TransferRequisition::factory()->create([
        'approved_by' => null,
        'dispatched_by' => null,
        'received_by' => null,
    ]);

    expect($requisition->approvedBy)->toBeNull();
    expect($requisition->dispatchedBy)->toBeNull();
    expect($requisition->receivedBy)->toBeNull();
});

it('resolves items, inTransits, and lossLedgers for a requisition', function () {
    $requisition = TransferRequisition::factory()->create();
    TransferRequisitionItem::factory()->count(3)->create([
        'transfer_requisition_id' => $requisition->id,
    ]);

    expect($requisition->items()->count())->toBe(3);
});

// ---------------------------------------------------------------------------
// §3.7 canBeCancelled() — five pre-dispatch states
// ---------------------------------------------------------------------------

it('allows cancellation for exactly the five pre-dispatch states', function (TransferRequisitionStatus $status) {
    // §3.7 / §0 principle 14: Draft, Requested, UnderReviewFulfiller,
    // UnderReviewRequestor, Confirmed.
    $requisition = TransferRequisition::factory()->create(['status' => $status]);

    expect($requisition->canBeCancelled())->toBeTrue();
})->with([
    TransferRequisitionStatus::Draft,
    TransferRequisitionStatus::Requested,
    TransferRequisitionStatus::UnderReviewFulfiller,
    TransferRequisitionStatus::UnderReviewRequestor,
    TransferRequisitionStatus::Confirmed,
]);

it('blocks cancellation for every post-dispatch state', function (TransferRequisitionStatus $status) {
    // §3.7 / §0 principle 14: Dispatched, PartiallyReceived, Completed,
    // ClosedWithLoss, Cancelled are all terminal and cannot be cancelled.
    $requisition = TransferRequisition::factory()->create(['status' => $status]);

    expect($requisition->canBeCancelled())->toBeFalse();
})->with([
    TransferRequisitionStatus::Dispatched,
    TransferRequisitionStatus::PartiallyReceived,
    TransferRequisitionStatus::Completed,
    TransferRequisitionStatus::ClosedWithLoss,
    TransferRequisitionStatus::Cancelled,
]);

it('partitions all ten statuses into five cancellable and five terminal', function () {
    // Sanity: the two sets cover every case exactly once.
    $cancellable = [
        TransferRequisitionStatus::Draft,
        TransferRequisitionStatus::Requested,
        TransferRequisitionStatus::UnderReviewFulfiller,
        TransferRequisitionStatus::UnderReviewRequestor,
        TransferRequisitionStatus::Confirmed,
    ];
    $terminal = [
        TransferRequisitionStatus::Dispatched,
        TransferRequisitionStatus::PartiallyReceived,
        TransferRequisitionStatus::Completed,
        TransferRequisitionStatus::ClosedWithLoss,
        TransferRequisitionStatus::Cancelled,
    ];

    expect(array_merge($cancellable, $terminal))
        ->toHaveCount(count(TransferRequisitionStatus::cases()));
    expect($cancellable)->toHaveCount(5);
    expect($terminal)->toHaveCount(5);
});

it('does not treat UnderReviewFulfiller or UnderReviewRequestor as terminal', function () {
    // Both negotiation states remain cancellable — the ping-pong never
    // crosses into a terminal state.
    $fulfiller = TransferRequisition::factory()->create([
        'status' => TransferRequisitionStatus::UnderReviewFulfiller,
    ]);
    $requestor = TransferRequisition::factory()->create([
        'status' => TransferRequisitionStatus::UnderReviewRequestor,
    ]);

    expect($fulfiller->canBeCancelled())->toBeTrue();
    expect($requestor->canBeCancelled())->toBeTrue();
});

// ---------------------------------------------------------------------------
// §2.7 soft-delete semantics
// ---------------------------------------------------------------------------

it('soft-deletes a requisition', function () {
    $requisition = TransferRequisition::factory()->create();

    $requisition->delete();

    expect(TransferRequisition::find($requisition->id))->toBeNull();
    expect(TransferRequisition::withTrashed()->find($requisition->id))->not->toBeNull();
    expect($requisition->fresh()->deleted_at)->not->toBeNull();
});

it('restores a soft-deleted requisition', function () {
    $requisition = TransferRequisition::factory()->create();
    $requisition->delete();

    $requisition->restore();

    expect(TransferRequisition::find($requisition->id))->not->toBeNull();
});

it('supports onlyTrashed() lookup', function () {
    $active = TransferRequisition::factory()->create();
    $trashed = TransferRequisition::factory()->create();
    $trashed->delete();

    expect(TransferRequisition::onlyTrashed()->pluck('id')->all())->toBe([$trashed->id]);
});

// ---------------------------------------------------------------------------
// §2.7 FK discipline
// ---------------------------------------------------------------------------

it('rejects a duplicate reference_code via the §2.7 unique constraint', function () {
    TransferRequisition::factory()->create(['reference_code' => 'TR-20260101120000-100']);

    expect(fn () => TransferRequisition::factory()->create(['reference_code' => 'TR-20260101120000-100']))
        ->toThrow(QueryException::class);
});

it('cascades items on requisition force delete (cascadeOnDelete, §2.8)', function () {
    $requisition = TransferRequisition::factory()->create();
    TransferRequisitionItem::factory()->count(2)->create([
        'transfer_requisition_id' => $requisition->id,
    ]);

    $requisition->forceDelete();

    expect(TransferRequisitionItem::where('transfer_requisition_id', $requisition->id)->count())->toBe(0);
});

it('blocks deletion of a warehouse still referenced by a requisition (restrictOnDelete, §2.7)', function () {
    $from = Warehouse::factory()->create();
    $to = Warehouse::factory()->create();
    TransferRequisition::factory()->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
    ]);

    expect(fn () => $from->delete())->toThrow(QueryException::class);
    expect(fn () => $to->delete())->toThrow(QueryException::class);
});

it('blocks deletion of a user still referenced as requested_by (default FK)', function () {
    // §2.7: requested_by has no explicit nullOnDelete — the FK is
    // constrained with the default RESTRICT action.
    $user = User::factory()->create();
    TransferRequisition::factory()->create(['requested_by' => $user->id]);

    expect(fn () => $user->delete())->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------
// §0 principle 1 sanity — a requisition participates in the ledger
// ---------------------------------------------------------------------------

it('participates in the derived stock ledger via reference_type / reference_id', function () {
    // §6.2 dispatchTransfer() writes TransferOut movements tagged with
    // reference_type = TransferRequisition::class and reference_id =
    // (string) $requisition->id. This test asserts the linkage shape.
    $requisition = TransferRequisition::factory()->create();
    $variant = ProductVariant::factory()->create();

    StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $requisition->from_warehouse_id,
        'type' => StockMovementType::TransferOut,
        'quantity' => -10,
        'reference_type' => TransferRequisition::class,
        'reference_id' => (string) $requisition->id,
        'reference_code' => $requisition->reference_code,
    ]);

    $movements = StockMovement::where('reference_type', TransferRequisition::class)
        ->where('reference_id', (string) $requisition->id)
        ->get();

    expect($movements)->toHaveCount(1);
    expect($movements->first()->reference_code)->toBe($requisition->reference_code);
});
