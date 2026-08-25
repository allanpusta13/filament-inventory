# Plan 05-01: ProductResource with form, table, and pages

## Plan

Create Filament v5 ProductResource following existing UserResource pattern.

### Files
- `app/Models/Product.php` — add `totalQuantity()` method
- `app/Filament/Resources/Products/ProductResource.php` — resource with canCreate/canEdit/canDelete
- `app/Filament/Resources/Products/Schemas/ProductForm.php` — form with sku, name, category, unit, reorder_point
- `app/Filament/Resources/Products/Tables/ProductsTable.php` — table with computed total_stock column, low stock highlighting
- `app/Filament/Resources/Products/Pages/ListProducts.php`
- `app/Filament/Resources/Products/Pages/CreateProduct.php`
- `app/Filament/Resources/Products/Pages/EditProduct.php`
- `tests/Feature/ProductResourceTest.php` — 21 tests

### Key Implementation Details
- `totalQuantity()` delegates to `StockMovement::sum('quantity')`
- Table uses `state()` closure for computed total_stock column
- `recordClasses()` highlights rows where total stock <= reorder point
- `canCreate/canEdit/canDelete` use `auth()->user()?->isAdmin() ?? false` for null-safety
- Tests use `actingAs($this->admin)` for authorization tests

## Council Summary

No council review needed — follows established UserResource pattern exactly.
