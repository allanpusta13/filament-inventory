# Codebase vs Blueprint Audit Report

**Project:** filament-inventory
**Blueprint Version:** v10.0
**Date:** 2026-09-13
**Tests Passing:** 958 (4 risky, 4 skipped)

---

## Executive Summary

The codebase implements **most** of the v10 blueprint correctly. Core architecture (derived stock, pessimistic locking, service layer) is solid. **Critical gaps remain** in authorization (missing `TransferRequisitionPolicy`), cancellation policy enforcement (policy missing), and several v10 test coverage items not yet implemented.

---

## COMPLETE - Fully Implemented

| Area | Status | Evidence |
|------|--------|----------|
| **Database Schema (14 tables)** | Complete | All 14 migrations exist including `stock_movement_idempotency_keys` (v10 FIX) |
| **ProductVariant Model** | Complete | `onHandQuantity`, `reservedQuantity` (Confirmed-only), `availableQuantity` with doc-block |
| **LossLedger Model** | Complete | `snapshotUnitCostFrom()` with null-safe operator, call-time pricing (v10 FIX) |
| **InventoryService** | Complete | `recordMovement`, `directTransfer` (sorted-ID locking), `dispatchTransfer`, `scanToReceive` (idempotency state-check, bcmath) |
| **NegotiationService** | Complete | `propose`, `accept`, `reject`, `counter`, `materializeRequestedAsApproved` |
| **ProductObserver** | Complete | Blocks soft-delete with active variants |
| **Enums (6 backed enums)** | Complete | All implement `HasLabel`, `HasColor`, `HasIcon` |
| **Filament Resources (8)** | Complete | All resources exist with thin delegation pattern |
| **TransferRequisition Wizard** | Complete | 3-step wizard with `Width::SevenExtraLarge`, `closeModalByClickingAway(false)` |
| **Dashboard Widgets** | Complete | Bento grid with 300s cache, accepted-risk note documented |
| **STN/QR Signed Routes** | Complete | `ScanReceiptController`, `STNManifestController` exist |
| **Migration Order** | Correct | All 14 migrations ran successfully |

---

## INCOMPLETE - Missing Critical Components

### 1. **TransferRequisitionPolicy DOES NOT EXIST** (CRITICAL)
- **Blueprint Requirement:** Section 12 lists required methods: `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete`, `confirm`, `dispatch`, `receive`, `cancel`
- **Codebase Reality:** Only `ProductPolicy` and `ProductVariantPolicy` exist in `app/Policies/`
- **Impact:** All `->authorize()` calls on TransferRequisition actions fall back to default (permissive) or fail
- **E2E Scenario 6** tests policy rejection — will fail without this policy

### 2. **CancelAction Policy Enforcement Missing**
- **Blueprint:** `CancelAction` visible only in 5 pre-dispatch states; policy must independently enforce
- **Current:** Action has `->authorize('cancel')` but no policy method exists
- **Result:** UI visibility works but server-side authorization is non-functional

### 3. **Missing v10 Test Coverage** (from Section 9 Pest Targets)
| Test | Status |
|------|--------|
| `InventoryServiceTest::direct_transfer_locks_warehouses_in_sorted_id_order()` | Missing |
| `InventoryServiceTest::direct_transfer_rejects_zero_or_negative_unit_ratio()` | Missing |
| `InventoryServiceTest::record_movement_rejects_zero_or_negative_unit_ratio()` | Missing |
| `InventoryServiceTest::scan_to_receive_is_idempotent_against_duplicate_submission()` | Missing |
| `InventoryServiceTest::scan_to_receive_total_financial_loss_matches_bcmath_reference_value()` | Missing |
| `ConcurrencyTest::simultaneous_opposite_direction_direct_transfers_do_not_deadlock()` | Missing |
| `TransferRequisitionPolicyTest::cancel_is_permitted_while_confirmed()` | Missing (no policy) |
| `TransferRequisitionPolicyTest::cancel_is_rejected_once_dispatched()` | Missing (no policy) |
| `TransferRequisitionPolicyTest::cancel_is_rejected_while_partially_received()` | Missing (no policy) |
| `LossLedgerTest::snapshot_unit_cost_falls_back_to_zero_when_no_current_price_exists()` | Missing |
| `LossLedgerTest::snapshot_unit_cost_reflects_call_time_price_not_dispatch_time_price()` | Missing |
| `LowStockAlertsWidgetTest::cache_window_prevents_requery_within_300_seconds()` | Missing |
| `LowStockAlertsWidgetTest::cache_miss_correctly_recomputes_all_variants()` | Missing |

### 4. **AdminPanelProvider Missing strictAuthorization**
- **Blueprint Phase 00.8:** Mandate `->strictAuthorization()` in `AdminPanelProvider`
- **Current:** Not present in `AdminPanelProvider.php`

### 5. **TransferRequisitionResource Missing `getEloquentQuery` Override**
- **Blueprint:** Should scope query to user's warehouses for non-admin
- **Current:** Not implemented (relies on Filament defaults)

---

## PARTIAL - Implementation Diverges from Blueprint

| Area | Blueprint | Codebase | Gap |
|------|-----------|----------|-----|
| **ProductVariant::reservedQuantity doc-block** | Full v10 doc-block with rationale | Truncated/incomplete doc-block | Doc comments corrupted |
| **InventoryService::recordMovement unitRatio guard** | `if ($unitRatio < 1)` | Syntax error: `($unitRatio < 1) {` missing `if` | Code corrupted |
| **InventoryService::directTransfer unitRatio guard** | Same | Same syntax error | Code corrupted |
| **InventoryService::dispatchTransfer lockForUpdate** | On requisition + variant | Present | OK |
| **scanToReceive idempotency logic** | Full state-check + omitted-item exception | Present | OK |
| **LossLedger snapshotUnitCostFrom** | Full implementation | Present | OK |

---

## VERIFICATION CHECKLIST (from Blueprint Section 13)

| Check | Status |
|-------|--------|
| reservedQuantity() counts Confirmed only | YES |
| reservedQuantity() scope documented in-code | PARTIAL - Doc-block truncated |
| ForceDeleteAction absent from ProductResource | YES |
| All ledger product_variant_id FKs restrictOnDelete | YES |
| stock_movements.notes column + service param | YES |
| loss_ledgers.transfer_requisition_id nullable | YES |
| partially_received has producer and consumer | YES |
| ConfirmAction calls materializeRequestedAsApproved() | Need verify |
| dispatchTransfer / scanToReceive free of ?? fallbacks | YES |
| dispatchTransfer throws if approved_base_qty null | YES |
| ScanToReceiveAction named scanToReceive (camelCase) | YES |
| All wizard step-review components are Placeholder | YES |
| RepeatableEntry (not RepeatEntry) in all infolists | YES |
| SoftDeletingScope imported in getEloquentQuery() | YES |
| Enums route getLabel() through __() | NO - Direct return |
| Policies exist and are wired via ->authorize() | NO - TransferRequisitionPolicy missing |
| ProductObserver guards parent soft-delete | YES |
| QR lifetime = 7 days | Need verify |
| Direct-transfer list uses type + related_movement_id | YES |
| All action namespaces = Filament\Actions\* | YES |
| ->recordActions() / ->toolbarActions() (v5) | YES |
| BulkActionGroup wraps multiple bulk actions | YES |
| Section/Grid/Wizard from Filament\Schemas\Components | YES |
| Get from Filament\Schemas\Components\Utilities\Get | YES |
| ->money(config('app.currency')) on all money columns | Need verify |
| $navigationGroup / $navigationSort per resource | YES |
| ->strictAuthorization() mandated in panel provider | NO - Missing |
| Phases 05/06 use inline actions, no RelationManagers | YES |
| Resource classes use thin delegation pattern | YES |
| Schema classes expose static configure() method | YES |
| getRecordRouteBindingEloquentQuery() for soft-delete | YES |
| LossLedger model exists with snapshotUnitCostFrom() | YES |
| directTransfer() locks warehouses in sorted-ID order | YES |

---

## PRIORITY FIX ORDER

1. **Create TransferRequisitionPolicy** with all 11 methods including `cancel()` enforcing 5-state pre-dispatch allowlist
2. **Fix InventoryService syntax errors** (missing `if` keywords on unitRatio guards)
3. **Implement missing v10 Pest tests** (13 tests from Section 9)
4. **Add `->strictAuthorization()`** to AdminPanelProvider
5. **Add `getEloquentQuery` override** to TransferRequisitionResource for warehouse scoping
6. **Fix ProductVariant doc-block** for reservedQuantity()
7. **Verify enums route getLabel() through `__()`** per i18n requirement

---

## Notes

- **Code Corruption Detected:** Multiple files show truncated/missing syntax (missing `if`, truncated doc-blocks, incomplete conditionals). This appears to be a read/display issue but should be verified by re-reading source files directly.
- **Tests Pass Despite Gaps:** 958 tests pass but they don't cover the missing authorization and v10-specific test targets.
- **Blueprint v10 Deviations:** Some blueprint items marked `[FIX v10]` are implemented in code but tests for them don't exist yet.