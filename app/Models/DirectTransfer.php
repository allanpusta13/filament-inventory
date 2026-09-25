<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DirectTransfer extends Model
{
    /** @use HasFactory<\Database\Factories\DirectTransferFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_code',
        'from_warehouse_id',
        'to_warehouse_id',
        'transferred_by',
        'notes',
        'transferred_at',
    ];

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DirectTransferItem::class);
    }

    protected function casts(): array
    {
        return [
            'transferred_at' => 'datetime',
        ];
    }
}
