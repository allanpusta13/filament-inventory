# Plan 03-01: UserRole enum, isAdmin(), canAccessWarehouse()

## Plan

Implement native role system for the Laravel inventory app:

### 1. UserRole Enum
- `app/Enums/UserRole.php` — backed enum with `Admin = 'admin'` and `WarehouseStaff = 'warehouse_staff'`

### 2. User Model Updates
- Change `'role' => 'string'` cast to `'role' => UserRole::class`
- Add `isAdmin(): bool` — `$this->role === UserRole::Admin`
- Add `canAccessWarehouse(Warehouse $warehouse): bool` — admin always true, warehouse_staff checks `$this->warehouses->contains($warehouse)`

### 3. Tests
- 9 tests in UserModelTest covering: default role, mass assignment, warehouse relationships, isAdmin, canAccessWarehouse (admin, assigned, unassigned)

## Council Summary

**Reviewers:** Architecture, Security/Access Control

**Architecture Reviewer:** DENIED (initially) — Suggested moving canAccessWarehouse to a WarehousePolicy. Proceeded because the prompt explicitly requests model methods. Policy approach noted as future enhancement.

**Security/Access Control Reviewer:** APPROVED — Confirmed model helpers are the correct foundation for later policy delegation. No caching needed at this stage.
