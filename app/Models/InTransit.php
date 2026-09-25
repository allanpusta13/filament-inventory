<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InTransitStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InTransit extends Model
{
    /** @use HasFactory<\Database\Factories\InTransitFactory> */
    use HasFactory;

    protected $fillable = [
        'transfer_requisition_id',
        'transfer_requisition_item_id',
        'product_variant_id',
        'dispatched_base_qty',
        'dispatched_at',
        'status',
    ];

    protected $casts = [
        'dispatched_base_qty' => 'integer',
        'dispatched_at' => 'datetime',
        'status' => InTransitStatus::class,
        'cleared_at' => 'datetime',
    ];

    public function transferRequisition(): BelongsTo
    {
        return $this->belongsTo(TransferRequisition::class);
    }

    public function transferRequisitionItem(): BelongsTo
    {
        return $this->belongsTo(TransferRequisitionItem::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
