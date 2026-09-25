<?php

declare(strict_types=1);

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

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

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Accepted => 'success',
            self::Rejected => 'danger',
        };
    }

    public function getIcon(): string|BackedEnum|null
    {
        return match ($this) {
            self::Pending => Heroicon::Clock,
            self::Accepted => Heroicon::CheckCircle,
            self::Rejected => Heroicon::XCircle,
        };
    }

    public function isResolved(): bool
    {
        return $this !== self::Pending;
    }
}
