# Blueprint Addendum — Purchases (External Intake) & Sales (External Dispatch) Module (v11.1)

**Applies on top of:** Multi-Warehouse Inventory System Blueprint v11.0
**Status:** Specification only — not yet implemented. This document is written to be handed to an implementer/agent as a standalone build prompt, cross-referenced against the parent blueprint.

> **Scope boundary:** The parent blueprint (v11.0) is a *pure inter-warehouse logistics* system — stock only ever moves between warehouses you already own (`TransferOut`/`TransferIn`, `TransitOut`/`TransitIn`). It has no concept of stock entering from outside the company (a supplier) or leaving to outside the company (a customer). This addendum adds both, without modifying any of the 13 existing tables, without altering `reservedQuantity()`'s permanent Confirmed-only scope (Principle #13), and without introducing any compensating-reversal pathway that the parent blueprint deliberately avoided (Principle #14).

---

## 🧭 Addendum Architecture & Principles

**A1. Same Ledger, New Movement Types.** Per Principle #1, `onHandQuantity()` sums *all* signed `stock_movements` rows for a variant+warehouse. Purchases and sales are simply new `StockMovementType` cases — no new "stock" table is introduced. This is the whole reason the addendum is small.

**A2. Purchases and Sales Are Symmetric, Single-Entity Flows — Not Negotiated.** Unlike `transfer_requisitions` (two internal warehouses negotiating), a purchase involves one external supplier and one internal warehouse; a sale involves one internal warehouse and one external customer. Neither needs a negotiation state machine. Each gets a lightweight **draft → confirmed → (partially_fulfilled) → completed / cancelled** lifecycle — simpler than the transfer requisition's 8-state machine, because there is no counter-offer thread.

**A3. External Party Entities Are Minimal Master Data.** `suppliers` and `customers` are simple lookup tables (name, contact, is_active), not full CRM/vendor-management systems. Kept deliberately thin — expand later if needed.

**A4. Cost & Price Interplay With `product_variant_prices`.**
- A **received purchase** at a cost different from the variant's current `cost_price` triggers a new `product_variant_prices` row (`is_current = true`, superseding the prior one) — mirroring `SetCurrentPriceAction`'s existing behavior. This is opt-in per purchase order (`update_cost_price` flag), not automatic, because a one-off discounted PO shouldn't silently reprice the catalog.
- A **sale** always dispatches at `currentPrice.sale_price` at the moment of confirmation, snapshotted onto the sale item row (`unit_sale_price_snapshot`) — same call-time-snapshot philosophy as `LossLedger::snapshotUnitCostFrom()` (Principle #15). Historical sales must never retroactively change value when catalog prices change later.

**A5. Reservation Boundary Stays Untouched, Sales Get Their Own Boundary.** Per `[FIX v11]` Principle #13, `reservedQuantity()` is **permanently and explicitly** bounded to `Confirmed` transfer requisitions. This addendum does **not** extend that method. Instead, a **new, separate** method `reservedForSalesQuantity()` sums `Confirmed`-status `sales_order_items`. `availableQuantity()` is extended to net out *both*:
  ```
  availableQuantity = onHandQuantity - reservedQuantity (transfers) - reservedForSalesQuantity (sales)
  ```
  This keeps the transfer-reservation logic's documented invariant intact (a future maintainer diffing `ProductVariant` will see the original method byte-for-byte unchanged) while still correctly reflecting stock promised to customers.

**A6. No Reversal Pathway for Dispatched Sales — Same Philosophy as Principle #14.** Once a `SalesOrder` transitions to `Dispatched` (stock movement fired), cancellation is permanently unavailable, exactly like `TransferRequisition`. A dispatched sale can only be unwound via an explicit, separate `SalesReturn` record (new movement type `SaleReturn`, positive quantity, referencing the original sale) — never by mutating or deleting the original movement. This avoids reintroducing the exact class of reversal-logic bug Principle #14 was written to eliminate.

**A7. Purchases Have No "Loss" Concept at Intake (Deliberately Deferred).** The parent blueprint's `scanToReceive()` loss/damage machinery exists because *transfers* have a shipping leg that can lose cargo in transit between two of your own warehouses. A purchase from a supplier is modeled as a single point-in-time receipt at the destination warehouse — there is no transit leg in this addendum's v1 scope. If you later want supplier shipment tracking with its own loss ledger, that is a **v2 addition**, explicitly deferred (see Section "Deferred" at the end of this addendum) — do not conflate it with `loss_ledgers`, which is FK-scoped to `transfer_requisitions`.

**A8. Policies Are the ONLY Home for Permission/Role Logic — System-Wide, Not Just This Addendum, and Permanent Once Correct.** This principle governs every Policy class in the entire system, not only the six new ones this addendum introduces (`PurchaseOrderPolicy`, `SalesOrderPolicy`, `SupplierPolicy`, `CustomerPolicy`) — it applies equally and retroactively to every existing parent v11.0 policy (`ProductPolicy`, `ProductVariantPolicy`, `TransferRequisitionPolicy`, `TransferRequisitionItemRevisionPolicy`, `StockMovementPolicy`, `InTransitPolicy`, `LossLedgerPolicy`, `WarehousePolicy`, `UserPolicy`). The rule:

- **All permission/role logic — every check of who is allowed to do what — must live inside the relevant Policy class's method body, and nowhere else.** Not in a Filament `->visible()` closure (those control DOM rendering only, per Principle #12, and must call the policy rather than duplicate its logic — e.g. `->visible(fn ($record) => $record->status === X && auth()->user()->can('someAbility', $record))` is acceptable *status-display* logic composed with a policy call, but the actual "is this role/user allowed" decision is never re-derived inline). Not inside a Service class method (services enforce data-integrity invariants — stock sufficiency, state-machine legality, decimal precision — not "is this user allowed to call me"; that check belongs to the Policy, invoked before the service is ever reached). Not scattered across Blade views, Livewire component methods, or ad-hoc `auth()->user()->role === 'admin'` checks anywhere else in the codebase. If a permission decision needs to be made, there is exactly one place that decision is made: the Policy method for that ability.
- **This is a consolidation requirement, not merely a "add policies too" requirement.** Wherever role/permission logic currently exists outside a Policy class anywhere in the system — including in the parent v11.0 codebase, if Phase 0's audit finds any — it must be moved into the appropriate Policy method as part of closing this gap, not left in place alongside a newly-added, redundant Policy check. Two sources of truth for the same permission decision is the exact failure mode this principle exists to prevent, even if both currently happen to agree.
- **Once a Policy method correctly and completely encodes the permission/role logic for its ability, it is treated as a closed, frozen contract — exactly like `reservedQuantity()` under Principle #13.** After this consolidation is done and verified (see the test checkpoint below), that Policy method is not touched again except to add a genuinely new ability or to fix a demonstrated bug in its logic. Every other part of the system — Actions' `->authorize()` calls, Service-layer callers, Livewire components, future features — **only ever calls the existing Policy method** (`$user->can('ability', $model)` / `->authorize('ability')`) and never re-implements, duplicates, special-cases, or bypasses what that method decides. A future feature needing a *new* permission decision gets a *new* Policy method (or a new Policy class for a new model) — it never gets an inline check bolted on somewhere else because "it's just this one case."
- **Practical effect for this addendum specifically:** `PurchaseOrderPolicy::cancelPurchase()` and `SalesOrderPolicy::cancelSalesOrder()` (Integration Point #7 / Authorization Mapping below) are where the "no stock has moved yet" guard actually lives — not duplicated as a second inline check inside `PurchaseService::cancelPurchaseOrder()`/`SalesService::cancelSalesOrder()`. The Service methods still defensively re-check their own preconditions (per Principle #14's philosophy — a service should not trust that authorization was checked upstream, since services can be called from contexts other than a Filament Action), but that Service-level check is a **data-integrity guard** ("is this transition legal given the record's current state"), not a **permission check** ("is this user allowed to attempt it"). The two can look similar in code but answer different questions — do not collapse them into one, and do not let the Service's data-integrity guard become a place where role-specific logic (e.g. "unless the user is an admin") creeps in. If a role-based exception to a state-machine rule is ever needed, that exception is expressed in the Policy method, and the Service still enforces the state machine unconditionally.

**Test checkpoint (required before this principle is considered satisfied, for both new and existing policies):**
```
PolicyAuditTest::no_permission_or_role_check_exists_outside_a_policy_class_in_app_filament()
PolicyAuditTest::no_permission_or_role_check_exists_outside_a_policy_class_in_app_services()
PurchaseOrderPolicyTest::cancelPurchase_is_the_sole_source_of_truth_for_the_five_state_pre_dispatch_style_guard()
SalesOrderPolicyTest::cancelSalesOrder_is_the_sole_source_of_truth_for_the_pre_dispatch_guard()
```
The first two are intentionally broad and somewhat unusual as automated tests — they may need to be implemented as a static-analysis grep/AST check (e.g. flagging `auth()->user()->role` or `auth()->user()->isAdmin()` literal occurrences outside `app/Policies/`) rather than a conventional Pest assertion, since "prove a negative exists nowhere in the codebase" doesn't fit neatly into a single unit test. Implement it as whatever mechanism actually catches the violation — a custom Pest architecture test (`arch()->expect(...)`, if the project's Pest version supports architecture testing) is the cleanest fit if available; otherwise a CI-time grep check is an acceptable substitute. Either way, this check must exist and must be run, not just aspired to in a comment.



### 14. suppliers

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| name | string | No | — |
| contact_person | string | Yes | — |
| phone | string | Yes | — |
| email | string | Yes | — |
| address | text | Yes | — |
| is_active | boolean | No | true |
| deleted_at | timestamp | Yes | — |
| created_at / updated_at | timestamp | Yes | — |

### 15. customers

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| name | string | No | — |
| contact_person | string | Yes | — |
| phone | string | Yes | — |
| email | string | Yes | — |
| address | text | Yes | — |
| is_active | boolean | No | true |
| deleted_at | timestamp | Yes | — |
| created_at / updated_at | timestamp | Yes | — |

### 16. purchase_orders

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| reference_code | string, unique | No | — |
| supplier_id | FK → suppliers.id (restrictOnDelete) | No | — |
| warehouse_id | FK → warehouses.id (restrictOnDelete) | No | — |
| status | string | No | `PurchaseOrderStatus::Draft->value` |
| update_cost_price | boolean | No | false |
| ordered_by | FK → users.id | No | — |
| received_by | FK → users.id | Yes | — |
| ordered_at | timestamp | Yes | — |
| received_at | timestamp | Yes | — |
| cancelled_at | timestamp | Yes | — |
| notes | text | Yes | — |
| deleted_at | timestamp | Yes | — |
| created_at / updated_at | timestamp | Yes | — |

Indexes: `status`, `(supplier_id, warehouse_id)`

**Key Implementation Notes (mirrors `transfer_requisitions` conventions):**
- String column + PHP backed enum (`App\Enums\PurchaseOrderStatus`), not DB `enum()`.
- `supplier_id` and `warehouse_id` both `restrictOnDelete` — cannot delete a supplier/warehouse with PO history.
- Soft deletes enabled.
- `update_cost_price`: if true, `receivePurchase()` will insert a new `is_current=true` `product_variant_prices` row per line item at receipt time (see A4).

### 17. purchase_order_items

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| purchase_order_id | FK → purchase_orders.id (cascadeOnDelete) | No | — |
| product_variant_id | FK → product_variants.id (restrictOnDelete) | No | — |
| ordered_unit_name | string | No | — |
| ordered_unit_ratio | integer | No | — |
| ordered_qty | integer | No | — |
| ordered_base_qty | integer | No | — |
| unit_cost_price | decimal(15,4) | No | 0.0000 |
| received_base_qty | integer | No | 0 |
| notes | text | Yes | — |
| created_at / updated_at | timestamp | Yes | — |

Indexes: `purchase_order_id`

**Key Implementation Notes:**
- `product_variant_id` is `restrictOnDelete` — same pattern as `transfer_requisition_items`.
- `unit_cost_price` is captured **at order time**, decimal(15,4) for micro-pricing parity with the rest of the system.
- No `substitute_product_variant_id` — purchases are not negotiated, so substitution has no meaning here. (If a supplier ships a substitute, that's a data-entry correction to the line item pre-receipt, not a negotiation thread.)

### 18. sales_orders

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| reference_code | string, unique | No | — |
| customer_id | FK → customers.id (restrictOnDelete) | No | — |
| warehouse_id | FK → warehouses.id (restrictOnDelete) | No | — |
| status | string | No | `SalesOrderStatus::Draft->value` |
| ordered_by | FK → users.id | No | — |
| dispatched_by | FK → users.id | Yes | — |
| ordered_at | timestamp | Yes | — |
| confirmed_at | timestamp | Yes | — |
| dispatched_at | timestamp | Yes | — |
| cancelled_at | timestamp | Yes | — |
| notes | text | Yes | — |
| deleted_at | timestamp | Yes | — |
| created_at / updated_at | timestamp | Yes | — |

Indexes: `status`, `(customer_id, warehouse_id)`

**Key Implementation Notes:**
- `customer_id` and `warehouse_id` both `restrictOnDelete`.
- Soft deletes enabled.
- Lifecycle: `draft → confirmed → dispatched → completed / cancelled`. `confirmed` is the point at which `reservedForSalesQuantity()` starts counting the order (mirrors `Confirmed` semantics on `transfer_requisitions`). `cancelled` is **only legal pre-dispatch** — identical philosophy to `[FIX v11]` Principle #14.

### 19. sales_order_items

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| sales_order_id | FK → sales_orders.id (cascadeOnDelete) | No | — |
| product_variant_id | FK → product_variants.id (restrictOnDelete) | No | — |
| unit_name | string | No | — |
| unit_ratio | integer | No | — |
| qty | integer | No | — |
| base_qty | integer | No | — |
| unit_sale_price_snapshot | decimal(15,4) | No | 0.0000 |
| dispatched_base_qty | integer | No | 0 |
| notes | text | Yes | — |
| created_at / updated_at | timestamp | Yes | — |

Indexes: `sales_order_id`

**Key Implementation Notes:**
- `unit_sale_price_snapshot` captured **at confirm-time**, per A4 — never recalculated later, even if catalog `sale_price` changes.
- `dispatched_base_qty` supports partial dispatch (e.g., partial stock available now, rest backordered) — same pattern as `shipped_base_qty` / `received_good_base_qty` on `transfer_requisition_items`.

### 20. `stock_movements` — new `type` values only (no column changes)

Add four new cases to the existing `StockMovementType` enum (no migration needed — `type` is already a plain string column):

- `Purchase` — positive quantity, fired on PO receipt
- `Sale` — negative quantity, fired on sales order dispatch
- `SaleReturn` — positive quantity, fired on a customer return against a dispatched sale (A6)
- `PurchaseReturn` — negative quantity, fired when returning stock to a supplier post-receipt (symmetric completeness; optional for v1 if you don't need it yet)

`reference_type` / `reference_id` / `reference_code` on these movements point back to `PurchaseOrder::class` / `SalesOrder::class` and their `id`/`reference_code`, exactly like transfer movements already do.

---

## 🛠️ Model Additions

### Supplier / Customer (thin models)

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'contact_person', 'phone', 'email', 'address', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'contact_person', 'phone', 'email', 'address', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }
}
```

### PurchaseOrder / PurchaseOrderItem

```php
namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_code', 'supplier_id', 'warehouse_id', 'status',
        'update_cost_price', 'ordered_by', 'received_by',
        'ordered_at', 'received_at', 'cancelled_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'update_cost_price' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function orderedBy(): BelongsTo { return $this->belongsTo(User::class, 'ordered_by'); }
    public function receivedBy(): BelongsTo { return $this->belongsTo(User::class, 'received_by'); }
    public function items(): HasMany { return $this->hasMany(PurchaseOrderItem::class); }
}

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id', 'product_variant_id', 'ordered_unit_name',
        'ordered_unit_ratio', 'ordered_qty', 'ordered_base_qty',
        'unit_cost_price', 'received_base_qty', 'notes',
    ];

    protected function casts(): array
    {
        return ['unit_cost_price' => 'decimal:4'];
    }

    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function productVariant(): BelongsTo { return $this->belongsTo(ProductVariant::class); }

    public function outstandingBaseQty(): int
    {
        return max(0, $this->ordered_base_qty - $this->received_base_qty);
    }
}
```

### SalesOrder / SalesOrderItem

```php
namespace App\Models;

use App\Enums\SalesOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_code', 'customer_id', 'warehouse_id', 'status',
        'ordered_by', 'dispatched_by', 'ordered_at', 'confirmed_at',
        'dispatched_at', 'cancelled_at', 'notes',
    ];

    protected function casts(): array
    {
        return ['status' => SalesOrderStatus::class];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function orderedBy(): BelongsTo { return $this->belongsTo(User::class, 'ordered_by'); }
    public function dispatchedBy(): BelongsTo { return $this->belongsTo(User::class, 'dispatched_by'); }
    public function items(): HasMany { return $this->hasMany(SalesOrderItem::class); }
}

class SalesOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_id', 'product_variant_id', 'unit_name', 'unit_ratio',
        'qty', 'base_qty', 'unit_sale_price_snapshot', 'dispatched_base_qty', 'notes',
    ];

    protected function casts(): array
    {
        return ['unit_sale_price_snapshot' => 'decimal:4'];
    }

    public function salesOrder(): BelongsTo { return $this->belongsTo(SalesOrder::class); }
    public function productVariant(): BelongsTo { return $this->belongsTo(ProductVariant::class); }

    public function outstandingBaseQty(): int
    {
        return max(0, $this->base_qty - $this->dispatched_base_qty);
    }

    public function lineTotal(): string
    {
        return bcmul((string) $this->dispatched_base_qty, (string) $this->unit_sale_price_snapshot, 4);
    }
}
```

### `ProductVariant` extension (A5) — additive only, does not touch `reservedQuantity()`

```php
use App\Enums\SalesOrderStatus;

/**
 * New in this addendum. Deliberately SEPARATE from reservedQuantity(),
 * which per [FIX v11] Principle #13 is permanently scoped to Confirmed
 * transfer_requisitions only and must not be widened. Sales reservations
 * are a distinct concern with a distinct lifecycle and are summed here
 * instead, then combined in availableQuantity() below.
 */
public function reservedForSalesQuantity(int $warehouseId): int
{
    return (int) SalesOrderItem::where('product_variant_id', $this->id)
        ->whereHas('salesOrder', function ($query) use ($warehouseId) {
            $query->where('warehouse_id', $warehouseId)
                ->where('status', SalesOrderStatus::Confirmed);
        })
        ->sum('base_qty');
}

/**
 * [FIX v11.1] availableQuantity() now nets out BOTH transfer reservations
 * and sales reservations. This REPLACES the parent blueprint's
 * availableQuantity() body — reservedQuantity() itself is untouched.
 */
public function availableQuantity(int $warehouseId): int
{
    return $this->onHandQuantity($warehouseId)
        - $this->reservedQuantity($warehouseId)
        - $this->reservedForSalesQuantity($warehouseId);
}
```

### `[Added v11.1 — query optimization]` Batched `availableQuantity()` for N-item contexts — avoids the per-item N+1 that a naive `dispatchSale` modal would otherwise cause

**Why this exists:** `availableQuantity()` runs three separate aggregate queries (`onHandQuantity`, `reservedQuantity`, `reservedForSalesQuantity`) per call. Calling it once per line item inside `dispatchSale`'s modal `->schema()` closure (Filament Resources section below) means an order with 10 items issues **30 queries** just to render one modal — every time it's opened, not once per cache window. This is the same class of problem the parent blueprint already named and accepted for `LowStockAlertsWidget` (Section 7's `[ACCEPTED RISK]` note), but worse here because it sits on an interactive, uncached path rather than a 300-second-cached dashboard read. Per your direction to optimize proactively rather than defer, this addendum closes it at the addendum layer rather than inheriting the parent's accepted-risk posture for a case where deferring isn't warranted.

**The fix — one grouped-aggregate static method, computing all three components for a whole item collection in three queries total, not `3 × N`:**

```php
namespace App\Models;

use App\Enums\TransferRequisitionStatus;
use App\Enums\SalesOrderStatus;
use Illuminate\Support\Collection;

// Add to ProductVariant, alongside reservedForSalesQuantity() above.

/**
 * [Added v11.1] Batched sibling of availableQuantity(), for any context
 * that needs the figure for MULTIPLE variants against ONE warehouse at
 * once (e.g. every line item on a single sales order's dispatch modal).
 * Issues exactly 3 queries total regardless of how many variant IDs are
 * passed, instead of 3 queries PER variant via the instance method.
 *
 * This mirrors the exact upgrade path the parent blueprint's own
 * LowStockAlertsWidget accepted-risk note already prescribes ("a single
 * grouped aggregate query... grouped by (product_variant_id,
 * warehouse_id)") — applied here at addendum-authoring time instead of
 * being deferred as an accepted risk, since this call site is
 * uncached and on the interactive path, not a 300s-cached dashboard read.
 *
 * Returns [product_variant_id => availableQuantity] for the given
 * warehouse. Variant IDs with no movements/reservations at all correctly
 * return 0, not an array-key-missing gap — every requested ID is present
 * in the result.
 */
public static function batchAvailableQuantity(array $variantIds, int $warehouseId): array
{
    if (empty($variantIds)) {
        return [];
    }

    $onHand = StockMovement::whereIn('product_variant_id', $variantIds)
        ->where('warehouse_id', $warehouseId)
        ->selectRaw('product_variant_id, SUM(quantity) as total')
        ->groupBy('product_variant_id')
        ->pluck('total', 'product_variant_id');

    $reservedTransfers = TransferRequisitionItem::whereIn('product_variant_id', $variantIds)
        ->whereHas('transferRequisition', function ($query) use ($warehouseId) {
            $query->where('from_warehouse_id', $warehouseId)
                ->where('status', TransferRequisitionStatus::Confirmed);
        })
        ->selectRaw('product_variant_id, SUM(approved_base_qty) as total')
        ->groupBy('product_variant_id')
        ->pluck('total', 'product_variant_id');

    $reservedSales = SalesOrderItem::whereIn('product_variant_id', $variantIds)
        ->whereHas('salesOrder', function ($query) use ($warehouseId) {
            $query->where('warehouse_id', $warehouseId)
                ->where('status', SalesOrderStatus::Confirmed);
        })
        ->selectRaw('product_variant_id, SUM(base_qty) as total')
        ->groupBy('product_variant_id')
        ->pluck('total', 'product_variant_id');

    return collect($variantIds)->mapWithKeys(function ($id) use ($onHand, $reservedTransfers, $reservedSales) {
        $available = (int) ($onHand[$id] ?? 0)
            - (int) ($reservedTransfers[$id] ?? 0)
            - (int) ($reservedSales[$id] ?? 0);

        return [$id => $available];
    })->all();
}
```

**Usage in `dispatchSale`'s modal (replaces the naive per-item `$item->productVariant->availableQuantity(...)` call shown in an earlier draft of this addendum):**

```php
->schema(function (SalesOrder $record) {
    $variantIds = $record->items->pluck('product_variant_id')->all();
    $availableByVariant = \App\Models\ProductVariant::batchAvailableQuantity($variantIds, $record->warehouse_id);

    return collect($record->items)
        ->map(function ($item) use ($availableByVariant) {
            $available = $availableByVariant[$item->product_variant_id] ?? 0;
            $safeMax = min($item->outstandingBaseQty(), max(0, $available));

            return TextInput::make("dispatch.{$item->id}")
                ->label("{$item->productVariant->sku} — outstanding {$item->outstandingBaseQty()} {$item->unit_name} (available: {$available})")
                ->numeric()
                ->minValue(0)
                ->maxValue($safeMax)
                ->default($safeMax)
                ->helperText($available < $item->outstandingBaseQty()
                    ? 'Insufficient stock for full dispatch — partial dispatch only.'
                    : null);
        })
        ->all();
})
```

Now one modal open costs **3 queries total**, regardless of line-item count, instead of `3 × N`. (The `Filament Resources` section further below still shows the original per-item form for narrative clarity where the optimization isn't yet threaded through — the version here is the one to actually implement.)

**Test checkpoint:**
```
ProductVariantTest::batchAvailableQuantity_matches_instance_method_for_each_variant_individually()
ProductVariantTest::batchAvailableQuantity_returns_zero_for_variant_with_no_movements_or_reservations()
ProductVariantTest::batchAvailableQuantity_issues_exactly_three_queries_regardless_of_variant_count()
```
The third test is the one that actually matters here — assert query count directly (e.g. via `DB::enableQueryLog()` / `assertQueryCountLessThan` if the project has that assertion helper, or a raw `DB::getQueryLog()` count) with both 1 and 20 variant IDs, and confirm the count doesn't grow with N. This is the regression guard against someone "simplifying" the batched method back into a loop later.

---

## 🏭 Model Factories (previously missing — required for every test in this addendum)

**Every Pest test specified elsewhere in this addendum — service layer, policy, widget, and concurrency — depends on factories for the six new models.** None were specified in earlier drafts of this addendum. This is a closeable gap of the same shape as the parent blueprint's own `[FIX v11]` Gap #6 (`LossLedger` was called from `scanToReceive()` but never defined) — a dependency assumed by the spec but never actually supplied. Fixed here.

Add `HasFactory` to each new model (`Supplier`, `Customer`, `PurchaseOrder`, `PurchaseOrderItem`, `SalesOrder`, `SalesOrderItem`) — the addendum's earlier Model Additions section omitted the trait on all six; add it alongside `SoftDeletes` where applicable, matching the parent blueprint's own `ProductVariant`/`TransferRequisition` pattern (`use HasFactory, SoftDeletes;`).

```php
namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'name'           => fake()->company(),
            'contact_person' => fake()->name(),
            'phone'          => fake()->phoneNumber(),
            'email'          => fake()->companyEmail(),
            'address'        => fake()->address(),
            'is_active'      => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
```

```php
namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name'           => fake()->company(),
            'contact_person' => fake()->name(),
            'phone'          => fake()->phoneNumber(),
            'email'          => fake()->companyEmail(),
            'address'        => fake()->address(),
            'is_active'      => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
```

```php
namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'reference_code'    => 'PO-'.fake()->unique()->numerify('######'),
            'supplier_id'       => Supplier::factory(),
            'warehouse_id'      => Warehouse::factory(),
            'status'            => PurchaseOrderStatus::Draft,
            'update_cost_price' => false,
            'ordered_by'        => User::factory(),
        ];
    }

    /**
     * [EDGE CASE SUPPORT] Ordered state — sets ordered_at, does NOT create
     * items. Chain ->has(PurchaseOrderItem::factory()->count(n)) separately,
     * since item count/content varies per test.
     */
    public function ordered(): static
    {
        return $this->state(fn () => [
            'status'     => PurchaseOrderStatus::Ordered,
            'ordered_at' => now(),
        ]);
    }

    public function partiallyReceived(): static
    {
        return $this->state(fn () => [
            'status'     => PurchaseOrderStatus::PartiallyReceived,
            'ordered_at' => now()->subDay(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status'      => PurchaseOrderStatus::Completed,
            'ordered_at'  => now()->subDays(2),
            'received_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status'       => PurchaseOrderStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    public function withCostUpdate(): static
    {
        return $this->state(fn () => ['update_cost_price' => true]);
    }
}
```

```php
namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;

    public function definition(): array
    {
        $qty  = fake()->numberBetween(1, 50);
        $unitRatio = 1;

        return [
            'purchase_order_id'  => PurchaseOrder::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'ordered_unit_name'  => 'pcs',
            'ordered_unit_ratio' => $unitRatio,
            'ordered_qty'        => $qty,
            'ordered_base_qty'   => $qty * $unitRatio,
            'unit_cost_price'    => fake()->randomFloat(4, 1, 500),
            'received_base_qty'  => 0,
        ];
    }

    /**
     * [EDGE CASE SUPPORT] Fully received — required for cancel-guard tests
     * (`cancel_rejected_once_any_stock_received`) and idempotency no-op
     * tests (`receive_purchase` on an item with nothing outstanding).
     */
    public function fullyReceived(): static
    {
        return $this->state(fn (array $attrs) => [
            'received_base_qty' => $attrs['ordered_base_qty'],
        ]);
    }

    public function partiallyReceived(int $receivedBaseQty): static
    {
        return $this->state(fn () => ['received_base_qty' => $receivedBaseQty]);
    }
}
```

```php
namespace Database\Factories;

use App\Enums\SalesOrderStatus;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesOrderFactory extends Factory
{
    protected $model = SalesOrder::class;

    public function definition(): array
    {
        return [
            'reference_code' => 'SO-'.fake()->unique()->numerify('######'),
            'customer_id'    => Customer::factory(),
            'warehouse_id'   => Warehouse::factory(),
            'status'         => SalesOrderStatus::Draft,
            'ordered_by'     => User::factory(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status'       => SalesOrderStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }

    public function partiallyDispatched(): static
    {
        return $this->state(fn () => [
            'status'        => SalesOrderStatus::PartiallyDispatched,
            'confirmed_at'  => now()->subDay(),
            'dispatched_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status'        => SalesOrderStatus::Completed,
            'confirmed_at'  => now()->subDays(2),
            'dispatched_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status'       => SalesOrderStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
```

```php
namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesOrderItemFactory extends Factory
{
    protected $model = SalesOrderItem::class;

    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 30);
        $unitRatio = 1;

        return [
            'sales_order_id'           => SalesOrder::factory(),
            'product_variant_id'       => ProductVariant::factory(),
            'unit_name'                => 'pcs',
            'unit_ratio'               => $unitRatio,
            'qty'                      => $qty,
            'base_qty'                 => $qty * $unitRatio,
            // [EDGE CASE SUPPORT] Deliberately defaults to '0.0000', NOT a
            // random fake price. Per Principle A4, this field is only ever
            // meant to be populated by SalesService::confirmSalesOrder()'s
            // call-time snapshot — a factory default of anything else would
            // let tests accidentally pass by coincidence rather than by
            // actually exercising the snapshot logic. Tests that need a
            // *confirmed* order with a real snapshot value should build the
            // order via the service (confirmSalesOrder()), not by faking
            // this field directly.
            'unit_sale_price_snapshot' => '0.0000',
            'dispatched_base_qty'      => 0,
        ];
    }

    public function dispatched(?int $dispatchedBaseQty = null): static
    {
        return $this->state(fn (array $attrs) => [
            'dispatched_base_qty' => $dispatchedBaseQty ?? $attrs['base_qty'],
        ]);
    }

    public function withSnapshotPrice(string $price): static
    {
        return $this->state(fn () => ['unit_sale_price_snapshot' => $price]);
    }
}
```

**Usage note for the tests already specified elsewhere in this addendum:** wherever a test needs a *realistic* confirmed sales order (with a genuine price snapshot) rather than a raw factory state, build it through `SalesService::confirmSalesOrder()` against a factory-created `Draft` order, not through `SalesOrderItemFactory::withSnapshotPrice()` directly — the latter is for tests that need to assert something *about* the snapshot value itself (e.g. `SalesServiceTest::confirm_snapshots_sale_price_at_confirm_time_not_dispatch_time`) without caring how it got there, while the former is for tests that need the snapshot to have been produced by the actual code path under test.

**Test checkpoint:** `FactoryTest::all_six_new_model_factories_produce_valid_persistable_records()` — a single smoke test creating one of each new factory (and its default nested relations) and asserting it saves without constraint violations. Cheap insurance against a factory silently drifting out of sync with its migration as the schema evolves.

---

## 🏷️ New Enums

```php
namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum PurchaseOrderStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Ordered = 'ordered';
    case PartiallyReceived = 'partially_received';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Ordered => __('Ordered'),
            self::PartiallyReceived => __('Partially received'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Ordered => 'info',
            self::PartiallyReceived => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'gray',
        };
    }

    public function getIcon(): string|BackedEnum|null
    {
        return match ($this) {
            self::Draft => Heroicon::DocumentText,
            self::Ordered => Heroicon::PaperAirplane,
            self::PartiallyReceived => Heroicon::ArchiveBoxArrowDown,
            self::Completed => Heroicon::CheckBadge,
            self::Cancelled => Heroicon::XCircle,
        };
    }
}

enum SalesOrderStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case PartiallyDispatched = 'partially_dispatched';
    case Dispatched = 'dispatched';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Confirmed => __('Confirmed'),
            self::PartiallyDispatched => __('Partially dispatched'),
            self::Dispatched => __('Dispatched'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Confirmed => 'primary',
            self::PartiallyDispatched => 'warning',
            self::Dispatched => 'info',
            self::Completed => 'success',
            self::Cancelled => 'gray',
        };
    }

    public function getIcon(): string|BackedEnum|null
    {
        return match ($this) {
            self::Draft => Heroicon::DocumentText,
            self::Confirmed => Heroicon::CheckCircle,
            self::PartiallyDispatched => Heroicon::ArchiveBoxArrowDown,
            self::Dispatched => Heroicon::Truck,
            self::Completed => Heroicon::CheckBadge,
            self::Cancelled => Heroicon::XCircle,
        };
    }
}
```

Add `Purchase`, `Sale`, `SaleReturn`, `PurchaseReturn` cases to the existing `StockMovementType` enum (same file, no new file).

---

## ⚙️ Service Layer — `PurchaseService` and extend `InventoryService`

Both new services follow the exact locking/transaction/guard discipline of `InventoryService::dispatchTransfer()` / `scanToReceive()` — pessimistic row locks, atomic transactions, unit-ratio validation, bcmath for money.

### `[DRY v11.1]` Shared over-fulfillment guard trait

`PurchaseService::receivePurchase()` and `SalesService::dispatchSale()` both need the identical shape of check — "does this incoming quantity exceed what's still outstanding on this line item?" — and both throw an identically-worded exception. Extracted here so the wording and the comparison logic exist in exactly one place, rather than as two copies that could silently drift (e.g. one gets an off-by-one fix the other doesn't).

```php
namespace App\Services\Concerns;

use Exception;

/**
 * [Added v11.1] Shared by PurchaseService and SalesService. Both need to
 * reject an incoming quantity that exceeds a line item's outstandingBaseQty()
 * — PurchaseOrderItem and SalesOrderItem both expose that method with
 * identical semantics (see Model Additions section), so the guard itself
 * doesn't need to know which kind of item it's validating.
 */
trait GuardsOutstandingQuantity
{
    /**
     * @param  object{outstandingBaseQty: callable}  $item  Any model exposing outstandingBaseQty(): int
     * @throws Exception if $incomingQty exceeds the item's outstanding quantity
     */
    protected function assertWithinOutstanding(object $item, int $incomingQty, string $verb, int $itemId): void
    {
        $remaining = $item->outstandingBaseQty();

        if ($incomingQty > $remaining) {
            throw new Exception(
                "Cannot {$verb} {$incomingQty} units for item #{$itemId}: only ".
                "{$remaining} units remain outstanding on this order."
            );
        }
    }
}
```

Both services below `use GuardsOutstandingQuantity;` and call `$this->assertWithinOutstanding($item, $incomingQty, 'receive', $item->id)` / `$this->assertWithinOutstanding($item, $qty, 'dispatch', $item->id)` in place of their own inline `if ($incomingQty > $remaining) { throw ... }` blocks.

```php
namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ProductVariantPrice;
use App\Models\StockMovement;
use Exception;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    use \App\Services\Concerns\GuardsOutstandingQuantity;

    public function orderPurchase(PurchaseOrder $po): void
    {
        if ($po->status !== PurchaseOrderStatus::Draft) {
            throw new Exception("Purchase order must be in draft to be ordered. Current: {$po->status->value}.");
        }

        if ($po->items()->count() === 0) {
            throw new Exception('Purchase order must have at least one line item.');
        }

        $po->update([
            'status'     => PurchaseOrderStatus::Ordered,
            'ordered_at' => now(),
        ]);
    }

    /**
     * Receives a purchase order, in full or in part. Mirrors
     * InventoryService::scanToReceive()'s incremental-receipt pattern,
     * but without the loss/damage machinery — see Addendum Principle A7.
     *
     * $receivedItemsData: [purchase_order_item_id => received_base_qty, ...]
     */
    public function receivePurchase(int $purchaseOrderId, array $receivedItemsData): void
    {
        DB::transaction(function () use ($purchaseOrderId, $receivedItemsData) {
            $po = PurchaseOrder::with('items.productVariant.currentPrice')
                ->lockForUpdate()
                ->findOrFail($purchaseOrderId);

            $allowed = [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::PartiallyReceived];

            if (! in_array($po->status, $allowed, true)) {
                throw new Exception("Purchase order not in a receivable state. Current: {$po->status->value}.");
            }

            foreach ($po->items as $item) {
                if (! isset($receivedItemsData[$item->id])) {
                    continue;
                }

                $incomingQty = (int) $receivedItemsData[$item->id];

                if ($incomingQty <= 0) {
                    continue;
                }

                // [EDGE CASE] Over-receipt guard: never allow receiving more
                // than was ordered. Supplier over-shipments must be handled
                // as a separate line item / PO amendment, not silently
                // absorbed here — this keeps ordered_base_qty a reliable
                // upper bound for reporting.
                $this->assertWithinOutstanding($item, $incomingQty, 'receive', $item->id);

                $variant = ProductVariant::with('currentPrice')->lockForUpdate()->findOrFail($item->product_variant_id);

                StockMovement::create([
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id'       => $po->warehouse_id,
                    'type'               => StockMovementType::Purchase,
                    'quantity'           => $incomingQty,
                    'unit_name_used'     => $item->ordered_unit_name,
                    'unit_ratio_used'    => $item->ordered_unit_ratio,
                    'reference_type'     => PurchaseOrder::class,
                    'reference_id'       => (string) $po->id,
                    'reference_code'     => $po->reference_code,
                    'created_by'         => auth()->id(),
                ]);

                // A4: opt-in cost-price update, one new is_current row per
                // line item, only if the PO's unit_cost_price differs from
                // the variant's existing current cost.
                if ($po->update_cost_price) {
                    $currentCost = $variant->currentPrice?->cost_price;

                    if ($currentCost === null || bccomp((string) $currentCost, (string) $item->unit_cost_price, 4) !== 0) {
                        ProductVariantPrice::where('product_variant_id', $variant->id)
                            ->where('is_current', true)
                            ->update(['is_current' => false]);

                        ProductVariantPrice::create([
                            'product_variant_id' => $variant->id,
                            'cost_price'         => $item->unit_cost_price,
                            'sale_price'         => $variant->currentPrice?->sale_price ?? '0.0000',
                            'effective_from'     => now(),
                            'is_current'         => true,
                            'set_by'             => auth()->id(),
                            'notes'              => "Auto-updated from PO {$po->reference_code}",
                        ]);
                    }
                }

                $item->update(['received_base_qty' => $item->received_base_qty + $incomingQty]);
            }

            $allReceived = $po->items()
                ->whereColumn('received_base_qty', '<', 'ordered_base_qty')
                ->doesntExist();

            $po->update([
                'status'      => $allReceived ? PurchaseOrderStatus::Completed : PurchaseOrderStatus::PartiallyReceived,
                'received_by' => auth()->id(),
                'received_at' => $allReceived ? now() : $po->received_at,
            ]);
        });
    }

    public function cancelPurchaseOrder(PurchaseOrder $po): void
    {
        // Mirrors [FIX v11] Principle #14: no reversal pathway needed
        // because cancellation is only legal before any stock has moved.
        if ($po->items()->where('received_base_qty', '>', 0)->exists()) {
            throw new Exception(
                'Cannot cancel a purchase order that has already received stock. '.
                'Use a return/adjustment instead.'
            );
        }

        $po->update([
            'status'       => PurchaseOrderStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
```

```php
namespace App\Services;

use App\Enums\SalesOrderStatus;
use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use Exception;
use Illuminate\Support\Facades\DB;

class SalesService
{
    use \App\Services\Concerns\GuardsOutstandingQuantity;

    public function confirmSalesOrder(SalesOrder $order): void
    {
        if ($order->status !== SalesOrderStatus::Draft) {
            throw new Exception("Sales order must be in draft to confirm. Current: {$order->status->value}.");
        }

        DB::transaction(function () use ($order) {
            // Snapshot sale price at confirm-time (A4) — never recalculated later.
            foreach ($order->items as $item) {
                $variant = ProductVariant::with('currentPrice')->findOrFail($item->product_variant_id);
                $item->update([
                    'unit_sale_price_snapshot' => $variant->currentPrice?->sale_price ?? '0.0000',
                ]);
            }

            $order->update([
                'status'       => SalesOrderStatus::Confirmed,
                'confirmed_at' => now(),
            ]);
        });
    }

    /**
     * Dispatches stock against a confirmed sales order. Supports partial
     * dispatch (e.g., available stock now, backorder the rest).
     *
     * $dispatchData: [sales_order_item_id => dispatch_base_qty, ...]
     */
    public function dispatchSale(int $salesOrderId, array $dispatchData): void
    {
        DB::transaction(function () use ($salesOrderId, $dispatchData) {
            $order = SalesOrder::with('items.productVariant')
                ->lockForUpdate()
                ->findOrFail($salesOrderId);

            $allowed = [SalesOrderStatus::Confirmed, SalesOrderStatus::PartiallyDispatched];

            if (! in_array($order->status, $allowed, true)) {
                throw new Exception("Sales order not in a dispatchable state. Current: {$order->status->value}.");
            }

            foreach ($order->items as $item) {
                if (! isset($dispatchData[$item->id])) {
                    continue;
                }

                $qty = (int) $dispatchData[$item->id];

                if ($qty <= 0) {
                    continue;
                }

                $this->assertWithinOutstanding($item, $qty, 'dispatch', $item->id);

                $variant = ProductVariant::lockForUpdate()->findOrFail($item->product_variant_id);
                $onHand  = $variant->onHandQuantity($order->warehouse_id);

                // [EDGE CASE] Sales, unlike internal transfers, must never
                // be allowed to drive on-hand stock negative — there is no
                // internal warehouse absorbing the deficit. This check is
                // deliberately independent of recordMovement()'s own
                // insufficient-stock guard so the exception message is
                // sales-context-specific.
                if ($onHand < $qty) {
                    throw new Exception(
                        "Insufficient stock for SKU {$variant->sku} at warehouse ID {$order->warehouse_id}. ".
                        "Available: {$onHand}, requested dispatch: {$qty}."
                    );
                }

                StockMovement::create([
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id'       => $order->warehouse_id,
                    'type'               => StockMovementType::Sale,
                    'quantity'           => -$qty,
                    'unit_name_used'     => $item->unit_name,
                    'unit_ratio_used'    => $item->unit_ratio,
                    'reference_type'     => SalesOrder::class,
                    'reference_id'       => (string) $order->id,
                    'reference_code'     => $order->reference_code,
                    'created_by'         => auth()->id(),
                ]);

                $item->update(['dispatched_base_qty' => $item->dispatched_base_qty + $qty]);
            }

            $allDispatched = $order->items()
                ->whereColumn('dispatched_base_qty', '<', 'base_qty')
                ->doesntExist();

            $order->update([
                'status'         => $allDispatched ? SalesOrderStatus::Completed : SalesOrderStatus::PartiallyDispatched,
                'dispatched_by'  => auth()->id(),
                'dispatched_at'  => $order->dispatched_at ?? now(),
            ]);
        });
    }

    public function cancelSalesOrder(SalesOrder $order): void
    {
        // Same philosophy as [FIX v11] Principle #14 / CancelAction: illegal
        // once any stock has left the warehouse. No reversal pathway.
        if (in_array($order->status, [SalesOrderStatus::PartiallyDispatched, SalesOrderStatus::Dispatched, SalesOrderStatus::Completed], true)) {
            throw new Exception(
                "Cannot cancel sales order once dispatch has begun. Current: {$order->status->value}. ".
                'Use a sales return instead.'
            );
        }

        $order->update([
            'status'       => SalesOrderStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Records a customer return against an already-dispatched sale.
     * Positive movement, distinct StockMovementType so it's never confused
     * with a fresh Purchase in reporting.
     */
    public function recordSalesReturn(int $salesOrderItemId, int $returnedBaseQty, ?string $notes = null): StockMovement
    {
        if ($returnedBaseQty <= 0) {
            throw new Exception('Returned quantity must be a positive number of base units.');
        }

        return DB::transaction(function () use ($salesOrderItemId, $returnedBaseQty, $notes) {
            $item = \App\Models\SalesOrderItem::with('salesOrder')->lockForUpdate()->findOrFail($salesOrderItemId);

            // [EDGE CASE] Cannot return more than was actually dispatched.
            if ($returnedBaseQty > $item->dispatched_base_qty) {
                throw new Exception(
                    "Cannot return {$returnedBaseQty} units: only {$item->dispatched_base_qty} ".
                    "units were dispatched for item #{$item->id}."
                );
            }

            return StockMovement::create([
                'product_variant_id' => $item->product_variant_id,
                'warehouse_id'       => $item->salesOrder->warehouse_id,
                'type'               => StockMovementType::SaleReturn,
                'quantity'           => $returnedBaseQty,
                'unit_name_used'     => $item->unit_name,
                'unit_ratio_used'    => $item->unit_ratio,
                'reference_type'     => \App\Models\SalesOrder::class,
                'reference_id'       => (string) $item->sales_order_id,
                'reference_code'     => $item->salesOrder->reference_code,
                'notes'              => $notes,
                'created_by'         => auth()->id(),
            ]);
        });
    }
}
```

**Pest coverage added (service layer):**
```
PurchaseServiceTest::order_throws_when_no_items()
PurchaseServiceTest::order_throws_when_not_draft()
PurchaseServiceTest::receive_purchase_supports_partial_batches()
PurchaseServiceTest::receive_purchase_rejects_over_receipt_beyond_ordered_qty()
PurchaseServiceTest::receive_purchase_updates_cost_price_when_flag_set()
PurchaseServiceTest::receive_purchase_does_not_update_cost_price_when_flag_unset()
PurchaseServiceTest::receive_purchase_skips_price_update_when_cost_unchanged()
PurchaseServiceTest::receive_purchase_sets_completed_when_fully_received()
PurchaseServiceTest::receive_purchase_sets_partially_received_when_incomplete()
PurchaseServiceTest::cancel_rejected_once_any_stock_received()
PurchaseServiceTest::cancel_succeeds_while_fully_unreceived()
SalesServiceTest::confirm_snapshots_sale_price_at_confirm_time_not_dispatch_time()
SalesServiceTest::confirm_throws_when_not_draft()
SalesServiceTest::dispatch_supports_partial_batches()
SalesServiceTest::dispatch_rejects_over_dispatch_beyond_ordered_qty()
SalesServiceTest::dispatch_rejects_when_on_hand_insufficient()
SalesServiceTest::dispatch_does_not_touch_reservedQuantity_transfers_scope()
SalesServiceTest::dispatch_sets_completed_when_fully_dispatched()
SalesServiceTest::dispatch_sets_partially_dispatched_when_incomplete()
SalesServiceTest::cancel_rejected_once_dispatch_has_begun()
SalesServiceTest::cancel_succeeds_while_draft_or_confirmed()
SalesServiceTest::sales_return_rejected_beyond_dispatched_qty()
SalesServiceTest::sales_return_creates_positive_sale_return_movement()
ConcurrencyTest::simultaneous_sales_dispatch_against_same_variant_does_not_oversell()
```

---

## 📋 Filament Resources

### PurchaseOrderResource

**Model:** `App\Models\PurchaseOrder` · **Navigation Group:** PURCHASING (new group) · **Base Route:** `/admin/purchase-orders`

- Wizard-style create (mirrors `TransferRequisitionForm`): Step 1 (Supplier & Warehouse), Step 2 (Line Items repeater — variant, unit, qty, cost price), Step 3 (Review, with `update_cost_price` toggle).
- Table actions: `orderPurchase` (draft→ordered), `receivePurchase` modal (per-item qty inputs, defaulting to `outstandingBaseQty()`), `cancel` (visible only in `Draft`/`Ordered` **and** no received qty yet — matches service guard).
- Infolist: header profile (reference, supplier, warehouse, status badge), line items repeatable entry (ordered vs received base qty, unit cost).

### SalesOrderResource

**Model:** `App\Models\SalesOrder` · **Navigation Group:** SALES (new group) · **Base Route:** `/admin/sales-orders`

- Wizard-style create: Step 1 (Customer & Warehouse), Step 2 (Line Items — variant, unit, qty; sale price shown read-only from `currentPrice`, not editable pre-confirm), Step 3 (Review).
- Table actions: `confirm` (draft→confirmed, snapshots price), `dispatchSale` modal (per-item qty inputs, defaulting to `outstandingBaseQty()`, blocked client-side if `onHandQuantity < requested` — server still re-validates), `recordReturn` modal (visible once any item has `dispatched_base_qty > 0`), `cancel` (visible only in `Draft`/`Confirmed`).
- Infolist: mirrors `TransferRequisitionInfolist` structure — profile grid, line items repeatable entry with ordered/dispatched/outstanding columns and a computed line-total column (`lineTotal()`).

### SupplierResource / CustomerResource

Simple CRUD resources, System Admin-style (like `WarehouseResource`) — drawer forms, `Width::Large`, no wizard needed.

---

## 📐 Concrete Resource Sketch — PurchaseOrderResource & SalesOrderResource

The following is a working sketch, not final production code — it follows the parent blueprint's thin-Resource / `Schemas/` / `Tables/` directory split (Section 1) exactly, so an implementer can drop these files into the same directory shape as `TransferRequisitionResource`. Names, field lists, and modal widths match the abstract description above; adjust only where Phase 0 of the implementation prompt's codebase audit finds real divergence.

> **`[Verified v11.1]`** Cross-checked against Filament v5's own documentation (`filamentphp.com/docs/5.x`) across two passes (2026-09-21 and a follow-up full pass afterward), covering every distinct Filament construct used in this sketch:
> - **Directory structure:** Form, Table, and Infolist classes all live under one `Schemas/` subdirectory (no separate `Infolists/` folder); Table classes get their own `Tables/` subdirectory. Matches the parent blueprint's own `TransferRequisitionInfolist` placement and this addendum's directory tree below. ✅
> - **`Grid` / `Section`:** `Filament\Schemas\Components\Grid` and `Filament\Schemas\Components\Section`, both taking `->schema([...])` and `->columnSpan()`, are exactly as documented. ✅
> - **`Wizard` / `Step`:** `Filament\Schemas\Components\Wizard` and `Filament\Schemas\Components\Wizard\Step` match the documented pattern exactly, including a `Step::make('Order')->schema([...])` per step. Note: Filament v5 also ships an alternative, more modern `CreateRecord\Concerns\HasWizard` trait + `getSteps()` approach for wizard-based create pages, which this addendum does not use, because the parent blueprint's own `TransferRequisitionForm` builds its wizard directly inside the Resource's `form()` schema instead — this addendum follows the parent's existing pattern for consistency rather than introducing a second, different official pattern into the same codebase. Both are valid Filament v5 patterns; this is a consistency choice, not a correctness one. ✅
> - **`Repeater` + `->relationship()` on create pages — corrected during this verification pass.** An earlier revision of this addendum removed `->relationship()` from the `items` Repeaters, based on an unverified assumption that a create-page wizard couldn't reliably bind a Repeater to a `hasMany` relationship before the parent record exists. Direct doc verification shows this was likely wrong: Filament's standard `CreateRecord` page automatically calls `saveRelationships()` after the parent is created, which is the documented, built-in mechanism for exactly this case. `->relationship()` has been restored on both `PurchaseOrderForm` and `SalesOrderForm`'s `items` Repeaters, and the now-unnecessary custom `CreatesRecordWithLineItems` trait has been removed — see the corrected `CreatePurchaseOrder.php` / `CreateSalesOrder.php` section for the full explanation, including an open question (not resolved here) about whether the parent blueprint's own `TransferRequisitionForm` has the same unverified omission or a deliberate reason for it not visible in the source document. ⚠️ Corrected — see inline note.
> - **Table actions with dynamic `->schema()` closures:** confirmed a `->schema([...])` closure can be a function of the acted-upon `$record` (injected as a typed parameter, same as `->action(function (array $data, Post $record) {...})`), which is exactly what `receivePurchase`'s and `dispatchSale`'s per-line-item dynamic modals rely on. ✅
> - **`Select::make(...)->options(EnumClass::class)`:** confirmed as the official pattern for backed enums implementing `HasLabel`, matching every `SelectFilter`/`Select` usage against `PurchaseOrderStatus`/`SalesOrderStatus` throughout this addendum. ✅
> - **`Select::make(...)->native(false)`:** confirmed as the documented way to switch to the JS-based dropdown, used correctly in `AdminReviewFilters::period()`'s preset selector. ✅
> - **`DatePicker` inside a custom `Filter::make(...)->schema([...])`:** confirmed as the exact officially-documented pattern for building custom date-based table filters — `AdminReviewFilters::period()` matches this closely. ✅
> - **`->indicateUsing()` — improved during this verification pass.** Confirmed the method's documented signature and behavior, and found the docs show a cleaner idiom than this addendum's original single-string return for a multi-field case like `custom_range`: returning an array of `Indicator::make(...)->removeField(...)` objects lets each date be cleared independently from the active-filters bar. `AdminReviewFilters::period()` has been updated to use this pattern for its `custom_range` case. ⚠️ Improved — see the method's inline comment.
> - **`RepeatableEntry::make(...)->schema([...])`:** confirmed this is Filament's documented way to render both plain-array and Eloquent-relationship-backed repeating infolist data automatically — matches every infolist sketch in this addendum (and the parent blueprint's own `TransferRequisitionInfolist`). ✅

```
app/Filament/Resources/
├── PurchaseOrders/
│   ├── PurchaseOrderResource.php
│   ├── Pages/
│   │   ├── ListPurchaseOrders.php
│   │   ├── CreatePurchaseOrder.php
│   │   ├── EditPurchaseOrder.php
│   │   └── ViewPurchaseOrder.php
│   ├── Schemas/
│   │   ├── PurchaseOrderForm.php
│   │   └── PurchaseOrderInfolist.php
│   └── Tables/
│       └── PurchaseOrdersTable.php
│
├── SalesOrders/
│   ├── SalesOrderResource.php
│   ├── Pages/
│   │   ├── ListSalesOrders.php
│   │   ├── CreateSalesOrder.php
│   │   ├── EditSalesOrder.php
│   │   └── ViewSalesOrder.php
│   ├── Schemas/
│   │   ├── SalesOrderForm.php
│   │   └── SalesOrderInfolist.php
│   └── Tables/
│       └── SalesOrdersTable.php
│
├── Suppliers/
│   ├── SupplierResource.php
│   ├── Pages/{ListSuppliers,CreateSupplier,EditSupplier}.php
│   └── Schemas/SupplierForm.php
│
└── Customers/
    ├── CustomerResource.php
    ├── Pages/{ListCustomers,CreateCustomer,EditCustomer}.php
    └── Schemas/CustomerForm.php
```

### PurchaseOrderResource.php (thin resource class)

```php
namespace App\Filament\Resources\PurchaseOrders;

use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use App\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderInfolist;
use App\Filament\Resources\PurchaseOrders\Tables\PurchaseOrdersTable;
use App\Models\PurchaseOrder;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string | \UnitEnum | null $navigationGroup = 'PURCHASING';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'reference_code';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedShoppingCart;

    public static function form(Schema $schema): Schema
    {
        return PurchaseOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseOrdersTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PurchaseOrderInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['supplier', 'warehouse', 'items.productVariant']);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'view'   => ViewPurchaseOrder::route('/{record}'),
            'edit'   => EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
```

### PurchaseOrderForm.php (create wizard)

```php
namespace App\Filament\Resources\PurchaseOrders\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;

class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make([
                Step::make('Supplier & Warehouse')
                    ->schema([
                        Select::make('supplier_id')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm(fn (Schema $schema) => \App\Filament\Resources\Suppliers\Schemas\SupplierForm::configure($schema)),
                        Select::make('warehouse_id')
                            ->label('Receiving Warehouse')
                            ->options(fn () => auth()->user()->warehouses()->pluck('name', 'id'))
                            ->default(fn () => auth()->user()->warehouses()->count() === 1
                                ? auth()->user()->warehouses()->first()->id
                                : null)
                            ->required(),
                    ]),
                Step::make('Line Items')
                    ->schema([
                        // [Corrected v11.1 — see "Re-verified against
                        // Filament v5 docs via Context7" note further below
                        // in this document] An earlier revision of this
                        // addendum removed ->relationship('items') here,
                        // reasoning that a create-page wizard has no
                        // persisted parent record for a Repeater to bind
                        // against mid-wizard. That reasoning was NOT
                        // verified against Filament's actual documentation
                        // at the time, and a direct doc check now shows it
                        // was likely wrong: Filament's own CreateRecord page
                        // class automatically calls saveRelationships()
                        // after the parent record is created, which is
                        // exactly the documented, supported mechanism for a
                        // ->relationship()-bound Repeater on a create page —
                        // this is standard behavior, not something that
                        // needs a hand-rolled trait to work around. Kept
                        // here per the addendum's later correction — see the
                        // note for the full explanation and what changed.
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Select::make('product_variant_id')
                                    ->label('Variant (SKU)')
                                    ->relationship('productVariant', 'sku')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                TextInput::make('ordered_unit_name')
                                    ->label('Unit')
                                    ->required(),
                                TextInput::make('ordered_unit_ratio')
                                    ->label('Unit Ratio (to base)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required(),
                                TextInput::make('ordered_qty')
                                    ->label('Ordered Qty')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, $set) => $set(
                                        'ordered_base_qty',
                                        (int) $get('ordered_qty') * (int) $get('ordered_unit_ratio')
                                    )),
                                TextInput::make('ordered_base_qty')
                                    ->label('Base Qty (computed)')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(),
                                TextInput::make('unit_cost_price')
                                    ->label('Unit Cost Price')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->minValue(0)
                                    ->required(),
                            ])
                            ->columns(3)
                            ->minItems(1)
                            ->required(),
                    ]),
                Step::make('Review & Verify')
                    ->schema([
                        Toggle::make('update_cost_price')
                            ->label('Update catalog cost price on receipt')
                            ->helperText('If enabled, receiving this PO will set each variant\'s current cost price to this order\'s unit cost, if different.')
                            ->default(false),
                        Placeholder::make('review_summary')
                            ->content(fn (Get $get) => view(
                                'filament.wizards.purchase-order-review',
                                ['state' => $get()],
                            )),
                    ]),
            ])
                ->modalWidth(Width::SevenExtraLarge)
                ->closeModalByClickingAway(false),
        ]);
    }
}
```

### CreatePurchaseOrder.php & CreateSalesOrder.php (Pages/ classes)

**`[Corrected v11.1 — re-verified against Filament v5 docs via Context7]`** An earlier revision of this addendum specified a hand-rolled `CreatesRecordWithLineItems` trait here, reasoning that a create-page wizard's `Repeater` couldn't reliably bind to a `hasMany` relationship before the parent record exists. That reasoning was not checked against Filament's actual documentation at the time it was written. A direct doc check now shows it was very likely wrong: **Filament's standard `CreateRecord` page class automatically calls `saveRelationships()` after the parent record is created**, which is exactly the documented, built-in mechanism for a `->relationship()`-bound `Repeater` (or any relationship-bound component) on a create page — the framework already handles "create parent, then create children against it," and does so specifically *because* the children can't exist until the parent has an ID. This is standard behavior for any resource using the default `CreateRecord` page, wizard or not; the docs draw no distinction for wizards.

**What this means for `PurchaseOrderForm`/`SalesOrderForm` above:** `->relationship()` has been restored on both `items` Repeaters (see the corrected comments there). With that restored, `CreatePurchaseOrder`/`CreateSalesOrder` need **no special `afterCreate()`/`mutateFormDataBeforeCreate()` logic for the items themselves at all** — Filament handles it. The `CreatesRecordWithLineItems` trait from the earlier revision is no longer needed for that purpose and has been removed from this addendum.

**What each page class still legitimately needs** — parent-record fields that aren't part of the wizard's own visible inputs (a generated `reference_code`, stamping `ordered_by` with the acting user) — via the standard, documented `mutateFormDataBeforeCreate()` hook, same as any ordinary Filament create page:

```php
namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reference_code'] = $data['reference_code'] ?? 'PO-'.now()->format('YmdHis').'-'.random_int(100, 999);
        $data['ordered_by'] = auth()->id();

        return $data;
    }
}
```

```php
namespace App\Filament\Resources\SalesOrders\Pages;

use App\Filament\Resources\SalesOrders\SalesOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesOrder extends CreateRecord
{
    protected static string $resource = SalesOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reference_code'] = $data['reference_code'] ?? 'SO-'.now()->format('YmdHis').'-'.random_int(100, 999);
        $data['ordered_by'] = auth()->id();

        return $data;
    }
}
```

Note `unit_sale_price_snapshot` needs no special handling here either — it's simply absent from the `SalesOrderForm`'s line-item schema (the form only collects `product_variant_id`, `unit_name`, `unit_ratio`, `qty`, `base_qty`), so `Repeater::make('items')->relationship()` creates each `SalesOrderItem` without that column, and it falls through to the model's `'0.0000'` default automatically — no code needs to actively "leave it unset."

**`[Open verification item — flagged for Phase 0 of the implementation prompt, not resolved here]`** The parent v11.0 blueprint's own `TransferRequisitionForm` (shown in the source blueprint document) omits `->relationship('items')` on its Repeater, which was this addendum's original justification for the now-reverted trait. Given what Filament's docs actually show, one of two things is true, and this addendum cannot determine which from the document alone: **(a)** the parent blueprint's own document has a similar unverified simplification worth double-checking against the real codebase, or **(b)** `CreateTransferRequisition.php` in the real codebase does something the document doesn't show (e.g. a custom, non-standard create flow that bypasses `CreateRecord`'s automatic relationship-saving, for a reason not stated in the blueprint text) that makes omitting `->relationship()` there deliberate and correct. Phase 0 of the implementation prompt should check the real `CreateTransferRequisition.php` and `TransferRequisitionForm.php` directly, and treat whichever pattern the *real, working* code uses as authoritative for both the parent's own resource and this addendum's two new ones — consistency between the three wizard-based create flows matters more than which one this document guessed correctly.

**`ListPurchaseOrders.php`, `EditPurchaseOrder.php`, `ViewPurchaseOrder.php`, `ListSalesOrders.php`, `EditSalesOrder.php`, `ViewSalesOrder.php`** all follow the parent blueprint's standard `ListRecords`/`EditRecord`/`ViewRecord` boilerplate (Section 1's directory structure) — no addendum-specific logic needed for any of the six, since editing is restricted to `Draft` status only (table action visibility) where a straightforward relationship-bound repeater re-save is safe and, per the correction above, requires no special handling either.

**Test checkpoint:**
```
CreatePurchaseOrderTest::creates_purchase_order_and_all_line_items_via_standard_relationship_repeater()
CreatePurchaseOrderTest::generates_reference_code_when_not_supplied()
CreateSalesOrderTest::creates_sales_order_and_all_line_items_via_standard_relationship_repeater()
CreateSalesOrderTest::leaves_unit_sale_price_snapshot_at_default_until_confirmed()
```

### PurchaseOrdersTable.php

```php
namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_code')
                    ->label('REFERENCE')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('supplier.name')
                    ->label('SUPPLIER')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('warehouse.name')
                    ->label('WAREHOUSE')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('items_count')
                    ->label('LINE ITEMS')
                    ->counts('items')
                    ->numeric(),
                TextColumn::make('ordered_at')
                    ->label('ORDERED')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('received_at')
                    ->label('RECEIVED')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(PurchaseOrderStatus::class),
                SelectFilter::make('supplier_id')->relationship('supplier', 'name')->label('Supplier'),
                SelectFilter::make('warehouse_id')->relationship('warehouse', 'name')->label('Warehouse'),
                TrashedFilter::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),

                EditAction::make()
                    ->visible(fn (PurchaseOrder $record) => $record->status === PurchaseOrderStatus::Draft)
                    ->modalWidth(Width::Large),

                Action::make('orderPurchase')
                    ->label('ORDER')
                    ->icon(Heroicon::PaperAirplane)
                    ->color('primary')
                    ->authorize('orderPurchase')
                    ->visible(fn (PurchaseOrder $record) => $record->status === PurchaseOrderStatus::Draft)
                    ->requiresConfirmation()
                    ->action(fn (PurchaseOrder $record) => app(\App\Services\PurchaseService::class)->orderPurchase($record)),

                // [Addendum v11.1] Receive modal — per-item qty inputs, default to outstanding.
                Action::make('receivePurchase')
                    ->label('RECEIVE')
                    ->icon(Heroicon::ArchiveBoxArrowDown)
                    ->color('success')
                    ->authorize('receivePurchase')
                    ->visible(fn (PurchaseOrder $record) => in_array($record->status, [
                        PurchaseOrderStatus::Ordered,
                        PurchaseOrderStatus::PartiallyReceived,
                    ]))
                    ->modalWidth(Width::FourExtraLarge)
                    ->schema(fn (PurchaseOrder $record) => collect($record->items)
                        ->map(fn ($item) => TextInput::make("received.{$item->id}")
                            ->label("{$item->productVariant->sku} — outstanding {$item->outstandingBaseQty()} {$item->ordered_unit_name}")
                            ->numeric()
                            ->minValue(0)
                            ->maxValue($item->outstandingBaseQty())
                            ->default($item->outstandingBaseQty())
                        )
                        ->all())
                    ->action(function (array $data, PurchaseOrder $record) {
                        $received = collect($data['received'] ?? [])
                            ->filter(fn ($qty) => (int) $qty > 0)
                            ->mapWithKeys(fn ($qty, $itemId) => [(int) $itemId => (int) $qty])
                            ->all();

                        app(\App\Services\PurchaseService::class)->receivePurchase($record->id, $received);

                        Notification::make()->title('Purchase order received')->success()->send();
                    })
                    ->requiresConfirmation(),

                Action::make('cancelPurchase')
                    ->label('CANCEL')
                    ->icon(Heroicon::XMark)
                    ->color('danger')
                    ->authorize('cancelPurchase')
                    ->visible(fn (PurchaseOrder $record) => in_array($record->status, [
                        PurchaseOrderStatus::Draft,
                        PurchaseOrderStatus::Ordered,
                    ]) && $record->items->every(fn ($item) => $item->received_base_qty === 0))
                    ->requiresConfirmation()
                    ->action(fn (PurchaseOrder $record) => app(\App\Services\PurchaseService::class)->cancelPurchaseOrder($record)),

                DeleteAction::make()
                    ->authorize('delete')
                    ->visible(fn (PurchaseOrder $record) => in_array($record->status, [
                        PurchaseOrderStatus::Draft,
                        PurchaseOrderStatus::Cancelled,
                    ])),

                RestoreAction::make()->authorize('restore'),

                ForceDeleteAction::make()
                    ->authorize('forceDelete')
                    ->visible(fn () => auth()->user()->isAdmin()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorize('deleteAny'),
                    RestoreBulkAction::make()->authorize('restoreAny'),
                    ForceDeleteBulkAction::make()->authorize('forceDeleteAny'),
                ]),
            ]);
    }
}
```

**Note on the `receivePurchase` action's `->schema()` closure:** building per-item dynamic form fields keyed by `received.{itemId}` is the same technique the parent blueprint uses conceptually for `recordLoss` (Section 6, TransferRequisitionResource) but generalized to N items instead of one. `maxValue($item->outstandingBaseQty())` gives client-side over-receipt prevention; the server-side guard inside `PurchaseService::receivePurchase()` is what's actually authoritative (never trust the client-side max alone — this mirrors the addendum's own edge-case note that `dispatchSale`'s client-side block on insufficient stock does not replace the server re-validation).

### SalesOrderResource.php (thin resource class — previously missing from the sketch; mirrors `PurchaseOrderResource.php` exactly, DRY by direct symmetry)

```php
namespace App\Filament\Resources\SalesOrders;

use App\Filament\Resources\SalesOrders\Pages\CreateSalesOrder;
use App\Filament\Resources\SalesOrders\Pages\EditSalesOrder;
use App\Filament\Resources\SalesOrders\Pages\ListSalesOrders;
use App\Filament\Resources\SalesOrders\Pages\ViewSalesOrder;
use App\Filament\Resources\SalesOrders\Schemas\SalesOrderForm;
use App\Filament\Resources\SalesOrders\Schemas\SalesOrderInfolist;
use App\Filament\Resources\SalesOrders\Tables\SalesOrdersTable;
use App\Models\SalesOrder;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SalesOrderResource extends Resource
{
    protected static ?string $model = SalesOrder::class;

    protected static string | \UnitEnum | null $navigationGroup = 'SALES';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'reference_code';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function form(Schema $schema): Schema
    {
        return SalesOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesOrdersTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalesOrderInfolist::configure($schema);
    }

    // [Query optimization — same reasoning as PurchaseOrderResource] Eager-load
    // items.productVariant so every table row's line-item-count column and
    // every action's ->visible()/->schema() closure below reads relations
    // already in memory, never triggering a lazy-load per row. This is what
    // makes the dispatchSale modal's per-item ->productVariant->sku label
    // (Filament Resources section) free — the relation is already hydrated
    // by the time the modal opens.
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['customer', 'warehouse', 'items.productVariant']);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListSalesOrders::route('/'),
            'create' => CreateSalesOrder::route('/create'),
            'view'   => ViewSalesOrder::route('/{record}'),
            'edit'   => EditSalesOrder::route('/{record}/edit'),
        ];
    }
}
```

### SalesOrderForm.php (create wizard — abbreviated, mirrors PurchaseOrderForm's shape)

```php
namespace App\Filament\Resources\SalesOrders\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;

class SalesOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make([
                Step::make('Customer & Warehouse')
                    ->schema([
                        Select::make('customer_id')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm(fn (Schema $schema) => \App\Filament\Resources\Customers\Schemas\CustomerForm::configure($schema)),
                        Select::make('warehouse_id')
                            ->label('Dispatching Warehouse')
                            ->options(fn () => auth()->user()->warehouses()->pluck('name', 'id'))
                            ->default(fn () => auth()->user()->warehouses()->count() === 1
                                ? auth()->user()->warehouses()->first()->id
                                : null)
                            ->required(),
                    ]),
                Step::make('Line Items')
                    ->schema([
                        // [Corrected v11.1 — same correction as
                        // PurchaseOrderForm above] ->relationship() restored
                        // after re-verifying against Filament v5's actual
                        // documentation — see the note further below in
                        // this document for the full explanation.
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Select::make('product_variant_id')
                                    ->label('Variant (SKU)')
                                    ->relationship('productVariant', 'sku')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, $set, $state) {
                                        // Display-only preview of the current catalog sale price.
                                        // NOT persisted here — actual snapshot happens at
                                        // confirm-time inside SalesService::confirmSalesOrder(),
                                        // per Addendum Principle A4. This is a UI convenience only.
                                        $variant = \App\Models\ProductVariant::with('currentPrice')->find($state);
                                        $set('_current_sale_price_preview', $variant?->currentPrice?->sale_price ?? '0.0000');
                                    }),
                                TextInput::make('unit_name')
                                    ->label('Unit')
                                    ->required(),
                                TextInput::make('unit_ratio')
                                    ->label('Unit Ratio (to base)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required(),
                                TextInput::make('qty')
                                    ->label('Qty')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, $set) => $set(
                                        'base_qty',
                                        (int) $get('qty') * (int) $get('unit_ratio')
                                    )),
                                TextInput::make('base_qty')
                                    ->label('Base Qty (computed)')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(),
                                Placeholder::make('_current_sale_price_preview')
                                    ->label('Current Catalog Sale Price')
                                    ->content(fn (Get $get) => $get('_current_sale_price_preview') ?? '—'),
                            ])
                            ->columns(3)
                            ->minItems(1)
                            ->required(),
                    ]),
                Step::make('Review & Verify')
                    ->schema([
                        Placeholder::make('review_summary')
                            ->content(fn (Get $get) => view(
                                'filament.wizards.sales-order-review',
                                ['state' => $get()],
                            )),
                    ]),
            ])
                ->modalWidth(Width::SevenExtraLarge)
                ->closeModalByClickingAway(false),
        ]);
    }
}
```

### SalesOrdersTable.php (key actions only — table columns follow the same shape as `PurchaseOrdersTable`)

```php
namespace App\Filament\Resources\SalesOrders\Tables;

use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SalesOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_code')->label('REFERENCE')->searchable()->sortable()->copyable()->weight('bold'),
                TextColumn::make('customer.name')->label('CUSTOMER')->searchable()->sortable(),
                TextColumn::make('warehouse.name')->label('WAREHOUSE')->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('items_count')->label('LINE ITEMS')->counts('items')->numeric(),
                TextColumn::make('confirmed_at')->label('CONFIRMED')->dateTime('M j, Y')->sortable()->placeholder('—'),
                TextColumn::make('dispatched_at')->label('DISPATCHED')->dateTime('M j, Y')->sortable()->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                \Filament\Actions\ViewAction::make(),

                \Filament\Actions\EditAction::make()
                    ->visible(fn (SalesOrder $record) => $record->status === SalesOrderStatus::Draft)
                    ->modalWidth(Width::Large),

                Action::make('confirmSalesOrder')
                    ->label('CONFIRM')
                    ->icon(Heroicon::CheckCircle)
                    ->color('primary')
                    ->authorize('confirmSalesOrder')
                    ->visible(fn (SalesOrder $record) => $record->status === SalesOrderStatus::Draft)
                    ->requiresConfirmation()
                    ->action(fn (SalesOrder $record) => app(\App\Services\SalesService::class)->confirmSalesOrder($record)),

                // [Addendum v11.1] Dispatch modal — client-side max is a convenience
                // only; SalesService::dispatchSale() re-validates on-hand server-side.
                Action::make('dispatchSale')
                    ->label('DISPATCH')
                    ->icon(Heroicon::Truck)
                    ->color('success')
                    ->authorize('dispatchSale')
                    ->visible(fn (SalesOrder $record) => in_array($record->status, [
                        SalesOrderStatus::Confirmed,
                        SalesOrderStatus::PartiallyDispatched,
                    ]))
                    ->modalWidth(Width::FourExtraLarge)
                    // [Optimized v11.1] Uses ProductVariant::batchAvailableQuantity()
                    // (see the Model Additions section) instead of calling
                    // ->availableQuantity() once per item — 3 queries total for
                    // this modal regardless of line-item count, not 3 × N.
                    ->schema(function (SalesOrder $record) {
                        $variantIds = $record->items->pluck('product_variant_id')->all();
                        $availableByVariant = \App\Models\ProductVariant::batchAvailableQuantity($variantIds, $record->warehouse_id);

                        return collect($record->items)
                            ->map(function ($item) use ($availableByVariant) {
                                $available = $availableByVariant[$item->product_variant_id] ?? 0;
                                $safeMax = min($item->outstandingBaseQty(), max(0, $available));

                                return TextInput::make("dispatch.{$item->id}")
                                    ->label("{$item->productVariant->sku} — outstanding {$item->outstandingBaseQty()} {$item->unit_name} (available: {$available})")
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue($safeMax)
                                    ->default($safeMax)
                                    ->helperText($available < $item->outstandingBaseQty()
                                        ? 'Insufficient stock for full dispatch — partial dispatch only.'
                                        : null);
                            })
                            ->all();
                    })
                    ->action(function (array $data, SalesOrder $record) {
                        $dispatch = collect($data['dispatch'] ?? [])
                            ->filter(fn ($qty) => (int) $qty > 0)
                            ->mapWithKeys(fn ($qty, $itemId) => [(int) $itemId => (int) $qty])
                            ->all();

                        app(\App\Services\SalesService::class)->dispatchSale($record->id, $dispatch);

                        Notification::make()->title('Sales order dispatched')->success()->send();
                    })
                    ->requiresConfirmation(),

                // [Addendum v11.1] Return modal — only visible once any item has shipped.
                Action::make('recordReturn')
                    ->label('RECORD RETURN')
                    ->icon(Heroicon::ArrowUturnLeft)
                    ->color('warning')
                    ->authorize('recordSalesReturn')
                    ->visible(fn (SalesOrder $record) => $record->items->contains(fn ($item) => $item->dispatched_base_qty > 0))
                    ->modalWidth(Width::Large)
                    ->schema([
                        \Filament\Forms\Components\Select::make('sales_order_item_id')
                            ->label('Line Item')
                            ->options(fn (SalesOrder $record) => $record->items
                                ->where('dispatched_base_qty', '>', 0)
                                ->mapWithKeys(fn ($item) => [$item->id => "{$item->productVariant->sku} (dispatched: {$item->dispatched_base_qty})"]))
                            ->required(),
                        TextInput::make('returned_base_qty')
                            ->label('Returned Qty (Base)')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        \Filament\Forms\Components\Textarea::make('notes')->columnSpanFull(),
                    ])
                    ->action(function (array $data) {
                        app(\App\Services\SalesService::class)->recordSalesReturn(
                            (int) $data['sales_order_item_id'],
                            (int) $data['returned_base_qty'],
                            $data['notes'] ?? null,
                        );

                        Notification::make()->title('Return recorded')->success()->send();
                    })
                    ->requiresConfirmation(),

                Action::make('cancelSalesOrder')
                    ->label('CANCEL')
                    ->icon(Heroicon::XMark)
                    ->color('danger')
                    ->authorize('cancelSalesOrder')
                    ->visible(fn (SalesOrder $record) => in_array($record->status, [
                        SalesOrderStatus::Draft,
                        SalesOrderStatus::Confirmed,
                    ]))
                    ->requiresConfirmation()
                    ->action(fn (SalesOrder $record) => app(\App\Services\SalesService::class)->cancelSalesOrder($record)),
            ]);
    }
}
```

**Note on the `dispatchSale` action's stock-aware defaulting:** the sketch pre-computes `$safeMax = min(outstandingBaseQty(), availableQuantity())` so the modal's default value never proposes an over-dispatch, and surfaces a helper text warning when stock is short. This is a UX nicety layered on top of — never a substitute for — `SalesService::dispatchSale()`'s own server-side insufficient-stock exception, per the addendum's explicit "client-side block ... server still re-validates" note.

### SupplierResource.php / CustomerResource.php (thin, no wizard)

```php
namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('contact_person')->maxLength(255),
            TextInput::make('phone')->tel(),
            TextInput::make('email')->email(),
            Textarea::make('address')->columnSpanFull(),
            Toggle::make('is_active')->default(true),
        ]);
    }
}
```

`CustomerForm` is field-for-field identical (same shape as `Supplier` — kept as two separate classes rather than a shared trait/base, matching the parent blueprint's preference for explicit, un-abstracted resource code per the thin-class pattern in Section 1).

`SupplierResource` / `CustomerResource` themselves follow `WarehouseResource`'s exact thin pattern (Section 6, "System Admin" of the parent blueprint) — drawer-style `EditAction`/`CreateAction` at `Width::Large`, no infolist needed for such simple master data, standard `DeleteAction`/`RestoreAction` pair guarded by `restrictOnDelete` at the DB layer.

### `[Added v11.1]` PurchaseOrderPolicy.php, SalesOrderPolicy.php, SupplierPolicy.php, CustomerPolicy.php

**These four classes are where Principle A8 is actually satisfied, not just declared.** Every permission/role decision for purchase orders, sales orders, suppliers, and customers lives here — nowhere else. Each custom-ability method below is the single source of truth the addendum's earlier Authorization Mapping table's `->authorize()` column refers to; the `->visible()` closures on the table actions (already shown in `PurchaseOrdersTable.php`/`SalesOrdersTable.php` above) never re-derive these decisions — they only decide which UI state a button shows in, composed with a `->authorize()` call for the actual permission gate.

Per A8, once these are implemented and their test checkpoint passes, they are treated as closed: the doc-block on each class states this explicitly, mirroring `reservedQuantity()`'s own permanence language in the parent blueprint.

```php
namespace App\Policies;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;

/**
 * [FIX v11.1 / Principle A8] This class is the SOLE source of truth for
 * every permission/role decision involving PurchaseOrder. No ->visible()
 * closure on PurchaseOrdersTable, no method on PurchaseService, and no
 * check anywhere else in the codebase may re-derive what is decided here.
 *
 * Per Principle A8, once each method below is implemented and its test
 * checkpoint (PurchaseOrderPolicyTest) passes, this class is FROZEN except
 * for two cases: adding a genuinely new ability, or fixing a demonstrated
 * bug in an existing method's logic. Do not reopen a method here to "just
 * tweak" behavior that belongs to PurchaseService instead (e.g. whether an
 * item has already been received — that is a data-integrity question the
 * Service re-checks independently; this class only answers "is this user
 * allowed to attempt it").
 */
class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->status === PurchaseOrderStatus::Draft;
    }

    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return in_array($purchaseOrder->status, [
            PurchaseOrderStatus::Draft,
            PurchaseOrderStatus::Cancelled,
        ], true);
    }

    public function restore(User $user): bool
    {
        return true;
    }

    public function forceDelete(User $user): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Custom ability. Mirrors TransferRequisitionPolicy's convention of
     * naming the ability after the action it gates, not a generic CRUD verb.
     */
    public function orderPurchase(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->status === PurchaseOrderStatus::Draft;
    }

    /**
     * [Open decision — do not resolve unilaterally] Per the addendum's
     * Integration Point #7 policy-composition question: should this also
     * require $user->can('setPrice', ProductVariant::class) when the PO's
     * update_cost_price flag is true? As specified, it does NOT — receiving
     * stock and updating catalog cost price are treated as one combined
     * grant here. If Alvin decides they should be separate grants, this is
     * the one specific line to change, and per A8 that change is a
     * legitimate "new ability" edit, not a violation of the freeze.
     */
    public function receivePurchase(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return in_array($purchaseOrder->status, [
            PurchaseOrderStatus::Ordered,
            PurchaseOrderStatus::PartiallyReceived,
        ], true);
    }

    /**
     * [FIX v11.1] This is the actual, sole enforcement point for the
     * "no stock has moved yet" cancellation boundary — not a duplicate
     * inline check inside PurchaseService::cancelPurchaseOrder(). The
     * Service's own guard (Exception thrown if any item has
     * received_base_qty > 0) is a data-integrity re-check, independent of
     * this permission check, per the addendum's explicit Service-vs-Policy
     * distinction (Principle A8's "Practical effect" paragraph) — the two
     * happen to enforce the same boundary condition but answer different
     * questions, and both exist because a Service must not assume
     * authorization was already checked by whatever called it.
     */
    public function cancelPurchase(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if (! in_array($purchaseOrder->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Ordered], true)) {
            return false;
        }

        return $purchaseOrder->items->every(fn ($item) => $item->received_base_qty === 0);
    }
}
```

```php
namespace App\Policies;

use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use App\Models\User;

/**
 * [FIX v11.1 / Principle A8] Sole source of truth for every permission/role
 * decision involving SalesOrder. Frozen once verified, per the same terms
 * as PurchaseOrderPolicy's doc-block above — read that one first, since
 * this class mirrors its reasoning rather than repeating it in full.
 */
class SalesOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SalesOrder $salesOrder): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SalesOrder $salesOrder): bool
    {
        return $salesOrder->status === SalesOrderStatus::Draft;
    }

    public function delete(User $user, SalesOrder $salesOrder): bool
    {
        return in_array($salesOrder->status, [
            SalesOrderStatus::Draft,
            SalesOrderStatus::Cancelled,
        ], true);
    }

    public function restore(User $user): bool
    {
        return true;
    }

    public function forceDelete(User $user): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function confirmSalesOrder(User $user, SalesOrder $salesOrder): bool
    {
        return $salesOrder->status === SalesOrderStatus::Draft;
    }

    public function dispatchSale(User $user, SalesOrder $salesOrder): bool
    {
        return in_array($salesOrder->status, [
            SalesOrderStatus::Confirmed,
            SalesOrderStatus::PartiallyDispatched,
        ], true);
    }

    public function recordSalesReturn(User $user, SalesOrder $salesOrder): bool
    {
        return $salesOrder->items->contains(fn ($item) => $item->dispatched_base_qty > 0);
    }

    /**
     * [FIX v11.1] Sole enforcement point for "illegal once dispatch has
     * begun" — same Service-vs-Policy split as PurchaseOrderPolicy::
     * cancelPurchase() above. SalesService::cancelSalesOrder()'s own
     * exception is a data-integrity re-check, not a duplicate of this
     * permission decision.
     */
    public function cancelSalesOrder(User $user, SalesOrder $salesOrder): bool
    {
        return in_array($salesOrder->status, [
            SalesOrderStatus::Draft,
            SalesOrderStatus::Confirmed,
        ], true);
    }
}
```

```php
namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

/**
 * [FIX v11.1 / Principle A8] Sole source of truth for Supplier permissions.
 * Deliberately thin — Supplier is simple master data (Addendum Principle
 * A3) with no custom abilities, so this policy is standard CRUD only.
 * Frozen once verified, same terms as PurchaseOrderPolicy above.
 */
class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return true;
    }

    /**
     * restrictOnDelete on purchase_orders.supplier_id means the DB layer
     * already blocks deleting a referenced supplier (LedgerIntegrityTest
     * covers this per Integration Point #6) — this policy method still
     * exists to control who may ATTEMPT the delete, which is a distinct
     * question from whether the delete would succeed.
     */
    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
```

`CustomerPolicy` is field-for-field identical to `SupplierPolicy` with `Supplier` replaced by `Customer` throughout — kept as a separate class rather than a shared base, for the same explicit-over-abstracted reasoning already given for `SupplierForm`/`CustomerForm` above, and because collapsing two Policy classes into one shared base is exactly the kind of premature abstraction Principle A8's "frozen, single-purpose" intent argues against: a future change to supplier permissions that doesn't apply to customers should never risk touching customer authorization by accident because they shared a base class.

**Test checkpoint (in addition to what's already listed in the Integration Points and Full Edge-Case Test Matrix sections):**
```
PurchaseOrderPolicyTest::orderPurchase_allowed_only_when_draft()
PurchaseOrderPolicyTest::receivePurchase_allowed_only_when_ordered_or_partially_received()
PurchaseOrderPolicyTest::cancelPurchase_denied_once_any_item_received_even_when_status_allows_it()
PurchaseOrderPolicyTest::forceDelete_admin_only()
SalesOrderPolicyTest::confirmSalesOrder_allowed_only_when_draft()
SalesOrderPolicyTest::dispatchSale_allowed_only_when_confirmed_or_partially_dispatched()
SalesOrderPolicyTest::recordSalesReturn_denied_when_nothing_dispatched_yet()
SalesOrderPolicyTest::cancelSalesOrder_denied_once_dispatch_has_begun()
SupplierPolicyTest::delete_admin_only()
CustomerPolicyTest::delete_admin_only()
```

### `[Added v11.1 — Principle A8 / Integration Point 9A]` Sketches of all nine EXISTING parent v11.0 policies, consolidated

**Why these are here, in an addendum document, for files that belong to the parent blueprint:** the parent v11.0 blueprint document — as shown in the source material this addendum was built against — states each of these nine policies' *method list* (the table reproduced below) and a set of one-line "implementation extensions beyond blueprint" notes, but never actually printed any of the nine class *bodies*. That gap is exactly what let Principle A8 become necessary in the first place: without seeing the actual code, there's no way to confirm the permission logic these notes describe is fully consolidated inside the policy methods rather than partly re-implemented somewhere else (a `->visible()` closure, a model observer doing double duty as an authorization gate, etc.). Per Integration Point 9A, closing that gap is explicitly in scope for this addendum's implementation work. The sketches below are what "consolidated per A8" looks like for each of the nine — Phase 4 of the implementation prompt uses these as the target shape, then reconciles them against whatever the real codebase's current policy files actually contain (per Phase 0's audit), since these are reconstructed from the blueprint document's table and notes, not copied from real source.

Parent blueprint's own method-list table, reproduced here as the checklist these nine sketches implement:

| Policy | Methods |
|---|---|
| ProductPolicy | viewAny, view, create, update, delete (blocks while active children exist), restore, forceDelete |
| ProductVariantPolicy | viewAny, view, create, update, delete, restore, forceDelete (always false), setPrice, adjustStock |
| TransferRequisitionPolicy | viewAny, view, create, update, delete, restore, forceDelete (admin only), confirm, dispatch, receive, cancel (five-state pre-dispatch allowlist, enforced server-side) |
| TransferRequisitionItemRevisionPolicy | viewAny, view, create, update, delete, restore, forceDelete (admin only), deleteAny, restoreAny, forceDeleteAny |
| StockMovementPolicy | viewAny, view, create/update/delete/restore/forceDelete/deleteAny/restoreAny/forceDeleteAny all false — immutable audit trail |
| InTransitPolicy | viewAny, view, create/update/delete/restore/forceDelete/deleteAny/restoreAny/forceDeleteAny all false, receive |
| LossLedgerPolicy | viewAny, view, create/update/delete/restore/forceDelete/deleteAny/restoreAny/forceDeleteAny all false, recordLoss |
| WarehousePolicy | viewAny, view, create (admin), update, delete/restore/forceDelete/deleteAny/restoreAny/forceDeleteAny all false, adjustStock (all users), recordLoss (all users) |
| UserPolicy | viewAny, view, create (admin), update (admin or self), delete (admin, not self), restore/forceDelete/deleteAny/restoreAny/forceDeleteAny all admin |

```php
namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * [Principle A8] Sole source of truth for Product permissions.
 * Frozen once verified against the real codebase (Phase 4 of the
 * implementation prompt) — same terms as every other policy in this file.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $product): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Product $product): bool
    {
        return true;
    }

    /**
     * Mirrors ProductObserver::deleting()'s own guard (parent blueprint,
     * Section 4) so the delete action doesn't even authorize for a user
     * who would immediately hit the observer's Exception anyway. This is
     * intentional duplication of a CONDITION, not of an AUTHORIZATION
     * DECISION — the observer enforces referential integrity at the model
     * layer regardless of who's asking (it fires even for direct Tinker/
     * Artisan calls with no authenticated user in context), while this
     * policy method answers "should a user acting through the panel be
     * offered this action at all." Per Principle A8 this distinction is
     * why both are allowed to exist without one being a redundant copy of
     * the other — the observer isn't a permission check, so this method
     * doesn't collapse into it.
     */
    public function delete(User $user, Product $product): bool
    {
        return $product->variants()->whereNull('deleted_at')->count() === 0;
    }

    public function restore(User $user): bool
    {
        return true;
    }

    public function forceDelete(User $user): bool
    {
        return $user->isAdmin();
    }
}
```

```php
namespace App\Policies;

use App\Models\ProductVariant;
use App\Models\User;

class ProductVariantPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductVariant $productVariant): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ProductVariant $productVariant): bool
    {
        return true;
    }

    public function delete(User $user, ProductVariant $productVariant): bool
    {
        return true;
    }

    public function restore(User $user): bool
    {
        return true;
    }

    /**
     * "always false" per the parent blueprint's method table — a variant
     * referenced by any ledger table (restrictOnDelete throughout Section 2)
     * should never be force-deletable through the panel at all, regardless
     * of role. This is a blanket, role-independent false rather than an
     * admin gate, which is itself a meaningful permission decision worth
     * stating explicitly rather than leaving as an accidental omission.
     */
    public function forceDelete(User $user): bool
    {
        return false;
    }

    public function setPrice(User $user): bool
    {
        return true;
    }

    public function adjustStock(User $user): bool
    {
        return $user->isAdmin();
    }
}
```

```php
namespace App\Policies;

use App\Enums\TransferRequisitionStatus;
use App\Models\TransferRequisition;
use App\Models\User;

/**
 * [FIX v11 in the parent blueprint, reaffirmed under Principle A8 here]
 * cancel() below is the parent blueprint's own flagship example of exactly
 * what A8 requires everywhere: the five-state pre-dispatch allowlist is
 * enforced HERE, in PHP, independently of TransferRequisitionResource's
 * ->visible() closure — this is the method the parent blueprint's own
 * Playwright E2E Scenario 6 (Section 9) exists to verify hasn't regressed.
 */
class TransferRequisitionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TransferRequisition $transferRequisition): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TransferRequisition $transferRequisition): bool
    {
        return $transferRequisition->status === TransferRequisitionStatus::Draft;
    }

    public function delete(User $user, TransferRequisition $transferRequisition): bool
    {
        return in_array($transferRequisition->status, [
            TransferRequisitionStatus::Draft,
            TransferRequisitionStatus::Cancelled,
        ], true);
    }

    public function restore(User $user): bool
    {
        return true;
    }

    public function forceDelete(User $user): bool
    {
        return $user->isAdmin();
    }

    public function confirm(User $user, TransferRequisition $transferRequisition): bool
    {
        return in_array($transferRequisition->status, [
            TransferRequisitionStatus::Requested,
            TransferRequisitionStatus::UnderReviewFulfiller,
            TransferRequisitionStatus::UnderReviewRequestor,
        ], true);
    }

    public function dispatch(User $user, TransferRequisition $transferRequisition): bool
    {
        return $transferRequisition->status === TransferRequisitionStatus::Confirmed;
    }

    public function receive(User $user, TransferRequisition $transferRequisition): bool
    {
        return in_array($transferRequisition->status, [
            TransferRequisitionStatus::Dispatched,
            TransferRequisitionStatus::PartiallyReceived,
        ], true);
    }

    /**
     * `[FIX v11]` THE method this whole principle is named after in the
     * parent blueprint. Permanently five-state, pre-dispatch only — see
     * Principle #14. Do not widen this to include Dispatched or
     * PartiallyReceived; there is no compensating stock-reversal pathway.
     */
    public function cancel(User $user, TransferRequisition $transferRequisition): bool
    {
        return in_array($transferRequisition->status, [
            TransferRequisitionStatus::Draft,
            TransferRequisitionStatus::Requested,
            TransferRequisitionStatus::UnderReviewFulfiller,
            TransferRequisitionStatus::UnderReviewRequestor,
            TransferRequisitionStatus::Confirmed,
        ], true);
    }
}
```

```php
namespace App\Policies;

use App\Models\TransferRequisitionItemRevision;
use App\Models\User;

class TransferRequisitionItemRevisionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TransferRequisitionItemRevision $revision): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TransferRequisitionItemRevision $revision): bool
    {
        return true;
    }

    public function delete(User $user, TransferRequisitionItemRevision $revision): bool
    {
        return true;
    }

    public function restore(User $user): bool
    {
        return true;
    }

    public function forceDelete(User $user): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return true;
    }

    public function restoreAny(User $user): bool
    {
        return true;
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
```

```php
namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;

/**
 * Immutable audit trail by design (parent blueprint, Section 12's
 * "Implementation extensions" note). Every mutating method is a blanket
 * false, independent of role — there is no role, including admin, for
 * which a stock_movements row should ever be editable or deletable
 * through the panel. This is the strictest possible expression of
 * Principle A8: the permission decision here is "never," full stop, and
 * that decision lives in exactly these nine methods.
 */
class StockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, StockMovement $stockMovement): bool
    {
        return true;
    }

    public function create(User $user): bool { return false; }
    public function update(User $user, StockMovement $stockMovement): bool { return false; }
    public function delete(User $user, StockMovement $stockMovement): bool { return false; }
    public function restore(User $user): bool { return false; }
    public function forceDelete(User $user): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
    public function restoreAny(User $user): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }
}
```

```php
namespace App\Policies;

use App\Models\InTransit;
use App\Models\TransferRequisition;
use App\Models\User;

class InTransitPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, InTransit $inTransit): bool
    {
        return true;
    }

    public function create(User $user): bool { return false; }
    public function update(User $user, InTransit $inTransit): bool { return false; }
    public function delete(User $user, InTransit $inTransit): bool { return false; }
    public function restore(User $user): bool { return false; }
    public function forceDelete(User $user): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
    public function restoreAny(User $user): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }

    /**
     * Note this takes the InTransit's PARENT TransferRequisition's status,
     * not any status field on InTransit itself — InTransitResource's
     * ReceiveIntakeAction (parent blueprint, Section 6.4.1) routes to the
     * STN scan flow keyed on transfer_requisition_id, so the permission
     * question is really "is the parent requisition receivable," which
     * TransferRequisitionPolicy::receive() already answers. Delegating to
     * it here (rather than re-deriving the same status check inline) is
     * itself an application of Principle A8 — one status-allowlist, one
     * place it's decided, called from wherever it's needed.
     */
    public function receive(User $user, InTransit $inTransit): bool
    {
        return $user->can('receive', $inTransit->transferRequisition);
    }
}
```

```php
namespace App\Policies;

use App\Models\LossLedger;
use App\Models\User;

class LossLedgerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LossLedger $lossLedger): bool
    {
        return true;
    }

    public function create(User $user): bool { return false; }
    public function update(User $user, LossLedger $lossLedger): bool { return false; }
    public function delete(User $user, LossLedger $lossLedger): bool { return false; }
    public function restore(User $user): bool { return false; }
    public function forceDelete(User $user): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
    public function restoreAny(User $user): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }

    /**
     * "all users" per the parent blueprint's extension note — recordLoss
     * is operationally available to anyone, not admin-gated. Stated
     * explicitly as `true` rather than omitted, so a future reviewer sees
     * a deliberate decision, not a method someone forgot to write.
     */
    public function recordLoss(User $user): bool
    {
        return true;
    }
}
```

```php
namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return true;
    }

    public function delete(User $user, Warehouse $warehouse): bool { return false; }
    public function restore(User $user): bool { return false; }
    public function forceDelete(User $user): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
    public function restoreAny(User $user): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }

    /**
     * "all authenticated users" per the parent blueprint's extension note
     * (operational flexibility) — deliberately NOT admin-gated, unlike
     * create() above. The asymmetry (create is admin-only, but
     * adjustStock/recordLoss are open to everyone) is itself the kind of
     * decision Principle A8 wants living in exactly one visible place —
     * without this doc-block a future maintainer might "fix" the
     * asymmetry by admin-gating these two, which would be a behavior
     * change the parent blueprint's own notes explicitly did not intend.
     */
    public function adjustStock(User $user): bool
    {
        return true;
    }

    public function recordLoss(User $user): bool
    {
        return true;
    }
}
```

```php
namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, User $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin() || $user->id === $model->id;
    }

    /**
     * Self-protection guard per the parent blueprint's extension note —
     * an admin cannot delete their own account through this policy. This
     * is a role-independent safety rail layered on top of the role check,
     * not a separate permission concern, so it stays in this one method
     * rather than becoming, say, a Livewire component-level check that
     * happens to agree with this one today and silently diverges later.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->id !== $model->id;
    }

    public function restore(User $user): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
```

**These nine sketches are reconstructions against the parent blueprint document's table and notes, not a read of the real codebase — Phase 0 of the implementation prompt must diff them against whatever the actual `app/Policies/*.php` files currently contain before treating any of them as correct.** Where the real code already matches, nothing changes and the file is simply confirmed-frozen per A8. Where the real code has permission logic these sketches don't capture (or has drifted to something different — e.g. a role name that isn't `isAdmin()`), the real code wins and the discrepancy gets noted in the Phase 4 completion report, per the addendum's Permanence Checklist.

**Test checkpoint (parent-policy consolidation, per Integration Point 9A):**
```
ProductPolicyTest::delete_denied_while_active_variants_exist()
ProductPolicyTest::delete_allowed_once_all_variants_trashed()
ProductVariantPolicyTest::forceDelete_always_false_regardless_of_role()
ProductVariantPolicyTest::adjustStock_admin_only()
TransferRequisitionPolicyTest::cancel_matches_the_exact_five_state_allowlist_no_more_no_less()
StockMovementPolicyTest::every_mutating_method_returns_false_regardless_of_admin_status()
InTransitPolicyTest::receive_delegates_to_parent_transfer_requisition_policy_not_a_separate_check()
LossLedgerPolicyTest::recordLoss_allowed_for_non_admin_users()
WarehousePolicyTest::create_admin_only_but_adjustStock_and_recordLoss_are_not()
UserPolicyTest::delete_denied_when_target_is_self_even_for_admin()
UserPolicyTest::update_allowed_for_self_even_when_not_admin()
```

### PurchaseOrderInfolist.php

Mirrors `TransferRequisitionInfolist`'s structure (profile grid + repeatable line-item entry) — placed under `Schemas/`, per the `[Verified v11.1]` note above.

```php
namespace App\Filament\Resources\PurchaseOrders\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class PurchaseOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)
                    ->schema([
                        Section::make('PURCHASE ORDER PROFILE')
                            ->icon(Heroicon::DocumentText)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('reference_code')
                                            ->label('REFERENCE CODE')
                                            ->weight(FontWeight::Bold)
                                            ->size('lg')
                                            ->copyable()
                                            ->color('primary'),

                                        TextEntry::make('status')
                                            ->label('STATUS')
                                            ->badge(),

                                        TextEntry::make('supplier.name')
                                            ->label('SUPPLIER')
                                            ->icon(Heroicon::BuildingStorefront),

                                        TextEntry::make('warehouse.name')
                                            ->label('RECEIVING WAREHOUSE')
                                            ->icon(Heroicon::BuildingOffice2),

                                        TextEntry::make('update_cost_price')
                                            ->label('UPDATES CATALOG COST')
                                            ->badge()
                                            ->color(fn (bool $state) => $state ? 'warning' : 'gray')
                                            ->formatStateUsing(fn (bool $state) => $state ? 'Yes' : 'No'),
                                    ]),
                            ])
                            ->columnSpan(2),

                        Section::make('SIGN-OFFS')
                            ->icon(Heroicon::ShieldCheck)
                            ->schema([
                                TextEntry::make('orderedBy.name')
                                    ->label('ORDERED BY')
                                    ->icon(Heroicon::User)
                                    ->placeholder('—'),

                                TextEntry::make('receivedBy.name')
                                    ->label('RECEIVED BY')
                                    ->icon(Heroicon::ArchiveBoxArrowDown)
                                    ->placeholder('Pending Intake'),

                                TextEntry::make('ordered_at')
                                    ->label('ORDERED AT')
                                    ->dateTime('M j, Y H:i')
                                    ->placeholder('—'),

                                TextEntry::make('received_at')
                                    ->label('RECEIVED AT')
                                    ->dateTime('M j, Y H:i')
                                    ->placeholder('—'),
                            ])
                            ->columnSpan(1),

                        Section::make('LINE ITEMS')
                            ->icon(Heroicon::ClipboardDocumentList)
                            ->schema([
                                RepeatableEntry::make('items')
                                    ->label('')
                                    ->schema([
                                        Grid::make(6)
                                            ->schema([
                                                TextEntry::make('productVariant.sku')
                                                    ->label('SKU')
                                                    ->weight(FontWeight::Bold)
                                                    ->columnSpan(1),

                                                TextEntry::make('productVariant.name')
                                                    ->label('PRODUCT')
                                                    ->columnSpan(2),

                                                TextEntry::make('ordered_base_qty')
                                                    ->label('ORDERED (BASE)')
                                                    ->numeric()
                                                    ->columnSpan(1),

                                                TextEntry::make('received_base_qty')
                                                    ->label('RECEIVED (BASE)')
                                                    ->numeric()
                                                    ->columnSpan(1),

                                                TextEntry::make('unit_cost_price')
                                                    ->label('UNIT COST')
                                                    ->money(config('app.currency'), decimals: 4)
                                                    ->columnSpan(1),
                                            ]),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
```

### SalesOrderInfolist.php

Same shape, adjusted for the sales side's line-total and status-badge coloring.

```php
namespace App\Filament\Resources\SalesOrders\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class SalesOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)
                    ->schema([
                        Section::make('SALES ORDER PROFILE')
                            ->icon(Heroicon::DocumentText)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('reference_code')
                                            ->label('REFERENCE CODE')
                                            ->weight(FontWeight::Bold)
                                            ->size('lg')
                                            ->copyable()
                                            ->color('primary'),

                                        TextEntry::make('status')
                                            ->label('STATUS')
                                            ->badge(),

                                        TextEntry::make('customer.name')
                                            ->label('CUSTOMER')
                                            ->icon(Heroicon::UserGroup),

                                        TextEntry::make('warehouse.name')
                                            ->label('DISPATCHING WAREHOUSE')
                                            ->icon(Heroicon::BuildingOffice2),
                                    ]),
                            ])
                            ->columnSpan(2),

                        Section::make('SIGN-OFFS')
                            ->icon(Heroicon::ShieldCheck)
                            ->schema([
                                TextEntry::make('orderedBy.name')
                                    ->label('ORDERED BY')
                                    ->icon(Heroicon::User)
                                    ->placeholder('—'),

                                TextEntry::make('dispatchedBy.name')
                                    ->label('DISPATCHED BY')
                                    ->icon(Heroicon::Truck)
                                    ->placeholder('Pending Dispatch'),

                                TextEntry::make('confirmed_at')
                                    ->label('CONFIRMED AT')
                                    ->dateTime('M j, Y H:i')
                                    ->placeholder('—'),

                                TextEntry::make('dispatched_at')
                                    ->label('DISPATCHED AT')
                                    ->dateTime('M j, Y H:i')
                                    ->placeholder('—'),
                            ])
                            ->columnSpan(1),

                        Section::make('LINE ITEMS')
                            ->icon(Heroicon::ClipboardDocumentList)
                            ->schema([
                                RepeatableEntry::make('items')
                                    ->label('')
                                    ->schema([
                                        Grid::make(6)
                                            ->schema([
                                                TextEntry::make('productVariant.sku')
                                                    ->label('SKU')
                                                    ->weight(FontWeight::Bold)
                                                    ->columnSpan(1),

                                                TextEntry::make('productVariant.name')
                                                    ->label('PRODUCT')
                                                    ->columnSpan(2),

                                                TextEntry::make('base_qty')
                                                    ->label('ORDERED (BASE)')
                                                    ->numeric()
                                                    ->columnSpan(1),

                                                TextEntry::make('dispatched_base_qty')
                                                    ->label('DISPATCHED (BASE)')
                                                    ->numeric()
                                                    ->columnSpan(1),

                                                TextEntry::make('unit_sale_price_snapshot')
                                                    ->label('SNAPSHOT PRICE')
                                                    ->money(config('app.currency'), decimals: 4)
                                                    ->columnSpan(1),
                                            ]),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
```

**Note on `unit_sale_price_snapshot` display:** shown here read-only in the infolist as-is — never recomputed from the current catalog price at render time, since that would defeat the entire point of A4's call-time snapshot. If the infolist ever appears to show a "stale" price compared to the catalog, that's correct behavior, not a bug — it's the historical price the sale actually confirmed at.

---

## 🔍 System-Admin-Only Warehouse & Period Filters

**Scope, per your direction:** these two filters — filter by warehouse, and filter by date period (specific date, weekly, monthly, yearly, or a custom range) — are added **only for System Admin / Auditor-role users**, on the resources where cross-warehouse, cross-period review is actually the point: `PurchaseOrdersTable`, `SalesOrdersTable`, and the parent blueprint's own `StockMovementResource` / `LossLedgerResource` (both already have partial date filtering — this section upgrades and unifies it). Regular warehouse-scoped staff continue to see only their assigned warehouse(s) via the existing `auth()->user()->warehouses()` scoping already used throughout the parent blueprint (e.g. `TransferRequisitionForm`'s `from_warehouse_id`/`to_warehouse_id` selects) — the filter below is additive visibility for admins reviewing across warehouses, not a way for a scoped user to see warehouses they don't have access to.

### `[DRY v11.1]` One reusable filter set, not four near-duplicate ones

Rather than writing a separate warehouse filter and a separate period filter for each of the four resources it applies to, both are built once as static factory methods on a shared class, and each resource's `->filters([...])` array just calls them.

```php
namespace App\Filament\Support\Filters;

use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * [Added v11.1] Shared, System-Admin-only filters — warehouse and period —
 * reused across PurchaseOrdersTable, SalesOrdersTable, StockMovementsTable,
 * and LossLedgersTable. Built once here instead of four times, per your
 * direction to keep this DRY. Both factory methods return the exact filter
 * definitions to splice into a resource's ->filters([...]) array; they do
 * not include the ->visible(fn () => auth()->user()->isAdmin()) gate
 * themselves — apply that at the call site (see usage examples below),
 * since Filament v5's ->filters() array-level visibility gating differs
 * slightly by context and each resource should make its own admin-only
 * intent explicit rather than trusting a shared class to have done it.
 */
class AdminReviewFilters
{
    /**
     * Warehouse filter — a plain SelectFilter listing ALL warehouses
     * (not auth()->user()->warehouses(), which is the staff-scoped list
     * used elsewhere in the parent blueprint's wizards). This is
     * deliberate: an admin reviewing cross-warehouse activity needs to
     * see and filter by warehouses they may not be personally assigned
     * to, which is exactly why this filter is admin-gated at the call
     * site rather than using the staff-scoped relationship.
     */
    public static function warehouse(string $relationshipName = 'warehouse'): SelectFilter
    {
        return SelectFilter::make('warehouse_id')
            ->label('Warehouse')
            ->relationship($relationshipName, 'name')
            ->searchable()
            ->preload();
    }

    /**
     * Period filter — a single dropdown of common presets (Today, This
     * Week, This Month, This Year, Specific Date, Custom Range), with
     * conditional fields that only appear for the presets that need them
     * (a DatePicker for "Specific Date", two DatePickers for "Custom
     * Range"). "This Week" and "This Month" and "This Year" need no
     * extra input at all — they resolve relative to now() at query time.
     *
     * $dateColumn: the column to filter on — differs per resource
     * (created_at for stock_movements, recorded_at for loss_ledgers,
     * ordered_at for purchase_orders, confirmed_at or ordered_at for
     * sales_orders — pass whichever is the resource's primary date of
     * record).
     */
    public static function period(string $dateColumn): Filter
    {
        return Filter::make('period')
            ->label('Period')
            ->schema([
                Select::make('preset')
                    ->label('Period')
                    ->options([
                        'today'         => 'Today',
                        'this_week'     => 'This Week',
                        'this_month'    => 'This Month',
                        'this_year'     => 'This Year',
                        'specific_date' => 'Specific Date',
                        'custom_range'  => 'Custom Range',
                    ])
                    ->default(null)
                    ->native(false)
                    ->live(),

                DatePicker::make('specific_date')
                    ->label('Date')
                    ->visible(fn (Get $get) => $get('preset') === 'specific_date'),

                DatePicker::make('range_from')
                    ->label('From')
                    ->visible(fn (Get $get) => $get('preset') === 'custom_range'),

                DatePicker::make('range_until')
                    ->label('Until')
                    ->visible(fn (Get $get) => $get('preset') === 'custom_range'),
            ])
            ->query(function (Builder $query, array $data) use ($dateColumn): Builder {
                return match ($data['preset'] ?? null) {
                    'today'         => $query->whereDate($dateColumn, now()->toDateString()),
                    'this_week'     => $query->whereBetween($dateColumn, [now()->startOfWeek(), now()->endOfWeek()]),
                    'this_month'    => $query->whereBetween($dateColumn, [now()->startOfMonth(), now()->endOfMonth()]),
                    'this_year'     => $query->whereBetween($dateColumn, [now()->startOfYear(), now()->endOfYear()]),
                    'specific_date' => $query->when(
                        $data['specific_date'] ?? null,
                        fn (Builder $q, $date) => $q->whereDate($dateColumn, $date),
                    ),
                    'custom_range' => $query
                        ->when($data['range_from'] ?? null, fn (Builder $q, $date) => $q->whereDate($dateColumn, '>=', $date))
                        ->when($data['range_until'] ?? null, fn (Builder $q, $date) => $q->whereDate($dateColumn, '<=', $date)),
                    default => $query,
                };
            })
            // [Verified v11.1 against Filament v5 docs] indicateUsing() may
            // return either a single string (used for the single-value
            // presets below) or an array of Indicator::make(...) objects
            // when a filter has more than one independently-clearable
            // field — the documented pattern for exactly this custom_range
            // case, since it lets each date be removed on its own from the
            // active-filters bar via ->removeField() rather than clearing
            // the whole filter at once.
            ->indicateUsing(function (array $data): string|array|null {
                return match ($data['preset'] ?? null) {
                    'today'         => 'Today',
                    'this_week'     => 'This week',
                    'this_month'    => 'This month',
                    'this_year'     => 'This year',
                    'specific_date' => isset($data['specific_date'])
                        ? 'On '.Carbon::parse($data['specific_date'])->toFormattedDateString()
                        : null,
                    'custom_range'  => array_filter([
                        isset($data['range_from'])
                            ? Indicator::make('From '.Carbon::parse($data['range_from'])->toFormattedDateString())
                                ->removeField('range_from')
                            : null,
                        isset($data['range_until'])
                            ? Indicator::make('Until '.Carbon::parse($data['range_until'])->toFormattedDateString())
                                ->removeField('range_until')
                            : null,
                    ]),
                    default => null,
                };
            });
    }
}
```

### Usage in `PurchaseOrdersTable.php` (add to the existing `->filters([...])` array)

```php
->filters([
    SelectFilter::make('status')->options(PurchaseOrderStatus::class),
    SelectFilter::make('supplier_id')->relationship('supplier', 'name')->label('Supplier'),
    SelectFilter::make('warehouse_id')->relationship('warehouse', 'name')->label('Warehouse'),
    TrashedFilter::make(),

    // [Added v11.1] Admin/Auditor-only cross-warehouse review filters.
    // Note the existing warehouse_id SelectFilter above already covers
    // basic warehouse filtering for all users — AdminReviewFilters::warehouse()
    // is intentionally NOT duplicated here for this resource, since a plain
    // SelectFilter on the same column already exists. Only the period
    // filter is added here; see StockMovementsTable / LossLedgersTable
    // below for a resource that needs both because it previously had
    // neither.
    \App\Filament\Support\Filters\AdminReviewFilters::period('ordered_at')
        ->visible(fn () => auth()->user()->isAdmin() || auth()->user()->isAuditor()),
])
```

### Usage in `SalesOrdersTable.php` (add a `->filters([...])` array — the earlier sketch omitted one entirely, matching its "key actions only" scope note; this closes that)

```php
use App\Enums\SalesOrderStatus;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;

// Inside SalesOrdersTable::configure(), after ->columns([...]):
->filters([
    SelectFilter::make('status')->options(SalesOrderStatus::class),
    SelectFilter::make('customer_id')->relationship('customer', 'name')->label('Customer'),
    SelectFilter::make('warehouse_id')->relationship('warehouse', 'name')->label('Warehouse'),
    TrashedFilter::make(),

    \App\Filament\Support\Filters\AdminReviewFilters::period('confirmed_at')
        ->visible(fn () => auth()->user()->isAdmin() || auth()->user()->isAuditor()),
])
```

### Usage in the parent blueprint's `StockMovementsTable.php` and `LossLedgersTable.php`

**`[Improves on parent v11.0]`** `StockMovementResource` currently has **no date filter at all** in the parent blueprint (only `type` and `warehouse_id` `SelectFilter`s). `LossLedgerResource` already has an ad-hoc inline `date_range` filter (parent blueprint, Section 6.4.3) with only two fields (`from`/`until`) and no presets — this replaces that narrower filter with the shared, preset-aware one, purely as an *option* for Alvin to take, not a mandatory change to a file outside this addendum's normal scope (see the note at the end of this section).

```php
// StockMovementsTable.php — add to ->filters([...]):
\App\Filament\Support\Filters\AdminReviewFilters::warehouse()
    ->visible(fn () => auth()->user()->isAdmin() || auth()->user()->isAuditor()),
\App\Filament\Support\Filters\AdminReviewFilters::period('created_at')
    ->visible(fn () => auth()->user()->isAdmin() || auth()->user()->isAuditor()),
```

```php
// LossLedgersTable.php — REPLACES the parent blueprint's existing inline
// date_range Filter::make(...) block (Section 6.4.3) with:
\App\Filament\Support\Filters\AdminReviewFilters::period('recorded_at')
    ->visible(fn () => auth()->user()->isAdmin() || auth()->user()->isAuditor()),
// warehouse_id SelectFilter already exists on this resource — unchanged.
```

**Scope note — read before implementing:** `StockMovementsTable.php` and `LossLedgersTable.php` belong to the **parent v11.0 blueprint**, not this addendum. Per this addendum's opening scope boundary (additive-only, no modification of parent blueprint files beyond what's explicitly named — the `StockMovementType` enum cases and `availableQuantity()`), touching these two files is **optional, presented to Alvin as a convenience, not a required part of this addendum's implementation**. If he wants the shared filter applied to the parent's audit ledgers too, that's a small, low-risk, easily-reviewed follow-up — but it should be a decision he makes, not something silently bundled into "implementing the addendum." The `AdminReviewFilters` class itself lives under `App\Filament\Support\Filters`, a new namespace, so creating it does not touch any existing parent file — only its *optional* application to `StockMovementsTable`/`LossLedgersTable` does.

**Test checkpoint:**
```
AdminReviewFiltersTest::warehouse_filter_lists_all_warehouses_not_just_staff_assigned_ones()
AdminReviewFiltersTest::period_filter_today_matches_only_todays_records()
AdminReviewFiltersTest::period_filter_this_week_matches_records_within_current_week_boundaries()
AdminReviewFiltersTest::period_filter_this_month_matches_records_within_current_month_boundaries()
AdminReviewFiltersTest::period_filter_this_year_matches_records_within_current_year_boundaries()
AdminReviewFiltersTest::period_filter_specific_date_matches_only_that_date()
AdminReviewFiltersTest::period_filter_custom_range_is_inclusive_of_both_boundary_dates()
AdminReviewFiltersTest::period_filter_custom_range_with_only_from_set_is_open_ended()
AdminReviewFiltersTest::period_filter_custom_range_with_only_until_set_is_open_ended()
AdminReviewFiltersTest::period_filter_with_no_preset_selected_returns_unfiltered_query()
PurchaseOrdersTableTest::period_filter_hidden_from_non_admin_non_auditor_users()
SalesOrdersTableTest::period_filter_hidden_from_non_admin_non_auditor_users()
```

**Edge cases folded into the matrix above, called out explicitly:**
- **Boundary values:** "This Week"/"This Month"/"This Year" boundaries must use `startOfWeek()`/`endOfWeek()` etc. (Carbon's locale-aware week start, e.g. Monday vs Sunday depending on app locale) consistently with whatever the rest of the parent blueprint's i18n config assumes — verify against actual `config('app.locale')` behavior in Phase 0 of the implementation prompt, don't assume Sunday-start.
- **Null relation paths:** a `custom_range` with only one of `range_from`/`range_until` set must produce an open-ended filter (`>=` only, or `<=` only), never silently do nothing or throw — covered by the two "open_ended" tests above.
- **Authorization bypass:** a non-admin user must not be able to force the period/warehouse filter's query logic to run by crafting the request directly (e.g. a manipulated query-string filter payload) — the `->visible()` gate controls DOM rendering only, so if this matters for your threat model, the underlying `SelectFilter`/`Filter` class needs its own `->query()` closure to no-op when `! auth()->user()->isAdmin()`, not just rely on the field being hidden. **Flagged as a decision point, not resolved here**, since the parent blueprint's own posture on filter-level (as opposed to action-level) authorization bypass isn't established elsewhere in either document — Alvin should decide whether table filters warrant the same `->authorize()`-independent-of-`->visible()` rigor the parent blueprint applies to Actions (Principle #12), or whether filters are considered lower-stakes (read-only, non-destructive) and the DOM-hiding is accepted as sufficient here.

---

## 📊 Dashboard — Sales Charts (extends Section 7's Bento Grid)

New widgets, following the exact `ChartWidget` pattern and caching discipline already established for `LowStockAlertsWidget` / `RecentMovementsWidget`:

| Widget | Data Source | Cache TTL | Type |
|---|---|---|---|
| **SalesRevenueTrendWidget** | Daily `Sale` movement value (`\|quantity\| × unit_sale_price_snapshot` from `sales_order_items`, bucketed by dispatch day, last 30 days) | 300s | ChartWidget (line) |
| **TopSellingVariantsWidget** | Top 10 variants by total dispatched base qty across `Sale` movements, current month | 300s | ChartWidget (bar, horizontal) |
| **SalesVsPurchasesWidget** | Side-by-side monthly total: `Purchase` movement value (qty × `unit_cost_price`) vs `Sale` movement value (qty × `unit_sale_price_snapshot`), last 6 months | 300s | ChartWidget (bar, grouped) |
| **PendingFulfillmentWidget** | Count of `SalesOrder` in `Confirmed`/`PartiallyDispatched` and `PurchaseOrder` in `Ordered`/`PartiallyReceived`, scoped to user's warehouses | 60s | TableWidget |

**Implementation notes (mirrors existing widget conventions exactly):**
- All widgets gate on Admin/Auditor role in `getData()`, returning empty structure otherwise — same as `LowStockAlertsWidget`.
- Cache keys: `sales_revenue_trend_{userId}_{firstWarehouseId}`, `top_selling_variants_{userId}_{firstWarehouseId}_{month}`, `sales_vs_purchases_{userId}_{firstWarehouseId}`, no cache key needed for `PendingFulfillmentWidget` beyond its 60s TTL bucket.
- Warehouse-id scoping enforced on every underlying query — same as existing widgets' edge-case coverage list.
- **Money precision in charts:** chart datasets pass pre-rounded floats to Chart.js for *display only*; the underlying aggregate query must use `bcmul`/`SUM()` on the decimal columns server-side, never sum floats in PHP, to avoid the same class of precision loss `[FIX v11]` fixed in `scanToReceive()`.
- `SalesVsPurchasesWidget` is a genuinely new **query shape** (two aggregates joined by month across two different tables) — flag this explicitly to the implementer as the one widget in this addendum that is *not* a drop-in copy of the existing pattern and deserves its own close look during review.

**Pest coverage added (widgets):**
```
SalesRevenueTrendWidgetTest::chart_data_cached_for_300_seconds()
SalesRevenueTrendWidgetTest::revenue_computed_via_bcmath_not_float_sum()
SalesRevenueTrendWidgetTest::warehouse_id_scoping_on_every_query()
SalesRevenueTrendWidgetTest::non_admin_receives_empty_data()
SalesRevenueTrendWidgetTest::empty_warehouse_returns_empty_chart()
TopSellingVariantsWidgetTest::ranks_by_dispatched_base_qty_descending()
TopSellingVariantsWidgetTest::limits_to_top_10()
TopSellingVariantsWidgetTest::excludes_cancelled_and_draft_orders()
SalesVsPurchasesWidgetTest::monthly_aggregates_match_bcmath_reference_values()
SalesVsPurchasesWidgetTest::six_month_window_boundary_is_inclusive()
SalesVsPurchasesWidgetTest::warehouse_id_scoping_on_both_aggregates()
PendingFulfillmentWidgetTest::counts_only_confirmed_and_partially_states()
PendingFulfillmentWidgetTest::excludes_cancelled_and_completed()
```

---

## 🧩 What Connects to This Module (Integration Points & Edge Cases)

This section is the "anything that can be connected to it" audit — every existing blueprint component this addendum touches, plus the failure modes to test.

### 1. `ProductVariant.availableQuantity()`
- **Connection:** Now nets three things instead of two (A5).
- **Edge case:** A variant with confirmed sales reservations *and* confirmed transfer reservations simultaneously must show both deducted — test with overlapping reservations on the same variant/warehouse.
- **Edge case:** A variant with **zero** `stock_movements` rows at all must return `0`, not throw, from all three component methods (existing `onHandQuantity()` behavior — verify it still holds once combined).

### 2. `product_variant_prices` (`is_current` uniqueness constraint)
- **Connection:** `receivePurchase()` writes a new `is_current=true` row and must flip the old one to `false` in the **same transaction** — a race here would violate the "at most one `is_current=true` per variant" constraint documented in the parent blueprint's Section 2.
- **Edge case:** Two purchase orders for the same variant, both with `update_cost_price=true`, received concurrently — needs the same `lockForUpdate()` discipline the parent blueprint already applies to `ProductVariant` in `recordMovement()`. Test explicitly: `PurchaseServiceTest::concurrent_receipts_with_cost_update_do_not_violate_is_current_uniqueness()`.

### 3. `StockMovementResource` (read-only audit ledger)
- **Connection:** New `type` badge colors needed for `Purchase` (success/green, like `Receive`), `Sale` (danger/red or a distinct color from `TransferOut`, since it's now a different kind of outbound), `SaleReturn`, `PurchaseReturn`.
- **Edge case:** The existing footer sum row (`$records->sum('quantity')`) will now mix transfer, purchase, and sale movements in one signed sum — confirm this is still the intended semantic (net physical stock change) or whether Purchases/Sales need a **separate filtered view/tab**, since "net movement including sales" is a very different number from "net movement excluding sales" for anyone doing warehouse reconciliation. **Flag this as a decision point for Alvin, not an assumed default.**

### 4. `LowStockAlertsWidget`
- **Connection:** `isBelowReorderPoint()` / the widget's underlying query currently compares against `availableQuantity()` (indirectly, per Section 7's query pattern description) — once sales reservations are netted in, **more variants will appear low-stock** than before (sold-but-not-yet-shipped stock now correctly reduces "available"). This is a correct behavior change, but will look like a regression if not communicated — document it as an expected side effect, and add a test asserting the new lower numbers are intentional: `LowStockAlertsWidgetTest::confirmed_sales_reservations_reduce_available_quantity_for_reorder_check()`.

### 4A. `InventoryService`'s transfer dispatch checks stock against `onHandQuantity()`, never `availableQuantity()` — this is a pre-existing v11.0 characteristic, not something this addendum introduces, but it now has a sharper failure mode.

- **What's actually happening in v11.0 today:** `recordMovement()`, `directTransfer()`, and `dispatchTransfer()` all guard insufficient stock by checking `onHandQuantity($warehouseId) < $qty` — **never** `availableQuantity($warehouseId)`. `availableQuantity()` exists purely as a display accessor (used by `LowStockAlertsWidget`'s reorder-point comparison); it is not enforced as a dispatch guard anywhere in the parent service layer. This means v11.0 can already let a `Confirmed` transfer requisition's reserved stock be dispatched away by a **different**, unrelated `directTransfer()` call on the same variant/warehouse, since `directTransfer()` doesn't check `reservedQuantity()` either. **This is not a bug introduced by this addendum — it is how the parent blueprint's service layer is currently written.**
- **What this addendum inherits, unchanged:** `SalesService::dispatchSale()` (per this addendum's Service Layer section) follows the *exact same pattern* the parent blueprint already uses — it checks `onHandQuantity()`, not `availableQuantity()`. This is intentional consistency with existing code, not a new gap invented here.
- **Why the failure mode is sharper now than before:** before this addendum, the only thing that could be over-committed against `onHandQuantity()` was *other transfers* — an internal, self-correcting problem (both sides are warehouses you own, and Principle #14's no-reversal stance already accepts that transfers are irreversible once dispatched). Now, a **sales order confirmed against a customer** can also be silently starved by a transfer that only checked `onHandQuantity()` and ignored the sales reservation sitting on top of it. A customer promise can be broken by an internal warehouse-to-warehouse move that had no visibility into that promise.
- **This is a design decision for Alvin, not something to silently fix or silently leave.** Two honest options, presented without a default:
  1. **Leave as-is (status quo, lowest risk to existing behavior):** accept that `onHandQuantity()`-only checks are the system's existing posture everywhere, including for sales. Operationally mitigate by training staff to check the dashboard's "available" figure before initiating a transfer, and rely on `dispatchSale()`'s own guard to reject dispatch if stock genuinely runs out (the sale itself won't oversell — it'll correctly fail at *its own* dispatch time — the risk is only that it fails when it didn't need to, because a transfer took stock that was supposed to be reserved for it).
  2. **Harden `directTransfer()`/`dispatchTransfer()` to check `availableQuantity()` instead of `onHandQuantity()`:** this is a **breaking change to the parent v11.0 service layer**, not an addendum-scoped change — it touches code this addendum was explicitly instructed not to modify beyond `availableQuantity()`'s formula itself (see this addendum's opening scope boundary). If Alvin wants this, it should be scoped and reviewed as its own follow-up change to the parent blueprint, with its own audit — e.g. does tightening this check change any *existing* transfer-only Pest test's expected outcome, since some of those tests may currently rely on `onHandQuantity()`-only semantics.
- **Minimum required action regardless of which option Alvin picks:** add a test that makes today's actual behavior explicit and intentional, not accidental: `ConcurrencyTest::transfer_dispatch_can_deplete_stock_reserved_by_a_confirmed_sales_order_pre_existing_behavior()`. This test should currently **pass** (documenting the gap as it exists), and serves as the regression marker if/when Alvin later decides to close it.

### 5. `WarehouseResource` deletion guard
- **Connection:** `warehouses.restrictOnDelete` already blocks deletion when referenced by transfers. Must now also block when referenced by `purchase_orders.warehouse_id` or `sales_orders.warehouse_id` — this is automatic via the FK constraint itself (no code change), but **add an explicit test** since it's a new referential path the original `LedgerIntegrityTest` suite didn't cover: `LedgerIntegrityTest::warehouse_delete_restricted_by_purchase_orders()`, `LedgerIntegrityTest::warehouse_delete_restricted_by_sales_orders()`.

### 6. `ProductVariant` / `ProductVariantPolicy` soft-delete & restrict guards
- **Connection:** `product_variant_id` is `restrictOnDelete` on both new item tables — same pattern, automatic via FK, but needs the same explicit test treatment as #5: `LedgerIntegrityTest::force_delete_variant_restricted_by_purchase_order_items()`, `LedgerIntegrityTest::force_delete_variant_restricted_by_sales_order_items()`.

### 7. Authorization Mapping (Section 12 of parent blueprint)
New policy abilities required, following the existing table's exact format:

| Action | `->authorize()` | `->visible()` |
|---|---|---|
| OrderPurchaseAction | orderPurchase | status === Draft |
| ReceivePurchaseAction | receivePurchase | status ∈ {Ordered, PartiallyReceived} |
| CancelPurchaseAction | cancelPurchase | status ∈ {Draft, Ordered} **and** no item has received_base_qty > 0 |
| ConfirmSalesOrderAction | confirmSalesOrder | status === Draft |
| DispatchSaleAction | dispatchSale | status ∈ {Confirmed, PartiallyDispatched} |
| RecordSalesReturnAction | recordSalesReturn | any item has dispatched_base_qty > 0 |
| CancelSalesOrderAction | cancelSalesOrder | status ∈ {Draft, Confirmed} |

New policies needed: `PurchaseOrderPolicy`, `SalesOrderPolicy`, `SupplierPolicy`, `CustomerPolicy` — same shape as `TransferRequisitionPolicy` (viewAny, view, create, update, delete, restore, forceDelete, plus the custom abilities above). Full concrete sketches of all four are in the "📐 Concrete Resource Sketch" section above, immediately following `SupplierResource.php`/`CustomerResource.php`.

> **Critical implementation note (mirrors the parent blueprint's own critical note verbatim in spirit):** `CancelPurchaseAction` and `CancelSalesOrderAction` must independently re-verify their guard conditions in the Policy class, not merely rely on `->visible()`. Add the equivalent Playwright E2E scenario: *attempt to invoke `cancelSalesOrder` via direct Livewire method call on a `Dispatched` order → verify the policy still rejects it server-side.*

> **`[A8]` This table's "→visible()" column is display logic ONLY, composed with a Policy call where a permission check is involved — it is never itself the place a permission/role decision is decided.** Per Principle A8 above, the actual "is this user allowed" logic for every row in this table lives exclusively inside the named Policy method (`orderPurchase`, `receivePurchase`, `cancelPurchase`, `confirmSalesOrder`, `dispatchSale`, `recordSalesReturn`, `cancelSalesOrder`) — the `->visible()` column's status-allowlist is purely about which UI state the button appears in, not about who is allowed to press it. Once each of these seven Policy methods is implemented and verified correct, per A8 it is not modified again except for a genuinely new ability or a demonstrated bug — everything else in the system that needs to know "can this user do X" calls the existing method rather than re-deriving the answer.

### 8. i18n (Section 15 of parent blueprint)
All new enum `getLabel()` calls route through `__()`; all new resource labels get entries in `lang/en/`, `lang/es/`, `lang/tl/` — same mandate, no exceptions.

### 9. `->strictAuthorization()` (Phase 00, Principle #8)
Every new custom ability listed in #7 above must be enumerated and covered by a policy method **before** this addendum's resources are registered, or the panel will fail closed on first use — same gotcha the parent blueprint calls out for `dispatch`/`receive`/`cancel`/`setPrice`/`recordLoss`/`adjustStock`.

### 9A. `[A8]` Parent v11.0 Policy Consolidation Audit — applies to every existing policy, not just this addendum's four new ones
Per Principle A8, this addendum's implementation is also the trigger for an audit of the **nine existing parent v11.0 policies** (`ProductPolicy`, `ProductVariantPolicy`, `TransferRequisitionPolicy`, `TransferRequisitionItemRevisionPolicy`, `StockMovementPolicy`, `InTransitPolicy`, `LossLedgerPolicy`, `WarehousePolicy`, `UserPolicy`) for any permission/role logic that currently lives outside those policy classes — most likely candidates, based on what the parent blueprint document itself shows: the `->visible(fn () => auth()->user()->isAdmin())` double-guard on `ForceDeleteAction` (Section 6, TransferRequisitionResource table actions, and Section 12's own Authorization Mapping table entry for it), and the parent blueprint's noted "**Implementation extensions beyond blueprint**" callouts (Section 12) describing role checks like "`adjustStock` and `recordLoss` return `true` for all authenticated users" and "`delete` includes self-protection guard" — confirm these live inside the actual `WarehousePolicy`/`UserPolicy` method bodies in the real codebase (not just described in the blueprint's prose) and are not *also* duplicated as an inline check anywhere else. Where an inline check is found outside a Policy class, consolidate it into the appropriate Policy method as part of this audit, per A8 — this is explicitly in scope for this addendum's implementation work precisely because A8 is a system-wide principle, not an addendum-scoped one, even though the nine files themselves belong to the parent blueprint. **Full concrete sketches of all nine existing policies, reconstructed from the parent blueprint's own method table and extension notes, are in the "📐 Concrete Resource Sketch" section above** (immediately following the four new addendum policies) — these are the target shape Phase 4 of the implementation prompt reconciles against the real codebase, not a description to re-derive from scratch.

### 10. `[Audit finding v11.1]` `->money()` currency hardcoding — a pre-existing parent v11.0 inconsistency, not something this addendum introduces, but this addendum's resources should not copy it.
- **What was found:** the parent blueprint's own Principle #10 states *"All `->money()` calls pass `config('app.currency')`"*, and Section 13's Cross-Cutting Verification Checklist marks this ✅. However, `LossLedgerResource`'s actual code (both its Table and Infolist) hardcodes `->money('PHP', locale: 'en_PH', decimals: 4)` in four separate places — this contradicts the parent's own stated principle and its own checklist claim. This is a genuine drift in the *parent* v11.0 blueprint document, discovered while auditing this addendum, not a defect this addendum introduces.
- **What this addendum does about it:** `PurchaseOrderInfolist` and `SalesOrderInfolist` above use `->money(config('app.currency'), decimals: 4)` — correctly following Principle #10 — rather than copying `LossLedgerResource`'s hardcoded `'PHP'` pattern, even though visual consistency with the existing `LossLedgerResource` might otherwise argue for matching it verbatim. Compliance with the parent's own explicitly-stated principle takes precedence over matching a component that already violates that principle.
- **What Alvin should decide, separately from this addendum's implementation:** whether to fix `LossLedgerResource`'s four hardcoded `'PHP'` calls in the parent v11.0 codebase to match Principle #10, since as written, any deployment targeting a currency other than PHP will show inconsistent currency formatting between `LossLedgerResource` (hardcoded PHP) and every other money column in the system (config-driven). This is a **parent blueprint fix**, out of this addendum's additive-only scope — flagged here only because auditing the addendum's own dashboard/infolist work is what surfaced it.

---

## 🧪 Full Edge-Case Test Matrix (per the project's test coverage mandate)

Per the project's standing practice — *"boundary values, concurrency/lock ordering, state-boundary transitions, idempotency, null relation paths, decimal precision, constraint violations, and authorization bypass attempts"* — applied specifically to this addendum:

| Category | Test |
|---|---|
| Boundary values | Receiving/dispatching exactly the outstanding qty (0 remaining after) succeeds; qty = 0 is a no-op, not an error; qty < 0 throws |
| Boundary values | `unit_ratio < 1` rejected on `purchase_order_items` / `sales_order_items` creation, same guard as `[FIX v11]` Gap #9 |
| Concurrency / lock ordering | Two `receivePurchase()` calls on the same PO, same item, concurrently — second must see the updated `received_base_qty` and correctly reject over-receipt, not race past it |
| Concurrency / lock ordering | Two `dispatchSale()` calls against the same variant/warehouse with combined qty > on-hand — only one should succeed, the other must throw insufficient-stock, never allow negative on-hand |
| State-boundary transitions | `receivePurchase()` on a `Draft` or `Cancelled` PO throws (not receivable) |
| State-boundary transitions | `dispatchSale()` on a `Draft` sales order throws (must be confirmed first) |
| State-boundary transitions | `cancelPurchaseOrder()` throws once `received_base_qty > 0` on any item, even if other items are unreceived |
| State-boundary transitions | `cancelSalesOrder()` throws once status is `PartiallyDispatched`, `Dispatched`, or `Completed` |
| Idempotency | Submitting the same `receivePurchase()` payload twice (e.g., item already fully received) results in a no-op for that item, not a duplicate `Purchase` movement — mirrors `[FIX v11]` Gap #4's state-equality philosophy |
| Null relation paths | `receivePurchase()` with `update_cost_price=true` on a variant with **no** `currentPrice` row at all (null-safe `?->` required, same as `LossLedger::snapshotUnitCostFrom()`) |
| Null relation paths | `confirmSalesOrder()` on a line item whose variant has no `currentPrice` — snapshot falls back to `'0.0000'`, not a fatal null-property error |
| Decimal precision | `SalesOrderItem::lineTotal()` and all chart-widget monetary aggregates verified against a `bcmath`-computed reference value, never a float-cast comparison |
| Decimal precision | Cost-price update on receipt correctly compares old vs new cost via `bccomp()`, not `==`/`!=` on decimal strings (floating-point string comparison pitfalls) |
| Constraint violations | Attempting to delete a `Supplier`/`Customer` referenced by any PO/SO throws (restrictOnDelete) |
| Constraint violations | Attempting to force-delete a `ProductVariant` referenced by any `purchase_order_items`/`sales_order_items` row throws |
| Constraint violations | Two `is_current=true` rows for the same variant cannot coexist even under concurrent purchase receipts (see Integration Point #2) |
| Authorization bypass | Direct Livewire method invocation of `dispatchSale`/`receivePurchase`/`cancelSalesOrder`/`cancelPurchaseOrder` bypassing `->visible()`, verified rejected by the Policy layer independently |
| Authorization bypass | Non-admin user attempting `update_cost_price`-flagged receipt when `setPrice` ability (already defined in parent blueprint) is denied to them — decide: does `receivePurchase` need to *also* check `setPrice` when `update_cost_price=true`? **Flag as an open policy-composition question for review**, not a pre-decided answer. |
| Cross-cutting with parent system | Confirmed sales reservation on a variant does **not** appear in, or affect, `reservedQuantity()`'s Confirmed-transfer-only count (regression guard on `[FIX v11]` Principle #13) |
| Cross-cutting with parent system | A `TransferOut` dispatch and a `Sale` dispatch racing against the same variant/warehouse's on-hand stock — both must use the same `ProductVariant::lockForUpdate()` row lock, so one blocks until the other's transaction commits (no double-spend across the two subsystems) |

---

## 📌 Deferred (v2 — explicitly out of scope for this addendum)

1. **Supplier shipment / transit tracking for purchases** — a shipping leg between supplier and warehouse with its own loss ledger, mirroring `in_transits`/`loss_ledgers`. Deferred per A7; do not retrofit into `loss_ledgers` (FK-scoped to `transfer_requisitions`).
2. **Purchase-side negotiation** (price counter-offers with a supplier) — no equivalent to `NegotiationService` is planned; POs are assumed pre-negotiated externally before entry.
3. **FIFO / weighted-average / lot-level COGS costing** — v1 sales use current `currentPrice.cost_price` for margin reporting if needed later; true lot-costing is a substantially larger change (per-movement cost layers) and is explicitly not in this addendum.
4. **`PurchaseReturn` full workflow UI** — the movement type is defined for schema completeness (Section "New Database Schema," item 20) but no resource/action is specified for triggering it in v1; add only if/when needed.
5. **Backorder auto-fulfillment** — when a `PartiallyDispatched` sales order's remaining qty becomes available (e.g., via a subsequent purchase receipt), no automatic notification or fulfillment trigger is specified. Manual re-check only, for now — same posture as the parent blueprint's deferred event/notification layer (its own Section 10, item #2).
6. **Reporting-view decision from Integration Point #3** (separate Purchases/Sales tab on `StockMovementResource` vs. one mixed ledger) — left as an open decision for Alvin, not resolved here.
7. **Hardening transfer dispatch to check `availableQuantity()` instead of `onHandQuantity()`** (Integration Point #4A) — this would be a breaking change to the *parent v11.0* `InventoryService`, out of this addendum's additive-only scope by design. Left as an explicit open decision for Alvin, with a pre-existing-behavior regression test in place as the marker if he later chooses to pursue it as its own reviewed change to the parent blueprint.
8. **Applying `AdminReviewFilters` to the parent blueprint's `StockMovementsTable`/`LossLedgersTable`** — presented as an optional convenience in the "System-Admin-Only Warehouse & Period Filters" section, since it touches parent-blueprint files outside this addendum's additive-only scope. Alvin's call on whether to take it.
9. **Filter-level authorization bypass hardening** (same section, "Edge cases" callout) — whether `AdminReviewFilters::warehouse()`/`period()` need their `->query()` closures to independently no-op for non-admin users, rather than relying solely on `->visible()` to hide the field from the DOM. The parent blueprint applies this rigor to Actions (Principle #12: `->authorize()` vs `->visible()`) but never establishes a position on read-only table Filters specifically. Left open rather than assumed.

---

## ✅ Permanence Checklist for Principle A8 (Policy Consolidation)

This is not a checklist for the whole addendum — the rest of this document's edge-case matrix and integration points cover that. This one exists specifically to make A8's "verified correct, then frozen" claim checkable rather than aspirational, the same way the parent blueprint's Section 13 makes Principle #13's `reservedQuantity()` boundary checkable rather than just asserted in prose. Do not check an item off until it is actually true in the codebase, not merely intended.

| Check | Status |
|---|---|
| `PurchaseOrderPolicy`, `SalesOrderPolicy`, `SupplierPolicy`, `CustomerPolicy` exist and contain 100% of this addendum's permission/role logic | ☐ |
| No `->visible()` closure anywhere in this addendum's resources re-derives a permission decision instead of composing a policy call | ☐ |
| No Service method in `PurchaseService`/`SalesService` contains a role check (`isAdmin()`, `role === ...`, etc.) — only data-integrity/state-machine guards | ☐ |
| `PolicyAuditTest` (or equivalent architecture/static check) exists and passes against the current codebase, covering `app/Filament` and `app/Services` | ☐ |
| Parent v11.0's nine existing policies audited per Integration Point 9A; any found inline role logic consolidated into the relevant Policy method | ☐ |
| Alvin has been shown the audit results from the row above and has confirmed the consolidation (or explicitly declined it) — not silently assumed | ☐ |
| Every Policy method above is documented (in-code doc-block, mirroring `reservedQuantity()`'s style) as closed/frozen per A8, so a future maintainer sees the same permanence signal the parent blueprint gives `reservedQuantity()` | ☐ |

Once every row above is checked, Principle A8 is satisfied for this addendum's scope, and per A8 itself, these Policy methods are not to be reopened except for a genuinely new ability or a demonstrated bug.

---

*End of Addendum v11.1 — Purchases & Sales Module Specification.*