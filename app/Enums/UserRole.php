<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * User role — the single source of role-based authorization decisions.
 *
 * Blueprint §4.8 originally defined three cases (Admin, Auditor,
 * WarehouseStaff). The project now defines four; BranchManager has been
 * added per owner direction. This is a blueprint deviation — every
 * downstream section that enumerates roles (badge scope §1B.1a, policy
 * predicates §8, the `User` model §3.18, and `enums.user_role.*`
 * translations §0A.2a) must be extended to cover the new case before
 * the deviation is complete.
 *
 * Implements HasLabel + HasColor + HasIcon. Colors are code-level
 * presentation metadata (untranslated), per §0A.1 item 19.
 *
 * Stored values correspond to `users.role` (§2.12), which defaults to
 * `'warehouse_staff'` and is cast on the model (§3.18 `User`).
 *
 * Role semantics (A8 — policies are the ONLY home for permission logic):
 *   - Admin          — full system scope, including user management and
 *                      all warehouse operations.
 *   - Auditor        — read-only across the full system, including audit
 *                      ledgers and period filters. Never mutates
 *                      operational documents or stock, even when
 *                      assigned to warehouses (§8.3–§8.8).
 *   - WarehouseStaff — operational scope restricted to assigned
 *                      warehouses via the `user_warehouse` pivot
 *                      (§2.13). Badge scope follows role + warehouse
 *                      assignment (§1B.1a, A12).
 *   - BranchManager  — added in v13.6+. Operational semantics are
 *                      owner-defined and not yet codified in the
 *                      blueprint's policy or badge-scope sections.
 *
 * OPEN SEMANTICS (blocking full integration — needs owner decision):
 *   The blueprint's badge-scope rule (§1B.1a) enumerates four tiers and
 *   has no branch_manager tier. Every §8 policy keys on
 *   `isAdmin()` / `isAuditor()` / `isWarehouseStaff()`. Before this
 *   enum case is wired into production, the following must be decided:
 *
 *     1. Badge scope: does BranchManager see all warehouses (like
 *        Admin/Auditor), the union of assigned warehouses (like
 *        WarehouseStaff N≥2), or some branch-level scope that does not
 *        yet exist in the schema (no `branches` table is defined)?
 *     2. Warehouse assignment: does BranchManager participate in the
 *        `user_warehouse` pivot, or does it derive scope from a
 *        different relation?
 *     3. Policy treatment per §8 policy: for each of the 13 policies,
 *        is BranchManager treated as Admin-equivalent, WarehouseStaff-
 *        equivalent, Auditor-equivalent (read-only), or a distinct
 *        capability set?
 *     4. Cancellation/approval boundaries: does BranchManager receive
 *        any approval authority currently reserved for Admin (e.g.
 *        §8.3 `delete`, `forceDelete`)?
 *
 *   Until these are decided, this enum case MUST NOT be relied on by
 *   policies or the badge-scope resolver — treat it as inert.
 */
enum UserRole: string implements HasColor, HasIcon, HasLabel
{
    case Admin = 'admin';
    case Auditor = 'auditor';
    case WarehouseStaff = 'warehouse_staff';
    case BranchManager = 'branch_manager';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => __('enums.user_role.admin'),
            self::Auditor => __('enums.user_role.auditor'),
            self::WarehouseStaff => __('enums.user_role.warehouse_staff'),
            self::BranchManager => __('enums.user_role.branch_manager'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Auditor => 'info',
            self::WarehouseStaff => 'primary',
            self::BranchManager => 'warning',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Admin => Heroicon::OutlinedShieldCheck,
            self::Auditor => Heroicon::OutlinedEye,
            self::WarehouseStaff => Heroicon::OutlinedUsers,
            self::BranchManager => Heroicon::OutlinedBriefcase,
        };
    }
}
