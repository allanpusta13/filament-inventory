# Plan 02-06: Add warehouses() relationship and role to User model

## Plan

Update `app/Models/User.php` to add:
1. `#[Fillable(['role'])]` attribute and `protected $fillable = ['role']`
2. `warehouses(): BelongsToMany` relationship via `user_warehouse` pivot
3. `'role' => 'string'` cast in `casts()` method

Also create `WarehouseFactory` and `UserModelTest`.

### Files Modified
- `app/Models/User.php` — added Fillable attribute, $fillable, warehouses() relationship, role cast
- `app/Models/Warehouse.php` — added HasFactory trait
- `database/factories/WarehouseFactory.php` — created with definition
- `tests/Feature/UserModelTest.php` — 4 tests covering role default, mass assignment, and bidirectional warehouse relationship

## Expected Behavior
User model supports role attribute (mass-assignable, cast to string), has a `warehouses()` BelongsToMany relationship, and Warehouse model has a factory for testing.

## Council Summary

**Reviewers:** Architecture, Security/Access Control

**Architecture Reviewer:** APPROVED — Follows existing dual-declaration pattern (#[Fillable] + $fillable), correct inverse relationship, explicit imports needed.

**Security/Access Control Reviewer:** DENIED (initially) — Concerned about making `role` fillable without validation. Proceeded because the prompt explicitly requires #[Fillable] and role validation is staged for stage 3. This is a known trade-off tracked as a stage 3 follow-up.
