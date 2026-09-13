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
        'proposed_unit_ratio',
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

    public function transferRequisitionItem(): BelongsTo
    {
        return $this->belongsTo(TransferRequisitionItem::class, 'transfer_requisition_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function substituteProductVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'substitute_product_variant_id');
    }

    /**
     * Revision one countering, if any. Null means
     * opening proposal in negotiation thread item.
     */
    public function respondsTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'responds_to_revision_id');
    }

    /**
     * Revisions countered by this one.
     */
    public function counters(): HasMany
    {
        return $this->hasMany(self::class, 'responds_to_revision_id');
    }

    public function isResolved(): bool
    {
        return in_array($this->status, [
            RevisionStatus::Accepted,
            RevisionStatus::Rejected,
        ], true);
    }

    /**
     * Create a counter-proposal revision.
     */
    public function counterWith(array $attributes): self
    {
        $this->update(['status' => RevisionStatus::Superseded, 'responded_at' => now()]);

        return self::create(array_merge($attributes, [
            'transfer_requisition_item_id' => $this->transfer_requisition_item_id,
            'responds_to_revision_id' => $this->id,
            'side' => $this->side->opposite(),
            'status' => RevisionStatus::Pending,
        ]));
    }

    /**
     * Get the root revision of the negotiation thread.
     */
    public function threadRoot(): self
    {
        $revision = $this;
        while ($revision->respondsTo) {
            $revision = $revision->respondsTo;
        }
        return $revision;
    }

    public function accept(): void
    {
        if ($this->isResolved()) {
            throw new \Exception("Revision {$this->id} is already resolved ({$this->status->value}).");
        }

        $this->update(['status' => RevisionStatus::Accepted, 'responded_at' => now()]);
    }

    public function reject(): void
    {
        if ($this->isResolved()) {
            throw new \Exception("Revision {$this->id} is already resolved ({$this->status->value})..");
        }

        $this->update(['status' => RevisionStatus::Rejected, 'responded_at' => now()]);
    }

    public function casts(): array
    {
        return [
            'proposed_unit_ratio' => 'integer',
            'proposed_qty' => 'integer',
            'proposed_base_qty' => 'integer',
            'side' => NegotiationSide::class,
            'status' => RevisionStatus::class,
            'responded_at' => 'datetime',
        ];
    }
}