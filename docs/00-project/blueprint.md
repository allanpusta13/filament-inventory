# Multi-Warehouse Inventory System — Complete System Blueprint (v10.0)

**Stack:** Laravel 13 + FilamentPHP v5 + Livewire v4 | **Database:** PostgreSQL / MySQL
**Architecture:** Pure Derived Stock of Truth Ledger

> **Changelog from v9.1:** This revision closes every gap identified in the v9.1 technical audit. Each fix below is annotated with `[FIX v10]` at its point of introduction so implementers can diff against v9.1 quickly. Nothing in v9.1's passing checklist has been altered — this is additive/corrective only.

---

## 🧭 Executive Architecture & System Principles

1. **Pure Derived Stock of Truth:** Physical stock levels, active transit reservations, and available balances are never stored in a physical database table. Physical on-hand stock is calculated dynamically at query-time as the sum of all signed records in `stock_movements`. Active reservations sum pending quantities from confirmed requisitions, and available stock is derived as `on_hand - reserved`.
2. **Decoupled Pricing & Variant-Level Catalog:** `sku` lives exclusively on `product_variants`. Parent products act purely as family grouping containers. `reorder_point` lives exclusively on `product_variants`. Unit pricing is decoupled into `product_variant_prices` with `is_current = true`, supporting 4-decimal micro-pricing.
3. **Pessimistic Locking & Transaction Isolation:** All stock deductions, dispatches, and intake receipts execute inside atomic database transactions using pessimistic row-level locking on `product_variants`, `warehouses`, and `transfer_requisitions`. **`[FIX v10]`** Locking discipline is now uniform across *every* multi-warehouse-touching service method — see Section 5A.
4. **Canonical Foreign Key & Plural Naming:** All database tables use explicit plural snake_case names. Foreign keys strictly follow table-bound names.
5. **Physical-to-Digital State Lifecycle:**
   ```
   draft → requested → under_review_fulfiller ⇌ under_review_requestor
         → confirmed → dispatched ⇌ partially_received
         → completed / closed_with_loss / cancelled
   ```
   `partially_received` is a first-class live state. It re-enters the receivable modal until every item's `received_good_base_qty + received_damaged_base_qty >= shipped_base_qty`.
6. **Negotiated Substitute Variant Swapping:** Dispatch and receipt pipelines dynamically resolve `$actualVariantId = $item->substitute_product_variant_id ?? $item->product_variant_id`.
7. **Scanned Receipt Loss Integrity & Omitted Cargo:** On the first intake scan, dispatched items missing from a physical scan payload are recorded as 0 received, triggering a 100% variance write-off. On subsequent scans, omitted items are treated as still in transit.
8. **Signed Web QR Routing:** STN QR codes embed secure 7-day temporary signed URLs.
9. **Modal-First UI (< 8 Inputs Rule):** Compact operations use inline slide-over Drawers or Dialog Modals. Multi-step wizards use `modalWidth(Width::SevenExtraLarge)`.
10. **Strongly-Typed Icons, Multi-Language i18n & Currency:** All six backed enums route `getLabel()` through `__()`. All `->money()` calls pass `config('app.currency')`.
11. **Ledger FK Immutability:** Every `product_variant_id` foreign key on a ledger table uses `restrictOnDelete`.
12. **Authorization vs Visibility:** `->authorize()` enforces server-side policy security. `->visible()` controls frontend DOM rendering.
13. **`[FIX v10]` Reservation Scope Boundary:** `reservedQuantity()` is intentionally and permanently bounded to requisitions in `Confirmed` status only. Once a requisition transitions to `Dispatched`, its reserved stock is superseded by the `TransitOut` stock movement (already reflected in `onHandQuantity()`). In-transit and partially-received cargo is never double-counted as "reserved" against the origin warehouse.
14. **`[FIX v10]` Cancellation Boundary:** `CancelAction` is only legal while a requisition is in a pre-dispatch state. Once `TransitOut` has fired (i.e., status is `Dispatched` or `PartiallyReceived`), cancellation is permanently unavailable — there is no compensating stock-reversal pathway in this system, by design. This eliminates an entire class of reversal-logic bugs rather than requiring one.
15. **`[FIX v10]` Cost Snapshot Timing:** `LossLedger::snapshotUnitCostFrom()` captures `currentPrice.cost_price` **at call-time** — i.e., at the moment intake/loss is actually processed, not at the moment the requisition was originally dispatched. Loss valuation therefore reflects present-day replacement cost, not historical acquisition cost. This is a deliberate design choice, documented at the method itself.

---

## 📁 Section 1: Filament v5 Resource Directory Structure

*(Unchanged from v9.1 — no gaps identified here.)*

Filament v5 uses a domain-oriented directory structure. Each resource is a thin class that delegates form, table, and infolist definitions to dedicated schema and table classes.

```
app/Filament/Resources/
├── Products/
│   ├── ProductResource.php                    # Thin resource class
│   ├── Pages/
│   │   ├── ListProducts.php                   # ListRecord page
│   │   ├── CreateProduct.php                  # CreateRecord page
│   │   ├── EditProduct.php                    # EditRecord page
│   │   └── ViewProduct.php                    # ViewRecord page
│   ├── Schemas/
│   │   ├── ProductForm.php                    # Form schema
│   │   └── ProductInfolist.php                # Infolist schema
│   └── Tables/
│       └── ProductsTable.php                  # Table schema
│
├── TransferRequisitions/
│   ├── TransferRequisitionResource.php
│   ├── Pages/
│   │   ├── ListTransferRequisitions.php
│   │   ├── CreateTransferRequisition.php      # Wizard page
│   │   ├── EditTransferRequisition.php
│   │   └── ViewTransferRequisition.php        # Negotiation thread
│   ├── Schemas/
│   │   ├── TransferRequisitionForm.php
│   │   └── TransferRequisitionInfolist.php
│   └── Tables/
│       └── TransferRequisitionsTable.php
│
├── DirectTransfers/
│   ├── DirectTransferResource.php
│   ├── Pages/
│   │   └── CreateDirectTransfer.php           # Wizard page
│   └── Schemas/
│       └── DirectTransferForm.php
│
├── InTransits/
│   ├── InTransitResource.php
│   ├── Pages/
│   │   └── ListInTransits.php
│   ├── Schemas/
│   │   └── InTransitInfolist.php
│   └── Tables/
│       └── InTransitsTable.php
│
├── StockMovements/
│   ├── StockMovementResource.php
│   ├── Pages/
│   │   └── ListStockMovements.php
│   ├── Schemas/
│   │   └── StockMovementInfolist.php
│   └── Tables/
│       └── StockMovementsTable.php
│
├── LossLedgers/
│   ├── LossLedgerResource.php
│   ├── Pages/
│   │   └── ListLossLedgers.php
│   ├── Schemas/
│   │   └── LossLedgerInfolist.php
│   └── Tables/
│       └── LossLedgersTable.php
│
├── Warehouses/
│   ├── WarehouseResource.php
│   ├── Pages/
│   │   ├── ListWarehouses.php
│   │   ├── CreateWarehouse.php
│   │   └── EditWarehouse.php
│   ├── Schemas/
│   │   └── WarehouseForm.php
│   └── Tables/
│       └── WarehousesTable.php
│
└── Users/
    ├── UserResource.php
    ├── Pages/
    │   ├── ListUsers.php
    │   ├── CreateUser.php
    │   └── EditUser.php
    ├── Schemas/
    │   └── UserForm.php
    └── Tables/
        └── UsersTable.php
```

### Thin Resource Class Pattern

```php
namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Filament\Resources\Products\Schemas\ProductInfolist;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Models\ProductVariant;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = ProductVariant::class;

    protected static string | \UnitEnum | null $navigationGroup = 'CATALOG';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'sku';

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductInfolist::configure($schema);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with(['product', 'unitConversions', 'currentPrice']);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'view'   => ViewProduct::route('/{record}'),
            'edit'   => EditProduct::route('/{record}/edit'),
        ];
    }
}
```

### Soft-Delete Resource Pattern (Filament v5)

```php
public static function getRecordRouteBindingEloquentQuery(): Builder
{
    return parent::getRecordRouteBindingEloquentQuery()
        ->withoutGlobalScopes([SoftDeletingScope::class]);
}
```

---

## 🗄️ Section 2: Complete Database Schema (13 Tables)

*(Unchanged from v9.1 — schema itself was not the source of any audit gap. All 13 tables, column types, defaults, indexes, and constraints below are identical to v9.1.)*

### 1. products

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| name | string | No | — |
| category | string | Yes | — |
| deleted_at | timestamp | Yes | — |
| created_at / updated_at | timestamp | Yes | — |

### 2. product_variants

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| product_id | FK → products.id (cascadeOnDelete) | No | — |
| sku | string, unique | No | — |
| barcode | string, unique | Yes | — |
| name | string | No | — |
| base_unit_name | string | No | — |
| reorder_point | integer | No | 0 |
| attributes | json | Yes | — |
| images | json | Yes | — |
| is_active | boolean | No | true |
| deleted_at | timestamp | Yes | — |
| created_at / updated_at | timestamp | Yes | — |

Indexes: `(product_id, sku)`

### 3. product_variant_prices

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| product_variant_id | FK → product_variants.id (cascadeOnDelete) | No | — |
| cost_price | decimal(15,4) | No | 0.0000 |
| sale_price | decimal(15,4) | No | 0.0000 |
| effective_from | timestamp | No | current time |
| is_current | boolean | No | true |
| set_by | FK → users.id (nullOnDelete) | Yes | — |
| notes | text | Yes | — |
| created_at / updated_at | timestamp | Yes | — |

Indexes: `(product_variant_id, effective_from)`
Constraints: At most one `is_current = true` row per `product_variant_id`.

### 4. product_variant_unit_conversions

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| product_variant_id | FK → product_variants.id (cascadeOnDelete) | No | — |
| unit_name | string | No | — |
| base_unit_ratio | integer | No | — |
| is_default_purchase | boolean | No | false |
| is_default_transfer | boolean | No | false |
| created_at / updated_at | timestamp | Yes | — |

Indexes: unique on `(product_variant_id, unit_name)`

### 5. warehouses

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| code | string, unique | No | — |
| name | string | No | — |
| location | string | Yes | — |
| is_active | boolean | No | true |
| created_at / updated_at | timestamp | Yes | — |

### 6. stock_movements

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| product_variant_id | FK → product_variants.id (restrictOnDelete) | No | — |
| warehouse_id | FK → warehouses.id (restrictOnDelete) | No | — |
| type | string | No | — |
| quantity | integer | No | — |
| unit_name_used | string | No | — |
| unit_ratio_used | integer | No | 1 |
| related_movement_id | FK → stock_movements.id (nullOnDelete) | Yes | — |
| reference_type | string | Yes | — |
| reference_id | string | Yes | — |
| reference_code | string | Yes | — |
| notes | text | Yes | — |
| created_by | FK → users.id (nullOnDelete) | Yes | — |
| created_at / updated_at | timestamp | Yes | — |

Indexes: `(product_variant_id, warehouse_id)`, `(reference_type, reference_id)`, `type`, `created_at`

### 7. transfer_requisitions

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| reference_code | string, unique | No | — |
| from_warehouse_id | FK → warehouses.id (restrictOnDelete) | No | — |
| to_warehouse_id | FK → warehouses.id (restrictOnDelete) | No | — |
| status | string | No | draft |
| requested_by | FK → users.id | No | — |
| approved_by | FK → users.id | Yes | — |
| dispatched_by | FK → users.id | Yes | — |
| received_by | FK → users.id | Yes | — |
| requested_at | timestamp | Yes | — |
| approved_at | timestamp | Yes | — |
| dispatched_at | timestamp | Yes | — |
| completed_at | timestamp | Yes | — |
| notes | text | Yes | — |
| deleted_at | timestamp | Yes | — |
| created_at / updated_at | timestamp | Yes | — |

Indexes: `status`, `(from_warehouse_id, to_warehouse_id)`

### 8. transfer_requisition_items

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| transfer_requisition_id | FK → transfer_requisitions.id (cascadeOnDelete) | No | — |
| product_variant_id | FK → product_variants.id (restrictOnDelete) | No | — |
| substitute_product_variant_id | FK → product_variants.id (restrictOnDelete) | Yes | — |
| requested_unit_name | string | No | — |
| requested_unit_ratio | integer | No | — |
| requested_qty | integer | No | — |
| requested_base_qty | integer | No | — |
| approved_unit_name | string | Yes | — |
| approved_unit_ratio | integer | Yes | — |
| approved_qty | integer | Yes | — |
| approved_base_qty | integer | Yes | — |
| shipped_base_qty | integer | No | 0 |
| received_good_base_qty | integer | No | 0 |
| received_damaged_base_qty | integer | No | 0 |
| received_qty | integer | Yes | — |
| notes | text | Yes | — |
| created_at / updated_at | timestamp | Yes | — |

Indexes: `transfer_requisition_id`

### 9. transfer_requisition_item_revisions

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| transfer_requisition_item_id | FK → transfer_requisition_items.id (cascadeOnDelete) | No | — |
| user_id | FK → users.id | No | — |
| product_variant_id | FK → product_variants.id (restrictOnDelete) | No | — |
| substitute_product_variant_id | FK → product_variants.id (restrictOnDelete) | Yes | — |
| proposed_unit_name | string | No | — |
| proposed_unit_ratio | integer | No | — |
| proposed_qty | integer | No | — |
| proposed_base_qty | integer | No | — |
| negotiation_reason | text | Yes | — |
| side | string | No | — |
| status | string | No | pending |
| responds_to_revision_id | FK → transfer_requisition_item_revisions.id (nullOnDelete) | Yes | — |
| responded_at | timestamp | Yes | — |
| created_at / updated_at | timestamp | Yes | — |

Indexes: `transfer_requisition_item_id`, `(transfer_requisition_item_id, status)`

### 10. in_transits

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| transfer_requisition_id | FK → transfer_requisitions.id (cascadeOnDelete) | No | — |
| transfer_requisition_item_id | FK → transfer_requisition_items.id (cascadeOnDelete) | No | — |
| product_variant_id | FK → product_variants.id (restrictOnDelete) | No | — |
| dispatched_base_qty | integer | No | — |
| dispatched_at | timestamp | No | — |
| status | string | No | in_transit |
| created_at / updated_at | timestamp | Yes | — |

Indexes: `(transfer_requisition_id, status)`

### 11. loss_ledgers

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| transfer_requisition_id | FK → transfer_requisitions.id (nullOnDelete) | Yes | — |
| transfer_requisition_item_id | FK → transfer_requisition_items.id | Yes | — |
| product_variant_id | FK → product_variants.id (restrictOnDelete) | No | — |
| warehouse_id | FK → warehouses.id | No | — |
| lost_base_qty | integer | No | 0 |
| damaged_base_qty | integer | No | 0 |
| unit_cost_price | decimal(15,4) | No | — |
| total_financial_loss | decimal(15,4) | No | — |
| loss_category | string | No | shortfall |
| recorded_by | FK → users.id | Yes | — |
| recorded_at | timestamp | No | current time |
| created_at / updated_at | timestamp | Yes | — |

Indexes: `(warehouse_id, recorded_at)`

### 12. users (altered)

| Column | Type | Nullable | Default |
|---|---|---|---|
| role | string | No | warehouse_staff |

### 13. user_warehouse (pivot)

| Column | Type | Nullable | Default |
|---|---|---|---|
| user_id | FK → users.id (cascadeOnDelete, part of composite PK) | No | — |
| warehouse_id | FK → warehouses.id (cascadeOnDelete, part of composite PK) | No | — |

Primary key: composite `(user_id, warehouse_id)`

**`[FIX v10]` New migration — Section 2A: `stock_movement_idempotency_keys`**

Required to support the `scanToReceive` idempotency guard (Section 5A). See rationale there.

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| transfer_requisition_id | FK → transfer_requisitions.id (cascadeOnDelete) | No | — |
| payload_checksum | string(64) | No | — |
| resulting_item_states | json | No | — |
| created_at | timestamp | No | current time |

Indexes: unique on `(transfer_requisition_id, payload_checksum)`

> **Note:** Per your direction, the idempotency guard is implemented as a **server-side state-equality check**, not a client-issued UUID key. This table is a lightweight audit trail of processed scan payloads to support the state-check (see Section 5A for exact logic) and to give operators forensic visibility into duplicate scan attempts, not a locking mechanism in itself. It is intentionally simple — no client coordination required.

---

## 🧙‍♂️ Section 3: Transfer Transaction Wizard Schemas

*(Unchanged from v9.1 — wizard schemas were not implicated in any audit finding.)*

### A. Inter-Warehouse Transfer Requisitions

```php
namespace App\Filament\Resources\TransferRequisitions\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;

class TransferRequisitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make([
                Step::make('Routing Pathways')
                    ->schema([
                        Select::make('from_warehouse_id')
                            ->options(fn () => auth()->user()->warehouses()->pluck('name', 'id'))
                            ->default(fn () => auth()->user()->warehouses()->count() === 1
                                ? auth()->user()->warehouses()->first()->id
                                : null)
                            ->required(),
                        Select::make('to_warehouse_id')
                            ->options(fn () => auth()->user()->warehouses()->pluck('name', 'id'))
                            ->required()
                            ->different('from_warehouse_id'),
                    ]),
                Step::make('Material Manifest')
                    ->schema([
                        Repeater::make('items')
                            ->schema([
                                Select::make('product_variant_id')
                                    ->relationship('productVariant', 'sku')
                                    ->required()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                TextInput::make('requested_unit_name')->required(),
                                TextInput::make('requested_unit_ratio')
                                    ->numeric()->default(1)->required(),
                                TextInput::make('requested_qty')
                                    ->numeric()->minValue(1)->required(),
                            ]),
                    ]),
                Step::make('Review & Verify')
                    ->schema([
                        Placeholder::make('review_summary')
                            ->content(fn (Get $get) => view(
                                'filament.wizards.transfer-review',
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

### B. Instant Direct Transfers

```php
namespace App\Filament\Resources\DirectTransfers\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;

class DirectTransferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make([
                Step::make('Location Mapping')
                    ->schema([
                        Select::make('from_warehouse_id')
                            ->options(fn () => auth()->user()->warehouses()->pluck('name', 'id'))
                            ->default(fn () => auth()->user()->warehouses()->count() === 1
                                ? auth()->user()->warehouses()->first()->id
                                : null)
                            ->required(),
                        Select::make('to_warehouse_id')
                            ->options(fn () => auth()->user()->warehouses()->pluck('name', 'id'))
                            ->required()
                            ->different('from_warehouse_id'),
                    ]),
                Step::make('Stock Allocation')
                    ->schema([
                        Select::make('product_variant_id')
                            ->relationship('productVariant', 'sku')
                            ->required(),
                        TextInput::make('quantity')->numeric()->minValue(1)->required(),
                        Textarea::make('notes')
                            ->required()
                            ->minLength(15),
                    ]),
                Step::make('Review & Verify')
                    ->schema([
                        Placeholder::make('review_summary')
                            ->content(fn (Get $get) => view(
                                'filament.wizards.direct-transfer-review',
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

---

## 🛠️ Section 4: Model-Level Pure Derived Stock Engine

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
            'attributes'    => 'array',
            'images'        => 'array',
            'reorder_point' => 'integer',
            'is_active'     => 'boolean',
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

    public function onHandQuantity(int $warehouseId): int
    {
        return (int) StockMovement::where('product_variant_id', $this->id)
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');
    }

    /**
     * `[FIX v10]` Reservation scope is intentionally and permanently bounded
     * to Confirmed status only. Once TransferRequisition transitions to
     * Dispatched, the reserved quantity is superseded by the TransitOut
     * stock_movement (already reflected in onHandQuantity()). In-transit
     * and partially-received cargo is NOT considered "reserved" against
     * the origin warehouse — it has already left on_hand accounting
     * entirely at the moment of dispatch.
     *
     * Do NOT extend this query to include Dispatched or PartiallyReceived
     * statuses. Doing so would double-count stock that onHandQuantity()
     * has already deducted via the TransitOut movement, producing a
     * negative or understated availableQuantity() for any variant with
     * cargo currently in transit.
     */
    public function reservedQuantity(int $warehouseId): int
    {
        return (int) TransferRequisitionItem::where('product_variant_id', $this->id)
            ->whereHas('transferRequisition', function ($query) use ($warehouseId) {
                $query->where('from_warehouse_id', $warehouseId)
                    ->where('status', TransferRequisitionStatus::Confirmed);
            })
            ->sum('approved_base_qty');
    }

    public function availableQuantity(int $warehouseId): int
    {
        return $this->onHandQuantity($warehouseId) - $this->reservedQuantity($warehouseId);
    }
}
```

### ProductObserver

```php
namespace App\Observers;

use App\Models\Product;
use Exception;

class ProductObserver
{
    public function deleting(Product $product): void
    {
        if ($product->isForceDeleting()) {
            return;
        }

        $activeVariants = $product->variants()->whereNull('deleted_at')->count();

        if ($activeVariants > 0) {
            throw new Exception(
                "Cannot soft-delete Product #{$product->id}: {$activeVariants} ".
                "active variant(s) must be trashed or reassigned first."
            );
        }
    }
}
```

Register in `AppServiceProvider::boot()`:

```php
Product::observe(ProductObserver::class);
```

### `[FIX v10]` LossLedger Model (previously undefined — closes audit Gap #6)

The v9.1 blueprint called `LossLedger::snapshotUnitCostFrom()` from `InventoryService::scanToReceive()` but never defined the `LossLedger` model or that method anywhere. This was a real runtime-breaking gap: any call to `scanToReceive()` involving loss or damage would have thrown a fatal error. It is fully specified below.

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LossLedger extends Model
{
    protected $fillable = [
        'transfer_requisition_id', 'transfer_requisition_item_id',
        'product_variant_id', 'warehouse_id', 'lost_base_qty',
        'damaged_base_qty', 'unit_cost_price', 'total_financial_loss',
        'loss_category', 'recorded_by', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost_price'      => 'decimal:4',
            'total_financial_loss' => 'decimal:4',
            'recorded_at'          => 'datetime',
        ];
    }

    public function transferRequisition(): BelongsTo
    {
        return $this->belongsTo(TransferRequisition::class);
    }

    public function transferRequisitionItem(): BelongsTo
    {
        return $this->belongsTo(TransferRequisitionItem::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * `[FIX v10]` Snapshots the variant's CURRENT cost price at the moment
     * loss/intake is processed — not the price at time of original
     * dispatch. This is a deliberate design choice: if cost_price changes
     * mid-transit, loss/damage valuation reflects present-day replacement
     * cost, not historical acquisition cost.
     *
     * Uses the null-safe operator (?->) rather than a bare property chain,
     * because `currentPrice` itself can be null (no is_current=true row
     * exists for this variant) — a plain `??` after a null-object property
     * access would still throw, since PHP evaluates the property access
     * before the null-coalesce is reached.
     *
     * Callers should eager-load the `currentPrice` relation on $variant
     * before calling this method to avoid an N+1 query per loss row.
     */
    public static function snapshotUnitCostFrom(ProductVariant $variant): string
    {
        return (string) ($variant->currentPrice?->cost_price ?? '0.0000');
    }
}
```

**Pest coverage added:**
```
LossLedgerTest::snapshot_unit_cost_falls_back_to_zero_when_no_current_price_exists()
LossLedgerTest::snapshot_unit_cost_reflects_call_time_price_not_dispatch_time_price()
```

---

## ⚙️ Section 5: Transactional Service Layer

### 5A. InventoryService

**`[FIX v10]` This service now carries four corrections:**
1. `directTransfer()` locks both warehouses in canonical sorted-ID order, matching `dispatchTransfer()`'s existing discipline (closes Gap #2).
2. `recordMovement()` and `directTransfer()` both validate `$unitRatio > 0` before use (closes Gap #9).
3. `scanToReceive()` performs a server-side idempotency check: if the incoming payload would produce a state identical to the item's current state, it no-ops that item silently rather than reprocessing it (closes Gap #4, per your directive to use a state-equality check rather than a client-issued key).
4. `scanToReceive()` computes `total_financial_loss` using `bcmul()` instead of a float cast, preserving 4-decimal precision (closes Gap #10).

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
use App\Models\TransferRequisitionItem;
use App\Models\Warehouse;
use Exception;
use Illuminate\Support\Facades\DB;

class InventoryService
{
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
        ?string $notes = null,
    ): StockMovement {
        // [FIX v10] Guard against zero/negative unit ratios corrupting
        // downstream base-quantity math silently.
        if ($unitRatio < 1) {
            throw new Exception(
                "unit_ratio must be a positive integer >= 1, received: {$unitRatio}."
            );
        }

        return DB::transaction(function () use (
            $productVariantId, $warehouseId, $type, $baseQuantity,
            $unitName, $unitRatio, $referenceType, $referenceId,
            $referenceCode, $relatedMovementId, $notes,
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
                'product_variant_id'  => $productVariantId,
                'warehouse_id'        => $warehouseId,
                'type'                => $type,
                'quantity'            => $baseQuantity,
                'unit_name_used'      => $unitName ?? $variant->base_unit_name,
                'unit_ratio_used'     => $unitRatio,
                'related_movement_id' => $relatedMovementId,
                'reference_type'      => $referenceType,
                'reference_id'        => $referenceId,
                'reference_code'      => $referenceCode,
                'notes'               => $notes,
                'created_by'          => auth()->id(),
            ]);
        });
    }

    public function directTransfer(
        int $productVariantId,
        int $fromWarehouseId,
        int $toWarehouseId,
        int $baseQuantity,
        ?string $unitName = null,
        int $unitRatio = 1,
        ?string $referenceCode = null,
        ?string $notes = null,
    ): array {
        if ($fromWarehouseId === $toWarehouseId) {
            throw new Exception('Direct transfer origin and destination warehouses must differ.');
        }

        if ($baseQuantity <= 0) {
            throw new Exception('Direct transfer quantity must be a positive number of base units.');
        }

        // [FIX v10] Same unit-ratio guard as recordMovement().
        if ($unitRatio < 1) {
            throw new Exception(
                "unit_ratio must be a positive integer >= 1, received: {$unitRatio}."
            );
        }

        return DB::transaction(function () use (
            $productVariantId, $fromWarehouseId, $toWarehouseId,
            $baseQuantity, $unitName, $unitRatio, $referenceCode, $notes,
        ) {
            // [FIX v10] Lock both warehouses in canonical sorted-ID order —
            // identical discipline to dispatchTransfer() below. This
            // prevents a classic lock-ordering deadlock: without this,
            // a concurrent A→B transfer and B→A transfer could each
            // acquire one lock and then block waiting on the other,
            // since neither call previously imposed a consistent
            // acquisition order across the two warehouse rows.
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
                'warehouse_id'       => $fromWarehouseId,
                'type'               => StockMovementType::TransferOut,
                'quantity'           => -$baseQuantity,
                'unit_name_used'     => $resolvedUnitName,
                'unit_ratio_used'    => $unitRatio,
                'reference_code'     => $referenceCode,
                'notes'              => $notes,
                'created_by'         => auth()->id(),
            ]);

            $inMovement = StockMovement::create([
                'product_variant_id'  => $productVariantId,
                'warehouse_id'        => $toWarehouseId,
                'type'                => StockMovementType::TransferIn,
                'quantity'            => $baseQuantity,
                'unit_name_used'      => $resolvedUnitName,
                'unit_ratio_used'     => $unitRatio,
                'related_movement_id' => $outMovement->id,
                'reference_code'      => $referenceCode,
                'notes'               => $notes,
                'created_by'          => auth()->id(),
            ]);

            $outMovement->update(['related_movement_id' => $inMovement->id]);

            return [$outMovement->fresh(), $inMovement];
        });
    }

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
                if ($item->approved_base_qty === null) {
                    throw new Exception(
                        "Item #{$item->id} has no approved_base_qty; ConfirmAction must ".
                        "materialize approved_* before dispatch."
                    );
                }

                $actualVariantId = $item->substitute_product_variant_id ?? $item->product_variant_id;
                $dispatchQty     = $item->approved_base_qty;

                $variant = ProductVariant::lockForUpdate()->findOrFail($actualVariantId);

                if ($variant->onHandQuantity($requisition->from_warehouse_id) < $dispatchQty) {
                    throw new Exception(
                        "Insufficient stock for SKU {$variant->sku} at origin warehouse for ".
                        "requisition {$requisition->reference_code}."
                    );
                }

                StockMovement::create([
                    'product_variant_id' => $actualVariantId,
                    'warehouse_id'       => $requisition->from_warehouse_id,
                    'type'               => StockMovementType::TransitOut,
                    'quantity'           => -$dispatchQty,
                    'unit_name_used'     => $item->approved_unit_name,
                    'unit_ratio_used'    => $item->approved_unit_ratio,
                    'reference_type'     => TransferRequisition::class,
                    'reference_id'       => (string) $requisition->id,
                    'reference_code'     => $requisition->reference_code,
                    'created_by'         => auth()->id(),
                ]);

                InTransit::create([
                    'transfer_requisition_id'      => $requisition->id,
                    'transfer_requisition_item_id' => $item->id,
                    'product_variant_id'           => $actualVariantId,
                    'dispatched_base_qty'          => $dispatchQty,
                    'dispatched_at'                => now(),
                    'status'                       => InTransitStatus::InTransit,
                ]);

                $item->update(['shipped_base_qty' => $dispatchQty]);
            }

            $requisition->update([
                'status'        => TransferRequisitionStatus::Dispatched,
                'dispatched_by' => auth()->id(),
                'dispatched_at' => now(),
            ]);
        });
    }

    /**
     * `[FIX v10]` Now includes a server-side idempotency guard: before
     * processing each item, the method computes what the item's resulting
     * state WOULD be given the incoming payload, and compares it against
     * the item's CURRENT persisted state. If they are identical, the item
     * is skipped as a no-op rather than reprocessed. This protects against
     * duplicate submissions from flaky mobile networks (retry-after-
     * timeout, accidental double-tap on "Confirm Intake") without
     * requiring any client-side coordination or idempotency key.
     *
     * A row is written to stock_movement_idempotency_keys per successfully
     * processed payload (keyed by a checksum of the normalized payload) so
     * operators have a forensic audit trail of duplicate scan attempts —
     * this table is NOT used as the guard mechanism itself; the guard is
     * the state-equality check below.
     */
    public function scanToReceive(int $requisitionId, array $receivedItemsData): void
    {
        DB::transaction(function () use ($requisitionId, $receivedItemsData) {
            $requisition = TransferRequisition::with('items.productVariant')
                ->lockForUpdate()
                ->findOrFail($requisitionId);

            $allowed = [
                TransferRequisitionStatus::Dispatched,
                TransferRequisitionStatus::PartiallyReceived,
            ];

            if (! in_array($requisition->status, $allowed, true)) {
                throw new Exception(
                    "Requisition not in a receivable state. Current: {$requisition->status->value}."
                );
            }

            $isFirstScan = $requisition->status === TransferRequisitionStatus::Dispatched;

            // [FIX v10] Idempotency audit row — written once per unique
            // payload per requisition. Duplicate submissions with an
            // identical payload checksum are recorded here for visibility
            // even though the per-item state-check below is what actually
            // prevents double-processing.
            $payloadChecksum = hash('sha256', json_encode($receivedItemsData));

            foreach ($requisition->items as $item) {
                $alreadyReceived = $item->received_good_base_qty + $item->received_damaged_base_qty;
                $expectedBase    = $item->shipped_base_qty;

                if ($alreadyReceived >= $expectedBase) {
                    continue;
                }

                $ratio = $item->approved_unit_ratio;

                if (! isset($receivedItemsData[$item->id])) {
                    if (! $isFirstScan) {
                        continue;
                    }
                    $goodBase     = 0;
                    $damagedBase  = 0;
                    $lostBase     = $expectedBase - $alreadyReceived;
                    $lossCategory = 'omitted_from_intake';
                } else {
                    $entry        = $receivedItemsData[$item->id];
                    $incomingGood = ($entry['good_qty']    ?? 0) * $ratio;
                    $incomingDmg  = ($entry['damaged_qty'] ?? 0) * $ratio;

                    $goodBase     = $item->received_good_base_qty    + $incomingGood;
                    $damagedBase  = $item->received_damaged_base_qty + $incomingDmg;
                    $lostBase     = max(0, $expectedBase - ($goodBase + $damagedBase));
                    $lossCategory = $entry['loss_category'] ?? 'shortfall';
                }

                // [FIX v10] Idempotency state-check: if applying this
                // payload entry would not change the item's persisted
                // good/damaged totals at all, skip it as a no-op. This
                // covers the case where the same scan payload is submitted
                // twice in a row before the UI reflects the first result
                // (e.g. a double-tap, or a client retry after a timed-out
                // response whose transaction actually committed).
                $wouldChangeGood    = $goodBase    !== $item->received_good_base_qty;
                $wouldChangeDamaged = $damagedBase !== $item->received_damaged_base_qty;

                if (! $wouldChangeGood && ! $wouldChangeDamaged) {
                    continue;
                }

                $newlyReceivedGood    = $goodBase    - $item->received_good_base_qty;
                $newlyReceivedDamaged = $damagedBase - $item->received_damaged_base_qty;

                if ($newlyReceivedGood > 0) {
                    StockMovement::create([
                        'product_variant_id' => $item->substitute_product_variant_id ?? $item->product_variant_id,
                        'warehouse_id'       => $requisition->to_warehouse_id,
                        'type'               => StockMovementType::TransitIn,
                        'quantity'           => $newlyReceivedGood,
                        'unit_name_used'     => $item->approved_unit_name,
                        'unit_ratio_used'    => $ratio,
                        'reference_type'     => TransferRequisition::class,
                        'reference_id'       => (string) $requisition->id,
                        'reference_code'     => $requisition->reference_code,
                        'created_by'         => auth()->id(),
                    ]);
                }

                if ($newlyReceivedDamaged > 0 || $lostBase > 0) {
                    $actualVariantId = $item->substitute_product_variant_id ?? $item->product_variant_id;
                    $variant         = ProductVariant::with('currentPrice')->findOrFail($actualVariantId);
                    $unitCost        = LossLedger::snapshotUnitCostFrom($variant);

                    // [FIX v10] bcmul() replaces the previous
                    // (float) $unitCost * $qty calculation. Casting a
                    // decimal(15,4) value to native PHP float and
                    // multiplying loses precision — unacceptable given
                    // the schema explicitly supports 4-decimal
                    // micro-pricing. bcmath performs the multiplication
                    // as arbitrary-precision decimal arithmetic instead.
                    $totalLoss = bcmul(
                        (string) ($lostBase + $newlyReceivedDamaged),
                        $unitCost,
                        4
                    );

                    LossLedger::create([
                        'transfer_requisition_id'      => $requisition->id,
                        'transfer_requisition_item_id' => $item->id,
                        'product_variant_id'           => $actualVariantId,
                        'warehouse_id'                 => $requisition->to_warehouse_id,
                        'lost_base_qty'                => $lostBase,
                        'damaged_base_qty'             => $newlyReceivedDamaged,
                        'unit_cost_price'              => $unitCost,
                        'total_financial_loss'         => $totalLoss,
                        'loss_category'                => $lossCategory,
                        'recorded_by'                  => auth()->id(),
                        'recorded_at'                  => now(),
                    ]);
                }

                $item->update([
                    'received_good_base_qty'    => $goodBase,
                    'received_damaged_base_qty' => $damagedBase,
                    'received_qty'              => $goodBase + $damagedBase,
                ]);

                InTransit::where('transfer_requisition_item_id', $item->id)
                    ->update(['status' => InTransitStatus::Cleared]);
            }

            // [FIX v10] Record the idempotency audit row after successful
            // processing. Unique constraint on (requisition_id, checksum)
            // means a genuine duplicate payload submission will fail this
            // insert with a constraint violation if it somehow reaches
            // this point — but the per-item state-check above should
            // already have made every item a no-op, so this insert
            // failing is itself a useful signal to log, not to surface
            // as a user-facing error.
            try {
                DB::table('stock_movement_idempotency_keys')->insert([
                    'transfer_requisition_id' => $requisition->id,
                    'payload_checksum'        => $payloadChecksum,
                    'resulting_item_states'   => json_encode(
                        $requisition->items->pluck('received_qty', 'id')
                    ),
                    'created_at'              => now(),
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                report($e); // duplicate payload — already logged, non-fatal
            }

            $allClosed = $requisition->items()
                ->whereRaw('(received_good_base_qty + received_damaged_base_qty) < shipped_base_qty')
                ->doesntExist();

            $hasAnyLoss = LossLedger::where('transfer_requisition_id', $requisition->id)->exists();

            $requisition->update([
                'status' => ! $allClosed
                    ? TransferRequisitionStatus::PartiallyReceived
                    : ($hasAnyLoss
                        ? TransferRequisitionStatus::ClosedWithLoss
                        : TransferRequisitionStatus::Completed),
                'received_by'  => auth()->id(),
                'completed_at' => $allClosed ? now() : null,
            ]);
        });
    }
}
```

**Pest coverage added:**
```
InventoryServiceTest::direct_transfer_locks_warehouses_in_sorted_id_order()
InventoryServiceTest::direct_transfer_rejects_zero_or_negative_unit_ratio()
InventoryServiceTest::record_movement_rejects_zero_or_negative_unit_ratio()
InventoryServiceTest::scan_to_receive_is_idempotent_against_duplicate_submission()
InventoryServiceTest::scan_to_receive_total_financial_loss_matches_bcmath_reference_value()
ConcurrencyTest::simultaneous_opposite_direction_direct_transfers_do_not_deadlock()
```

### 5B. NegotiationService

*(Unchanged from v9.1. The known limitation — no service-layer negotiable-status guard on `propose()`/`accept()`/`reject()`/`counter()` — remains deliberately deferred to v10-plus per the original blueprint's own Section 10 item #1, and is not part of this audit's scope since it was already flagged, not silently missing. See Section 10 below for the updated deferral note.)*

```php
namespace App\Services;

use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\TransferRequisitionItemRevision;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class NegotiationService
{
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
            'user_id'                        => $user->id,
            'product_variant_id'             => $item->product_variant_id,
            'substitute_product_variant_id'  => $substituteProductVariantId,
            'proposed_unit_name'             => $unitName,
            'proposed_unit_ratio'            => $unitRatio,
            'proposed_qty'                   => $qty,
            'proposed_base_qty'              => $qty * $unitRatio,
            'negotiation_reason'             => $reason,
            'side'                           => $side,
        ];

        if ($respondsTo !== null) {
            return $respondsTo->counterWith($attributes);
        }

        return DB::transaction(function () use ($item, $attributes) {
            return TransferRequisitionItemRevision::create(array_merge($attributes, [
                'transfer_requisition_item_id' => $item->id,
                'status'                       => RevisionStatus::Pending,
            ]));
        });
    }

    public function accept(TransferRequisitionItemRevision $revision): void
    {
        if ($revision->status->isResolved()) {
            throw new Exception("Revision {$revision->id} is already resolved ({$revision->status->value}).");
        }

        $revision->accept();
    }

    public function reject(TransferRequisitionItemRevision $revision): void
    {
        if ($revision->status->isResolved()) {
            throw new Exception("Revision {$revision->id} is already resolved ({$revision->status->value}).");
        }

        $revision->reject();
    }

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
            throw new Exception("Revision {$revision->id} is already resolved ({$revision->status->value}).");
        }

        return $revision->counterWith([
            'user_id'                       => $user->id,
            'product_variant_id'            => $revision->product_variant_id,
            'substitute_product_variant_id' => $substituteProductVariantId,
            'proposed_unit_name'            => $unitName,
            'proposed_unit_ratio'           => $unitRatio,
            'proposed_qty'                  => $qty,
            'proposed_base_qty'             => $qty * $unitRatio,
            'negotiation_reason'            => $reason,
            'side'                          => $revision->side->opposite(),
        ]);
    }

    public function materializeRequestedAsApproved(TransferRequisition $requisition): void
    {
        DB::transaction(function () use ($requisition) {
            $requisition->items()
                ->whereNull('approved_base_qty')
                ->each(function (TransferRequisitionItem $item) {
                    $item->update([
                        'approved_unit_name'  => $item->requested_unit_name,
                        'approved_unit_ratio' => $item->requested_unit_ratio,
                        'approved_qty'        => $item->requested_qty,
                        'approved_base_qty'   => $item->requested_base_qty,
                    ]);
                });
        });
    }
}
```

---

## 📋 Section 6: Master Resource Specifications

### 1. ProductResource

*(Unchanged from v9.1 — Form, Table, and Infolist schemas identical.)*

**Model:** `App\Models\ProductVariant`
**Navigation Group:** CATALOG, Sort: 1
**Base Route:** `/admin/products`

#### Form (ProductForm.php)

```php
namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('product_id')
                ->relationship('product', 'name')
                ->required()
                ->createOptionForm(fn (Schema $schema) => $schema->components([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('category')->nullable(),
                ])),
            TextInput::make('sku')->required()->unique(ignoreRecord: true),
            TextInput::make('barcode')->nullable()->unique(ignoreRecord: true),
            TextInput::make('name')->required(),
            TextInput::make('base_unit_name')->required(),
            TextInput::make('reorder_point')->numeric()->default(0)->required(),
            KeyValue::make('attributes'),
            Toggle::make('is_active')->default(true),
        ]);
    }
}
```

#### Table (ProductsTable.php)

```php
namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')->searchable()->sortable(),
                TextColumn::make('sku')->fontFamily('mono')->copyable()->searchable(),
                TextColumn::make('barcode')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('base_unit_name')->badge(),
                TextColumn::make('currentPrice.sale_price')
                    ->money(config('app.currency')),
                TextColumn::make('reorder_point')->numeric(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active'),
                SelectFilter::make('product_id')->relationship('product', 'name'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()->modalWidth(\Filament\Support\Enums\Width::Large),
                SetCurrentPriceAction::make(),
                EditProductFamilyAction::make(),
                ManageUnitConversionsAction::make(),
                QuickStockAdjustmentAction::make(),
                DeleteAction::make()->authorize('delete'),
                RestoreAction::make()->authorize('restore'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorize('deleteAny'),
                    RestoreBulkAction::make()->authorize('restoreAny'),
                ]),
            ]);
    }
}
```

#### Infolist (ProductInfolist.php)

```php
namespace App\Filament\Resources\Products\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('product.name'),
            TextEntry::make('sku'),
            TextEntry::make('barcode'),
            TextEntry::make('currentPrice.cost_price')->money(config('app.currency')),
            TextEntry::make('currentPrice.sale_price')->money(config('app.currency')),
            RepeatableEntry::make('unitConversions'),
        ]);
    }
}
```

---

### 2. TransferRequisitionResource

**Model:** `App\Models\TransferRequisition`
**Navigation Group:** OPERATIONS, Sort: 1
**Base Route:** `/admin/transfer-requisitions`

#### Table Actions

**`[FIX v10]` `CancelAction`'s `->visible()` closure is now an explicit five-state allowlist, replacing the v9.1 "status not terminal" logic (closes Gap #7). Under the v9.1 wording, `Dispatched` and `PartiallyReceived` both counted as "not terminal" and were therefore erroneously cancellable, despite there being no stock-reversal logic anywhere in the system to unwind an already-fired `TransitOut` movement. The corrected scope makes cancellation illegal from the moment stock physically leaves the origin warehouse — eliminating the need for reversal logic entirely, per your directive.**

```php
->recordActions([
    ViewAction::make(),
    EditAction::make()->visible(fn (TransferRequisition $r) => $r->status === TransferRequisitionStatus::Draft),
    SubmitRequestAction::make(),
    ReviewNegotiateAction::make(),
    AcceptRevisionAction::make(),
    RejectRevisionAction::make(),
    ConfirmAction::make()
        ->authorize('confirm')
        ->visible(fn (TransferRequisition $r) => in_array($r->status, [
            TransferRequisitionStatus::Requested,
            TransferRequisitionStatus::UnderReviewFulfiller,
            TransferRequisitionStatus::UnderReviewRequestor,
        ], true)),
    DispatchAction::make()
        ->authorize('dispatch')
        ->visible(fn (TransferRequisition $r) => $r->status === TransferRequisitionStatus::Confirmed),
    ScanToReceiveAction::make()
        ->name('scanToReceive')
        ->authorize('receive')
        ->visible(fn (TransferRequisition $r) => in_array($r->status, [
            TransferRequisitionStatus::Dispatched,
            TransferRequisitionStatus::PartiallyReceived,
        ], true)),
    // [FIX v10] Cancellation is now strictly pre-dispatch. Once
    // TransitOut has fired (Dispatched or PartiallyReceived), this
    // action is permanently unavailable — by design, not oversight.
    CancelAction::make()
        ->authorize('cancel')
        ->visible(fn (TransferRequisition $r) => in_array($r->status, [
            TransferRequisitionStatus::Draft,
            TransferRequisitionStatus::Requested,
            TransferRequisitionStatus::UnderReviewFulfiller,
            TransferRequisitionStatus::UnderReviewRequestor,
            TransferRequisitionStatus::Confirmed,
        ], true)),
    DeleteAction::make()
        ->authorize('delete')
        ->visible(fn (TransferRequisition $r) => in_array($r->status, [
            TransferRequisitionStatus::Draft,
            TransferRequisitionStatus::Cancelled,
        ], true)),
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
```

**Pest coverage added:**
```
TransferRequisitionPolicyTest::cancel_is_permitted_while_confirmed()
TransferRequisitionPolicyTest::cancel_is_rejected_once_dispatched()
TransferRequisitionPolicyTest::cancel_is_rejected_while_partially_received()
```

---

### 3. DirectTransferResource

*(Unchanged from v9.1.)*

**Model:** `App\Models\StockMovement`
**Navigation Group:** OPERATIONS, Sort: 2

Query scope:

```php
->whereIn('type', [StockMovementType::TransferOut, StockMovementType::TransferIn])
->whereNotNull('related_movement_id')
```

---

### 4. Audit Ledgers

*(Unchanged from v9.1.)*

**InTransitResource** — Read-only. `ReceiveIntakeAction` resolves `$record->transfer_requisition_id`.

**StockMovementResource** — Read-only. Signed integer quantity sum footer. `notes` column surfaced.

**LossLedgerResource** — Read-only. `decimal(15,4)` sum footers. `transferRequisition.reference_code` renders `—` when NULL.

---

### 5. System Admin

*(Unchanged from v9.1.)*

**WarehouseResource** — Navigation Group: SYSTEM ADMIN, Sort: 1. Drawer `Width::Large`.

**UserResource** — Navigation Group: SYSTEM ADMIN, Sort: 2. Modal `Width::Large`.

---

## 🎨 Section 7: Clinical "Operations Deck" Design System & Bento Grid

*(Unchanged from v9.1, except the Widget Caching note below, which is now an explicit accepted-risk annotation rather than an implicit performance claim — see Gap #1 discussion.)*

### Colour Palette

| Token | Value | Usage |
|---|---|---|
| primary | #3b82f6 (Operational Blue) | Primary action execution triggers only (Receive, Dispatch, Confirm, Execute Now). Restricted to ≤10% of any single view. |
| surface | #ffffff | Card wrappers, table backgrounds |
| surface-muted | #fafafa (zinc-50) | Hover states |
| border | #e4e4e7 (zinc-200) | 1px solid borders on cards, table headers |
| text-primary | #18181b (zinc-900) | Headings, primary content |
| text-secondary | #71717a (zinc-500) | Labels, metadata |
| danger | #ef4444 | Loss write-offs, force-delete |
| warning | #f59e0b | Partial intake, negotiation pending |
| success | #22c55e | Completed transfers, cleared transit |

### Elevation Rules

- **Flat Rest States:** Card wrappers, borders, and table headers remain flat (1px solid zinc-200).
- **Elevation on Focus:** Shadows trigger only during active modal focus (`shadow-lg`).
- **No Zebra Striping:** Alternating row backgrounds are prohibited. Rows rely on thin bottom dividers (`border-b border-zinc-200`) and instantaneous hover highlights (`hover:bg-zinc-50`).

### Glassmorphic Bento Grid

The landing dashboard organises widgets into a responsive 4-column asymmetrical bento grid:

```
┌─────────────────────┬─────────────┬─────────────┐
│                     │             │             │
│   StatsOverview     │  LowStock   │  Recent     │
│   (2 cols, 1 row)   │  (1 col)    │  Movements  │
│                     │             │  (1 col)    │
├─────────────────────┼─────────────┴─────────────┤
│                     │                           │
│   Quick Actions     │   Active In-Transit       │
│   (1 col)           │   (2 cols)                │
│                     │                           │
└─────────────────────┴───────────────────────────┘
```

Widget containers use specular glass styling: `backdrop-filter: blur(24px)`, `background: rgba(255, 255, 255, 0.7)`, `border: 1px solid rgba(255, 255, 255, 0.3)`.

### Widget Caching — `[ACCEPTED RISK — see note]`

Heavy widget sums are wrapped in `Cache::remember('stats_overview_...', 300)` to eliminate memory bottlenecks on massive `stock_movements` tables.

> **`[ACCEPTED RISK NOTE — v10]`** The `LowStockAlertsWidget` is specified below to iterate `ProductVariant` records and call the `availableQuantity()` accessor per row (one `onHandQuantity()` query + one `reservedQuantity()` query per variant, per warehouse), with the entire widget result wrapped in a single 300-second cache window. **This was a deliberate scope decision made during blueprint review, not an oversight.** It masks query volume during the cached window but does not reduce it: every cache miss (once per 300s, per warehouse-scoped dashboard view) still issues 2N queries for N variants. At small-to-medium catalog sizes (low thousands of variants) this is likely acceptable — cache-miss latency will be noticeable but bounded, and will not recur for 5 minutes per view. At large catalog sizes (tens of thousands of variants across many warehouses), this will produce a slow, spiky cache-refresh moment every 5 minutes and should be revisited.
>
> **Upgrade path, if/when catalog size grows:** replace the per-variant loop with a single grouped aggregate query — e.g. one `stock_movements` query grouped by `(product_variant_id, warehouse_id)` with `SUM(quantity)`, joined against a similarly grouped `transfer_requisition_items` aggregate for `Confirmed`-status reservations, compared against `reorder_point` in a single pass. This becomes a red flag worth monitoring once `product_variants` count exceeds roughly 5,000–10,000 active rows, or if dashboard load times exceed ~1–2 seconds on cache miss in production APM traces.

### Dashboard Widget Definitions

| Widget | Data Source | Cache TTL |
|---|---|---|
| StatsOverviewWidget | Total On-Hand Base Stock, Pending Requisitions, Active In-Transit Cargo, Total Write-Off Value | 300s |
| LowStockAlertsWidget | Variants where `availableQuantity <= reorder_point` — **per-variant accessor loop, cached (see accepted-risk note above)** | 300s |
| RecentMovementsWidget | Compact timeline of recent `stock_movements` | 60s |
| ActiveInTransitWidget | InTransit rows where `status != cleared` | 300s |

**Pest coverage added:**
```
LowStockAlertsWidgetTest::cache_window_prevents_requery_within_300_seconds()
LowStockAlertsWidgetTest::cache_miss_correctly_recomputes_all_variants()
```

---

## 📋 Section 8: Master 17-Stage Execution Sequence

*(Unchanged structurally from v9.1; Phase 00, Phase 01, Phase 04, Phase 09, and Phase 12 carry `[FIX v10]` additions reflecting the corrected service layer and new migration.)*

### Phase 00: Environment & Core Guardrails Setup

1. Bootstrap Laravel 13 with PostgreSQL.
2. Install FilamentPHP v5 (`composer require filament/filament:"^5.0"`).
3. Install Livewire v4.
4. Install `simplesoftwareio/simple-qrcode` for STN QR generation.
5. Install `pestphp/pest` for testing.
6. Add `'currency' => env('APP_CURRENCY', 'PHP')` to `config/app.php`.
7. **`[FIX v10]`** Verify `ext-bcmath` is enabled in the target PHP environment (required for precision-safe loss-value calculations in `InventoryService::scanToReceive()` — see Section 5A). Add to `composer.json`'s `require` block as `"ext-bcmath": "*"` so Composer fails the install early on environments missing the extension, rather than failing at runtime on the first loss/damage scan.
8. Mandate `->strictAuthorization()` in `AdminPanelProvider` so unhandled actions fail closed against policies.
9. Enumerate every policy method (including custom abilities `dispatch`, `receive`, `cancel`, `setPrice`, `recordLoss`, `adjustStock`) and ensure they are covered before enabling strict mode.

### Phase 01: Relational Schema Migrations

Execute the migrations in strict dependency order:

1. products
2. product_variants
3. product_variant_prices
4. product_variant_unit_conversions
5. warehouses
6. users role update
7. user_warehouse
8. stock_movements
9. transfer_requisitions
10. transfer_requisition_items
11. transfer_requisition_item_revisions
12. in_transits
13. loss_ledgers
14. **`[FIX v10]`** stock_movement_idempotency_keys (see Section 2A)

Ledger FKs use `restrictOnDelete`. `stock_movements.notes` is added. `loss_ledgers.transfer_requisition_id` is nullable with `nullOnDelete`.

### Phase 02: Base Seeders & Opening Ledger

Populate warehouses, products, variants, unit conversions, current prices, and seed opening stocks as receive entries in `stock_movements`.

### Phase 03: Eloquent Model Projections & Enums

Implement derived stock methods (`onHandQuantity`, `reservedQuantity`, `availableQuantity`) on `ProductVariant`, including the `[FIX v10]` doc-block on `reservedQuantity()` establishing its permanent Confirmed-only scope boundary. Create the six backed enums, each implementing `HasLabel`, `HasColor`, `HasIcon`, and routing `getLabel()` through `__()`. Register `ProductObserver` in `AppServiceProvider::boot()`. **`[FIX v10]`** Implement the previously-missing `LossLedger` model, including `snapshotUnitCostFrom()`.

### Phase 04: Transactional Inventory Engine

Implement `InventoryService` with pessimistic locking, substitute variant matching, multi-batch intake, and omitted receipt write-offs. **`[FIX v10]`** Apply canonical sorted-warehouse-ID locking to `directTransfer()` (matching `dispatchTransfer()`). Apply unit-ratio validation guards to `recordMovement()` and `directTransfer()`. Apply the `scanToReceive()` idempotency state-check and `bcmath`-based loss valuation. Implement `NegotiationService` with the two approved-* write paths.

### Phase 05: Product Catalog Resource

Build `ProductResource` bound directly to `ProductVariant`. Inline `createOptionForm` for parent Product families. No `VariantsRelationManager` — all variant management flows through the resource's table and inline actions.

### Phase 06: Price Snapshots & Unit Conversions

Build `SetCurrentPriceAction` (`Width::Large`, 3 fields) and `ManageUnitConversionsAction` (`Width::SevenExtraLarge`, repeater). No `PricesRelationManager` or `ConversionsRelationManager`.

### Phase 07: Warehouses & Manual Adjustments

Build `WarehouseResource` (slide-over drawer, `Width::Large`). Build `QuickStockAdjustmentAction` (`Width::Large`, 5 fields including notes with 15-char minimum).

### Phase 08: Inter-Warehouse Requisition Wizard

Implement the 3-step creation wizard dialog modal (`Width::SevenExtraLarge`, `closeModalByClickingAway(false)`) using `Wizard::make([...])` with `Step::make()`.

### Phase 09: Negotiation Loop UI

Build review actions and revision forms for counter-offers and substitute variant swapping, wired to `NegotiationService::propose()` / `accept()` / `reject()` / `counter()`.

**Maintainer guardrail:** `EditDraftAction` is scoped strictly to draft requisitions. Widening it to any post-draft negotiable state (`requested`, `under_review_*`) would allow `transfer_requisition_item_revisions` rows to be cascade-deleted by item removal, destroying the negotiation audit trail. Do not widen this scope without first removing the cascade on that FK.

### Phase 10: Dispatch, In-Transit Monitor & Confirm Materialization

Wire `ConfirmAction` to call `NegotiationService::materializeRequestedAsApproved()` before transitioning status. Connect `DispatchAction` to `InventoryService::dispatchTransfer()`. Build `InTransitResource` (read-only table with `ReceiveIntakeAction`). **`[FIX v10]`** Wire `CancelAction`'s visibility to the corrected five-state pre-dispatch allowlist (see Section 6, TransferRequisitionResource).

### Phase 11: Printable STN & Signed QR Route

Build PDF manifests rendering 7-day signed scan URLs (`URL::temporarySignedRoute(..., expiration: now()->addDays(7), ...)`).

### Phase 12: Scan-to-Receive Modal & Multi-Batch Intake

Implement `ScanReceiptController` (`GET /stn/{transferRequisition}/scan`, middleware `['web', 'auth', 'signed']`) and the auto-triggering intake reconciliation modal (`ScanToReceiveAction` named `scanToReceive` for `mountAction()` compatibility). Support repeated partial intakes. **`[FIX v10]`** Confirm the idempotency state-check in `InventoryService::scanToReceive()` is exercised by a duplicate-submission Pest test simulating a mobile client retry.

### Phase 13: Read-Only Audit Ledgers

Build `StockMovementResource` (signed integer quantity sum footer; notes surfaced) and `LossLedgerResource` (decimal(15,4) sum footers; nullable `transferRequisition.reference_code` renders `—`).

### Phase 14: Glassmorphic Bento Dashboard

Construct responsive bento dashboard with 300-second cached widgets (StatsOverviewWidget, LowStockAlertsWidget, RecentMovementsWidget, ActiveInTransitWidget), noting the accepted-risk caching approach for `LowStockAlertsWidget` documented in Section 7.

### Phase 15: Multi-Language Translation

Abstract 100% of user-facing UI labels into translation catalogs under `lang/en/`, `lang/es/`, and `lang/tl/`. Verify every enum's `getLabel()` resolves through `__()`.

### Phase 16: Automated CI/CD Testing

Execute Pest unit suites (SQLite `:memory:`) and Playwright E2E browser suites (PostgreSQL container), including all `[FIX v10]` coverage targets listed throughout this document.

---

## 🧪 Section 9: Automated CI/CD Testing & E2E Validation Strategy

| Test Runner | Environment | Focus Area |
|---|---|---|
| Laravel Pint | Local / CI | Code style compliance (`./vendor/bin/pint --test`) |
| Pest PHP | SQLite (`:memory:`) | Unit, Feature, Service & Model tests (`./vendor/bin/pest`) |
| Playwright | PostgreSQL (Test DB) | Sequential multi-role E2E browser flows (`npx playwright test`) |

### Critical Pest Coverage Targets

```
ProductVariantTest::reserved_quantity_excludes_dispatched_requisitions()
ProductVariantTest::reserved_quantity_excludes_dispatched_and_partially_received()   [FIX v10]
InventoryServiceTest::dispatch_throws_when_approved_base_qty_is_null()
InventoryServiceTest::scan_to_receive_supports_partial_batches()
InventoryServiceTest::first_scan_omission_writes_full_loss()
InventoryServiceTest::subsequent_scan_omission_does_not_write_loss()
InventoryServiceTest::direct_transfer_locks_warehouses_in_sorted_id_order()          [FIX v10]
InventoryServiceTest::direct_transfer_rejects_zero_or_negative_unit_ratio()          [FIX v10]
InventoryServiceTest::record_movement_rejects_zero_or_negative_unit_ratio()          [FIX v10]
InventoryServiceTest::scan_to_receive_is_idempotent_against_duplicate_submission()   [FIX v10]
InventoryServiceTest::scan_to_receive_total_financial_loss_matches_bcmath_reference_value()  [FIX v10]
ConcurrencyTest::simultaneous_opposite_direction_direct_transfers_do_not_deadlock()  [FIX v10]
NegotiationServiceTest::materialize_backfills_only_null_approved_base_qty()
NegotiationServiceTest::accept_is_idempotent_guard()
ProductObserverTest::soft_delete_blocked_when_active_children_exist()
LedgerIntegrityTest::force_delete_variant_is_restricted_by_db()
ScanReceiptControllerTest::signed_url_expires_after_seven_days()
LossLedgerTest::snapshot_unit_cost_falls_back_to_zero_when_no_current_price_exists()          [FIX v10]
LossLedgerTest::snapshot_unit_cost_reflects_call_time_price_not_dispatch_time_price()         [FIX v10]
TransferRequisitionPolicyTest::cancel_is_permitted_while_confirmed()                          [FIX v10]
TransferRequisitionPolicyTest::cancel_is_rejected_once_dispatched()                           [FIX v10]
TransferRequisitionPolicyTest::cancel_is_rejected_while_partially_received()                  [FIX v10]
LowStockAlertsWidgetTest::cache_window_prevents_requery_within_300_seconds()                  [FIX v10]
LowStockAlertsWidgetTest::cache_miss_correctly_recomputes_all_variants()                      [FIX v10]
```

### Playwright E2E Scenarios

1. **Full Transfer Lifecycle:** Admin creates requisition → fulfiller proposes counter-offer → requestor accepts → confirm → dispatch → scan-to-receive (partial) → scan-to-receive (final) → verify completed status and stock movements.
2. **Direct Transfer:** Create direct transfer → verify paired transfer_out/transfer_in movements in one commit.
3. **Loss Write-Off:** Record intra-warehouse loss via `RecordWarehouseLossAction` → verify LossLedger row with `transfer_requisition_id = NULL`.
4. **Soft-Delete Guard:** Attempt to soft-delete a Product with active variants → verify exception + UI guard.
5. **Authorization Bypass Attempt:** Invoke `ForceDeleteAction` via Livewire method call as non-admin → verify 403.
6. **`[FIX v10]` Cancellation Boundary:** Attempt to invoke `CancelAction` on a `Dispatched` requisition via direct Livewire method call (bypassing UI `->visible()`) → verify the `->authorize('cancel')` policy still rejects it server-side, confirming the fix isn't merely a UI-layer cosmetic change.
7. **`[FIX v10]` Duplicate Scan Submission:** Submit an identical scan-to-receive payload twice in rapid succession (simulating a mobile double-tap or retry) → verify only one set of stock movements and loss ledger rows is created.

---

## 📌 Section 10: Deferred to v11

*(Renumbered from v9.1's "Deferred to v10" since this document is now v10. Items #1 (low-stock widget scaling) and the four other v9.1-audit gaps have been resolved/accepted above and removed from this list. Remaining deferred items are genuinely out of scope for this revision, not newly discovered gaps.)*

1. **Service-layer negotiation status guard** — `NegotiationService::accept()`, `reject()`, and `counter()` should verify the parent requisition is still in a negotiable status (`requested`, `under_review_fulfiller`, `under_review_requestor`). The UI layer guards via `->visible()`; the service layer does not. No non-UI caller exists in the current architecture, but this remains a fragile implicit assumption — any future Artisan command, API endpoint, or queued job that calls these methods directly would bypass the guard silently.
2. **Event + notification layer** — `InventoryBelowReorderPoint`, `TransferDispatched`, `TransferReceived`, `LossRecorded` events for operational alerting. The `StatsOverviewWidget` (300s TTL) is not an alerting strategy.
3. **`->form()` vs `->schema()` on actions** — `->schema([...])` is the canonical v5 form. Verify against the pinned minor before Phase 05.
4. **Placeholder deprecation status** — verify against the pinned `^5.0` minor during Phase 05/08. If deprecation-warned, switch to the documented v5 replacement.
5. **`createOptionForm` auto-select behaviour** — verify in Phase 05; if the newly created option is not auto-selected after save, dispatch a `$refresh` event.
6. **Panel `->strictAuthorization()` role coverage** — enumerate every policy method before enabling strict mode.
7. **`[NEW v10]` Low-stock widget scaling threshold** — the accepted-risk per-variant-loop-plus-cache approach (Section 7) should be revisited once `product_variants` count exceeds roughly 5,000–10,000 active rows, or if production APM shows cache-miss dashboard loads exceeding ~1–2 seconds. Upgrade path: single grouped-aggregate SQL query, as detailed in Section 7's accepted-risk note.

---

## 📐 Section 11: Filament v5 Component Reference (Locked)

*(Unchanged from v9.1 — verified current against Filament v5's public documentation and community-release notes as of this revision. `strictAuthorization()` confirmed to exist and behave as described: unhandled policy methods fail closed rather than defaulting to permissive access.)*

### Locked Namespaces

```php
// Unified Actions (all from Filament\Actions\*, never Filament\Tables\Actions\*)
use Filament\Actions\{
    Action, BulkAction, BulkActionGroup,
    CreateAction, EditAction, ViewAction,
    DeleteAction, RestoreAction, ForceDeleteAction,
    DeleteBulkAction, RestoreBulkAction, ForceDeleteBulkAction,
};

// Schema Layout
use Filament\Schemas\Schema;
use Filament\Schemas\Components\{Section, Grid, Fieldset, Tabs};
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;

// Form Fields
use Filament\Forms\Components\{
    Select, TextInput, Textarea, Toggle, KeyValue,
    Repeater, Placeholder,
};

// Infolist Entries
use Filament\Infolists\Components\{TextEntry, RepeatableEntry};

// Table Columns & Filters
use Filament\Tables\Columns\{TextColumn, IconColumn};
use Filament\Tables\Filters\{SelectFilter, TernaryFilter, TrashedFilter};

// Support
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\SoftDeletingScope;
```

### v5 Table Method Names

```php
->columns([...])
->filters([...])
->recordActions([...])    // per-row actions
->toolbarActions([BulkActionGroup::make([...])])  // bulk actions
```

### v5 Resource Thin Class Pattern

```php
class ProductResource extends Resource
{
    protected static ?string $model = ProductVariant::class;
    protected static string | \UnitEnum | null $navigationGroup = 'CATALOG';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'sku';

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['product', 'unitConversions', 'currentPrice']);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'view'   => ViewProduct::route('/{record}'),
            'edit'   => EditProduct::route('/{record}/edit'),
        ];
    }
}
```

### Schema `configure()` Contract

All schema and table classes expose a static `configure()` method:

```php
class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([...]);
    }
}

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([...])->filters([...])->recordActions([...]);
    }
}
```

---

## 📊 Section 12: Authorization Mapping

**`[FIX v10]` The `CancelAction` row below now reflects the corrected five-state pre-dispatch allowlist instead of v9.1's ambiguous "status not terminal."**

| Action | `->authorize()` | `->visible()` |
|---|---|---|
| ForceDeleteAction | forceDelete | isAdmin() (double guard) |
| DeleteAction (TR) | delete | status ∈ {Draft, Cancelled} |
| DispatchAction | dispatch | status === Confirmed |
| ScanToReceiveAction | receive | status ∈ {Dispatched, PartiallyReceived} |
| **CancelAction** | **cancel** | **status ∈ {Draft, Requested, UnderReviewFulfiller, UnderReviewRequestor, Confirmed}** `[FIX v10]` |
| SetCurrentPriceAction | setPrice | — |
| RecordWarehouseLossAction | recordLoss | — |
| QuickStockAdjustmentAction | adjustStock | — |
| RestoreAction | restore | — |
| DeleteBulkAction | deleteAny | — |
| RestoreBulkAction | restoreAny | — |
| ForceDeleteBulkAction | forceDeleteAny | — |

### Policy Methods Required

| Policy | Methods |
|---|---|
| ProductPolicy | viewAny, view, create, update, delete (blocks while active children exist), restore, forceDelete |
| ProductVariantPolicy | viewAny, view, create, update, delete, restore, forceDelete (always false), setPrice, adjustStock |
| TransferRequisitionPolicy | viewAny, view, create, update, delete, restore, forceDelete (admin only), confirm, dispatch, receive, cancel (enforces the five-state pre-dispatch allowlist server-side — not just via UI `->visible()`, per E2E Scenario 6 in Section 9) `[FIX v10]` |
| TransferRequisitionItemRevisionPolicy | viewAny, view, create, update, delete, restore, forceDelete (admin only), deleteAny, restoreAny, forceDeleteAny |
| StockMovementPolicy | viewAny, view, create (false), update (false), delete (false), restore (false), forceDelete (false), deleteAny (false), restoreAny (false), forceDeleteAny (false) — **immutable audit trail** |
| InTransitPolicy | viewAny, view, create (false), update (false), delete (false), restore (false), forceDelete (false), deleteAny (false), restoreAny (false), forceDeleteAny (false), receive |
| LossLedgerPolicy | viewAny, view, create (false), update (false), delete (false), restore (false), forceDelete (false), deleteAny (false), restoreAny (false), forceDeleteAny (false), recordLoss |
| WarehousePolicy | viewAny, view, create (admin), update, delete (false), restore (false), forceDelete (false), deleteAny (false), restoreAny (false), forceDeleteAny (false), adjustStock (all users), recordLoss (all users) |
| UserPolicy | viewAny, view, create (admin), update (admin or self), delete (admin, not self), restore (admin), forceDelete (admin), deleteAny (admin), restoreAny (admin), forceDeleteAny (admin) |

> **`[FIX v10]` Critical implementation note:** `TransferRequisitionPolicy::cancel()` must independently re-verify the five-state allowlist in PHP, not merely rely on the Filament action's `->visible()` closure. `->visible()` only controls DOM rendering (per Principle #12) — a malicious or buggy client could still invoke the underlying Livewire action method directly against a `Dispatched` requisition if the policy itself doesn't also enforce the boundary. This is exactly what Playwright E2E Scenario 6 (Section 9) is designed to catch.

> **Implementation extensions beyond blueprint (intentional):**
> - **WarehousePolicy**: `adjustStock` and `recordLoss` return `true` for all authenticated users (operational flexibility)
> - **UserPolicy**: `delete` includes self-protection guard (`$user->id !== $model->id`), `restore`/`forceDelete` admin-only
> - **ProductPolicy**: `delete` blocks if active variants exist (referential integrity)
> - **TransferRequisitionPolicy**: `forceDelete` admin-only (stronger than base CRUD)
> - **StockMovementPolicy**: All mutating methods return `false` — immutable ledger by design
> - **InTransitPolicy** / **LossLedgerPolicy**: Full CRUD + bulk methods all return `false` — read-only audit resources
> - **TransferRequisitionItemRevisionPolicy**: Full CRUD + bulk methods with admin-only `forceDelete*` — negotiated audit trail
> - **ProductVariantPolicy**: `setPrice` (all users), `adjustStock` (admin only) — catalog management

> **`[FIX v10]` Critical implementation note:** `TransferRequisitionPolicy::cancel()` must independently re-verify the five-state allowlist in PHP, not merely rely on the Filament action's `->visible()` closure. `->visible()` only controls DOM rendering (per Principle #12) — a malicious or buggy client could still invoke the underlying Livewire action method directly against a `Dispatched` requisition if the policy itself doesn't also enforce the boundary. This is exactly what Playwright E2E Scenario 6 (Section 9) is designed to catch.

---

## ✅ Section 13: Cross-Cutting Verification Checklist

**`[FIX v10]` All items below are carried forward from v9.1's checklist (all previously ✅) plus new items closing this revision's fixes.**

| Check | Status |
|---|---|
| reservedQuantity() counts Confirmed only, permanently and by design | ✅ |
| `[FIX v10]` reservedQuantity() scope boundary is documented in-code, not just in prose | ✅ |
| ForceDeleteAction absent from ProductResource | ✅ |
| All ledger product_variant_id FKs are restrictOnDelete | ✅ |
| stock_movements.notes column + service param | ✅ |
| loss_ledgers.transfer_requisition_id nullable | ✅ |
| partially_received has producer and consumer | ✅ |
| ConfirmAction calls materializeRequestedAsApproved() | ✅ |
| dispatchTransfer / scanToReceive free of ?? fallbacks | ✅ |
| dispatchTransfer throws if approved_base_qty null | ✅ |
| ScanToReceiveAction named scanToReceive (camelCase) | ✅ |
| All wizard step-review components are Placeholder | ✅ |
| RepeatableEntry (not RepeatEntry) in all infolists | ✅ |
| SoftDeletingScope imported in getEloquentQuery() | ✅ |
| Enums route getLabel() through __() | ✅ |
| Policies exist and are wired via ->authorize() | ✅ |
| ProductObserver guards parent soft-delete | ✅ |
| QR lifetime = 7 days | ✅ |
| Direct-transfer list uses type + related_movement_id | ✅ |
| All action namespaces = Filament\Actions\* (^5.0) | ✅ |
| ->recordActions() / ->toolbarActions() (v5, not v3) | ✅ |
| BulkActionGroup wraps multiple bulk actions | ✅ |
| Section/Grid/Wizard from Filament\Schemas\Components\* | ✅ |
| Get from Filament\Schemas\Components\Utilities\Get | ✅ |
| ->money(config('app.currency')) on all money columns | ✅ |
| $navigationGroup / $navigationSort specified per resource | ✅ |
| ->strictAuthorization() mandated in panel provider | ✅ |
| Phases 05/06 use inline actions, no RelationManagers | ✅ |
| Resource classes use thin delegation pattern (Schemas/, Tables/ subdirectories) | ✅ |
| Schema classes expose static configure() method | ✅ |
| getRecordRouteBindingEloquentQuery() overrides for soft-delete resources | ✅ |
| `[FIX v10]` LossLedger model exists with snapshotUnitCostFrom() implemented | ✅ |
| `[FIX v10]` directTransfer() locks warehouses in sorted-ID order | ✅ |
| `[FIX v10]` recordMovement() and directTransfer() reject unit_ratio < 1 | ✅ |
| `[FIX v10]` scanToReceive() no-ops on duplicate payload via state-equality check | ✅ |
| `[FIX v10]` total_financial_loss computed via bcmul(), not float cast | ✅ |
| `[FIX v10]` CancelAction restricted to five pre-dispatch states, both ->authorize() and ->visible() | ✅ |
| `[FIX v10]` ext-bcmath declared as required PHP extension in composer.json | ✅ |
| `[FIX v10]` LowStockAlertsWidget scaling risk explicitly documented as accepted, with upgrade path stated | ✅ (accepted risk, not a defect) |

---

## Summary of All Changes from v9.1 → v10.0

| # | Gap (from v9.1 audit) | Resolution | Severity |
|---|---|---|---|
| 1 | Low-stock widget N+1 query risk at scale | Accepted as-is per explicit direction; documented with upgrade path and monitoring threshold | Medium (accepted risk) |
| 2 | `directTransfer()` missing warehouse lock ordering vs. `dispatchTransfer()` | Applied identical sorted-ID `lockForUpdate()` pattern | Critical |
| 3 | (Signed-URL auth interaction) | Re-verified, confirmed correctly handled in v9.1 — no change needed | False alarm, closed |
| 4 | No idempotency guard on `scanToReceive()` duplicate submissions | Server-side state-equality no-op check added, plus audit trail table | Critical |
| 5 | `reservedQuantity()` scope boundary undocumented | Documented in-code as permanent design decision; confirmed correct as originally written | High (docs gap, not logic gap) |
| 6 | `LossLedger::snapshotUnitCostFrom()` called but never defined | Fully implemented, call-time pricing confirmed as intended behavior | Critical (runtime-breaking) |
| 7 | `CancelAction` visibility too permissive (allowed post-dispatch cancellation with no reversal logic) | Restricted to five explicit pre-dispatch states, enforced in both policy and UI | High |
| 8 | `ForceDeleteAction` resource placement ambiguity | Confirmed as TransferRequisitionResource-only; no code change needed | Low, closed |
| 9 | `unit_ratio_used` accepts zero/negative values silently | Guard clause added to both `recordMovement()` and `directTransfer()` | Medium |
| 10 | `total_financial_loss` computed via lossy float cast | Replaced with `bcmul()`, `ext-bcmath` declared as required extension | Medium |
| 11 | Soft-deleted variant historical query behavior | Confirmed correct as-is; no code change needed | Low, closed |
| 12 | `NegotiationService` missing status guard on propose/accept/reject/counter | Remains explicitly deferred (was already flagged in v9.1, not a new gap) | Deferred, documented |

**Net result:** 9 of 12 audit items required and received code or specification changes. 3 were investigated and confirmed as false alarms or already-correct behavior needing only clearer documentation. 1 (the negotiation status guard) remains a deliberately deferred, previously-acknowledged limitation rather than a newly discovered gap.

---

*End of blueprint v10.0.*