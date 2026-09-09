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

    protected function casts(): array
    {
        return [
            'status' => InTransitStatus::class,
            'dispatched_base_qty' => 'integer',
            'dispatched_at' => 'datetime',
        ];
    }

    public function transferRequisition(): BelongsTo
    {
        return $this->belongsTo(TransferRequisition::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(TransferRequisitionItem::class, 'transfer_requisition_item_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
