<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Negotiation side — which party to a transfer requisition authored or
 * is currently driving a revision.
 *
 * Blueprint §4.6 — implements HasLabel only (no HasColor by design in
 * the blueprint's original contract).
 *
 * Extension (authorized per standing instruction): implements the full
 * HasLabel + HasColor + HasIcon triple. Colors and icons are code-level
 * presentation metadata (untranslated), per §0A.1 item 19.
 *
 * Stored values correspond to
 * `transfer_requisition_item_revisions.side` (§2.9), cast on the model
 * (§3.9 `TransferRequisitionItemRevision`).
 *
 * Negotiation loop (§6.3 `NegotiationService::submitRevision()`):
 *   - Fulfiller submits → parent moves to `UnderReviewRequestor`.
 *   - Requestor submits → parent moves to `UnderReviewFulfiller`.
 * This enum identifies which side authored each revision row; the
 * `responds_to_revision_id` chain links the ping-pong history.
 */
enum NegotiationSide: string implements HasColor, HasIcon, HasLabel
{
    case Fulfiller = 'fulfiller';
    case Requestor = 'requestor';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fulfiller => __('enums.negotiation_side.fulfiller'),
            self::Requestor => __('enums.negotiation_side.requestor'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Fulfiller => 'info',
            self::Requestor => 'primary',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Fulfiller => Heroicon::OutlinedTruck,
            self::Requestor => Heroicon::OutlinedPaperAirplane,
        };
    }
}
