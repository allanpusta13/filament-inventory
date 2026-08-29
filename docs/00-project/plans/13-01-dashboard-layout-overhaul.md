# Dashboard Layout Overhaul Plan

## Council Approval Status
**APPROVED** — All 5 councils (UI/UX, Frontend Architecture, Accessibility, Copywriting, Operations) approved with corrections.

## Council Summary
- **UI/UX & Visual Aesthetics Council:** APPROVED. Corrected that section headers ARE already registered (initial plan had this wrong). Required RecentStockActivityWidget column span fix and chart height normalization.
- **Frontend Architecture Council:** APPROVED. Confirmed 12-column grid math is correct. Verified warehouse filter contract holds. Noted AccountWidget needs explicit sort value.
- **Accessibility & Usability Council:** APPROVED. Required chart canvas ARIA labels (WCAG 1.1.1), table keyboard scroll accessibility (WCAG 2.1.1), and section header icon contrast verification (WCAG 1.4.11).
- **Copywriting & De-duplication Council:** APPROVED. Confirmed ProductCatalogWidget removal is clean (ProductResource exists). WarehouseCapacityWidget data covered by StockByWarehouseWidget + RecentStockActivityWidget. Terminology consistent.
- **Operations & Logistics Domain Council:** APPROVED. Required adding transferStockAction() to QuickActionsWidget (already exists in StockActions trait). Confirmed warehouse_staff dashboard 403 is intentional.

---

## Scope

Fix the visually broken dashboard layout by:
1. Fixing responsive column spans so widgets pack correctly in the 12-column grid
2. Removing redundant widgets that don't fit the approved layout
3. Adding a missing operational action (Transfer)
4. Improving accessibility (chart ARIA, table keyboard scroll)
5. Fixing chart height consistency and table overflow

## What Will Change

### 1. Fix Widget Column Spans
- `FastMovingStockChart`: Change `lg => 'full'` to `lg => 7` (pair with RecentStockActivity)
- `RecentStockActivityWidget`: Change `lg => 'full'` to `lg => 5` (pair with FastMovingStockChart)
- Normalize chart `maxHeight` to `300px` across all chart widgets for visual consistency

### 2. Remove Redundant Widgets
- Remove `ProductCatalogWidget` from `Dashboard::getWidgets()` (duplicate of ProductResource, not in approved layout)
- Remove `WarehouseCapacityWidget` from `Dashboard::getWidgets()` (data covered by StockByWarehouseWidget + RecentStockActivityWidget)

### 3. Add Transfer Action
- Add `$this->transferStockAction()` to `QuickActionsWidget::getActions()` (core multi-warehouse operation)

### 4. Improve Accessibility
- Add `role="img"` and `aria-label` to chart canvases
- Add keyboard-accessible table overflow containers (`role="region"`, `tabindex="0"`)
- Verify section header icon contrast meets WCAG 1.4.11 (3:1 minimum)

### 5. Fix Theme CSS
- Remove `.fi-chart-widget { min-height: 400px }` (conflicts with widget `maxHeight`)
- Add `.fi-ta-table-container` horizontal scroll for narrow viewports
- Add `@media (prefers-reduced-motion: reduce)` for chart animations

## Exact Files to Modify

| File | Change |
|------|--------|
| `app/Filament/Pages/Dashboard.php` | Remove ProductCatalogWidget and WarehouseCapacityWidget from getWidgets() |
| `app/Filament/Widgets/FastMovingStockChart.php` | Change `lg => 'full'` to `lg => 7`, normalize maxHeight to `300px` |
| `app/Filament/Widgets/RecentStockActivityWidget.php` | Change `lg => 'full'` to `lg => 5` |
| `app/Filament/Widgets/QuickActionsWidget.php` | Add transferStockAction() to getActions() |
| `app/Filament/Widgets/StockMovementTrendChart.php` | Verify maxHeight is `300px` (already correct) |
| `app/Filament/Widgets/CategoryStockChart.php` | Verify maxHeight is `300px` (already correct) |
| `resources/css/filament/admin/theme.css` | Fix chart min-height, add table overflow, add reduced-motion, add chart ARIA styles |
| `tests/Feature/DashboardWidgetTest.php` | Update tests for removed widgets, add transfer action test |

## Expected Behavior After Change

**Layout Structure (admin view):**
| Row | Widget(s) | Desktop Span |
|-----|-----------|-------------|
| 1 | Warehouse Filter (header) | full |
| 2 | Performance Overview Header | full |
| 3 | Stats Overview (4 KPI cards) | full |
| 4 | Operations & Alerts Header | full |
| 5 | Low Stock Alerts / Common Actions | 7 / 5 |
| 6 | Warehouse Status Header (admin only) | full |
| 7 | Warehouse Inventory Summary | full |
| 8 | Inventory Analytics Header | full |
| 9 | Stock Inflow vs Outflow / Category Distribution | 7 / 5 |
| 10 | Planning & Activity Header | full |
| 11 | Top 10 High-Turnover / Recent Stock Movements | 7 / 5 |
| 12 | Account | full |

**Removed widgets:** ProductCatalogWidget, WarehouseCapacityWidget
**Added action:** Transfer Stock in Quick Actions

**Accessibility:**
- Chart canvases have ARIA labels for screen readers
- Tables have keyboard-scrollable overflow containers
- Reduced motion respected for chart animations

## Scope Limitations

This plan does NOT cover:
- Warehouse staff dashboard access (remains admin-only, intentional per existing ADR)
- Creating new widgets or business logic
- Changing database schema or queries
- Modifying role-based access control beyond widget removal
