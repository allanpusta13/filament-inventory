<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RevisionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransferRequisitionItem extends Model
{
    /** @use HasFactory<\Database\Factories\TransferRequisitionItemFactory> */
    use HasFactory;

    protected $fillable = [
        'transfer_requisition_id',
        'product_variant_id',
        'substitute_product_variant_id',
        'requested_unit_name',
        'requested_unit_ratio',
        'requested_qty',
        'requested_base_qty',
        'approved_unit_name',
        'approved_unit_ratio',
        'approved_qty',
        'approved_base_qty',
        'shipped_base_qty',
        'received_good_base_qty',
        'received_damaged_base_qty',
        'received_qty',
        'notes',
    ];

    public function transferRequisition(): BelongsTo
    {
        return $this->belongsTo(TransferRequisition::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function substituteVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'substitute_product_variant_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(TransferRequisitionItemRevision::class);
    }

    /**
     * Full negotiation log for this item, oldest first — every proposal across
     * every thread (there can be more than one root if fulfiller and requestor
     * each open independent proposals before either responds).
     */
    public function negotiationHistory(): HasMany
    {
        return $this->revisions()->orderBy('created_at')->orderBy('id');
    }

    /**
     * Revisions still awaiting a decision.
     */
    public function pendingRevisions(): HasMany
    {
        return $this->revisions()->where('status', RevisionStatus::Pending);
    }

    public function inTransits(): HasMany
    {
        return $this->hasMany(InTransit::class);
    }

    public function lossLedgers(): HasMany
    {
        return $this->hasMany(LossLedger::class);
    }

    /**
     * Shortfall between what was approved/shipped and what actually arrived in good condition.
     */
    public function outstandingBaseQty(): int
    {
        $expected = $this->approved_base_qty ?? $this->requested_base_qty;

        return max(0, $expected - $this->received_good_base_qty);
    }

    protected function casts(): array
    {
        return [
            'requested_unit_ratio' => 'integer',
            'requested_qty' => 'integer',
            'requested_base_qty' => 'integer',
            'approved_unit_ratio' => 'integer',
            'approved_qty' => 'integer',
            'approved_base_qty' => 'integer',
            'shipped_base_qty' => 'integer',
            'received_good_base_qty' => 'integer',
            'received_damaged_base_qty' => 'integer',
            'received_qty' => 'integer',
        ];
    }
}
