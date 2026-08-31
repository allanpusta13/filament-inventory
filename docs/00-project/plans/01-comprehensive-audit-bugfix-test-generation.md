# Plan: Comprehensive Audit Bugfix & Dual-Layer Test Generation

## Summary
Full top-to-bottom audit of the Laravel/Filament inventory application. Fix all runtime errors, null accessor bugs, status filter mismatches, and missing policies. Generate comprehensive Pest backend tests and Playwright E2E specs for every model, resource, policy, and workflow.

---

## Stage 0 Findings: Bugs Discovered

### HIGH RISK — Runtime Errors

| # | File | Line | Issue |
|---|------|------|-------|
| 1 | `Pages/StockAdjustment.php` | 48-50 | `Auth::user()` can return null; accessing `->role` on null throws `Attempt to read property "role" on null` |
| 2 | `Pages/StockAdjustment.php` | 55 | `Auth::user()->warehouses()` — same null access risk |
| 3 | `Pages/StockAdjustment.php` | 79 | `$v->product->sku` — `$v->product` could be null for orphaned variants |
| 4 | `Widgets/WarehouseStockOverviewWidget.php` | 33 | `$variant->product->reorder_point` — `$variant->product` could be null |
| 5 | `Widgets/PendingTransfersWidget.php` | 28 | Status filter uses `'under_review'` but actual statuses are `'under_review_fulfiller'` and `'under_review_requestor'` — pending requisitions in review won't appear |
| 6 | `Widgets/PendingTransfersWidget.php` | 24 | `$user->isAdmin()` without null-checking `$user` |
| 7 | `TransferRequisitions/Schemas/TransferRequisitionForm.php` | 89, 96 | `$v->product` could be null after `->with('product')` for orphaned variants; accessing `->sku`/`->name` on null throws error |

| 7b | `Widgets/PendingTransfersWidget.php` | 31-33 | **SECURITY: OR-grouping bug** — `whereIn('from_warehouse_id', ...)->orWhereIn('to_warehouse_id', ...)` without closure wrapper leaks data across warehouse boundaries for non-admin users. Must wrap in `->where(function ($q) use ($warehouseIds) { ... })` like `TransferRequisitionsTable.php:35-38` |

### MEDIUM RISK — Edge Case Errors

| # | File | Line | Issue |
|---|------|------|-------|
| 8 | `ProductPriceResource.php` | 93-98 | `variant.sku`/`variant.name` table columns access relationship without null guard |
| 9 | `Widgets/WarehouseStockOverviewWidget.php` | 47-52 | Dead code: `$transitQuery` built but never used |
| 10 | `Widgets/WarehouseStockOverviewWidget.php` | 31-44 | N+1 queries: loops ALL ProductVariant records running separate queries per variant |

### LOW RISK — Code Quality

| # | File | Issue |
|---|------|-------|
| 12 | `Filament/Traits/DashboardFilterable.php` | Dead code: unused trait |

**Note:** Bug 11 (DashboardSections empty constructors) was dropped per Architecture Council — conflicts with approved ADR `remove-basesectionheaderwidget.md` which preserves these files.

### Missing Test Coverage

| Gap | Details |
|-----|---------|
| ProductPolicy::view() | Never tested with any role |
| TransferRequisitionPolicy::viewAny() | Never tested |
| Manager role policies | Systematically under-tested across all policies |
| Auditor create denial (LossLedger) | Untested |
| TransferRequisition delete edge cases | Auditor/non-owner/non-draft untested |
| Browser/E2E tests | All 3 tests are `.skip()`-ed |

---

## Files to Modify

### Bug Fixes (6 files)
1. `app/Filament/Pages/StockAdjustment.php` — Null-guard Auth::user(), null-guard $v->product
2. `app/Filament/Widgets/WarehouseStockOverviewWidget.php` — Null-guard $variant->product, fix N+1, remove dead code
3. `app/Filament/Widgets/PendingTransfersWidget.php` — Fix status filter mismatch, fix OR-grouping security bug, null-guard $user
4. `app/Filament/Resources/TransferRequisitions/Schemas/TransferRequisitionForm.php` — Null-guard $v->product
5. `app/Filament/Resources/ProductPriceResource.php` — Null-guard variant relationship columns

### New Test Files (Backend)
6. `tests/Feature/PolicyCoverageTest.php` — Comprehensive policy tests for ALL roles across ALL policies
7. `tests/Feature/Filament/Pages/StockAdjustmentTest.php` — Feature test for StockAdjustment page
8. `tests/Feature/Filament/Widgets/PendingTransfersWidgetTest.php` — Widget test
9. `tests/Feature/Filament/Widgets/WarehouseStockOverviewWidgetTest.php` — Widget test

### New Test Files (Frontend/Playwright)
10. `tests/playwright/dashboard.spec.ts` — Dashboard widget rendering
11. `tests/playwright/stock-adjustment.spec.ts` — StockAdjustment page E2E
12. `tests/playwright/transfer-requisition.spec.ts` — Transfer requisition workflow E2E
13. `tests/playwright/role-access.spec.ts` — Role-based access control E2E

### Cleanup
6. `app/Filament/Traits/DashboardFilterable.php` — Delete unused dead trait

---

## Non-Goals
- No new Filament resources or pages
- No database schema changes
- No new model relationships (documented but not added this pass)
- TransferOrder system remains as-is (separate feature, not in scope)
