# AI Implementation Plan — Filament v5 `TextEntry::boolean()` crash

Status: `APPROVED`

## Objective

Fix `BadMethodCallException: Method Filament\Infolists\Components\TextEntry::boolean does not exist` at `PurchaseOrderInfolist.php:42`, remove the anti-pattern from the blueprint source-of-truth, add an i18n key, and lock the rule with a regression guard.

## Scope

### In Scope
- Patch `app/Filament/Resources/PurchaseOrders/Schemas/PurchaseOrderInfolist.php` `update_cost_price` entry.
- Add `common.yes` / `common.no` to `lang/en/common.php` (only locale missing them).
- Add static-scan regression test `tests/Feature/Architecture/TextBooleanScopeTest.php`.
- Amend `docs/00-project/blueprint.md` §7A.3, §7G.4, §0A.2a, §7O.8, §12 (separate commit).

### Out of Scope
- `ProductInfolist.php:82-85` — verified `IconEntry`, valid, no change.
- `UsersTable:41`, `ProductsTable:94` — `IconColumn`, valid.
- `lang/es`, `lang/tl` — already carry `yes`/`no`.
- Cross-locale catalogue parity (pre-existing divergence, separate task).
- `composer install` / dependency changes.

## Governing Inputs
- User request: bugfix brief + "approved".
- System Blueprint: `docs/00-project/blueprint.md` (§7A.3, §7G.4 reference impl `WarehouseInfolist::is_active` §7K.3).
- Existing conventions: `WarehouseInfolist.php:51-55` canonical `formatStateUsing()+color()` pattern.

## Discovery Findings
### Direct Source Inspection
- Sole broken carrier: `PurchaseOrderInfolist.php:42` `TextEntry::make('update_cost_price')->badge()->boolean()`.
- `ProductInfolist.php:82` is `IconEntry` — valid.
- `lang/en/common.php` lacks `yes`/`no`; `es`/`tl` already have both.
- Real i18n test: `tests/Unit/Lang/TranslationCompletenessTest.php` (en-only audit). No parity/coverage tests exist despite brief.
- Blueprint `->boolean()` sites: `:7867` `IconColumn` (ok), `:8014` `TextEntry` (defect), `:10951` `TextEntry` (defect), `:12374` `IconColumn` (ok).
- Schema API confirmed class-based (`::configure(Schema|Table)`).

### Environment
- `vendor/` MISSING, `composer.lock` MISSING in this worktree → no test/artisan/browser run possible here.

## Council Review
### Product / PM
Single-field runtime crash; fix satisfies requirement, no scope creep. ProductInfolist edit dropped as unnecessary.
### Security
No auth/authz/input surface touched. i18n keys are static strings.
### Architecture
Reuses established `formatStateUsing()+color()` convention; no new abstraction.
### QA
Regression guard is a deterministic static scan (no unverifiable vendor internals). Existing i18n audit covers the `en` key.
### Skeptic
- Evidence-honesty: cannot runtime-verify without `vendor/`; stated as unverified.
- `fn (bool $state)` throws on null under strict_types — column expected non-null, matches existing convention; flagged edge case.
- Regex guard scans to first `;` — theoretical false positive on nested closure; none today.

## Decisions Requiring User Approval
1. Non-carrier ProductInfolist dropped from scope (verified IconEntry) — approved.
2. Regression guard implemented as static scan rather than schema-walk (vendor internals unverifiable) — approved.
3. `common.active/inactive` NOT reused (semantically wrong) — approved.
4. `composer install` NOT run in this worktree (dependency-graph change) — approved.

## Implementation Tasks
### Phase 2 — Build
- [x] Patch `PurchaseOrderInfolist.php` `update_cost_price` + inline comment.
- [x] Add `yes`/`no` to `lang/en/common.php`.
- [x] Add `tests/Feature/Architecture/TextBooleanScopeTest.php`.
### Phase 3 — Test / Refine
- [x] Static scan — zero offenders.
- [ ] Pest suite — blocked (`vendor/` missing).
- [ ] Blueprint amendments (separate commit).

## Verification / Evidence
- [x] Static scan `grep -E "Text(Entry|Column)::make" | grep boolean` → 0 hits.
- [ ] Pest Unit/Feature — BLOCKED, vendor missing.
- [ ] Runtime browser verification — BLOCKED, vendor missing.
- [x] Source inspection of all carrier/non-carrier sites.

## Stop Conditions
Stop for material product, architecture, security, data, dependency, destructive/consequential, or plan-deviation decisions.

## Approval Metadata
- Council status: reviewed (all five seats).
- User approval: explicit "approved" in conversation, 2026-10-05.
- Approved date: 2026-10-05.

## Completion Metadata
- Status: Implemented, verification partial (static only).
- Tests: added `TextBooleanScopeTest`; suite not run (vendor missing).
- Evidence: static grep clean; file edits as scoped.
- Deviations: none from approved plan.
- Completion date: 2026-10-05.
