<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TransferOrderItem extends Model
{
    use HasFactory;

    #[Fillable([
        'transfer_order_id',
        'product_id',
        'requested_quantity',
        'approved_quantity',
        'received_quantity',
        'damaged_quantity',
        'item_status',
        'added_by_branch_id',
        'variance_reason',
    ])]
    protected $fillable = [
        'transfer_order_id',
        'product_id',
        'requested_quantity',
        'approved_quantity',
        'received_quantity',
        'damaged_quantity',
        'item_status',
        'added_by_branch_id',
        'variance_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'requested_quantity' => 'integer',
        'approved_quantity' => 'integer',
        'received_quantity' => 'integer',
        'damaged_quantity' => 'integer',
    ];

    /**
     * @return BelongsTo<TransferOrder, $this>
     */
    public function transferOrder(): BelongsTo
    {
        return $this->belongsTo(TransferOrder::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function addedByBranch(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'added_by_branch_id');
    }

    /**
     * Compute lost quantity: approved - (received + damaged).
     * This is a derived value, never stored.
     */
    public function getLostQuantityAttribute(): ?int
    {
        if ($this->approved_quantity === null || $this->received_quantity === null) {
            return null;
        }

        $received = $this->received_quantity ?? 0;
        $damaged = $this->damaged_quantity ?? 0;

        return max(0, $this->approved_quantity - ($received + $damaged));
    }
}
