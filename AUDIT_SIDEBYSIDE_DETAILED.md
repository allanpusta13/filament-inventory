# Filament Inventory — Blueprint v10 vs Codebase Side-by-Side with Actual Differences

**Format:** Each section shows Blueprint Spec | Codebase Implementation | **Actual Diff** | Status

---

## 🧭 Section 1: Executive Architecture & System Principles

| # | Blueprint Principle | Codebase | **Actual Difference** | Status |
|---|---------------------|----------|----------------------|--------|
| 1 | Pure Derived Stock of Truth | `ProductVariant::onHandQuantity()` uses SUM | **None** — matches exactly | ✅ |
| 2 | Decoupled Pricing — SKU on variants | Model + migration match | **None** | ✅ |
| 3 | Pessimistic Locking | `InventoryService` all methods use `lockForUpdate()` | **None** | ✅ |
| 4 | Canonical FK naming | Migrations follow convention | **None** | ✅ |
| 5 | State lifecycle 10 states | `TransferRequisitionStatus` enum has all 10 cases | **None** | ✅ |
| 6 | Substitute variant swapping | `substitute_product_variant_id` + service resolves | **None** | ✅ |
| 7 | Scanned receipt loss integrity | `scanToReceive()` implements exactly | **None** | ✅ |
| 8 | Signed Web QR routing 7-day | Controllers exist | **Need verify URL::temporarySignedRoute 7 days** | ⚠️ |
| 9 | Modal-first UI | Both wizard forms use `Width::SevenExtraLarge` | **None** | ✅ |
| 10 | i18n enums | Enums have HasIcon/HasColor/HasLabel **but no `__()`** | **MISSING `__()` wrapper** — see diff below | ⚠️ |
| 11 | Ledger FK restrictOnDelete | All ledger migrations use `restrictOnDelete` | **None** | ✅ |
| 12 | Authorization vs Visibility | Actions use both correctly | **None** | ✅ |
| 13 | v10 FIX Reservation = Confirmed only | `reservedQuantity()` filters Confirmed + doc-block | **None** | ✅ |
| 14 | v10 FIX Cancellation boundary | `CancelAction` visible 5 states **but no policy** | **POLICY CLASS MISSING** | ❌ |
| 15 | v10 FIX Cost snapshot call-time | `LossLedger::snapshotUnitCostFrom()` uses current price | **None** | ✅ |

### Actual Diff: Enum i18n (Principle 10)

**Blueprint Requirement:**
```php
// All backed enums route getLabel() through __()
public function getLabel(): string
{
    return match ($this) {
        self::Draft => __('draft'),
        self::Requested => __('requested'),
        // ...
    };
}
```

**Codebase (TransferRequisitionStatus.php:26-40):**
```php
public function getLabel(): string
{
    return match ($this) {
        self::Draft => 'Draft',
        self::Requested => 'Requested',
        self::UnderReviewFulfiller => 'Under review (fulfiller)',
        self::UnderReviewRequestor => 'Under review (requestor)',
        self::Confirmed => 'Confirmed',
        self::Dispatched => 'Dispatched',
        self::PartiallyReceived => 'Partially received',
        self::Completed => 'Completed',
        self::ClosedWithLoss => 'Closed with loss',
        self::Cancelled => 'Cancelled',
    };
}
```

**Difference:** Direct string returns vs `__('translation.key')` — affects all 6 enums.

---

## 📁 Section 2: Filament v5 Resource Directory Structure

| Resource | Blueprint | Codebase | **Actual Difference** | Status |
|----------|-----------|----------|----------------------|--------|
| Products | Thin delegation pattern | `app/Filament/Resources/Products/` | **None** | ✅ |
| TransferRequisitions | Thin + Revisions | `app/Filament/Resources/TransferRequisitions/` | **Missing `getEloquentQuery()` override** | ❌ |
| DirectTransfers | Thin + Wizard | `app/Filament/Resources/DirectTransfers/` | **None** | ✅ |
| InTransits | Read-only | `app/Filament/Resources/InTransits/` | **None** | ✅ |
| StockMovements | Read-only | `app/Filament/Resources/StockMovements/` | **None** | ✅ |
| LossLedgers | Read-only + View | `app/Filament/Resources/LossLedgers/` | **None** | ✅ |
| Warehouses | Thin + Drawer | `app/Filament/Resources/Warehouses/` | **None** | ✅ |
| Users | Thin + Modal | `app/Filament/Resources/Users/` | **None** | ✅ |

### Actual Diff: TransferRequisitionResource getEloquentQuery()

**Blueprint Spec (Section 13, Item 16):**
```php
// Scope query to user's warehouses for non-admin
public static function getEloquentQuery(): Builder
{
    $user = auth()->user();
    $query = parent::getEloquentQuery();
    
    if (!$user->isAdmin() && !$user->isAuditor()) {
        $warehouseIds = $user->warehouses()->pluck('warehouses.id');
        $query->whereIn('warehouse_id', $warehouseIds);
    }
    
    return $query;
}
```

**Codebase (TransferRequisitionResource.php:48-70):**
```php
// ONLY has getRecordRouteBindingEloquentQuery()
public static function getRecordRouteBindingEloquentQuery(): Builder
{
    return parent::getRecordRouteBindingEloquentQuery()
        ->withoutGlobalScopes([SoftDeletingScope::class]);
}

// getEloquentQuery() IS MISSING
```

---

## 🗄️ Section 3: Database Schema (14 Tables)

All 14 migrations match exactly. **No differences found.**

| Table | Migration File | Status |
|-------|----------------|--------|
| `products` | `2026_09_09_065947` | ✅ |
| `product_variants` | `2026_09_09_070024` | ✅ |
| `product_variant_prices` | `2026_09_09_091933` | ✅ |
| `product_variant_unit_conversions` | `2026_09_09_070135` | ✅ |
| `warehouses` | `2026_09_09_070302` | ✅ |
| `stock_movements` | `2026_09_09_070750` | ✅ |
| `transfer_requisitions` | `2026_09_09_070949` | ✅ |
| `transfer_requisition_items` | `2026_09_09_071135` | ✅ |
| `transfer_requisition_item_revisions` | `2026_09_09_071451` | ✅ |
| `in_transits` | `2026_09_09_071521` | ✅ |
| `loss_ledgers` | `2026_09_09_071726` | ✅ |
| `users` role | `2026_09_09_070421` | ✅ |
| `user_warehouse` | `2026_09_09_070629` | ✅ |
| `stock_movement_idempotency_keys` (v10) | `2026_09_11_015014` | ✅ |

---

## 🧙‍♂️ Section 4: Transfer Transaction Wizard Schemas

### TransferRequisitionForm — **No Differences**

### DirectTransferForm — **No Differences**

---

## 🛠️ Section 5: Model-Level Pure Derived Stock Engine

### ProductVariant — **No Differences** (including full v10 doc-block on `reservedQuantity()`)

### LossLedger (v10 FIX) — **No Differences**

---

## ⚙️ Section 6: Transactional Service Layer

### InventoryService — **No Differences** (all v10 fixes implemented correctly)

| Method | Key v10 Fix | Codebase Line | Verified |
|--------|-------------|---------------|----------|
| `recordMovement()` | `if ($unitRatio < 1)` guard | Line 50 | ✅ |
| `directTransfer()` | Sorted-ID warehouse locking | Lines 119-121 | ✅ |
| `directTransfer()` | `if ($unitRatio < 1)` guard | Line 124 | ✅ |
| `dispatchTransfer()` | No `??` fallbacks, throws if `approved_base_qty` null | Lines 215-220 | ✅ |
| `scanToReceive()` | Idempotency state-check | Lines 369-374 | ✅ |
| `scanToReceive()` | Omitted items first scan = 100% loss | Lines 343-372 | ✅ |
| `scanToReceive()` | bcmath for loss calc | Lines 398-410 | ✅ |
| `scanToReceive()` | Idempotency audit row | Lines 447-475 | ✅ |

---

## 📋 Section 7: Master Resource Specifications

### TransferRequisitionResource Table Actions

All actions present in `TransferRequisitionsTable.php` with correct `->authorize()` and `->visible()` closures. **Only difference: no backing policy class.**

---

## 📊 Section 12: Authorization Mapping — CRITICAL DIFFERENCES

### TransferRequisitionPolicy — **ENTIRELY MISSING**

**Blueprint Spec (11 methods):**
```php
class TransferRequisitionPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, TransferRequisition $r): bool { return true; }
    public function create(User $user): bool { return true; }
    public function update(User $user, TransferRequisition $r): bool { return true; }
    public function delete(User $user, TransferRequisition $r): bool 
    { 
        return in_array($r->status, [TransferRequisitionStatus::Draft, TransferRequisitionStatus::Cancelled]);
    }
    public function restore(User $user, TransferRequisition $r): bool { return true; }
    public function forceDelete(User $user, TransferRequisition $r): bool 
    { 
        return $user->isAdmin(); 
    }
    public function confirm(User $user, TransferRequisition $r): bool
    {
        return in_array($r->status, [
            TransferRequisitionStatus::Requested,
            TransferRequisitionStatus::UnderReviewFulfiller,
            TransferRequisitionStatus::UnderReviewRequestor,
        ]);
    }
    public function dispatch(User $user, TransferRequisition $r): bool
    {
        return $r->status === TransferRequisitionStatus::Confirmed;
    }
    public function receive(User $user, TransferRequisition $r): bool
    {
        return in_array($r->status, [
            TransferRequisitionStatus::Dispatched,
            TransferRequisitionStatus::PartiallyReceived,
        ]);
    }
    // v10 FIX: 5-state pre-dispatch allowlist
    public function cancel(User $user, TransferRequisition $r): bool
    {
        return in_array($r->status, [
            TransferRequisitionStatus::Draft,
            TransferRequisitionStatus::Requested,
            TransferRequisitionStatus::UnderReviewFulfiller,
            TransferRequisitionStatus::UnderReviewRequestor,
            TransferRequisitionStatus::Confirmed,
        ]);
    }
}
```

**Codebase:** `app/Policies/TransferRequisitionPolicy.php` **DOES NOT EXIST**

**Directory listing:**
```
app/Policies/
├── ProductPolicy.php          (900 bytes)
└── ProductVariantPolicy.php   (1040 bytes)
```

---

### Missing Policies (5 additional)

| Policy | Blueprint Methods | Codebase File | Status |
|--------|-------------------|---------------|--------|
| StockMovementPolicy | viewAny, view (all else false) | **MISSING** | ❌ |
| InTransitPolicy | viewAny, view, receive | **MISSING** | ❌ |
| LossLedgerPolicy | viewAny, view, recordLoss | **MISSING** | ❌ |
| WarehousePolicy | viewAny, view, create, update, adjustStock, recordLoss | **MISSING** | ❌ |
| UserPolicy | viewAny, view, create, update | **MISSING** | ❌ |

---

## 🎨 Section 8: Design System — NEED VERIFICATION

| Element | Blueprint Spec | Codebase | **Actual Difference** |
|---------|----------------|----------|----------------------|
| Colors | Full palette in DESIGN.md | AdminPanelProvider sets primary Blue | Need verify CSS variables |
| Elevation | Flat rest, elevation on interaction | DESIGN.md specifies | Need verify |
| Bento Grid | 4-col asymmetrical, glassmorphism | DESIGN.md specifies | Need verify |
| Widget Caching | 300s TTL, LowStock per-variant loop | StatsOverviewWidget exists | Need verify other 3 widgets |
| Dashboard Widgets | 4 widgets specified | Only StatsOverview verified | Need verify |

---

## 📋 Section 8: Master 17-Stage Execution Sequence

| Phase | Blueprint | Codebase | **Actual Difference** |
|-------|-----------|----------|----------------------|
| 00 | strictAuthorization mandated | **MISSING** from AdminPanelProvider | See diff below |
| 01 | 14 migrations | All 14 present | ✅ |
| 02 | Base seeders | Not verified | ⚠️ |
| 03 | Models + Enums + Observer + LossLedger | All present | ✅ |
| 04 | Services with v10 fixes | Both complete | ✅ |
| 05 | ProductResource | Complete | ✅ |
| 06 | Price/Unit actions | Actions exist | ✅ |
| 07 | Warehouses + Adjustments | Complete | ✅ |
| 08 | Requisition Wizard | Complete | ✅ |
| 09 | Negotiation Loop UI | Complete | ✅ |
| 10 | Dispatch + Cancel (5-state) | Actions present, **policy missing** | ❌ Policy |
| 11 | STN + QR 7 days | Controllers exist | Need verify 7-day expiry |
| 12 | Scan-to-Receive | Complete with idempotency | ✅ |
| 13 | Audit Ledgers | Complete | ✅ |
| 14 | Bento Dashboard | Partial | Need verify |
| 15 | i18n translation | **Enums missing `__()`** | ❌ See diff above |
| 16 | CI/CD Testing | Tests run, 13 v10 targets missing | ❌ See test diff below |

### Actual Diff: AdminPanelProvider strictAuthorization

**Blueprint Phase 00.8:**
```php
->strictAuthorization()
```

**Codebase (AdminPanelProvider.php:30-81):**
```php
public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        ->path('admin')
        ->login(Login::class)
        ->spa()
        ->profile()
        ->multiFactorAuthentication(
            AppAuthentication::make()->recoverable(),
        )
        ->sidebarCollapsibleOnDesktop()
        ->maxContentWidth(Width::Full)
        ->colors([
            'primary' => Color::Blue,
        ])
        ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
        ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
        ->pages([Dashboard::class])
        ->widgets([
            // ...
        ])
        ->middleware([
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            AuthenticateSession::class,
            ShareErrorsFromSession::class,
            PreventRequestForgery::class,
            SubstituteBindings::class,
            DisableBladeIconComponents::class,
            DispatchServingFilamentEvent::class,
        ]);
        // ->strictAuthorization() IS MISSING
}
```

---

## 🧪 Section 9: CI/CD Testing — 13 MISSING v10 TESTS

### Missing Pest Tests (Exact Blueprint Names)

| # | Test Name | File Location (Expected) | Codebase Status |
|---|-----------|--------------------------|-----------------|
| 1 | `InventoryServiceTest::direct_transfer_locks_warehouses_in_sorted_id_order()` | `tests/Unit/Services/InventoryServiceTest.php` | **MISSING** |
| 2 | `InventoryServiceTest::direct_transfer_rejects_zero_or_negative_unit_ratio()` | `tests/Unit/Services/InventoryServiceTest.php` | **MISSING** |
| 3 | `InventoryServiceTest::record_movement_rejects_zero_or_negative_unit_ratio()` | `tests/Unit/Services/InventoryServiceTest.php` | **MISSING** |
| 4 | `InventoryServiceTest::scan_to_receive_is_idempotent_against_duplicate_submission()` | `tests/Unit/Services/InventoryServiceTransferLifecycleTest.php` | **MISSING** |
| 5 | `InventoryServiceTest::scan_to_receive_total_financial_loss_matches_bcmath_reference_value()` | `tests/Unit/Services/InventoryServiceTransferLifecycleTest.php` | **MISSING** |
| 6 | `ConcurrencyTest::simultaneous_opposite_direction_direct_transfers_do_not_deadlock()` | `tests/Unit/ConcurrencyTest.php` (new) | **MISSING** |
| 7 | `TransferRequisitionPolicyTest::cancel_is_permitted_while_confirmed()` | `tests/Feature/Policies/TransferRequisitionPolicyTest.php` (new) | **MISSING (needs policy first)** |
| 8 | `TransferRequisitionPolicyTest::cancel_is_rejected_once_dispatched()` | `tests/Feature/Policies/TransferRequisitionPolicyTest.php` (new) | **MISSING (needs policy first)** |
| 9 | `TransferRequisitionPolicyTest::cancel_is_rejected_while_partially_received()` | `tests/Feature/Policies/TransferRequisitionPolicyTest.php` (new) | **MISSING (needs policy first)** |
| 10 | `LossLedgerTest::snapshot_unit_cost_falls_back_to_zero_when_no_current_price_exists()` | `tests/Unit/Models/LossLedgerTest.php` | **MISSING** |
| 11 | `LossLedgerTest::snapshot_unit_cost_reflects_call_time_price_not_dispatch_time_price()` | `tests/Unit/Models/LossLedgerTest.php` | **MISSING** |
| 12 | `LowStockAlertsWidgetTest::cache_window_prevents_requery_within_300_seconds()` | `tests/Feature/Filament/Widgets/LowStockAlertsWidgetTest.php` (new) | **MISSING** |
| 13 | `LowStockAlertsWidgetTest::cache_miss_correctly_recomputes_all_variants()` | `tests/Feature/Filament/Widgets/LowStockAlertsWidgetTest.php` (new) | **MISSING** |

### Existing Related Tests (for reference)

| Test File | Existing Tests |
|-----------|----------------|
| `tests/Unit/Services/InventoryServiceMovementTest.php` | 18 tests covering recordMovement, directTransfer basics |
| `tests/Unit/Services/InventoryServiceTransferLifecycleTest.php` | 25 tests covering dispatch, scanToReceive, partial batches, loss, substitute variants |
| `tests/Unit/Models/LossLedgerTest.php` | **DOES NOT EXIST** |
| `tests/Feature/Filament/Widgets/` | **NO WIDGET TESTS EXIST** |

---

## ✅ Section 13: Verification Checklist — DETAILED DIFFS

### Item 15: Enums route getLabel() through `__()`

**Blueprint:**
```php
public function getLabel(): string
{
    return match ($this) {
        self::Draft => __('filament-inventory::transfer_requisition_status.draft'),
        // ...
    };
}
```

**Codebase (all 6 enums):**
```php
public function getLabel(): string
{
    return match ($this) {
        self::Draft => 'Draft',  // Direct string, no __()
        // ...
    };
}
```

**Files affected:**
- `app/Enums/TransferRequisitionStatus.php:26-40`
- `app/Enums/StockMovementType.php` (similar pattern)
- `app/Enums/InTransitStatus.php` (similar pattern)
- `app/Enums/NegotiationSide.php` (similar pattern)
- `app/Enums/RevisionStatus.php` (similar pattern)
- `app/Enums/UserRole.php` (similar pattern)

---

### Item 16: TransferRequisitionPolicy exists

**Blueprint:** Full 11-method policy class
**Codebase:** **FILE DOES NOT EXIST**

---

### Item 27: strictAuthorization() in panel provider

**Blueprint:** `->strictAuthorization()` in panel chain
**Codebase:** **MISSING** — see AdminPanelProvider diff above

---

### Item 8: ConfirmAction calls materializeRequestedAsApproved()

**Blueprint:** `ConfirmAction` → `NegotiationService::materializeRequestedAsApproved()`
**Codebase:** Need verify in `EditTransferRequisition.php` or action class

---

### Item 18: QR lifetime = 7 days

**Blueprint:** `URL::temporarySignedRoute(..., expiration: now()->addDays(7))`
**Codebase:** Need verify in `STNManifestController.php`

---

### Item 25: `->money(config('app.currency'))` on all money columns

**Blueprint:** All price/cost columns use `->money(config('app.currency'))`
**Codebase:** Need verify in `ProductsTable.php`, `LossLedgersTable.php`, etc.

---

## Summary of Actual Code Differences

| Category | Count | Details |
|----------|-------|---------|
| **Missing Files** | 6 | TransferRequisitionPolicy, StockMovementPolicy, InTransitPolicy, LossLedgerPolicy, WarehousePolicy, UserPolicy |
| **Missing Methods** | 1 | `TransferRequisitionResource::getEloquentQuery()` |
| **Missing Config** | 1 | `AdminPanelProvider::strictAuthorization()` |
| **Missing i18n** | 6 enums × ~10 cases = ~60 | All `getLabel()` return direct strings |
| **Missing Tests** | 13 | Exact test names from Blueprint Section 9 |
| **Need Verification** | 7 | QR 7-day, ConfirmAction wiring, money columns, 3 widgets, bento CSS, seeders, Playwright E2E |
| **Matching Exactly** | 80%+ | Database, Models, Services, Resources, Wizards, Actions UI |

---

## Priority Fix with Exact Files to Create/Modify

### 1. Create `app/Policies/TransferRequisitionPolicy.php` (11 methods)
### 2. Create `app/Policies/StockMovementPolicy.php` (2 methods)
### 3. Create `app/Policies/InTransitPolicy.php` (3 methods)
### 4. Create `app/Policies/LossLedgerPolicy.php` (3 methods)
### 5. Create `app/Policies/WarehousePolicy.php` (6 methods)
### 6. Create `app/Policies/UserPolicy.php` (4 methods)
### 7. Modify `app/Providers/Filament/AdminPanelProvider.php` — add `->strictAuthorization()`
### 8. Modify `app/Filament/Resources/TransferRequisitions/TransferRequisitionResource.php` — add `getEloquentQuery()`
### 9. Modify 6 enum files — wrap `getLabel()` returns in `__()`
### 10. Create 13 missing Pest test files
### 11. Verify 7 "Need Verification" items