<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * In-transit cargo status — the lifecycle of a dispatched requisition
 * line while stock is physically between warehouses.
 *
 * Blueprint §4.7 — implements HasLabel only (no HasColor by design in
 * the blueprint's original contract).
 *
 * Extension (authorized per standing instruction): implements the full
 * HasLabel + HasColor + HasIcon triple. Colors and icons are code-level
 * presentation metadata (untranslated), per §0A.1 item 19.
 *
 * Stored values correspond to `in_transits.status` (§2.10), which
 * defaults to `'in_transit'` and is cast on the model (§3.10
 * `InTransit`).
 *
 * State semantics (§6.2 `InventoryService::scanToReceive()` /
 * `writeOffOmittedItem()` / `markInTransit()`):
 *   - `InTransit` — dispatched, not yet accounted. Terminal states are
 *                   reachable only from this state.
 *   - `Cleared`   — good + damaged receipts cover the approved qty
 *                   (scan or recordLoss path).
 *   - `Lost`      — the shortfall write-off covers the remainder
 *                   (first-scan omitted item, or recordLoss with a
 *                   matching shortfall).
 * Terminal states are never re-entered into the active cargo list
 * (§10 `ActiveInTransitWidget` filters on `status = 'in_transit'`).
 */
enum InTransitStatus: string implements HasColor, HasIcon, HasLabel
{
    case InTransit = 'in_transit';
    case Cleared = 'cleared';
    case Lost = 'lost';

    public function getLabel(): string
    {
        return match ($this) {
            self::InTransit => __('enums.in_transit_status.in_transit'),
            self::Cleared => __('enums.in_transit_status.cleared'),
            self::Lost => __('enums.in_transit_status.lost'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InTransit => 'info',
            self::Cleared => 'success',
            self::Lost => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::InTransit => Heroicon::OutlinedTruck,
            self::Cleared => Heroicon::OutlinedCheckCircle,
            self::Lost => Heroicon::OutlinedExclamationTriangle,
        };
    }
}
