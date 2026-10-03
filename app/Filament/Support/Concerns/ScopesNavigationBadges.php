<?php

declare(strict_types=1);

namespace App\Filament\Support\Concerns;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;

/**
 * ScopesNavigationBadges — single canonical resolver for badge
 * warehouse scoping.
 *
 * Blueprint §1B.3 / §18.5. Consumed by the four badge-bearing
 * resources:
 *
 *   - TransferRequisitionResource  (§1B.3a, §18.1a)
 *   - PurchaseOrderResource        (§1B.3a, §18.1a)
 *   - SalesOrderResource           (§1B.3a, §18.1a)
 *   - InTransitResource            (§1B.3a, §18.1a)
 *
 * Badge scope is determined by the acting user's role and warehouse
 * assignments — never by the warehouse endpoints of a specific
 * document. See Principle A12.
 *
 * Five scope tiers (§1B.1a extended by owner direction for
 * BranchManager):
 *
 *   | Role             | Assigned warehouses | Badge scope                    |
 *   |------------------|---------------------|--------------------------------|
 *   | Admin            | any                 | every warehouse in the system  |
 *   | Auditor          | any                 | every warehouse in the system  |
 *   | WarehouseStaff   | N >= 2              | union of assigned warehouses   |
 *   | WarehouseStaff   | N == 1              | exactly the single assigned    |
 *   | WarehouseStaff   | N == 0              | empty set → badge = null       |
 *   | BranchManager    | N >= 2              | union of assigned warehouses   |
 *   | BranchManager    | N == 1              | exactly the single assigned    |
 *   | BranchManager    | N == 0              | empty set → badge = null       |
 *
 * BranchManager tier decision (owner direction):
 *
 *   BranchManager follows the same warehouse-assignment tier as
 *   WarehouseStaff — assigned warehouses only, with no widening to
 *   all warehouses. This matches the operational-role treatment
 *   applied across the §8 policies (BranchManager is an operational
 *   user, not an Admin-equivalent or Auditor-equivalent). The
 *   resolver handles BranchManager EXPLICITLY rather than as a
 *   fall-through, so a future role addition cannot silently inherit
 *   BranchManager's scope.
 *
 *   If the owner later decides BranchManager should see all
 *   warehouses (Admin-equivalent) or a branch-scoped set (requires a
 *   new `branches` table), this resolver must be edited explicitly
 *   and the five-tier table above updated.
 *
 * `badgeScopedWarehouseIds()` is the ONLY sanctioned way to compute
 * badge warehouse IDs. Ad hoc `auth()->user()->warehouses()->pluck('id')`
 * inside a `getNavigationBadge()` method is prohibited (§1B.5) — it
 * silently produces the wrong result for admins and auditors, whose
 * warehouse pivot may be empty while their badge authority is global.
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
     * Badge scope for the current request is cached once and shared
     * across `getNavigationBadge()`, `getNavigationBadgeColor()`, and
     * `getNavigationBadgeTooltip()` calls.
     *
     * Public so `AppServiceProvider` can flush every badge-bearing
     * resource on `Auth::logout` / `Auth::login` (§17.2) — required in
     * long-lived workers (Octane, queue workers that boot Filament).
     * Each resource carries its own copy of the static caches (trait
     * semantics), so all four must be flushed.
     *
     * Resets BOTH the warehouse-ID scope and the cached count:
     * resetting only the scope leaves the previous user's count visible
     * to the next user in a long-lived worker.
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
     * - BranchManager: same warehouse-assignment tier as WarehouseStaff —
     *   assigned warehouses only. Explicit branch (not a fall-through).
     * - Any other role: empty array (defensive).
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

        // Full-system tiers — Admin and Auditor see every warehouse
        // regardless of their `user_warehouse` pivot contents.
        if ($user->isAdmin() || $user->isAuditor()) {
            return self::$badgeWarehouseIds = Warehouse::query()
                ->pluck('id')
                ->all();
        }

        // Warehouse-assignment tiers — WarehouseStaff and BranchManager
        // both resolve to their assigned warehouses only. N >= 2 → the
        // union; N == 1 → exactly that one; N == 0 → empty.
        if ($user->isWarehouseStaff() || $user->isBranchManager()) {
            return self::$badgeWarehouseIds = $user->warehouses()
                ->pluck('warehouses.id')
                ->all();
        }

        // Defensive: an unknown role resolves to no badge scope rather
        // than silently widening to another role's tier. A new role
        // added to `UserRole` must be wired into one of the branches
        // above.
        return self::$badgeWarehouseIds = [];
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
