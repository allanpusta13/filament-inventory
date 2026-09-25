<?php

declare(strict_types=1);

namespace App\Models;

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

    protected $casts = [
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

    public function transferRequisition(): BelongsTo
    {
        return $this->belongsTo(TransferRequisition::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function substituteProductVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'substitute_product_variant_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(TransferRequisitionItemRevision::class);
    }

    public function actualVariantId(): int
    {
        return $this->substitute_product_variant_id ?? $this->product_variant_id;
    }

    public function outstandingShippedBaseQty(): int
    {
        return max(0, (int) $this->approved_base_qty - (int) $this->shipped_base_qty);
    }
}
