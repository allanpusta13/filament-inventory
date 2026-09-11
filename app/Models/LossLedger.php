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

    protected function casts(): array
    {
        return [
            'lost_base_qty'        => 'integer',
            'damaged_base_qty'     => 'integer',
            'unit_cost_price'      => 'decimal:4',
            'total_financial_loss' => 'decimal:4',
            'recorded_at'          => 'datetime',
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

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * [FIX v10] Snapshots variant's CURRENT cost price at the moment
     * loss/intake is actually processed — not the price at time of
     * original dispatch. This is a deliberate design choice: if cost_price
     * changes mid-transit, loss/damage valuation reflects present-day
     * replacement cost, not historical acquisition cost.
     *
     * Uses null-safe operator (?->) on the bare property chain, because
     * `currentPrice` itself can be null (no is_current=true row exists
     * for the variant) — a plain `??` on the property access would still
     * throw, since PHP evaluates the property access before the null-coalesce
     * is reached.
     *
     * Callers should eager-load `currentPrice` relation on $variant
     * before calling this method to avoid an N+1 query per loss row.
     */
    public static function snapshotUnitCostFrom(ProductVariant $variant): string
    {
        return (string) ($variant->currentPrice?->cost_price ?? '0.0000');
    }
}