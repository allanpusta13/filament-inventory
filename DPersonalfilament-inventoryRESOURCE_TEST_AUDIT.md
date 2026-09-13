# Resource Test Audit Report

**Generated:** 2025-09-12
**Blueprint:** v10.0
**Test Runner:** Pest + Livewire
**Total Tests:** 284 (1 flaky) | **Assertions:** 1085

---

## Executive Summary

| Metric | Value |
|--------|-------|
| **Total Tests** | 284 (1 flaky) |
| **Assertions** | 1085 |
| **Livewire Coverage** | 100% (all tests use `livewire()`) |
| **DB Assertions** | 66 (`assertDatabaseHas`/`assertDatabaseMissing`) |
| **Resources Tested** | 9/9 |
| **Form Submission Tests** | **1/9** (ProductResource only) |
| **Relation Save Tests** | **0/9** |

---

## Test Coverage Matrix

| Resource | Livewire | List/View | Columns/Sort/Filter | Delete/Bulk | Form Submit | Relation Save |
|----------|----------|-----------|-------------------|-------------|-------------|-------------|
| **Products** | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **TransferRequisitions** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **DirectTransfers** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **InTransits** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **StockMovements** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **LossLedgers** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **TransferReqItemRev** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ (substitute, respondsTo) |
| **Warehouses** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Users** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |

**Summary:** 9/9 resources use Livewire, 9/9 test list/view/columns/sort/filter/delete/bulk, **1/9** test form submissions, **0/9** test relation persistence.

---

## Test Coverage Matrix

| Resource | Livewire | List/View | Columns/Sort/Filter | Delete/Bulk | Form Submit | Relation Save |
|----------|----------|-----------|-------------------|-------------|-------------|-------------|
| **Products** | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **TransferRequisitions** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **DirectTransfers** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **InTransits** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **StockMovements** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **LossLedgers** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **TransferReqItemRev** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ (substitute, respondsTo) |
| **Warehouses** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Users** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |

**Summary:** 9/9 resources use Livewire, 9/9 test list/view/columns/sort/filter/delete/bulk, **1/9** test form submissions, **0/9** test relation persistence.

---

## What's Tested ✅

### All Resources (9/9)
- Index page rendering (`livewire(List::class)->assertOk()`)
- View page rendering with schema state
- Table column existence
- Column sorting (asc/desc)
- Column filtering (select filters)
- Search functionality
- Single record deletion
- Bulk deletion
- Record restoration (where SoftDeletes)
- Status/side badge rendering

### Database Assertions (66 total)
- `assertDatabaseHas` / `assertDatabaseMissing` for delete/bulk delete/restore
- Status/badge value assertions on models

### ProductResource Only (1/9)
- `fillForm()->call('create')` with `assertDatabaseHas`
- `fillForm()->call('save')` with `assertDatabaseHas`
- Validation error assertions (`assertHasFormErrors`)

---

## Critical Gaps ❌

### 1. Zero Form Submission Tests (8/9 Resources)
| Resource | Missing Test |
|----------|--------------|
| TransferRequisitions | Wizard create (3-step), Edit page save |
| DirectTransfers | Wizard create (3-step) |
| InTransits | N/A (read-only) |
| StockMovements | N/A (read-only) |
| LossLedgers | N/A (read-only) |
| TransferReqItemRev | Edit propose/accept/reject/counter |
| Warehouses | Create/Edit form submit |
| Users | Create/Edit form submit |

### 2. Zero Relation Persistence Tests (All Resources)
| Relation Type | Example | Tested? |
|---------------|---------|---------|
| Repeater/nested items | TransferRequisition wizard items | ❌ |
| Substitute variant swap | TransferRequisitionItemRevision.substituteProductVariant | ❌ |
| Self-referential revisions | TransferRequisitionItemRevision.respondsTo | ❌ |
| Bidirectional stock movements | DirectTransfer related_movement_id | ❌ |
| Item→Requisition link | InTransit.item relation | ❌ |
| Item→Requisition link | LossLedger.item relation | ❌ |
| Nested JSON data | ProductVariant attributes/images | ❌ |

### 3. Service Layer Only (No UI Integration)
- **InventoryService:** 7 tests (unit, mock DB)
- **NegotiationService:** tests exist
- **Gap:** No test verifies UI form → service → DB relation persistence chain

---

## Livewire Test Patterns Used

```php
// All 284 tests use this pattern:
livewire(ListResource::class)
    ->assertOk()
    ->assertTableColumnExists('column')
    ->sortTable('column')
    ->filterTable('filter', 'value')
    ->searchTable('query')
    ->callAction(DeleteAction::class)
    ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
    ->assertCanSeeTableRecords($records)

// Only ProductResource uses:
livewire(CreateProduct::class)
    ->fillForm([...])
    ->call('create')
    ->assertNotified()
    ->assertHasNoFormErrors()
    ->assertDatabaseHas(Model::class, [...])
```

---

## Recommendations (Priority Order)

### P0 - Core Workflow Forms
1. **TransferRequisition wizard create** (3-step: routing → items → review)
2. **DirectTransfer wizard create** (3-step: location → allocation → review)
3. **Warehouse/User create/edit** (simple forms)

### P1 - Negotiation & Relations
1. **TransferRequisitionItemRevision** propose/accept/reject/counter with `substituteProductVariant` + `respondsTo`
2. **TransferRequisition wizard items** (Repeater nested data)

### P2 - Advanced Relations
1. **DirectTransfer** `related_movement_id` bidirectional save
3. **InTransit** scan-to-receive (when Phase 12 implemented)
4. **LossLedger** creation with item relation

---

## Test Commands

```bash
# Run all resource tests
vendor/bin/pest tests/Feature/Filament/Resources/ --compact

# Run specific resource
vendor/bin/pest tests/Feature/Filament/Resources/ProductResourceTest.php --compact

# With coverage
vendor/bin/pest tests/Feature/Filament/Resources/ --coverage
```

---

## Council Verdict Summary

> **Consensus:** Tests pass (284), cover core read/CRUD/delete. But **form submission + relation persistence = ~0% coverage**.
>
> **Strongest Dissent:** Critic: nested relations (repeater items, substitute variants, bidirectional stock movements) completely untested.
>
> **Recommendation:** Add form submission tests for all 8 missing resources + nested relation persistence tests.
