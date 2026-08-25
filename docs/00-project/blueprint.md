# Multi-Warehouse Inventory System — Complete Blueprint
**Stack:** Laravel 13 + FilamentPHP v5 | **Scale:** ~few hundred SKUs, <10 warehouses

---

## 1. Final Data Model

### `products`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| sku | string, unique | |
| name | string | |
| category | string, nullable | |
| unit | string, default 'each' | |
| reorder_point | integer, default 0 | global default threshold |
| timestamps | | |

### `warehouses`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| name | string | |
| location | string, nullable | |
| is_active | boolean, default true | |
| timestamps | | |

### `stock_movements` (source of truth — no separate `inventory` table)
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| product_id | FK → products | |
| warehouse_id | FK → warehouses | |
| type | enum: receive, ship, transfer_out, transfer_in, adjustment | |
| quantity | integer, **signed** | + for in, − for out |
| related_movement_id | FK → stock_movements, nullable | links transfer pairs |
| reference | string, nullable | PO#, order#, note |
| created_by | FK → users, nullable | who recorded it |
| timestamps | | |

**Index:** `(product_id, warehouse_id)` for fast `SUM()` lookups.

> **Current stock = `SUM(quantity)` grouped by product_id + warehouse_id.** Never stored directly. No cache table, no drift, no rebuild jobs needed at this scale.

### `users` (extended)
| column | type | notes |
|---|---|---|
| role | string/enum, default 'warehouse_staff' | 'admin' or 'warehouse_staff' |

### `user_warehouse` (pivot)
| column | type |
|---|---|
| user_id | FK → users |
| warehouse_id | FK → warehouses |

---

## 2. Access Rules (locked in)

- **Admin:** sees all warehouses, all inventory, all movements. Manages products, warehouses, users.
- **Warehouse staff:** sees only their assigned warehouse(s). Sees incoming (receive, transfer_in) and outgoing (ship, transfer_out) movements for their warehouse, with the counterpart warehouse shown on transfers (name only, not that warehouse's full inventory).
- No package — `role` column + `UserRole` enum + `isAdmin()` / `canAccessWarehouse()` helper methods on `User`.

---

## 3. Core Service Logic

All quantity changes flow through **one service class** — `InventoryService`. Filament resources never touch quantities directly.

```
InventoryService::recordMovement($productId, $warehouseId, $type, $quantity, $reference, $relatedMovementId)
InventoryService::ship($productId, $warehouseId, $quantity, $reference)      // guards against negative stock
InventoryService::transfer($productId, $fromWarehouseId, $toWarehouseId, $quantity, $reference)
InventoryService::currentQuantity($productId, $warehouseId)
InventoryService::totalQuantity($productId)                                  // across all warehouses
```

Transfers = two linked movement rows (`transfer_out` + `transfer_in`) in one DB transaction.

---

## 4. Filament v5 File Structure (per resource)

```
app/Filament/Resources/{Name}/
├── {Name}Resource.php
├── Pages/
│   ├── List{Name}s.php
│   ├── Create{Name}.php
│   └── Edit{Name}.php
├── Schemas/
│   └── {Name}Form.php      (Filament\Schemas\Schema)
└── Tables/
    └── {Name}sTable.php
```

Generate each with:
```bash
php artisan make:filament-resource {Name} --generate --view
```

Resources needed: `Product`, `Warehouse`, `StockMovement` (+ a dashboard widget for low stock, no separate `Inventory` resource — the "current stock" view is a grouped-aggregate table built from `stock_movements`).

---

## 5. Build Order (11 stages)

1. Fresh Laravel 13 project + Filament v5 install + admin panel setup
2. Migrations + models (products, warehouses, stock_movements, user_warehouse, users.role)
3. User role enum + `isAdmin()` / `canAccessWarehouse()` + warehouse assignment relation
4. `InventoryService` class (recordMovement, ship, transfer, currentQuantity, totalQuantity)
5. `ProductResource` (admin-manages, everyone can view)
6. `WarehouseResource` (admin-only)
7. `StockMovementResource` — base CRUD scaffold, read-only table (no manual edit/delete on log rows)
8. Custom header actions on StockMovementResource: Receive, Ship, Transfer, Adjustment — wired to `InventoryService`
9. Query scoping for warehouse staff (movements + any "current stock" views) + counterpart-warehouse display on transfers
10. Current-stock table (grouped aggregate view) + low-stock dashboard widget, both scoped by role
11. User-warehouse assignment UI (admin manages which staff sees which warehouse) + polish (filters, badges, search)

---

## 6. Build Prompts

The step-by-step build prompts corresponding to the 11 stages above are kept
as separate files (see the `prompts/` directory) so they can be copied one at
a time without pasting this whole blueprint into each conversation.
