<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Product extends Model
{
    use HasFactory;

    #[Fillable(['sku', 'name', 'category', 'unit', 'reorder_point'])]
    protected $fillable = [
        'sku',
        'name',
        'category',
        'unit',
        'reorder_point',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'reorder_point' => 'integer',
    ];

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
