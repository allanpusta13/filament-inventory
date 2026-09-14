# Filament Inventory — Re-Audit: Codebase vs Blueprint v10

**Format:** Side-by-side comparison with Blueprint Spec | Codebase | **Actual Difference** | Status | Priority

---

## COUNCIL VERDICT SUMMARY (Pre-Computed)

| Question | Consensus | Priority |
|----------|-----------|----------|
| 1. All 6 missing policies critical? | **TransferRequisitionPolicy = CRITICAL** (enforces cancellation boundary server-side). Other 5 = DEFER to v11 (read-only resources, no state-changing actions). | P0 / P2 |
| 2. strictAuthorization blocking? | **YES — P0**. Without it, unhandled policy methods default to permissive. Must enable AFTER all policies exist. | P0 |
| 3. 13 missing v10 tests prioritized? | **Tier 1 (P0):** 5 InventoryService v10 tests (locking, unitRatio, idempotency, bcmath). **Tier 2 (P1):** Concurrency test. **Tier 3 (P2):** 3 Policy tests (need policy first), 2 LossLedger, 2 Widget tests. | P0/P1/P2 |
| 4. Enum i18n blocker v10? | **NO — v11**. Blueprint Principle 10 says "route through __()" but no test validates it. Defer. | P2 |
| 5. getEloquentQuery() needed if policies exist? | **YES — P1**. Policy = server auth. Query scope = data isolation + performance (avoids loading unauthorized rows). Both layers needed. | P1 |

---

## RE-AUDIT: SIDE-BY-SIDE WITH PRIORITY

### Section 1: Executive Architecture & System Principles

| # | Blueprint | Codebase | **Actual Diff** | Status | Priority |
|---|-----------|----------|-----------------|--------|----------|
| 1 | Pure Derived Stock | SUM in onHandQuantity | None | YES | — |
| 2 | Decoupled Pricing | Matches | None | YES | — |
| 3 | Pessimistic Locking | lockForUpdate() everywhere | None | YES | — |
| 4 | Canonical FK naming | Matches | None | YES | — |
| 5 | 10-state lifecycle | Enum has 10 cases | None | YES | — |
| 6 | Substitute variant swap | Implemented | None | YES | — |
| 7 | Scanned receipt loss | scanToReceive() exact | None | YES | — |
| 8 | Signed QR 7-day | Controllers exist | Need verify expiry | PARTIAL | P1 |
| 9 | Modal-first UI | Width::SevenExtraLarge | None | YES | — |
| 10 | **i18n enums** | **Direct strings** | **Missing `__()`** | PARTIAL | **P2 (v11)** |
| 11 | FK restrictOnDelete | All migrations | None | YES | — |
| 12 | Auth vs Visibility | Both used | None | YES | — |
| 13 | v10 Reservation=Confirmed | Full doc-block | None | YES | — |
| 14 | v10 Cancellation boundary | UI only, **no policy** | **Policy missing** | NO | **P0** |
| 15 | v10 Cost snapshot call-time | Implemented | None | YES | — |

---

### Section 2: Filament Resource Structure

| Resource | Blueprint | Codebase | **Actual Diff** | Status | Priority |
|----------|-----------|----------|-----------------|--------|----------|
| Products | Thin delegation | Complete | None | YES | — |
| **TransferRequisitions** | Thin + **getEloquentQuery()** | **Missing getEloquentQuery()** | **Missing warehouse scope** | NO | **P1** |
| DirectTransfers | Thin + Wizard | Complete | None | YES | — |
| InTransits | Read-only | Complete | None | YES | — |
| StockMovements | Read-only | Complete | None | YES | — |
| LossLedgers | Read-only + View | Complete | None | YES | — |
| Warehouses | Thin + Drawer | Complete | None | YES | — |
| Users | Thin + Modal | Complete | None | YES | — |

---

### Section 3: Database Schema (14 Tables)

| Table | Blueprint | Codebase | **Diff** | Status |
|-------|-----------|----------|----------|--------|
| All 14 | Exact spec | All migrations present | **None** | YES |

---

### Section 4: Wizard Forms

| Form | Blueprint | Codebase | **Diff** | Status |
|------|-----------|----------|----------|--------|
| TransferRequisitionForm | 3-step, Width::SevenExtraLarge | Exact | None | YES |
| DirectTransferForm | 3-step, Width::SevenExtraLarge | Exact | None | YES |

---

### Section 5: Model-Level Stock Engine

| Model/Method | Blueprint | Codebase | **Diff** | Status |
|--------------|-----------|----------|----------|--------|
| ProductVariant (all) | Complete | Complete | None | YES |
| reservedQuantity() | Confirmed-only + doc-block | Exact | None | YES |
| LossLedger | snapshotUnitCostFrom() call-time | Exact | None | YES |
| ProductObserver | Blocks soft-delete with variants | Registered | None | YES |

---

### Section 6: Transactional Service Layer

| Method | Blueprint v10 Fixes | Codebase | **Diff** | Status |
|--------|---------------------|----------|----------|--------|
| recordMovement() | unitRatio >= 1 guard | Line 50 | None | YES |
| directTransfer() | Sorted-ID locking + unitRatio guard | Lines 119-124 | None | YES |
| dispatchTransfer() | No ??, throws if approved null | Lines 215-220 | None | YES |
| scanToReceive() | Idempotency state-check + omitted + bcmath | Lines 343-475 | None | YES |
| NegotiationService | Unchanged v9.1 | Complete | None | YES |

---

### Section 7: Master Resources — TransferRequisitionResource

| Aspect | Blueprint | Codebase | **Diff** | Status | Priority |
|--------|-----------|----------|----------|--------|----------|
| Model | TransferRequisition | YES | None | YES | — |
| Navigation | OPERATIONS sort 1 | YES | None | YES | — |
| Table Actions | All 11 with authorize/visible | Present in Table | **No policy backing** | NO | **P0** |
| getEloquentQuery() | **Warehouse scope for non-admin** | **MISSING** | **Missing method** | NO | **P1** |
| getRecordRouteBinding | Without SoftDeletingScope | YES | None | YES | — |

---

### Section 8: Design System

| Element | Blueprint | Codebase | **Diff** | Status | Priority |
|---------|-----------|----------|----------|--------|----------|
| Colors | DESIGN.md palette | Primary Blue set | Need verify CSS vars | PARTIAL | P2 |
| Elevation | Flat rest, interaction only | DESIGN.md | Need verify | PARTIAL | P2 |
| Bento Grid | 4-col glassmorphism | DESIGN.md | Need verify | PARTIAL | P2 |
| Widget Caching | 300s, LowStock per-variant | StatsOverview only | 3 widgets missing | PARTIAL | P1 |
| Dashboard Widgets | 4 specified | 1 verified | Need verify 3 | PARTIAL | P1 |

---

### Section 8: 17-Stage Execution Sequence

| Phase | Blueprint | Codebase | **Diff** | Status | Priority |
|-------|-----------|----------|----------|--------|----------|
| 00 | **strictAuthorization** | **MISSING** | **Missing config** | NO | **P0** |
| 01 | 14 migrations | All 14 | None | YES | — |
| 02 | Base seeders | Not verified | Unknown | PARTIAL | P2 |
| 03 | Models + Enums + Observer | All present | None | YES | — |
| 04 | Services v10 | Complete | None | YES | — |
| 05 | ProductResource | Complete | None | YES | — |
| 06 | Price/Unit actions | Exist | None | YES | — |
| 07 | Warehouses + Adjustments | Complete | None | YES | — |
| 08 | Requisition Wizard | Complete | None | YES | — |
| 09 | Negotiation Loop UI | Complete | None | YES | — |
| 10 | Dispatch + Cancel 5-state | Actions OK, **policy missing** | **Policy missing** | NO | **P0** |
| 11 | STN + QR 7-day | Controllers exist | Need verify expiry | PARTIAL | P1 |
| 12 | Scan-to-Receive | Complete + idempotency | None | YES | — |
| 13 | Audit Ledgers | Complete | None | YES | — |
| 14 | Bento Dashboard | Partial | Need verify | PARTIAL | P1 |
| 15 | **i18n translation** | **Enums missing `__()`** | **6 enums × 10 cases** | PARTIAL | **P2 (v11)** |
| 16 | CI/CD Testing | Tests run, 13 missing | **13 v10 tests missing** | NO | **P0/P1/P2** |

---

### Section 9: CI/CD Testing — 13 Missing v10 Tests (PRIORITIZED)

| Tier | Test | Blueprint Name | Expected File | Status | Priority |
|------|------|----------------|---------------|--------|----------|
| **P0** | 1 | `direct_transfer_locks_warehouses_in_sorted_id_order` | InventoryServiceTest.php | NO | **P0** |
| **P0** | 2 | `direct_transfer_rejects_zero_or_negative_unit_ratio` | InventoryServiceTest.php | NO | **P0** |
| **P0** | 3 | `record_movement_rejects_zero_or_negative_unit_ratio` | InventoryServiceTest.php | NO | **P0** |
| **P0** | 4 | `scan_to_receive_is_idempotent_against_duplicate_submission` | InventoryServiceTransferLifecycleTest.php | NO | **P0** |
| **P0** | 5 | `scan_to_receive_total_financial_loss_matches_bcmath_reference_value` | InventoryServiceTransferLifecycleTest.php | NO | **P0** |
| **P1** | 6 | `simultaneous_opposite_direction_direct_transfers_do_not_deadlock` | ConcurrencyTest.php (new) | NO | **P1** |
| **P2** | 7 | `cancel_is_permitted_while_confirmed` | TransferRequisitionPolicyTest.php (new) | NO | **P2** (needs policy) |
| **P2** | 8 | `cancel_is_rejected_once_dispatched` | TransferRequisitionPolicyTest.php (new) | NO | **P2** (needs policy) |
| **P2** | 9 | `cancel_is_rejected_while_partially_received` | TransferRequisitionPolicyTest.php (new) | NO | **P2** (needs policy) |
| **P2** | 10 | `snapshot_unit_cost_falls_back_to_zero_when_no_current_price_exists` | LossLedgerTest.php (new) | NO | **P2** |
| **P2** | 11 | `snapshot_unit_cost_reflects_call_time_price_not_dispatch_time_price` | LossLedgerTest.php (new) | NO | **P2** |
| **P2** | 12 | `cache_window_prevents_requery_within_300_seconds` | LowStockAlertsWidgetTest.php (new) | NO | **P2** |
| **P2** | 13 | `cache_miss_correctly_recomputes_all_variants` | LowStockAlertsWidgetTest.php (new) | NO | **P2** |

---

### Section 12: Authorization Mapping — POLICIES

| Policy | Blueprint Methods | Codebase | **Diff** | Status | Priority |
|--------|-------------------|----------|----------|--------|----------|
| ProductPolicy | 7 methods | YES Exists | None | YES | — |
| ProductVariantPolicy | 10 methods | YES Exists | None | YES | — |
| **TransferRequisitionPolicy** | **11 methods (incl cancel 5-state)** | **MISSING** | **FILE ABSENT** | NO | **P0** |
| StockMovementPolicy | viewAny, view | **MISSING** | File absent | NO | **P2** |
| InTransitPolicy | viewAny, view, receive | **MISSING** | File absent | NO | **P2** |
| LossLedgerPolicy | viewAny, view, recordLoss | **MISSING** | File absent | NO | **P2** |
| WarehousePolicy | 6 methods | **MISSING** | File absent | NO | **P2** |
| UserPolicy | 4 methods | **MISSING** | File absent | NO | **P2** |

---

### Section 13: Verification Checklist — KEY FAILS

| # | Check | Blueprint | Codebase | **Diff** | Status | Priority |
|---|-------|-----------|----------|----------|--------|----------|
| 15 | Enums route getLabel through `__()` | YES | NO Direct strings | **6 enums × 10 cases** | NO | **P2 (v11)** |
| 16 | TransferRequisitionPolicy exists | YES | NO Missing | **File absent** | NO | **P0** |
| 27 | strictAuthorization() in panel | YES | NO Missing | **Config absent** | NO | **P0** |
| 8 | ConfirmAction → materializeRequestedAsApproved | YES | Need verify | Unknown | PARTIAL | P1 |
| 18 | QR lifetime = 7 days | YES | Need verify | Unknown | PARTIAL | P1 |
| 25 | `->money(config('app.currency'))` | YES | Need verify | Unknown | PARTIAL | P1 |

---

## PRIORITY FIX ORDER (COUNCIL-ALIGNED)

### P0 — DO FIRST (Blocking v10 Completion)

| # | Action | Files to Create/Modify |
|---|--------|------------------------|
| 1 | **Create TransferRequisitionPolicy** (11 methods, cancel 5-state) | `app/Policies/TransferRequisitionPolicy.php` |
| 2 | **Add strictAuthorization()** to AdminPanelProvider | `app/Providers/Filament/AdminPanelProvider.php` |
| 3 | **Implement 5 Tier-1 v10 tests** (InventoryService locking, unitRatio, idempotency, bcmath) | `tests/Unit/Services/InventoryServiceTest.php`, `tests/Unit/Services/InventoryServiceTransferLifecycleTest.php` |

### P1 — DO SECOND (Data Isolation + Concurrency)

| # | Action | Files |
|---|--------|-------|
| 4 | Add `getEloquentQuery()` warehouse scope to TransferRequisitionResource | `app/Filament/Resources/TransferRequisitions/TransferRequisitionResource.php` |
| 5 | Implement ConcurrencyTest (deadlock prevention) | `tests/Unit/ConcurrencyTest.php` (new) |
| 6 | Verify QR 7-day expiry in STNManifestController | `app/Http/Controllers/STNManifestController.php` |
| 7 | Verify ConfirmAction → materializeRequestedAsApproved wiring | `app/Filament/Resources/TransferRequisitions/Pages/EditTransferRequisition.php` |
| 8 | Verify `->money(config('app.currency'))` on all money columns | `ProductsTable.php`, `LossLedgersTable.php`, etc. |
| 9 | Verify/implement 3 missing dashboard widgets | `LowStockAlertsWidget`, `RecentMovementsWidget`, `ActiveInTransitWidget` |

### P2 — DEFER TO v11 (Non-Blocking)

| # | Action | Files |
|---|--------|-------|
| 10 | Create 5 read-only resource policies | `StockMovementPolicy`, `InTransitPolicy`, `LossLedgerPolicy`, `WarehousePolicy`, `UserPolicy` |
| 11 | Fix enum i18n — wrap getLabel() in `__()` | 6 enum files in `app/Enums/` |
| 12 | Implement 3 Policy tests (need #10 first) | `TransferRequisitionPolicyTest.php` |
| 13 | Implement 2 LossLedger tests | `LossLedgerTest.php` (new) |
| 14 | Implement 2 Widget tests | `LowStockAlertsWidgetTest.php` (new) |
| 15 | Verify base seeders | `database/seeders/` |
| 16 | Verify Bento CSS glassmorphism | `resources/css/filament/admin/theme.css` |
| 17 | Run Playwright E2E scenarios (7 scenarios) | `tests/playwright/` |

---

## STATUS MATRIX

| Category | Total | YES | PARTIAL | NO | P0 | P1 | P2 |
|----------|-------|-----|---------|-----|-----|-----|-----|
| Database | 14 | 14 | 0 | 0 | 0 | 0 | 0 |
| Models | 7 | 7 | 0 | 0 | 0 | 0 | 0 |
| Services | 5 | 5 | 0 | 0 | 0 | 0 | 0 |
| Resources | 8 | 7 | 0 | 1 | 0 | 1 | 0 |
| Policies | 7 | 2 | 0 | 5 | 1 | 0 | 4 |
| Config | 1 | 0 | 0 | 1 | 1 | 0 | 0 |
| Enums i18n | 6 | 0 | 0 | 6 | 0 | 0 | 6 |
| Tests v10 | 13 | 0 | 0 | 13 | 5 | 1 | 7 |
| Design/Widgets | 5 | 1 | 0 | 4 | 0 | 4 | 0 |
| Verification | 33 | 22 | 7 | 4 | 2 | 5 | 4 |

---

## VERDICT

**v10 Blockers (P0):** 3 items — TransferRequisitionPolicy, strictAuthorization, 5 Tier-1 tests
**v10 Completers (P1):** 6 items — getEloquentQuery, concurrency test, 4 verifications
**v11 Deferred (P2):** 17 items — 5 policies, 6 enums i18n, 7 remaining tests, 4 design verifications

**Estimated effort to v10 complete:** ~2-3 days focused work
**Estimated effort to v11 complete:** ~1-2 weeks including E2E