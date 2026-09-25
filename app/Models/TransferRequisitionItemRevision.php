<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use App\Exceptions\InvalidRevisionTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferRequisitionItemRevision extends Model
{
    /** @use HasFactory<\Database\Factories\TransferRequisitionItemRevisionFactory> */
    use HasFactory;

    protected $fillable = [
        'transfer_requisition_item_id',
        'user_id',
        'product_variant_id',
        'substitute_product_variant_id',
        'proposed_unit_name',
        'proposed_unit_ratio',
        'proposed_qty',
        'proposed_base_qty',
        'negotiation_reason',
        'side',
        'status',
        'responds_to_revision_id',
        'responded_at',
    ];

    protected $casts = [
        'proposed_unit_ratio' => 'integer',
        'proposed_qty' => 'integer',
        'proposed_base_qty' => 'integer',
        'side' => NegotiationSide::class,
        'status' => RevisionStatus::class,
        'responded_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(TransferRequisitionItem::class, 'transfer_requisition_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function respondsTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'responds_to_revision_id');
    }

    public function isResolved(): bool
    {
        return $this->status !== RevisionStatus::Pending;
    }

    public function ensureCanTransitionTo(RevisionStatus $target): void
    {
        if ($this->status !== RevisionStatus::Pending) {
            throw new InvalidRevisionTransitionException($this->status->value, $target->value);
        }
        if ($target === RevisionStatus::Pending) {
            throw new InvalidRevisionTransitionException($this->status->value, $target->value);
        }
    }
}
