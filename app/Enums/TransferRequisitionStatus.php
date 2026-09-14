<?php

declare(strict_types=1);

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

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
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Requested => __('Requested'),
            self::UnderReviewFulfiller => __('Under review (fulfiller)'),
            self::UnderReviewRequestor => __('Under review (requestor)'),
            self::Confirmed => __('Confirmed'),
            self::Dispatched => __('Dispatched'),
            self::PartiallyReceived => __('Partially received'),
            self::Completed => __('Completed'),
            self::ClosedWithLoss => __('Closed with loss'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Requested => 'info',
            self::UnderReviewFulfiller, self::UnderReviewRequestor => 'warning',
            self::Confirmed => 'primary',
            self::Dispatched => 'info',
            self::PartiallyReceived => 'warning',
            self::Completed => 'success',
            self::ClosedWithLoss => 'danger',
            self::Cancelled => 'gray',
        };
    }

    public function getIcon(): string|BackedEnum|null
    {
        return match ($this) {
            self::Draft => Heroicon::Pencil,
            self::Requested => Heroicon::PaperAirplane,
            self::UnderReviewFulfiller, self::UnderReviewRequestor => Heroicon::ChatBubbleLeftRight,
            self::Confirmed => Heroicon::CheckCircle,
            self::Dispatched => Heroicon::Truck,
            self::PartiallyReceived => Heroicon::ArchiveBoxArrowDown,
            self::Completed => Heroicon::CheckBadge,
            self::ClosedWithLoss => Heroicon::ExclamationTriangle,
            self::Cancelled => Heroicon::XCircle,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::ClosedWithLoss, self::Cancelled], true);
    }
}
