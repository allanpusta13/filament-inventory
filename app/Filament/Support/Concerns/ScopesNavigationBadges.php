<?php

declare(strict_types=1);

namespace App\Filament\Support\Concerns;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;

/**
 * Centralized navigation badge scoping.
 *
 * Badge scope is determined by the acting user's role and warehouse
 * assignments — never by the warehouse endpoints of a specific document.
 * See Principle A12.
 */
trait ScopesNavigationBadges
{
    /** @var array<int>|null */
    private static ?array $badgeWarehouseIds = null;

    /**
     * Cached badge count shared by `getNavigationBadge()` and
     * `getNavigationBadgeColor()`. Trait-owned, so each using resource
     * gets its own copy (trait semantics) while `flushBadgeScope()`
     * resets both caches together — a resource-level copy would survive
     * logout in long-lived workers.
     */
    private static ?int $badgeCount = null;

    /**
     * Badge scope for the current request is cached once and shared across
     * `getNavigationBadge()`, `getNavigationBadgeColor()`, and
     * `getNavigationBadgeTooltip()` calls.
     *
     * Public so `AppServiceProvider` can flush every badge-bearing resource
     * on `Auth::logout` (§17.3) — required in long-lived workers (Octane,
     * queue workers that boot Filament). Each resource carries its own copy
     * of the static caches (trait semantics), so all four must be flushed.
     *
     * Resets BOTH the warehouse-ID scope and the cached count: resetting
     * only the scope leaves the previous user's count visible to the next
     * user in a long-lived worker.
     */
    public static function flushBadgeScope(): void
    {
        self::$badgeWarehouseIds = null;
        self::$badgeCount = null;
    }

    /**
     * Resolve the warehouse ID set used to scope every navigation badge
     * on this resource.
     *
     * - Admin / Auditor: all warehouses.
     * - WarehouseStaff with N >= 2: union of assigned warehouses.
     * - WarehouseStaff with N == 1: exactly the single assigned warehouse.
     * - WarehouseStaff with N == 0: empty array (badge resolves to null).
     *
     * @return array<int>
     */
    protected static function badgeScopedWarehouseIds(): array
    {
        if (self::$badgeWarehouseIds !== null) {
            return self::$badgeWarehouseIds;
        }

        $user = Auth::user();

        if (! $user instanceof User) {
            return self::$badgeWarehouseIds = [];
        }

        if ($user->isAdmin() || $user->isAuditor()) {
            return self::$badgeWarehouseIds = Warehouse::query()
                ->pluck('id')
                ->all();
        }

        return self::$badgeWarehouseIds = $user->warehouses()
            ->pluck('warehouses.id')
            ->all();
    }

    /**
     * Whether the current user has any badge scope at all.
     * Used by resources that must suppress their badge entirely when the
     * user's role resolves to zero warehouses.
     */
    protected static function hasBadgeScope(): bool
    {
        return count(self::badgeScopedWarehouseIds()) > 0;
    }
}
