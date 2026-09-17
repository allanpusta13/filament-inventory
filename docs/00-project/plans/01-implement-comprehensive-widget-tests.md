# Stage 1 — Plan: Comprehensive Widget Test Coverage (Warehouse-Secure)

## Graphify Structural Context

The filament-inventory project has 3 Filament table widgets with existing test coverage:

| Widget | Test File | Existing Tests | New Tests | Total |
|--------|-----------|----------------|-----------|-------|
| ActiveInTransitWidget | Tests\Feature\Filament\Widgets\ActiveInTransitWidgetTest.php | 15 | 10 | 25 |
| LowStockAlertsWidget | Tests\Feature\Filament\Widgets\LowStockAlertsWidgetTest.php | 8 | 8 | 16 |
| RecentMovementsWidget | Tests\Feature\Filament\Widgets\RecentMovementsWidgetTest.php | 14 | 9 | 23 |

**Total new tests: 27** (confirmed: 10 + 8 + 9 = 27)

Call paths:
- Widgets use `Cache::remember()` with 300s/60s TTL for results
- `InventoryService` handles all stock movements (`recordMovement`, `directTransfer`, `dispatchTransfer`, `scanToReceive`)
- `ProductVariant::onHandQuantity()` computes stock from `stock_movements` table
- InTransit models track dispatched goods between warehouses
- Widgets are warehouse-scoped via `$user->warehouses` BelongsToMany relationship (no `tenant_id` column in codebase)

## What Will Change and Why

The existing test suites have good coverage but lack several **security** edge case categories (identified in Stage 2 Security Review):

### Security Gaps Addressed in This Plan:

1. **Warehouse Isolation** — Queries must be scoped by the user's assigned warehouse IDs; cross-warehouse access must be explicitly denied
2. **Authorization Boundaries** — Non-admin users must receive 403/Unauthorized when attempting operations outside their scope
3. **Input & Sanitization** — Malicious inputs (SQL payloads, XSS scripts) must be rejected at the form request layer
4. **Data Exposure** — Sensitive fields (password, token, internal IDs) must not appear in widget response data

### Non-Security Edge Cases (preserved from original plan):

- Concurrent access / race conditions
- Failed authorization
- Cache invalidation patterns
- Empty/null state handling
- Error conditions
- Widget table column behavior

## Exact Files to Touch

All files are under `tests/Feature/Filament/Widgets/`:

| File | New Tests | Total After |
|------|-----------|-------------|
| `ActiveInTransitWidgetTest.php` | 10 | 25 |
| `LowStockAlertsWidgetTest.php` | 8 | 16 |
| `RecentMovementsWidgetTest.php` | 9 | 23 |

**Total new tests: 27** (confirmed: 10 + 8 + 9 = 27)

Non-overlapping scope: Each widget test file is independent; no two subagents write to the same file.

## Explicit Non-Goals / Out of Scope

- ❌ Do NOT modify widget PHP implementations (`ActiveInTransitWidget.php`, `LowStockAlertsWidget.php`, `RecentMovementsWidget.php`) **unless** adding input sanitization middleware required by security ADRs
- ❌ Do NOT add new Filament resource tests (covered separately)
- ❌ Do NOT modify `InventoryService` implementation (already has v10 guards)
- ❌ Do NOT run playwright E2E tests (separate pipeline stage)
- ❌ Do NOT add unit tests for InventoryService (separate concern)
- ❌ Do NOT change cache TTL values (300s/60s are intentional)

## Interface Contracts (Written Specifications)

### Security-Required Test Additions

**Key architectural change: Warehouse_id scoping replaces tenant_id scoping.** The codebase uses `$user->warehouses` BelongsToMany relationship. All query scoping uses warehouse IDs, not tenant IDs.

#### ActiveInTransitWidgetTest (10 new tests — security + business logic):

1. `it returns 403 when non-admin attempts access` — Gate check for admin-only widget
2. `it rejects SQL injection in warehouse context` — Form request validation for SQL payloads
3. `it rejects XSS scripts in variant names` — Input sanitization test
4. `it scopes queries by user's warehouse IDs` — Every query must include warehouse filtering via `$user->warehouses`
5. `it denies cross-warehouse access` — User A cannot access User B's warehouse in-transits
6. `it handles null warehouse assignment gracefully` — Edge case for unassigned warehouses
7. `it cache_bypass_preserves_warehouse_scoping` — Bypass cache but maintain warehouse filter
8. `it shows generic error not details` — Error messages must not leak internal IDs
9. `it validates unitRatio is positive integer` — Already in service, test the guard
10. `it caches results correctly per warehouse` — Separate cache keys per warehouse

#### LowStockAlertsWidgetTest (8 new tests — security + business logic):

1. `it returns 403 when non-admin attempts access` — Gate check for admin-only widget
2. `it rejects SQL injection in warehouse search` — Form request validation for SQL payloads
3. `it rejects XSS scripts in variant names` — Input sanitization test
4. `it scopes queries by user's warehouse IDs` — Every query must include warehouse filtering via `$user->warehouses`
5. `it denies cross-warehouse access` — User A cannot access User B's low stock variants
6. `it handles null barcode in shortfall display` — Missing barcode edge case
7. `it cache_invalidated_on_stock_movement` — New movement triggers recompute with warehouse scoping
8. `it shows generic error not details` — Error messages must not leak internal IDs

#### RecentMovementsWidgetTest (9 new tests — security + business logic):

1. `it returns 403 when non-admin attempts access` — Gate check for admin-only widget
2. `it handles warehouse with no movements yet` — Empty state
3. `it excludes movements older than 365 days` — Age filter
4. `it handles null reference_code gracefully` — Missing reference
5. `it race-condition safe cache bypass` — `$bypassCache` flag with warehouse scoping
6. `it filters by movement type array` — Type filter parameter
7. `it handles deleted createdBy user` — Orphaned audit with warehouse check
8. `it orders correctly across midnight boundary` — Date rollover
9. `it denies cross-warehouse movement access` — User A cannot see User B's movements

## Design Notes

- All new tests follow the existing `uses(RefreshDatabase::class)` pattern
- All new tests use `$this->actingAs($this->user)` for auth
- **Every query must include warehouse scoping**: filtering via `$user->warehouses` — this is now MANDATORY (matches codebase architecture)
- Cache keys include warehouse ID: `active_in_transit_{userId}_{warehouseId}`, etc.
- No new dependencies introduced
- Tests are pure PHP Pest tests — no Playwright browser tests in this stage
- If a widget is NOT admin-restricted, the test must explicitly document that and add authorization-denial tests for non-admin users

## ADR Decisions

**SEC-001: Warehouse-Scoped Test Requirement** — All widget tests must include warehouse ID scoping via the user's warehouse relationship (`$user->warehouses`). This matches the codebase architecture (warehouse-scoped, not tenant-scoped). If a widget serves multiple warehouses without role-based isolation, the test plan must document the multi-warehouse access pattern and add explicit cross-warehouse denial assertions. Written: 2026-09-17. If future changes remove the warehouse_id requirement, a new ADR must be written to justify the change.

**SEC-002: Admin Authorization Boundary** — Widgets must explicitly define whether they are admin-restricted or multi-warehouse. If admin-restricted, tests must verify non-admin users receive 403. If multi-warehouse, tests must verify warehouse isolation with cross-warehouse denial assertions. Written: 2026-09-17.

**MATH-001: Test Count Verification** — Total new test count is 27 (10 + 8 + 9), not 38. This is verified and documented in the "Exact Files to Touch" table and the Graphify Structural Context section. Written: 2026-09-17.

**ARCH-001: Codebase Architecture Alignment** — This plan uses warehouse_id scoping matching the existing `$user->warehouses` BelongsToMany relationship. The previous plan's tenant_id scoping (2026-09-17 revision) was revised to align with codebase architecture. Written: 2026-09-17.

If the user wishes to proceed with a revised plan addressing the security review and architecture review findings, they must incorporate these ADRs. Otherwise, the plan remains revised and no implementation proceeds with the warehouse-secure pattern.