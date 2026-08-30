<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TransferOrderAudit extends Model
{
    use HasFactory;

    #[Fillable([
        'transfer_order_id',
        'user_id',
        'action',
        'changes_payload',
    ])]
    protected $fillable = [
        'transfer_order_id',
        'user_id',
        'action',
        'changes_payload',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'changes_payload' => 'array',
    ];

    /**
     * @return BelongsTo<TransferOrder, $this>
     */
    public function transferOrder(): BelongsTo
    {
        return $this->belongsTo(TransferOrder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
