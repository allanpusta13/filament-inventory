<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Transfer requisition lifecycle status.
 *
 * Blueprint §4.1 — implements HasLabel + HasColor.
 *
 * Extension (authorized per standing instruction): also implements
 * HasIcon so the enum's rendered state carries a semantic Heroicon
 * alongside its translated label and color. Icons are code-level
 * presentation metadata (untranslated), mirroring the existing color
 * contract (§0A.1 item 19). Every enum generated from this point
 * forward follows the same pattern.
 */
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

    public function getColor(): string
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

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Draft => Heroicon::OutlinedPencilSquare,
            self::Requested => Heroicon::OutlinedPaperAirplane,
            self::UnderReviewFulfiller => Heroicon::OutlinedChatBubbleLeftRight,
            self::UnderReviewRequestor => Heroicon::OutlinedChatBubbleLeftRight,
            self::Confirmed => Heroicon::OutlinedCheckBadge,
            self::Dispatched => Heroicon::OutlinedTruck,
            self::PartiallyReceived => Heroicon::OutlinedArchiveBoxArrowDown,
            self::Completed => Heroicon::OutlinedCheckCircle,
            self::ClosedWithLoss => Heroicon::OutlinedExclamationTriangle,
            self::Cancelled => Heroicon::OutlinedXMark,
        };
    }
}
