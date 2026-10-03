<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Signed stock movement type — every ledger row carries one of these.
 *
 * Blueprint §4.4 — implements HasLabel only (no HasColor by design in
 * the blueprint's original contract).
 *
 * Extension (authorized per standing instruction): implements the full
 * HasLabel + HasColor + HasIcon triple. Colors and icons are code-level
 * presentation metadata (untranslated), per §0A.1 item 19.
 *
 * Color semantics — intent-based, not raw direction:
 *   - success → normal inbound (Purchase, TransferIn).
 *   - primary → normal outbound (Sale, TransferOut).
 *   - warning → attention-worthy (Adjustment override; returns).
 *   - danger  → exceptional loss (Loss, Damage).
 * `Sale` is `primary`, not `danger`: it is the normal business outflow,
 * and `danger` is reserved for exceptional events. This is intentional —
 * the table's per-column signed-quantity coloring (§7E.2
 * `->color(fn ($state) => $state >= 0 ? 'success' : 'danger')`) already
 * signals direction; the enum color signals intent.
 *
 * Reserved-in-v1 cases (no writer by design):
 *   - Loss / Damage: transfer shortfalls and damage are recorded via
 *     LossLedger only (§4.4, §6.2 writeOffOmittedItem() / recordLoss()),
 *     never as Loss/Damage movements.
 *   - PurchaseReturn: the full supplier-return workflow is deferred
 *     (§13 item 4); InventoryService::recordMovement() rejects this
 *     type (§6.2) so it can only be written once that workflow is
 *     specified.
 */
enum StockMovementType: string implements HasColor, HasIcon, HasLabel
{
    case Adjustment = 'adjustment';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Loss = 'loss';
    case Damage = 'damage';
    case Purchase = 'purchase';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case PurchaseReturn = 'purchase_return';

    public function getLabel(): string
    {
        return match ($this) {
            self::Adjustment => __('enums.stock_movement_type.adjustment'),
            self::TransferOut => __('enums.stock_movement_type.transfer_out'),
            self::TransferIn => __('enums.stock_movement_type.transfer_in'),
            self::Loss => __('enums.stock_movement_type.loss'),
            self::Damage => __('enums.stock_movement_type.damage'),
            self::Purchase => __('enums.stock_movement_type.purchase'),
            self::Sale => __('enums.stock_movement_type.sale'),
            self::SaleReturn => __('enums.stock_movement_type.sale_return'),
            self::PurchaseReturn => __('enums.stock_movement_type.purchase_return'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Adjustment => 'warning',
            self::TransferOut => 'primary',
            self::TransferIn => 'success',
            self::Loss => 'danger',
            self::Damage => 'danger',
            self::Purchase => 'success',
            self::Sale => 'primary',
            self::SaleReturn => 'warning',
            self::PurchaseReturn => 'warning',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Adjustment => Heroicon::OutlinedAdjustmentsHorizontal,
            self::TransferOut => Heroicon::OutlinedArrowUpTray,
            self::TransferIn => Heroicon::OutlinedArrowDownTray,
            self::Loss => Heroicon::OutlinedExclamationTriangle,
            self::Damage => Heroicon::OutlinedShieldExclamation,
            self::Purchase => Heroicon::OutlinedShoppingCart,
            self::Sale => Heroicon::OutlinedBanknotes,
            self::SaleReturn => Heroicon::OutlinedArrowUturnLeft,
            self::PurchaseReturn => Heroicon::OutlinedArrowUturnRight,
        };
    }

    /**
     * Whether a movement of this type increases on-hand stock.
     *
     * Used by `InventoryService::recordMovement()` (§6.2) to sign the
     * stored quantity: positive for increasing types, negative for
     * decreasing types. `Adjustment` is intentionally excluded — the
     * caller supplies the sign via `InventoryService::adjustment()`, and
     * `recordMovement()` rejects `Adjustment` outright (§6.2).
     */
    public function isPositive(): bool
    {
        return in_array($this, [self::TransferIn, self::Purchase, self::SaleReturn], true);
    }
}
