# Enterprise Dashboard — Task 1: Access Boundary

## Council Approval Status
**APPROVED** — Architecture and Security reviewers approved.

## Council Summary
- **Architecture:** Approved. `canAccess()` is the canonical Filament v5 mechanism for page-level authorization. Correctly stacks with existing `canAccessPanel()` (panel-level) and widget `canView()` (widget-level) without conflicts.
- **Security:** Approved. Server-side 403 before any page rendering or widget mounting. No Livewire bypass vectors. No data leakage. Defense-in-depth with existing `canView()`.

---

## Plan

### What Will Change
Add a server-side authorization gate to the Dashboard page so non-admin users receive a clean 403 Forbidden response with zero dashboard markup or data. This is a prerequisite for all subsequent dashboard sections.

### Exact Files to Create/Modify

| File | Change |
|------|--------|
| `app/Filament/Pages/Dashboard.php` | Add `public static function canAccess(): bool` that returns `auth()->user()->isAdmin()` |
| `tests/Feature/DashboardAccessTest.php` | Pest tests proving: (a) non-admin gets 403 with no dashboard data, (b) admin gets real dashboard |

### Expected Behavior
- Admin visiting `/admin` sees the full dashboard (no change).
- Non-admin (warehouse_staff) visiting `/admin` gets a 403 Forbidden page — no dashboard markup, no data in HTML response.
- The check is server-side via `canAccess()`, not a Blade conditional.

### Not in Scope
- Building new dashboard sections.
- Changing widget-level `canView()` logic.
- Modifying the User model or roles.
