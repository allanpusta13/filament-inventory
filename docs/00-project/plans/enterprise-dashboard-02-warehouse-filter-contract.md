# Enterprise Dashboard — Task 2: Shared Warehouse-Filter Interface Contract

## Council Approval Status
**APPROVED** — Architecture and Security reviewers approved.

## Council Summary
- **Architecture:** Approved. The existing `DashboardFilterable` trait + `WarehouseFilterWidget` session pattern is the correct approach. No new abstraction needed — formalize the existing pattern as the contract.
- **Security:** Approved. Backend refetch ensures data never leaves the server unsandboxed. Session-based filtering is role-scoped (admin sees all, staff sees assigned only).

---

## Plan

### Resolution: Real Backend Refetch

When the warehouse filter changes, **every consuming section re-queries its own real data scoped to that warehouse from the backend**. This is NOT a client-side filter over already-fetched data.

**Why backend refetch:**
- Enterprise inventory datasets can be large — loading all warehouse data upfront is wasteful
- Backend scoping ensures data never leaks across warehouse boundaries
- Consistent with the existing `DashboardFilterable` trait pattern already used by all current widgets
- More secure — filtered data never reaches the client unscoped

### How It Works (Existing Mechanism, Formalized)

1. **State location:** Session key `admin_warehouse_filter` (set by `WarehouseFilterWidget`)
2. **State values:** `null` (all warehouses) | warehouse ID (specific warehouse)
3. **Propagation:** Filament widget polling (60s default) causes each widget to re-mount, reading the updated session value via `DashboardFilterable::getFilterWarehouseIds()`
4. **Scoping:** Each widget uses `scopeToWarehouses()` to filter its queries to the selected warehouse
5. **Role behavior:** Admin sees filtered results; WarehouseStaff sees only their assigned warehouses (filter widget hidden from staff)

### Contract for New Widgets

Any new widget that displays warehouse-scoped data MUST:

1. Use the `App\Traits\DashboardFilterable` trait
2. Call `$this->scopeToWarehouses($query, $user)` in its data-fetching method
3. Call `$this->getFilterWarehouseIds($user)` when needing raw IDs for conditional logic
4. Be registered in `Dashboard::getWidgets()` (not as a standalone Livewire component)

### Files Involved

| File | Role |
|------|------|
| `app/Traits/DashboardFilterable.php` | Contract trait — provides `getFilterWarehouseIds()` and `scopeToWarehouses()` |
| `app/Filament/Widgets/WarehouseFilterWidget.php` | State owner — stores/reads filter from session |
| `app/Filament/Pages/Dashboard.php` | Container — registers filter widget as header widget |
| All dashboard widgets | Consumers — use `DashboardFilterable` to scope queries |

### Expected Behavior
- Admin changes warehouse filter → all widgets re-render with data scoped to that warehouse
- Widget data is fetched from backend with warehouse scope applied at query level
- No widget renders data from warehouses outside the filter scope
- WarehouseStaff never sees the filter widget and always sees data scoped to their assigned warehouses

### Not in Scope
- Changing the polling interval or propagation mechanism
- Adding real-time event broadcasting for instant filter propagation
- Modifying the `DashboardFilterable` trait logic
