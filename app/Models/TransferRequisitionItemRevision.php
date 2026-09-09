<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'proposed_qty',
        'proposed_base_qty',
        'negotiation_reason',
        'side',
        'status',
        'responds_to_revision_id',
        'responded_at',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(TransferRequisitionItem::class, 'transfer_requisition_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function substituteVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'substitute_product_variant_id');
    }

    /**
     * The revision this one is countering, if any. Null means this is the
     * opening proposal in the negotiation thread for its item.
     */
    public function respondsTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'responds_to_revision_id');
    }

    /**
     * Revisions that countered this one.
     */
    public function counters(): HasMany
    {
        return $this->hasMany(self::class, 'responds_to_revision_id');
    }

    public function accept(): void
    {
        $this->update(['status' => RevisionStatus::Accepted, 'responded_at' => now()]);
    }

    public function reject(): void
    {
        $this->update(['status' => RevisionStatus::Rejected, 'responded_at' => now()]);
    }

    /**
     * Record a counter-proposal against this revision. Marks this one as
     * superseded and returns the new revision, threaded via responds_to_revision_id.
     */
    public function counterWith(array $attributes): self
    {
        $this->update(['status' => RevisionStatus::Superseded, 'responded_at' => now()]);

        return self::create(array_merge($attributes, [
            'transfer_requisition_item_id' => $this->transfer_requisition_item_id,
            'responds_to_revision_id' => $this->id,
            'status' => RevisionStatus::Pending,
        ]));
    }

    /**
     * Walk the thread back to its opening proposal (responds_to_revision_id is null).
     */
    public function threadRoot(): self
    {
        $node = $this;
        while ($node->responds_to_revision_id !== null) {
            $node = $node->respondsTo()->firstOrFail();
        }

        return $node;
    }

    protected function casts(): array
    {
        return [
            'proposed_qty' => 'integer',
            'proposed_base_qty' => 'integer',
            'side' => NegotiationSide::class,
            'status' => RevisionStatus::class,
            'responded_at' => 'datetime',
        ];
    }
}
