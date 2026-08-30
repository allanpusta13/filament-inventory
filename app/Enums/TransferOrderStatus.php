<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;

enum TransferOrderStatus: string implements HasColor
{
    case Draft = 'draft';
    case Requested = 'requested';
    case UnderReviewFulfiller = 'under_review_fulfiller';
    case UnderReviewRequestor = 'under_review_requestor';
    case Confirmed = 'confirmed';
    case Dispatched = 'dispatched';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Requested => 'warning',
            self::UnderReviewFulfiller => 'info',
            self::UnderReviewRequestor => 'info',
            self::Confirmed => 'success',
            self::Dispatched => 'primary',
            self::Received => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Requested => 'Requested',
            self::UnderReviewFulfiller => 'Under Review (Fulfiller)',
            self::UnderReviewRequestor => 'Under Review (Requestor)',
            self::Confirmed => 'Confirmed',
            self::Dispatched => 'Dispatched',
            self::Received => 'Received',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Requested, self::UnderReviewFulfiller, self::UnderReviewRequestor]);
    }

    public function canBeSubmitted(): bool
    {
        return $this === self::Draft;
    }

    public function canBeReviewed(): bool
    {
        return in_array($this, [self::Requested, self::UnderReviewFulfiller, self::UnderReviewRequestor]);
    }

    public function canBeConfirmed(): bool
    {
        return in_array($this, [self::Requested, self::UnderReviewRequestor]);
    }

    public function canBeDispatched(): bool
    {
        return $this === self::Confirmed;
    }

    public function canBeReceived(): bool
    {
        return $this === self::Dispatched;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this, [self::Draft, self::Requested, self::UnderReviewFulfiller, self::UnderReviewRequestor]);
    }
}
