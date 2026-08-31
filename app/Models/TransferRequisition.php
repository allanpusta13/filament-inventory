<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransferRequisitionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TransferRequisition extends Model
{
    use HasFactory;

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

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'status' => TransferRequisitionStatus::class,
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public static function generateReferenceCode(): string
    {
        $year = date('Y');
        $last = self::where('reference_code', 'like', "TRQ-{$year}-%")
            ->orderByDesc('reference_code')
            ->value('reference_code');

        $sequence = $last ? ((int) mb_substr($last, -4) + 1) : 1;

        return sprintf('TRQ-%s-%04d', $year, $sequence);
    }

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
}
