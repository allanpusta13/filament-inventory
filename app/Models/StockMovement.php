<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StockMovement extends Model
{
    use HasFactory;

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
        'type' => MovementType::class,
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

    protected static function boot(): void
    {
        parent::boot();

        self::creating(function (StockMovement $movement): void {
            if (auth()->check() && is_null($movement->created_by)) {
                $movement->created_by = auth()->id();
            }
        });
    }
}
