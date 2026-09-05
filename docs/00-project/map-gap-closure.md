# Gap Closure — master-sidebar-resource-map-v5.md

Resolves the 6 blocking/major defects found in council review. Decisions locked:
- Stock of truth: **pure derived** (SUM of stock_movements). No stored WarehouseStock table.
- Roles: **4 canonical roles** — admin, auditor, branch_manager, warehouse_staff.

---

## Fix 1 — Filament v5 Schema API (was: v4 `Form` syntax)

Every `Schemas/*Form.php` file in the map uses the wrong container. Replace throughout:

**Before (v4, broken on v5):**
```php
use Filament\Forms\Form;

class ProductForm
{
    public static function make(Form $form): Form
    {
        return $form->schema([...]);
    }
}
```

**After (v5, correct):**
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
                        ->placeholder('PROD-COF-001'),
                    TextInput::make('name')
                        ->label('PRODUCT FAMILY NAME')
                        ->required(),
                    TextInput::make('category')
                        ->label('CATEGORY'),
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

**Apply this same swap** (`Form` → `Schema`, `->schema()` on Form → `->components()` on Schema, `Card::make()` → `Section::make()`, method renamed `make()` → `configure()`) to every one of: `ProductForm`, `WarehouseForm`, `UserForm`, and any RelationManager's `form()` method. The resource classes then call it as:

```php
public static function form(Schema $schema): Schema
{
    return ProductForm::configure($schema);
}
```

---

## Fix 2 — Stock of Truth: Pure Derived (drop WarehouseStock)

**Remove:** the `WarehouseStock` model, migration, and `WarehouseStocksRelationManager`'s dependency on stored `on_hand_quantity`/`reserved_quantity` columns.

**Reserved quantity is redefined as computed, not stored** — it's the sum of base quantities tied up in requisitions that are approved/confirmed/dispatched but not yet completed or cancelled:

```php
// app/Models/ProductVariant.php
public function onHandQuantity(int $warehouseId): int
{
    return StockMovement::where('variant_id', $this->id)
        ->where('warehouse_id', $warehouseId)
        ->sum('quantity');
}

public function reservedQuantity(int $warehouseId): int
{
    return $this->requisitionItems()
        ->whereHas('requisition', function ($q) use ($warehouseId) {
            $q->where('from_warehouse_id', $warehouseId)
              ->whereIn('status', ['confirmed', 'dispatched']);
        })
        ->sum('approved_base_qty');
}

public function availableQuantity(int $warehouseId): int
{
    return $this->onHandQuantity($warehouseId) - $this->reservedQuantity($warehouseId);
}
```

**Rewrite `WarehouseStocksRelationManager`** as a table built on a query, not a real relationship:

```php
namespace App\Filament\Resources\Warehouses\RelationManagers;

use App\Models\ProductVariant;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class WarehouseStocksRelationManager extends RelationManager
{
    protected static string $relationship = 'stockMovements'; // hasMany StockMovement, used only to scope the panel

    public function table(Table $table): Table
    {
        $warehouseId = $this->getOwnerRecord()->id;

        return $table
            ->query(
                ProductVariant::query()
                    ->whereHas('stockMovements', fn (Builder $q) => $q->where('warehouse_id', $warehouseId))
            )
            ->columns([
                TextColumn::make('sku')->label('PRODUCT SKU')->searchable(),
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

No migration needed for this fix — it removes a table rather than adding one.

---

## Fix 3 — Canonical `InventoryService`

One service, one set of method signatures. Every wizard/action in the map calls into this exact contract:

```php
namespace App\Services;

use App\Models\StockMovement;
use App\Models\TransferRequisition;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Core primitive. Every stock change — receive, ship, transfer leg,
     * adjustment — goes through this. Quantity is in BASE units, signed.
     */
    public function recordMovement(
        int $variantId,
        int $warehouseId,
        string $type,               // receive | ship | transfer_in | transfer_out | adjustment | loss
        int $baseQuantity,          // signed: + in, - out
        ?int $relatedMovementId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $referenceCode = null,
    ): StockMovement {
        return StockMovement::create([
            'variant_id' => $variantId,
            'warehouse_id' => $warehouseId,
            'type' => $type,
            'quantity' => $baseQuantity,
            'related_movement_id' => $relatedMovementId,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reference_code' => $referenceCode,
            'created_by' => auth()->id(),
        ]);
    }

    public function currentQuantity(int $variantId, int $warehouseId): int
    {
        return StockMovement::where('variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');
    }

    /**
     * Instant, unconditional two-leg transfer (used by DirectTransferResource).
     */
    public function executeDirectTransfer(
        int $variantId,
        int $fromWarehouseId,
        int $toWarehouseId,
        int $qty,               // positive, in the given packaging unit
        string $unitName,
        int $unitRatio,
        ?string $notes = null,
    ): void {
        DB::transaction(function () use ($variantId, $fromWarehouseId, $toWarehouseId, $qty, $unitRatio, $notes) {
            $baseQty = $qty * $unitRatio;
            $current = $this->currentQuantity($variantId, $fromWarehouseId);

            if ($current < $baseQty) {
                throw new \Exception("Insufficient stock: only {$current} base units available at origin.");
            }

            $referenceCode = 'DTR-' . date('Ymd') . '-' . strtoupper(uniqid());

            $out = $this->recordMovement(
                $variantId, $fromWarehouseId, 'transfer_out', -$baseQty,
                referenceCode: $referenceCode
            );

            $this->recordMovement(
                $variantId, $toWarehouseId, 'transfer_in', $baseQty,
                relatedMovementId: $out->id,
                referenceCode: $referenceCode
            );
        });
    }

    /**
     * Dispatch leg of a TransferRequisition: moves stock OUT of origin only.
     * Destination receives nothing yet — it's in transit.
     */
    public function dispatchRequisition(TransferRequisition $requisition): void
    {
        DB::transaction(function () use ($requisition) {
            foreach ($requisition->items as $item) {
                $this->recordMovement(
                    $item->variant_id,
                    $requisition->from_warehouse_id,
                    'transfer_out',
                    -$item->approved_base_qty,
                    referenceType: TransferRequisition::class,
                    referenceId: $requisition->id,
                    referenceCode: $requisition->reference_code,
                );
            }
            $requisition->update(['status' => 'dispatched', 'dispatched_at' => now(), 'dispatched_by' => auth()->id()]);
        });
    }

    /**
     * Closes the loop In-Transit resolves to: moves stock IN at destination.
     * If received_base_qty < approved_base_qty, the shortfall is logged to LossLedger.
     */
    public function receiveRequisition(TransferRequisition $requisition, array $receivedQuantities): void
    {
        DB::transaction(function () use ($requisition, $receivedQuantities) {
            $hasLoss = false;

            foreach ($requisition->items as $item) {
                $received = $receivedQuantities[$item->id] ?? $item->approved_base_qty;

                $this->recordMovement(
                    $item->variant_id,
                    $requisition->to_warehouse_id,
                    'transfer_in',
                    $received,
                    referenceType: TransferRequisition::class,
                    referenceId: $requisition->id,
                    referenceCode: $requisition->reference_code,
                );

                $shortfall = $item->approved_base_qty - $received;
                if ($shortfall > 0) {
                    $hasLoss = true;
                    app(LossLedgerService::class)->record($requisition, $item, $shortfall);
                }
            }

            $requisition->update([
                'status' => $hasLoss ? 'closed_with_loss' : 'completed',
                'completed_at' => now(),
            ]);
        });
    }

    public function ship(int $variantId, int $warehouseId, int $baseQuantity, ?string $referenceCode = null): StockMovement
    {
        return DB::transaction(function () use ($variantId, $warehouseId, $baseQuantity, $referenceCode) {
            $current = $this->currentQuantity($variantId, $warehouseId);
            if ($current < $baseQuantity) {
                throw new \Exception("Insufficient stock: only {$current} available.");
            }
            return $this->recordMovement($variantId, $warehouseId, 'ship', -$baseQuantity, referenceCode: $referenceCode);
        });
    }
}
```

This resolves the mismatch: `manualStockAdjustment` should call `recordMovement()` directly (it already does, now matching this signature), and the Direct Transfer wizard calls `executeDirectTransfer()` (now actually defined). Requisition dispatch/receive are new methods that close Fix 6 below.

---

## Fix 4 — `LossLedger` Model, Migration, and Service

**Migration:**
```php
Schema::create('loss_ledgers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('requisition_id')->constrained('transfer_requisitions');
    $table->foreignId('variant_id')->constrained('product_variants');
    $table->foreignId('warehouse_id')->constrained('warehouses'); // site bearing the loss = destination
    $table->unsignedBigInteger('lost_base_qty');
    $table->unsignedBigInteger('damaged_base_qty')->default(0);
    $table->decimal('unit_cost_price', 12, 4);
    $table->decimal('total_financial_loss', 14, 4);
    $table->string('loss_category')->default('shortfall'); // shortfall | damaged | write_off
    $table->timestamps();
});
```

**Model:**
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LossLedger extends Model
{
    protected $fillable = [
        'requisition_id', 'variant_id', 'warehouse_id',
        'lost_base_qty', 'damaged_base_qty', 'unit_cost_price',
        'total_financial_loss', 'loss_category',
    ];

    public function requisition() { return $this->belongsTo(TransferRequisition::class, 'requisition_id'); }
    public function variant() { return $this->belongsTo(ProductVariant::class, 'variant_id'); }
    public function warehouse() { return $this->belongsTo(Warehouse::class, 'warehouse_id'); }
}
```

**Service that populates it** (called from `InventoryService::receiveRequisition()` above):
```php
namespace App\Services;

use App\Models\LossLedger;
use App\Models\TransferRequisition;
use App\Models\RequisitionItem;

class LossLedgerService
{
    public function record(TransferRequisition $requisition, RequisitionItem $item, int $shortfallBaseQty): LossLedger
    {
        $costPrice = $item->variant->cost_price; // per base unit

        return LossLedger::create([
            'requisition_id' => $requisition->id,
            'variant_id' => $item->variant_id,
            'warehouse_id' => $requisition->to_warehouse_id,
            'lost_base_qty' => $shortfallBaseQty,
            'damaged_base_qty' => 0, // set explicitly if a damage-inspection step is added later
            'unit_cost_price' => $costPrice,
            'total_financial_loss' => $shortfallBaseQty * $costPrice,
            'loss_category' => 'shortfall',
        ]);
    }
}
```

The `LossLedgersTable` from the original map now has a real data source — no changes needed to that file.

---

## Fix 5 — Canonical RBAC (4 roles, one definition, everywhere)

**Locked role list:** `admin`, `auditor`, `branch_manager`, `warehouse_staff`

**Migration:**
```php
Schema::table('users', function (Blueprint $table) {
    $table->string('role')->default('warehouse_staff');
});
```

**Enum:**
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

**User model additions:**
```php
use App\Enums\UserRole;

protected $casts = ['role' => UserRole::class];

public function warehouses() { return $this->belongsToMany(Warehouse::class, 'user_warehouse'); }

public function isAdmin(): bool { return $this->role === UserRole::Admin; }
public function isAuditor(): bool { return $this->role === UserRole::Auditor; }
public function isBranchManager(): bool { return $this->role === UserRole::BranchManager; }

public function canAccessWarehouse(Warehouse $warehouse): bool
{
    return $this->isAdmin() || $this->isAuditor() || $this->warehouses->contains($warehouse->id);
}
```

Auditor gets read access to all warehouses (matches the sidebar table's "Admin, Auditor, Manager" visibility on Audit Ledgers) without needing warehouse assignment. Branch Manager is scoped like warehouse_staff but with elevated action permissions (e.g., can approve requisitions — enforce via `canEdit()`/custom action `visible()` checks per resource, not shown here since it's resource-specific).

**Fix every file that hardcoded inconsistent labels:**
- `UsersRelationManager` badge `match()` → replace with `$state->badgeColor()` using the enum, or keep literal strings but make them exactly `admin`, `auditor`, `branch_manager`, `warehouse_staff` (drop `Manager` as a bare label, drop the missing `warehouse_staff` case from that file's match).
- `UserForm` select options → already matches this canonical list, no change needed.
- Sidebar table in Part 1 → update "Role Visibility" column for Audit Ledgers group to explicitly read "Admin, Auditor, Branch Manager" (drop ambiguous "Manager").

---

## Fix 6 — Close the In-Transit → Completed Loop

**Add a "Confirm Receipt" action** on `InTransitResource`'s table (the missing piece):

```php
// InTransits/Tables/InTransitsTable.php — add to ->actions([])
use App\Services\InventoryService;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

Action::make('confirmReceipt')
    ->label('CONFIRM RECEIPT')
    ->icon('heroicon-o-check-circle')
    ->modalWidth('2xl')
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

        $service->receiveRequisition($record->requisition, $receivedQuantities);

        Notification::make()
            ->title('Receipt confirmed')
            ->success()
            ->send();
    }),
```

This is the action that was missing entirely — without it, `dispatched` requisitions had no way to become `completed` or `closed_with_loss`.

---

## Summary: What's Now Buildable

| Original defect | Resolution |
|---|---|
| v4 Form syntax on v5 | All Schemas files use `Filament\Schemas\Schema` + `configure()` |
| WarehouseStock vs. derived SUM contradiction | Dropped stored table; `onHandQuantity()`/`reservedQuantity()`/`availableQuantity()` computed on `ProductVariant` |
| Undefined `InventoryService` | Full canonical service with 6 methods, consistent signatures matching every existing call site in the map |
| Missing `LossLedger` model/migration/service | Added, wired into `receiveRequisition()` |
| 3 inconsistent role lists | 1 canonical `UserRole` enum, 4 roles, used everywhere |
| No dispatched→completed path | `receiveRequisition()` + `confirmReceipt` action on `InTransitResource` |

The map's UI/table layer (columns, badges, filters, styling) needed no changes — the gaps were entirely in the layer beneath it: models, migrations, and the service contract the UI assumed existed. With these six fixes, every resource in the original map now has a real, consistent implementation path.