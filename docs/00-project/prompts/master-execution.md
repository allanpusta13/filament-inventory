# Filament Inventory (v3.0): Model, Migration, Factory & Test Repair Protocol

You are an autonomous Senior Laravel Architect and Quality Assurance Lead. Your task is to audit, refactor, and repair all database migrations, Eloquent models, model factories, enums, and Pest unit/feature tests for the **Filament Inventory (v3.0)** multi-warehouse platform. 

You must run real execution commands (`vendor/bin/pest`, `php artisan migrate:fresh --seed`), inspect failure tracebacks, diagnose root causes, and refactor code until 100% of tests pass cleanly with ZERO schema or relationship gaps.

---

## 🧭 Non-Negotiable Architectural Laws (v3.0 Schema Standards)

Every fix you apply on disk MUST strictly comply with the following 10 system laws:

1. **Pure Derived Stock Model (NO Cached Tables)**:
   - The `warehouse_stock` table and columns (`on_hand_quantity`, `reserved_quantity`) MUST NOT exist in migrations or models.
   - Physical stock levels MUST be calculated dynamically on `ProductVariant` via `StockMovement::where('product_variant_id', $this->id)->where('warehouse_id', $warehouseId)->sum('quantity')`.
   - Reserved quantity MUST be computed on `ProductVariant` as the sum of `approved_base_qty` on `TransferRequisitionItem` records where the parent `TransferRequisition` has `from_warehouse_id = $warehouseId` and status in `['confirmed', 'dispatched']`.
   - Available stock MUST be computed dynamically as `$this->onHandQuantity($warehouseId) - $this->reservedQuantity($warehouseId)`.

2. **Variant-Only SKU & Safety Thresholds**:
   - `sku` and `reorder_point` MUST exist EXCLUSIVELY on `product_variants`.
   - The parent `products` table MUST act purely as a family container holding `id`, `name`, `category`, `softDeletes()`, and `timestamps()`.

3. **Consolidated Pricing on Variants**:
   - There is NO separate `product_variant_prices` table.
   - `cost_price` and `sale_price` MUST exist directly on `product_variants` as `decimal(15,4)` columns with a default value of `0.0000`.

4. **Explicit Foreign Key Contracts**:
   - All table names MUST be plural `snake_case`: `products`, `product_variants`, `product_variant_unit_conversions`, `warehouses`, `stock_movements`, `transfer_requisitions`, `transfer_requisition_items`, `transfer_requisition_item_revisions`, `in_transits`, `loss_ledgers`, `users`, `user_warehouse`.
   - All foreign keys MUST match explicit parent entity names: `product_id`, `product_variant_id`, `substitute_product_variant_id`, `warehouse_id`, `from_warehouse_id`, `to_warehouse_id`, `transfer_requisition_id`, `transfer_requisition_item_id`, `user_id`, `created_by`, `recorded_by`.

5. **Canonical 4-Role RBAC**:
   - `users.role` MUST be cast to the `UserRole` Enum (`admin`, `auditor`, `branch_manager`, `warehouse_staff`).

---

## 🛠️ Step-by-Step Execution Plan

Execute the following 5 phases sequentially. Do not stop until all tests pass with zero failures.

### Phase 1: Migration Audit & Schema Realignment
Scan every migration file in `database/migrations/` and verify the execution sequence and table definitions:
1. `create_products_table`: `id`, `name`, `category` (nullable), `softDeletes()`, `timestamps()`. (Ensure `sku` and `reorder_point` are removed).
2. `create_product_variants_table`: `id`, `product_id` (FK → `products`), `sku` (unique), `barcode` (nullable, unique), `name`, `base_unit_name`, `cost_price` (`decimal:15,4`, default `0.0000`), `sale_price` (`decimal:15,4`, default `0.0000`), `reorder_point` (`integer`, default `0`), `attributes` (`json`, nullable), `images` (`json`, nullable), `is_active` (`boolean`, default `true`), `softDeletes()`, `timestamps()`.
3. `create_product_variant_unit_conversions_table`: `id`, `product_variant_id` (FK → `product_variants`), `unit_name`, `base_unit_ratio`, `is_default_purchase`, `is_default_transfer`, `timestamps()`.
4. `create_warehouses_table`: `id`, `code` (unique), `name`, `location` (nullable), `is_active` (default `true`), `timestamps()`.
5. `update_users_table_for_rbac`: `role` (`string`, default `'warehouse_staff'`).
6. `create_user_warehouse_table`: `user_id` (FK), `warehouse_id` (FK), composite primary key `['user_id', 'warehouse_id']`.
7. `create_stock_movements_table`: `id`, `product_variant_id` (FK), `warehouse_id` (FK), `type` (enum), `quantity` (`signed integer`), `unit_name_used`, `unit_ratio_used`, `related_movement_id` (nullable FK), `reference_type` (nullable), `reference_id` (nullable), `reference_code` (nullable), `created_by` (nullable FK), `timestamps()`. Add composite index `['product_variant_id', 'warehouse_id']`.
8. `create_transfer_requisitions_table`: `id`, `reference_code` (unique), `from_warehouse_id` (FK), `to_warehouse_id` (FK), `status` (enum), `requested_by` (FK), `approved_by` (nullable FK), `dispatched_by` (nullable FK), `received_by` (nullable FK), `requested_at`, `approved_at`, `dispatched_at`, `completed_at`, `notes`, `softDeletes()`, `timestamps()`.
9. `create_transfer_requisition_items_table`: `id`, `transfer_requisition_id` (FK), `product_variant_id` (FK), `substitute_product_variant_id` (nullable FK), `requested_unit_name`, `requested_unit_ratio`, `requested_qty`, `requested_base_qty`, `approved_unit_name` (nullable), `approved_unit_ratio` (nullable), `approved_qty` (nullable), `approved_base_qty` (nullable), `shipped_base_qty` (default 0), `received_good_base_qty` (default 0), `received_damaged_base_qty` (default 0), `received_qty` (nullable), `notes`, `timestamps()`.
10. `create_transfer_requisition_item_revisions_table`: `id`, `transfer_requisition_item_id` (FK), `user_id` (FK), `product_variant_id` (FK), `substitute_product_variant_id` (nullable FK), `proposed_unit_name`, `proposed_qty`, `proposed_base_qty`, `negotiation_reason` (nullable), `side` (enum), `status` (enum), `responds_to_revision_id` (nullable FK), `responded_at` (nullable), `timestamps()`.
11. `create_in_transits_table`: `id`, `transfer_requisition_id` (FK), `transfer_requisition_item_id` (FK), `product_variant_id` (FK), `dispatched_base_qty`, `dispatched_at`, `status` (enum), `timestamps()`.
12. `create_loss_ledgers_table`: `id`, `transfer_requisition_id` (FK), `transfer_requisition_item_id` (nullable FK), `product_variant_id` (FK), `warehouse_id` (FK), `lost_base_qty` (default 0), `damaged_base_qty` (default 0), `unit_cost_price` (`decimal:15,4`), `total_financial_loss` (`decimal:15,4`), `loss_category`, `recorded_by` (nullable FK), `recorded_at`, `timestamps()`.

*Action*: Delete any obsolete migration files (like `create_warehouse_stock_table` or `create_product_variant_prices_table`).

---

### Phase 2: Eloquent Models & Relationship Realignment
Inspect and update all models in `app/Models/`:
- **`Product`**:
  - `$fillable = ['name', 'category']`
  - Relationship: `variants(): HasMany` → `ProductVariant` (`product_id`)
- **`ProductVariant`**:
  - `$fillable = ['product_id', 'sku', 'barcode', 'name', 'base_unit_name', 'cost_price', 'sale_price', 'reorder_point', 'attributes', 'images', 'is_active']`
  - `$casts = ['cost_price' => 'decimal:4', 'sale_price' => 'decimal:4', 'attributes' => 'array', 'images' => 'array', 'is_active' => 'boolean']`
  - Relationships: `product(): BelongsTo`, `unitConversions(): HasMany`, `stockMovements(): HasMany` (`product_variant_id`), `transferRequisitionItems(): HasMany` (`product_variant_id`)
  - Methods: `onHandQuantity(int $warehouseId): int`, `reservedQuantity(int $warehouseId): int`, `availableQuantity(int $warehouseId): int`.
- **`ProductVariantUnitConversion`**:
  - `$fillable = ['product_variant_id', 'unit_name', 'base_unit_ratio', 'is_default_purchase', 'is_default_transfer']`
  - Relationship: `productVariant(): BelongsTo` → `ProductVariant` (`product_variant_id`)
- **`StockMovement`**:
  - `$fillable = ['product_variant_id', 'warehouse_id', 'type', 'quantity', 'unit_name_used', 'unit_ratio_used', 'related_movement_id', 'reference_type', 'reference_id', 'reference_code', 'created_by']`
  - Relationships: `productVariant(): BelongsTo`, `warehouse(): BelongsTo`, `creator(): BelongsTo` (`created_by`), `relatedMovement(): BelongsTo`
- **`TransferRequisition`**:
  - `$fillable = ['reference_code', 'from_warehouse_id', 'to_warehouse_id', 'status', 'requested_by', 'approved_by', 'dispatched_by', 'received_by', 'requested_at', 'approved_at', 'dispatched_at', 'completed_at', 'notes']`
  - Relationships: `fromWarehouse(): BelongsTo`, `toWarehouse(): BelongsTo`, `requestedBy(): BelongsTo`, `items(): HasMany` → `TransferRequisitionItem` (`transfer_requisition_id`)
  - Observers / Boot Listener: Ensure `deleting` observer automatically returns locked reservations if status is `confirmed` or `dispatched`.
- **`TransferRequisitionItem`**:
  - `$fillable = ['transfer_requisition_id', 'product_variant_id', 'substitute_product_variant_id', 'requested_unit_name', 'requested_unit_ratio', 'requested_qty', 'requested_base_qty', 'approved_unit_name', 'approved_unit_ratio', 'approved_qty', 'approved_base_qty', 'shipped_base_qty', 'received_good_base_qty', 'received_damaged_base_qty', 'received_qty', 'notes']`
  - Relationships: `transferRequisition(): BelongsTo`, `productVariant(): BelongsTo` (`product_variant_id`), `substituteProductVariant(): BelongsTo` (`substitute_product_variant_id`)
- **`InTransit`**:
  - `$fillable = ['transfer_requisition_id', 'transfer_requisition_item_id', 'product_variant_id', 'dispatched_base_qty', 'dispatched_at', 'status']`
  - Relationships: `transferRequisition(): BelongsTo`, `transferRequisitionItem(): BelongsTo`, `productVariant(): BelongsTo`
- **`LossLedger`**:
  - `$fillable = ['transfer_requisition_id', 'transfer_requisition_item_id', 'product_variant_id', 'warehouse_id', 'lost_base_qty', 'damaged_base_qty', 'unit_cost_price', 'total_financial_loss', 'loss_category', 'recorded_by', 'recorded_at']`
  - `$casts = ['unit_cost_price' => 'decimal:4', 'total_financial_loss' => 'decimal:4']`
  - Relationships: `transferRequisition(): BelongsTo`, `transferRequisitionItem(): BelongsTo`, `productVariant(): BelongsTo`, `warehouse(): BelongsTo`, `recorder(): BelongsTo`
- **`User`**:
  - `$casts = ['role' => UserRole::class]`
  - Relationships: `warehouses(): BelongsToMany` (`user_warehouse`)
  - Helpers: `isAdmin(): bool`, `isAuditor(): bool`, `isBranchManager(): bool`, `canAccessWarehouse(Warehouse $warehouse): bool`

---

### Phase 3: Database Factories Audit & Repair
Inspect all files in `database/factories/` and refactor definitions to match updated models and foreign keys:
- `ProductFactory`: generates `name` and `category`. (Remove `sku` / `reorder_point`).
- `ProductVariantFactory`: generates `product_id` (`Product::factory()`), `sku` (unique), `barcode`, `name`, `base_unit_name`, `cost_price` (e.g. `10.5000`), `sale_price` (e.g. `15.0000`), `reorder_point` (e.g. `10`).
- `ProductVariantUnitConversionFactory`: generates `product_variant_id`, `unit_name`, `base_unit_ratio`.
- `StockMovementFactory`: generates `product_variant_id`, `warehouse_id`, `type`, `quantity` (signed), `unit_name_used`, `unit_ratio_used`.
- `TransferRequisitionFactory`: generates `reference_code`, `from_warehouse_id`, `to_warehouse_id`, `status`, `requested_by`.
- `TransferRequisitionItemFactory`: generates `transfer_requisition_id`, `product_variant_id`, `requested_unit_name`, `requested_unit_ratio`, `requested_qty`, `requested_base_qty`.
- `InTransitFactory`: generates `transfer_requisition_id`, `transfer_requisition_item_id`, `product_variant_id`, `dispatched_base_qty`, `dispatched_at`, `status`.
- `LossLedgerFactory`: generates `transfer_requisition_id`, `transfer_requisition_item_id`, `product_variant_id`, `warehouse_id`, `lost_base_qty`, `unit_cost_price`, `total_financial_loss`.

---

### Phase 4: Pest Unit & Model Test Refactoring
Inspect every test file in `tests/Unit/` and `tests/Feature/Models/`:
1. Fix any references to old foreign key names (`variant_id` → `product_variant_id`, `requisition_id` → `transfer_requisition_id`).
2. Fix any tests trying to query or assert against a physical `warehouse_stock` or `product_variant_prices` table.
3. Update assertions testing `ProductVariant` methods:
   - Test `onHandQuantity()` sums `stock_movements.quantity` for that `product_variant_id` and `warehouse_id`.
   - Test `reservedQuantity()` correctly sums `approved_base_qty` for active confirmed/dispatched requisitions.
   - Test `availableQuantity()` equals `onHandQuantity() - reservedQuantity()`.
4. Update assertions testing `TransferRequisition` soft-deletion observer releasing reserved stock.
5. Update tests for `User::canAccessWarehouse()`.

---

### Phase 5: Test Execution & Diagnostic Repair Loop
1. Run fresh database migrations and seeders:
   ```bash
   php artisan migrate:fresh --seed
   ```
2. Run the Pest test suite:
   ```bash
   vendor/bin/pest
   ```
3. If any test fails:
   - Read the traceback error message carefully.
   - Identify whether the failure is due to a migration mismatch, model relationship/cast error, factory array discrepancy, or outdated test assertion.
   - Refactor the code on disk to fix the underlying issue.
   - Re-run `vendor/bin/pest` until 100% of unit, feature, and model tests pass cleanly with zero errors or warnings.

---

## 📋 Required Final Output

Once execution is complete, report:
1. **Summary of Schema Fixes**: List of migrations, models, and factories modified.
2. **Pest Test Results**: Full output of `vendor/bin/pest` confirming 100% passing tests.
3. **Verification Confirmation**: Confirmation that `warehouse_stock` and `product_variant_prices` tables are zeroed out and derived stock math functions perfectly.
```

---

### 🔍 Summary of the 5-Pass Audit Behind This Prompt

1. **Pass 1 (Migration Alignment)**: Corrects column placements (shifting `sku` and `reorder_point` to variants) and enforces exact plural table and FK names.
2. **Pass 2 (Model & Method Realignment)**: Implements dynamic query-time stock accessors (`onHandQuantity`, `reservedQuantity`, `availableQuantity`) directly on `ProductVariant` with zero DB table caching.
3. **Pass 3 (Factory Integrity)**: Ensures all synthetic seed data generated during testing aligns with the updated foreign key relationships and high-precision `decimal(15,4)` data types.
4. **Pass 4 (Test Logic Refactoring)**: Replaces outdated test assertions expecting static `WarehouseStock` rows with pure derived ledger assertions.
5. **Pass 5 (Execution Diagnostics)**: Establishes an iterative `migrate:fresh` and `vendor/bin/pest` repair loop to systematically resolve any runtime tracebacks.

***

💡 **Would you like me to write a corresponding Playwright E2E browser repair prompt to audit and fix your front-end wizard and scan-to-receive browser specs as well?**