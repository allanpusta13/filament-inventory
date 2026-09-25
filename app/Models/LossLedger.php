<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

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

    protected $casts = [
        'lost_base_qty' => 'integer',
        'damaged_base_qty' => 'integer',
        'unit_cost_price' => 'decimal:4',
        'total_financial_loss' => 'decimal:4',
        'recorded_at' => 'datetime',
    ];

    /**
     * Snapshot the current cost price of the variant (Principle 15).
     *
     * If cost is missing or zero, log a warning — the loss will be recorded
     * at zero financial impact but flagged for review.
     */
    public static function snapshotUnitCostFrom(ProductVariant $variant): string
    {
        $cost = $variant->currentPrice?->cost_price;

        if ($cost === null || bccomp((string) $cost, '0.0000', 4) === 0) {
            Log::warning('Loss recorded with missing or zero cost price.', [
                'product_variant_id' => $variant->id,
                'sku' => $variant->sku,
            ]);

            return '0.0000';
        }

        return (string) $cost;
    }

    /**
     * Compute total loss using BCMath to avoid float drift.
     */
    public static function calculateTotalFinancialLoss(string $unitCost, int $totalQty): string
    {
        return bcmul($unitCost, (string) $totalQty, 4);
    }

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

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
