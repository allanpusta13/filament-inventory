<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Loss category — the qualitative classification of a recorded loss.
 *
 * Blueprint §4.9 — implements HasLabel only (no HasColor by design in
 * the blueprint's original contract).
 *
 * Extension (authorized per standing instruction): implements the full
 * HasLabel + HasColor + HasIcon triple. Colors and icons are code-level
 * presentation metadata (untranslated), per §0A.1 item 19.
 *
 * Stored values correspond to `loss_ledgers.loss_category` (§2.11),
 * which defaults to `'shortfall'` and is cast on the model (§3.11
 * `LossLedger`).
 *
 * Authoring paths (§6.2):
 *   - `writeOffOmittedItem()` — always writes `Shortfall` (omitted cargo
 *     on first scan).
 *   - `recordLoss()` — accepts any case, chosen by the operator in the
 *     §7B.3 `recordLoss` modal.
 *
 * The category is descriptive only. It does not drive financial
 * treatment: `total_financial_loss` is computed from the snapshotted
 * unit cost and lost + damaged base quantity regardless of category
 * (§3.11 `LossLedger::calculateTotalFinancialLoss()`).
 *
 * Color semantics — severity + cause:
 *   - danger  → intentional / harmful (Damage, Theft).
 *   - warning → unintentional / process-driven (Shortfall, Spoilage).
 *   - gray    → unclassified (Other).
 * Every category is a loss; colors distinguish cause and severity, not
 * whether the row is a loss at all.
 */
enum LossCategory: string implements HasColor, HasIcon, HasLabel
{
    case Shortfall = 'shortfall';
    case Damage = 'damage';
    case Spoilage = 'spoilage';
    case Theft = 'theft';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Shortfall => __('enums.loss_category.shortfall'),
            self::Damage => __('enums.loss_category.damage'),
            self::Spoilage => __('enums.loss_category.spoilage'),
            self::Theft => __('enums.loss_category.theft'),
            self::Other => __('enums.loss_category.other'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Shortfall => 'warning',
            self::Damage => 'danger',
            self::Spoilage => 'warning',
            self::Theft => 'danger',
            self::Other => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Shortfall => Heroicon::OutlinedArrowTrendingDown,
            self::Damage => Heroicon::OutlinedShieldExclamation,
            self::Spoilage => Heroicon::OutlinedClock,
            self::Theft => Heroicon::OutlinedLockClosed,
            self::Other => Heroicon::OutlinedEllipsisHorizontal,
        };
    }
}
