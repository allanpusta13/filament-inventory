<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LossLedger extends Model
{
    /** @use HasFactory<\Database\Factories\LossLedgerFactory> */
    use HasFactory;

    protected $fillable = [
        'transfer_requisition_id',
        'transfer_requisition_item_id',
        'product_variant_id',
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
     * unit_cost_price is a snapshot taken at incident time — pull it from the
     * variant's ProductPrice::currentPrice() when creating this record, since
     * ProductVariant no longer carries cost_price directly.
     */
    public static function snapshotUnitCostFrom(ProductVariant $variant): ?string
    {
        return $variant->currentPrice?->cost_price;
    }

    public function transferRequisition(): BelongsTo
    {
        return $this->belongsTo(TransferRequisition::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(TransferRequisitionItem::class, 'transfer_requisition_item_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    protected function casts(): array
    {
        return [
            'lost_base_qty' => 'integer',
            'damaged_base_qty' => 'integer',
            'unit_cost_price' => 'decimal:4',
            'total_financial_loss' => 'decimal:4',
            'recorded_at' => 'datetime',
        ];
    }
}
