# Plan 12-03: Dashboard Interactive Charts

## Plan

Add 3 interactive Chart.js widgets to the Filament dashboard: stock movement trend line chart, category stock valuation donut chart, and fast-moving items bar chart. These provide visual analytics that complement the existing KPI cards and data tables.

### Key Findings
- Filament v5 has built-in `ChartWidget` class supporting line, bar, doughnut, pie via Chart.js
- No `laravel-trend` package installed — time-series queries built manually
- Current dashboard: QuickActions → StatsOverview → LowStock → RecentActivity → Warehouses → Account
- No chart visualizations exist yet — only a small sparkline in StatsOverview

### Files to Create
1. `app/Filament/Widgets/StockMovementTrendChart.php` — Line chart: daily stock inflow vs outflow (last 30 days)
2. `app/Filament/Widgets/CategoryStockChart.php` — Doughnut chart: stock quantity breakdown by product category
3. `app/Filament/Widgets/FastMovingStockChart.php` — Horizontal bar chart: top 10 products by movement frequency

### Files to Modify
1. `app/Filament/Pages/Dashboard.php` — Register the 3 new chart widgets

### Chart Design Details

#### 1. StockMovementTrendChart (Line/Area)
- **Type:** `line` with `fill: true` (area chart)
- **Data:** Last 30 days, two datasets:
  - "Stock In" (receive + transfer_in) — emerald/green
  - "Stock Out" (ship + transfer_out) — rose/red
- **Query:** Group stock_movements by `DATE(created_at)`, sum quantities per direction
- **X-axis:** Dates (last 30 days)
- **Y-axis:** Quantity
- **Role-scoped:** Non-admins filtered to their warehouses

#### 2. CategoryStockChart (Doughnut)
- **Type:** `doughnut`
- **Data:** Total quantity per product category
- **Query:** Join products + stock_movements, group by `products.category`, sum quantity
- **Colors:** Distinct palette per category (Indigo, Emerald, Amber, Rose, Blue, etc.)
- **Fallback:** Products with null category shown as "Uncategorized"

#### 3. FastMovingStockChart (Horizontal Bar)
- **Type:** `bar` (horizontal via `indexAxis: 'y'`)
- **Data:** Top 10 products by total movement count (most active)
- **Query:** Count stock_movements per product_id, join product name, take top 10
- **Color:** Gradient from emerald (high activity) to gray (low)
- **Role-scoped:** Non-admins filtered to their warehouses

### Dashboard Layout (After)
```
Row 0: QuickActionsWidget
Row 1: StatsOverviewWidget (4 KPI cards)
Row 2: StockMovementTrendChart (full width)
Row 3: CategoryStockChart | FastMovingStockChart (50/50 split)
Row 4: LowStockAlertWidget (full width)
Row 5: RecentStockActivityWidget (full width)
Row 6: StockByWarehouseWidget (full width)
Row 7: AccountWidget
```

### Key Implementation Details
- All charts extend `Filament\Widgets\ChartWidget`
- Charts use `getPollingInterval()` set to `'60s'` for live updates
- Queries use aggregate functions (SUM, COUNT) with DATE grouping — no N+1
- Empty data handled gracefully (empty chart with "No data" message)
- All queries role-scoped via `auth()->user()->isAdmin()` and `auth()->user()->warehouses`

### Not in Scope
- Purchase Order status charts (no PO model yet)
- Interactive drill-down on charts (static charts only)
- Chart.js plugins or custom tooltips beyond defaults

### Testing
- Test each chart widget renders without error (`livewire()->test()->assertOk()`)
- Test chart data structure is valid (datasets and labels present)
- All existing 130 tests must continue to pass
