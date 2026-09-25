<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectTransferItem extends Model
{
    /** @use HasFactory<\Database\Factories\DirectTransferItemFactory> */
    use HasFactory;

    protected $fillable = [
        'direct_transfer_id',
        'product_variant_id',
        'unit_name',
        'unit_ratio',
        'qty',
        'base_qty',
        'notes',
    ];

    protected $casts = [
        'unit_ratio' => 'integer',
        'qty' => 'integer',
        'base_qty' => 'integer',
    ];

    public function directTransfer(): BelongsTo
    {
        return $this->belongsTo(DirectTransfer::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
