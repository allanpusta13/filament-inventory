# Branch Inventory Upgrade: Negotiated Multi-Branch Transfers with Loss Tracking

## Overview
Complete rewrite of the Transfer Order system to support two-way negotiation, reserved stock derivation, audit trails, substitute products, item soft-deletion, variance tracking, and concurrency locking.

## Pre-Requisites
- All 8 design ambiguities resolved (see ADR: `enhanced-transfer-order-design.md`)
- Existing simple transfer order implementation will be replaced
- Verify `chillerlan/php-qrcode` is in `composer.json` (already installed)
- No new dependencies required (dompdf not needed — use browser print for STN)

## Phase 1: Database Architecture & Core Models

### 1.1 Migration: Enhanced Transfer Orders
**Create NEW migration files** (do not modify existing ones):

**File 1: `xxxx_add_negotiation_columns_to_transfer_orders_table.php`**
- `driver_name` (nullable string)
- `vehicle_plate` (nullable string)

**File 2: `xxxx_add_negotiation_columns_to_transfer_order_items_table.php`**
- `requested_quantity` (integer)
- `approved_quantity` (nullable integer)
- `received_quantity` (nullable integer)
- `damaged_quantity` (nullable integer, default 0)
- `item_status` (string, default 'requested')
- `added_by_branch_id` (nullable foreign key to warehouses, **with index**)
- `variance_reason` (nullable text)
- **Remove `lost_quantity` column** — it will be a computed accessor

**File 3: `xxxx_create_transfer_order_audits_table.php`**
- `transfer_order_id` (foreign key)
- `user_id` (foreign key)
- `action` (string)
- `changes_payload` (json)
- `created_at` (timestamp)
- **Composite index:** `(transfer_order_id, created_at)`

### 1.2 Enums
- Update `TransferOrderStatus`: Add `Requested`, `UnderReviewFulfiller`, `UnderReviewRequestor`, `Confirmed` (TitleCase keys per CLAUDE.md)
- Create `TransferOrderItemStatus`: `Requested`, `Approved`, `Modified`, `Added`, `Removed` (TitleCase keys)

### 1.3 Models
- Update `TransferOrder`: Add relationships, status helpers, locking methods
- Update `TransferOrderItem`: Add relationships, **computed `getLostQuantityAttribute()` accessor** (NOT stored)
- Create `TransferOrderAudit`: Simple model for audit trail
- Create `TransferOrderAuditFactory`

### 1.4 Services
- Create `AuditService`: Atomic audit recording within transactions
  - `record(TransferOrder $order, User $user, string $action, array $changes): void`
- Update `InventoryService`:
  - `reservedQuantity(int $productId, int $warehouseId): int` — SUM approved for confirmed/dispatched orders
  - `availableForNegotiation(int $productId, int $warehouseId): int` — on_hand - reserved
  - `lockStockForProduct(int $productId, int $warehouseId): void` — executes `lockForUpdate()` query

### 1.5 Actions
- Create `SubmitTransferAction`: Validate status = draft, validate user belongs to requesting branch, transition to `requested`
- Create `ConfirmTransferAction`: Validate status = requested/under_review_requestor, validate user belongs to fulfilling branch, lock stock, verify availability, transition to `confirmed`
- Create `CancelTransferAction`: Validate status ∈ {requested, under_review_fulfiller, under_review_requestor}, validate user belongs to sender OR receiver branch, transition to `cancelled`
- Update `DispatchTransferAction`: Require status = confirmed, use `lockStockForProduct()` instead of bare `currentQuantity()`, validate stock under lock
- Update `ReceiveTransferAction`: Require status = dispatched, validate `received_quantity + damaged_quantity <= approved_quantity` under lock, compute lost_quantity via accessor

### 1.6 Validation Rules
- `driver_name`: nullable, string, max:255
- `vehicle_plate`: nullable, string, max:50
- `requested_quantity`: required, integer, min:1
- `approved_quantity`: nullable, integer, min:0
- `received_quantity`: nullable, integer, min:0
- `damaged_quantity`: nullable, integer, min:0, default:0
- `item_status`: required, in:Requested,Approved,Modified,Added,Removed

## Phase 2: Requisition & Two-Way Negotiation Workflow

### 2.1 Filament Resource
- Update `TransferOrderResource`: Add new statuses to filters, update form
- Create `CreateTransferOrder` wizard: 3 steps (Order Details, Line Items, Review)
  - Step 1: Select sender/receiver branches, driver/vehicle info
  - Step 2: Repeater for line items (product, requested_quantity)
  - Step 3: Review summary before submission
- Create `EditTransferOrder`: For draft orders only
- Create `ViewTransferOrder`: **Use Filament ViewRecord page** to inherit `getEloquentQuery()` scoping
- Create `ListTransferOrders`: Table with status badges, filters

### 2.2 Negotiation Actions
- Warehouse A creates order (status: `draft`)
- Warehouse A submits → `requested`
- Warehouse B reviews: adjust quantities, add substitutes, mark removed → `under_review_requestor`
- Warehouse A counter-reviews: accept → `confirmed` or re-adjust → `under_review_fulfiller`
- Either party can cancel from any pre-dispatch state

### 2.3 Form Components
- Repeater for line items with conditional fields
- Substitute product selection (when adding new items)
- Quantity adjustment fields with validation
- Reason/notes fields
- Item status indicators (requested/modified/added/removed)

## Phase 3: Inventory Movement Service

### 3.1 Dispatch
- `DispatchTransferAction`: Validate status = confirmed, check stock under lock, create movements
- Use `lockStockForProduct()` to lock source warehouse rows
- Validate stock availability after lock
- Create `transfer_out` movements for each item
- Update order status to `dispatched`

### 3.2 Receive
- `ReceiveTransferAction`: Validate status = dispatched, collect received/damaged quantities
- Validate `received_quantity + damaged_quantity <= approved_quantity` under lock
- Compute `lost_quantity` via `TransferOrderItem::getLostQuantityAttribute()` accessor
- Lock destination warehouse rows with `lockStockForProduct()`
- Create `transfer_in` movements for received quantities
- Update order status to `received`

### 3.3 Audit Trail
- Every action writes audit row atomically via `AuditService::record()`
- Changes payload captures: field name, old value, new value
- User ID recorded for accountability
- **Test:** Verify no orphaned audit rows on failed transactions

## Phase 4: Unified Detail View & Printable Slips

### 4.1 View Page
- **Use Filament `ViewRecord` page** (inherits `getEloquentQuery()` scoping)
- Status/logistics bar showing current state
- Side-by-side quantity comparison (requested/approved/received/damaged/lost)
- Audit log timeline with user attribution
- Role-based visibility (both branches see full record)

### 4.2 Printable STN
- Generate HTML view for browser print (no new dependencies)
- QR code of reference number (use existing `chillerlan/php-qrcode`)
- Include: origin/destination, items, quantities, driver/vehicle info

## Phase 5: Product Catalog Layout (Independent)

### 5.1 View Toggle
- Table view (default) + card view
- Toggle persists per user session (Livewire property)
- Dynamic switching without page reload

## Testing Requirements

### Required Test Cases (per Council feedback)

**1. `SubmitTransferActionTest`**
- Happy path: draft → requested
- Invalid status → rejected
- Unauthorized user → rejected

**2. `ConfirmTransferActionTest`**
- Happy path: requested → confirmed with stock available
- Insufficient stock → `InsufficientStockException`
- Invalid status (e.g., dispatched) → rejected
- Unauthorized user → rejected

**3. `CancelTransferActionTest`**
- Cancel from requested → cancelled
- Cancel from under_review_fulfiller → cancelled
- Cancel from under_review_requestor → cancelled
- Cancel from dispatched → rejected
- Unauthorized user → rejected

**4. `DispatchTransferActionTest` (updated)**
- Happy path: confirmed → dispatched
- Invalid status → rejected
- Stock under lock verified

**5. `ReceiveTransferActionTest` (updated)**
- Happy path: dispatched → received
- `received + damaged > approved` → exception
- `lost_quantity` computed correctly
- Damaged quantity equals approved → lost = 0

**6. `InventoryServiceTest` (new)**
- `reservedQuantity()` returns correct sum
- `availableForNegotiation()` = on_hand - reserved
- Reserved decreases after receive
- `lockStockForProduct()` acquires lock

**7. `AuditServiceTest` (new)**
- Audit row written atomically
- Failed transaction leaves no orphaned rows
- Changes payload captures old/new values

**8. Concurrency Tests (updated)**
- Two concurrent confirms against same product serialize
- Two concurrent dispatches against same product serialize
- Dispatch vs. receive on same order serialize

**9. Edge Case Tests (existing, updated)**
- Negative lost_quantity guard (point 3)
- Removed items stay visible in audit trail (point 7)
- Full negotiation round-trip

**10. Authorization Tests**
- Unrelated branch user cannot view/confirm/cancel orders
- Both branches can view orders involving them

### Test Coverage Matrix
- Unit tests for all actions and services
- Feature tests for Filament resource pages
- Livewire tests for wizard/negotiation components
- Integration tests for stock locking and audit trail

## Implementation Order
1. Phase 1 (Database & Core) - MUST complete first
2. Phase 2 (Negotiation UI) - Depends on Phase 1
3. Phase 3 (Inventory Service) - Depends on Phase 1
4. Phase 4 (Detail View) - Depends on Phases 1-3
5. Phase 5 (Catalog Layout) - Independent, can run in parallel

## Out of Scope
- Real-time notifications for negotiation updates
- Email notifications
- Mobile-specific UI
- Reporting/analytics dashboards
- Barcode scanning for products
- dompdf (use browser print for STN)
