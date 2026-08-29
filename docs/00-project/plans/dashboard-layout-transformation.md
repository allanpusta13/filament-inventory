# Dashboard Layout Transformation Plan

## Council Approval Status
**APPROVED** - All 5 councils (UI/UX, Frontend Architecture, Accessibility, Copywriting, Operations) have approved this revised plan.

## Council Summary
- **UI/UX & Visual Aesthetics Council:** Approved after addressing typography overuse, color contrast, missing interactive states, and asymmetric chart layout
- **Frontend Architecture Council:** Approved after adding responsive breakpoint configuration, CLS prevention, Chart.js bundling strategy, and widget column span specifications
- **Accessibility & Usability Council:** Approved after implementing WCAG AAA compliance, chart accessibility, touch target sizing, color independence, and keyboard navigation
- **Copywriting & Layout Clarity Council:** Approved after standardizing terminology, removing parenthetical clutter, and adding clear section headers
- **Operations & Logistics Domain Council:** Approved after reorganizing to operations-first hierarchy with immediate action zones

Notable feedback incorporated:
- Operations council demanded moving Low Stock Alerts from bottom to Row 2 for immediate action access
- UI/UX council restricted monospace fonts to SKUs only and improved color contrast
- Accessibility council required WCAG AAA (7:1) contrast and comprehensive chart accessibility
- Copywriting council standardized terminology (SKUs, Units, Inventory, Stock) and removed visual noise

---

## Implementation Plan

### What Will Change
Transform the existing Filament inventory dashboard from a plain, vertically-stacked layout into a visually stunning, operations-first enterprise dashboard with:

1. **Reorganized widget hierarchy** based on operational priority
2. **Modern visual design** with premium styling, consistent typography, and interactive states
3. **Responsive grid system** using Filament's 12-column layout with proper breakpoints
4. **Enhanced accessibility** meeting WCAG AAA standards
5. **Improved performance** with optimized loading and reduced CLS

### Exact Files to be Created/Modified

**Modified Files:**
1. `app/Filament/Pages/Dashboard.php` - Add `getColumns()` method, reorder widgets, add section headers
2. `app/Filament/Widgets/StatsOverviewWidget.php` - Update labels, add `getColumns()` method, improve styling
3. `app/Filament/Widgets/StockMovementTrendChart.php` - Update label, add accessibility, responsive column span
4. `app/Filament/Widgets/CategoryStockChart.php` - Update label, add accessibility, responsive column span
5. `app/Filament/Widgets/FastMovingStockChart.php` - Update label, add accessibility, responsive column span
6. `app/Filament/Widgets/LowStockAlertWidget.php` - Update styling, add accessibility features
7. `app/Filament/Widgets/RecentStockActivityWidget.php` - Update label, add accessibility features
8. `app/Filament/Widgets/StockByWarehouseWidget.php` - Update label, improve styling
9. `app/Filament/Widgets/QuickActionsWidget.php` - Update label, improve positioning and styling
10. `resources/views/filament/widgets/quick-actions.blade.php` - Improve button sizing and spacing
11. `resources/views/filament/widgets/stock-by-warehouse.blade.php` - Enhance styling and accessibility
12. `resources/css/filament/admin/theme.css` - Add custom styling for improved contrast and visual polish

**Created Files:**
1. `resources/views/filament/widgets/chart-data-tables/` - Alternative data tables for chart accessibility
2. `resources/views/filament/dashboard-sections/` - Section header blade components

### Expected Behavior After Change

**Layout Structure:**
- **Row 1 - Performance Overview:** 4 KPI stat cards with updated labels + Quick Actions (top-right)
- **Row 2 - Operations & Alerts:** Low Stock Alerts table (7 cols) + Quick Actions (5 cols)
- **Row 3 - Warehouse Status:** Stock by Warehouse cards (full width, 3-column grid)
- **Row 4 - Inventory Analytics:** Stock Inflow vs Outflow chart (6 cols) + Inventory by Category chart (6 cols)
- **Row 5 - Planning & Activity:** Top 10 High-Turnover Products chart (full width) + Recent Stock Movements table (full width)

**Visual Improvements:**
- Premium dark/rich light theme with proper contrast (WCAG AAA)
- Inter/Geist typography with tabular-nums for data, monospace for SKUs only
- Hover/active states, elevation shadows, loading skeletons
- Enhanced table design with striping, sticky headers, proper spacing
- Consistent chart card heights and styling

**Accessibility Enhancements:**
- WCAG AAA (7:1) contrast ratios throughout
- Chart.js accessibility plugin with data table alternatives
- 48x48px minimum touch targets
- Keyboard navigation with visible focus indicators
- ARIA landmarks, aria-live regions, screen reader support
- Color-independent information design

**Performance Improvements:**
- Responsive breakpoint configuration for all widgets
- Height constraints to prevent CLS
- Chart.js bundling via Vite
- Staggered polling intervals
- Loading skeleton states

### Scope Limitations

This plan does NOT cover:
- Creating new business logic or widgets (reorganizing existing only)
- Changing database schema or queries
- Modifying role-based access control
- Adding new features beyond layout/styling improvements
- Mobile app development (web dashboard only)

---

## Detailed Widget Configuration

### Dashboard.php
```php
public function getColumns(): int|array
{
    return [
        'default' => 1,   // < 640px (mobile)
        'sm' => 1,        // 640px+ (small tablets)
        'md' => 2,        // 768px+ (tablets)
        'lg' => 12,       // 1024px+ (desktops)
        'xl' => 12,       // 1280px+ (large desktops)
    ];
}

public function getWidgets(): array
{
    return [
        QuickActionsWidget::class,        // sort: 0
        StatsOverviewWidget::class,       // sort: 1
        LowStockAlertWidget::class,       // sort: 10 (moved up)
        StockByWarehouseWidget::class,    // sort: 15 (moved up)
        StockMovementTrendChart::class,  // sort: 20
        CategoryStockChart::class,       // sort: 21
        FastMovingStockChart::class,      // sort: 25
        RecentStockActivityWidget::class, // sort: 30
        AccountWidget::class,             // sort: 100
    ];
}
```

### Widget Column Spans
- **StatsOverviewWidget:** `['sm' => 1, 'md' => 2, 'lg' => 4]` (internal columns)
- **QuickActionsWidget:** `['sm' => 'full', 'md' => 'full', 'lg' => 2]` (Row 2 placement)
- **LowStockAlertWidget:** `['sm' => 'full', 'md' => 'full', 'lg' => 7]`
- **StockByWarehouseWidget:** `['sm' => 'full', 'md' => 'full', 'lg' => 'full']`
- **StockMovementTrendChart:** `['sm' => 'full', 'md' => 'full', 'lg' => 6]`
- **CategoryStockChart:** `['sm' => 'full', 'md' => 'full', 'lg' => 6]`
- **FastMovingStockChart:** `['sm' => 'full', 'md' => 'full', 'lg' => 'full']`
- **RecentStockActivityWidget:** `['sm' => 'full', 'md' => 'full', 'lg' => 'full']`

### Updated Labels
- **KPIs:** "Total SKUs", "Total Units on Hand", "Items Below Reorder Point", "Zero Stock SKUs"
- **Charts:** "Stock Inflow vs Outflow (30 Days)", "Inventory by Product Category", "Top 10 High-Turnover Products"
- **Tables:** "Low Stock Alerts", "Recent Stock Movements"
- **Cards:** "Warehouse Inventory Summary"
- **Actions:** "Common Actions"

---

## Technical Considerations

### Color System
- **Surfaces:** slate-900/zinc-800 (dark), white/gray-50 (light)
- **Borders:** slate-700/zinc-700 (dark), gray-200/gray-300 (light)
- **Text:** gray-300 minimum on dark, gray-800 minimum on light
- **Contrast:** WCAG AAA (7:1) verified with automated tools

### Typography
- **UI Labels:** Inter/Geist with font-variant-numeric: tabular-nums
- **Data Display:** Inter/Geist with tabular-nums for counts, currency
- **SKUs/Identifiers:** JetBrains Mono/Fira Code (monospace)
- **Headers:** Inter/Geist semibold/bold

### Performance
- **Chart.js:** Bundle via Vite instead of CDN
- **Polling:** Stagger intervals (30s charts, 60s tables)
- **Loading:** Skeleton states for all widgets
- **CLS:** Height constraints on tables (400px max)

### Accessibility
- **Charts:** Chart.js accessibility plugin + data table alternatives
- **Touch:** 48x48px minimum targets, 8px spacing
- **Keyboard:** 2px visible focus indicators, proper tab order
- **Screen Readers:** ARIA landmarks, aria-live regions, linear order
- **Color:** Text labels alongside all color indicators

---

## Implementation Order

1. **Configure Dashboard Grid:** Add `getColumns()` method to Dashboard.php
2. **Update Widget Labels:** Rename all widgets with new terminology
3. **Configure Widget Column Spans:** Add responsive `$columnSpan` to each widget
4. **Implement Visual Styling:** Add theme.css improvements (colors, typography, states)
5. **Add Accessibility Features:** Chart accessibility plugin, ARIA labels, keyboard nav
6. **Create Section Headers:** Add blade components for section dividers
7. **Reorder Widgets:** Update Dashboard.php widget array with new sort order
8. **Performance Optimizations:** Add height constraints, loading states, staggered polling
9. **Testing:** Verify responsive behavior, accessibility compliance, visual polish

---

## Success Criteria

- [ ] Layout matches approved hierarchy (operations-first)
- [ ] All widgets render with proper responsive column spans
- [ ] Visual styling matches approved design system
- [ ] WCAG AAA contrast achieved across all components
- [ ] Charts accessible with keyboard and screen readers
- [ ] Touch targets meet 48x48px minimum
- [ ] No cumulative layout shift (CLS < 0.1)
- [ ] Performance scores maintained (Lighthouse 90+)
- [ ] Cross-browser compatibility (Chrome, Firefox, Safari, Edge)
- [ ] Mobile/tablet/desktop responsive behavior verified

---

## Post-Implementation Verification

After implementation, verify with:
1. **Automated Testing:** Run `php artisan test` to ensure no regressions
2. **Accessibility Audit:** Use axe DevTools or WAVE to verify WCAG AAA compliance
3. **Performance Testing:** Lighthouse audit for CLS, accessibility, performance
4. **Visual Testing:** Playwright screenshots across viewport sizes (375px, 768px, 1280px, 1920px)
5. **User Testing:** Warehouse operator feedback on operational flow
