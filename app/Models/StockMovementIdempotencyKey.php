<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * StockMovementIdempotencyKey — idempotency record for scan-to-receive.
 *
 * Blueprint §3.22 / §18.4. One row per (requisition, canonical scan
 * payload) pair. Written by `InventoryService::scanToReceive()`
 * (§6.2) BEFORE any ledger write so that a concurrent duplicate scan
 * loses the unique-constraint race cleanly and no-ops instead of
 * double-applying stock movements.
 *
 * The unique constraint on `(transfer_requisition_id, payload_checksum)`
 * (§2.20) is the authority: exactly one caller may create the key for
 * a given (requisition, checksum) pair. The service catches the
 * duplicate-`QueryException` on the losing side, re-reads the winner's
 * key, and returns early.
 *
 * `resulting_item_states` is a JSON snapshot of the requisition items
 * AFTER the scan was applied. It is written to provide an audit trail
 * of the winning scan's effect and to allow a losing request to
 * observe the winner's state without re-computing it. The shape is the
 * array form of `$requisition->items()->get()->toArray()` as captured
 * by `scanToReceive()` (§6.2).
 *
 * `$timestamps = false` — the schema (§2.20) has only `created_at`,
 * not the usual `created_at`/`updated_at` pair. The row is
 * append-only by design; there is no "update the idempotency record"
 * operation.
 *
 * No `SoftDeletes` — the §2.20 schema declares no `deleted_at`.
 * Rows cascade with their parent requisition (§2.20 `cascadeOnDelete`).
 *
 * Factory: `App\Database\Factories\StockMovementIdempotencyKeyFactory`
 *          (§5.21).
 */
class StockMovementIdempotencyKey extends Model
{
    use HasFactory;

    /**
     * Disable the `updated_at` timestamp column.
     *
     * §2.20 / §18.4: the table has only `created_at`. Eloquent would
     * otherwise try to write `updated_at` on every save.
     */
    public $timestamps = false;

    /**
     * Mass-assignable attributes (§2.20).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transfer_requisition_id',
        'payload_checksum',
        'resulting_item_states',
        'created_at',
    ];

    /**
     * Attribute casts (§3.22).
     *
     * `resulting_item_states` is a JSON column cast to array; the
     * service writes the array form of the post-scan item states.
     *
     * `created_at` is cast to datetime explicitly because the model
     * disables timestamps (`$timestamps = false`) — the cast makes the
     * attribute a Carbon instance on read without Eloquent managing
     * writes on save. The service passes `created_at` explicitly on
     * create (via `useCurrent()` on the column, or `now()` in the
     * factory).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'resulting_item_states' => 'array',
        'created_at' => 'datetime',
    ];

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

    public function transferRequisition(): BelongsTo
    {
        return $this->belongsTo(TransferRequisition::class);
    }
}
