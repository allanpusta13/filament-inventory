<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class StockMovement extends Model
{
    use HasFactory;

    #[Fillable(['variant_id', 'warehouse_id', 'type', 'quantity', 'unit_name_used', 'unit_ratio_used', 'related_movement_id', 'reference_type', 'reference_id', 'reference_code', 'created_by'])]
    protected $fillable = [
        'variant_id',
        'warehouse_id',
        'type',
        'quantity',
        'unit_name_used',
        'unit_ratio_used',
        'related_movement_id',
        'reference_type',
        'reference_id',
        'reference_code',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'quantity' => 'integer',
        'unit_ratio_used' => 'integer',
        'type' => MovementType::class,
    ];

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
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

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
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
