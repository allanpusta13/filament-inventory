# Multi-Warehouse Inventory System — Complete System Blueprint (v8.0)

**Stack:** Laravel 13 + FilamentPHP v5 + Livewire v4 | **Database:** PostgreSQL / MySQL | **Architecture:** Pure Derived Stock of Truth Ledger

> **v8.0 changelog (vs v7.0):** Section 5 now includes the complete, audited `NegotiationService` alongside a corrected `InventoryService` (added `directTransfer()`, fixed `productVariant()` relationship naming, replaced raw status strings with backed enums, fixed the `reference_id` type to support UUID/ULID). Section 4's `ProductVariant` model corrected to match (`ProductVariantUnitConversion`, enum-based status checks, `SoftDeletes`). Section 2's `transfer_requisition_item_revisions` table gained `proposed_unit_ratio`. Phase 01 and Phase 09 of Section 7 updated to match. One open gap flagged at the end of Section 5B: no service-level guard yet prevents accepting/rejecting/countering a revision once its parent requisition has left a negotiable status.

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
| `proposed_unit_ratio` | integer | Packaging multiplier proposed (lets `NegotiationService::accept()` populate the item's `approved_unit_ratio` directly, with no division/rounding) |
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

On-hand physical stock, active requisition allocations, and physical stock availability are calculated dynamically at query-time on the `ProductVariant` model. Status comparisons use the `TransferRequisitionStatus` backed enum, never raw strings, per Principle #10 (Strongly-Typed values throughout).

```php
namespace App\Models;

use App\Enums\TransferRequisitionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id', 'sku', 'barcode', 'name', 'base_unit_name',
        'reorder_point', 'attributes', 'images', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'images' => 'array',
            'reorder_point' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unitConversions(): HasMany
    {
        return $this->hasMany(ProductVariantUnitConversion::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductVariantPrice::class);
    }

    /**
     * The single active price row for this variant. App logic is responsible
     * for keeping exactly one is_current=true row per variant; the DB
     * enforces this with a partial/generated unique index (see Section 2,
     * table 3).
     */
    public function currentPrice(): HasOne
    {
        return $this->hasOne(ProductVariantPrice::class)->where('is_current', true);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function requisitionItems(): HasMany
    {
        return $this->hasMany(TransferRequisitionItem::class);
    }

    public function isBelowReorderPoint(int $currentBaseQty): bool
    {
        return $currentBaseQty <= $this->reorder_point;
    }

    /**
     * Physical on-hand stock: raw SUM of signed stock movements. This is the
     * single source of truth for physical quantity — no stock is ever stored
     * directly on this model or in any dedicated stock table.
     */
    public function onHandQuantity(int $warehouseId): int
    {
        return (int) StockMovement::where('product_variant_id', $this->id)
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');
    }

    /**
     * Active requisition reservations: SUM of approved_base_qty across items
     * belonging to requisitions in 'confirmed' or 'dispatched' status,
     * fulfilled from the given warehouse. Deliberately does NOT reserve
     * during earlier negotiation stages (draft/requested/under_review_*) —
     * approved_base_qty is null until a NegotiationService::accept() call
     * resolves it, so those rows contribute 0 to the sum.
     */
    public function reservedQuantity(int $warehouseId): int
    {
        return (int) TransferRequisitionItem::where('product_variant_id', $this->id)
            ->whereHas('transferRequisition', function ($query) use ($warehouseId) {
                $query->where('from_warehouse_id', $warehouseId)
                    ->whereIn('status', [
                        TransferRequisitionStatus::Confirmed,
                        TransferRequisitionStatus::Dispatched,
                    ]);
            })
            ->sum('approved_base_qty');
    }

    /**
     * Available physical stock: on-hand minus active reservations.
     */
    public function availableQuantity(int $warehouseId): int
    {
        return $this->onHandQuantity($warehouseId) - $this->reservedQuantity($warehouseId);
    }
}
```

---

## ⚙️ Section 5: Transactional Service Layer

Two services own every write to the stock ledger and the negotiation state machine, respectively. `InventoryService` never reads or writes `approved_*` fields except by consuming them — those fields are populated exclusively by `NegotiationService` (via an accepted revision), never inferred or derived on the fly during dispatch.

### A. `InventoryService`

```php
namespace App\Services;

use App\Enums\InTransitStatus;
use App\Enums\StockMovementType;
use App\Enums\TransferRequisitionStatus;
use App\Models\InTransit;
use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\TransferRequisition;
use App\Models\Warehouse;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Transactional stock engine. Physical stock is never stored directly — it is
 * always the signed SUM of stock_movements rows (see ProductVariant::onHandQuantity()).
 * Every write path here runs inside DB::transaction() with lockForUpdate() on
 * the ProductVariant and/or TransferRequisition/Warehouse rows involved, per
 * Principle #3 (Pessimistic Locking & Transaction Isolation).
 */
class InventoryService
{
    /**
     * Record a single signed stock movement for a variant at a warehouse,
     * after verifying it won't drive on-hand stock negative.
     */
    public function recordMovement(
        int $productVariantId,
        int $warehouseId,
        StockMovementType $type,
        int $baseQuantity,
        ?string $unitName = null,
        int $unitRatio = 1,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $referenceCode = null,
        ?int $relatedMovementId = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $productVariantId, $warehouseId, $type, $baseQuantity,
            $unitName, $unitRatio, $referenceType, $referenceId,
            $referenceCode, $relatedMovementId,
        ) {
            $variant = ProductVariant::lockForUpdate()->findOrFail($productVariantId);
            $currentStock = $variant->onHandQuantity($warehouseId);

            if ($baseQuantity < 0 && ($currentStock + $baseQuantity) < 0) {
                throw new Exception(
                    "Insufficient stock for SKU {$variant->sku} at warehouse ID {$warehouseId}. ".
                    "Available: {$currentStock}, requested deduction: ".abs($baseQuantity).'.'
                );
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

    /**
     * Instant two-leg transfer between warehouses, bypassing the requisition
     * workflow entirely (backs DirectTransferResource). Creates a
     * transfer_out at the origin and a transfer_in at the destination,
     * linked via related_movement_id in both directions, inside one
     * transaction — unlike dispatchTransfer(), there is no in-transit
     * period: stock leaves one warehouse and lands in the other atomically,
     * in the same commit.
     */
    public function directTransfer(
        int $productVariantId,
        int $fromWarehouseId,
        int $toWarehouseId,
        int $baseQuantity,
        ?string $unitName = null,
        int $unitRatio = 1,
        ?string $referenceCode = null,
    ): array {
        if ($fromWarehouseId === $toWarehouseId) {
            throw new Exception('Direct transfer origin and destination warehouses must differ.');
        }

        if ($baseQuantity <= 0) {
            throw new Exception('Direct transfer quantity must be a positive number of base units.');
        }

        return DB::transaction(function () use (
            $productVariantId, $fromWarehouseId, $toWarehouseId,
            $baseQuantity, $unitName, $unitRatio, $referenceCode,
        ) {
            // Lock both warehouse rows in a deterministic order (by ID) to avoid
            // deadlocking against a concurrent reverse-direction direct transfer
            // between the same two warehouses.
            $warehouseIds = collect([$fromWarehouseId, $toWarehouseId])->sort()->values();
            Warehouse::whereIn('id', $warehouseIds)->lockForUpdate()->get();

            $variant = ProductVariant::lockForUpdate()->findOrFail($productVariantId);
            $currentStock = $variant->onHandQuantity($fromWarehouseId);

            if ($currentStock < $baseQuantity) {
                throw new Exception(
                    "Insufficient stock for SKU {$variant->sku} at origin warehouse ID {$fromWarehouseId}. ".
                    "Available: {$currentStock}, requested: {$baseQuantity}."
                );
            }

            $resolvedUnitName = $unitName ?? $variant->base_unit_name;

            $outMovement = StockMovement::create([
                'product_variant_id' => $productVariantId,
                'warehouse_id' => $fromWarehouseId,
                'type' => StockMovementType::TransferOut,
                'quantity' => -$baseQuantity,
                'unit_name_used' => $resolvedUnitName,
                'unit_ratio_used' => $unitRatio,
                'reference_code' => $referenceCode,
                'created_by' => auth()->id(),
            ]);

            $inMovement = StockMovement::create([
                'product_variant_id' => $productVariantId,
                'warehouse_id' => $toWarehouseId,
                'type' => StockMovementType::TransferIn,
                'quantity' => $baseQuantity,
                'unit_name_used' => $resolvedUnitName,
                'unit_ratio_used' => $unitRatio,
                'related_movement_id' => $outMovement->id,
                'reference_code' => $referenceCode,
                'created_by' => auth()->id(),
            ]);

            $outMovement->update(['related_movement_id' => $inMovement->id]);

            return [$outMovement->fresh(), $inMovement];
        });
    }

    /**
     * Dispatch a confirmed requisition: for each item, resolve the actual
     * variant (substitute if negotiated), verify stock, debit the origin
     * warehouse with a transit_out movement, and open an in_transits row.
     *
     * approved_base_qty is populated exclusively by an accepted negotiation
     * revision (see NegotiationService::accept() below). If no negotiation
     * ever occurred on an item, this falls back to requested_base_qty — an
     * item that was never negotiated ships exactly what was requested.
     */
    public function dispatchTransfer(int $requisitionId): void
    {
        DB::transaction(function () use ($requisitionId) {
            $requisition = TransferRequisition::with('items.productVariant')
                ->lockForUpdate()
                ->findOrFail($requisitionId);

            if ($requisition->status !== TransferRequisitionStatus::Confirmed) {
                throw new Exception(
                    "Requisition must be confirmed before dispatch. Current status: {$requisition->status->value}."
                );
            }

            foreach ($requisition->items as $item) {
                $actualVariantId = $item->substitute_product_variant_id ?? $item->product_variant_id;
                $dispatchQty = $item->approved_base_qty ?? $item->requested_base_qty;

                $variant = ProductVariant::lockForUpdate()->findOrFail($actualVariantId);

                if ($variant->onHandQuantity($requisition->from_warehouse_id) < $dispatchQty) {
                    throw new Exception(
                        "Insufficient stock for SKU {$variant->sku} at origin warehouse for ".
                        "requisition {$requisition->reference_code}."
                    );
                }

                StockMovement::create([
                    'product_variant_id' => $actualVariantId,
                    'warehouse_id' => $requisition->from_warehouse_id,
                    'type' => StockMovementType::TransitOut,
                    'quantity' => -$dispatchQty,
                    'unit_name_used' => $item->approved_unit_name ?? $item->requested_unit_name,
                    'unit_ratio_used' => $item->approved_unit_ratio ?? $item->requested_unit_ratio,
                    'reference_type' => TransferRequisition::class,
                    'reference_id' => (string) $requisition->id,
                    'reference_code' => $requisition->reference_code,
                    'created_by' => auth()->id(),
                ]);

                InTransit::create([
                    'transfer_requisition_id' => $requisition->id,
                    'transfer_requisition_item_id' => $item->id,
                    'product_variant_id' => $actualVariantId,
                    'dispatched_base_qty' => $dispatchQty,
                    'dispatched_at' => now(),
                    'status' => InTransitStatus::InTransit,
                ]);

                $item->update(['shipped_base_qty' => $dispatchQty]);
            }

            $requisition->update([
                'status' => TransferRequisitionStatus::Dispatched,
                'dispatched_by' => auth()->id(),
                'dispatched_at' => now(),
            ]);
        });
    }

    /**
     * Reconcile a scan-to-receive intake against what was dispatched. Any
     * dispatched item absent from $receivedItemsData is treated as a total
     * loss (0 received) rather than silently skipped — this is Principle #7
     * (Scanned Receipt Loss Integrity & Omitted Cargo): omitted cargo is not
     * forgiven, it is written off at the variant's current cost price.
     *
     * $receivedItemsData is keyed by transfer_requisition_item_id, each value
     * shaped as ['good_qty' => int, 'damaged_qty' => int, 'loss_category' => ?string]
     * where good_qty/damaged_qty are in the item's approved (or requested)
     * packaging unit — NOT base units; this method converts using the ratio
     * that was actually shipped.
     */
    public function scanToReceive(int $requisitionId, array $receivedItemsData): void
    {
        DB::transaction(function () use ($requisitionId, $receivedItemsData) {
            $requisition = TransferRequisition::with('items.productVariant')
                ->lockForUpdate()
                ->findOrFail($requisitionId);

            if ($requisition->status !== TransferRequisitionStatus::Dispatched) {
                throw new Exception(
                    "Requisition must be in dispatched state to receive. Current status: {$requisition->status->value}."
                );
            }

            $hasLossOrDamage = false;

            foreach ($requisition->items as $item) {
                $actualVariantId = $item->substitute_product_variant_id ?? $item->product_variant_id;
                $expectedBase = $item->shipped_base_qty;
                $ratio = $item->approved_unit_ratio ?? $item->requested_unit_ratio;

                if (! isset($receivedItemsData[$item->id])) {
                    $goodBase = 0;
                    $damagedBase = 0;
                    $lostBase = $expectedBase;
                    $lossCategory = 'omitted_from_intake';
                } else {
                    $entry = $receivedItemsData[$item->id];
                    $goodBase = ($entry['good_qty'] ?? 0) * $ratio;
                    $damagedBase = ($entry['damaged_qty'] ?? 0) * $ratio;
                    $lostBase = max(0, $expectedBase - ($goodBase + $damagedBase));
                    $lossCategory = $entry['loss_category'] ?? 'shortfall';
                }

                if ($goodBase > 0) {
                    StockMovement::create([
                        'product_variant_id' => $actualVariantId,
                        'warehouse_id' => $requisition->to_warehouse_id,
                        'type' => StockMovementType::TransitIn,
                        'quantity' => $goodBase,
                        'unit_name_used' => $item->approved_unit_name ?? $item->requested_unit_name,
                        'unit_ratio_used' => $ratio,
                        'reference_type' => TransferRequisition::class,
                        'reference_id' => (string) $requisition->id,
                        'reference_code' => $requisition->reference_code,
                        'created_by' => auth()->id(),
                    ]);
                }

                if ($damagedBase > 0 || $lostBase > 0) {
                    $hasLossOrDamage = true;

                    // Fetched by $actualVariantId (not eager-loaded above), since
                    // a substitute variant's price is never preloaded off the
                    // original item's relation.
                    $variant = ProductVariant::with('currentPrice')->findOrFail($actualVariantId);
                    $unitCost = LossLedger::snapshotUnitCostFrom($variant) ?? '0.0000';

                    LossLedger::create([
                        'transfer_requisition_id' => $requisition->id,
                        'transfer_requisition_item_id' => $item->id,
                        'product_variant_id' => $actualVariantId,
                        'warehouse_id' => $requisition->to_warehouse_id,
                        'lost_base_qty' => $lostBase,
                        'damaged_base_qty' => $damagedBase,
                        'unit_cost_price' => $unitCost,
                        'total_financial_loss' => ($lostBase + $damagedBase) * (float) $unitCost,
                        'loss_category' => $lossCategory,
                        'recorded_by' => auth()->id(),
                        'recorded_at' => now(),
                    ]);
                }

                $item->update([
                    'received_good_base_qty' => $goodBase,
                    'received_damaged_base_qty' => $damagedBase,
                    'received_qty' => $goodBase + $damagedBase,
                ]);

                InTransit::where('transfer_requisition_item_id', $item->id)
                    ->update(['status' => InTransitStatus::Cleared]);
            }

            $requisition->update([
                'status' => $hasLossOrDamage
                    ? TransferRequisitionStatus::ClosedWithLoss
                    : TransferRequisitionStatus::Completed,
                'received_by' => auth()->id(),
                'completed_at' => now(),
            ]);
        });
    }
}
```

### B. `NegotiationService`

Owns the counter-offer lifecycle for `transfer_requisition_item_revisions`. This is the **only** path by which a `TransferRequisitionItem`'s `approved_unit_name` / `approved_unit_ratio` / `approved_qty` / `approved_base_qty` / `substitute_product_variant_id` fields get populated — `InventoryService::dispatchTransfer()` reads those fields on the assumption they reflect an accepted revision, never writes to them directly.

```php
namespace App\Services;

use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use App\Models\TransferRequisitionItem;
use App\Models\TransferRequisitionItemRevision;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class NegotiationService
{
    /**
     * Open a new negotiation thread on an item, or start a counter-thread if
     * $respondsTo is given.
     */
    public function propose(
        TransferRequisitionItem $item,
        User $user,
        NegotiationSide $side,
        string $unitName,
        int $unitRatio,
        int $qty,
        ?int $substituteProductVariantId = null,
        ?string $reason = null,
        ?TransferRequisitionItemRevision $respondsTo = null,
    ): TransferRequisitionItemRevision {
        $attributes = [
            'user_id' => $user->id,
            'product_variant_id' => $item->product_variant_id,
            'substitute_product_variant_id' => $substituteProductVariantId,
            'proposed_unit_name' => $unitName,
            'proposed_unit_ratio' => $unitRatio,
            'proposed_qty' => $qty,
            'proposed_base_qty' => $qty * $unitRatio,
            'negotiation_reason' => $reason,
            'side' => $side,
        ];

        if ($respondsTo !== null) {
            return $respondsTo->counterWith($attributes);
        }

        return DB::transaction(function () use ($item, $attributes) {
            return TransferRequisitionItemRevision::create(array_merge($attributes, [
                'transfer_requisition_item_id' => $item->id,
                'status' => RevisionStatus::Pending,
            ]));
        });
    }

    /**
     * Accept a revision, syncing its proposed values onto the parent item.
     * Delegates to TransferRequisitionItemRevision::accept(), which wraps
     * this in its own transaction with a row lock on the item.
     */
    public function accept(TransferRequisitionItemRevision $revision): void
    {
        if ($revision->status->isResolved()) {
            throw new Exception("Revision {$revision->id} is already resolved ({$revision->status->value}) and cannot be accepted again.");
        }

        $revision->accept();
    }

    public function reject(TransferRequisitionItemRevision $revision): void
    {
        if ($revision->status->isResolved()) {
            throw new Exception("Revision {$revision->id} is already resolved ({$revision->status->value}) and cannot be rejected.");
        }

        $revision->reject();
    }

    /**
     * Counter a pending revision with a new proposal from the opposite side.
     */
    public function counter(
        TransferRequisitionItemRevision $revision,
        User $user,
        string $unitName,
        int $unitRatio,
        int $qty,
        ?int $substituteProductVariantId = null,
        ?string $reason = null,
    ): TransferRequisitionItemRevision {
        if ($revision->status->isResolved()) {
            throw new Exception("Revision {$revision->id} is already resolved ({$revision->status->value}) and cannot be countered.");
        }

        return $revision->counterWith([
            'user_id' => $user->id,
            'product_variant_id' => $revision->product_variant_id,
            'substitute_product_variant_id' => $substituteProductVariantId,
            'proposed_unit_name' => $unitName,
            'proposed_unit_ratio' => $unitRatio,
            'proposed_qty' => $qty,
            'proposed_base_qty' => $qty * $unitRatio,
            'negotiation_reason' => $reason,
            'side' => $revision->side->opposite(),
        ]);
    }
}
```

> **Known open gap (not yet resolved):** neither `accept()`, `reject()`, nor `counter()` currently checks whether the parent requisition itself is still in a negotiable status (e.g. `under_review_fulfiller` / `under_review_requestor`). A revision could theoretically be accepted after the requisition has already moved to `confirmed` or beyond. Guarding this — either here or via a DB constraint — is still undecided.

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
2. **Phase 01: Relational Schema Migrations**: Execute the 13 clean migrations in strict dependency order (`products`, `product_variants`, `product_variant_prices`, `product_variant_unit_conversions`, `warehouses`, `users` role update, `user_warehouse`, `stock_movements`, `transfer_requisitions`, `transfer_requisition_items`, `transfer_requisition_item_revisions`, `in_transits`, `loss_ledgers`).
3. **Phase 02: Base Seeders & Opening Ledger**: Populate warehouses, products, variants, unit conversions, current prices, and seed opening stocks as `receive` entries in `stock_movements`.
4. **Phase 03: Eloquent Model Projections & UserRole Enum**: Implement derived stock methods (`onHandQuantity`, `reservedQuantity`, `availableQuantity`) on `ProductVariant` and create `UserRole` enum.
5. **Phase 04: Transactional Inventory Engine**: Implement `InventoryService` with pessimistic locking, substitute variant matching, and omitted receipt write-offs.
6. **Phase 05: Product Catalog Resources**: Build `ProductResource` (family name/category) and `VariantsRelationManager` (SKU, GTIN, sub-cent pricing, reorder points).
7. **Phase 06: Price History & Packaging Conversions**: Build `PricesRelationManager` (location-scoped price overrides) and `ConversionsRelationManager` mapping packaging multipliers.
8. **Phase 07: Warehouses & Manual Adjustments**: Build `WarehouseResource` and slide-over stock adjuster drawer with 15-character note validation.
9. **Phase 08: Inter-Warehouse Requisition Wizard**: Implement 3-step creation wizard dialog modals (3xl width, `closeModalByClickingAway(false)`).
10. **Phase 09: Negotiation Loop UI**: Build review actions and revisions form for counter-offers and substitute variant swapping, wired to `NegotiationService::propose()` / `accept()` / `reject()` / `counter()`.
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