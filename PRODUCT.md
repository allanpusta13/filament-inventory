# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Laravel 13, Filament v5, Livewire 4, Tailwind CSS v4, Pest 4, PHP 8.4

## Users

- **Warehouse staff** on the floor — receive, ship, transfer, and adjust stock at their assigned warehouse(s); need fast, scan-friendly actions and clear movement history
- **Inventory managers/operators** at desks — monitor current stock across warehouses, track low-stock alerts, review movement logs, manage reorder points
- **Admins** — configure products, warehouses, users, and warehouse assignments; see all data across the organization

All three personas use the same Filament admin panel; access is differentiated by `role` (admin vs. warehouse_staff) and the `user_warehouse` pivot.

## Product Purpose

A multi-warehouse inventory system where **current stock is never stored directly** — it is always computed as `SUM(quantity)` from an immutable `stock_movements` log. This eliminates drift, cache invalidation, and rebuild jobs at this scale (~few hundred SKUs, <10 warehouses).

Success means: staff can record movements in seconds; managers trust stock levels implicitly; admins never worry about data integrity.

## Positioning

**Role-scoped warehouse visibility built into every query.** Warehouse staff see only their assigned warehouse(s) — incoming, outgoing, and transfer movements — with the counterpart warehouse shown on transfers. Admins see everything. This scoping applies to the movement log, the current-stock aggregate view, and the low-stock widget automatically, without per-resource configuration.

A neighboring product could copy the schema, but not the guarantee that *every* view respects warehouse assignment without extra code.

## Operating Context

- Physical warehouse environment: staff may use tablets or laptops on the floor, sometimes with barcode scanners
- Movements are recorded in real time: Receive (PO), Ship (order), Transfer (between warehouses), Adjustment (cycle count)
- Transfers create two linked movement rows (`transfer_out` + `transfer_in`) in a single transaction
- Low-stock dashboard widget surfaces products below their `reorder_point` at the staff's warehouse(s)
- No separate "inventory" table — the movement log *is* the source of truth
- Testing uses SQLite; production uses MySQL/PostgreSQL
- All quantity changes flow through `InventoryService` — resources never touch quantities directly

## Capabilities and Constraints

- **Five movement types**: `receive`, `ship`, `transfer_out`, `transfer_in`, `adjustment` (signed quantities: + for in, − for out)
- **Role-based access**: `admin` sees all; `warehouse_staff` sees only assigned warehouses via `user_warehouse` pivot
- **InventoryService API**: `recordMovement`, `ship` (guards negative stock), `transfer` (paired movements), `currentQuantity`, `totalQuantity`
- **Current stock view**: grouped aggregate table (product × warehouse → SUM) with sorting, search, and role scoping
- **Low-stock widget**: TableWidget on dashboard, scoped to user's warehouses
- **Navigation groups**: Catalog (Products), Operations (Stock Movements, Current Stock), Admin (Warehouses, Users)
- **Form validation**: quantity positive integer on Receive/Ship/Transfer; Adjustment allows negative; required selects for product/warehouse
- **Date range filter** on movement log
- **Created-by tracking** on every movement (auto-filled from `auth()->id()`)
- **No API surface** — Filament admin panel only
- **Undecided**: whether to add barcode scanning, batch operations, or export features

## Brand Commitments

None — greenfield visual world. No existing name, logo, palette, typography, or voice to preserve. Filament defaults are the current baseline.

## Evidence on Hand

- Working Filament v5 admin panel with 5 resources (Product, Warehouse, StockMovement, CurrentStock, User)
- 130 passing Pest tests covering services, resources, scoping, and actions
- Blueprint at `docs/00-project/blueprint.md`
- Build prompts at `docs/00-project/prompts/00.md`–`11.md`
- Architecture decisions in `docs/00-project/plans/`
- No marketing copy, testimonials, case studies, or press assets exist

## Product Principles

1. **Immutability over convenience** — the movement log is append-only; corrections are new adjustment rows, never edits
2. **Scoping is invisible** — staff never see a "filter by warehouse" control; their warehouse assignment *is* the filter
3. **Single service owns quantity** — `InventoryService` is the only way stock changes; resources delegate to it
4. **Computed, not cached** — current stock is a `SUM()` at query time; at this scale, it's fast enough and always correct
5. **Admin panel first** — the UI is a Filament admin panel; no separate frontend, no API consumers

## Accessibility & Inclusion

- Must meet WCAG 2.1 AA for the admin panel (Filament default + custom forms/tables)
- Staff may use the system in bright warehouse lighting — sufficient contrast is critical
- Keyboard navigation for all actions (Filament baseline + custom action modals)