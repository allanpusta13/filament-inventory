<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\TransferRequisition;
use RuntimeException;

final class NegotiationNotAllowedException extends RuntimeException
{
    public function __construct(
        public readonly TransferRequisition $requisition,
        public readonly string $action,
    ) {
        parent::__construct(
            "Negotiation {$action} not allowed for requisition {$requisition->reference_code}: "
            ."status is {$requisition->status->value} (must be requested, under_review_fulfiller, or under_review_requestor)"
        );
    }
}
