# Plan: Inventory System Audit Trail & Code Quality

## Objective
Comprehensive audit of the Filament inventory app against blueprint and prompts 00–14, eliminating code redundancy, fixing bugs, implementing missing features, and adding audit trail coverage for transfer requisitions.

## Changes Made

### Step 4a: InventoryService DRY Refactor
- Extracted `variantIds()` helper method to replace 6 duplicate `$this->query(...)->where(...)->where(...)->pluck('variant_id')` chains
- Fixed `availableForNegotiation()` multi-variant bug (was returning boolean instead of collection intersection)

### Step 4b: StatsOverviewWidget Dead Code Removal
- Removed unused `getStockTrendData()` method and `StockMovement` import

### Step 4c: LowStockWidget Fixes
- `canView()` now checks `admin`, `branch_manager`, `warehouse_staff` roles (was only `admin`)
- Added `DashboardFilterable` trait for warehouse-scoped filtering
- Changed data source from `stock_movements` to `warehouse_stock` (canonical source per blueprint)
- Fixed `selectRaw` and `groupBy` column references

### Step 4d: StockByWarehouseWidget Fixes
- `canView()` now allows `admin`, `branch_manager`, `warehouse_staff`
- Changed subqueries from `stock_movements` to `warehouse_stock`
- Removed redundant `withCount`

### Step 11: StockByWarehouseWidget Final Fix
- Added `total_movements` subquery to `warehouses()` — `(SELECT COUNT(*) FROM stock_movements sm WHERE sm.warehouse_id = warehouses.id) as total_movements`
- Added `'total_movements' => (int) $warehouse->total_movements` to the mapped data array
- Updated Pest test: `warehouse staff cannot view stock by warehouse` → `warehouse staff can view stock by warehouse` with `expect($isVisible)->toBeTrue()`

### Step 5a: Dashboard Access Control
- Fixed `canAccess()` to include `UserRole::BranchManager` and `UserRole::Auditor`

### Step 6: Stock Adjustment Audit Log
- Created migration `2026_09_03_084350_add_notes_to_stock_movements_table.php` — adds `notes` column to `stock_movements`
- Updated `StockMovement` model with `$fillable` entry for `notes`
- Updated `InventoryService::recordMovement()` to accept `?string $notes = null` parameter
- Updated `StockAdjustment` to pass reason as notes when recording adjustments
- Added 15-char minimum length validation on stock adjustment reason

### Step 7: Transfer Requisition Audit Trail
- Created migration `2026_09_03_091000_create_transfer_requisition_audits_table.php`
- Created `TransferRequisitionAudit` model (`app/Models/TransferRequisitionAudit.php`)
- Extended `AuditService` with `recordRequisition()` method
- Modified `InventoryService::lockStockForRequisition()` — accepts `?User $user`, records `confirmed` audit
- Modified `InventoryService::dispatchTransfer()` — accepts `?User $user`, records `dispatched` audit
- Modified `InventoryService::scanToReceive()` — accepts `?User $user`, records `received` audit inside transaction
- Modified `ViewTransferRequisition::submit` action — records `submitted` audit
- Modified `ViewTransferRequisition::counter_offer` action — records `counter_offered` audit

### Step 9: Audit Trail & Notes Tests
- Created `tests/Feature/AuditTrailAndNotesTest.php` — 7 Pest test cases:
  1. `audit service can record stock adjustment`
  2. `audit service can record transfer requisition action`
  3. `stock adjustment notes are persisted in stock_movements`
  4. `lock stock for requisition records audit with user`
  5. `dispatch transfer records audit with user`
  6. `scan to receive records audit with user`
  7. `stock adjustment requires 15 character minimum reason`

### Step 10: Playwright E2E Tests — Dashboard & Stock Adjustment
- `tests/playwright/dashboard.spec.ts`: 5 tests, all passing
  1. renders KPI cards
  2. renders stock by warehouse widget
  3. filters by warehouse via dashboard filter
  4. renders low stock alerts
  5. shows alert on empty warehouses
- `tests/playwright/stock-adjustment.spec.ts`: 4 tests, all passing
  1. opens stock adjustment page
  2. validates form fields
  3. submits valid stock adjustment
  4. validates 15 char minimum reason
- Config: `playwright.config.ts` — baseURL `http://filament-inventory.test`, timeout 120s, projects: chromium/firefox/webkit, storageState `./tests/playwright/.auth/user.json`

## Files Changed
- `app/Services/InventoryService.php` — DRY refactor + audit integration (Steps 4a, 6, 7)
- `app/Services/AuditService.php` — `recordRequisition()` method (Step 7)
- `app/Models/TransferRequisitionAudit.php` — NEW model (Step 7)
- `app/Models/StockMovement.php` — `notes` in fillable (Step 6)
- `app/Filament/Widgets/LowStockWidget.php` — role + data source fixes (Step 4c)
- `app/Filament/Widgets/StockByWarehouseWidget.php` — role + query + `total_movements` fixes (Steps 4d, 11)
- `app/Filament/Widgets/StatsOverviewWidget.php` — dead method removal (Step 4b)
- `app/Filament/Pages/Dashboard.php` — access control (Step 5a)
- `app/Filament/Pages/StockAdjustment.php` — validation + notes passthrough (Step 6)
- `app/Filament/Resources/TransferRequisitions/Pages/ViewTransferRequisition.php` — audit integration (Step 7)
- `database/migrations/2026_09_03_084350_add_notes_to_stock_movements_table.php` — NEW (Step 6)
- `database/migrations/2026_09_03_091000_create_transfer_requisition_audits_table.php` — NEW (Step 7)
- `tests/Feature/AuditTrailAndNotesTest.php` — NEW (Step 9)
- `tests/playwright/dashboard.spec.ts` — NEW (Step 10)
- `tests/playwright/stock-adjustment.spec.ts` — NEW (Step 10)
- `tests/Feature/DashboardWidgetTest.php` — updated StockByWarehouse section (Step 11)

## Verification
- Pint formatting applied (dirty files only)
- Pest suite: **401 passed (784 assertions), 0 failures**
- Playwright E2E Tests - All Specs Passing:
  - dashboard.spec.ts: **5/5** (KPI cards, widgets, filters, alerts)
  - stock-adjustment.spec.ts: **4/4** (form fields, validation, submission)
  - products.spec.ts: **6/6** (list, create, role access, no JS errors)
  - warehouses.spec.ts: **6/6** (list, create, manager/staff 403 expected, no JS errors)
  - in-transit.spec.ts: **4/4** (list, manager access, no JS errors, data table)
  - transfer-orders.spec.ts: **4/4** (list, manager access, no JS errors, data table)
  - loss-ledger.spec.ts: **4/4** (admin/auditor access, no JS errors, data table)
  - stock-adjustment-form.spec.ts: **4/4** (form interaction, validation, submission, feedback)
  - stock-movements.spec.ts: **4/4** (list, filters, no JS errors, data table)
  - transfer-requisition.spec.ts: existing (verified passing)
  - admin.spec.ts, auditor.spec.ts, manager.spec.ts, staff.spec.ts: existing (verified passing)
  - role-access.spec.ts, workflow.spec.ts: existing (verified passing, workflow.test.skip intentional)
- All tests use storageState auth, zero flaky retries
- migrate:fresh --seed verified - all migrations pass, seeders complete
- Full test suite execution time: ~8 minutes (Playwright) + ~2 minutes (Pest)

## Impact
- Eliminates code duplication across InventoryService
- Corrects dashboard widget authorization and data sources
- Provides full audit trail for requisition lifecycle: submitted → confirmed → dispatched → received
- Ensures stock adjustment reasons are captured in the audit log
- All status transitions in `ViewTransferRequisition` are now auditable
- StockByWarehouseWidget displays correct movement counts
- Comprehensive Pest test coverage for audit trail and notes functionality
- Playwright E2E coverage for dashboard and stock adjustment flows
