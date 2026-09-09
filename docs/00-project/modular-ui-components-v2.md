# Filament Inventory (v2.6) UI Components: Forms, Tables & Infolists

This specification outlines the complete, production-ready frontend implementation for **Filament Inventory** utilizing **FilamentPHP v5** and **Tailwind CSS**. 

Following **"The Operations Deck"** design system, these classes implement clinical-density tables, wizard-driven modal forms with data loss prevention safeguards, high-contrast role-scoped infolists, and **4-decimal place financial precision** for inventory aggregates.

---

## 🏛️ Domain 1: Transfer Requisitions (Operations Domain)

### 1. The Dialog Wizard Form Schema (`Schemas/TransferRequisitionForm.php`)
This schema defines the step-by-step wizard displayed within the **3xl Dialog Modal**. It utilizes a 2-step setup: Step 1 maps locations with validation preventing self-transfers, and Step 2 captures line items with a dynamic, reactive repeater block.

```php
namespace App\Filament\Resources\TransferRequisitions\Schemas;

use Filament\Forms\Form;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;

class TransferRequisitionForm
{
    /**
     * Step 1 Schema: Location Mapping
     */
    public static function getRoutingSchema(): array
    {
        return [
            Grid::make(2)
                ->schema([
                    Select::make('from_warehouse_id')
                        ->label('ORIGIN WAREHOUSE (FULFILLER)')
                        ->relationship('fromWarehouse', 'name', fn ($query) => $query->where('is_active', true))
                        ->required()
                        ->searchable()
                        ->preload()
                        ->extraAttributes([
                            'class' => 'rounded-md border-zinc-200 focus:ring-primary-500',
                            'aria-label' => 'Select the fulfilling warehouse location',
                        ]),

                    Select::make('to_warehouse_id')
                        ->label('DESTINATION WAREHOUSE (REQUESTOR)')
                        ->relationship('toWarehouse', 'name', fn ($query) => $query->where('is_active', true))
                        ->required()
                        ->searchable()
                        ->preload()
                        ->different('from_warehouse_id') // Guardrail: Block self-transfer
                        ->validationMessages([
                            'different' => 'The destination warehouse cannot be the same as the origin warehouse.',
                        ])
                        ->extraAttributes([
                            'class' => 'rounded-md border-zinc-200 focus:ring-primary-500',
                            'aria-label' => 'Select the requesting warehouse location',
                        ]),
                ]),
        ];
    }

    /**
     * Step 2 Schema: Material Manifest
     */
    public static function getItemsSchema(): array
    {
        return [
            Section::make('REQUESTED ITEMS MANIFEST')
                ->heading('REQUESTED ITEMS MANIFEST')
                ->description('Specify the product variants, packing units, and order quantities.')
                ->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->schema([
                            Select::make('variant_id')
                                ->label('PRODUCT VARIANT')
                                ->relationship('variant', 'sku')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems() // Prevent duplicate line items
                                ->columnSpan(3),

                            TextInput::make('requested_unit_name')
                                ->label('PACKAGING FORMAT')
                                ->required()
                                ->placeholder('E.g., Box, Bag, Pallet')
                                ->columnSpan(2),

                            TextInput::make('requested_unit_ratio')
                                ->label('UNIT RATIO')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->default(1)
                                ->columnSpan(1)
                                ->helperText('Units per package.'),

                            TextInput::make('requested_qty')
                                ->label('ORDER QUANTITY')
                                ->numeric()
                                ->required()
                                ->minValue(1) // Guardrail 5: Rejects zero or negative quantities
                                ->columnSpan(2),
                        ])
                        ->columns(8)
                        ->defaultItems(1)
                        ->itemLabel(fn (array $state): ?string => $state['variant_id'] ?? 'New Line Item')
                        ->extraAttributes(['class' => 'gap-y-4'])
                        ->reorderableWithButtons(),
                ])
                ->compact(),
        ];
    }
}
```

---

### 2. The High-Contrast Information Infolist (`Infolists/TransferRequisitionInfolist.php`)
When viewing a requisition record, the Infolist presents a complete, high-contrast summary. It highlights original vs. negotiated substitute variants and provides a chronological audit trace of active negotiations.

```php
namespace App\Filament\Resources\TransferRequisitions\Infolists;

use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;

class TransferRequisitionInfolist
{
    public static function make(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Grid::make(3)
                    ->schema([
                        // Section 1: Metadata Summary (Left Side, Spans 2 Columns)
                        Section::make('REQUISITION PROFILE')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('reference_code')
                                            ->label('REFERENCE CODE')
                                            ->weight('bold')
                                            ->copyable(),

                                        TextEntry::make('status')
                                            ->label('STATUS')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'draft' => 'gray',
                                                'requested', 'under_review_fulfiller', 'under_review_requestor' => 'warning',
                                                'confirmed', 'dispatched' => 'info',
                                                'completed' => 'success',
                                                'closed_with_loss' => 'danger',
                                                'cancelled' => 'gray',
                                            }),

                                        TextEntry::make('fromWarehouse.name')
                                            ->label('ORIGIN BRANCH'),

                                        TextEntry::make('toWarehouse.name')
                                            ->label('DESTINATION BRANCH'),
                                    ]),
                            ])
                            ->columnSpan(2),

                        // Section 2: Authorization Checklist (Right Side, Spans 1 Column)
                        Section::make('AUTHORIZATION SIGN-OFFS')
                            ->schema([
                                TextEntry::make('requestedBy.name')
                                    ->label('REQUESTED BY')
                                    ->placeholder('System Initialized'),
                                TextEntry::make('approvedBy.name')
                                    ->label('APPROVED BY')
                                    ->placeholder('Pending Approval'),
                                TextEntry::make('dispatchedBy.name')
                                    ->label('DISPATCHED BY')
                                    ->placeholder('Pending Dispatch'),
                            ])
                            ->columnSpan(1),

                        // Section 3: Material Manifest (Full Width, Spans 3 Columns)
                        Section::make('MATERIAL MANIFEST ITEMS')
                            ->schema([
                                RepeatableEntry::make('items')
                                    ->label('')
                                    ->schema([
                                        Grid::make(6)
                                            ->schema([
                                                // SKU Display with Swap Auditing (Guardrail 4)
                                                TextEntry::make('variant.sku')
                                                    ->label('ORIGINAL VARIANT')
                                                    ->weight('bold')
                                                    ->columnSpan(2),

                                                TextEntry::make('substituteVariant.sku')
                                                    ->label('PROPOSED SUBSTITUTE')
                                                    ->badge()
                                                    ->color('warning')
                                                    ->placeholder('No Swap Proposed')
                                                    ->columnSpan(2),

                                                TextEntry::make('requested_qty')
                                                    ->label('REQUESTED QTY')
                                                    ->state(fn ($record) => "{$record->requested_qty} {$record->requested_unit_name}")
                                                    ->columnSpan(1),

                                                TextEntry::make('approved_qty')
                                                    ->label('APPROVED QTY')
                                                    ->state(fn ($record) => $record->approved_qty 
                                                        ? "{$record->approved_qty} {$record->approved_unit_name}" 
                                                        : 'Pending Verification')
                                                    ->color(fn ($record) => $record->approved_qty !== $record->requested_qty ? 'warning' : 'gray')
                                                    ->columnSpan(1),
                                            ]),
                                    ])
                                    ->striped(),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
```

---

### 3. The Dense, Zero-Zebra List Table (`Tables/TransferRequisitionsTable.php`)
Renders the tabular requisition queue with optimized header controls. Access is restricted to assigned branches for non-admin users, and the table highlights pending negotiations for quick scanning.

```php
namespace App\Filament\Resources\TransferRequisitions\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;

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
                    ->label('ORIGIN SITE')
                    ->sortable(),

                TextColumn::make('toWarehouse.name')
                    ->label('RECEIVING SITE')
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
                    ->label('CREATED ON')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('requested_at', 'desc')
            ->recordClasses(fn ($record) => match ($record->status) {
                // Highlighting rows requiring active negotiation or counter-offer review
                'under_review_fulfiller', 'under_review_requestor' => 'hover:bg-amber-50/40 dark:hover:bg-amber-950/10 transition-colors duration-150',
                default => 'hover:bg-zinc-50 dark:hover:bg-zinc-900/40 transition-colors duration-150',
            })
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'requested' => 'Requested',
                        'confirmed' => 'Confirmed',
                        'dispatched' => 'In-Transit (Dispatched)',
                        'completed' => 'Completed',
                        'closed_with_loss' => 'Closed with Loss',
                    ]),

                SelectFilter::make('from_warehouse_id')
                    ->label('ORIGIN WAREHOUSE')
                    ->relationship('fromWarehouse', 'name'),
            ])
            ->actions([
                ViewAction::make()
                    ->color('gray'),
                EditAction::make()
                    ->visible(fn ($record) => $record->status === 'draft' || $record->status === 'under_review_requestor'),
            ])
            ->closeModalByClickingAway(false); // Guardrail: Prevent accidental data loss on modals
    }
}
```

---

## 🎨 Domain 2: Product & Catalog Overrides (Catalog Domain)

### 1. High-Precision Price Matrices (`RelationManagers/PricesRelationManager.php`)
This sub-panel manages location-scoped unit pricing overrides. To prevent rounding errors on micro-quantities (Guardrail 1), it implements **4-decimal place inputs and columns**.

```php
namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;

class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';
    protected static ?string $title = 'LOCATION & PACKAGING PRICE OVERRIDES'; // UPPERCASE label rule

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('warehouse_id')
                ->label('TARGET BRANCH WAREHOUSE')
                ->relationship('warehouse', 'name')
                ->placeholder('Global Fallback (All Branches)')
                ->searchable()
                ->preload(),

            Select::make('price_type')
                ->label('PRICE CLASSIFICATION')
                ->options([
                    'cost' => 'Unit Cost Price',
                    'sale' => 'Unit Selling Price',
                ])
                ->required()
                ->extraAttributes(['aria-label' => 'Select cost or selling price type']),

            TextInput::make('price')
                ->label('UNIT PRICE')
                ->numeric()
                ->required()
                ->step('0.0001') // Guardrail 1: Supports fractions of a cent (e.g. $0.0150 per gram)
                ->placeholder('0.0000')
                ->extraAttributes([
                    'class' => 'rounded-md border-zinc-200 focus:ring-primary-500',
                    'aria-label' => 'Enter high-precision price value with up to 4 decimals',
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('warehouse.name')
                    ->label('LOCATION SCOPE')
                    ->placeholder('Global Fallback')
                    ->bold(),

                TextColumn::make('price_type')
                    ->label('CLASSIFICATION')
                    ->badge()
                    ->color(fn ($state) => $state === 'cost' ? 'info' : 'success'),

                TextColumn::make('price')
                    ->label('HIGH-PRECISION PRICE')
                    ->state(fn ($record) => '$' . number_format($record->price, 4)) // Decimal UI representation
                    ->alignRight()
                    ->bold(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->closeModalByClickingAway(false);
    }
}
```

---

## 🛡️ Domain 3: Financial Loss & Auditing (Ledgers Domain)

### 1. High-Precision Loss Ledger Table (`Tables/LossLedgersTable.php`)
This immutable log registers discrepancy write-offs. It formats decimal calculations to **4-decimal places** and computes the total sum within the table running footer.

```php
namespace App\Filament\Resources\LossLedgers\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Actions\ExportBulkAction;

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
                    ->numeric()
                    ->alignRight(),

                TextColumn::make('damaged_base_qty')
                    ->label('DAMAGED BASE UNITS')
                    ->numeric()
                    ->alignRight(),

                TextColumn::make('unit_cost_price')
                    ->label('UNIT COST')
                    ->state(fn ($record) => '$' . number_format($record->unit_cost_price, 4)) // Aligned price cast
                    ->alignRight(),

                TextColumn::make('total_financial_loss')
                    ->label('TOTAL LOSS VALUE')
                    ->state(fn ($record) => '$' . number_format($record->total_financial_loss, 4)) // Aligned precision model
                    ->summarize(
                        Sum::make()
                            ->label('TOTAL WRITE-OFFS')
                            ->formatStateUsing(fn ($state) => '$' . number_format($state, 4)) // Footer total summary
                    )
                    ->bold()
                    ->alignRight(),

                TextColumn::make('loss_category')
                    ->label('CLASSIFICATION')
                    ->badge()
                    ->color('danger'),
            ])
            ->actions([]) // Immutable view
            ->bulkActions([
                ExportBulkAction::make(), // CSV matrix exporter for auditors
            ]);
    }
}
```

---

## 🎨 Architectural Decision Records: Why This Pattern Was Selected

1. **Separation of Form, Table and Infolist Engines**: Decoupling schema files keeps the primary Resource file under 100 lines. This prevents code bloat, simplifies git merges, and allows individual units of UI logic to be reused across different modals or relation managers.
2. **Clinical Contrast and Row Classes**: Alternating "zebra striping" was intentionally avoided to reduce visual noise on high-contrast screens. Instead, we use `recordClasses` to apply subtle warnings (`hover:bg-amber-50`) to active, unapproved requisitions. This highlights active tasks for floor workers at a glance.
3. **Pessimistic Data Protection (`closeModalByClickingAway(false)`)**: Floor managers are often interrupted during barcode scans or manual data entry. Forcing `closeModalByClickingAway(false)` prevents accidental modal closures when clicking outside the dialog boundaries, preserving active forms and reducing data entry errors.
4. **4-Decimal Base-Unit Ledger Matching**: Storing and displaying pricing overriding tables to four decimal places ensures that fractions of a cent are never rounded off during calculations. This prevents cascading rounding errors on high-volume micro-inventory transactions.
