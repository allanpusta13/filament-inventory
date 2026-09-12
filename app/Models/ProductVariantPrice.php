<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariantPrice extends Model
{
    /** @use HasFactory<\Database\Factories\ProductVariantPriceFactory> */
    use HasFactory;

    protected $fillable = [
        'product_variant_id',
        'cost_price',
        'sale_price',
        'effective_from',
        'is_current',
        'set_by',
        'notes',
    ];

    /**
     * Record a new price as current, unsetting whatever was current before it.
     * Wrap in a transaction at the call site if combined with other writes.
     */
    public static function recordNewPrice(ProductVariant $variant, float $costPrice, float $salePrice, ?int $setBy = null, ?string $notes = null): self
    {
        static::where('product_variant_id', $variant->id)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        return static::create([
            'product_variant_id' => $variant->id,
            'cost_price' => $costPrice,
            'sale_price' => $salePrice,
            'effective_from' => now(),
            'is_current' => true,
            'set_by' => $setBy,
            'notes' => $notes,
        ]);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:4',
            'sale_price' => 'decimal:4',
            'effective_from' => 'datetime',
            'is_current' => 'boolean',
        ];
    }
}
