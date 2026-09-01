<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LossLedger extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'requisition_id',
        'requisition_item_id',
        'variant_id',
        'warehouse_id',
        'lost_base_qty',
        'damaged_base_qty',
        'unit_cost_price',
        'total_financial_loss',
        'loss_category',
        'recorded_by',
        'recorded_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'unit_cost_price' => 'decimal:4',
        'total_financial_loss' => 'decimal:4',
        'recorded_at' => 'datetime',
    ];

    /**
     * Get the requisition associated with the loss ledger.
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(TransferRequisition::class);
    }

    /**
     * Get the requisition item associated with the loss ledger.
     */
    public function requisitionItem(): BelongsTo
    {
        return $this->belongsTo(TransferRequisitionItem::class);
    }

    /**
     * Get the variant associated with the loss ledger.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Get the warehouse associated with the loss ledger.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the user who recorded the loss.
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
