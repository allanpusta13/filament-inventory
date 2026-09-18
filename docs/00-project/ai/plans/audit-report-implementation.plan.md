# Implementation Plan: Audit Report v11 Recommendations

**Source**: docs/00-project/ai/references/audit_report_v11.md
**Complexity**: Medium-Large (phased across multiple priorities)

## Context
The codebase audit against blueprint v11 revealed strong alignment with all [FIX v11] items implemented and tested. However, 7 minor findings and 7 deferred v12 items require implementation. This plan covers all P0-P3 priorities in strict order.

## Current State (Verified)
- 1054 tests passing
- All [FIX v11] test targets passing
- Navigation groups correct; only `navigationSort` misaligned
- `recordLoss` action has 3 issues: wrong `visible()` states, bypasses bcmath, missing `warehouse_id` (actually present but needs verification)
- Doc-block corruption in `ProductVariant.php`
- Actions use `->schema()` already (no `->form()` found)
- `Placeholder` used in wizard review steps (needs `WizardReviewStep`)

## Patterns to Mirror

| Category | Source | Pattern |
|---|---|---|
| Table Actions | `TransferRequisitionsTable.php:161-226` | `Action::make()` with `->schema()`, `->action()`, `->requiresConfirmation()` |
| bcmath Precision | `InventoryService.php:1128-1132` | `bcmul((string) $qty, $unitCost, 4)` |
| Cost Snapshot | `LossLedger.php:46-49` | `snapshotUnitCostFrom()` with null-safe operator |
| Authorization | `TransferRequisitionPolicy.php` | Enum-based status allowlists in policy methods |
| Navigation | Resource classes | `navigationGroup` + `navigationSort` |
| Wizard Forms | `TransferRequisitionForm.php` | `Wizard::make([Step::make()])` with `->modalWidth()` |

## Files to Change

| File | Action | Priority |
|---|---|---|
| `TransferRequisitionsTable.php` | UPDATE | P0 |
| `WarehouseResource.php` | UPDATE | P0 |
| `StockMovementResource.php` | UPDATE | P0 |
| `LossLedgerResource.php` | UPDATE | P0 |
| `TransferRequisitionItemRevisionResource.php` | DELETE | P0 |
| `ProductVariant.php` | FORMAT (Pint) | P0 |
| `TransferRequisitionForm.php` | UPDATE | P1 |
| `DirectTransferForm.php` | UPDATE | P1 |
| `ProductForm.php` | TEST/UPDATE | P1 |
| All Policy classes | ENUMERATE | P1 |
| `NegotiationService.php` | UPDATE | P2 |
| `TransferRequisitionPolicy.php` | UPDATE | P2 |
| New Event classes | CREATE | P2 |
| `LowStockAlertsWidget.php` | UPDATE (when triggered) | P3 |

## Phase 0: P0 Pre-Merge (Do First)

### Task 0.1: Fix `recordLoss` Action
- **File**: `TransferRequisitionsTable.php:161-226`
- **Changes**:
  1. Line 167: Change `visible()` from `['dispatched', 'partially_received', 'completed']` to `['dispatched', 'partially_received']`
  2. Line 196-200: Remove `total_financial_loss` form field (auto-compute)
  3. Lines 205-220: Use `LossLedger::snapshotUnitCostFrom()` + `bcmul()` for `total_financial_loss`
- **Edge Cases**: null cost snapshot, zero cost, high precision, unauthorized access
- **Tests**: Add visibility tests per status, bcmath precision test, authorization test
- **Validation**: `php vendor/bin/pest --filter="recordLoss"`

### Task 0.2: Navigation Sort Alignment
- **Files**: 
  - `WarehouseResource.php:31` → `navigationSort = 1`
  - `StockMovementResource.php:24` → `navigationSort = 4`
  - `LossLedgerResource.php:23` → `navigationSort = 5`
  - `TransferRequisitionItemRevisionResource.php` → DELETE file
- **Edge Cases**: No duplicate sorts, autoloading works, permissions intact
- **Tests**: Assert sort values, verify removed resource not registered
- **Validation**: `php vendor/bin/pest --filter="navigation"` + manual panel check

### Task 0.3: Doc-block Corruption
- **File**: `ProductVariant.php`
- **Action**: Run `vendor/bin/pint --dirty` on this file
- **Edge Cases**: Only formatting/docblocks change, no logic diff
- **Tests**: Run existing `ProductVariant` tests
- **Validation**: `php vendor/bin/pint --dirty app/Models/ProductVariant.php && php vendor/bin/pest --filter="ProductVariant"`

## Phase 1: P1 v12 Phase 00 (Prep)

### Task 1.1: Panel `strictAuthorization()` Role Coverage
- **Files**: All Policy classes in `app/Policies/`
- **Action**: Enumerate every policy method; ensure each has explicit implementation before enabling strict mode
- **Methods to verify per blueprint §12**: viewAny, view, create, update, delete, restore, forceDelete, confirm, dispatch, receive, cancel, setPrice, adjustStock, recordLoss, negotiate
- **Edge Cases**: Guest, authenticated, each role, super admin; missing method under strict mode
- **Tests**: Policy matrix test for every method × role
- **Validation**: `php vendor/bin/pest --filter="Policy"`

## Phase 2: P1 v12 Phase 05 (Catalog)

### Task 2.1: `->form()` vs `->schema()` Audit
- **Search**: Grep for `->form(` in Action classes
- **Finding**: Codebase already uses `->schema()` — verify no `->form()` remains
- **Action**: If any found, replace with `->schema([...])`
- **Validation**: `grep -r "->form(" app/Filament/Resources/*/Tables/`

### Task 2.2: Placeholder → WizardReviewStep
- **Files**: `TransferRequisitionForm.php`, `DirectTransferForm.php`
- **Action**: Replace `Placeholder::make('review_summary')` with `WizardReviewStep::make()` Livewire component
- **Pattern**: Check Filament v5 docs for `WizardReviewStep` usage
- **Edge Cases**: Step navigation, data display, empty states, validation blocking
- **Tests**: Livewire wizard flow tests
- **Validation**: `php vendor/bin/pest --filter="Wizard"`

### Task 2.3: `createOptionForm` Auto-select
- **File**: `ProductForm.php`
- **Action**: Test inline Product create → variant Select auto-selects new Product; fix with `$refresh` if needed
- **Edge Cases**: Single/multiple/no variants, validation failure, modal cancel
- **Tests**: Livewire test for inline create + auto-select
- **Validation**: `php vendor/bin/pest --filter="ProductForm"`

## Phase 3: P2 v12 Phase 09 (Negotiation)

### Task 3.1: Service-Layer Negotiation Guard
- **Files**: `NegotiationService.php`, `TransferRequisitionPolicy.php`
- **Implementation** (per blueprint §10.1):
  1. Custom Exception: `NegotiationNotAllowedException`
  2. Guard Method: `assertNegotiable(TransferRequisitionItemRevision $revision)` — check status ∈ {Requested, UnderReviewFulfiller, UnderReviewRequestor}, revision status = Pending
  3. Model Defense: `TransferRequisitionItemRevision::accept()/reject()` add `ensureCanTransitionTo()`
  4. Policy Ability: Add `negotiate(User, TransferRequisition)` mirroring status allowlist
  5. UI Wiring: Table actions with `->action()` handlers, `mountActionRecord`, `requiresConfirmation()`
- **Tests**: 27 status-matrix tests (9 statuses × 3 methods), side-mismatch, non-pending revision
- **Validation**: `php vendor/bin/pest --filter="Negotiation"`

### Task 3.2: Event + Notification Layer
- **New Files**: `app/Events/InventoryBelowReorderPoint.php`, `TransferDispatched.php`, `TransferReceived.php`, `LossRecorded.php`
- **Action**: Create Event classes + Listeners + Notifications
- **Edge Cases**: Correct transitions only, no duplicates, queued failures, missing notifiable
- **Tests**: `Event::fake()` + `Notification::fake()` tests
- **Validation**: `php vendor/bin/pest --filter="Event"`

## Phase 4: Phase 16 Testing

### Task 4.1: v12 Test Targets + Playwright Scenario 8
- **Action**: Implement all test targets from blueprint §9 for v12 items
- **Playwright**: Negotiate → accept → counter → reject → confirm flow
- **Validation**: `php vendor/bin/pest && npx playwright test`

## Phase 5: P3 Scaling (Trigger-Based)

### Task 5.1: Low-Stock Widget Optimization
- **Trigger**: Catalog > 5k–10k variants OR cache-miss > 1–2s
- **File**: `LowStockAlertsWidget.php`
- **Action**: Replace per-variant loop with single grouped-aggregate query
- **Pattern**: One `stock_movements` query grouped by `(product_variant_id, warehouse_id)` + `SUM(quantity)`, joined with `transfer_requisition_items` aggregate for Confirmed reservations
- **Tests**: Query-count test, result parity test, benchmark at scale

## Validation Commands

```bash
# Full test suite
php vendor/bin/pest

# Pint formatting
php vendor/bin/pint --dirty

# Specific test filters
php vendor/bin/pest --filter="recordLoss"
php vendor/bin/pest --filter="Navigation"
php vendor/bin/pest --filter="Policy"
php vendor/bin/pest --filter="Negotiation"
php vendor/bin/pest --filter="Event"

# Playwright E2E
npx playwright test
```

## Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| P0 changes break existing tests | Low | Run full suite after each P0 task |
| WizardReviewStep not in current Filament minor | Medium | Check pinned version; fallback to Placeholder if unavailable |
| Negotiation guard 27 tests complex | Medium | Generate test matrix programmatically |
| Event layer adds queue dependencies | Low | Use sync driver for tests; document queue config |
| P3 optimization query complexity | Low | Only implement when metrics trigger |

## Acceptance Criteria
- [ ] All P0 tasks complete with tests passing
- [ ] P1 Phase 00 complete (policy enumeration)
- [ ] P1 Phase 05 complete (form/schema, Placeholder, createOptionForm)
- [ ] P2 Phase 09 complete (negotiation guard + 27 tests)
- [ ] P2 Events complete (4 events + notifications)
- [ ] Phase 16 complete (all v12 tests + Playwright Scenario 8)
- [ ] P3 documented for future trigger
- [ ] Full test suite passes (1054+ tests)
- [ ] Pint clean on all modified files