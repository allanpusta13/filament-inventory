# Plan 06-01: WarehouseResource with admin-only access and user assignment

## Plan

Create Filament v5 WarehouseResource with admin-only access and user assignment via multi-select.

### Files
- `app/Filament/Resources/Warehouses/WarehouseResource.php` — resource with canViewAny/canCreate/canEdit/canDelete
- `app/Filament/Resources/Warehouses/Schemas/WarehouseForm.php` — form with name, location, is_active, users multi-select
- `app/Filament/Resources/Warehouses/Tables/WarehousesTable.php` — table with name, location, is_active, users_count
- `app/Filament/Resources/Warehouses/Pages/ListWarehouses.php`
- `app/Filament/Resources/Warehouses/Pages/CreateWarehouse.php`
- `app/Filament/Resources/Warehouses/Pages/EditWarehouse.php`
- `tests/Feature/WarehouseResourceTest.php` — 15 tests

### Key Implementation Details
- All can* methods use `auth()->user()?->isAdmin() ?? false` for null-safety
- Users assigned via `Select::make('users')->relationship('users', 'name')->multiple()`
- Table shows users_count via `->counts('users')`
- Tests cover admin access, staff denial, CRUD operations, user assignment, and validation
