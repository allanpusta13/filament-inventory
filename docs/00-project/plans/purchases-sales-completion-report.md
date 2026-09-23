# Purchases / Sales — Completion Report

## Summary of All Changes

| Item | Resolution | Status |
| --- | --- | --- |
| Migrations / enums | Purchase / sales tables plus StockMovementType Purchase / Sale / SaleReturn / PurchaseReturn cases added via migrations | Done |
| 6 models + factories | PurchaseOrder, PurchaseOrderItem, SalesOrder, SalesOrderItem, Supplier, Customer models with factories | Done (factories pre-existed; see Drift) |
| batchAvailableQuantity | Batched availability with 3-query proof test | Done |
| GuardsOutstandingQuantity trait + tests | Shared guard for outstanding purchase / sales quantities with dedicated tests | Done |
| Purchase / Sales services | Domain services for receive / dispatch flows | Done |
| 4 new policies + 7 abilities | Policies for new order / party models wiring 7 new abilities | Done |
| A8 consolidation | 22 of 25 authorization call sites moved to policies; 3 left NEEDS-RULING | Partial — 3 NEEDS-RULING |
| AdminReviewFilters warehouse() / period() | Added to both order tables, visible-gated to admin / auditor | Done |
| 4 widgets | Purchasing / sales overview widgets registered | Done |
| Ledger / concurrency tests | Ledger integrity and concurrency coverage, including transfer-depletes-reservation marker test (confirmed behavior: directTransfer checks on-hand only, dispatchSale throws after) | Done |
| i18n | Grep for `__()` in the 4 new resources returned zero matches: `rg -n "__\(" app/Filament/Resources/PurchaseOrders app/Filament/Resources/SalesOrders app/Filament/Resources/Suppliers app/Filament/Resources/Customers` exit 1, no output | Not done — no `__()` usage found |

## Evidence 1 — reservedQuantity untouched

- `ProductVariant::reservedQuantity()` Confirmed-only scope was not modified.
- Protected by unmodified parent tests re-run green.
- PolicyAudit green confirms no authorization regression around the quantity surface.

## Evidence 2 — Open decisions with rulings

- Ledger mixed / split: OPEN (untouched, mixed).
- setPrice composition: OPEN (spec-as-written stands).
- Transfer hardening: EVIDENCE-BASED FOLLOW-UP (marker test passes; parent dispatch change out of scope).
- Extend filters to parent: OPEN (not done, additive-only).
- Filter hardening: OPEN (visible-only per parent convention).

Additional owner decisions recorded:

- Playwright suite deferred (broken, later pass; new `purchases-sales.spec.ts` not written).
- 3 NEEDS-RULING consolidation sites:
  - `DirectTransferResource:35-43` — admin-only vs shared policy.
  - `TransferRequisitionResource:69` — data-scoping.
  - `StockActions:245` / `DashboardFilterable:26` — data-scoping.

## Evidence 3 — Test runs

- Lane: 1478 passed / 0 failed.
- Final master: 1514 passed / 0 failed / 7 skipped (pre-existing markers) / 1 risky (pre-existing).
- Playwright NOT run per deferral.
- No test runs were executed while writing this report; figures carried from recorded runs.

## Evidence 4 — Drift resolutions

- Six new-model factories pre-existed; the addendum assumed greenfield.
- Implementation `8f8563f` predated the voyage.
- Merge-base verified; no factory was treated as voyage-new.

## Evidence 5 — A8 checklist

- `PolicyAuditTest` green, pinning only the 2 ruling-pending files.
- Arch rule `not->toUse Gate` in Services holds.
- 5 new `viewAdminReview` policy methods with frozen doc-blocks.
- Owner visibility via this report.

## Explicit follow-ups

1. Playwright fix + new `purchases-sales.spec.ts` — deferred, not written.
2. 3 NEEDS-RULING consolidation sites (DirectTransferResource:35-43, TransferRequisitionResource:69, StockActions:245 / DashboardFilterable:26) — awaiting rulings.
3. 5 open decisions above (ledger mixed/split, setPrice composition, transfer hardening follow-up, extend-filters-to-parent, filter hardening).
4. TODO.md line — not added; final criteria incomplete.
