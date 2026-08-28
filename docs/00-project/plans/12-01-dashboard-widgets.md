# Plan 12-01: Custom Dashboard Widgets

## Plan

Transform the default Filament dashboard into a high-converting enterprise inventory dashboard with 5 custom widgets and a custom dashboard page.

### Key Findings
- Current dashboard: default Filament page + 1 LowStockWidget + 2 default widgets (AccountWidget, FilamentInfoWidget)
- No KPI metrics, no activity timeline, no warehouse stock breakdown
- Theme is nearly stock Filament — no custom color system
- Role-based scoping needed for non-admin users

### Files to Create
1. `app/Filament/Pages/Dashboard.php` — Custom dashboard page replacing default
2. `app/Filament/Widgets/StatsOverviewWidget.php` — 4 KPI stat cards
3. `app/Filament/Widgets/RecentStockActivityWidget.php` — Stock movement timeline
4. `app/Filament/Widgets/StockByWarehouseWidget.php` — Warehouse stock breakdown
5. `app/Filament/Widgets/LowStockAlertWidget.php` — Enhanced low stock alerts (replaces existing LowStockWidget)
6. Update `app/Providers/Filament/AdminPanelProvider.php` — Register new widgets

### Files to Modify
- `app/Providers/Filament/AdminPanelProvider.php` — Add widget registrations

### Widgets Design

#### 1. StatsOverviewWidget (4 KPI Cards)
- **Total Items**: Count of unique products in catalog
- **Total Stock Value**: Sum of stock across all warehouses (quantity × unit cost — using quantity as proxy since no unit_cost column)
- **Low Stock Alerts**: Count of products at or below reorder_point with stock > 0
- **Out-of-Stock**: Count of products with zero or negative total stock
- Each card has a distinct icon and color (emerald for value, amber for low stock, rose for out-of-stock, blue for total items)
- Pulsing dot indicator on Low Stock and Out-of-Stock when count > 0

#### 2. RecentStockActivityWidget
- Table showing last 10 stock movements
- Columns: Product, Warehouse, Type (badge), Quantity (colored), Reference, Date
- Role-scoped: non-admins see only their assigned warehouses
- Quick-action header button to record new movement

#### 3. StockByWarehouseWidget
- Visual cards showing stock levels per warehouse
- Each warehouse card shows: name, location, total items, total quantity
- Admin sees all warehouses; staff sees only assigned

#### 4. LowStockAlertWidget (Enhanced)
- Replaces existing LowStockWidget
- Shows products at or below reorder_point
- Added: product SKU, warehouse name, current stock, reorder point
- Color-coded severity: amber for low stock, rose for critical (stock = 0)

### Key Implementation Details
- All queries use aggregate functions (SUM, COUNT) for performance
- Role scoping via `auth()->user()->isAdmin()` and `auth()->user()->warehouses`
- Widgets use `->sort()` for ordering on dashboard
- No N+1 queries — eager loading where needed
- Tests: Pest feature tests for each widget

### Expected Behavior After Change
- Dashboard displays 4 KPI stat cards at top
- Below: Recent Activity table, Warehouse Stock cards, Low Stock Alert table
- All data is role-scoped for warehouse staff
- Widgets are responsive and visually distinct
- Default AccountWidget and FilamentInfoWidget removed

### Not in Scope
- Theme/color system changes (separate task)
- Resource table visual enhancements (separate task)
- Custom Blade views (using Filament's built-in widget rendering)
