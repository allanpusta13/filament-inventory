<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InTransitStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InTransit extends Model
{
    use HasFactory;

    protected $table = 'in_transit';

    protected $fillable = [
        'requisition_id',
        'requisition_item_id',
        'variant_id',
        'dispatched_base_qty',
        'dispatched_at',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'dispatched_base_qty' => 'integer',
        'dispatched_at' => 'datetime',
        'status' => InTransitStatus::class,
    ];

    /**
     * @return BelongsTo<TransferRequisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(TransferRequisition::class, 'requisition_id');
    }

    /**
     * @return BelongsTo<TransferRequisitionItem, $this>
     */
    public function requisitionItem(): BelongsTo
    {
        return $this->belongsTo(TransferRequisitionItem::class, 'requisition_item_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
