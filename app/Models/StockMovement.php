<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StockMovement extends Model
{
    #[Fillable(['product_id', 'warehouse_id', 'type', 'quantity', 'related_movement_id', 'reference', 'created_by'])]
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'type',
        'quantity',
        'related_movement_id',
        'reference',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'type' => 'string',
        'quantity' => 'integer',
    ];

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
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<StockMovement, $this>
     */
    public function relatedMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_movement_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Model event structure for auto-populating created_by
    // Implementation deferred to stage 3
    // protected static function boot(): void
    // {
    //     parent::boot();
    //
    //     static::creating(function (StockMovement $movement) {
    //         if (auth()->check() && is_null($movement->created_by)) {
    //             $movement->created_by = auth()->id();
    //         }
    //     });
    // }
}
