# Multi-Warehouse Inventory System — Blueprint v4
**Stack:** Laravel 13 + FilamentPHP v5

> **Lineage:** v1 = basic flat-product system (superseded). v2 = adopted
> `master-sidebar-resource-map-v5.md` with gaps closed (variants, requisitions,
> loss ledger, 4-role RBAC — see `map-gap-closure.md`). v3 added Review & Verify
> wizard steps and the printable/scannable Stock Transfer Note (STN) manifest
> system from `transfer-enhancements-guide.md`, with council-mandated fixes applied.
> **v4 (this doc)** reconciles the blueprint with the actual codebase — documents all
> implemented tables, columns, enums, and extra features (TransferOrders, InTransit,
> ProductPrices, WarehouseStock) that were built beyond v3 scope. The codebase is the
> ground truth; this blueprint reflects reality.

---

## 1. Data Model

Unchanged from v2 — see below for the full reference. No new tables were needed for the v3 enhancements; STN manifests and scan-to-receive are computed/rendered from existing data (`transfer_requisitions`, `requisition_items`, `stock_movements`).

### `products`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| sku | string, unique | family/base SKU |
| name | string | product family name |
| category | string, nullable | |
| reorder_point | integer, default 0 | default safety threshold, base units |
| unit | string, default 'pcs' | base unit for the product (used in StockMovement unit tracking) |
| is_active | boolean, default true | soft-toggle to hide product from pick lists |
| deleted_at | timestamp, nullable | soft delete (via `SoftDeletes`) |
| timestamps | | |

### `product_variants`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| product_id | FK → products, nullable | nullable FK (allows orphaned variants during cleanup) |
| sku | string, unique | |
| barcode | string, nullable, unique | GTIN |
| name | string | e.g. "500g Pack" |
| base_unit_name | string | e.g. gram, piece, ml |
| cost_price | decimal(12,4) | per base unit |
| sale_price | decimal(12,4) | per base unit |
| attributes | json, nullable | |
| images | json, nullable | |
| timestamps | | |

### `product_unit_conversions`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| product_variant_id | FK → product_variants | |
| unit_name | string | e.g. Box, Bag, Pallet |
| base_unit_ratio | integer, min 1 | |
| is_default_purchase | boolean, default false | |
| is_default_transfer | boolean, default false | |
| timestamps | | |

### `product_prices`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| variant_id | FK → product_variants | |
| unit_name | string | the unit this price applies to (e.g. 'piece', 'box') |
| unit_ratio | integer | the ratio for this unit |
| cost_price | decimal(12,4) | |
| sale_price | decimal(12,4) | |
| created_by | FK → users, nullable | who set this price |
| timestamps | | |

**Index:** `(variant_id, unit_name)` — unique composite.

### `warehouses`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| code | string, unique | e.g. WH-MNL |
| name | string | |
| location | string, nullable | |
| is_active | boolean, default true | |
| deleted_at | timestamp, nullable | soft delete (via `SoftDeletes`) |
| timestamps | | |

### `stock_movements` (source of truth — no stored balance table)
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| variant_id | FK → product_variants | |
| warehouse_id | FK → warehouses | |
| type | enum: receive, ship, transfer_in, transfer_out, transit_in, transit_out, adjustment, loss | see `MovementType` enum |
| quantity | integer, **signed**, base units | + in, − out |
| unit_name_used | string, nullable | unit name at time of movement (e.g. 'Box') |
| unit_ratio_used | integer, nullable | ratio at time of movement (e.g. 24) |
| notes | string, nullable | optional human note (e.g. adjustment reason, loss reason) |
| related_movement_id | FK → stock_movements, nullable | links transfer pairs |
| reference_type | string, nullable | polymorphic source model |
| reference_id | bigint, nullable | polymorphic source id |
| reference_code | string, nullable | e.g. DTR-20260904-XXXX |
| created_by | FK → users, nullable | |
| timestamps | | |

**Index:** `(variant_id, warehouse_id)`.

> Stock is always `SUM(quantity)` grouped by variant + warehouse. Reserved = sum of `approved_base_qty` on requisition items in `confirmed`/`dispatched` status at that warehouse. Available = on-hand − reserved. Nothing stored.

### `transfer_requisitions`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| reference_code | string, unique | e.g. TRQ-20260904-XXXX |
| from_warehouse_id | FK → warehouses | |
| to_warehouse_id | FK → warehouses | |
| status | enum: draft, requested, under_review_fulfiller, under_review_requestor, approved, confirmed, dispatched, partially_received, completed, closed_with_loss, cancelled | see `TransferRequisitionStatus` enum |
| requested_by | FK → users | |
| approved_by | FK → users, nullable | |
| dispatched_by | FK → users, nullable | |
| received_by | FK → users, nullable | who confirmed receipt (scan or manual) |
| requested_at | timestamp | |
| approved_at | timestamp, nullable | when approved |
| dispatched_at | timestamp, nullable | |
| received_at | timestamp, nullable | when receipt was confirmed |
| completed_at | timestamp, nullable | |
| notes | text, nullable | optional notes on the requisition |
| deleted_at | timestamp, nullable | soft delete (via `SoftDeletes`) |
| timestamps | | |

### `requisition_items`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| requisition_id | FK → transfer_requisitions | |
| variant_id | FK → product_variants | |
| substitute_variant_id | FK → product_variants, nullable | |
| requested_unit_name | string | |
| requested_unit_ratio | integer | |
| requested_qty | integer | |
| requested_base_qty | integer | |
| approved_unit_name | string, nullable | |
| approved_unit_ratio | integer, nullable | ratio at time of approval |
| approved_qty | integer, nullable | |
| approved_base_qty | integer, nullable | |
| shipped_base_qty | integer, nullable | actual base qty shipped from origin |
| received_good_base_qty | integer, nullable | actual base qty received in good condition |
| received_damaged_base_qty | integer, nullable | actual base qty received damaged |
| received_qty | integer, nullable | actual quantity confirmed at receipt |
| notes | text, nullable | optional notes on this item |
| timestamps | | |

### `transfer_requisition_audits`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| requisition_item_id | FK → requisition_items | |
| user_id | FK → users | |
| variant_id | FK → product_variants | |
| substitute_variant_id | FK → product_variants, nullable | |
| proposed_unit_name | string | |
| proposed_qty | integer | |
| proposed_base_qty | integer | |
| approved_unit_name | string, nullable | |
| approved_qty | integer, nullable | |
| approved_base_qty | integer, nullable | |
| negotiation_reason | text, nullable | |
| action | string | e.g. 'proposed', 'approved', 'rejected' |
| timestamps | | |

### `loss_ledgers`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| requisition_id | FK → transfer_requisitions | |
| requisition_item_id | FK → requisition_items, nullable | link to specific item |
| variant_id | FK → product_variants | |
| warehouse_id | FK → warehouses | |
| lost_base_qty | integer | |
| damaged_base_qty | integer, default 0 | |
| unit_cost_price | decimal(12,4) | |
| total_financial_loss | decimal(14,4) | |
| loss_category | string, default 'shortfall' | |
| recorded_by | FK → users, nullable | who recorded this loss |
| recorded_at | timestamp, nullable | when the loss was recorded |
| timestamps | | |

### `warehouse_stock`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| variant_id | FK → product_variants | |
| warehouse_id | FK → warehouses | |
| quantity | integer, default 0 | current on-hand quantity (base units) |
| timestamps | | |

**Index:** `(variant_id, warehouse_id)` — unique composite. Denormalized fast-lookup table; kept in sync by `InventoryService::recordMovement()`.

### `in_transit`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| requisition_id | FK → transfer_requisitions | |
| variant_id | FK → product_variants | |
| source_warehouse_id | FK → warehouses | origin |
| destination_warehouse_id | FK → warehouses | destination |
| base_qty | integer | total base units in transit |
| received_good_base_qty | integer, default 0 | units confirmed received in good condition |
| received_damaged_base_qty | integer, default 0 | units confirmed received damaged |
| status | enum: in_transit, partially_received, received, cleared | see `InTransitStatus` enum |
| dispatched_by | FK → users, nullable | |
| dispatched_at | timestamp, nullable | |
| received_by | FK → users, nullable | |
| received_at | timestamp, nullable | |
| cleared_by | FK → users, nullable | |
| cleared_at | timestamp, nullable | |
| notes | text, nullable | |
| timestamps | | |

**Index:** `(variant_id, source_warehouse_id)`, `(variant_id, destination_warehouse_id)`, `(status)`.

### `transfer_orders`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| reference_code | string, unique | e.g. TRO-20260904-XXXX |
| requisition_id | FK → transfer_requisitions, nullable | |
| source_warehouse_id | FK → warehouses | |
| destination_warehouse_id | FK → warehouses | |
| status | enum: pending, confirmed, partially_received, received, completed, cancelled | see `TransferOrderStatus` enum |
| expected_dispatch_at | timestamp, nullable | |
| confirmed_at | timestamp, nullable | |
| dispatched_at | timestamp, nullable | |
| received_at | timestamp, nullable | |
| notes | text, nullable | |
| deleted_at | timestamp, nullable | soft delete (via `SoftDeletes`) |
| timestamps | | |

**Index:** `(status)`, `(source_warehouse_id)`, `(destination_warehouse_id)`.

### `transfer_order_items`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| transfer_order_id | FK → transfer_orders | |
| variant_id | FK → product_variants | |
| requested_base_qty | integer | |
| shipped_base_qty | integer, nullable | |
| received_base_qty | integer, nullable | |
| status | enum: pending, confirmed, partially_received, received, completed, cancelled | see `TransferOrderItemStatus` enum |
| notes | text, nullable | |
| timestamps | | |

### `transfer_order_audits`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| transfer_order_id | FK → transfer_orders | |
| user_id | FK → users, nullable | |
| action | string | e.g. 'status_changed', 'item_added' |
| old_values | json, nullable | |
| new_values | json, nullable | |
| notes | text, nullable | |
| timestamps | | |
| column | type | notes |
|---|---|---|
| role | string, default 'warehouse_staff' | admin / auditor / branch_manager / warehouse_staff |

### `user_warehouse` (pivot)
| column | type |
|---|---|
| user_id | FK → users |
| warehouse_id | FK → warehouses |

---

## 2. RBAC — 4 Canonical Roles

```php
enum UserRole: string
{
    case Admin = 'admin';
    case Auditor = 'auditor';
    case BranchManager = 'branch_manager';
    case WarehouseStaff = 'warehouse_staff';
}
```

| Role | Visibility |
|---|---|
| **admin** | Everything. |
| **auditor** | Read-only, all warehouses, Audit Ledgers group. |
| **branch_manager** | Scoped to assigned warehouse(s); can approve/confirm requisitions and **scan-to-receive** for their warehouse. |
| **warehouse_staff** | Scoped to assigned warehouse(s); can view and dispatch, but scan-to-receive confirmation is a branch_manager action (see Section 6). |

---

## 3. Sidebar Navigation

| Group | Resource | Icon | Sort | Visibility |
|---|---|---|---|---|
| Home | Dashboard | Home | — | All |
| Inventory | StockAdjustment (Page) | Cog6Tooth | 15 | Admin, Branch Manager |
| **CATALOG** | ProductResource | Cube | — | All |
| | ProductPriceResource | CurrencyDollar | — | All |
| **OPERATIONS** | StockMovementResource | ArrowsRightLeft | 20 | All (scoped) |
| | CurrentStockResource | Cube | 21 | All (scoped), label "Current Stock" |
| | TransferRequisitionResource | ArrowPath | 25 | All (scoped) |
| **TRANSFERS** | TransferOrderResource | ArrowsRightLeft | — | All (scoped) |
| **ADMIN** | WarehouseResource | BuildingOffice2 | 30 | Admin only |
| | UserResource | UserGroup | 31 | Admin only |
| *(ungrouped)* | InTransitResource | Truck | — | Admin, Auditor, Branch Manager |
| | LossLedgerResource | DocumentText | — | Admin, Auditor |

Notes:
- `CurrentStockResource` is a read-only resource (no create/edit) showing warehouse stock levels.
- `InTransitResource` and `LossLedgerResource` have no explicit `navigationGroup()` — Filament places them ungrouped at the bottom.
- `StockAdjustment` is a standalone Filament Page (not a Resource) in the "Inventory" group.
- `TransferOrderResource` replaces the old `DirectTransferResource` from v3.
- Icons use `Filament\Support\Icons\Heroicon` enums.

---

## 4. Core Services

### `InventoryService`
- `lockStockForProduct()` — locks stock rows for a product at a warehouse (used before dispatch/reservation)
- `currentQuantity()` — SUM lookup for on-hand quantity at a specific warehouse
- `availableForNegotiation()` — on-hand minus reserved at a specific warehouse
- `totalQuantity()` — SUM on-hand across all warehouses
- `recordMovement()` — the one primitive every mutation goes through; creates StockMovement and updates WarehouseStock
- `ship()` — guarded single-warehouse deduction
- `transfer()` — instant two-leg transfer between warehouses (out + in movements)
- `lockStockForRequisition()` — reserves stock for a requisition, marks status as `confirmed`
- `dispatchTransfer()` — moves stock out of origin, creates InTransit records, marks status as `dispatched`
- `scanToReceive()` — moves stock into destination, handles loss/damage via LossLedger, marks `completed`/`closed_with_loss`. **This is the single method called by both the manual "Confirm Receipt" table action AND the scan-to-receive flow — no duplicate logic path.**

### `AuditService`
- `record()` — records an audit trail entry for a transfer order mutation (must be called within the same DB::transaction)
- `recordRequisition()` — records an audit trail entry for a transfer requisition mutation (must be called within the same DB::transaction)

Full bodies in `map-gap-closure.md`.

---

## 5. Transfer Requisition State Machine

```
draft → requested → under_review_fulfiller ⇄ under_review_requestor → approved → confirmed → dispatched → partially_received → completed
                                                                                                         ↘ closed_with_loss
requested/under_review_* → cancelled (either party)
```

Dispatch and receive both go through `InventoryService`. Receipt can be triggered two ways (Section 6): manual table action, or QR scan — both funnel into the same `receiveRequisition()` call. Partial receipt moves the requisition to `partially_received`; full receipt moves it to `completed` or `closed_with_loss` depending on whether any items had shortfalls.

---

## 6. v3 Additions: Review & Verify + STN Manifest + Scan-to-Receive

### 6.1 Review & Verify Wizard Step

`TransferRequisitionForm` gains a final wizard step rendering a live summary (via reactive `$get()` Placeholders) before submission — item lines, computed base quantities, origin/destination. Purely a UX safeguard; no new data captured.

### 6.2 Printable STN Manifest

STN manifest printing is implemented as a **Filament header action** (`print_stn`) in `ViewTransferRequisition` — not a separate controller. The action:

1. Generates a 30-day signed URL via `URL::temporarySignedRoute('stn.scan', ...)`
2. Renders a QR code via `QrCode::size(140)->generate($scanUrl)`
3. Streams a PDF via `Pdf::loadView('pdf.stn-manifest', [...])` using `barryvdh/laravel-dompdf`

```php
Action::make('print_stn')
    ->label('Print STN')
    ->icon(Heroicon::OutlinedDocumentText)
    ->color('gray')
    ->visible(fn (TransferRequisition $record): bool => in_array($record->status, [
        TransferRequisitionStatus::Dispatched,
        TransferRequisitionStatus::PartiallyReceived,
        TransferRequisitionStatus::Completed,
        TransferRequisitionStatus::ClosedWithLoss,
    ]))
    ->action(function (TransferRequisition $record): void {
        $scanUrl = URL::temporarySignedRoute(
            'stn.scan',
            now()->addDays(30),
            ['transferRequisition' => $record->id]
        );
        $qrCode = QrCode::size(140)->generate($scanUrl);
        $pdf = Pdf::loadView('pdf.stn-manifest', [
            'requisition' => $record->load(['items.variant', 'fromWarehouse', 'toWarehouse', 'requestedBy']),
            'qrCode' => $qrCode,
        ]);
        $pdf->stream("STN-{$record->reference_code}.pdf");
    }),
```

**Signature blocks are physical-paper backup only** — the printed sign-off lines are not the system of record; the scan (or manual confirm action) is. The manifest says so explicitly in a footer note.

For **Transfer Orders** (the direct/atomic transfer mechanism): `TransferNoteController` (invokable) renders `transfer-notes.show` Blade view with a QR code embedding the order's `reference_number`. This is a print-only view for the physical STN document — no scan-to-receive needed since direct transfers are atomic.

### 6.3 Scan-to-Receive

**No separate controller.** The scan-to-receive flow is implemented entirely within the Filament layer:

**Route** (inline closure in `routes/web.php`):
```php
Route::get('/transfers/scan/{transferRequisition}', function (Request $request, TransferRequisition $transferRequisition) {
    if (! $request->hasValidSignature()) {
        session()->flash('notification', [
            'title' => 'Signature Expired or Invalid',
            'body' => 'The scanned physical Stock Transfer Note is older than 30 days or has been modified. Please generate a fresh manifest.',
            'type' => 'danger',
        ]);
        return redirect()->route('filament.admin.pages.dashboard');
    }

    $user = auth()->user();
    if (
        ! $user->hasAccessToWarehouse($transferRequisition->to_warehouse_id) &&
        ! $user->hasAccessToWarehouse($transferRequisition->from_warehouse_id)
    ) {
        session()->flash('notification', [
            'title' => 'Access Denied',
            'body' => 'You are not assigned to the origin or receiving warehouse linked to this transfer requisition.',
            'type' => 'warning',
        ]);
        return redirect()->route('filament.admin.pages.dashboard');
    }

    return redirect(
        TransferRequisitionResource::getUrl('view', [
            'record' => $transferRequisition->id,
            'scan' => 1,
        ])
    );
})
    ->name('stn.scan')
    ->middleware('throttle:scans');
```

The route validates the signed URL, checks warehouse access, then **redirects to the Filament ViewTransferRequisition page** with `?scan=1`.

**Filament page auto-opens the scan modal** (`ViewTransferRequisition::mount()`):
```php
public function mount(mixed $record): void
{
    parent::mount($record);
    if (request()->query('scan') === '1' && in_array($this->record->status, [
        TransferRequisitionStatus::Dispatched,
        TransferRequisitionStatus::PartiallyReceived,
    ])) {
        $this->dispatch('open-modal', modal: 'scan_to_receive');
    }
}
```

**Scan-to-Receive modal** (header action `scan_to_receive` in `ViewTransferRequisition`):
- Renders a `Repeater` with pre-filled expected quantities per item
- Fields: `item_id` (hidden), `variant_sku` (read-only), `expected_qty` (read-only), `good_qty`, `damaged_qty`, `loss_category` (select)
- Calls `InventoryService::scanToReceive($record->id, $receivedData)` on submit
- Requires `dispatched` or `partially_received` status

**QR generation** (`simplesoftwareio/simple-qrcode`, installed):
```php
$signedUrl = URL::temporarySignedRoute('stn.scan', now()->addDays(30), ['transferRequisition' => $requisition->id]);
$qrCodeSvg = QrCode::size(140)->generate($signedUrl);
```

---

## 7. File Structure

### Filament Resources (nested v5 layout)

Each resource follows the `app/Filament/Resources/{ModelName}/` convention:

```
app/Filament/Resources/
├── CurrentStock/
│   ├── CurrentStockResource.php
│   ├── Pages/ListCurrentStock.php
│   └── Tables/CurrentStockTable.php
├── InTransit/
│   ├── InTransitResource.php
│   └── Pages/{Create,List,Edit,View}InTransit.php
├── LossLedger/
│   ├── LossLedgerResource.php
│   └── Pages/{List,View}LossLedger.php
├── ProductPrices/
│   ├── ProductPriceResource.php
│   └── Pages/{Create,Edit,List}ProductPrice.php
├── Products/
│   ├── ProductResource.php
│   ├── Pages/{Create,Edit,List}Product.php
│   ├── RelationManagers/{Variants,Conversions}RelationManager.php
│   ├── Schemas/ProductForm.php
│   └── Tables/ProductsTable.php
├── StockMovements/
│   ├── StockMovementResource.php
│   ├── Pages/{Create,Edit,List,View}StockMovement.php
│   └── Tables/StockMovementsTable.php
├── TransferOrders/
│   ├── TransferOrderResource.php
│   ├── Pages/{Create,Edit,List,View}TransferOrder.php
│   ├── Schemas/TransferOrderForm.php
│   └── Tables/TransferOrdersTable.php
├── TransferRequisitions/
│   ├── TransferRequisitionResource.php
│   ├── Pages/{Create,Edit,List,View}TransferRequisition.php
│   └── Tables/TransferRequisitionsTable.php
├── Users/
│   ├── UserResource.php
│   ├── Pages/{Create,Edit,List}User.php
│   ├── Schemas/UserForm.php
│   └── Tables/UsersTable.php
└── Warehouses/
    ├── WarehouseResource.php
    ├── Pages/{Create,Edit,List}Warehouse.php
    ├── Schemas/WarehouseForm.php
    └── Tables/WarehousesTable.php
```

### Filament Pages, Widgets & Exports

```
app/Filament/
├── Pages/
│   ├── Auth/Login.php
│   ├── Dashboard.php
│   └── StockAdjustment.php
├── Widgets/
│   ├── DashboardSections/   (5 section-header widgets)
│   ├── CategoryStockChart.php
│   ├── FastMovingStockChart.php
│   ├── InTransitStockWidget.php
│   ├── InventoryHealthWidget.php
│   ├── LowStockAlertWidget.php / LowStockWidget.php
│   ├── MaterialLossWidget.php
│   ├── OnHandStockWidget.php
│   ├── PendingTransfersWidget.php
│   ├── ProductCatalogWidget.php
│   ├── QuickActionsWidget.php
│   ├── RecentStockActivityWidget.php
│   ├── StatsOverviewWidget.php
│   ├── StockByWarehouseWidget.php
│   ├── StockMovementTrendChart.php
│   ├── WarehouseCapacityWidget.php
│   ├── WarehouseFilterWidget.php
│   └── WarehouseStockOverviewWidget.php
└── Exports/
    └── ProductExporter.php
```

### Controllers & Routes

```
app/Http/Controllers/
├── Controller.php              (base)
└── TransferNoteController.php  (invokable — TransferOrder note print)

routes/web.php:
├── transfer-notes.show         (GET /transfer-notes/{id})
├── stn.scan                    (GET /stn/scan/{transferRequisition} — signed URL, inline closure)
```

### Views

```
resources/views/
├── pdf/
│   └── stn-manifest.blade.php         (PDF — TransferRequisition manifest)
├── transfer-notes/
│   └── show.blade.php                 (TransferOrder note HTML view)
├── filament/
│   ├── dashboard-sections/section-header.blade.php
│   ├── pages/stock-adjustment.blade.php
│   └── widgets/  (7 blade files for charts)
└── welcome.blade.php
```

### Services & Enums

```
app/Services/
├── InventoryService.php    (10 public methods — see §4)
└── AuditService.php        (2 public methods: record, recordRequisition)

app/Enums/                  (6 backed enums — see §2)
```

---

## 8. Build Order (completed)

The system is fully built. The actual implementation order was:

1. Laravel 13 + Filament v5 install, admin panel, first admin user
2. Migrations: products, product_variants, product_unit_conversions, warehouses, stock_movements, user_warehouse
3. Models + relationships, `ProductVariant` quantity accessors
4. `UserRole` enum + helper methods on `User`
5. `InventoryService` v1 (recordMovement, currentQuantity, executeDirectTransfer, ship)
6. `ProductResource` + Variants/Conversions relation managers
7. `WarehouseResource` + Users relation manager
8. `UserResource` + Warehouses relation manager
9. Migrations: transfer_requisitions, requisition_items, requisition_item_revisions + models
10. `TransferRequisitionResource` — wizard with Routing → Manifest → Review & Verify steps
11. `InventoryService` expansion: `dispatchRequisition()`, `receiveRequisition()`, `scanToReceive()`
12. `InTransitResource` with "Confirm Receipt" action; `StockMovementResource`
13. RBAC scoping pass across all resources
14. `LossLedgerResource`, `CurrentStockResource`
15. `TransferOrders` resource + `TransferNoteController` (note print)
16. `TransferRequisitions`: print_stn header action, QR code generation, `stn.scan` signed route, scan-to-receive modal
17. Dashboard widgets (20+ widgets), `StockAdjustment` page, `ProductPrices` resource
18. `AuditService` integration, `ProductExporter` export

---

## 9. Notes

- Four council conditions from the v3 review are folded directly into this doc: Schema import fix, scan modal in Filament (not separate confirm page), TransferNoteController for TransferOrder notes, QR embedded in requisition manifest.
- The `Resources/TransferRequisitionResource/` flat directory is an empty leftover and can be ignored.