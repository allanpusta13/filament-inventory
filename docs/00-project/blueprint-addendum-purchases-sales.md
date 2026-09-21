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

---

## 🗄️ New Database Schema

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

Add two new cases to the existing `StockMovementType` enum (no migration needed — `type` is already a plain string column):

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

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use SoftDeletes;

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
    use SoftDeletes;

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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use SoftDeletes;

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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    use SoftDeletes;

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

                $remaining = $item->outstandingBaseQty();

                // [EDGE CASE] Over-receipt guard: never allow receiving more
                // than was ordered. Supplier over-shipments must be handled
                // as a separate line item / PO amendment, not silently
                // absorbed here — this keeps ordered_base_qty a reliable
                // upper bound for reporting.
                if ($incomingQty > $remaining) {
                    throw new Exception(
                        "Cannot receive {$incomingQty} units for item #{$item->id}: only ".
                        "{$remaining} units remain outstanding on this purchase order."
                    );
                }

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

                $remaining = $item->outstandingBaseQty();

                if ($qty > $remaining) {
                    throw new Exception(
                        "Cannot dispatch {$qty} units for item #{$item->id}: only ".
                        "{$remaining} units remain outstanding on this sales order."
                    );
                }

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
                        Repeater::make('items')
                            ->relationship('items')
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
                        Repeater::make('items')
                            ->relationship('items')
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
                    ->schema(fn (SalesOrder $record) => collect($record->items)
                        ->map(function ($item) use ($record) {
                            $available = $item->productVariant->availableQuantity($record->warehouse_id);
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
                        ->all())
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

New policies needed: `PurchaseOrderPolicy`, `SalesOrderPolicy`, `SupplierPolicy`, `CustomerPolicy` — same shape as `TransferRequisitionPolicy` (viewAny, view, create, update, delete, restore, forceDelete, plus the custom abilities above).

> **Critical implementation note (mirrors the parent blueprint's own critical note verbatim in spirit):** `CancelPurchaseAction` and `CancelSalesOrderAction` must independently re-verify their guard conditions in the Policy class, not merely rely on `->visible()`. Add the equivalent Playwright E2E scenario: *attempt to invoke `cancelSalesOrder` via direct Livewire method call on a `Dispatched` order → verify the policy still rejects it server-side.*

### 8. i18n (Section 15 of parent blueprint)
All new enum `getLabel()` calls route through `__()`; all new resource labels get entries in `lang/en/`, `lang/es/`, `lang/tl/` — same mandate, no exceptions.

### 9. `->strictAuthorization()` (Phase 00, Principle #8)
Every new custom ability listed in #7 above must be enumerated and covered by a policy method **before** this addendum's resources are registered, or the panel will fail closed on first use — same gotcha the parent blueprint calls out for `dispatch`/`receive`/`cancel`/`setPrice`/`recordLoss`/`adjustStock`.

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

---

*End of Addendum v11.1 — Purchases & Sales Module Specification.*