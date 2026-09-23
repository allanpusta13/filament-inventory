# Purchases / Sales — Phase 0 Audit

Source: read-only scout + verification. No code changed during this audit.

## 1. ProductVariant quantity surface

- `reservedQuantity(int $warehouseId): int` at `app/Models/ProductVariant.php:144`.
  Confirmed-only scope untouched.
- `reservedForSalesQuantity()` at `app/Models/ProductVariant.php:161`.
  Sums `base_qty - dispatched_base_qty` for Confirmed sales.
- `availableQuantity()` at `app/Models/ProductVariant.php:176`.
  Three-term computation.
- Verdict: MATCHED.

## 2. StockMovementType enum

- 12 cases including Purchase, Sale, SaleReturn, PurchaseReturn.
- Implements `getLabel()`, `getColor()`, `getIcon()`, `isInbound()`, `isOutbound()`.
- Verdict: MATCHED.

## 3. InventoryService

- `recordMovement`: transaction + `lockForUpdate` + insufficient-stock Exception.
- `directTransfer`: canonical sorted-ID warehouse locking.
- `dispatchTransfer`: locks requisition.
- `scanToReceive`: bcmath + idempotency + LossLedger.
- Verdict: MATCHED.

## 4. Transfer create flow

- `TransferRequisitionForm` Repeater `items->relationship('items')` plus
  `mutateRelationshipDataBeforeCreateUsing`.
- Purchase / Sales forms use `->relationship()`; page classes use
  `mutateFormDataBeforeCreate` for reference / status only.
- Verdict: MATCHED — standard `saveRelationships` flow everywhere.

## 5. AdminPanelProvider

- `strictAuthorization` active at
  `app/Providers/Filament/AdminPanelProvider.php:62`.
- Abilities resolved via Policies.
- Groups: CATALOG, OPERATIONS, PURCHASING, SALES, AUDIT LEDGERS, SYSTEM ADMIN.
- Verdict: MATCHED.

## 6. Tests

- Suites under `tests/Feature` and `tests/Unit`.
- `RefreshDatabase`, sqlite `:memory:`.
- DRIFT: six new-model factories already existed. The addendum assumed
  greenfield, but implementation `8f8563f` predated the voyage.

## 7. Regression question

- YES — unmodified parent `reservedQuantity` tests re-run green.

## 8. Git note

- `main` had 26 uncommitted hardening files, reconciled to the lane as
  `1b39d2a`, PR #2, merged as `befa082`.
- Ship never booted (no tmux on Windows); lane worktree retired.
