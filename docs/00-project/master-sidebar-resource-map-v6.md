# Master Sidebar Navigation & Modular Resource Map (v2.7) — Ultimate Unified Edition

This master design blueprint establishes the definitive front-end layout, database-access policies, and complete UI-to-service contracts for **Caduceus (v2.2)**. 

It synthesizes the **Council Gap Closure Specification** and **Blueprint v3 Requirements** into a single, comprehensive, production-ready codebase roadmap.

---

## 🗺️ Part 1: Unified Navigation Architecture & RBAC Policies

The sidebar is structured into four uppercase-styled groups designed for speed and clarity in active warehouse operations. Security and data filtering are enforced dynamically at the database query-policy level using our casted **`UserRole` Enum**:

| Group Name | Resource | Base Route | Role Visibility | Sort Order | Eager-Loaded Relations (N+1 Guard) |
| :--- | :--- | :--- | :--- | :---: | :--- |
| **[Home]** | Dashboard | `/admin` | *All Roles* | — | — |
| **CATALOG** | `ProductResource` | `/admin/products` | *All Roles* | 1 | `variants`, `variants.unitConversions` |
| **OPERATIONS** | `TransferRequisitionResource` | `/admin/transfer-requisitions` | *All Roles* | 1 | `fromWarehouse`, `toWarehouse`, `requestedBy`, `items` |
| | `DirectTransferResource` | `/admin/direct-transfers` | *All Roles* | 2 | `variant`, `warehouse`, `relatedMovement`, `creator` |
| **AUDIT LEDGERS** | `InTransitResource` | `/admin/in-transits` | Admin, Auditor, Branch Manager | 1 | `requisition.fromWarehouse`, `requisition.toWarehouse`, `variant` |
| | `StockMovementResource` | `/admin/stock-movements` | Admin, Auditor, Branch Manager | 2 | `variant`, `warehouse`, `creator` |
| | `LossLedgerResource` | `/admin/loss-ledgers` | Admin, Auditor | 3 | `requisition`, `variant`, `warehouse` |
| **SYSTEM ADMIN** | `WarehouseResource` | `/admin/warehouses` | Admin Only | 1 | `users` |
| | `UserResource` | `/admin/users` | Admin Only | 2 | `warehouses` |

### 🔑 UserRole Enum (`app/Enums/UserRole.php`)
```php
namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Auditor = 'auditor';
    case BranchManager = 'branch_manager';
    case WarehouseStaff = 'warehouse_staff';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Auditor => 'Logistics Auditor',
            self::BranchManager => 'Branch Manager',
            self::WarehouseStaff => 'Warehouse Staff',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Auditor => 'info',
            self::BranchManager => 'warning',
            self::WarehouseStaff => 'gray',
        };
    }
}
```

---

## 📁 Part 2: Complete Modular Directory Tree

Following modern Filament v5 design patterns, form schemas, table definitions, infolists, and relation managers are decoupled into isolated single-responsibility classes. This keeps main resource files exceptionally clean and prevents dynamic Livewire states from leaking into database queries:

```
app/
├── Enums/
│   └── UserRole.php                       # Strict 4-role canonical RBAC enum
├── Filament/
│   ├── Pages/
│   │   └── Dashboard.php                  # Bento-grid panel with 300s query cache
│   └── Resources/
│       ├── Products/
│       │   ├── ProductResource.php        # Catalog router class
│       │   ├── Pages/
│       │   │   ├── CreateProduct.php
│       │   │   ├── EditProduct.php
│       │   │   └── ListProducts.php
│       │   ├── Schemas/
│       │   │   └── ProductForm.php        # v5 Schema configure() class
│       │   ├── Tables/
│       │   │   └── ProductsTable.php      # High-density catalog grid
│       │   └── RelationManagers/
│       │       ├── VariantsRelationManager.php   # Attribute matrix builder
│       │       ├── ConversionsRelationManager.php # Packaging unit translator
│       │       └── PricesRelationManager.php      # 4-decimal price override editor
│       │
│       ├── TransferRequisitions/
│       │   ├── TransferRequisitionResource.php # State-machine router
│       │   ├── Pages/
│       │   │   ├── EditTransferRequisition.php
│       │   │   ├── ViewTransferRequisition.php
│       │   │   └── ListTransferRequisitions.php # Launches 3-Step Wizard Modal
│       │   ├── Infolists/
│       │   │   └── RequisitionInfolist.php    # High-contrast 3-column detail view
│       │   ├── RelationManagers/
│       │   │   └── RevisionsRelationManager.php  # Round-by-round negotiation timeline
│       │   ├── Schemas/
│       │   │   ├── TransferRequisitionForm.php # 3-step reactive wizard steps
│       │   │   └── RevisionsForm.php           # Counter-offer slide-over inputs
│       │   └── Tables/
│       │       └── TransferRequisitionsTable.php # Color-coded status list
│       │
│       ├── DirectTransfers/
│       │   ├── DirectTransferResource.php     # Instant direct movements router
│       │   ├── Pages/
│       │   │   └── ListDirectTransfers.php    # Launches 3-Step Instant Modal
│       │   ├── Schemas/
│       │   │   └── DirectTransferForm.php     # Instant 3-step reactive schema
│       │   └── Tables/
│       │       └── DirectTransfersTable.php   # Paired-leg movement ledger view
│       │
│       ├── InTransits/
│       │   ├── InTransitResource.php          # Cargo monitor router
│       │   ├── Pages/
│       │   │   └── ListInTransits.php
│       │   └── Tables/
│       │       └── InTransitsTable.php        # Live transit age & Confirm Receipt Dialog
│       │
│       ├── StockMovements/
│       │   ├── StockMovementResource.php      # Immutable ledger router
│       │   ├── Pages/
│       │   │   └── ListStockMovements.php
│       │   └── Tables/
│       │       └── StockMovementsTable.php    # Signed (+/-) transaction table
│       │
│       ├── LossLedgers/
│       │   ├── LossLedgerResource.php         # Write-off monitor router
│       │   ├── Pages/
│       │   │   └── ListLossLedgers.php
│       │   └── Tables/
│       │       └── LossLedgersTable.php       # High-precision financial summaries
│       │
│       ├── Warehouses/
│       │   ├── WarehouseResource.php          # Location setup router
│       │   ├── Pages/
│       │   │   ├── CreateWarehouse.php
│       │   │   ├── EditWarehouse.php
│       │   │   └── ListWarehouses.php
│       │   ├── Schemas/
│       │   │   └── WarehouseForm.php          # Address & routing toggle schemas
│       │   ├── Tables/
│       │   │   └── WarehousesTable.php        # Location list + note adjustment drawer
│       │   └── RelationManagers/
│       │       └── WarehouseStocksRelationManager.php # Dynamic derived stock table
│       │
│       └── Users/
│           ├── UserResource.php               # Account setup router
│           ├── Pages/
│           │   ├── CreateUser.php
│           │   ├── EditUser.php
│           │   └── ListUsers.php
│           ├── Schemas/
│           │   └── UserForm.php               # Accounts & multi-warehouse assignment
│           └── Tables/
│               └── UsersTable.php             # Directory table with RBAC badges
├── Http/
│   └── Controllers/
│       ├── STNManifestController.php          # Printable PDFs (Requisitions & Direct)
│       └── ScanReceiptController.php          # QR Scan-to-Receive Landing Page
├── Models/
│   ├── Product.php
│   ├── ProductVariant.php                     # Pure derived query calculations
│   ├── ProductUnitConversion.php
│   ├── Warehouse.php
│   ├── StockMovement.php                      # Single transaction source of truth
│   ├── TransferRequisition.php
│   ├── RequisitionItem.php
│   ├── TransferRequisitionItemRevision.php
│   ├── LossLedger.php
│   └── User.php
└── Services/
    ├── InventoryService.php                   # Atomic database transaction blocks
    └── LossLedgerService.php                  # Snapshot write-off recording
```

---

## 🛠️ Part 3: Model-Level Pure Derived Stock of Truth

To permanently eliminate physical-to-digital inventory levels desynchronizing, **there is no stored warehouse_stock database table.** 

On-hand counts, pending transit allocations, and physical availability are dynamically calculated at query-time on the `ProductVariant` model:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\StockMovement;
use App\Models\RequisitionItem;

class ProductVariant extends Model
{
    // ... basic definitions ...

    /**
     * Physical on-hand stock: The exact SUM of all signed physical logs.
     */
    public function onHandQuantity(int $warehouseId): int
    {
        return StockMovement::where('variant_id', $this->id)
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');
    }

    /**
     * Active Requisition Reservations: The SUM of items locked in unreceived transfers.
     */
    public function reservedQuantity(int $warehouseId): int
    {
        return RequisitionItem::where('variant_id', $this->id)
            ->whereHas('requisition', function ($q) use ($warehouseId) {
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

## 🧱 Part 4: Technical Specifications for Resource Directories

### 1. Catalog Domain: Products (`Products/`)

*   **Active Eloquent Relations**:
    *   `Product` $\rightarrow$ `HasMany` $\rightarrow$ `ProductVariant`
    *   `ProductVariant` $\rightarrow$ `HasMany` $\rightarrow$ `ProductUnitConversion` (packaging units)
    *   `ProductVariant` $\rightarrow$ `HasMany` $\rightarrow$ `ProductPrice` (custom prices overrides)

#### 📝 Form Schema (`Schemas/ProductForm.php`)
```php
namespace App\Filament\Resources\Products\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('sku')
                        ->label('BASE PRODUCT SKU')
                        ->required()
                        ->unique(ignorable: fn ($record) => $record)
                        ->placeholder('PROD-COF-001')
                        ->extraInputAttributes(['aria-label' => 'BASE PRODUCT SKU']),
                        
                    TextInput::make('name')
                        ->label('PRODUCT FAMILY NAME')
                        ->required()
                        ->placeholder('Arabica Specialty Coffee'),
                        
                    TextInput::make('category')
                        ->label('CATEGORY')
                        ->placeholder('Beans'),
                        
                    TextInput::make('reorder_point')
                        ->label('DEFAULT SAFETY REORDER POINT (BASE UNITS)')
                        ->numeric()
                        ->default(0)
                        ->required(),
                ]),
        ]);
    }
}
```

#### 📊 Table Schema (`Tables/ProductsTable.php`)
```php
namespace App\Filament\Resources\Products\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class ProductsTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->bold()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('PRODUCT FAMILY NAME')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->label('CATEGORY')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('reorder_point')
                    ->label('SAFETY THRESHOLD')
                    ->numeric()
                    ->sortable(),
            ]);
    }
}
```

#### 🔗 Price Override Relation Manager (`RelationManagers/PricesRelationManager.php`)
```php
namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';
    protected static ?string $title = 'LOCATION & PACKAGING PRICE OVERRIDES';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('warehouse_id')
                ->label('TARGET WAREHOUSE BRANCH')
                ->relationship('warehouse', 'name')
                ->placeholder('Global Fallback (All Locations)')
                ->searchable(),

            Select::make('price_type')
                ->label('PRICE CLASSIFICATION')
                ->options([
                    'cost' => 'Unit Cost Price',
                    'sale' => 'Unit Sale Price',
                ])
                ->required(),

            TextInput::make('price')
                ->label('PRICE PER UNIT')
                ->numeric()
                ->required()
                ->step('0.0001') // Decimal:4 compliance
                ->placeholder('0.0000'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('warehouse.name')
                    ->label('LOCATION SCOPE')
                    ->placeholder('Global Fallback'),
                TextColumn::make('price_type')
                    ->label('TYPE')
                    ->badge()
                    ->color(fn ($state) => $state === 'cost' ? 'info' : 'success'),
                TextColumn::make('price')
                    ->label('HIGH-PRECISION PRICE')
                    ->state(fn ($record) => '$' . number_format($record->price, 4))
                    ->alignRight()
                    ->bold(),
            ]);
    }
}
```

---

### 2. Operations Domain: Transfer Requisitions (`TransferRequisitions/`)

*   **Active Eloquent Relations**:
    *   `TransferRequisition` $\rightarrow$ `BelongsTo` $\rightarrow$ `Warehouse` (as `fromWarehouse`, `toWarehouse`)
    *   `TransferRequisition` $\rightarrow$ `HasMany` $\rightarrow$ `RequisitionItem` (`items`)
    *   `RequisitionItem` $\rightarrow$ `HasMany` $\rightarrow$ `TransferRequisitionItemRevision` (`itemRevisions`)

#### 📝 3-Step Wizard Form Schema (`Schemas/TransferRequisitionForm.php`)
```php
namespace App\Filament\Resources\TransferRequisitions\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use App\Models\ProductVariant;

class TransferRequisitionForm
{
    public static function getRoutingSchema(): array
    {
        return [
            Select::make('from_warehouse_id')
                ->label('ORIGIN WAREHOUSE (FULFILLER)')
                ->relationship('fromWarehouse', 'name', fn ($q) => $q->where('is_active', true))
                ->required()
                ->searchable(),

            Select::make('to_warehouse_id')
                ->label('DESTINATION WAREHOUSE (REQUESTOR)')
                ->relationship('toWarehouse', 'name', fn ($q) => $q->where('is_active', true))
                ->required()
                ->searchable()
                ->different('from_warehouse_id'),
        ];
    }

    public static function getItemsSchema(): array
    {
        return [
            Repeater::make('items')
                ->schema([
                    Select::make('variant_id')
                        ->label('PRODUCT VARIANT')
                        ->relationship('variant', 'sku')
                        ->required()
                        ->searchable()
                        ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                    TextInput::make('requested_unit_name')
                        ->label('PACKAGING FORMAT')
                        ->required()
                        ->placeholder('E.g., Box'),

                    TextInput::make('requested_unit_ratio')
                        ->label('UNIT MULTIPLIER RATIO')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->default(1),

                    TextInput::make('requested_qty')
                        ->label('ORDER QUANTITY')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->default(1),
                ])
                ->columns(4)
                ->defaultItems(1),
        ];
    }

    public static function getReviewSchema(): array
    {
        return [
            Placeholder::make('review_summary')
                ->label('VERIFY TRANSFER REQUISITION DETAILS')
                ->content(function ($get) {
                    $fromId = $get('from_warehouse_id');
                    $toId = $get('to_warehouse_id');
                    $items = $get('items') ?? [];

                    if (!$fromId || !$toId || empty($items)) {
                        return 'Complete the previous steps to review the requisition manifest.';
                    }

                    $fromName = \App\Models\Warehouse::find($fromId)?->name ?? 'Unknown';
                    $toName = \App\Models\Warehouse::find($toId)?->name ?? 'Unknown';

                    $rowsHtml = '';
                    foreach ($items as $item) {
                        $sku = ProductVariant::find($item['variant_id'])?->sku ?? 'Unknown';
                        $format = $item['requested_unit_name'] ?? 'Base Unit';
                        $ratio = $item['requested_unit_ratio'] ?? 1;
                        $qty = $item['requested_qty'] ?? 0;
                        $totalBase = $qty * $ratio;

                        $rowsHtml .= "
                            <tr class='border-b border-zinc-200'>
                                <td class='py-2 font-mono text-xs'>{$sku}</td>
                                <td class='py-2 text-xs'>{$format}</td>
                                <td class='py-2 text-xs text-right'>1 : {$ratio}</td>
                                <td class='py-2 text-xs text-right'>{$qty}</td>
                                <td class='py-2 text-xs text-right font-semibold'>{$totalBase} Pcs</td>
                            </tr>";
                    }

                    return new \Illuminate\Support\HtmlString("
                        <div class='rounded-lg bg-zinc-50 p-4 border border-zinc-200'>
                            <div class='grid grid-cols-2 gap-4 mb-4 pb-4 border-b border-zinc-200'>
                                <div>
                                    <span class='text-[10px] uppercase font-semibold text-zinc-500'>Dispatching From</span>
                                    <p class='text-sm font-semibold text-zinc-800'>{$fromName}</p>
                                </div>
                                <div>
                                    <span class='text-[10px] uppercase font-semibold text-zinc-500'>Shipping To</span>
                                    <p class='text-sm font-semibold text-zinc-800'>{$toName}</p>
                                </div>
                            </div>
                            <table class='w-full text-left'>
                                <thead>
                                    <tr class='border-b border-zinc-300'>
                                        <th class='pb-2 text-[10px] uppercase font-semibold text-zinc-500'>Product SKU</th>
                                        <th class='pb-2 text-[10px] uppercase font-semibold text-zinc-500'>Packaging</th>
                                        <th class='pb-2 text-[10px] uppercase font-semibold text-zinc-500 text-right'>Ratio</th>
                                        <th class='pb-2 text-[10px] uppercase font-semibold text-zinc-500 text-right'>Qty</th>
                                        <th class='pb-2 text-[10px] uppercase font-semibold text-zinc-500 text-right'>Total Base</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {$rowsHtml}
                                </tbody>
                            </table>
                        </div>
                    ");
                }),
        ];
    }
}
```

#### 📊 Table Schema (`Tables/TransferRequisitionsTable.php`)
```php
namespace App\Filament\Resources\TransferRequisitions\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class TransferRequisitionsTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_code')
                    ->label('TRANSFER CODE')
                    ->bold()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('fromWarehouse.name')
                    ->label('ORIGIN')
                    ->sortable(),
                TextColumn::make('toWarehouse.name')
                    ->label('DESTINATION')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('OPERATIONAL STATUS')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'requested', 'under_review_fulfiller', 'under_review_requestor' => 'warning',
                        'confirmed', 'dispatched' => 'info',
                        'completed' => 'success',
                        'closed_with_loss' => 'danger',
                        'cancelled' => 'gray',
                    }),
                TextColumn::make('requested_at')
                    ->label('SUBMITTED ON')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordClasses(fn ($record) => match ($record->status) {
                'requested', 'under_review_fulfiller' => 'bg-amber-50/20 hover:bg-amber-50/40 transition-colors',
                default => 'hover:bg-zinc-50/50 transition-colors'
            });
    }
}
```

---

### 3. Operations Domain: Instant Direct Transfers (`DirectTransfers/`)

*   **Active Eloquent Relations**:
    *   `StockMovement` $\rightarrow$ `BelongsTo` $\rightarrow$ `ProductVariant` (`variant`)
    *   `StockMovement` $\rightarrow$ `BelongsTo` $\rightarrow$ `Warehouse` (`warehouse`)
    *   `StockMovement` $\rightarrow$ `BelongsTo` $\rightarrow$ `StockMovement` (`relatedMovement` to pair the logs)

#### 📝 3-Step Wizard Form Schema (`Schemas/DirectTransferForm.php`)
```php
namespace App\Filament\Resources\DirectTransfers\Schemas;

use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Placeholder;
use App\Models\ProductVariant;

class DirectTransferForm
{
    public static function getRoutingSchema(): array
    {
        return [
            Select::make('from_warehouse_id')
                ->label('ORIGIN BRANCH (FULFILLER)')
                ->relationship('fromWarehouse', 'name', fn ($q) => $q->where('is_active', true))
                ->required()
                ->searchable(),

            Select::make('to_warehouse_id')
                ->label('DESTINATION BRANCH (RECEIVER)')
                ->relationship('toWarehouse', 'name', fn ($q) => $q->where('is_active', true))
                ->required()
                ->searchable()
                ->different('from_warehouse_id'),
        ];
    }

    public static function getAllocationSchema(): array
    {
        return [
            Select::make('variant_id')
                ->label('PRODUCT VARIANT')
                ->relationship('variant', 'sku')
                ->required()
                ->searchable(),

            TextInput::make('quantity')
                ->label('TRANSFER QUANTITY (BASE)')
                ->numeric()
                ->required()
                ->minValue(1)
                ->default(1),

            Textarea::make('notes')
                ->label('AUDIT COMPLIANCE REASON')
                ->required()
                ->minLength(15) // Guardrail 12 Compliance
                ->regex('/^(?!(.)\1+$)(?!\b(test|dummy|notes|adjust|none)\b)/i')
                ->placeholder('Specify why this off-schedule manual transfer is being recorded...'),
        ];
    }

    public static function getReviewSchema(): array
    {
        return [
            Placeholder::make('review_summary')
                ->label('VERIFY INSTANT DIRECT TRANSFER DETAILS')
                ->content(function ($get) {
                    $fromId = $get('from_warehouse_id');
                    $toId = $get('to_warehouse_id');
                    $variantId = $get('variant_id');
                    $qty = $get('quantity');
                    $notes = $get('notes');

                    if (!$fromId || !$toId || !$variantId || !$qty) {
                        return 'Complete previous steps to construct the verification sheet.';
                    }

                    $fromName = \App\Models\Warehouse::find($fromId)?->name ?? 'Unknown';
                    $toName = \App\Models\Warehouse::find($toId)?->name ?? 'Unknown';
                    $sku = ProductVariant::find($variantId)?->sku ?? 'Unknown';

                    return new \Illuminate\Support\HtmlString("
                        <div class='rounded-lg bg-zinc-50 p-4 border border-zinc-200'>
                            <div class='grid grid-cols-2 gap-4 mb-4 pb-4 border-b border-zinc-200'>
                                <div>
                                    <span class='text-[10px] uppercase font-semibold text-zinc-500'>Origin Branch</span>
                                    <p class='text-sm font-semibold text-zinc-800'>{$fromName}</p>
                                </div>
                                <div>
                                    <span class='text-[10px] uppercase font-semibold text-zinc-500'>Destination Branch</span>
                                    <p class='text-sm font-semibold text-zinc-800'>{$toName}</p>
                                </div>
                            </div>
                            <div class='mb-4 pb-4 border-b border-zinc-200'>
                                <span class='text-[10px] uppercase font-semibold text-zinc-500'>Stock Allocation details</span>
                                <p class='text-sm text-zinc-800'>Allocating <strong class='font-mono font-semibold'>{$qty} Base Pcs</strong> of SKU: <strong class='font-mono font-semibold text-blue-600'>{$sku}</strong> directly between sites.</p>
                            </div>
                            <div>
                                <span class='text-[10px] uppercase font-semibold text-zinc-500'>Audit Compliance statement</span>
                                <p class='text-xs italic text-zinc-600 font-mono'>\"{$notes}\"</p>
                            </div>
                        </div>
                    ");
                }),
        ];
    }
}
```

---

### 4. Audit Ledgers Domain: Virtual In-Transit (`InTransits/`)

*   **Active Relations**:
    *   `InTransit` $\rightarrow$ `BelongsTo` $\rightarrow$ `TransferRequisition` (`requisition`)
    *   `InTransit` $\rightarrow$ `BelongsTo` $\rightarrow$ `ProductVariant` (`variant`)

#### 📊 Table Schema with "Confirm Receipt" Action Dialog (`Tables/InTransitsTable.php`)
```php
namespace App\Filament\Resources\InTransits\Tables;

use App\Services\InventoryService;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class InTransitsTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('requisition.reference_code')
                    ->label('STN CODE')
                    ->searchable()
                    ->bold(),
                TextColumn::make('requisition.fromWarehouse.name')
                    ->label('FROM LOCATION'),
                TextColumn::make('requisition.toWarehouse.name')
                    ->label('TO LOCATION'),
                TextColumn::make('variant.sku')
                    ->label('VARIANT SKU')
                    ->searchable(),
                TextColumn::make('dispatched_base_qty')
                    ->label('IN-TRANSIT VOLUME (BASE)')
                    ->numeric()
                    ->alignRight(),
                TextColumn::make('dispatched_at')
                    ->label('DEPARTED ON')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('TRANSIT AGE')
                    ->state(fn ($record) => now()->diffInHours($record->dispatched_at) . ' Hrs')
                    ->badge()
                    ->color('warning'),
            ])
            ->actions([
                Action::make('confirmReceipt')
                    ->label('CONFIRM RECEIPT')
                    ->icon('heroicon-o-check-circle')
                    ->modalWidth('2xl')
                    ->closeModalByClickingAway(false) // Guard against accidental data loss
                    ->form(function ($record) {
                        return [
                            Repeater::make('items')
                                ->schema([
                                    TextInput::make('item_id')->hidden(),
                                    TextInput::make('variant_sku')->label('SKU')->disabled(),
                                    TextInput::make('received_qty')
                                        ->label('RECEIVED BASE QTY')
                                        ->numeric()
                                        ->required(),
                                ])
                                ->default(
                                    $record->requisition->items->map(fn ($i) => [
                                        'item_id' => $i->id,
                                        'variant_sku' => $i->variant->sku,
                                        'received_qty' => $i->approved_base_qty,
                                    ])->toArray()
                                ),
                        ];
                    })
                    ->action(function ($record, array $data, InventoryService $service) {
                        $receivedQuantities = collect($data['items'])
                            ->mapWithKeys(fn ($row) => [$row['item_id'] => (int) $row['received_qty']])
                            ->toArray();

                        $service->receiveRequisition($record->requisition, $receivedQuantities, receivedBy: auth()->id());

                        Notification::make()
                            ->title('Receipt confirmed and stock balances updated.')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
```

---

### 5. Audit Ledgers Domain: Material Loss Ledger (`LossLedgers/`)

*   **Active Relations**:
    *   `LossLedger` $\rightarrow$ `BelongsTo` $\rightarrow$ `TransferRequisition` (`requisition`)
    *   `LossLedger` $\rightarrow$ `BelongsTo` $\rightarrow$ `ProductVariant` (`variant`)
    *   `LossLedger` $\rightarrow$ `BelongsTo` $\rightarrow$ `Warehouse` (`warehouse`)

#### 📊 Table Schema with Decimal:4 Sum Footer (`Tables/LossLedgersTable.php`)
```php
namespace App\Filament\Resources\LossLedgers\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;

class LossLedgersTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('requisition.reference_code')
                    ->label('STN CODE')
                    ->searchable()
                    ->bold(),
                TextColumn::make('variant.sku')
                    ->label('DISCREPANT SKU')
                    ->searchable(),
                TextColumn::make('warehouse.name')
                    ->label('SITE BEARING LOSS')
                    ->sortable(),
                TextColumn::make('lost_base_qty')
                    ->label('LOST BASE UNITS')
                    ->numeric(),
                TextColumn::make('unit_cost_price')
                    ->label('UNIT COST')
                    ->state(fn ($record) => '$' . number_format($record->unit_cost_price, 4)),
                TextColumn::make('total_financial_loss')
                    ->label('TOTAL LOSS VALUE')
                    ->state(fn ($record) => '$' . number_format($record->total_financial_loss, 4))
                    ->summarize(
                        Sum::make()
                            ->label('TOTAL WRITE-OFFS')
                            ->formatStateUsing(fn ($state) => '$' . number_format($state, 4))
                    )
                    ->bold()
                    ->alignRight(),
                TextColumn::make('loss_category')
                    ->label('CLASSIFICATION')
                    ->badge()
                    ->color('danger'),
            ]);
    }
}
```

---

### 6. System Admin Domain: Warehouses (`Warehouses/`)

#### 🔗 Pure Derived Warehouse Stocks Table (`RelationManagers/WarehouseStocksRelationManager.php`)
```php
namespace App\Filament\Resources\Warehouses\RelationManagers;

use App\Models\ProductVariant;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class WarehouseStocksRelationManager extends RelationManager
{
    protected static string $relationship = 'stockMovements'; // scopes relation to active ID
    protected static ?string $title = 'REAL-TIME DERIVED STOCK OF TRUTH';

    public function table(Table $table): Table
    {
        $warehouseId = $this->getOwnerRecord()->id;

        return $table
            ->query(
                ProductVariant::query()
                    ->whereHas('stockMovements', fn (Builder $q) => $q->where('warehouse_id', $warehouseId))
            )
            ->columns([
                TextColumn::make('sku')
                    ->label('VARIANT SKU')
                    ->searchable()
                    ->bold(),
                TextColumn::make('name')
                    ->label('VARIANT NAME'),
                TextColumn::make('on_hand')
                    ->label('PHYSICAL ON-HAND')
                    ->state(fn ($record) => $record->onHandQuantity($warehouseId))
                    ->alignRight(),
                TextColumn::make('reserved')
                    ->label('RESERVED (PENDING TRANSFERS)')
                    ->state(fn ($record) => $record->reservedQuantity($warehouseId))
                    ->color('warning')
                    ->alignRight(),
                TextColumn::make('available')
                    ->label('AVAILABLE')
                    ->state(fn ($record) => $record->availableQuantity($warehouseId))
                    ->color(fn ($state) => $state <= 0 ? 'danger' : 'success')
                    ->bold()
                    ->alignRight(),
            ]);
    }
}
```

---

## 🖨️ Part 5: Printable Transfer Details (STN & Direct Manifests)

### 1. Print Controller Router (`app/Http/Controllers/STNManifestController.php`)
```php
namespace App\Http\Controllers;

use App\Models\TransferRequisition;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class STNManifestController extends Controller
{
    /**
     * Prints the multi-stage Stock Transfer Note (STN) Manifest.
     */
    public function print(Request $request, TransferRequisition $requisition)
    {
        $user = auth()->user();
        
        // Enforce RBAC site scope
        if (!$user->canAccessWarehouse($requisition->toWarehouse) && !$user->canAccessWarehouse($requisition->fromWarehouse)) {
            abort(403, 'Unauthorized access to this location manifest.');
        }

        // Generate 30-day secure temporary signed scan URL
        $signedUrl = URL::temporarySignedRoute(
            'stn.scan', 
            now()->addDays(30), 
            ['transferRequisition' => $requisition->id]
        );

        $qrCodeSvg = QrCode::size(120)->generate($signedUrl);

        return view('pdf.stn-manifest', [
            'requisition' => $requisition->load('items.variant', 'fromWarehouse', 'toWarehouse', 'requestedBy'),
            'qrCode' => $qrCodeSvg,
        ]);
    }

    /**
     * Prints the Instant Direct Transfer Manifest.
     */
    public function printDirectTransfer(Request $request, StockMovement $movement)
    {
        abort_unless($movement->type === 'transfer_out', 404);

        $user = auth()->user();
        $inLeg = StockMovement::where('related_movement_id', $movement->id)->firstOrFail();

        // Enforce RBAC physical scope
        if (!$user->canAccessWarehouse($movement->warehouse) && !$user->canAccessWarehouse($inLeg->warehouse)) {
            abort(403);
        }

        return view('pdf.direct-transfer-manifest', [
            'outLeg' => $movement->load('variant', 'warehouse', 'creator'),
            'inLeg' => $inLeg->load('warehouse'),
        ]);
    }
}
```

---

### 2. Clinical-Print Requisition Layout (`resources/views/pdf/stn-manifest.blade.php`)
```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>STN MANIFEST - {{ $requisition->reference_code }}</title>
    <style>
        body { font-family: monospace; color: #18181b; padding: 20px; line-height: 1.4; }
        .header-grid { display: grid; grid-template-columns: 1fr 120px; border-bottom: 2px solid #000; padding-bottom: 15px; }
        .manifest-title { font-size: 20px; font-weight: bold; text-transform: uppercase; margin: 0; }
        .meta-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .meta-table td { padding: 4px 8px; font-size: 11px; vertical-align: top; }
        .item-table { width: 100%; border-collapse: collapse; margin: 25px 0; }
        .item-table th, .item-table td { border: 1px solid #000; padding: 8px; font-size: 11px; }
        .item-table th { background-color: #fafafa; text-align: left; text-transform: uppercase; }
        .signature-section { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 50px; }
        .sig-box { border-top: 1px solid #000; padding-top: 8px; font-size: 10px; text-transform: uppercase; }
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="header-grid">
        <div>
            <h1 class="manifest-title">STOCK TRANSFER NOTE (STN)</h1>
            <p style="margin: 5px 0; font-size: 11px; font-weight: bold;">DOC REF: {{ $requisition->reference_code }}</p>
        </div>
        <div style="text-align: right;">
            {!! $qrCode !!}
        </div>
    </div>

    <table class="meta-table">
        <tr>
            <td><strong>ORIGIN (FROM):</strong><br>{{ $requisition->fromWarehouse->name }} ({{ $requisition->fromWarehouse->code }})</td>
            <td><strong>RECEIVING (TO):</strong><br>{{ $requisition->toWarehouse->name }} ({{ $requisition->toWarehouse->code }})</td>
        </tr>
        <tr>
            <td><strong>PREPARED BY:</strong> {{ $requisition->requestedBy->name }}</td>
            <td><strong>STATUS:</strong> {{ strtoupper($requisition->status) }}</td>
        </tr>
        <tr>
            <td><strong>DISPATCHED ON:</strong> {{ $requisition->dispatched_at ?? 'PENDING' }}</td>
            <td><strong>PRINTED ON:</strong> {{ now()->toDateTimeString() }}</td>
        </tr>
    </table>

    <table class="item-table">
        <thead>
            <tr>
                <th>Item SKU</th>
                <th>Description</th>
                <th>Proposed Substitutes</th>
                <th>Format</th>
                <th>Ratio</th>
                <th style="text-align: right;">Approved Qty</th>
                <th style="text-align: right;">Total Base Pcs</th>
            </tr>
        </thead>
        <tbody>
            @foreach($requisition->items as $item)
                <tr>
                    <td style="font-weight: bold;">{{ $item->variant->sku }}</td>
                    <td>{{ $item->variant->name }}</td>
                    <td style="color: #ea580c;">{{ $item->substituteVariant->sku ?? 'NONE' }}</td>
                    <td>{{ $item->approved_unit_name ?? $item->requested_unit_name }}</td>
                    <td style="text-align: right;">{{ $item->approved_unit_ratio ?? $item->requested_unit_ratio }}</td>
                    <td style="text-align: right;">{{ $item->approved_qty ?? $item->requested_qty }}</td>
                    <td style="text-align: right; font-weight: bold;">{{ $item->approved_base_qty ?? $item->requested_base_qty }} Pcs</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="signature-section">
        <div class="sig-box">
            DISPATCHING MANAGER SIGNATURE<br>
            DATE: _______________________
        </div>
        <div class="sig-box">
            RECEIVING MANAGER SIGNATURE<br>
            DATE: _______________________
        </div>
    </div>

    <div style="margin-top: 40px; border-top: 1px dotted #ccc; padding-top: 10px; font-size: 9px; color: #71717a; text-align: center;">
        Note: The signature box serves as physical backup documentation only. The digital scan or manual confirm actions within the admin system represent the definitive system of record.
    </div>
</body>
</html>
```

---

## 🎨 Part 6: Clinical Dark Mode Styling Stylesheet

```css
/* Sidebar Navigation Group Titles */
.fi-sidebar-group-label {
    font-size: 0.75rem !important;
    font-weight: 500 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05em !important;
    color: #71717a !important; /* Muted Zinc */
}

/* Base Sidebar Buttons */
.fi-sidebar-item-button {
    font-size: 0.875rem !important;
    color: #18181b !important; /* Charcoal */
    transition: all 200ms ease-in-out !important;
}

.fi-sidebar-item-button:hover {
    background-color: #fafafa !important; /* Whisper Gray hover state only */
}

/* Selected Accent Styles */
.fi-sidebar-item-active .fi-sidebar-item-button {
    color: #3b82f6 !important; /* Clinical Blue */
    font-weight: 600 !important;
    border-left: 3px solid #3b82f6 !important;
    background-color: #dbeafe !important;
}

/* Dark Mode Overrides */
.dark .fi-sidebar {
    background-color: #0f0f10 !important; /* High contrast ink black */
    border-right: 1px solid #18181b !important;
}

.dark .fi-sidebar-item-button {
    color: #f8fafc !important;
}

.dark .fi-sidebar-item-button:hover {
    background-color: rgba(255, 255, 255, 0.02) !important;
}
```
