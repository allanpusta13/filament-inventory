<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class WarehouseStock extends Model
{
    protected $table = 'warehouse_stock';

    protected $fillable = ['variant_id', 'warehouse_id', 'on_hand_quantity', 'reserved_quantity'];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function getAvailableQuantityAttribute()
    {
        return $this->on_hand_quantity - $this->reserved_quantity;
    }
}
