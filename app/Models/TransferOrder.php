<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransferOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

final class TransferOrder extends Model
{
    use HasFactory;

    #[Fillable([
        'reference_number',
        'sender_branch_id',
        'receiver_branch_id',
        'status',
        'notes',
        'driver_name',
        'vehicle_plate',
        'dispatched_by',
        'received_by',
        'dispatched_at',
        'received_at',
    ])]
    protected $fillable = [
        'reference_number',
        'sender_branch_id',
        'receiver_branch_id',
        'status',
        'notes',
        'driver_name',
        'vehicle_plate',
        'dispatched_by',
        'received_by',
        'dispatched_at',
        'received_at',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'status' => TransferOrderStatus::class,
        'dispatched_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    /**
     * Generate a unique reference number in the format TRF-{YYYYMMDD}-{sequence}.
     */
    public static function generateReferenceNumber(): string
    {
        return DB::transaction(function (): string {
            $today = now()->format('Ymd');
            $prefix = "TRF-{$today}-";

            $lastReference = static::where('reference_number', 'like', "{$prefix}%")
                ->orderByDesc('reference_number')
                ->value('reference_number');

            $sequence = 1;

            if ($lastReference !== null) {
                $lastSequence = (int) mb_substr($lastReference, -3);
                $sequence = $lastSequence + 1;
            }

            return $prefix.mb_str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
        });
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'sender_branch_id');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'receiver_branch_id');
    }

    /**
     * @return HasMany<TransferOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TransferOrderItem::class);
    }

    /**
     * @return HasMany<TransferOrderAudit, $this>
     */
    public function audits(): HasMany
    {
        return $this->hasMany(TransferOrderAudit::class);
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
     * Compute in-transit quantity for a specific product across all items in this order.
     * In-transit = SUM(approved_quantity) for items of this product where status is dispatched.
     */
    public function inTransitQuantity(int $productId): int
    {
        if ($this->status !== TransferOrderStatus::Dispatched) {
            return 0;
        }

        $item = $this->items()
            ->where('product_id', $productId)
            ->where('item_status', '!=', 'removed')
            ->first();

        if (! $item) {
            return 0;
        }

        return $item->approved_quantity ?? 0;
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function canBeSubmittedBy(User $user): bool
    {
        return $user->canAccessWarehouse($this->sender);
    }

    public function canBeReviewedBy(User $user): bool
    {
        return $user->canAccessWarehouse($this->receiver);
    }

    public function canBeConfirmedBy(User $user): bool
    {
        return $user->canAccessWarehouse($this->receiver);
    }

    public function canBeDispatchedBy(User $user): bool
    {
        return $user->canAccessWarehouse($this->sender);
    }

    public function canBeReceivedBy(User $user): bool
    {
        return $user->canAccessWarehouse($this->receiver);
    }

    public function canBeCancelledBy(User $user): bool
    {
        return $user->canAccessWarehouse($this->sender) || $user->canAccessWarehouse($this->receiver);
    }
}
