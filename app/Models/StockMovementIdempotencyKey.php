<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovementIdempotencyKey extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'transfer_requisition_id',
        'payload_checksum',
        'resulting_item_states',
        'created_at',
    ];

    protected $casts = [
        'resulting_item_states' => 'array',
        'created_at' => 'datetime',
    ];

    public function transferRequisition(): BelongsTo
    {
        return $this->belongsTo(TransferRequisition::class);
    }
}
