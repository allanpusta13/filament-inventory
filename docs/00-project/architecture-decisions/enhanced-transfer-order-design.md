# ADR: Enhanced Transfer Order Design (Negotiated Multi-Branch Transfers with Loss Tracking)

## Status
**SUPERSEDES**: `transfer-order-design-decisions.md`

## Context
The original Transfer Order implementation provided a basic dispatch/receive workflow. The Branch Inventory Upgrade requires a significantly more complex system with:
- Two-way negotiation between branches
- Reserved stock derivation
- Complete audit trail
- Substitute product support
- Item soft-deletion
- Variance tracking with loss calculation
- Concurrency locking with reservation checks
- Role-based visibility

Eight design ambiguities were identified and must be resolved before implementation.

## Decisions

### 1. Reserved Stock: DERIVED, Not Stored

**Decision:** Reserved quantity is computed as `SUM(approved_quantity)` across all orders currently in `confirmed` or `dispatched` status that haven't been `received` yet. This is a derived value, not a stored counter.

**Rationale:** This follows the codebase's established convention — stock is always derived via `SUM(quantity)` over `stock_movements`, never a stored/decremented column. A stored reservation counter would introduce drift risk.

**Implementation:**
- `reserved_quantity = SUM(approved_quantity)` WHERE `status IN ('confirmed', 'dispatched')` AND order not received
- `available_for_negotiation = on_hand_quantity - reserved_quantity`
- Must be computed under `lockForUpdate()` when checking availability for new negotiations

### 2. In-Transit Quantity: DERIVED, Not Stored

**Decision:** In-transit quantity is computed as `SUM(approved_quantity)` for orders in `dispatched` status that haven't been `received` yet.

**Rationale:** Same as point 1 — derived values prevent drift.

**Implementation:**
- `in_transit_quantity = SUM(approved_quantity)` WHERE `status = 'dispatched'` AND order not received
- This is a subset of the reserved quantity (reserved includes both confirmed and dispatched)

### 3. Variance Formula: Validate Non-Negative Lost Quantity

**Decision:** `lost_quantity = approved_quantity - (received_quantity + damaged_quantity)` is a **computed accessor**, NOT a stored column. Must be validated to be >= 0 at both the Filament modal and inside `ReceiveTransferAction`.

**Rationale:** Data-entry errors could cause received + damaged to exceed approved, producing negative lost_quantity which is nonsensical. Following the codebase convention, derived values are never stored.

**Implementation:**
- **Model accessor:** `TransferOrderItem::getLostQuantityAttribute()` computes: `$this->approved_quantity - ($this->received_quantity + $this->damaged_quantity)`
- **Form level:** Validation rule in Repeater: `received_quantity + damaged_quantity <= approved_quantity`
- **Action level:** `ReceiveTransferAction` validates under `lockForUpdate()` before computing lost_quantity
- Throws `InvalidArgumentException` if validation fails
- `lost_quantity` is **NOT** in `$fillable` — it's computed, never user-set

### 4. Negotiation State Machine: Explicit Exit Paths

**Decision:** The negotiation state machine has clear exit paths:

```
draft → requested → under_review_fulfiller → under_review_requestor → confirmed → dispatched → received
         ↓                    ↓                        ↓
      cancelled            cancelled                cancelled
         ↓                    ↓                        ↓
      (cancelled)          (cancelled)              (cancelled)
```

- **Cancellation:** Either party can cancel unilaterally from `requested`, `under_review_fulfiller`, or `under_review_requestor` states. No acknowledgment required.
- **Rejection:** Warehouse B can reject the entire order by cancelling. This is distinct from removing all items — a rejected order has status `cancelled`, not an empty item list.

**Implementation:**
- Add `cancelled` status to enum
- Add `CancelTransferAction` that validates authorization and transitions to `cancelled`
- Table actions for cancel available to both branches

### 5. Concurrency: lockForUpdate Covers Reservation Checks

**Decision:** All operations that check or modify stock must use `DB::transaction` with `lockForUpdate()` on the affected product/warehouse rows.

**Rationale:** Prevents race conditions between:
- Two concurrent negotiations checking availability
- A negotiation checking availability while a dispatch is modifying stock
- A dispatch and a receive operating on the same product

**Implementation:**
- **Exact locking query:** `StockMovement::where('product_id', $productId)->where('warehouse_id', $warehouseId)->lockForUpdate()` — locks all movement rows for the product at the warehouse, then sums after lock
- `ConfirmTransferAction`: Locks source warehouse rows when reserving stock
- `DispatchTransferAction`: Locks source warehouse rows when deducting stock (replaces bare `currentQuantity()` with locked version)
- `ReceiveTransferAction`: Locks destination warehouse rows when adding stock
- All use `lockForUpdate()` within `DB::transaction`
- **Replaces** `currentQuantity()` in critical paths — must not call unlocked sum in actions

### 6. Audit Trail: Atomic with Item Mutations

**Decision:** Every mutation to a `transfer_order_item` must write the corresponding `transfer_order_audit` row in the same `DB::transaction`.

**Rationale:** Prevents audit trail from drifting from actual item state.

**Implementation:**
- Create `TransferOrderAudit` model
- `transfer_order_audits` table: `transfer_order_id`, `user_id`, `action`, `changes_payload` (JSON)
- Single `AuditService::record()` method called within every transaction
- Changes payload captures: field name, old value, new value

### 7. Item Soft-Deletion: Status-Based, Never Hard-Deleted

**Decision:** Items marked "removed" during negotiation use `item_status = 'removed'`, never hard-deleted.

**Rationale:** The audit trail's purpose is preserved history. Hard deletion would erase exactly what the audit timeline is supposed to show.

**Implementation:**
- Add `item_status` enum: `requested`, `approved`, `modified`, `added`, `removed`
- Items with `item_status = 'removed'` are excluded from stock calculations but remain visible in audit trail
- `added_by_branch_id` tracks which branch added substitute items

### 8. Role-Based Visibility: Both Branches See Full Record

**Decision:** Both requesting and fulfilling branches see the full record once an order involves them. No field-level visibility restrictions.

**Rationale:** Simplifies implementation while maintaining security — if you're involved in the transfer, you see everything.

**Implementation:**
- `getEloquentQuery()` scopes to orders where user has access to sender OR receiver branch
- No additional field-level visibility checks needed

## Consequences
- All eight ambiguities are resolved before coding begins
- The design is consistent with the codebase's existing patterns (derived stock, `canAccessWarehouse()`, `InventoryService`)
- Reserved stock derivation prevents concurrent negotiation races
- Audit trail is structurally enforced, not convention-dependent
- Item soft-deletion preserves complete history
- Authorization is explicit in every action (not implicit)
- `lost_quantity` is computed, never stored — no drift risk

## Files Affected
### New Files
- `database/migrations/xxxx_add_negotiation_columns_to_transfer_orders_table.php`
- `database/migrations/xxxx_add_negotiation_columns_to_transfer_order_items_table.php`
- `database/migrations/xxxx_create_transfer_order_audits_table.php`
- `app/Enums/TransferOrderItemStatus.php`
- `app/Models/TransferOrderAudit.php`
- `database/factories/TransferOrderAuditFactory.php`
- `app/Actions/SubmitTransferAction.php` (draft → requested)
- `app/Actions/ConfirmTransferAction.php`
- `app/Actions/CancelTransferAction.php`
- `app/Services/AuditService.php`

### Modified Files
- `app/Enums/TransferOrderStatus.php` (add negotiation statuses)
- `app/Models/TransferOrder.php` (add relationships, methods)
- `app/Models/TransferOrderItem.php` (add relationships, computed lost_quantity accessor)
- `app/Actions/DispatchTransferAction.php` (add locking, reservation checks)
- `app/Actions/ReceiveTransferAction.php` (add variance validation, locking)
- `app/Services/InventoryService.php` (add reservation methods, locked queries)
- `app/Filament/Resources/TransferOrders/` (all resource files)
