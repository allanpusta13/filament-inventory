# Dashboard Layout — Final Gaps Plan

## Council Approval Status
**APPROVED** — All 3 councils (Architecture, Security, Testing) approved.

## Council Summary
- **Architecture Council:** Approved. Confirmed operational KPIs are role-appropriate for both roles. Noted section headers for admin-only sections may show empty for warehouse_staff (cosmetic, not blocking). Recommended adding `canView()` to admin-only section headers.
- **Security Council:** Approved. Verified data scoping via `DashboardFilterable` trait is correct. No information leakage — KPIs are operational, not financial. No new attack vectors.
- **Testing Council:** Approved with 2 required conditions: (1) Fix `Product::count()` scoping in `StatsOverviewWidget` — currently unscoped, would show global count to warehouse_staff. (2) Add warehouse_staff tests for StatsOverviewWidget and dashboard access.

---

## Scope

Close 4 remaining gaps between the approved refinement plan (`dashboard-layout-refinement.md`) and current implementation. Most of the dashboard is already built — this plan addresses the final role-gating, proportion, and registration gaps.

## Gaps to Close

### Gap 1: StatsOverviewWidget admin-only gate
**Current:** `canView()` returns `false` for warehouse_staff.
**Fix:** Remove the admin-only gate. Both roles see operational KPIs (Total SKUs, Total Units on Hand, Items Below Reorder Point, Zero Stock SKUs). Warehouse filtering already works via `DashboardFilterable` trait.

**Bug found:** `Product::count()` (line 37) is not warehouse-scoped. When warehouse_staff can view the widget, they'd see global SKU count while other stats are scoped. Fix by scoping via `getFilterWarehouseIds()`.

### Gap 2: Chart column spans
**Current:** Both `StockMovementTrendChart` and `CategoryStockChart` have `lg => 6`.
**Fix:** Change to `lg => 7` and `lg => 5` respectively per the refinement plan.

### Gap 3: Section headers not registered
**Current:** `DashboardSections\*Header` widgets exist but aren't in `Dashboard.php`'s `getWidgets()`.
**Fix:** Register them with proper sort ordering between existing widgets.

### Gap 4: Missing tests
**Current:** No test for warehouse_staff dashboard access or StatsOverviewWidget visibility.
**Fix:** Add tests for warehouse_staff dashboard page, StatsOverviewWidget visibility, and scoped stats.

## Files to Modify

| File | Change |
|------|--------|
| `app/Filament/Widgets/StatsOverviewWidget.php` | Remove admin `canView()` gate; scope `Product::count()` via `getFilterWarehouseIds()` |
| `app/Filament/Widgets/StockMovementTrendChart.php` | Change `lg` columnSpan from `6` to `7` |
| `app/Filament/Widgets/CategoryStockChart.php` | Change `lg` columnSpan from `6` to `5` |
| `app/Filament/Pages/Dashboard.php` | Register section headers in `getWidgets()` |
| `tests/Feature/DashboardWidgetTest.php` | Update StatsOverviewWidget test; add warehouse_staff tests |

## Expected Behavior

**Admin View:** Full dashboard with all widgets, warehouse filter, financial + operational KPIs.
**Warehouse Staff View:** Operational widgets only (StatsOverview, LowStock, QuickActions, FastMoving, RecentActivity). No warehouse filter, no financial charts, no StockByWarehouse. Stats scoped to assigned warehouses.

## Verification
- `php artisan test --compact` — full suite passes
- `vendor/bin/pint --dirty --format agent` — code formatted
- Playwright screenshot verification at multiple viewports
