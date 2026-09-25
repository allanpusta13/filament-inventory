<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    /** @use HasFactory<\Database\Factories\WarehouseFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'location',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_warehouse');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function transferRequisitionsFrom(): HasMany
    {
        return $this->hasMany(TransferRequisition::class, 'from_warehouse_id');
    }

    public function transferRequisitionsTo(): HasMany
    {
        return $this->hasMany(TransferRequisition::class, 'to_warehouse_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function lossLedgers(): HasMany
    {
        return $this->hasMany(LossLedger::class);
    }

    public function directTransfersFrom(): HasMany
    {
        return $this->hasMany(DirectTransfer::class, 'from_warehouse_id');
    }

    public function directTransfersTo(): HasMany
    {
        return $this->hasMany(DirectTransfer::class, 'to_warehouse_id');
    }
}
