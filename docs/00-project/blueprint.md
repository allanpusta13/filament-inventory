# Multi-Warehouse Inventory System — Complete System Blueprint (v7.0)

**Stack:** Laravel 13 + FilamentPHP v5 + Livewire v4 | **Database:** PostgreSQL / MySQL | **Architecture:** Pure Derived Stock of Truth Ledger

---

## 🧭 Executive Architecture & System Principles

1. **Pure Derived Stock of Truth**: Physical stock levels, active transit reservations, and available balances are **never** stored in a physical database table (e.g. no `warehouse_stock` table exists). Physical on-hand stock is calculated dynamically at query-time as the sum of all signed records in `stock_movements`. Active reservations sum pending quantities from unreceived requisitions (`confirmed` or `dispatched`), and available stock is derived as `on_hand - reserved`.
2. **Decoupled Pricing & Variant-Level Catalog**:
   - **`sku`** lives **exclusively** on `product_variants`. Parent `products` act purely as family grouping containers (`name`, `category`).
   - **`reorder_point`** lives **exclusively** on `product_variants` (default `0`).
   - **Unit Pricing** is decoupled into `product_variant_prices` (history/override table with `is_current = true`), supporting 4-decimal micro-pricing (`decimal(15,4)`).
3. **Pessimistic Locking & Transaction Isolation**: All stock deductions, dispatches, and intake receipts execute inside atomic database transactions (`DB::transaction()`) using pessimistic row-level locking (`lockForUpdate()`) on `product_variants` and `transfer_requisitions` to guarantee zero concurrency race conditions.
4. **Canonical Foreign Key & Plural Naming**: All database tables use explicit plural `snake_case` names (`transfer_requisitions`, `transfer_requisition_items`, `transfer_requisition_item_revisions`, `in_transits`, `loss_ledgers`), and foreign keys strictly follow table-bound names (`product_variant_id`, `transfer_requisition_id`, `transfer_requisition_item_id`).
5. **Physical-to-Digital State Lifecycle**: Requisitions follow an explicit, physical state machine:
   $$\text{draft} \rightarrow \text{requested} \rightarrow \text{under\_review\_fulfiller} \rightleftharpoons \text{under\_review\_requestor} \rightarrow \text{confirmed} \rightarrow \text{dispatched} \rightarrow \text{completed / closed\_with\_loss / cancelled}$$
6. **Negotiated Substitute Variant Swapping**: Dispatch and receipt pipelines dynamically resolve `$actualVariantId = $item->substitute_product_variant_id ?? $item->product_variant_id` at loop start. Inventory deductions, credit additions, and transit rows apply strictly to the substituted variant SKU.
7. **Scanned Receipt Loss Integrity & Omitted Cargo**: Dispatched items missing from a physical scan payload are not skipped. They are recorded as `0` received, triggering a 100% variance write-off to the `loss_ledgers` table at the variant's cost price, clearing virtual transit rows cleanly.
8. **Signed Web QR Routing**: STN QR codes embed secure 30-day temporary signed URLs (`APP_URL/stn/{id}/scan?signature=...`). Scanning redirects authenticated staff to the scan-to-receive landing page.
9. **Modal-First UI (< 8 Inputs Rule)**: Any setup or adjustment form containing fewer than 8 fields operates inside inline slide-over Drawers or Dialog Modals (`closeModalByClickingAway(false)`) directly from listing views.
10. **Strongly-Typed Icons & Multi-Language i18n**: Raw string icons are strictly prohibited, using `Filament\Support\Icons\Heroicon` enum properties instead. All UI strings are loaded dynamically from locale catalogs (`lang/{locale}/`).

---

## 🗺️ Section 1: Sidebar Navigation Resource Map

| Group Name | Resource | Model | Base Route | Role Visibility | Sort Order | Eager-Loaded Relations (N+1 Guard) |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **[Home]** | Dashboard | — | `/admin` | *All Roles* | — | — |
| **CATALOG** | `ProductResource` | `ProductVariant` | `/admin/products` | *All Roles* | 1 | `product`, `unitConversions`, `currentPrice` |
| **OPERATIONS** | `TransferRequisitionResource` | `TransferRequisition` | `/admin/transfer-requisitions` | *All Roles* (Scoped) | 1 | `fromWarehouse`, `toWarehouse`, `requestedBy`, `items.productVariant` |
| | `DirectTransferResource` | `StockMovement` | `/admin/direct-transfers` | *All Roles* (Scoped) | 2 | `productVariant`, `warehouse`, `relatedMovement.warehouse`, `creator` |
| **AUDIT LEDGERS** | `InTransitResource` | `InTransit` | `/admin/in-transits` | Admin, Auditor, Branch Manager | 1 | `transferRequisition.fromWarehouse`, `transferRequisition.toWarehouse`, `productVariant` |
| | `StockMovementResource` | `StockMovement` | `/admin/stock-movements` | Admin, Auditor, Branch Manager | 2 | `productVariant`, `warehouse`, `creator` |
| | `LossLedgerResource` | `LossLedger` | `/admin/loss-ledgers` | Admin, Auditor | 3 | `transferRequisition`, `productVariant`, `warehouse`, `recordedBy` |
| **SYSTEM ADMIN** | `WarehouseResource` | `Warehouse` | `/admin/warehouses` | Admin Only | 1 | `users` |
| | `UserResource` | `User` | `/admin/users` | Admin Only | 2 | `warehouses` |

> **Model-Level Optimization Note (`ProductResource`)**: `ProductResource` binds directly to `App\Models\ProductVariant` as its primary Eloquent model (`protected static ?string $model = ProductVariant::class;`). Because all inventory movements, GTIN barcodes, stock calculations, packaging conversions, and reorder thresholds operate on specific SKUs, targeting `ProductVariant` eliminates nested sub-query overhead, enables high-speed Card Grid rendering (`contentGrid`), and provides native sorting/searching directly on SKU data while referencing parent family metadata via `product` (`belongsTo`).

---

## 🗄️ Section 2: Complete Database Schema (13 Tables)

```
products ──< product_variants ──< product_variant_unit_conversions
                    │        ├──< product_variant_prices
                    │        └──< stock_movements >── warehouses
                    │
                    └──< transfer_requisition_items >── transfer_requisitions >── warehouses (from/to)
                                │        │                        │
                                │        └──< in_transits          ├──< in_transits
                                │        └──< loss_ledgers          └──< loss_ledgers
                                │
                                └──< transfer_requisition_item_revisions

users ──< user_warehouse >── warehouses
```

### 1. `products`
| Column | Type | Modifiers / Notes |
| :--- | :--- | :--- |
| `id` | bigint | PK |
| `name` | string | Product family name (e.g. "Arabica Specialty Coffee") |
| `category` | string | Nullable |
| `deleted_at` | timestamp | Soft deletes support |
| `timestamps` | timestamp | Created / Updated |

### 2. `product_variants`
| Column | Type | Modifiers / Notes |
| :--- | :--- | :--- |
| `id` | bigint | PK |
| `product_id` | foreignId | FK → `products.id` (cascadeOnDelete) |
| `sku` | string | Unique SKU (e.g. 'PROD-COF-500G') |
| `barcode` | string | Nullable GTIN scanner barcode |
| `name` | string | Variant identifier (e.g. "500g Whole Bean") |
| `base_unit_name` | string | Lowest non-divisible unit (e.g. 'gram', 'piece') |
| `reorder_point` | integer | Default safety threshold in Base Units (default `0`) |
| `attributes` | json | Custom key-value tags (e.g. `{"roast": "Medium"}`) |
| `images` | json | Nullable, array of image file paths |
| `is_active` | boolean | Default `true` |
| `deleted_at` | timestamp | Soft deletes support |
| `timestamps` | timestamp | Created / Updated |

### 3. `product_variant_prices`
| Column | Type | Modifiers / Notes |
| :--- | :--- | :--- |
| `id` | bigint | PK |
| `product_variant_id` | foreignId | FK → `product_variants.id` (cascadeOnDelete) |
| `cost_price` | decimal(15,4) | High-precision unit cost price (default `0.0000`) |
| `sale_price` | decimal(15,4) | High-precision unit selling price (default `0.0000`) |
| `effective_from` | timestamp | Default current time |
| `is_current` | boolean | Default `true` (prior row must be unset) |
| `set_by` | foreignId | Nullable, FK → `users.id` (nullOnDelete) |
| `notes` | text | Nullable price change notes |
| `timestamps` | timestamp | Created / Updated |

### 4. `product_variant_unit_conversions`
| Column | Type | Modifiers / Notes |
| :--- | :--- | :--- |
| `id` | bigint | PK |
| `product_variant_id` | foreignId | FK → `product_variants.id` (cascadeOnDelete) |
| `unit_name` | string | Packaging name (e.g. 'Box', 'Pallet') |
| `base_unit_ratio` | integer | Multiplier relative to base unit (1 Box = 24 Pcs → 24) |
| `is_default_purchase`| boolean | Default for receiving POs (default `false`) |
| `is_default_transfer`| boolean | Default for transfer requisitions (default `false`) |
| `timestamps` | timestamp | Created / Updated |

### 5. `warehouses`
| Column | Type | Modifiers / Notes |
| :--- | :--- | :--- |
| `id` | bigint | PK |
| `code` | string | Short branch code, unique (e.g. 'WH-MNL') |
| `name` | string | Warehouse or branch display name |
| `location` | string | Nullable location/address details |
| `is_active` | boolean | Default `true` |
| `timestamps` | timestamp | Created / Updated |

### 6. `stock_movements` (Pure Ledger Source of Truth)
| Column | Type | Modifiers / Notes |
| :--- | :--- | :--- |
| `id` | bigint | PK |
| `product_variant_id` | foreignId | FK → `product_variants.id` (cascadeOnDelete) |
| `warehouse_id` | foreignId | FK → `warehouses.id` (cascadeOnDelete) |
| `type` | string | Enum: `receive`, `ship`, `transfer_out`, `transfer_in`, `transit_out`, `transit_in`, `adjustment`, `loss` |
| `quantity` | integer | **Signed integer strictly in Base Units** (+ for credit, - for debit) |
| `unit_name_used` | string | Packaging format label used during transaction |
| `unit_ratio_used` | integer | Conversion ratio active at time of movement (default `1`) |
| `related_movement_id`| foreignId | Nullable, FK → `stock_movements.id` (nullOnDelete, links transfer legs) |
| `reference_type` | string | Nullable polymorphic class |
| `reference_id` | unsignedBigInteger | Nullable polymorphic ID |
| `reference_code` | string | Nullable business code (e.g. 'DTR-20260908-XXXX') |
| `created_by` | foreignId | Nullable, FK → `users.id` (nullOnDelete) |
| `timestamps` | timestamp | Created / Updated |

### 7. `transfer_requisitions`
| Column | Type | Modifiers / Notes |
| :--- | :--- | :--- |
| `id` | bigint | PK |
| `reference_code` | string | Unique index (e.g. 'TRQ-20260908-0001') |
| `from_warehouse_id` | foreignId | FK → `warehouses.id` (Fulfilling origin) |
| `to_warehouse_id` | foreignId | FK → `warehouses.id` (Requesting destination) |
| `status` | string | Enum: `draft`, `requested`, `under_review_fulfiller`, `under_review_requestor`, `confirmed`, `dispatched`, `completed`, `closed_with_loss`, `cancelled` |
| `requested_by` | foreignId | FK → `users.id` |
| `approved_by` | foreignId | Nullable, FK → `users.id` |
| `dispatched_by` | foreignId | Nullable, FK → `users.id` |
| `received_by` | foreignId | Nullable, FK → `users.id` |
| `requested_at` | timestamp | Nullable |
| `approved_at` | timestamp | Nullable |
| `dispatched_at` | timestamp | Nullable |
| `completed_at` | timestamp | Nullable |
| `notes` | text | Nullable |
| `deleted_at` | timestamp | Soft deletes support |
| `timestamps` | timestamp | Created / Updated |

### 8. `transfer_requisition_items`
| Column | Type | Modifiers / Notes |
| :--- | :--- | :--- |
| `id` | bigint | PK |
| `transfer_requisition_id` | foreignId | FK → `transfer_requisitions.id` (cascadeOnDelete) |
| `product_variant_id` | foreignId | FK → `product_variants.id` |
| `substitute_product_variant_id` | foreignId | Nullable, FK → `product_variants.id` (Negotiated swap SKU) |
| `requested_unit_name` | string | Packaging unit label requested |
| `requested_unit_ratio` | integer | Ratio at time of request |
| `requested_qty` | integer | Quantity in requested packaging unit |
| `requested_base_qty` | integer | Computed Base Unit quantity |
| `approved_unit_name` | string | Nullable, negotiated packaging unit |
| `approved_unit_ratio` | integer | Nullable, negotiated ratio |
| `approved_qty` | integer | Nullable, negotiated quantity |
| `approved_base_qty` | integer | Nullable, approved Base Unit quantity |
| `shipped_base_qty` | integer | Actual base units dispatched (default `0`) |
| `received_good_base_qty` | integer | Good base units received (default `0`) |
| `received_damaged_base_qty` | integer | Damaged base units received (default `0`) |
| `received_qty` | integer | Nullable, confirmed count at intake |
| `notes` | text | Nullable |
| `timestamps` | timestamp | Created / Updated |

### 9. `transfer_requisition_item_revisions`
| Column | Type | Modifiers / Notes |
| :--- | :--- | :--- |
| `id` | bigint | PK |
| `transfer_requisition_item_id` | foreignId | FK → `transfer_requisition_items.id` (cascadeOnDelete) |
| `user_id` | foreignId | FK → `users.id` |
| `product_variant_id` | foreignId | FK → `product_variants.id` |
| `substitute_product_variant_id` | foreignId | Nullable, FK → `product_variants.id` |
| `proposed_unit_name` | string | Packaging label proposed |
| `proposed_qty` | integer | Packaging quantity proposed |
| `proposed_base_qty` | integer | Computed base quantity proposed |
| `negotiation_reason` | text | Nullable compliance reason |
| `side` | string | Enum: `fulfiller`, `requestor` |
| `status` | string | Enum: `pending`, `accepted`, `rejected`, `superseded` |
| `responds_to_revision_id` | foreignId | Nullable, self-referencing FK |
| `responded_at` | timestamp | Nullable |
| `timestamps` | timestamp | Created / Updated |

### 10. `in_transits`
| Column | Type | Modifiers / Notes |
| :--- | :--- | :--- |
| `id` | bigint | PK |
| `transfer_requisition_id` | foreignId | FK → `transfer_requisitions.id` (cascadeOnDelete) |
| `transfer_requisition_item_id` | foreignId | FK → `transfer_requisition_items.id` (cascadeOnDelete) |
| `product_variant_id` | foreignId | FK → `product_variants.id` |
| `dispatched_base_qty` | integer | Base Unit quantity traveling |
| `dispatched_at` | timestamp | Dispatch timestamp |
| `status` | string | Enum: `in_transit`, `partially_received`, `cleared` |
| `timestamps` | timestamp | Created / Updated |

### 11. `loss_ledgers`
| Column | Type | Modifiers / Notes |
| :--- | :--- | :--- |
| `id` | bigint | PK |
| `transfer_requisition_id` | foreignId | FK → `transfer_requisitions.id` |
| `transfer_requisition_item_id` | foreignId | Nullable, FK → `transfer_requisition_items.id` |
| `product_variant_id` | foreignId | FK → `product_variants.id` |
| `warehouse_id` | foreignId | FK → `warehouses.id` (Destination site bearing loss) |
| `lost_base_qty` | integer | Missing/unaccounted units (default `0`) |
| `damaged_base_qty` | integer | Physically damaged units (default `0`) |
| `unit_cost_price` | decimal(15,4) | Cost price snapshot at incident time from `currentPrice` |
| `total_financial_loss` | decimal(15,4) | Total financial write-off value |
| `loss_category` | string | Default 'shortfall' (e.g. 'damaged', 'omitted') |
| `recorded_by` | foreignId | Nullable, FK → `users.id` |
| `recorded_at` | timestamp | Default current timestamp |
| `timestamps` | timestamp | Created / Updated |

### 12. `users` & 13. `user_warehouse` (Pivot)
```php
// Schema::table('users')
$table->string('role')->default('warehouse_staff')->after('password');

// Schema::create('user_warehouse')
$table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
$table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
$table->primary(['user_id', 'warehouse_id']);
```

---

## 🧙‍♂️ Section 3: Transfer Transaction Wizard Schemas

### A. Inter-Warehouse Transfer Requisitions (`TransferRequisitionForm.php`)
Configured as an in-page **3xl Dialog Modal** wizard (`closeModalByClickingAway(false)`):
1. **Step 1: Routing Pathways**:
   - `from_warehouse_id`: Origin/Fulfiller Warehouse (`Select`, required, options lookup).
   - `to_warehouse_id`: Destination/Requestor Warehouse (`Select`, required, `different('from_warehouse_id')`).
2. **Step 2: Material Manifest**:
   - `items` Repeater (`disableOptionsWhenSelectedInSiblingRepeaterItems()`): `product_variant_id`, `requested_unit_name`, `requested_unit_ratio`, and positive `requested_qty` (`minValue(1)`).
3. **Step 3: Review & Verify**:
   - Reactive live preview (`TextEntry::make('review_summary')->state(...)`) rendering an inline HTML table of selected SKUs, formats, multipliers, and computed base unit totals prior to submission.

### B. Instant Direct Transfers (`DirectTransferForm.php`)
Configured as a 3-step **3xl Dialog Modal** wizard:
1. **Step 1: Location Mapping**:
   - `from_warehouse_id` & `to_warehouse_id` (`Select`, required, `different()`).
2. **Step 2: Stock Allocation**:
   - `product_variant_id` (`Select`, required).
   - `quantity` (`TextInput`, positive integer, `minValue(1)`).
   - `notes` (`Textarea`, required, `minLength(15)`, strict regex validator rejecting generic entries).
3. **Step 3: Review & Verify**:
   - Reactive live preview (`TextEntry::make('review_summary')->state(...)`) detailing the immediate two-leg transfer confirmation statement, locations, and audit compliance note.

---

## 🛠️ Section 4: Model-Level Pure Derived Stock Engine

On-hand physical stock, active requisition allocations, and physical stock availability are calculated dynamically at query-time on the `ProductVariant` model:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'sku', 'barcode', 'name', 'base_unit_name',
        'reorder_point', 'attributes', 'images', 'is_active',
    ];

    protected $casts = [
        'reorder_point' => 'integer',
        'attributes' => 'array',
        'images' => 'array',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductVariantPrice::class);
    }

    public function currentPrice(): HasOne
    {
        return $this->hasOne(ProductVariantPrice::class)->where('is_current', true);
    }

    public function unitConversions(): HasMany
    {
        return $this->hasMany(ProductUnitConversion::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function requisitionItems(): HasMany
    {
        return $this->hasMany(TransferRequisitionItem::class);
    }

    /**
     * Physical on-hand stock: Raw SUM of signed stock movements.
     */
    public function onHandQuantity(int $warehouseId): int
    {
        return StockMovement::where('product_variant_id', $this->id)
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');
    }

    /**
     * Active Requisition Reservations: SUM of approved_base_qty locked in confirmed/dispatched requisitions.
     */
    public function reservedQuantity(int $warehouseId): int
    {
        return TransferRequisitionItem::where('product_variant_id', $this->id)
            ->whereHas('transferRequisition', function ($q) use ($warehouseId) {
                $q->where('from_warehouse_id', $warehouseId)
                  ->whereIn('status', ['confirmed', 'dispatched']);
            })
            ->sum('approved_base_qty');
    }

    /**
     * Available physical stock: On-Hand minus active Reservations.
     */
    public function availableQuantity(int $warehouseId): int
    {
        return $this->onHandQuantity($warehouseId) - $this->reservedQuantity($warehouseId);
    }
}
```

---

## ⚙️ Section 5: Transactional Service Layer

```php
namespace App\Services;

use App\Models\InTransit;
use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use Illuminate\Support\Facades\DB;
use Exception;

class InventoryService
{
    public function recordMovement(
        int $productVariantId,
        int $warehouseId,
        string $type,
        int $baseQuantity,
        ?string $unitName = null,
        int $unitRatio = 1,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $referenceCode = null,
        ?int $relatedMovementId = null
    ): StockMovement {
        return DB::transaction(function () use (
            $productVariantId, $warehouseId, $type, $baseQuantity,
            $unitName, $unitRatio, $referenceType, $referenceId,
            $referenceCode, $relatedMovementId
        ) {
            $variant = ProductVariant::lockForUpdate()->findOrFail($productVariantId);
            $currentStock = $variant->onHandQuantity($warehouseId);

            if ($baseQuantity < 0 && ($currentStock + $baseQuantity) < 0) {
                throw new Exception("Insufficient stock for SKU {$variant->sku} at Warehouse ID {$warehouseId}. Available: {$currentStock}, Requested deduction: " . abs($baseQuantity));
            }

            return StockMovement::create([
                'product_variant_id' => $productVariantId,
                'warehouse_id' => $warehouseId,
                'type' => $type,
                'quantity' => $baseQuantity,
                'unit_name_used' => $unitName ?? $variant->base_unit_name,
                'unit_ratio_used' => $unitRatio,
                'related_movement_id' => $relatedMovementId,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_code' => $referenceCode,
                'created_by' => auth()->id(),
            ]);
        });
    }

    public function dispatchTransfer(int $requisitionId): void
    {
        DB::transaction(function () use ($requisitionId) {
            $requisition = TransferRequisition::with('items.productVariant')->lockForUpdate()->findOrFail($requisitionId);

            if ($requisition->status !== 'confirmed') {
                throw new Exception("Requisition must be confirmed before dispatch. Current: {$requisition->status}");
            }

            foreach ($requisition->items as $item) {
                $actualVariantId = $item->substitute_product_variant_id ?? $item->product_variant_id;
                $dispatchQty = $item->approved_base_qty ?? $item->requested_base_qty;

                $variant = ProductVariant::lockForUpdate()->findOrFail($actualVariantId);
                if ($variant->onHandQuantity($requisition->from_warehouse_id) < $dispatchQty) {
                    throw new Exception("Insufficient stock for SKU {$variant->sku} at origin warehouse.");
                }

                StockMovement::create([
                    'product_variant_id' => $actualVariantId,
                    'warehouse_id' => $requisition->from_warehouse_id,
                    'type' => 'transit_out',
                    'quantity' => -$dispatchQty,
                    'unit_name_used' => $item->approved_unit_name ?? $item->requested_unit_name,
                    'unit_ratio_used' => $item->approved_unit_ratio ?? $item->requested_unit_ratio,
                    'reference_type' => TransferRequisition::class,
                    'reference_id' => $requisition->id,
                    'reference_code' => $requisition->reference_code,
                    'created_by' => auth()->id(),
                ]);

                InTransit::create([
                    'transfer_requisition_id' => $requisition->id,
                    'transfer_requisition_item_id' => $item->id,
                    'product_variant_id' => $actualVariantId,
                    'dispatched_base_qty' => $dispatchQty,
                    'dispatched_at' => now(),
                    'status' => 'in_transit',
                ]);

                $item->update(['shipped_base_qty' => $dispatchQty]);
            }

            $requisition->update([
                'status' => 'dispatched',
                'dispatched_by' => auth()->id(),
                'dispatched_at' => now(),
            ]);
        });
    }

    public function scanToReceive(int $requisitionId, array $receivedItemsData): void
    {
        DB::transaction(function () use ($requisitionId, $receivedItemsData) {
            $requisition = TransferRequisition::with(['items.productVariant.currentPrice'])->lockForUpdate()->findOrFail($requisitionId);

            if ($requisition->status !== 'dispatched') {
                throw new Exception("Requisition must be in dispatched state. Current: {$requisition->status}");
            }

            $hasLossOrDamage = false;

            foreach ($requisition->items as $item) {
                $actualVariantId = $item->substitute_product_variant_id ?? $item->product_variant_id;
                $expectedBase = $item->shipped_base_qty;

                if (!isset($receivedItemsData[$item->id])) {
                    $goodBase = 0;
                    $damagedBase = 0;
                    $lostBase = $expectedBase;
                    $lossCategory = 'Omitted From Intake / Transit Loss';
                } else {
                    $entry = $receivedItemsData[$item->id];
                    $ratio = $item->approved_unit_ratio ?? $item->requested_unit_ratio;
                    $goodBase = ($entry['good_qty'] ?? 0) * $ratio;
                    $damagedBase = ($entry['damaged_qty'] ?? 0) * $ratio;
                    $lostBase = max(0, $expectedBase - ($goodBase + $damagedBase));
                    $lossCategory = $entry['loss_category'] ?? 'Transit Variance';
                }

                if ($goodBase > 0) {
                    $this->recordMovement(
                        productVariantId: $actualVariantId,
                        warehouseId: $requisition->to_warehouse_id,
                        type: 'transit_in',
                        baseQuantity: $goodBase,
                        unitName: $item->approved_unit_name ?? $item->requested_unit_name,
                        unitRatio: $item->approved_unit_ratio ?? $item->requested_unit_ratio,
                        referenceType: TransferRequisition::class,
                        referenceId: $requisition->id,
                        referenceCode: $requisition->reference_code
                    );
                }

                if ($damagedBase > 0 || $lostBase > 0) {
                    $hasLossOrDamage = true;
                    $variant = ProductVariant::with('currentPrice')->findOrFail($actualVariantId);
                    $unitCost = $variant->currentPrice?->cost_price ?? 0.0000;

                    LossLedger::create([
                        'transfer_requisition_id' => $requisition->id,
                        'transfer_requisition_item_id' => $item->id,
                        'product_variant_id' => $actualVariantId,
                        'warehouse_id' => $requisition->to_warehouse_id,
                        'lost_base_qty' => $lostBase,
                        'damaged_base_qty' => $damagedBase,
                        'unit_cost_price' => $unitCost,
                        'total_financial_loss' => ($lostBase + $damagedBase) * $unitCost,
                        'loss_category' => $lossCategory,
                        'recorded_by' => auth()->id(),
                        'recorded_at' => now(),
                    ]);
                }

                $item->update([
                    'received_good_base_qty' => $goodBase,
                    'received_damaged_base_qty' => $damagedBase,
                    'received_qty' => ($goodBase + $damagedBase),
                ]);

                InTransit::where('transfer_requisition_item_id', $item->id)->update([
                    'status' => 'cleared',
                ]);
            }

            $requisition->update([
                'status' => $hasLossOrDamage ? 'closed_with_loss' : 'completed',
                'received_by' => auth()->id(),
                'completed_at' => now(),
            ]);
        });
    }
}
```

---

## 🎨 Section 6: Clinical "Operations Deck" Design System & Bento Grid

- **Operational Blue Authority (#3b82f6)**: Primary brand accent restricted strictly to **$\le$10% of any single view**, reserved for primary action execution triggers (*Receive*, *Dispatch*, *Confirm*, *Execute Now*).
- **Flat Surface Rest States**: Card wrappers, borders, and table headers remain flat (1px solid zinc-200). Elevation shadows trigger only during active modal focus.
- **Zero-Zebra Density**: Alternating background row striping is removed from all tables, relying on thin bottom dividers (`border-b border-zinc-200`) and instantaneous hover highlights (`hover:bg-zinc-50`).
- **Glassmorphic Bento Grid**: The primary landing dashboard organizes widgets into responsive 4-column asymmetrical bento grids using specular glass styling (`backdrop-filter: blur(24px)`).
- **Widgets Caching (300s TTL)**: Heavy widget sums are wrapped in `Cache::remember('stats_overview_...', 300)` to eliminate memory bottlenecks on massive movement tables.

---

## 📋 Section 7: Master 17-Stage Execution Sequence

1. **Phase 00: Environment & Core Guardrails Setup**: Bootstrap Laravel 13, FilamentPHP v5, Livewire v4, and register composer dependencies (`simplesoftwareio/simple-qrcode`, `pestphp/pest`).
2. **Phase 01: Relational Schema Migrations**: Execute the 13 clean migrations in strict dependency order (`products`, `product_variants`, `product_variant_prices`, `product_unit_conversions`, `warehouses`, `users` role update, `user_warehouse`, `stock_movements`, `transfer_requisitions`, `transfer_requisition_items`, `transfer_requisition_item_revisions`, `in_transits`, `loss_ledgers`).
3. **Phase 02: Base Seeders & Opening Ledger**: Populate warehouses, products, variants, unit conversions, current prices, and seed opening stocks as `receive` entries in `stock_movements`.
4. **Phase 03: Eloquent Model Projections & UserRole Enum**: Implement derived stock methods (`onHandQuantity`, `reservedQuantity`, `availableQuantity`) on `ProductVariant` and create `UserRole` enum.
5. **Phase 04: Transactional Inventory Engine**: Implement `InventoryService` with pessimistic locking, substitute variant matching, and omitted receipt write-offs.
6. **Phase 05: Product Catalog Resources**: Build `ProductResource` (family name/category) and `VariantsRelationManager` (SKU, GTIN, sub-cent pricing, reorder points).
7. **Phase 06: Price History & Packaging Conversions**: Build `PricesRelationManager` (location-scoped price overrides) and `ConversionsRelationManager` mapping packaging multipliers.
8. **Phase 07: Warehouses & Manual Adjustments**: Build `WarehouseResource` and slide-over stock adjuster drawer with 15-character note validation.
9. **Phase 08: Inter-Warehouse Requisition Wizard**: Implement 3-step creation wizard dialog modals (`closeModalByClickingAway(false)`).
10. **Phase 09: Negotiation Loop UI**: Build review actions and revisions form for counter-offers and substitute variant swapping.
11. **Phase 10: Dispatch & In-Transit Monitor**: Connect confirmation and dispatch actions to `InventoryService` and construct `InTransitResource`.
12. **Phase 11: Printable STN & Signed QR Route**: Build PDF manifests rendering 30-day signed scan URLs.
13. **Phase 12: Scan-to-Receive Modal**: Implement QR scan landing controller (`ScanReceiptController`) and auto-triggering intake reconciliation modal.
14. **Phase 13: Read-Only Audit Ledgers**: Build `StockMovementResource` and `LossLedgerResource` with `decimal(15,4)` sum footers.
15. **Phase 14: Glassmorphic Bento Dashboard**: Construct responsive bento dashboard with 300-second cached widgets.
16. **Phase 15: Multi-Language Translation**: Abstract 100% of user-facing UI labels into translation catalogs under `lang/en/`, `lang/es/`, and `lang/tl/`.
17. **Phase 16: Automated CI/CD Testing**: Execute Pest unit suites (SQLite `:memory:`) and Playwright E2E browser suites (PostgreSQL container).

---

## 🧪 Section 8: Automated CI/CD Testing & E2E Validation Strategy

| Test Runner | Environment | Focus Area |
| :--- | :--- | :--- |
| **Laravel Pint** | Local / CI | Code style compliance (`./vendor/bin/pint --test`) |
| **Pest PHP** | SQLite (`:memory:`) | Unit, Feature, Service & Model tests (`./vendor/bin/pest`) |
| **Playwright** | PostgreSQL (Test DB) | Sequential multi-role E2E browser flows (`npx playwright test`) |
