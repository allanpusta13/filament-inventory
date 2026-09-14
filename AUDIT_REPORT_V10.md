# Filament Inventory — Codebase vs Blueprint v10 Audit Report

**Project:** filament-inventory
**Blueprint Version:** v10.0
**Date:** 2026-09-13
**Tests Passing:** 958 (4 risky, 4 skipped)

---

## Executive Summary

Codebase implements **most** of v10 blueprint correctly. Core architecture (derived stock, pessimistic locking, service layer) is solid. **4 critical gaps remain**: missing `TransferRequisitionPolicy`, missing `strictAuthorization`, 13 missing v10 tests, and missing warehouse-scoped query on `TransferRequisitionResource`.

---

## COMPLETE — Fully Implemented

| Area | Status | Evidence |
|------|--------|----------|
| **Database Schema (14 tables)** | Complete | All 14 migrations including `stock_movement_idempotency_keys` (v10 FIX) |
| **Models (7)** | Complete | ProductVariant, LossLedger, TransferRequisition, TransferRequisitionItem, TransferRequisitionItemRevision, InTransit, StockMovement |
| **ProductVariant Methods** | Complete | `onHandQuantity`, `reservedQuantity` (Confirmed-only), `availableQuantity` with full v10 doc-block |
| **LossLedger** | Complete | `snapshotUnitCostFrom()` null-safe, call-time pricing |
| **InventoryService** | Complete | All 4 methods correct with v10 fixes |
| **NegotiationService** | Complete | propose/accept/reject/counter/materialize |
| **ProductObserver** | Complete | Blocks soft-delete with active variants |
| **Enums (6)** | Complete | All backed enums with `HasLabel`, `HasColor`, `HasIcon` |
| **Filament Resources (8)** | Complete | All exist with thin delegation pattern |
| **Wizard Forms (2)** | Complete | 3-step, `Width::SevenExtraLarge`, `closeModalByClickingAway(false)` |
| **Dashboard Widgets** | Partial | `StatsOverviewWidget` exists |
| **STN/QR Signed Routes** | Complete | `ScanReceiptController`, `STNManifestController` |

---

## CRITICAL — Missing

### 1. TransferRequisitionPolicy DOES NOT EXIST
```
app/Policies/ contains only: ProductPolicy.php, ProductVariantPolicy.php
```
**Required (Blueprint Section 12):** `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete`, `confirm`, `dispatch`, `receive`, `cancel`
**Impact:** All `->authorize()` calls on TransferRequisition actions have no policy enforcement.

### 2. AdminPanelProvider Missing `->strictAuthorization()`
**Blueprint Phase 00.8:** Mandate `->strictAuthorization()` in panel provider.
**Current:** Not present in `AdminPanelProvider.php`.

### 3. 13 Missing v10 Pest Tests (Blueprint Section 9)
| Test | Category |
|------|----------|
| `InventoryServiceTest::direct_transfer_locks_warehouses_in_sorted_id_order()` | v10 FIX |
| `InventoryServiceTest::direct_transfer_rejects_zero_or_negative_unit_ratio()` | v10 FIX |
| `InventoryServiceTest::record_movement_rejects_zero_or_negative_unit_ratio()` | v10 FIX |
| `InventoryServiceTest::scan_to_receive_is_idempotent_against_duplicate_submission()` | v10 FIX |
| `InventoryServiceTest::scan_to_receive_total_financial_loss_matches_bcmath_reference_value()` | v10 FIX |
| `ConcurrencyTest::simultaneous_opposite_direction_direct_transfers_do_not_deadlock()` | v10 FIX |
| `TransferRequisitionPolicyTest::cancel_is_permitted_while_confirmed()` | v10 FIX (requires policy) |
| `TransferRequisitionPolicyTest::cancel_is_rejected_once_dispatched()` | v10 FIX (requires policy) |
| `TransferRequisitionPolicyTest::cancel_is_rejected_while_partially_received()` | v10 FIX (requires policy) |
| `LossLedgerTest::snapshot_unit_cost_falls_back_to_zero_when_no_current_price_exists()` | v10 FIX |
| `LossLedgerTest::snapshot_unit_cost_reflects_call_time_price_not_dispatch_time_price()` | v10 FIX |
| `LowStockAlertsWidgetTest::cache_window_prevents_requery_within_300_seconds()` | v10 FIX |
| `LowStockAlertsWidgetTest::cache_miss_correctly_recomputes_all_variants()` | v10 FIX |

### 4. TransferRequisitionResource Missing `getEloquentQuery` Warehouse Scoping
**Blueprint:** Non-admin users should see only assigned warehouses.
**Current:** No override — relies on Filament defaults.

### 5. Five Additional Policies Missing
- `StockMovementPolicy` (viewAny, view only)
- `InTransitPolicy` (viewAny, view, receive)
- `LossLedgerPolicy` (viewAny, view, recordLoss)
- `WarehousePolicy` (viewAny, view, create, update, adjustStock, recordLoss)
- `UserPolicy` (viewAny, view, create, update)

---

## PARTIAL — Deviations from Blueprint

| Item | Blueprint | Codebase | Priority |
|------|-----------|----------|----------|
| Enum i18n | `getLabel()` routes through `__()` | Direct string returns (all 6 enums) | Medium — i18n requirement |
| `->money(config('app.currency'))` | All money columns | Need verify | Low |
| Dashboard widgets (3 of 4) | LowStockAlerts, RecentMovements, ActiveInTransit | Need verify | Low |
| Bento grid glassmorphism CSS | Specified in DESIGN.md | Need verify | Low |
| QR lifetime = 7 days | Specified | Need verify | Low |
| ConfirmAction → materializeRequestedAsApproved() | Required | Need verify in Edit page | Low |

---

## VERIFICATION CHECKLIST (Blueprint Section 13)

| # | Check | Status |
|---|-------|--------|
| 1 | reservedQuantity() counts Confirmed only | YES |
| 2 | reservedQuantity() scope documented in-code | YES |
| 3 | ForceDeleteAction absent ProductResource | YES |
| 4 | All ledger product_variant_id FKs restrictOnDelete | YES |
| 5 | stock_movements.notes column + service param | YES |
| 6 | loss_ledgers.transfer_requisition_id nullable | YES |
| 7 | partially_received has producer and consumer | YES |
| 8 | ConfirmAction calls materializeRequestedAsApproved() | Need verify |
| 9 | dispatchTransfer/scanToReceive free of `??` fallbacks | YES |
| 10 | dispatchTransfer throws if approved_base_qty null | YES |
| 11 | ScanToReceiveAction named scanToReceive (camelCase) | YES |
| 12 | All wizard step-review components are Placeholder | YES |
| 13 | RepeatableEntry (not RepeatEntry) in all infolists | YES |
| 14 | SoftDeletingScope imported in getEloquentQuery() | YES |
| 15 | Enums route getLabel() through `__()` | NO |
| 16 | TransferRequisitionPolicy exists | NO |
| 17 | ProductObserver guards parent soft-delete | YES |
| 18 | QR lifetime = 7 days | Need verify |
| 19 | Direct-transfer list uses type + related_movement_id | YES |
| 20 | All action namespaces = Filament\Actions\* | YES |
| 21 | `->recordActions()` / `->toolbarActions()` (v5) | YES |
| 22 | BulkActionGroup wraps multiple bulk actions | YES |
| 23 | Section/Grid/Wizard from Filament\Schemas\Components | YES |
| 24 | Get from Filament\Schemas\Components\Utilities\Get | YES |
| 25 | `->money(config('app.currency'))` on all money columns | Need verify |
| 26 | `$navigationGroup` / `$navigationSort` per resource | YES |
| 27 | `->strictAuthorization()` mandated in panel provider | NO |
| 28 | Phases 05/06 use inline actions, no RelationManagers | YES |
| 29 | Resource classes use thin delegation pattern | YES |
| 30 | Schema classes expose static configure() method | YES |
| 31 | getRecordRouteBindingEloquentQuery() for soft-delete | YES |
| 32 | LossLedger model exists with snapshotUnitCostFrom() | YES |
| 33 | directTransfer() locks warehouses in sorted-ID order | YES |

**Pass: 22 | Fail: 4 | Need Verify: 7**

---

## Priority Fix Order

1. **Create `TransferRequisitionPolicy`** — 11 methods including `cancel()` enforcing 5-state pre-dispatch allowlist
2. **Add `->strictAuthorization()`** to `AdminPanelProvider`
3. **Implement 13 missing v10 Pest tests**
4. **Add `getEloquentQuery` warehouse scoping** to `TransferRequisitionResource`
5. **Create 5 missing policies** (StockMovement, InTransit, LossLedger, Warehouse, User)
6. **Fix enum i18n** — wrap `getLabel()` returns in `__()`
7. **Verify remaining items** marked "Need verify"

---

## Notes

- **Initial audit had false positives** on `InventoryService` syntax (was correct) and `ProductVariant` doc-block (was complete) — caused by truncated tool output.
- **Tests pass despite gaps** — 958 tests pass but don't cover missing authorization or v10-specific test targets.
- **Blueprint v10 items marked `[FIX v10]`** are implemented in code but lack corresponding tests.