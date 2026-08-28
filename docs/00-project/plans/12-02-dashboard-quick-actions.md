# Plan 12-02: Dashboard Quick Actions & Inline Stock Adjustment

## Plan

Add quick-action buttons to the dashboard and inline stock adjustment on the low stock alert widget, enabling warehouse staff to receive/ship stock directly from the dashboard without navigating to the Stock Movements page.

### Key Findings
- Stock actions (Receive/Ship/Transfer/Adjustment) currently exist ONLY in `ListStockMovements::getHeaderActions()`
- Dashboard has no action buttons — users must navigate to Operations > Stock Movements to perform any stock operation
- LowStockAlertWidget shows products needing restock but provides no way to act on them
- All 4 action modals share the same form patterns (product select, warehouse select, quantity, reference)

### Approach: Shared Action Trait

Extract the 4 stock action modals into a reusable trait (`App\Traits\StockActions`) that both `ListStockMovements` and the Dashboard page can use. Add an inline "Quick Receive" action on the `LowStockAlertWidget` table.

### Files to Create
1. `app/Traits/StockActions.php` — Shared trait with `receiveStockAction()`, `shipStockAction()`, `transferStockAction()`, `adjustmentAction()` methods
2. `app/Filament/Widgets/QuickActionsWidget.php` — Custom widget with 3 header action buttons (Receive Stock, Ship Stock, New Product)

### Files to Modify
1. `app/Filament/Resources/StockMovements/Pages/ListStockMovements.php` — Refactor to use the shared trait instead of inline action methods
2. `app/Filament/Widgets/LowStockAlertWidget.php` — Add inline "Quick Receive" action on table rows
3. `app/Filament/Pages/Dashboard.php` — Register QuickActionsWidget

### Implementation Details

#### StockActions Trait
- `receiveStockAction(): Action` — Modal form: product, warehouse, quantity, reference
- `shipStockAction(): Action` — Modal form: product, warehouse, quantity, reference (validates stock)
- `transferStockAction(): Action` — Modal form: product, from_warehouse, to_warehouse, quantity, reference
- `adjustmentAction(): Action` — Modal form: product, warehouse, quantity (pos/neg), reason
- All actions use `InventoryService` for business logic
- Warehouse options scoped by role via `getWarehouseOptions()` method

#### LowStockAlertWidget Inline Action
- Add a table action column with "Quick Receive" button
- Pre-fills product_id from the row record
- Opens modal with warehouse and quantity fields only
- On success, notification sent and table refreshed

#### QuickActionsWidget
- Simple widget with 3 prominent action buttons in a row
- Each button opens the same modal forms as the stock actions
- Uses the shared trait for form definitions

### Expected Behavior After Change
- Dashboard shows 3 quick-action buttons at top: Receive Stock, Ship Stock, New Product
- LowStockAlertWidget table has a "Receive" action button on each row
- Clicking any action opens a modal form, submits via InventoryService
- Stock Movements page still works identically (uses same trait)
- All actions are role-scoped (staff only see their warehouses)

### Not in Scope
- Purchase Order model/widget (no PO data model exists yet)
- Transfer actions on dashboard (low complexity-to-value ratio for dashboard)
- Inline ship/transfer on low stock widget (receive is the primary use case)

### Testing
- Test that the trait methods return Action objects with correct forms
- Test that LowStockAlertWidget renders the action column
- Test that QuickActionsWidget renders action buttons
- Test that stock actions work from dashboard (receive creates StockMovement)
- All existing tests must continue to pass

### Council Summary
- **Architecture:** Trait extraction is clean DRY; no better approach identified
- **Security:** Actions use same role-scoping as existing ListStockMovements; no new attack surface
- **Testing:** Trait is testable via action rendering; inline widget action testable via Livewire test
