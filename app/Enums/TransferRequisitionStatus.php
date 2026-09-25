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
            self::Draft => __('enums.transfer_requisition_status.draft'),
            self::Requested => __('enums.transfer_requisition_status.requested'),
            self::UnderReviewFulfiller => __('enums.transfer_requisition_status.under_review_fulfiller'),
            self::UnderReviewRequestor => __('enums.transfer_requisition_status.under_review_requestor'),
            self::Confirmed => __('enums.transfer_requisition_status.confirmed'),
            self::Dispatched => __('enums.transfer_requisition_status.dispatched'),
            self::PartiallyReceived => __('enums.transfer_requisition_status.partially_received'),
            self::Completed => __('enums.transfer_requisition_status.completed'),
            self::ClosedWithLoss => __('enums.transfer_requisition_status.closed_with_loss'),
            self::Cancelled => __('enums.transfer_requisition_status.cancelled'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Requested => 'warning',
            self::UnderReviewFulfiller,
            self::UnderReviewRequestor => 'warning',
            self::Confirmed => 'primary',
            self::Dispatched => 'info',
            self::PartiallyReceived => 'warning',
            self::Completed => 'success',
            self::ClosedWithLoss => 'danger',
            self::Cancelled => 'danger',
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
