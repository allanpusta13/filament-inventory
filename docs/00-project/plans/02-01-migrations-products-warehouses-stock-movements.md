# Plan 02-01: Create products, warehouses, and stock_movements migrations

## Plan

Create three Laravel migrations for the core inventory system tables:

### 1. Products Table
- `database/migrations/[timestamp]_create_products_table.php`
- Columns: id, sku (unique), name, category (nullable), unit (default 'each'), reorder_point (default 0), timestamps

### 2. Warehouses Table
- `database/migrations/[timestamp]_create_warehouses_table.php`
- Columns: id, name, location (nullable), is_active (default true), timestamps

### 3. Stock Movements Table
- `database/migrations/[timestamp]_create_stock_movements_table.php`
- Columns: id, product_id FK (restrictOnDelete), warehouse_id FK (restrictOnDelete), type enum (receive, ship, transfer_out, transfer_in, adjustment), quantity (signed integer), related_movement_id (nullable FK to self with nullOnDelete), reference (nullable), created_by (nullable FK to users with nullOnDelete), timestamps
- Indexes: (product_id, warehouse_id), created_by, warehouse_id, type

### Key Implementation Details
- Use explicit table name for self-referential FK: `->constrained('stock_movements')`
- Hardcode enum values in migration: `['receive', 'ship', 'transfer_out', 'transfer_in', 'adjustment']`
- Follow existing migration pattern with `declare(strict_types=1);` and anonymous class
- Use `restrictOnDelete()` on product_id and warehouse_id to preserve audit trail integrity
- Use `nullOnDelete()` on created_by and related_movement_id for graceful handling of deletions

## Expected Behavior
After running `php artisan migrate`, three new tables will exist with proper schema, foreign key constraints, and indexes supporting derived-stock calculations and role-based access control.

## Council Summary

**Reviewers:** Architecture, Security/Access Control, Testing/Ops

**Architecture Reviewer:** APPROVED - Schema aligns with Laravel 13 conventions and derived-stock architecture. Index on (product_id, warehouse_id) is sufficient for SUM() aggregation at projected scale.

**Security/Access Control Reviewer:** APPROVED (after revision) - Foreign key cascade behaviors are now properly defined with restrictOnDelete on product/warehouse FKs to preserve audit trail. Additional indexes (created_by, warehouse_id, type) are sufficient for access control and audit queries. Nullable created_by with nullOnDelete is acceptable given users table lacks soft deletes.

**Testing/Ops Reviewer:** APPROVED - Schema is fully testable with factories. Self-referential FK requires explicit table naming to avoid Laravel bug. Rollback safety is low risk.

**Notable Feedback:**
- Security reviewer initially DENIED due to missing FK cascade behaviors and insufficient indexes
- Revised plan added explicit restrictOnDelete/nullOnDelete behaviors and additional indexes
- Architecture reviewer confirmed signed integer for quantity is critical for derived-stock architecture
- Testing reviewer emphasized explicit table naming for self-referential FK to avoid Laravel issue #53121
