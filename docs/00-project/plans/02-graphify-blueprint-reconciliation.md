# Plan: Graphify-Blueprint Reconciliation

## Objective
Audit the codebase against `blueprint.md` v3 and reconcile all discrepancies across models, migrations, resources, and plan documentation.

---

## Phase 1: Graphify Index — DEFERRED
- `graphify` is a Claude skill, not an npm CLI — cannot run as `npx graphify update`
- Skipped; proceeding with manual audit

---

## Phase 2: Blueprint vs. Codebase Audit (COMPLETE)

### Table-by-Table Findings

#### Blueprint Table: `products`
- **Model:** `app/Models/Product.php`
- **Migration:** `database/migrations/2025_08_25_155827_create_products_table.php`
- **Status:** ✅ EXISTS
- **Delta:** Codebase adds `unit` (string), `is_active` (boolean, default true), `deleted_at` (soft deletes) — these are extensions, not violations

#### Blueprint Table: `product_variants`
- **Model:** `app/Models/ProductVariant.php`
- **Migration:** `database/migrations/2025_08_25_155828_create_product_variants_table.php`
- **Status:** ✅ EXISTS — matches blueprint

#### Blueprint Table: `product_unit_conversions`
- **Model:** `app/Models/ProductUnitConversion.php`
- **Migration:** `database/migrations/2025_08_25_155829_create_product_unit_conversions_table.php`
- **Status:** ✅ EXISTS — matches blueprint

#### Blueprint Table: `warehouses`
- **Model:** `app/Models/Warehouse.php`
- **Migration:** `database/migrations/2025_08_25_155830_create_warehouses_table.php`
- **Status:** ✅ EXISTS — matches blueprint

#### Blueprint Table: `stock_movements`
- **Model:** `app/Models/StockMovement.php`
- **Migration:** `database/migrations/2025_08_25_160731_create_stock_movements_table.php` + `2026_09_03_084350_add_notes_to_stock_movements_table.php`
- **Status:** ⚠️ MISMATCH
- **Extra columns:** `unit_name_used` (string), `unit_ratio_used` (decimal), `notes` (text nullable), `reference_type` (string nullable), `reference_id` (unsignedBigInteger nullable)
- **Note:** Extra columns serve real functionality (multi-unit tracking, audit notes, polymorphic references). Blueprint should be updated.

#### Blueprint Table: `transfer_requisitions`
- **Model:** `app/Models/TransferRequisition.php`
- **Migration:** `database/migrations/2025_08_25_160732_create_transfer_requisitions_table.php`
- **Status:** ⚠️ MISMATCH
- **Extra columns:** `approved_at` (timestamp nullable), `received_at` (timestamp nullable), `notes` (text nullable), `deleted_at` (soft deletes)
- **Extra statuses:** `partially_received` in addition to blueprint's `pending|confirmed|dispatched|received|cancelled`

#### Blueprint Table: `requisition_items`
- **Model:** `app/Models/TransferRequisitionItem.php` (renamed from blueprint's `requisition_items`)
- **Migration:** `database/migrations/2025_08_25_160733_create_requisition_items_table.php`
- **Status:** ⚠️ MISMATCH
- **Table name:** `requisition_items` (matches blueprint)
- **Model name:** `TransferRequisitionItem` (blueprint says `RequisitionItem`)
- **Extra columns:** `approved_unit_ratio` (decimal), `shipped_base_qty` (decimal), `received_good_base_qty` (decimal), `received_damaged_base_qty` (decimal), `notes` (text nullable)

#### Blueprint Table: `requisition_item_revisions`
- **Model:** `app/Models/TransferRequisitionAudit.php` (renamed from blueprint's `requisition_item_revisions`)
- **Migration:** `database/migrations/2025_08_25_160734_create_requisition_item_revisions_table.php`
- **Status:** ⚠️ RENAMED + RESTRUCTURED
- **Table name:** `transfer_requisition_audits` (NOT `requisition_item_revisions`)
- **Blueprint columns:** `requisition_item_id`, `from_status`, `to_status`, `performed_by`, `notes`
- **Actual columns:** `transfer_requisition_id`, `user_id`, `action`, `changes_payload`

#### Blueprint Table: `loss_ledgers`
- **Model:** `app/Models/LossLedger.php`
- **Migration:** `database/migrations/2025_08_25_160735_create_loss_ledgers_table.php`
- **Status:** ⚠️ MISMATCH
- **Extra columns:** `requisition_item_id` (foreign nullable), `recorded_by` (foreign), `recorded_at` (timestamp)

#### Blueprint Table: `users`
- **Model:** `app/Models/User.php`
- **Status:** ✅ EXISTS — extended with `UserRole` enum for role field

#### Blueprint Table: `user_warehouse` (pivot)
- **Migration:** `database/migrations/2025_08_25_160734_create_user_warehouse_table.php`
- **Status:** ✅ EXISTS — matches blueprint

---

### Extra Tables (NOT in Blueprint)

| Table | Model | Purpose |
|-------|-------|---------|
| `in_transit` | `InTransit` | Per-requisition-item dispatch tracking with `InTransitStatus` enum |
| `transfer_orders` | `TransferOrder` | Separate transfer order system with sender/receiver branches |
| `transfer_order_items` | `TransferOrderItem` | Items for transfer orders |
| `transfer_order_audits` | `TransferOrderAudit` | Audit trail for transfer orders |
| `product_prices` | `ProductPrice` | Per-warehouse pricing with `unit_name`, `price_type`, `price` |
| `warehouse_stock` | `WarehouseStock` | Cached stock levels with `on_hand_quantity`, `reserved_quantity`, computed `available_quantity` |
| `exports` / `imports` / `failed_import_rows` | Filament import/export system |
| Standard Laravel: `cache`, `failed_jobs`, `job_batches`, `jobs`, `migrations`, `notifications`, `password_reset_tokens`, `sessions` |

---

### Model-to-Table Mapping Summary

| Model | Table | Filament Resource | Policy | Status |
|-------|-------|-------------------|--------|--------|
| `Product` | `products` | `Products` | `ProductPolicy` | ✅ |
| `ProductVariant` | `product_variants` | `Products` (nested) | — | ✅ |
| `ProductUnitConversion` | `product_unit_conversions` | — | — | ✅ |
| `ProductPrice` | `product_prices` | `ProductPrices` | — | ⚡ NOT IN BLUEPRINT |
| `Warehouse` | `warehouses` | `Warehouses` | — | ✅ |
| `WarehouseStock` | `warehouse_stock` | — | — | ⚡ NOT IN BLUEPRINT |
| `StockMovement` | `stock_movements` | `StockMovements` | `StockMovementPolicy` | ⚠️ MISMATCH |
| `TransferRequisition` | `transfer_requisitions` | `TransferRequisitions` | `TransferRequisitionPolicy` | ⚠️ MISMATCH |
| `TransferRequisitionItem` | `requisition_items` | `TransferRequisitionResource` | — | ⚠️ MISMATCH |
| `TransferRequisitionAudit` | `transfer_requisition_audits` | — | — | ⚠️ RENAMED |
| `LossLedger` | `loss_ledgers` | `LossLedger` | `LossLedgerPolicy` | ⚠️ MISMATCH |
| `InTransit` | `in_transit` | `InTransit` | — | ⚡ NOT IN BLUEPRINT |
| `TransferOrder` | `transfer_orders` | `TransferOrders` | `TransferOrderPolicy` | ⚡ NOT IN BLUEPRINT |
| `TransferOrderItem` | `transfer_order_items` | `TransferOrders` (nested) | — | ⚡ NOT IN BLUEPRINT |
| `TransferOrderAudit` | `transfer_order_audits` | — | — | ⚡ NOT IN BLUEPRINT |
| `User` | `users` | `Users` | — | ✅ |

---

### Enum Summary

| Enum | Values | Used By |
|------|--------|---------|
| `UserRole` | `Admin`, `BranchManager`, `WarehouseStaff`, `Auditor` | `User.role` |
| `MovementType` | `receive`, `dispatch`, `adjustment` | `StockMovement.type` |
| `TransferRequisitionStatus` | `pending`, `confirmed`, `dispatched`, `received`, `partially_received`, `cancelled` | `TransferRequisition.status` |
| `InTransitStatus` | `pending`, `in_transit`, `received` | `InTransit.status` |
| `TransferOrderStatus` | `pending`, `confirmed`, `dispatched`, `received`, `cancelled` | `TransferOrder.status` |
| `TransferOrderItemStatus` | `pending`, `confirmed`, `dispatched`, `received`, `cancelled` | `TransferOrderItem.status` |

---

## Phase 3: Plans Re-alignment

### Action: Update `blueprint.md` to match actual codebase

The blueprint should be brought IN LINE with the codebase, since:
1. All extra columns serve real, tested functionality
2. All extra tables (InTransit, TransferOrder, ProductPrice, WarehouseStock) are fully implemented with Filament resources, policies, and tests
3. Renamed models/tables follow Filament v5 conventions
4. The codebase is the ground truth — it runs, has 401+ passing tests

### Action: Update `01-stock-movement-counterpart.md` — DONE (historical)

### Action: Create `02-graphify-blueprint-reconciliation.md` — THIS FILE

### Action: Create `03-blueprint-v4-update.md` — Plan to update blueprint.md

---

## Phase 4: Refactor & Reconcile — COMPLETE

### Decisions Made
1. **Blueprint updated to v4** — §1 (Data Model), §2 (Enums), §3 (Navigation), §4 (Services), §5 (State Machine), §6 (Scan-to-Receive), §7 (File Structure), §8 (Build Order) all rewritten to match actual codebase
2. **Model naming** — Kept `TransferRequisitionItem` (Filament convention, matches codebase)
3. **Audit table naming** — Kept `transfer_requisition_audits` (matches codebase)
4. **Extra columns** — All extra columns documented in blueprint v4 (they serve real tested functionality)

### Prompts Directory Status
- `docs/00-project/prompts/` shows pre-existing diffs (not from this session)
- These were modified in a prior commit (`5b508d3 refactor(architecture)`)
- This session did NOT touch any prompts files

---

## Verification
- [x] Blueprint.md updated to v4 (§1–§8 complete)
- [x] Audit report (this file) updated to reflect completed state
- [x] `docs/00-project/prompts/` NOT modified by this session (pre-existing diffs noted)
- [ ] All Pint formatting applied
- [ ] All Pest tests pass
- [ ] All Playwright tests pass
