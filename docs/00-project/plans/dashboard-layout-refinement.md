# Dashboard Layout Refinement Plan

## Decision Record

This plan refines the earlier `dashboard-layout-transformation.md` decision after Graphify discovery and council review. It retains the approved unified dashboard approach, but corrects the desktop grid packing and aligns the work with the project design system in `DESIGN.md`.

## Scope

Arrange and style only the existing dashboard widgets. Do not add business logic, alter data queries, change authorization, or duplicate widgets.

## Widget Inventory

- `StatsOverviewWidget`: four inventory KPIs.
- `LowStockAlertWidget`: urgent stock-alert table.
- `QuickActionsWidget`: existing operational actions.
- `StockByWarehouseWidget`: warehouse summary.
- `StockMovementTrendChart`, `CategoryStockChart`, `FastMovingStockChart`: existing Chart.js widgets.
- `RecentStockActivityWidget`: movement log.
- `AccountWidget`: existing account widget, rendered last.

## Approved Layout

Use Filament's responsive 12-column widget grid and one continuous page. Do not introduce segmented tabs.

| Order | Widget / group | Desktop span |
| --- | --- | --- |
| 1 | KPI overview (four internal cards) | 12 |
| 2 | Low Stock Alerts / Common Actions | 7 / 5 |
| 3 | Warehouse Inventory Summary | 12 |
| 4 | Stock Inflow vs Outflow / Inventory by Product Category | 7 / 5 |
| 5 | Top 10 High-Turnover Products | 12 |
| 6 | Recent Stock Movements | 12 |
| 7 | Account | explicit final span |

At mobile widths, widgets stack in source order, with Low Stock Alerts before Common Actions. At tablet widths, only pair widgets where their content remains legible. Tables retain an internal horizontal scroll area rather than clipping columns or creating page-wide overflow. Chart cards and canvases reserve consistent heights to avoid layout shift.

## Visual and Accessibility Requirements

- Follow `DESIGN.md`: flat light Filament-native surfaces, Zinc borders, Instrument Sans only, compact operational density, and semantic colors only for operational signals.
- Do not add decorative gradients, static shadows, glow effects, or alternate font families.
- Preserve clear, concise widget labels and render status with text/icons as well as color.
- Provide an always-visible, high-contrast `:focus-visible` treatment and controls with at least 40px touch targets.
- Meet WCAG 2.1 AA throughout; target AAA contrast for critical chart and status text/surfaces where practical for bright warehouse conditions.
- Give every chart canvas wrapper an accessible name. Its linked, adjacent summary or data equivalent must remain available to assistive technology. Legends/series need text labels and a non-color differentiator.
- Respect reduced-motion preferences. No animation is required to convey meaning.

## Files Expected to Change

- `app/Filament/Pages/Dashboard.php`
- Existing dashboard widget classes under `app/Filament/Widgets/`
- Existing widget Blade views under `resources/views/filament/widgets/`
- `resources/css/filament/admin/theme.css`
- Existing dashboard tests, if coverage is needed for the revised layout contract.

## Verification

- Full Pest suite: `php artisan test --compact`.
- Production asset build: `npm run build`.
- Browser inspection at 375px, 768px, 1280px, and 1920px for order, overflow, clipping, responsive tables, chart render, and focus visibility.

## Council Summary

All five councils approved this revised plan.

- UI/UX approved the unified 7/5 hierarchy only after the design system was made authoritative over the earlier dark/glow proposal.
- Frontend architecture corrected the original accidental `2 + 4 + 7` grid wrap and required full-width KPIs plus explicit spans.
- Accessibility and copy required canvas names, exposed adjacent chart equivalents, non-color series labels, AA baseline, critical-signal AAA targets, and explicit high-contrast focus states.
- Operations approved the urgency-first ordering and the absence of tabs, conditional on preserving role gates and warehouse scoping.
- Testing and operations approved the limited scope and required full-suite, build, and multi-viewport browser verification.
