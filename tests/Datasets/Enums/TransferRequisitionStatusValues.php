<?php

declare(strict_types=1);

namespace Tests\Datasets\Enums;

use App\Enums\TransferRequisitionStatus;

class TransferRequisitionStatusValues
{
    public static function all(): array
    {
        return TransferRequisitionStatus::cases();
    }

    public static function preDispatch(): array
    {
        return [
            TransferRequisitionStatus::Draft,
            TransferRequisitionStatus::Requested,
            TransferRequisitionStatus::UnderReviewFulfiller,
            TransferRequisitionStatus::UnderReviewRequestor,
            TransferRequisitionStatus::Confirmed,
        ];
    }

    public static function receivable(): array
    {
        return [
            TransferRequisitionStatus::Dispatched,
            TransferRequisitionStatus::PartiallyReceived,
        ];
    }

    public static function terminal(): array
    {
        return [
            TransferRequisitionStatus::Completed,
            TransferRequisitionStatus::ClosedWithLoss,
            TransferRequisitionStatus::Cancelled,
        ];
    }

    public static function negotiable(): array
    {
        return [
            TransferRequisitionStatus::Requested,
            TransferRequisitionStatus::UnderReviewFulfiller,
            TransferRequisitionStatus::UnderReviewRequestor,
        ];
    }
}
