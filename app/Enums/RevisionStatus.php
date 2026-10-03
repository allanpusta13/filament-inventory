<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Negotiation revision resolution status.
 *
 * Blueprint §4.5 — implements HasLabel only (no HasColor by design in
 * the blueprint's original contract).
 *
 * Extension (authorized per standing instruction): implements the full
 * HasLabel + HasColor + HasIcon triple. Colors and icons are code-level
 * presentation metadata (untranslated), per §0A.1 item 19.
 *
 * Stored values correspond to `transfer_requisition_item_revisions.status`
 * (§2.9), which defaults to `'pending'` and is cast on the model
 * (§3.9 `TransferRequisitionItemRevision`).
 *
 * State semantics (§3.9, §6.3 NegotiationService):
 *   - `Pending`  — submitted, awaiting counterpart response.
 *   - `Accepted` — the proposed revision was accepted; the item's
 *                  effective variant/qty is updated at confirm time.
 *   - `Rejected` — the proposed revision was rejected.
 * The transition guard `ensureCanTransitionTo()` (§3.9) allows exactly
 * one move out of `Pending` — no further transitions from a resolved
 * state, and no transition back into `Pending`.
 */
enum RevisionStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => __('enums.revision_status.pending'),
            self::Accepted => __('enums.revision_status.accepted'),
            self::Rejected => __('enums.revision_status.rejected'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Accepted => 'success',
            self::Rejected => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Pending => Heroicon::OutlinedClock,
            self::Accepted => Heroicon::OutlinedCheckCircle,
            self::Rejected => Heroicon::OutlinedXCircle,
        };
    }
}
