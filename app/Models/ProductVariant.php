<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['product_id', 'sku', 'barcode', 'name', 'attributes', 'base_unit_name', 'images', 'cost_price', 'sale_price'];

    protected $casts = [
        'attributes' => 'json',
        'images' => 'json',
        'cost_price' => 'decimal:4',
        'sale_price' => 'decimal:4',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unitConversions()
    {
        return $this->hasMany(ProductUnitConversion::class);
    }

    public function prices()
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function warehouseStocks()
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class, 'variant_id');
    }

    public function stockMovements()
    {
        return $this->movements();
    }

    public function getAvailableQuantityForWarehouse(int $warehouseId): int
    {
        $stock = $this->warehouseStocks()->where('warehouse_id', $warehouseId)->first();

        return $stock ? $stock->available_quantity : 0;
    }
}
