<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransferRequisitionStatus;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class TransferRequisition extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_code',
        'from_warehouse_id',
        'to_warehouse_id',
        'status',
        'requested_by',
        'approved_by',
        'dispatched_by',
        'received_by',
        'requested_at',
        'approved_at',
        'dispatched_at',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'status' => TransferRequisitionStatus::class,
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return HasMany<TransferRequisitionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TransferRequisitionItem::class, 'requisition_id');
    }

    protected static function booted(): void
    {
        self::creating(function (TransferRequisition $requisition): void {
            if (empty($requisition->reference_code)) {
                $year = date('Y');
                $last = static::whereYear('created_at', $year)->count() + 1;
                $requisition->reference_code = 'TRQ-'.$year.'-'.mb_str_pad((string) $last, 4, '0', STR_PAD_LEFT);
            }
            if (is_null($requisition->requested_by)) {
                $requisition->requested_by = auth()->id();
            }
            if (is_null($requisition->requested_at)) {
                $requisition->requested_at = now();
            }
        });

        self::deleting(function (TransferRequisition $requisition) {
            if ($requisition->status === 'dispatched') {
                throw new Exception('Cannot delete a requisition that is currently in transit (Dispatched status).');
            }

            if ($requisition->status === 'confirmed') {
                \Illuminate\Support\Facades\DB::transaction(function () use ($requisition) {
                    foreach ($requisition->items as $item) {
                        $stock = WarehouseStock::where('variant_id', $item->variant_id)
                            ->where('warehouse_id', $requisition->from_warehouse_id)
                            ->lockForUpdate()
                            ->first();

                        if ($stock) {
                            $releaseQty = $item->approved_base_qty ?? $item->requested_base_qty;
                            $stock->reserved_quantity = max(0, $stock->reserved_quantity - $releaseQty);
                            $stock->save();
                        }
                    }
                });
            }
        });
    }
}
