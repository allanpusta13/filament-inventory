# Implementation Plan: Audit Fix v11 (Post-Blueprint v11 Cleanup)

**Date:** 2026-09-19  
**Status:** **COMPLETE** — All P0 items resolved  
**Source:** AUDIT_REPORT_v11.md findings  
**Scope:** Fix 5 codebase-ahead findings (4 medium, 1 low) — all P0 pre-merge

---

## Context

Blueprint v11 audit complete. 1017 tests passing. Codebase aligns 97%. 5 minor codebase-ahead findings require fixes before merge.

---

## Findings Fix

| # | Finding | Severity | Location | **Status** |
|---|---------|----------|----------|------------|
| 1 | `recordLoss` visible for `Completed` status | Medium | `TransferRequisitionsTable.php:167` | ✅ **DONE** |
| 2 | `recordLoss` uses float math (not bcmath) | Medium | `TransferRequisitionsTable.php:206-214` | ✅ **DONE** |
| 3 | `recordLoss` missing `warehouse_id` | Medium | `TransferRequisitionsTable.php:215-228` | ✅ **DONE** |
| 4 | `recordLoss` wrong `transfer_requisition_item_id` | Medium | `TransferRequisitionsTable.php:216` | ✅ **DONE** |
| 5 | `ProductVariant` doc-block corruption | Low | `ProductVariant.php:72-102` | ✅ **DONE** |

---

## Fix Summary (All Complete)

### Item 1: Restrict `recordLoss` visibility to `dispatched` / `partially_received`
**File:** `app/Filament/Resources/TransferRequisitions/Tables/TransferRequisitionsTable.php`
**Fix:** Removed `Completed` from `visible()` array. Policy already enforces correct boundary.
**Verified:** `TransferRequisitionPolicyTest` passes (3/3)

### Item 2: Replace float math with `LossLedger::calculateTotalFinancialLoss()` (bcmath)
**File:** `app/Filament/Resources/TransferRequisitions/Tables/TransferRequisitionsTable.php:206-214`
**Fix:** Uses `LossLedger::calculateTotalFinancialLoss()` with `bcmul()` internally for 4-decimal precision.
**Verified:** Service layer tests cover bcmath precision.

### Item 3: Add `warehouse_id` to `LossLedger::create()` payload
**File:** `app/Filament/Resources/TransferRequisitions/Tables/TransferRequisitionsTable.php` (line 218 area)
**Fix:** Added `'warehouse_id' => $record->to_warehouse_id` to create array.
**Rationale:** Blueprint Table 11: `warehouse_id` NOT NULL. Requisition's destination warehouse = loss location.

### Item 4: Fix `transfer_requisition_item_id` lookup
**File:** `app/Filament/Resources/TransferRequisitions/Tables/TransferRequisitionsTable.php` (line 216)
**Fix:** Look up `TransferRequisitionItem` by `product_variant_id` within current requisition:
```php
'transfer_requisition_item_id' => $record->items->where('product_variant_id', $data['product_variant_id'])->first()?->id,
```
**Verified:** Correct item ID now stored in LossLedger.

### Item 5: Fix `ProductVariant` doc-block corruption
**File:** `app/Models/ProductVariant.php` (lines 72-102)
**Fix:** Ran `vendor/bin/pint --dirty --format agent` on file. Doc-block formatting cleaned up.
**Verified:** `vendor/bin/pint --test app/Models/ProductVariant.php` passes. Full test suite passes.

---

## Test Verification Results

| Step | Command | Result |
|------|---------|--------|
| 1 | `vendor/bin/pest tests/Feature/Policies/TransferRequisitionPolicyTest.php` | ✅ 3 passed |
| 2 | `vendor/bin/pest tests/Feature/Resources/TransferRequisition` | ✅ All passed |
| 3 | `vendor/bin/pint --dirty --format agent app/Models/ProductVariant.php` | ✅ Clean |
| 4 | `vendor/bin/pest` (full suite) | ✅ 1017 passed, 8 risky, 13 skipped |

---

## Additional Verifications (P1 Items)

| Item | Status | Notes |
|------|--------|-------|
| `StockActions` `->form()` → `->schema()` migration | ✅ **VERIFIED** | Already uses `->schema()` on all 5 actions |
| `createOptionForm` auto-select | ✅ **VERIFIED** | `ProductForm.php` uses closure + `afterStateUpdated` |
| `strictAuthorization()` policy coverage | ✅ **VERIFIED** | All 9 policies have methods matching Filament abilities |

---

## Acceptance Criteria Met

- [x] `vendor/bin/pint --test app/Models/ProductVariant.php` passes
- [x] All 5 StockActions use `->schema()` and render correctly in UI (verified by existing tests)
- [x] `strictAuthorization()` enabled with zero "Ability not defined" errors
- [x] Product `createOptionForm` auto-selects new product in variant dropdown (verified)
- [x] Full test suite passes (1017+ tests)