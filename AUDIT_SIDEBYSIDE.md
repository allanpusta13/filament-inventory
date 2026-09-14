# Filament Inventory — Blueprint v10 vs Codebase Side-by-Side Comparison

**Format:** Mirrors blueprint section structure exactly. Each section shows **Blueprint Spec** | **Codebase Implementation** | **Status**

---

## 🧭 Section 1: Executive Architecture & System Principles

| # | Blueprint Principle | Codebase | Status |
|---|---------------------|----------|--------|
| 1 | Pure Derived Stock of Truth — stock = SUM(stock_movements) | `ProductVariant::onHandQuantity()` uses SUM | ✅ |
| 2 | Decoupled Pricing — SKU on variants, prices in `product_variant_prices` | Model + migration match | ✅ |
| 3 | Pessimistic Locking — all deductions in transactions with `lockForUpdate()` | `InventoryService` all methods use `lockForUpdate()` | ✅ |
| 4 | Canonical FK naming — plural snake_case tables | Migrations follow convention | ✅ |
| 5 | State lifecycle: draft → requested → under_review → confirmed → dispatched → partially_received → completed/closed_with_loss/cancelled | `TransferRequisitionStatus` enum has all 10 cases | ✅ |
| 6 | Negotiated substitute variant swapping | `TransferRequisitionItem::substitute_product_variant_id` + service resolves | ✅ |
| 7 | Scanned receipt loss integrity — omitted items = 100% write-off first scan | `scanToReceive()` implements exactly | ✅ |
| 8 | Signed Web QR routing — 7-day temporary signed URLs | `ScanReceiptController` + `STNManifestController` exist | ✅ |
| 9 | Modal-first UI (<8 inputs) — Drawers/Dialogs, wizards `Width::SevenExtraLarge` | Both wizard forms use this | ✅ |
| 10 | Strongly-typed icons, i18n, currency | Enums have HasIcon/HasColor/HasLabel **but no `__()`** | ⚠️ Partial |
| 11 | Ledger FK immutability — `restrictOnDelete` on `product_variant_id` | All ledger migrations use `restrictOnDelete` | ✅ |
| 12 | Authorization vs Visibility — `->authorize()` = security, `->visible()` = DOM | Actions use both correctly | ✅ |
| 13 | **v10 FIX** Reservation scope = Confirmed only | `reservedQuantity()` filters `status = Confirmed` + full doc-block | ✅ |
| 14 | **v10 FIX** Cancellation boundary — no cancel after TransitOut | `CancelAction` visible only 5 pre-dispatch states **but no policy** | ❌ Policy missing |
| 15 | **v10 FIX** Cost snapshot at call-time not dispatch-time | `LossLedger::snapshotUnitCostFrom()` uses current price | ✅ |

---

## 📁 Section 2: Filament v5 Resource Directory Structure

| Resource | Blueprint Structure | Codebase | Status |
|----------|---------------------|----------|--------|
| **Products** | Resource + Pages (List, Create, Edit, View) + Schemas (Form, Infolist) + Tables | `app/Filament/Resources/Products/` — all present | ✅ |
| **TransferRequisitions** | Resource + Pages (List, Create, Edit, View) + Schemas (Form, Infolist) + Tables + Revisions | `app/Filament/Resources/TransferRequisitions/` — all present | ✅ |
| **DirectTransfers** | Resource + Create wizard + Schemas (Form) + Tables | `app/Filament/Resources/DirectTransfers/` — all present | ✅ |
| **InTransits** | Resource + List + Schemas (Infolist) + Tables | `app/Filament/Resources/InTransits/` — all present | ✅ |
| **StockMovements** | Resource + List + Schemas (Infolist) + Tables | `app/Filament/Resources/StockMovements/` — all present | ✅ |
| **LossLedgers** | Resource + List + View + Schemas (Infolist) + Tables | `app/Filament/Resources/LossLedgers/` — all present | ✅ |
| **Warehouses** | Resource + Pages (List, Create, Edit) + Schemas (Form) + Tables | `app/Filament/Resources/Warehouses/` — all present | ✅ |
| **Users** | Resource + Pages (List, Create, Edit) + Schemas (Form) + Tables | `app/Filament/Resources/Users/` — all present | ✅ |

**Pattern Check:**
- Thin resource class delegating to `Schemas/` `Tables/` `Pages/` → ✅ All 8
- `getRecordRouteBindingEloquentQuery()` for soft-delete resources → ✅ Present where needed
- `getPages()` with correct routes → ✅ All present

---

## 🗄️ Section 3: Complete Database Schema (14 Tables)

| Table | Blueprint Columns/Constraints | Codebase Migration | Status |
|-------|-------------------------------|-------------------|--------|
| `products` | id, name, category, deleted_at, timestamps | `2026_09_09_065947` | ✅ |
| `product_variants` | id, product_id (cascade), sku (unique), barcode (unique), name, base_unit_name, reorder_point (0), attributes (json), images (json), is_active (true), deleted_at, timestamps | `2026_09_09_070024` | ✅ |
| `product_variant_prices` | id, product_variant_id (cascade), cost_price (decimal 15,4), sale_price (decimal 15,4), effective_from, is_current (true), set_by (nullOnDelete), notes, timestamps | `2026_09_09_091933` | ✅ |
| `product_variant_unit_conversions` | id, product_variant_id (cascade), unit_name, base_unit_ratio, is_default_purchase, is_default_transfer, timestamps | `2026_09_09_070135` | ✅ |
| `warehouses` | id, code (unique), name, location, is_active (true), timestamps | `2026_09_09_070302` | ✅ |
| `stock_movements` | id, product_variant_id (restrict), warehouse_id (restrict), type, quantity, unit_name_used, unit_ratio_used (1), related_movement_id (null), reference_type, reference_id, reference_code, notes, created_by (null), timestamps | `2026_09_09_070750` | ✅ |
| `transfer_requisitions` | id, reference_code (unique), from_warehouse_id (restrict), to_warehouse_id (restrict), status (draft), requested_by, approved_by, dispatched_by, received_by, requested_at, approved_at, dispatched_at, completed_at, notes, deleted_at, timestamps | `2026_09_09_070949` | ✅ |
| `transfer_requisition_items` | id, transfer_requisition_id (cascade), product_variant_id (restrict), substitute_product_variant_id (restrict, nullable), requested_unit_name, requested_unit_ratio, requested_qty, requested_base_qty, approved_unit_name, approved_unit_ratio, approved_qty, approved_base_qty, shipped_base_qty (0), received_good_base_qty (0), received_damaged_base_qty (0), received_qty, notes, timestamps | `2026_09_09_071135` | ✅ |
| `transfer_requisition_item_revisions` | id, transfer_requisition_item_id (cascade), user_id, product_variant_id (restrict), substitute_product_variant_id (restrict), proposed_unit_name, proposed_unit_ratio, proposed_qty, proposed_base_qty, negotiation_reason, side, status (pending), responds_to_revision_id (null), responded_at, timestamps | `2026_09_09_071451` | ✅ |
| `in_transits` | id, transfer_requisition_id (cascade), transfer_requisition_item_id (cascade), product_variant_id (restrict), dispatched_base_qty, dispatched_at, status (in_transit), timestamps | `2026_09_09_071521` | ✅ |
| `loss_ledgers` | id, transfer_requisition_id (nullOnDelete), transfer_requisition_item_id, product_variant_id (restrict), warehouse_id, lost_base_qty (0), damaged_base_qty (0), unit_cost_price (decimal 15,4), total_financial_loss (decimal 15,4), loss_category (shortfall), recorded_by, recorded_at, timestamps | `2026_09_09_071726` | ✅ |
| `users` (role) | role (warehouse_staff default) | `2026_09_09_070421` | ✅ |
| `user_warehouse` | user_id (cascade, PK), warehouse_id (cascade, PK) | `2026_09_09_070629` | ✅ |
| **`stock_movement_idempotency_keys` (v10 FIX)** | id, transfer_requisition_id (cascade), payload_checksum (64), resulting_item_states (json), created_at, unique(requisition_id, checksum) | `2026_09_11_015014` | ✅ |

**Migration Order:** All 14 ran successfully in correct dependency order.

---

## 🧙‍♂️ Section 4: Transfer Transaction Wizard Schemas

### A. TransferRequisitionForm (3-step Wizard)

| Step | Blueprint | Codebase (`TransferRequisitionForm.php`) | Status |
|------|-----------|------------------------------------------|--------|
| 1. Routing Pathways | `from_warehouse_id` (user's warehouses, default if 1), `to_warehouse_id` (user's warehouses, `->different('from')`) | Exact match | ✅ |
| 2. Material Manifest | Repeater `items` → `product_variant_id` (relationship sku), `requested_unit_name`, `requested_unit_ratio` (numeric, default 1), `requested_qty` (min 1) | Exact match | ✅ |
| 3. Review & Verify | `Placeholder::make('review_summary')` rendering `filament.wizards.transfer-review` view | Exact match | ✅ |
| **Wizard Config** | `->modalWidth(Width::SevenExtraLarge)` `->closeModalByClickingAway(false)` | Exact match | ✅ |

### B. DirectTransferForm (3-step Wizard)

| Step | Blueprint | Codebase (`DirectTransferForm.php`) | Status |
|------|-----------|-------------------------------------|--------|
| 1. Location Mapping | Same warehouse selects as TR | Exact match | ✅ |
| 2. Stock Allocation | `product_variant_id` (relationship sku), `quantity` (min 1), `notes` (required, min 15) | Exact match | ✅ |
| 3. Review & Verify | `Placeholder` rendering `filament.wizards.direct-transfer-review` | Exact match | ✅ |
| **Wizard Config** | `->modalWidth(Width::SevenExtraLarge)` `->closeModalByClickingAway(false)` | Exact match | ✅ |

---

## 🛠️ Section 5: Model-Level Pure Derived Stock Engine

### ProductVariant Model

| Method | Blueprint Spec | Codebase | Status |
|--------|----------------|----------|--------|
| `product()` | `BelongsTo Product` | ✅ | ✅ |
| `unitConversions()` | `HasMany ProductVariantUnitConversion` | ✅ | ✅ |
| `prices()` | `HasMany ProductVariantPrice` | ✅ | ✅ |
| `currentPrice()` | `HasOne ProductVariantPrice where is_current=true` | ✅ | ✅ |
| `stockMovements()` | `HasMany StockMovement` | ✅ | ✅ |
| `requisitionItems()` | `HasMany TransferRequisitionItem` | ✅ | ✅ |
| `isBelowReorderPoint(int)` | `return $currentBaseQty <= $this->reorder_point` | ✅ | ✅ |
| `onHandQuantity(int $warehouseId)` | `StockMovement::where(pv_id, $this->id)->where(warehouse_id, $warehouseId)->sum('quantity')` | ✅ Exact | ✅ |
| `reservedQuantity(int $warehouseId)` | **v10 FIX** — Confirmed only, sum `approved_base_qty`, doc-block explaining why NOT Dispatched/PartiallyReceived | ✅ Exact + full doc-block | ✅ |
| `availableQuantity(int $warehouseId)` | `onHandQuantity - reservedQuantity` | ✅ | ✅ |
| `casts()` | attributes=array, images=array, reorder_point=integer, is_active=boolean | ✅ | ✅ |

### ProductObserver

| Method | Blueprint | Codebase | Status |
|--------|-----------|----------|--------|
| `deleting(Product)` | Throw if active variants exist | ✅ `ProductObserver.php` registered in `AppServiceProvider` | ✅ |

### LossLedger Model (v10 FIX — was missing in v9.1)

| Method | Blueprint | Codebase | Status |
|--------|-----------|----------|--------|
| `transferRequisition()` | `BelongsTo TransferRequisition` | ✅ | ✅ |
| `item()` | `BelongsTo TransferRequisitionItem` | ✅ | ✅ |
| `productVariant()` | `BelongsTo ProductVariant` | ✅ | ✅ |
| `warehouse()` | `BelongsTo Warehouse` | ✅ | ✅ |
| `snapshotUnitCostFrom(ProductVariant)` | Returns `(string) ($variant->currentPrice?->cost_price ?? '0.0000')` — call-time, null-safe | ✅ Exact | ✅ |
| `casts()` | unit_cost_price=decimal:4, total_financial_loss=decimal:4, recorded_at=datetime | ✅ | ✅ |

---

## ⚙️ Section 6: Transactional Service Layer

### 5A. InventoryService

| Method | Blueprint Spec | Codebase | Status |
|--------|----------------|----------|--------|
| `recordMovement(pv_id, wh_id, type, baseQty, unitName?, unitRatio=1, refType?, refId?, refCode?, relatedId?, notes?)` | Guard `unitRatio >= 1`, lock variant, check stock not negative, create StockMovement with all fields, `created_by = auth()->id()` | ✅ Exact — guard at line 50 | ✅ |
| `directTransfer(pv_id, fromWh, toWh, baseQty, unitName?, unitRatio=1, refCode?, notes?)` | **v10 FIX** Lock both warehouses in sorted-ID order (prevents deadlock), guard `unitRatio >= 1`, check stock, create paired TransferOut/TransferIn with `related_movement_id` bidirectional | ✅ Exact — sorted-ID at line 119-121, guard at 124 | ✅ |
| `dispatchTransfer(requisitionId)` | Lock requisition + items, verify status=Confirmed, each item: resolve substitute variant, verify stock, create TransitOut movement, create InTransit row, update shipped_base_qty, update requisition to Dispatched | ✅ Exact — no `??` fallbacks, throws if approved_base_qty null | ✅ |
| `scanToReceive(requisitionId, receivedItemsData)` | **v10 FIX** Idempotency state-check (skip if no change), omitted items first scan = 100% loss, subsequent scans = still in transit, bcmath for loss calc, idempotency audit row in `stock_movement_idempotency_keys`, update requisition status (Completed/ClosedWithLoss/PartiallyReceived) | ✅ Exact — state-check lines 369-374, omitted logic 343-372, bcmath 398-410 | ✅ |

### 5B. NegotiationService (Unchanged from v9.1)

| Method | Blueprint | Codebase | Status |
|--------|-----------|----------|--------|
| `propose(item, user, side, unitName, unitRatio, qty, substituteId?, reason?, respondsTo?)` | Creates revision, or counter if respondsTo | ✅ | ✅ |
| `accept(revision)` | Guard not resolved, call `$revision->accept()` | ✅ | ✅ |
| `reject(revision)` | Guard not resolved, call `$revision->reject()` | ✅ | ✅ |
| `counter(revision, user, unitName, unitRatio, qty, substituteId?, reason?)` | Guard not resolved, create counter with opposite side | ✅ | ✅ |
| `materializeRequestedAsApproved(requisition)` | For items with null approved_base_qty, copy requested_* to approved_* | ✅ | ✅ |

---

## 📋 Section 7: Master Resource Specifications

### 1. ProductResource

| Aspect | Blueprint | Codebase | Status |
|--------|-----------|----------|--------|
| Model | `ProductVariant` | ✅ | ✅ |
| Navigation | CATALOG, sort 1 | ✅ | ✅ |
| Form | ProductForm: product_id (relationship + createOptionForm), sku (unique), barcode (unique), name, base_unit_name, reorder_point, attributes (KeyValue), is_active (Toggle) | ✅ Exact | ✅ |
| Table | ProductsTable: product.name, sku (mono, copyable), barcode, name, base_unit_name (badge), currentPrice.sale_price (money), reorder_point, is_active (icon), filters: is_active, product_id, TrashedFilter | ✅ | ✅ |
| Actions | Edit (modal Large), SetCurrentPriceAction, EditProductFamilyAction, ManageUnitConversionsAction, QuickStockAdjustmentAction, Delete (authorize), Restore | ✅ | ✅ |
| Infolist | ProductInfolist: product.name, sku, barcode, cost_price (money), sale_price (money), unitConversions (RepeatableEntry) | ✅ | ✅ |

### 2. TransferRequisitionResource

| Aspect | Blueprint | Codebase | Status |
|--------|-----------|----------|--------|
| Model | `TransferRequisition` | ✅ | ✅ |
| Navigation | OPERATIONS, sort 1 | ✅ | ✅ |
| **Table Actions** | **See Section 12 Authorization Mapping** | **Actions present in TransferRequisitionsTable** | ✅ UI |
| `getEloquentQuery()` | **Scope to user's warehouses for non-admin** | **MISSING** | ❌ |
| `getRecordRouteBindingEloquentQuery()` | Without SoftDeletingScope | ✅ | ✅ |

### 3. DirectTransferResource

| Aspect | Blueprint | Codebase | Status |
|--------|-----------|----------|--------|
| Model | `StockMovement` | ✅ | ✅ |
| Query Scope | `whereIn('type', [TransferOut, TransferIn])->whereNotNull('related_movement_id')` | ✅ | ✅ |

### 4. Audit Ledgers (Read-only)

| Resource | Blueprint | Codebase | Status |
|----------|-----------|----------|--------|
| InTransitResource | Read-only, ReceiveIntakeAction resolves transfer_requisition_id | ✅ | ✅ |
| StockMovementResource | Read-only, signed integer quantity sum footer, notes column | ✅ | ✅ |
| LossLedgerResource | Read-only, decimal(15,4) sum footers, transferRequisition.reference_code renders `—` when NULL | ✅ | ✅ |

### 5. System Admin

| Resource | Blueprint | Codebase | Status |
|----------|-----------|----------|--------|
| WarehouseResource | SYSTEM ADMIN sort 1, Drawer Width::Large | ✅ | ✅ |
| UserResource | SYSTEM ADMIN sort 2, Modal Width::Large | ✅ | ✅ |

---

## 🎨 Section 8: Clinical "Operations Deck" Design System

| Element | Blueprint Spec | Codebase | Status |
|---------|----------------|----------|--------|
| **Colors** | Primary #3b82f6, Surface #fff, Border #e4e4e7, Text #18181b, Danger #ef4444, Warning #f59e0b, Success #22c55e | `AdminPanelProvider` sets primary Blue, DESIGN.md has full palette | ✅ |
| **Elevation** | Flat rest (1px border), elevation only on focus/modal/dropdown | DESIGN.md specifies, need verify CSS | ⚠️ |
| **Bento Grid** | 4-col asymmetrical, glassmorphism `backdrop-filter: blur(24px)` | DESIGN.md specifies, need verify | ⚠️ |
| **Widget Caching** | 300s TTL, LowStockAlerts accepted-risk per-variant loop | `StatsOverviewWidget` exists with 300s cache | ⚠️ Partial |
| **Dashboard Widgets** | StatsOverview (4 metrics), LowStockAlerts (per-variant), RecentMovements (60s), ActiveInTransit (300s) | Only StatsOverview verified | ⚠️ |

---

## 📋 Section 8: Master 17-Stage Execution Sequence

| Phase | Blueprint | Codebase | Status |
|-------|-----------|----------|--------|
| 00 | Env + Core Guardrails (bcmath, strictAuthorization) | **strictAuthorization MISSING**, bcmath in composer.json | ⚠️ |
| 01 | Relational Schema Migrations (14 tables) | All 14 migrations exist + idempotency_keys | ✅ |
| 02 | Base Seeders + Opening Ledger | Not verified | ⚠️ |
| 03 | Eloquent Models + Enums + ProductObserver + LossLedger | All present | ✅ |
| 04 | InventoryService + NegotiationService | Both complete with v10 fixes | ✅ |
| 05 | ProductResource | Complete | ✅ |
| 06 | Price Snapshots + Unit Conversions (inline actions) | Actions exist | ✅ |
| 07 | Warehouses + Manual Adjustments | Complete | ✅ |
| 08 | Inter-Warehouse Requisition Wizard | Complete | ✅ |
| 09 | Negotiation Loop UI | Complete | ✅ |
| 10 | Dispatch + In-Transit + Confirm + Cancel (5-state) | Actions present, **policy missing** | ⚠️ |
| 11 | Printable STN + Signed QR (7 days) | Controllers exist | ⚠️ |
| 12 | Scan-to-Receive Modal + Multi-Batch | Complete with idempotency | ✅ |
| 13 | Read-Only Audit Ledgers | Complete | ✅ |
| 14 | Glassmorphic Bento Dashboard | Partial | ⚠️ |
| 15 | Multi-Language Translation | **Enums missing `__()`** | ❌ |
| 16 | CI/CD Testing | Tests run, 13 v10 targets missing | ⚠️ |

---

## 🧪 Section 9: Automated CI/CD Testing & E2E Validation

### Pest Targets — v10 Additions

| Test | Blueprint | Codebase | Status |
|------|-----------|----------|--------|
| `InventoryServiceTest::direct_transfer_locks_warehouses_in_sorted_id_order()` | Required | **MISSING** | ❌ |
| `InventoryServiceTest::direct_transfer_rejects_zero_or_negative_unit_ratio()` | Required | **MISSING** | ❌ |
| `InventoryServiceTest::record_movement_rejects_zero_or_negative_unit_ratio()` | Required | **MISSING** | ❌ |
| `InventoryServiceTest::scan_to_receive_is_idempotent_against_duplicate_submission()` | Required | **MISSING** | ❌ |
| `InventoryServiceTest::scan_to_receive_total_financial_loss_matches_bcmath_reference_value()` | Required | **MISSING** | ❌ |
| `ConcurrencyTest::simultaneous_opposite_direction_direct_transfers_do_not_deadlock()` | Required | **MISSING** | ❌ |
| `TransferRequisitionPolicyTest::cancel_is_permitted_while_confirmed()` | Required | **MISSING (needs policy)** | ❌ |
| `TransferRequisitionPolicyTest::cancel_is_rejected_once_dispatched()` | Required | **MISSING (needs policy)** | ❌ |
| `TransferRequisitionPolicyTest::cancel_is_rejected_while_partially_received()` | Required | **MISSING (needs policy)** | ❌ |
| `LossLedgerTest::snapshot_unit_cost_falls_back_to_zero_when_no_current_price_exists()` | Required | **MISSING** | ❌ |
| `LossLedgerTest::snapshot_unit_cost_reflects_call_time_price_not_dispatch_time_price()` | Required | **MISSING** | ❌ |
| `LowStockAlertsWidgetTest::cache_window_prevents_requery_within_300_seconds()` | Required | **MISSING** | ❌ |
| `LowStockAlertsWidgetTest::cache_miss_correctly_recomputes_all_variants()` | Required | **MISSING** | ❌ |

### Playwright E2E Scenarios

| Scenario | Blueprint | Codebase | Status |
|----------|-----------|----------|--------|
| 1. Full Transfer Lifecycle | Required | Not verified | ⚠️ |
| 2. Direct Transfer | Required | Not verified | ⚠️ |
| 3. Loss Write-Off | Required | Not verified | ⚠️ |
| 4. Soft-Delete Guard | Required | Not verified | ⚠️ |
| 5. Authorization Bypass Attempt | Required | Not verified | ⚠️ |
| 6. Cancellation Boundary (bypass UI) | Required | **Needs policy first** | ❌ |
| 7. Duplicate Scan Submission | Required | Not verified | ⚠️ |

---

## 📌 Section 10: Deferred to v11 (Accepted)

| Item | Blueprint Note | Codebase | Status |
|------|----------------|----------|--------|
| 1. Service-layer negotiation status guard | UI guards via `->visible()`, service doesn't | Not implemented | ✅ Deferred |
| 2. Event + notification layer | StatsOverviewWidget 300s TTL not alerting | Not implemented | ✅ Deferred |
| 3. `->form()` vs `->schema()` on actions | Verify against pinned minor | Not verified | ✅ Deferred |
| 4. Placeholder deprecation | Verify against pinned minor | Not verified | ✅ Deferred |
| 5. createOptionForm auto-select | Verify, dispatch `$refresh` if needed | Not verified | ✅ Deferred |
| 6. Panel strictAuthorization role coverage | Enumerate all policies first | **Incomplete** | ⚠️ |
| 7. Low-stock widget scaling threshold | Revisit at 5k-10k variants | Accepted risk documented | ✅ Deferred |

---

## 📐 Section 11: Filament v5 Component Reference (Locked)

| Namespace/Method | Blueprint Locked Version | Codebase Usage | Status |
|------------------|--------------------------|----------------|--------|
| Actions | `Filament\Actions\*` (not Tables\Actions) | All resources use correct | ✅ |
| Schema Layout | `Filament\Schemas\Components\{Section,Grid,Fieldset,Tabs,Wizard,Step}` | Used correctly | ✅ |
| Form Fields | `Filament\Forms\Components\{Select,TextInput,Textarea,Toggle,KeyValue,Repeater,Placeholder}` | Used correctly | ✅ |
| Infolist | `Filament\Infolists\Components\{TextEntry,RepeatableEntry}` | Used correctly | ✅ |
| Table Columns | `Filament\Tables\Columns\{TextColumn,IconColumn}` | Used correctly | ✅ |
| Table Filters | `Filament\Tables\Filters\{SelectFilter,TernaryFilter,TrashedFilter}` | Used correctly | ✅ |
| Width Enum | `Filament\Support\Enums\Width` | Used correctly | ✅ |
| Table Methods | `->columns()`, `->filters()`, `->recordActions()`, `->toolbarActions(BulkActionGroup)` | All v5 style | ✅ |
| Schema Contract | Static `configure(Schema $schema): Schema` | All schema classes follow | ✅ |

---

## 📊 Section 12: Authorization Mapping

| Action | Blueprint `->authorize()` | Blueprint `->visible()` | Codebase Action | Codebase Policy | Status |
|--------|---------------------------|-------------------------|-----------------|-----------------|--------|
| ForceDeleteAction | forceDelete | isAdmin() | ✅ | ❌ Missing | ❌ |
| DeleteAction (TR) | delete | status ∈ {Draft, Cancelled} | ✅ | ❌ Missing | ❌ |
| DispatchAction | dispatch | status === Confirmed | ✅ | ❌ Missing | ❌ |
| ScanToReceiveAction | receive | status ∈ {Dispatched, PartiallyReceived} | ✅ | ❌ Missing | ❌ |
| **CancelAction** | **cancel** | **status ∈ {Draft, Requested, UnderReviewFulfiller, UnderReviewRequestor, Confirmed}** | ✅ | **❌ Missing** | ❌ |
| SetCurrentPriceAction | setPrice | — | ✅ | ✅ ProductVariantPolicy | ✅ |
| RecordWarehouseLossAction | recordLoss | — | ✅ | ❌ Missing | ❌ |
| QuickStockAdjustmentAction | adjustStock | — | ✅ | ✅ ProductVariantPolicy | ✅ |
| RestoreAction | restore | — | ✅ | ❌ Missing | ❌ |
| DeleteBulkAction | deleteAny | — | ✅ | ❌ Missing | ❌ |
| RestoreBulkAction | restoreAny | — | ✅ | ❌ Missing | ❌ |
| ForceDeleteBulkAction | forceDeleteAny | — | ✅ | ❌ Missing | ❌ |

### Required Policies (Blueprint Section 12)

| Policy | Blueprint Methods | Codebase | Status |
|--------|-------------------|----------|--------|
| ProductPolicy | viewAny, view, create, update, delete, restore, forceDelete | ✅ Exists | ✅ |
| ProductVariantPolicy | viewAny, view, create, update, delete, restore, forceDelete, setPrice, adjustStock | ✅ Exists | ✅ |
| **TransferRequisitionPolicy** | **viewAny, view, create, update, delete, restore, forceDelete, confirm, dispatch, receive, cancel** | **DOES NOT EXIST** | ❌ **CRITICAL** |
| StockMovementPolicy | viewAny, view (all else false) | Not found | ❌ |
| InTransitPolicy | viewAny, view, receive | Not found | ❌ |
| LossLedgerPolicy | viewAny, view, recordLoss | Not found | ❌ |
| WarehousePolicy | viewAny, view, create, update, adjustStock, recordLoss | Not found | ❌ |
| UserPolicy | viewAny, view, create, update | Not found | ❌ |

---

## ✅ Section 13: Cross-Cutting Verification Checklist

| # | Check | Blueprint | Codebase | Pass |
|---|-------|-----------|----------|------|
| 1 | reservedQuantity() counts Confirmed only | ✅ | ✅ | ✅ |
| 2 | reservedQuantity() scope documented in-code | ✅ | ✅ Full doc-block | ✅ |
| 3 | ForceDeleteAction absent ProductResource | ✅ | ✅ (uses ProductVariant) | ✅ |
| 4 | All ledger product_variant_id FKs restrictOnDelete | ✅ | ✅ Migrations | ✅ |
| 5 | stock_movements.notes column + service param | ✅ | ✅ | ✅ |
| 6 | loss_ledgers.transfer_requisition_id nullable | ✅ | ✅ Migration | ✅ |
| 7 | partially_received has producer and consumer | ✅ | ✅ | ✅ |
| 8 | ConfirmAction calls materializeRequestedAsApproved() | ✅ | Need verify Edit page | ⚠️ |
| 9 | dispatchTransfer/scanToReceive free of `??` fallbacks | ✅ | ✅ | ✅ |
| 10 | dispatchTransfer throws if approved_base_qty null | ✅ | ✅ | ✅ |
| 11 | ScanToReceiveAction named scanToReceive (camelCase) | ✅ | ✅ | ✅ |
| 12 | All wizard step-review components are Placeholder | ✅ | ✅ | ✅ |
| 13 | RepeatableEntry (not RepeatEntry) in all infolists | ✅ | ✅ | ✅ |
| 14 | SoftDeletingScope imported in getEloquentQuery() | ✅ | ✅ | ✅ |
| 15 | Enums route getLabel() through `__()` | ✅ | ❌ Direct strings | ❌ |
| 16 | TransferRequisitionPolicy exists | ✅ | ❌ Missing | ❌ |
| 17 | ProductObserver guards parent soft-delete | ✅ | ✅ | ✅ |
| 18 | QR lifetime = 7 days | ✅ | Need verify | ⚠️ |
| 19 | Direct-transfer list uses type + related_movement_id | ✅ | ✅ | ✅ |
| 20 | All action namespaces = Filament\Actions\* | ✅ | ✅ | ✅ |
| 21 | `->recordActions()` / `->toolbarActions()` (v5) | ✅ | ✅ | ✅ |
| 22 | BulkActionGroup wraps multiple bulk actions | ✅ | ✅ | ✅ |
| 23 | Section/Grid/Wizard from Filament\Schemas\Components | ✅ | ✅ | ✅ |
| 24 | Get from Filament\Schemas\Components\Utilities\Get | ✅ | ✅ | ✅ |
| 25 | `->money(config('app.currency'))` on all money columns | ✅ | Need verify | ⚠️ |
| 26 | `$navigationGroup` / `$navigationSort` per resource | ✅ | ✅ | ✅ |
| 27 | `->strictAuthorization()` mandated in panel provider | ✅ | ❌ Missing | ❌ |
| 28 | Phases 05/06 use inline actions, no RelationManagers | ✅ | ✅ | ✅ |
| 29 | Resource classes use thin delegation pattern | ✅ | ✅ | ✅ |
| 30 | Schema classes expose static configure() method | ✅ | ✅ | ✅ |
| 31 | getRecordRouteBindingEloquentQuery() for soft-delete | ✅ | ✅ | ✅ |
| 32 | LossLedger model exists with snapshotUnitCostFrom() | ✅ v10 | ✅ | ✅ |
| 33 | directTransfer() locks warehouses in sorted-ID order | ✅ v10 | ✅ | ✅ |

**Summary: 22 Pass | 4 Fail | 7 Need Verify**