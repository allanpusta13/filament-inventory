# Master Sidebar Navigation & Modular Resource Map (v3.0) — Schema Realigned & Consolidated Edition

This master design blueprint establishes the definitive front-end layout, database-access policies, and complete UI-to-service contracts for **Filament Inventory (v3.0)**. 

It synthesizes all architectural guardrails with our latest schema realignment: **`sku` exists exclusively on `product_variants`**, **`reorder_point` is managed per variant**, **standalone price override tables are removed in favor of variant-level defaults**, and **all foreign keys follow explicit table-bound names** (`product_variant_id`, `transfer_requisition_id`, `transfer_requisition_item_id`).

---

## 🗺️ Part 1: Unified Navigation Architecture & RBAC Policies

The sidebar is structured into four uppercase-styled groups designed for speed and clarity in active warehouse operations. Security and data filtering are enforced dynamically at the database query-policy level using our casted **`UserRole` Enum**:

| Group Name | Resource | Base Route | Role Visibility | Sort Order | Eager-Loaded Relations (N+1 Guard) |
| :--- | :--- | :--- | :--- | :---: | :--- |
| **[Home]** | Dashboard | `/admin` | *All Roles* | — | — |
| **CATALOG** | `ProductResource` | `/admin/products` | *All Roles* | 1 | `variants`, `variants.unitConversions` |
| **OPERATIONS** | `TransferRequisitionResource` | `/admin/transfer-requisitions` | *All Roles* | 1 | `fromWarehouse`, `toWarehouse`, `requestedBy`, `items.productVariant` |
| | `DirectTransferResource` | `/admin/direct-transfers` | *All Roles* | 2 | `productVariant`, `warehouse`, `relatedMovement`, `creator` |
| **AUDIT LEDGERS** | `InTransitResource` | `/admin/in-transits` | Admin, Auditor, Branch Manager | 1 | `transferRequisition.fromWarehouse`, `transferRequisition.toWarehouse`, `productVariant` |
| | `StockMovementResource` | `/admin/stock-movements` | Admin, Auditor, Branch Manager | 2 | `productVariant`, `warehouse`, `creator` |
| | `LossLedgerResource` | `/admin/loss-ledgers` | Admin, Auditor | 3 | `transferRequisition`, `productVariant`, `warehouse` |
| **SYSTEM ADMIN** | `WarehouseResource` | `/admin/warehouses` | Admin Only | 1 | `users` |
| | `UserResource` | `/admin/users` | Admin Only | 2 | `warehouses` |

### 🔑 UserRole Enum (`app/Enums/UserRole.php`)
```php
namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum UserRole: string implements HasLabel, HasColor, HasIcon
{
    case Admin = 'admin';
    case Auditor = 'auditor';
    case BranchManager = 'branch_manager';
    case WarehouseStaff = 'warehouse_staff';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Admin => __('system.role_admin'),
            self::Auditor => __('system.role_auditor'),
            self::BranchManager => __('system.role_branch_manager'),
            self::WarehouseStaff => __('system.role_warehouse_staff'),
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Auditor => 'info',
            self::BranchManager => 'warning',
            self::WarehouseStaff => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Admin => Heroicon::ShieldCheck,
            self::Auditor => Heroicon::MagnifyingGlass,
            self::BranchManager => Heroicon::User,
            self::WarehouseStaff => Heroicon::IdentificationCard,
        };
    }
}
```

---

## 📁 Part 2: Complete Modular Directory Tree

Following Filament v5 design patterns, form schemas, table definitions, infolists, and relation managers are decoupled into isolated single-responsibility classes under `app/Filament/Resources/`. Standalone `PricesRelationManager` has been removed as pricing is consolidated directly on product variants:

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
│       │   │   └── ProductForm.php        # v5 Schema configure() class (Family Name/Category)
│       │   ├── Tables/
│       │   │   └── ProductsTable.php      # High-density catalog grid
│       │   └── RelationManagers/
│       │       ├── VariantsRelationManager.php   # SKU, GTIN, Prices (Cost/Sale), Reorder Point
│       │       └── ConversionsRelationManager.php # Packaging unit translator
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
│   ├── ProductVariant.php                     # Pure derived query calculations + SKU + Pricing + Reorder Point
│   ├── ProductVariantUnitConversion.php
│   ├── Warehouse.php
│   ├── StockMovement.php                      # Single transaction source of truth
│   ├── TransferRequisition.php
│   ├── TransferRequisitionItem.php
│   ├── TransferRequisitionItemRevision.php
│   ├── InTransit.php
│   ├── LossLedger.php
│   └── User.php
└── Services/
    ├── InventoryService.php                   # Atomic database transaction blocks
    └── LossLedgerService.php                  # Snapshot write-off recording
```

---

## 🛠️ Part 3: Model-Level Pure Derived Stock of Truth

Under our **Pure Derived Stock of Truth Model**, physical stocks and active transit allocations are dynamically computed at query-time on `ProductVariant` using realigned foreign keys:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\StockMovement;
use App\Models\TransferRequisitionItem;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'sku', 'barcode', 'name', 'base_unit_name',
        'cost_price', 'sale_price', 'reorder_point', 'attributes', 'images', 'is_active',
    ];

    protected $casts = [
        'cost_price' => 'decimal:4',
        'sale_price' => 'decimal:4',
        'attributes' => 'array',
        'images' => 'array',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unitConversions(): HasMany
    {
        return $this->hasMany(ProductVariantUnitConversion::class);
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
     * Physical on-hand stock: The exact SUM of all signed physical logs.
     */
    public function onHandQuantity(int $warehouseId): int
    {
        return StockMovement::where('product_variant_id', $this->id)
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');
    }

    /**
     * Active Requisition Reservations: The SUM of items locked in confirmed/dispatched transfers.
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

## 🧱 Part 4: Technical Specifications for Resource Directories

### 1. Catalog Domain: Products (`Products/`)

#### 📝 Form Schema (`Schemas/ProductForm.php`)
```php
namespace App\Filament\Resources\Products\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(fn () => Str::upper(__('catalog.product_family_profile')))
                ->icon(Heroicon::ClipboardDocumentList)
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label(fn () => Str::upper(__('catalog.family_name')))
                        ->required()
                        ->placeholder('Arabica Specialty Coffee'),
                        
                    TextInput::make('category')
                        ->label(fn () => Str::upper(__('catalog.category')))
                        ->placeholder('Beans'),
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
use Filament\Tables\Actions\EditAction;
use Filament\Support\Icons\Heroicon;

class ProductsTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('PRODUCT FAMILY NAME')
                    ->searchable()
                    ->sortable()
                    ->bold(),
                TextColumn::make('category')
                    ->label('CATEGORY')
                    ->badge()
                    ->color('gray')
                    ->icon(Heroicon::Tag),
                TextColumn::make('variants_count')
                    ->counts('variants')
                    ->label('VARIANTS')
                    ->alignRight(),
            ])
            ->actions([
                EditAction::make()
                    ->slideOver()
                    ->icon(Heroicon::PencilSquare),
            ]);
    }
}
```

#### 🔗 Variants Relation Manager (`RelationManagers/VariantsRelationManager.php`)
```php
namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Support\Icons\Heroicon;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';
    protected static ?string $recordTitleAttribute = 'sku';

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('sku')
                ->label('VARIANT SKU')
                ->required()
                ->unique(ignorable: fn ($record) => $record)
                ->placeholder('PROD-COF-500G')
                ->icon(Heroicon::QrCode),

            TextInput::make('barcode')
                ->label('BARCODE / GTIN')
                ->unique(ignorable: fn ($record) => $record)
                ->placeholder('4800123456789'),

            TextInput::make('name')
                ->label('VARIANT IDENTIFIER NAME')
                ->required()
                ->placeholder('500g Whole Bean'),

            TextInput::make('base_unit_name')
                ->label('BASE UNIT NAME')
                ->required()
                ->placeholder('gram'),

            TextInput::make('cost_price')
                ->label('COST PRICE PER BASE UNIT')
                ->numeric()
                ->required()
                ->default(0.0000)
                ->step('0.0001')
                ->icon(Heroicon::CurrencyDollar),

            TextInput::make('sale_price')
                ->label('SALE PRICE PER BASE UNIT')
                ->numeric()
                ->required()
                ->default(0.0000)
                ->step('0.0001')
                ->icon(Heroicon::CurrencyDollar),

            TextInput::make('reorder_point')
                ->label('SAFETY REORDER POINT (BASE UNITS)')
                ->numeric()
                ->default(0)
                ->required(),

            KeyValue::make('attributes')
                ->label('ATTRIBUTES DICTIONARY'),

            FileUpload::make('images')
                ->label('VARIANT IMAGES')
                ->multiple()
                ->directory('variant-images')
                ->json(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')->label('SKU')->bold()->searchable(),
                TextColumn::make('name')->label('VARIANT NAME')->searchable(),
                TextColumn::make('base_unit_name')->label('BASE UNIT'),
                TextColumn::make('cost_price')
                    ->label('COST PRICE')
                    ->state(fn ($record) => '$' . number_format($record->cost_price, 4))
                    ->alignRight(),
                TextColumn::make('sale_price')
                    ->label('SALE PRICE')
                    ->state(fn ($record) => '$' . number_format($record->sale_price, 4))
                    ->alignRight(),
                TextColumn::make('reorder_point')->label('REORDER POINT')->numeric()->alignRight(),
            ])
            ->actions([
                EditAction::make()->modalWidth('2xl')->icon(Heroicon::PencilSquare),
                DeleteAction::make()->icon(Heroicon::Trash),
            ])
            ->headerActions([
                CreateAction::make()->label('NEW VARIANT')->modalWidth('2xl')->icon(Heroicon::Plus),
            ]);
    }
}
```

---

### 2. Operations Domain: Transfer Requisitions (`TransferRequisitions/`)

#### 📝 3-Step Wizard Form Schema (`Schemas/TransferRequisitionForm.php`)
```php
namespace App\Filament\Resources\TransferRequisitions\Schemas;

use Filament\Schemas\Schema;
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
                ->relationship()
                ->schema([
                    Select::make('product_variant_id')
                        ->label('PRODUCT VARIANT')
                        ->relationship('productVariant', 'sku')
                        ->required()
                        ->searchable()
                        ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                    TextInput::make('requested_unit_name')
                        ->label('PACKAGING FORMAT')
                        ->required()
                        ->placeholder('Box'),

                    TextInput::make('requested_unit_ratio')
                        ->label('UNIT RATIO')
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
                        return 'Complete previous steps to review the manifest.';
                    }

                    $fromName = \App\Models\Warehouse::find($fromId)?->name ?? 'Unknown';
                    $toName = \App\Models\Warehouse::find($toId)?->name ?? 'Unknown';

                    $rowsHtml = '';
                    foreach ($items as $item) {
                        $sku = ProductVariant::find($item['product_variant_id'])?->sku ?? 'Unknown';
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
                                <div><span class='text-[10px] uppercase font-semibold text-zinc-500'>Origin</span><p class='text-sm font-semibold'>{$fromName}</p></div>
                                <div><span class='text-[10px] uppercase font-semibold text-zinc-500'>Destination</span><p class='text-sm font-semibold'>{$toName}</p></div>
                            </div>
                            <table class='w-full text-left'>
                                <thead><tr class='border-b border-zinc-300'><th class='pb-2 text-[10px] uppercase font-semibold text-zinc-500'>SKU</th><th class='pb-2 text-[10px] uppercase font-semibold text-zinc-500'>Format</th><th class='pb-2 text-[10px] uppercase font-semibold text-zinc-500 text-right'>Ratio</th><th class='pb-2 text-[10px] uppercase font-semibold text-zinc-500 text-right'>Qty</th><th class='pb-2 text-[10px] uppercase font-semibold text-zinc-500 text-right'>Total Base</th></tr></thead>
                                <tbody>{$rowsHtml}</tbody>
                            </table>
                        </div>
                    ");
                }),
        ];
    }
}
```

---

### 3. Operations Domain: Direct Transfers (`DirectTransfers/`)

#### 📝 3-Step Wizard Form Schema (`Schemas/DirectTransferForm.php`)
```php
namespace App\Filament\Resources\DirectTransfers\Schemas;

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
                ->label('ORIGIN BRANCH')
                ->relationship('fromWarehouse', 'name', fn ($q) => $q->where('is_active', true))
                ->required()
                ->searchable(),

            Select::make('to_warehouse_id')
                ->label('DESTINATION BRANCH')
                ->relationship('toWarehouse', 'name', fn ($q) => $q->where('is_active', true))
                ->required()
                ->searchable()
                ->different('from_warehouse_id'),
        ];
    }

    public static function getAllocationSchema(): array
    {
        return [
            Select::make('product_variant_id')
                ->label('PRODUCT VARIANT')
                ->relationship('productVariant', 'sku')
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
                ->minLength(15)
                ->regex('/^(?!(.)\1+$)(?!\b(test|dummy|notes|adjust|none)\b)/i')
                ->placeholder('Specify reason for off-schedule transfer...'),
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
                    $variantId = $get('product_variant_id');
                    $qty = $get('quantity');
                    $notes = $get('notes');

                    if (!$fromId || !$toId || !$variantId || !$qty) {
                        return 'Complete previous steps to construct review.';
                    }

                    $fromName = \App\Models\Warehouse::find($fromId)?->name ?? 'Unknown';
                    $toName = \App\Models\Warehouse::find($toId)?->name ?? 'Unknown';
                    $sku = ProductVariant::find($variantId)?->sku ?? 'Unknown';

                    return new \Illuminate\Support\HtmlString("
                        <div class='rounded-lg bg-zinc-50 p-4 border border-zinc-200'>
                            <p class='text-sm text-zinc-800'>Transferring <strong>{$qty} Base Units</strong> of SKU: <strong class='text-blue-600'>{$sku}</strong> from <strong>{$fromName}</strong> to <strong>{$toName}</strong>.</p>
                            <p class='text-xs italic text-zinc-600 mt-2'>"{$notes}"</p>
                        </div>
                    ");
                }),
        ];
    }
}
```

---

### 4. Audit Ledgers Domain: In-Transit Monitor (`InTransits/`)

#### 📊 Table Schema (`Tables/InTransitsTable.php`)
```php
namespace App\Filament\Resources\InTransits\Tables;

use App\Services\InventoryService;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class InTransitsTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transferRequisition.reference_code')->label('STN CODE')->bold()->searchable(),
                TextColumn::make('transferRequisition.fromWarehouse.name')->label('FROM'),
                TextColumn::make('transferRequisition.toWarehouse.name')->label('TO'),
                TextColumn::make('productVariant.sku')->label('VARIANT SKU')->searchable(),
                TextColumn::make('dispatched_base_qty')->label('IN-TRANSIT QTY')->numeric()->alignRight(),
                TextColumn::make('dispatched_at')->label('DEPARTED ON')->dateTime()->sortable(),
                TextColumn::make('created_at')
                    ->label('TRANSIT AGE')
                    ->state(fn ($record) => now()->diffInHours($record->dispatched_at) . ' Hrs')
                    ->badge()
                    ->color('warning'),
            ])
            ->actions([
                Action::make('confirmReceipt')
                    ->label('CONFIRM RECEIPT')
                    ->icon(Heroicon::CheckCircle)
                    ->modalWidth('2xl')
                    ->closeModalByClickingAway(false)
                    ->form(function ($record) {
                        return [
                            Repeater::make('items')
                                ->schema([
                                    TextInput::make('item_id')->hidden(),
                                    TextInput::make('variant_sku')->label('SKU')->disabled(),
                                    TextInput::make('received_qty')->label('RECEIVED BASE QTY')->numeric()->required(),
                                ])
                                ->default(
                                    $record->transferRequisition->items->map(fn ($i) => [
                                        'item_id' => $i->id,
                                        'variant_sku' => $i->productVariant->sku,
                                        'received_qty' => $i->approved_base_qty,
                                    ])->toArray()
                                ),
                        ];
                    })
                    ->action(function ($record, array $data, InventoryService $service) {
                        $receivedQuantities = collect($data['items'])
                            ->mapWithKeys(fn ($row) => [$row['item_id'] => (int) $row['received_qty']])
                            ->toArray();

                        $service->receiveRequisition($record->transferRequisition, $receivedQuantities, receivedBy: auth()->id());

                        Notification::make()->title('Receipt confirmed.')->success()->send();
                    }),
            ]);
    }
}
```

---

### 5. Audit Ledgers Domain: Financial Loss Ledger (`LossLedgers/`)

#### 📊 Table Schema (`Tables/LossLedgersTable.php`)
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
                TextColumn::make('transferRequisition.reference_code')->label('STN CODE')->bold()->searchable(),
                TextColumn::make('productVariant.sku')->label('DISCREPANT SKU')->searchable(),
                TextColumn::make('warehouse.name')->label('SITE BEARING LOSS')->sortable(),
                TextColumn::make('lost_base_qty')->label('LOST UNITS')->numeric()->alignRight(),
                TextColumn::make('damaged_base_qty')->label('DAMAGED UNITS')->numeric()->alignRight(),
                TextColumn::make('unit_cost_price')
                    ->label('UNIT COST')
                    ->state(fn ($record) => '$' . number_format($record->unit_cost_price, 4))
                    ->alignRight(),
                TextColumn::make('total_financial_loss')
                    ->label('TOTAL LOSS VALUE')
                    ->state(fn ($record) => '$' . number_format($record->total_financial_loss, 4))
                    ->summarize(
                        Sum::make()->label('TOTAL WRITE-OFFS')->formatStateUsing(fn ($state) => '$' . number_format($state, 4))
                    )
                    ->bold()
                    ->alignRight(),
                TextColumn::make('loss_category')->label('CLASSIFICATION')->badge()->color('danger'),
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
    protected static string $relationship = 'stockMovements';
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
                TextColumn::make('sku')->label('VARIANT SKU')->searchable()->bold(),
                TextColumn::make('name')->label('VARIANT NAME'),
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

### Print Controller Router (`app/Http/Controllers/STNManifestController.php`)
```php
namespace App\Http\Controllers;

use App\Models\TransferRequisition;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class STNManifestController extends Controller
{
    public function print(Request $request, TransferRequisition $requisition)
    {
        $user = auth()->user();
        
        if (!$user->canAccessWarehouse($requisition->toWarehouse) && !$user->canAccessWarehouse($requisition->fromWarehouse)) {
            abort(403, 'Unauthorized access to this manifest.');
        }

        $signedUrl = URL::temporarySignedRoute(
            'stn.scan', 
            now()->addDays(30), 
            ['transferRequisition' => $requisition->id]
        );

        $qrCodeSvg = QrCode::size(120)->generate($signedUrl);

        return view('pdf.stn-manifest', [
            'requisition' => $requisition->load('items.productVariant', 'fromWarehouse', 'toWarehouse', 'requestedBy'),
            'qrCode' => $qrCodeSvg,
        ]);
    }

    public function printDirectTransfer(Request $request, StockMovement $movement)
    {
        abort_unless($movement->type === 'transfer_out', 404);

        $user = auth()->user();
        $inLeg = StockMovement::where('related_movement_id', $movement->id)->firstOrFail();

        if (!$user->canAccessWarehouse($movement->warehouse) && !$user->canAccessWarehouse($inLeg->warehouse)) {
            abort(403);
        }

        return view('pdf.direct-transfer-manifest', [
            'outLeg' => $movement->load('productVariant', 'warehouse', 'creator'),
            'inLeg' => $inLeg->load('warehouse'),
        ]);
    }
}
```
