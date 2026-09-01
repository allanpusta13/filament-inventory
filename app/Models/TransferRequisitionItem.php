<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TransferRequisitionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'requisition_id',
        'variant_id',
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
        'substitute_variant_id',
        'notes',
    ];

    /**
     * @return BelongsTo<TransferRequisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(TransferRequisition::class, 'requisition_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function substituteVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'substitute_variant_id');
    }
}
