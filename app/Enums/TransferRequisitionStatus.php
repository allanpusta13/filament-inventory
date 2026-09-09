<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TransferRequisitionStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Requested = 'requested';
    case UnderReviewFulfiller = 'under_review_fulfiller';
    case UnderReviewRequestor = 'under_review_requestor';
    case Confirmed = 'confirmed';
    case Dispatched = 'dispatched';
    case PartiallyReceived = 'partially_received';
    case Completed = 'completed';
    case ClosedWithLoss = 'closed_with_loss';

    public function label(): string
    {
        return $this->getLabel();
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::ClosedWithLoss => true,
            default => false,
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Requested => 'info',
            self::UnderReviewFulfiller, self::UnderReviewRequestor => 'warning',
            self::Confirmed => 'success',
            self::Dispatched => 'primary',
            self::PartiallyReceived => 'warning',
            self::Completed => 'success',
            self::ClosedWithLoss => 'danger',
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
            self::PartiallyReceived => 'Partially Received',
            self::Completed => 'Completed',
            self::ClosedWithLoss => 'Closed with Loss',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Draft => 'heroicon-o-document',
            self::Requested => 'heroicon-o-arrow-right',
            self::UnderReviewFulfiller, self::UnderReviewRequestor => 'heroicon-o-chart-pie',
            self::Confirmed => 'heroicon-o-check-circle',
            self::Dispatched => 'heroicon-o-truck',
            self::PartiallyReceived => 'heroicon-o-chart-bar',
            self::Completed => 'heroicon-o-check-circle',
            self::ClosedWithLoss => 'heroicon-o-x-circle',
        };
    }
}
