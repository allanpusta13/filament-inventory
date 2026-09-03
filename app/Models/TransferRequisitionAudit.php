<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TransferRequisitionAudit extends Model
{
    use HasFactory;

    #[Fillable([
        'transfer_requisition_id',
        'user_id',
        'action',
        'changes_payload',
    ])]
    protected $fillable = [
        'transfer_requisition_id',
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
     * @return BelongsTo<TransferRequisition, $this>
     */
    public function transferRequisition(): BelongsTo
    {
        return $this->belongsTo(TransferRequisition::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
