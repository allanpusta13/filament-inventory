# Plan 02-05: Add role column migration + user_warehouse pivot migration

## Plan

Create two Laravel migrations to complete the schema required by Prompt 02:

### 1. Add Role Column to Users
- `database/migrations/[timestamp]_add_role_to_users_table.php`
- Column: `string('role')->default('warehouse_staff')`
- Follow existing migration pattern: `declare(strict_types=1);`, anonymous class

### 2. Create user_warehouse Pivot Table
- `database/migrations/[timestamp]_create_user_warehouse_table.php`
- Columns: `foreignId('user_id')->constrained()->cascadeOnDelete()`, `foreignId('warehouse_id')->constrained()->cascadeOnDelete()`
- Composite primary key on (user_id, warehouse_id)
- Follow existing migration pattern

### Key Implementation Details
- Role column uses plain string (not enum) — SQLite ignores enum constraints anyway, and PHP enum cast will be added on the model in Task 2
- CascadeOnDelete on pivot is appropriate (assignments are meaningless if parent is deleted, unlike data-bearing FKs in stock_movements)
- User model changes (#[Fillable], warehouses() relationship, role cast) are in Task 2, not this task

## Expected Behavior
After running `php artisan migrate`, the users table will have a `role` column defaulting to 'warehouse_staff', and a `user_warehouse` pivot table will exist connecting users to warehouses.

## Council Summary

**Reviewers:** Architecture, Security/Access Control, Testing/Ops

**Architecture Reviewer:** APPROVED — CascadeOnDelete correct for pivot, string column acceptable for staged build, pattern compliance confirmed.

**Security/Access Control Reviewer:** APPROVED — Least-privilege default ('warehouse_staff'), staged approach acceptable, composite PK prevents duplicate assignments.

**Testing/Ops Reviewer:** DENIED (initially) — Raised that plan mentions testing via relationships not yet defined. Revised plan clarifies this is migration-only; model changes are Task 2. SQLite compatibility confirmed.
