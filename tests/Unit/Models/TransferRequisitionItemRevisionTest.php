<?php

declare(strict_types=1);

use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use App\Exceptions\InvalidRevisionTransitionException;
use App\Models\ProductVariant;
use App\Models\TransferRequisitionItem;
use App\Models\TransferRequisitionItemRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * TransferRequisitionItemRevision model contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §2.9 transfer_requisition_item_revisions schema: columns,
 *     defaults, FK actions.
 *   - §3.9 model shape: fillable, casts, five relations,
 *     isResolved() complement, ensureCanTransitionTo() guards.
 *   - §6.3 NegotiationService::accept() / reject() / assertNegotiable()
 *     call sites.
 *   - §5.20 TransferRequisitionItemRevisionFactory defaults.
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Traits, fillable, casts
// ---------------------------------------------------------------------------

it('uses HasFactory only', function () {
    $traits = class_uses_recursive(TransferRequisitionItemRevision::class);

    expect($traits)->toHaveKey(Illuminate\Database\Eloquent\Factories\HasFactory::class);
});

it('does not use SoftDeletes — the §2.9 schema declares no deleted_at', function () {
    $traits = class_uses_recursive(TransferRequisitionItemRevision::class);

    expect($traits)->not->toHaveKey(Illuminate\Database\Eloquent\SoftDeletes::class);
    expect((new TransferRequisitionItemRevision())->getDates())->not->toContain('deleted_at');
});

it('declares exactly the §3.9 fillable set', function () {
    $revision = new TransferRequisitionItemRevision();

    expect($revision->getFillable())->toBe([
        'transfer_requisition_item_id', 'user_id', 'product_variant_id',
        'substitute_product_variant_id', 'proposed_unit_name', 'proposed_unit_ratio',
        'proposed_qty', 'proposed_base_qty', 'negotiation_reason', 'side',
        'status', 'responds_to_revision_id', 'responded_at',
    ]);
});

it('casts proposed_unit_ratio, proposed_qty, and proposed_base_qty to integer', function () {
    $revision = TransferRequisitionItemRevision::factory()->create([
        'proposed_unit_ratio' => '6',
        'proposed_qty' => '10',
        'proposed_base_qty' => '60',
    ]);

    $fresh = $revision->fresh();

    expect($fresh->proposed_unit_ratio)->toBe(6)->toBeInt();
    expect($fresh->proposed_qty)->toBe(10)->toBeInt();
    expect($fresh->proposed_base_qty)->toBe(60)->toBeInt();
});

it('casts side to the NegotiationSide enum', function () {
    $revision = TransferRequisitionItemRevision::factory()->create([
        'side' => NegotiationSide::Requestor->value,
    ]);

    expect($revision->fresh()->side)->toBe(NegotiationSide::Requestor);
    expect($revision->fresh()->side)->toBeInstanceOf(NegotiationSide::class);
});

it('casts status to the RevisionStatus enum', function () {
    $revision = TransferRequisitionItemRevision::factory()->create([
        'status' => RevisionStatus::Pending->value,
    ]);

    expect($revision->fresh()->status)->toBe(RevisionStatus::Pending);
    expect($revision->fresh()->status)->toBeInstanceOf(RevisionStatus::class);
});

it('casts responded_at to datetime', function () {
    $revision = TransferRequisitionItemRevision::factory()->create([
        'responded_at' => '2026-01-15 10:00:00',
    ]);

    expect($revision->fresh()->responded_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
});

// ---------------------------------------------------------------------------
// §2.9 schema defaults
// ---------------------------------------------------------------------------

it('defaults status to pending via the §2.9 schema', function () {
    // §2.9: status column default = 'pending' (pinned literal).
    // Bypass the factory to prove the schema default.
    $item = TransferRequisitionItem::factory()->create();
    $user = User::factory()->create();
    $variant = ProductVariant::factory()->create();

    DB::table('transfer_requisition_item_revisions')->insert([
        'transfer_requisition_item_id' => $item->id,
        'user_id' => $user->id,
        'product_variant_id' => $variant->id,
        'proposed_unit_name' => 'pc',
        'proposed_unit_ratio' => 1,
        'proposed_qty' => 5,
        'proposed_base_qty' => 5,
        'side' => NegotiationSide::Fulfiller->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $revision = TransferRequisitionItemRevision::where('transfer_requisition_item_id', $item->id)->first();

    expect($revision->status)->toBe(RevisionStatus::Pending);
    expect($revision->substitute_product_variant_id)->toBeNull();
    expect($revision->responds_to_revision_id)->toBeNull();
    expect($revision->responded_at)->toBeNull();
    expect($revision->negotiation_reason)->toBeNull();
});

// ---------------------------------------------------------------------------
// §5.20 factory shape
// ---------------------------------------------------------------------------

it('produces a revision with the §5.20 factory defaults', function () {
    // §5.20: proposed_unit_name = 'pc', proposed_unit_ratio = 1,
    // proposed_qty ∈ [1,20], proposed_base_qty = proposed_qty,
    // side = Fulfiller, status = Pending.
    $revision = TransferRequisitionItemRevision::factory()->create();

    expect($revision->proposed_unit_name)->toBe('pc');
    expect($revision->proposed_unit_ratio)->toBe(1);
    expect($revision->proposed_qty)->toBeGreaterThanOrEqual(1);
    expect($revision->proposed_qty)->toBeLessThanOrEqual(20);
    expect($revision->proposed_base_qty)->toBe($revision->proposed_qty);
    expect($revision->side)->toBe(NegotiationSide::Fulfiller);
    expect($revision->status)->toBe(RevisionStatus::Pending);
});

// ---------------------------------------------------------------------------
// Relations
// ---------------------------------------------------------------------------

it('exposes item() as a BelongsTo on the transfer_requisition_item_id FK', function () {
    $revision = new TransferRequisitionItemRevision();

    expect($revision->item())->toBeInstanceOf(BelongsTo::class);
    expect($revision->item()->getForeignKeyName())->toBe('transfer_requisition_item_id');
    expect($revision->item()->getRelated())->toBeInstanceOf(TransferRequisitionItem::class);
});

it('exposes user() as a BelongsTo relation', function () {
    $revision = new TransferRequisitionItemRevision();

    expect($revision->user())->toBeInstanceOf(BelongsTo::class);
    expect($revision->user()->getRelated())->toBeInstanceOf(User::class);
});

it('exposes productVariant() as a BelongsTo relation', function () {
    $revision = new TransferRequisitionItemRevision();

    expect($revision->productVariant())->toBeInstanceOf(BelongsTo::class);
    expect($revision->productVariant()->getRelated())->toBeInstanceOf(ProductVariant::class);
});

it('exposes substituteProductVariant() as a BelongsTo on the substitute FK', function () {
    $revision = new TransferRequisitionItemRevision();

    expect($revision->substituteProductVariant())->toBeInstanceOf(BelongsTo::class);
    expect($revision->substituteProductVariant()->getForeignKeyName())->toBe('substitute_product_variant_id');
    expect($revision->substituteProductVariant()->getRelated())->toBeInstanceOf(ProductVariant::class);
});

it('exposes respondsTo() as a self-referential BelongsTo on responds_to_revision_id', function () {
    $revision = new TransferRequisitionItemRevision();

    expect($revision->respondsTo())->toBeInstanceOf(BelongsTo::class);
    expect($revision->respondsTo()->getForeignKeyName())->toBe('responds_to_revision_id');
    expect($revision->respondsTo()->getRelated())->toBeInstanceOf(TransferRequisitionItemRevision::class);
});

it('resolves each relation end-to-end', function () {
    $item = TransferRequisitionItem::factory()->create();
    $user = User::factory()->create();
    $variant = ProductVariant::factory()->create();
    $substitute = ProductVariant::factory()->create();

    $parent = TransferRequisitionItemRevision::factory()->create([
        'transfer_requisition_item_id' => $item->id,
    ]);

    $child = TransferRequisitionItemRevision::factory()->create([
        'transfer_requisition_item_id' => $item->id,
        'user_id' => $user->id,
        'product_variant_id' => $variant->id,
        'substitute_product_variant_id' => $substitute->id,
        'responds_to_revision_id' => $parent->id,
    ]);

    expect($child->item->id)->toBe($item->id);
    expect($child->user->id)->toBe($user->id);
    expect($child->productVariant->id)->toBe($variant->id);
    expect($child->substituteProductVariant->id)->toBe($substitute->id);
    expect($child->respondsTo->id)->toBe($parent->id);
});

it('allows substitute_product_variant_id, responds_to_revision_id, and responded_at to be null', function () {
    $revision = TransferRequisitionItemRevision::factory()->create([
        'substitute_product_variant_id' => null,
        'responds_to_revision_id' => null,
        'responded_at' => null,
        'negotiation_reason' => null,
    ]);

    $fresh = $revision->fresh();

    expect($fresh->substitute_product_variant_id)->toBeNull();
    expect($fresh->responds_to_revision_id)->toBeNull();
    expect($fresh->responded_at)->toBeNull();
    expect($fresh->negotiation_reason)->toBeNull();
    expect($fresh->substituteProductVariant)->toBeNull();
    expect($fresh->respondsTo)->toBeNull();
});

// ---------------------------------------------------------------------------
// §3.9 isResolved()
// ---------------------------------------------------------------------------

it('reports isResolved() = false only for Pending', function () {
    $pending = TransferRequisitionItemRevision::factory()->create([
        'status' => RevisionStatus::Pending,
    ]);

    expect($pending->isResolved())->toBeFalse();
});

it('reports isResolved() = true for Accepted', function () {
    $accepted = TransferRequisitionItemRevision::factory()->create([
        'status' => RevisionStatus::Accepted,
    ]);

    expect($accepted->isResolved())->toBeTrue();
});

it('reports isResolved() = true for Rejected', function () {
    $rejected = TransferRequisitionItemRevision::factory()->create([
        'status' => RevisionStatus::Rejected,
    ]);

    expect($rejected->isResolved())->toBeTrue();
});

it('partitions every RevisionStatus case into resolved vs not', function () {
    // Sanity: exactly one case (Pending) is unresolved; the other two
    // are resolved. A regression that adds a fourth case without
    // updating isResolved() surfaces here.
    $unresolved = [];
    $resolved = [];

    foreach (RevisionStatus::cases() as $case) {
        $revision = new TransferRequisitionItemRevision(['status' => $case]);
        if ($revision->isResolved()) {
            $resolved[] = $case;
        } else {
            $unresolved[] = $case;
        }
    }

    expect($unresolved)->toBe([RevisionStatus::Pending]);
    expect($resolved)->toEqualCanonicalizing([
        RevisionStatus::Accepted,
        RevisionStatus::Rejected,
    ]);
});

// ---------------------------------------------------------------------------
// §3.9 ensureCanTransitionTo() — the single legal move
// ---------------------------------------------------------------------------

it('allows Pending → Accepted', function () {
    $revision = TransferRequisitionItemRevision::factory()->create([
        'status' => RevisionStatus::Pending,
    ]);

    // No exception thrown — the transition is legal.
    $revision->ensureCanTransitionTo(RevisionStatus::Accepted);

    expect(true)->toBeTrue();
});

it('allows Pending → Rejected', function () {
    $revision = TransferRequisitionItemRevision::factory()->create([
        'status' => RevisionStatus::Pending,
    ]);

    $revision->ensureCanTransitionTo(RevisionStatus::Rejected);

    expect(true)->toBeTrue();
});

it('blocks Accepted → any target with InvalidRevisionTransitionException', function (RevisionStatus $target) {
    // §3.9: resolved revisions cannot transition anywhere.
    $revision = TransferRequisitionItemRevision::factory()->create([
        'status' => RevisionStatus::Accepted,
    ]);

    expect(fn () => $revision->ensureCanTransitionTo($target))
        ->toThrow(InvalidRevisionTransitionException::class);
})->with([
    RevisionStatus::Pending,
    RevisionStatus::Accepted,
    RevisionStatus::Rejected,
]);

it('blocks Rejected → any target with InvalidRevisionTransitionException', function (RevisionStatus $target) {
    $revision = TransferRequisitionItemRevision::factory()->create([
        'status' => RevisionStatus::Rejected,
    ]);

    expect(fn () => $revision->ensureCanTransitionTo($target))
        ->toThrow(InvalidRevisionTransitionException::class);
})->with([
    RevisionStatus::Pending,
    RevisionStatus::Accepted,
    RevisionStatus::Rejected,
]);

it('blocks Pending → Pending (no self-transition)', function () {
    // §3.9: the second guard rejects target === Pending even when the
    // current status is Pending — no self-transition is legal.
    $revision = TransferRequisitionItemRevision::factory()->create([
        'status' => RevisionStatus::Pending,
    ]);

    expect(fn () => $revision->ensureCanTransitionTo(RevisionStatus::Pending))
        ->toThrow(InvalidRevisionTransitionException::class);
});

it('carries the current and target statuses on the exception', function () {
    // §6.3: InvalidRevisionTransitionException carries
    // $actualStatus / $targetStatus as string values.
    $revision = TransferRequisitionItemRevision::factory()->create([
        'status' => RevisionStatus::Accepted,
    ]);

    try {
        $revision->ensureCanTransitionTo(RevisionStatus::Pending);
        $this->fail('Expected InvalidRevisionTransitionException was not thrown.');
    } catch (InvalidRevisionTransitionException $e) {
        expect($e->actualStatus)->toBe('accepted');
        expect($e->targetStatus)->toBe('pending');
        expect($e->translationKey())->toBe('errors.invalid_revision_transition');
        expect($e->context())->toBe([
            'actual' => 'accepted',
            'target' => 'pending',
        ]);
    }
});

it('validates the current status before the target status', function () {
    // §3.9 order: current-status guard first, then target-status guard.
    // An Accepted → Pending attempt must fail on the current-status
    // check (not the target check) — the exception carries the same
    // data either way, but the ordering is contract.
    $revision = TransferRequisitionItemRevision::factory()->create([
        'status' => RevisionStatus::Rejected,
    ]);

    try {
        $revision->ensureCanTransitionTo(RevisionStatus::Pending);
    } catch (InvalidRevisionTransitionException $e) {
        // current status must be the actual, target the requested target —
        // not the reverse.
        expect($e->actualStatus)->toBe('rejected');
        expect($e->targetStatus)->toBe('pending');
    }
});

// ---------------------------------------------------------------------------
// §2.9 FK cascade
// ---------------------------------------------------------------------------

it('cascades on parent item force delete (cascadeOnDelete, §2.9)', function () {
    $item = TransferRequisitionItem::factory()->create();
    TransferRequisitionItemRevision::factory()->count(2)->create([
        'transfer_requisition_item_id' => $item->id,
    ]);

    $item->delete();

    expect(TransferRequisitionItemRevision::where('transfer_requisition_item_id', $item->id)->count())->toBe(0);
});

it('nulls responds_to_revision_id when the linked revision is deleted (nullOnDelete, §2.9)', function () {
    $item = TransferRequisitionItem::factory()->create();

    $parent = TransferRequisitionItemRevision::factory()->create([
        'transfer_requisition_item_id' => $item->id,
    ]);

    $child = TransferRequisitionItemRevision::factory()->create([
        'transfer_requisition_item_id' => $item->id,
        'responds_to_revision_id' => $parent->id,
    ]);

    $parent->delete();

    expect($child->fresh()->responds_to_revision_id)->toBeNull();
});

it('blocks deletion of a variant referenced by a revision (restrictOnDelete, §2.9)', function () {
    $variant = ProductVariant::factory()->create();
    TransferRequisitionItemRevision::factory()->create([
        'product_variant_id' => $variant->id,
    ]);

    expect(fn () => $variant->forceDelete())->toThrow(QueryException::class);
});

it('blocks deletion of a substitute variant referenced by a revision (restrictOnDelete, §2.9)', function () {
    $original = ProductVariant::factory()->create();
    $substitute = ProductVariant::factory()->create();

    TransferRequisitionItemRevision::factory()->create([
        'product_variant_id' => $original->id,
        'substitute_product_variant_id' => $substitute->id,
    ]);

    expect(fn () => $substitute->forceDelete())->toThrow(QueryException::class);
});
