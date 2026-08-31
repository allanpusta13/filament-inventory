# Plan: Comprehensive Top-to-Bottom Audit & Fix

## Status: APPROVED (self-approved — initiated by user)

## Context
Full application audit covering Filament v5 compliance, model integrity, policy coverage, service layer correctness, test completeness, and security. The audit discovered critical runtime crashes, legacy namespace imports, missing enum implementations, and security gaps.

## Critical Issues Found

### CRITICAL-1: Missing InventoryService Methods (Runtime Crash)
**Files:** `app/Actions/ConfirmTransferAction.php`, `app/Actions/DispatchTransferAction.php`, `app/Actions/ReceiveTransferAction.php`
**Impact:** TransferOrder workflow (confirm/dispatch/receive) throws `BadMethodCallException`
**Fix:** Add `lockStockForProduct()`, `currentQuantity()`, `availableForNegotiation()` methods to `app/Services/InventoryService.php`

### CRITICAL-2: Wrong Filament v5 Namespace (Runtime Crash)
**Files:** `app/Filament/Resources/Products/RelationManagers/VariantsRelationManager.php`, `app/Filament/Resources/Products/RelationManagers/ConversionsRelationManager.php`
**Impact:** `Filament\Forms\Components\Section` → must be `Filament\Schemas\Components\Section` in v5

### CRITICAL-3: Deprecated `->reactive()` Calls
**Files:** `app/Filament/Widgets/WarehouseFilterWidget.php`, `app/Filament/Pages/StockAdjustment.php`
**Fix:** Replace `->reactive()` with `->live()`

### CRITICAL-4: `->native(false)` May Not Exist in v5
**File:** `app/Filament/Resources/Users/Schemas/UserForm.php`
**Fix:** Remove both `->native(false)` calls

## High Priority Issues

### HIGH-1: Missing TransferOrderPolicy
**Fix:** Create `app/Policies/TransferOrderPolicy.php` with proper RBAC for all roles

### HIGH-2: TransferNoteController Missing Authorization
**File:** `app/Http/Controllers/TransferNoteController.php`
**Fix:** Add policy check or authorization guard

### HIGH-3: Missing Enum HasLabel Implementations
**Files:** `app/Enums/UserRole.php`, `app/Enums/TransferOrderItemStatus.php`, `app/Enums/MovementType.php`
**Fix:** Add `HasLabel` interface and `getLabel()` to all three

### HIGH-4: Missing Enum Casts on Models
**Files:** `app/Models/TransferOrderItem.php` (item_status), `app/Models/TransferRequisition.php` (status), `app/Models/InTransit.php` (status)
**Fix:** Add proper enum casts

## Medium Priority Issues

### MED-1: `->paginated([5])` Should Be `->paginated(5)`
**File:** `app/Filament/Widgets/PendingTransfersWidget.php`

### MED-2: Missing TransferRequisitionStatus Enum
**Fix:** Create `app/Enums/TransferRequisitionStatus.php` for the 10-value status column

### MED-3: Missing InTransitStatus Enum
**Fix:** Create `app/Enums/InTransitStatus.php` for the 3-value status column

## Out of Scope
- Race condition fixes for `generateReferenceNumber()` (low risk, admin-only operations)
- Inline FQN → import refactoring (style only, no functional impact)
- Nullable integer null-semantics (working as designed with integer cast)

## Files to Modify
1. `app/Services/InventoryService.php` — add 3 missing methods
2. `app/Filament/Resources/Products/RelationManagers/VariantsRelationManager.php` — fix Section import
3. `app/Filament/Resources/Products/RelationManagers/ConversionsRelationManager.php` — fix Section import
4. `app/Filament/Widgets/WarehouseFilterWidget.php` — reactive → live
5. `app/Filament/Pages/StockAdjustment.php` — reactive → live
6. `app/Filament/Resources/Users/Schemas/UserForm.php` — remove native(false)
7. `app/Filament/Widgets/PendingTransfersWidget.php` — paginated fix
8. `app/Enums/UserRole.php` — add HasLabel
9. `app/Enums/TransferOrderItemStatus.php` — add HasLabel
10. `app/Enums/MovementType.php` — add HasLabel
11. `app/Models/TransferOrderItem.php` — add item_status enum cast
12. `app/Models/TransferRequisition.php` — add status enum cast
13. `app/Models/InTransit.php` — add status enum cast

## Files to Create
14. `app/Policies/TransferOrderPolicy.php`
15. `app/Enums/TransferRequisitionStatus.php`
16. `app/Enums/InTransitStatus.php`

## Files to Modify (Security)
17. `app/Http/Controllers/TransferNoteController.php` — add authorization

## Test Plan
- All 322 existing tests must continue to pass
- New tests for InventoryService missing methods
- New tests for TransferOrderPolicy
- Run `vendor/bin/pint --dirty --format agent` after all changes
