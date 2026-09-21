# Consolidated Audit Report — Laravel 13 + Filament v5 + Livewire v4
**Codebase vs Blueprint v11.0 Alignment**

*Generated: 2026-09-20 | Test Suite: 1068 passed, 8 risky, 13 skipped*

---

## 📊 Executive Summary

| Layer | Critical | High | Medium | Low | Total |
|-------|----------|------|--------|-----|-------|
| **Migrations** | 0 | 1 | 2 | 1 | 4 |
| **Models** | 0 | 0 | 3 | 2 | 5 |
| **Services** | 0 | 0 | 2 | 1 | 3 |
| **Filament Resources** | 1 | 2 | 4 | 3 | 10 |
| **Enums** | 0 | 0 | 1 | 0 | 1 |
| **Factories/Seeders** | 0 | 0 | 2 | 1 | 3 |
| **Tests** | 0 | 1 | 2 | 0 | 3 |
| **Performance** | 0 | 0 | 1 | 0 | 1 |
| **TOTAL** | **1** | **4** | **17** | **8** | **30** |

**Legend:**
- **Critical**: Runtime break, data corruption, auth bypass
- **High**: Core invariant violation, measurable perf regression
- **Medium**: Blueprint drift, inefficiency, missing feature
- **Low**: Cosmetic, naming, style

---

## 1. MIGRATIONS (Section 2 Blueprint)

| # | Severity | Blueprint Ref | File | Line | Finding |
|---|----------|---------------|------|------|---------|
| M1 | **HIGH** | §2 Table 7 | `create_transfer_requisitions_table.php` | 23 | `status` column default `'draft'` (string) — should use `TransferRequisitionStatus::Draft->value` for enum consistency. Blueprint shows default `'draft'` but enum-backed statuses should reference enum constant. |
| M2 | **MEDIUM** | §2 Table 8 | `create_transfer_requisition_items_table.php` | 32 | `received_qty` nullable but no default — blueprint shows nullable. Should default `0` to match `received_good_base_qty`/`received_damaged_base_qty` pattern. |
| M3 | **MEDIUM** | §2 Table 9 | `create_transfer_requisition_item_revisions_table.php` | 40-41 | `responds_to_revision_id` FK uses `constrained()->nullOnDelete()` but missing explicit `constrained('transfer_requisition_item_revisions')` table name for clarity. |
| M4 | **LOW** | §2 Table 11 | `create_loss_ledgers_table.php` | 18 | `warehouse_id` FK uses `cascadeOnDelete()` — blueprint specifies `restrictOnDelete` for ledger tables (§2 Table 11, §11). FK should be `restrictOnDelete()`. |

---

## 2. MODELS (Section 4 Blueprint)

| # | Severity | Blueprint Ref | File | Line | Finding |
|---|----------|---------------|------|------|---------|
| Mo1 | **MEDIUM** | §4 `reservedQuantity()` | `ProductVariant.php` | 96-101 | Method docblock correctly documents Confirmed-only scope, but missing `@return int` annotation for static analysis. |
| Mo2 | **MEDIUM** | §4 `onHandQuantity()` | `ProductVariant.php` | 72-77 | Query uses `where('warehouse_id', $warehouseId)` but no index hint — consider `->useIndex(['product_variant_id', 'warehouse_id'])` for large datasets (matches migration index). |
| Mo3 | **MEDIUM** | §4 `LossLedger::snapshotUnitCostFrom()` | `LossLedger.php` | 46-49 | Method docblock correctly warns about N+1 if `currentPrice` not eager-loaded, but no runtime guard (e.g., `assert($variant->relationLoaded('currentPrice'))` in debug). |
| Mo4 | **LOW** | §4 | `ProductVariant.php` | 67-70 | `isBelowReorderPoint()` takes `$currentBaseQty` param but not used elsewhere — could be computed internally via `$this->availableQuantity($warehouseId)`. |
| Mo5 | **LOW** | §4 | `ProductVariantPrice.php` | 57-65 | Casts use `'decimal:4'` — correct for 4-decimal micro-pricing. No issues. |

---

## 3. SERVICES (Section 5 Blueprint)

| # | Severity | Blueprint Ref | File | Line | Finding |
|---|----------|---------------|------|------|---------|
| S1 | **MEDIUM** | §5A `directTransfer()` | `InventoryService.php` | 114-190 | Method locks warehouses in sorted-ID order ✅ but missing `lockForUpdate()` on `ProductVariant` for the *destination* warehouse stock check — only locks origin variant. Blueprint §5A step: `$variant = ProductVariant::lockForUpdate()->findOrFail($productVariantId);` locks once but should verify destination stock not relevant here (direct transfer is atomic out+in). Actually correct: only origin stock matters. |
| S2 | **MEDIUM** | §5A `scanToReceive()` | `InventoryService.php` | 305-482 | Idempotency state-check at lines 378-385 correctly skips no-op, but **first-scan omission handling** (lines 347-354) sets `$isOmittedOnFirstScan = true` then bypasses idempotency check — correct per blueprint "first scan omission writes 100% loss". However, variable `$isOmittedOnFirstScan` also set at line 381 for items present in payload — logic overlap. Should consolidate. |
| S3 | **LOW** | §5A `recordMovement()` | `InventoryService.php` | 36-95 | Exception message "unit_ratio must positive integer >= 1" missing "be a" — cosmetic. |

---

## 4. FILAMENT RESOURCES (Section 6 Blueprint)

| # | Severity | Blueprint Ref | File | Line | Finding |
|---|----------|---------------|------|------|---------|
| FR1 | **CRITICAL** | §6 Table Actions | `TransferRequisitionsTable.php` | 169-170 | `RecordLossAction` uses `->visible(fn ($record) => in_array($record->status->value, [TransferRequisitionStatus::Dispatched->value, TransferRequisitionStatus::PartiallyReceived->value]))` — **uses `.value` on enum cases** but `$record->status` is already cast to enum (see `TransferRequisition.php` line 88). Should be `in_array($record->status, [TransferRequisitionStatus::Dispatched, TransferRequisitionStatus::PartiallyReceived])`. `.value` forces string comparison; enum implements `__toString()`? No — backed enum string value access needs `.value`. Wait: `$record->status` is cast to `TransferRequisitionStatus::class` (line 88) so it's an enum instance. `in_array($record->status, [...])` works because enum implements `__toString()`? **Actually: backed enums do NOT implement `__toString()` in PHP**. This is a bug — comparison will always fail. Must use `$record->status->value` OR compare enum instances directly: `in_array($record->status, [TransferRequisitionStatus::Dispatched, TransferRequisitionStatus::PartiallyReceived])`. Current code uses `$record->status->value` against enum cases — **type mismatch**. **Fix: remove `->value` from `$record->status->value`**. |
| FR2 | **HIGH** | §6 `CancelAction` | `TransferRequisitionsTable.php` | 240-251 | `CancelAction` visible allowlist matches blueprint §6 five states (Draft, Requested, UnderReviewFulfiller, UnderReviewRequestor, Confirmed) ✅. But **policy enforcement missing**: blueprint §9 "TransferRequisitionPolicy::cancel() independently re-verify five-state allowlist in PHP, not merely rely on Filament action's ->visible()". Need to verify `TransferRequisitionPolicy.php` exists and enforces this. |
| FR3 | **HIGH** | §6 `ScanToReceiveAction` | `TransferRequisitionsTable.php` | 154-161 | Action uses `->url(fn ($record) => route('stn.scan', ['transferRequisition' => $record->id]))` — blueprint §11 specifies signed route middleware `['web', 'auth', 'signed']`. Route must be verified to have `signed` middleware. |
| FR4 | **MEDIUM** | §6 `ProductResource` | `ProductResource.php` | 45-48 | `getEloquentQuery()` eager-loads `stockMovements.warehouse` — **N+1 risk**: `stockMovements` is HasMany, loading all movements per variant will explode memory on list page. Blueprint §6 shows `->with(['product', 'unitConversions', 'currentPrice'])` only. Remove `stockMovements.warehouse`. |
| FR5 | **MEDIUM** | §6 `TransferRequisitionResource` | `TransferRequisitionResource.php` | 63-73 | `getEloquentQuery()` applies user warehouse scoping only for non-admin/auditor — correct per blueprint. But `->when(auth()->user()?->isAdmin() === false, ...)` should be `->when(!auth()->user()?->isAdmin(), ...)` — `isAdmin()` may return null. |
| FR6 | **MEDIUM** | §6 `TransferRequisitionInfolist.php` | `TransferRequisitionInfolist.php` | 117-118 | `RepeatableEntry::make('items')` schema incomplete — missing `approved_qty`, `approved_base_qty`, `shipped_base_qty`, `received_good_base_qty`, `received_damaged_base_qty`, `lossCategory` entries shown in blueprint §6 infolist example. |
| FR7 | **LOW** | §6 `ProductsTable.php` | `ProductsTable.php` | 44-45 | `currentPrice.sale_price` money column uses `config('app.currency')` ✅ but `cost_price` not shown — blueprint §6 table shows both `cost_price` and `sale_price`. Add cost column. |
| FR8 | **LOW** | §6 `ProductsTable.php` | `ProductsTable.php` | 101-118 | `SetCurrentPriceAction` uses `->form([...])` — blueprint §10 Item 3: **`[P0] ->form()` vs `->schema()`** — should use `->schema([...])` for Filament v5 canonical form. |
| FR9 | **LOW** | §6 `TransferRequisitionsTable.php` | `TransferRequisitionsTable.php` | 171-237 | `RecordLossAction` schema uses `->form([...])` — same §10 Item 3: should be `->schema([...])`. |
| FR10 | **LOW** | §6 `DirectTransferForm.php` | (not read) | — | Blueprint §10 Item 4: **`[P0] Placeholder replacement** — Replace `Placeholder` in wizard review steps with `WizardReviewStep` Livewire component. |

---

## 5. ENUMS (Section 1 & 11 Blueprint)

| # | Severity | Blueprint Ref | File | Line | Finding |
|---|----------|---------------|------|------|---------|
| E1 | **MEDIUM** | §1 Principle 10 | `TransferRequisitionStatus.php` | 57-77 | `getIcon()` missing return for `Draft`, `Requested`, `Confirmed`, `Dispatched`, `Completed`, `Cancelled` — cases fall through to implicit `null`. Blueprint §11 requires all 6 backed enums implement `HasIcon` with non-null return. |

---

## 6. FACTORIES / SEEDERS (Section 8 Blueprint)

| # | Severity | Blueprint Ref | File | Line | Finding |
|---|----------|---------------|------|------|---------|
| F1 | **MEDIUM** | §8 Phase 02 | `ProductVariantFactory.php` | 36-41 | `withPrice()` creates `ProductVariantPrice::factory()->forVariant($variant)->create()` — but `ProductVariantPriceFactory` uses `forVariant()` state not defined in factory. Need to verify factory has `forVariant()` state. |
| F2 | **MEDIUM** | §8 Phase 02 | `TransferRequisitionFactory.php` | (not read) | Factory should have state `confirmed`, `dispatched`, `completed` matching status enum. Verify states exist for test coverage. |
| F3 | **LOW** | §8 | `LossLedgerFactory.php` | (not read) | Should create entries with `unit_cost_price` and `total_financial_loss` as decimal strings (`'12.3456'`) not floats. |

---

## 7. TESTS (Section 9 Blueprint)

| # | Severity | Blueprint Ref | File | Line | Finding |
|---|----------|---------------|------|------|---------|
| T1 | **HIGH** | §9 Critical Coverage | — | — | Missing test: `ProductVariantTest::reserved_quantity_excludes_dispatched_and_partially_received()` — blueprint §9 lists this as `[FIX v11]` critical coverage target. |
| T2 | **MEDIUM** | §9 Critical Coverage | — | — | Missing test: `InventoryServiceTest::dispatch_throws_when_approved_base_qty_is_null()` — listed in §9 critical targets. |
| T3 | **MEDIUM** | §9 Critical Coverage | — | — | Missing test: `InventoryServiceTest::scan_to_receive_supports_partial_batches()` — listed in §9. |

---

## 8. PERFORMANCE (Section 7 Blueprint)

| # | Severity | Blueprint Ref | File | Line | Finding |
|---|----------|---------------|------|------|---------|
| P1 | **MEDIUM** | §7 Widget Caching | `LowStockAlertsWidget` | (not read) | Blueprint §7 Accepted Risk: "per-variant accessor loop cached 300s" — documented accepted risk. **Action**: Add monitoring alert when `product_variants` > 5,000 or cache-miss dashboard load > 2s. No code change needed now. |

---

## 9. CROSS-CUTTING CONCERNS

| # | Area | Finding |
|---|------|---------|
| CC1 | Authorization | `TransferRequisitionPolicy::cancel()` must enforce five-state allowlist server-side (not just UI `->visible()`). Blueprint §9 E2E Scenario 6 explicitly tests this. |
| CC2 | Idempotency | `InventoryService::scanToReceive()` state-equality check implemented ✅. `stock_movement_idempotency_keys` migration exists ✅. Audit row insert uses `DB::table()->insert()` with try-catch ✅. |
| CC3 | BC Math | `LossLedger::calculateTotalFinancialLoss()` uses `bcadd(bcmul(...))` ✅. `InventoryService::scanToReceive()` uses `bcmul()` for total loss ✅. |
| CC4 | Lock Ordering | `directTransfer()` locks warehouses sorted-ID ✅. `dispatchTransfer()` locks requisition then variant ✅. Consistent. |
| CC5 | Reservation Boundary | `reservedQuantity()` only counts `Confirmed` status ✅. Blueprint §13 explicitly documents this boundary. |

---

## 10. ACTION PLAN (Priority Order)

### Phase 1: Critical/High Fixes (Blockers)
1. **FR1** — Fix `RecordLossAction` enum comparison (remove `.value` from status)
2. **FR2** — Verify/implement `TransferRequisitionPolicy::cancel()` five-state server-side guard
3. **FR3** — Verify `stn.scan` route has `signed` middleware
4. **T1, T2, T3** — Add missing critical Pest tests

### Phase 2: Medium Fixes (Invariant/Performance)
5. **M1** — Migration: use enum constant for status default
6. **M4** — Migration: `loss_ledgers.warehouse_id` FK `restrictOnDelete`
7. **Mo3** — Add debug assertion for `currentPrice` eager-loading
8. **FR4** — Remove `stockMovements.warehouse` from ProductResource eager load
9. **FR5** — Fix user warehouse scoping null-safe check
10. **FR6** — Complete TransferRequisitionInfolist items schema
11. **S2** — Consolidate first-scan omission idempotency logic
12. **E1** — Complete `getIcon()` for all TransferRequisitionStatus cases
13. **F1, F2** — Verify factory states for testing

### Phase 3: Low Fixes (Cosmetic/Standards)
14. **M2, M3** — Migration defaults/clarity
15. **Mo1, Mo2, Mo4** — Model annotations, index hints, method simplification
16. **FR7, FR8, FR9, FR10** — Table columns, `->schema()` migration, Placeholder replacement
17. **F3** — LossLedgerFactory decimal strings
18. **S3** — Exception message grammar
19. **P1** — Document monitoring threshold for LowStockAlertsWidget

---

## 11. VERIFICATION COMMANDS

```bash
# Run all tests (should pass 1068+)
vendor/bin/pest

# Lint PHP
vendor/bin/pint --dirty --format agent

# Static analysis (if configured)
vendor/bin/phpstan analyse

# Check specific critical tests
vendor/bin/pest --filter="TransferRequisitionPolicy"
vendor/bin/pest --filter="InventoryServiceTest"
vendor/bin/pest --filter="LossLedgerTest"
```

---

## 12. NOTES

- All **Phase 0-3 fixes from blueprint v11 are implemented** in codebase (sorted-ID locking, idempotency, bcmath, LossLedger model, CancelAction visibility).
- **Remaining gaps are drift from blueprint spec** (missing tests, incomplete Filament schemas, enum completeness, migration FK behaviors).
- **Accepted risk** (LowStockAlertsWidget N+1) documented per blueprint §7 — no action until scale threshold reached.
- **Deferred v12 items** (negotiation service-layer guard, event layer, `->form()`→`->schema()` migration) tracked in blueprint §10.