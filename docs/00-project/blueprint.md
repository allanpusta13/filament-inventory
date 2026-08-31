### Multi-Warehouse Inventory System — Complete Revised Blueprint (v2.2)
**Stack:** Laravel 13 + FilamentPHP v5 | **Scale:** ~few hundred SKUs, <10 warehouses

---

#### Core System Architecture & Audited Guardrails
1. **Strict Base-Unit Ledger:** All inventory records and `stock_movements.quantity` values are stored as **signed integers in the product's Base Unit** (the lowest non-divisible unit, e.g., single pieces or grams). Packaging units (e.g., Boxes, Pallets) function purely as a display and input abstraction layer, converted at system boundaries using `base_unit_ratio`.
2. **Pessimistic Locking & Concurrency Control:** All stock checks and movement updates run inside database transactions (`DB::transaction()`) using pessimistic row locking (`lockForUpdate()`) on `product_variants` or `warehouse_stock` balances to eliminate race conditions during concurrent dispatches or adjustments.
3. **Concurrency-Safe Creating (`firstOrCreate` Exception Fallback):** When concurrent workers attempt to record stock movements for a newly allocated variant, database-level unique constraint collisions on the `(variant_id, warehouse_id)` composite index are intercepted. The system catches the `QueryException` and falls back to selecting the row directly.
4. **Hybrid Real-Time Ledger:** `stock_movements` serves as the immutable audit log of every stock event. A high-performance `warehouse_stock` table acts as a cached read-model for rapid queries, low-stock alerts, and pessimistic row-locking targets, synchronized via `InventoryService` transactions.
5. **Canonical State Machine:** Requisitions follow an explicit physical lifecycle:
   $$\text{draft} \rightarrow \text{requested} \rightarrow \text{under\_review\_fulfiller} \rightarrow \text{under\_review\_requestor} \rightarrow \text{confirmed} \rightarrow \text{dispatched} \rightarrow \text{partially\_received} \rightarrow \text{completed / closed\_with\_loss / cancelled}$$
6. **Dynamic Substitute Variant Fulfillment:** During dispatch and intake, the system checks for negotiated alternative stock items. Shipping and receiving operations resolve `$actualVariantId = $item->substitute_variant_id ?? $item->variant_id` at loop start, applying stock deductions and additions to the physically substituted variant.
7. **Negative Quantity Protections:** Strict validation assertions enforce that all requisition-locking and dispatch operations reject zero or negative numbers to protect calculation integrity.
8. **Scanned Receipt Loss Integrity:** If a dispatched item is omitted entirely from scanning data, it is not skipped. The system automatically records a 100% variance loss, registers the discrepancy to the `LossLedger`, and clears outstanding `InTransit` records.
9. **Signed QR Web Routing with Graceful Recovery:** STN QR codes encode fully-qualified, temporary signed URLs with 30-day expirations. Scanning redirects authenticated staff directly into Filament's `ViewTransferRequisition` page. If the signature is expired or the worker lacks clearance for that warehouse, routing interceptors redirect them gracefully to the dashboard with an explicit warning card instead of returning a raw Laravel 403 page.
10. **Soft-Delete Safety & Reservation Observer:** Registering a boot observer on `TransferRequisition` ensures that if a confirmed requisition is soft-deleted, the allocated `reserved_quantity` is automatically decremented from the source warehouse. Soft-deletes on active dispatched orders are strictly blocked.
11. **High-Performance Widget Caching:** Aggregations inside dashboard widgets are wrapped in cache tags with a 5-minute TTL to protect database memory under heavy traffic.
12. **Manual Stock Adjustment Validation:** To maintain audit ledger accountability, manual adjustment reason fields require a minimum of 15 characters and are bound by custom regex filters blocking dummy notes (e.g. ".", "test").
13. **Isolated CI/CD Testing Database Environments:** Unit and feature testing run on an isolated in-memory SQLite database (`:memory:`), while Dusk or Playwright E2E browser test sequences use the PostgreSQL container database sequentially to avoid state contamination.

---

#### 1. Complete Database Schema

##### products
| Column | Type | Modifiers / Notes |
| ------ | ------ | ------ |
| id | bigint | PK |
| sku | string | Base SKU, unique index |
| name | string | Product family name |
| category | string | Nullable |
| reorder_point | integer | Default threshold in Base Units (default 0) |
| deleted_at | timestamp | Soft deletes support |
| timestamps | timestamp |  |

##### product_variants
| Column | Type | Modifiers / Notes |
| ------ | ------ | ------ |
| id | bigint | PK |
| product_id | foreignId | FK → products.id (cascade delete) |
| sku | string | Variant-specific SKU, unique index |
| barcode | string | Nullable, unique index for high-speed scanning |
| name | string | E.g., "Red / Large", "500ml Bottle" |
| attributes | json | Custom key-value tags (e.g., `{"size": "L", "color": "Red"}`) |
| base_unit_name | string | Name of lowest non-divisible unit (e.g., "Piece", "Gram") |
| images | json | Nullable, array of image URLs |
| cost_price | decimal(15,4) | Default cost price per Base Unit (High precision) |
| sale_price | decimal(15,4) | Default selling price per Base Unit (High precision) |
| deleted_at | timestamp | Soft deletes support |
| timestamps | timestamp |  |

##### product_unit_conversions
| Column | Type | Modifiers / Notes |
| ------ | ------ | ------ |
| id | bigint | PK |
| variant_id | foreignId | FK → product_variants.id (cascade delete) |
| unit_name | string | Packaging name (e.g., "Box", "Pack", "Pallet") |
| base_unit_ratio | integer | Multiplier relative to base unit (1 Box = 24 Base Units → 24) |
| is_default_purchase | boolean | Default unit for receiving/PO (default false) |
| is_default_transfer | boolean | Default unit for requisitions (default false) |
| timestamps | timestamp |  |

##### product_prices
| Column | Type | Modifiers / Notes |
| ------ | ------ | ------ |
| id | bigint | PK |
| variant_id | foreignId | FK → product_variants.id (cascade delete) |
| warehouse_id | foreignId | Nullable, FK → warehouses.id (null = global price) |
| unit_name | string | Unit name matching `base_unit_name` or conversions |
| price_type | enum | `cost`, `sale` |
| price | decimal(15,4) | Packaging/location-specific price (High precision) |
| timestamps | timestamp |  |

##### warehouses
| Column | Type | Modifiers / Notes |
| ------ | ------ | ------ |
| id | bigint | PK |
| code | string | Short code, unique (e.g., WH-MNL, WH-CEB) |
| name | string | Warehouse or branch name |
| location | string | Nullable address / location details |
| is_active | boolean | Default true |
| timestamps | timestamp |  |

##### warehouse_stock (Read Model & Lock Target)
| Column | Type | Modifiers / Notes |
| ------ | ------ | ------ |
| id | bigint | PK |
| variant_id | foreignId | FK → product_variants.id (cascade delete) |
| warehouse_id | foreignId | FK → warehouses.id (cascade delete) |
| on_hand_quantity | integer | Physical stock present in warehouse (Base Units) |
| reserved_quantity | integer | Stock locked for confirmed transfers (Base Units) |
| timestamps | timestamp |  |

**Unique Index:** `unique(['variant_id', 'warehouse_id'])`  
**Available Stock Formula:** `available_quantity = on_hand_quantity - reserved_quantity`

##### stock_movements (Immutable Audit Ledger)
| Column | Type | Modifiers / Notes |
| ------ | ------ | ------ |
| id | bigint | PK |
| variant_id | foreignId | FK → product_variants.id |
| warehouse_id | foreignId | FK → warehouses.id |
| type | enum | `receive`, `ship`, `transfer_out`, `transfer_in`, `transit_out`, `transit_in`, `adjustment`, `loss` |
| quantity | integer | **Signed integer strictly in Base Units** (+ for in, − for out) |
| unit_name_used | string | Packaging unit label used during transaction (for audit) |
| unit_ratio_used | integer | Multiplier active at time of transaction |
| related_movement_id | foreignId | Nullable, FK → stock_movements.id (links transfer pairs) |
| reference_type | string | Nullable (e.g., TransferRequisition, PurchaseOrder) |
| reference_id | unsignedBigInteger | Nullable polymorphic ID |
| reference_code | string | Nullable reference string (e.g., STN-2026-0089) |
| created_by | foreignId | Nullable, FK → users.id |
| created_at | timestamp | Indexed |

**Indexes:** `(variant_id, warehouse_id)`, `(reference_type, reference_id)`, `created_at`

##### transfer_requisitions
| Column | Type | Modifiers / Notes |
| ------ | ------ | ------ |
| id | bigint | PK |
| reference_code | string | Unique index (e.g., TRQ-2026-0001) |
| from_warehouse_id | foreignId | FK → warehouses.id (Fulfiller) |
| to_warehouse_id | foreignId | FK → warehouses.id (Requestor) |
| status | enum | `draft`, `requested`, `under_review_fulfiller`, `under_review_requestor`, `confirmed`, `dispatched`, `partially_received`, `completed`, `closed_with_loss`, `cancelled` |
| requested_by | foreignId | FK → users.id |
| approved_by | foreignId | Nullable, FK → users.id |
| dispatched_by | foreignId | Nullable, FK → users.id |
| received_by | foreignId | Nullable, FK → users.id |
| requested_at | timestamp | Nullable |
| approved_at | timestamp | Nullable |
| dispatched_at | timestamp | Nullable |
| completed_at | timestamp | Nullable |
| notes | text | Nullable |
| deleted_at | timestamp | Soft deletes support |
| timestamps | timestamp |  |

##### transfer_requisition_items
| Column | Type | Modifiers / Notes |
| ------ | ------ | ------ |
| id | bigint | PK |
| requisition_id | foreignId | FK → transfer_requisitions.id (cascade delete) |
| variant_id | foreignId | FK → product_variants.id |
| requested_unit_name | string | UoM requested (e.g., "Box") |
| requested_unit_ratio | integer | Ratio at time of request |
| requested_qty | integer | Quantity in requested UoM |
| requested_base_qty | integer | Calculated Base Unit quantity |
| approved_unit_name | string | Nullable, UoM after negotiation |
| approved_unit_ratio | integer | Nullable |
| approved_qty | integer | Nullable |
| approved_base_qty | integer | Nullable, approved quantity in Base Units |
| shipped_base_qty | integer | Default 0, actual base units dispatched |
| received_good_base_qty | integer | Default 0, good base units received |
| received_damaged_base_qty | integer | Default 0, damaged base units received |
| substitute_variant_id | foreignId | Nullable, FK → product_variants.id (negotiated replacement) |
| notes | text | Nullable |
| timestamps | timestamp |  |

##### in_transit
| Column | Type | Modifiers / Notes |
| ------ | ------ | ------ |
| id | bigint | PK |
| requisition_id | foreignId | FK → transfer_requisitions.id (cascade delete) |
| requisition_item_id | foreignId | FK → transfer_requisition_items.id |
| variant_id | foreignId | FK → product_variants.id |
| dispatched_base_qty | integer | Base unit quantity currently in transit |
| dispatched_at | timestamp |  |
| status | enum | `in_transit`, `partially_received`, `cleared` |
| timestamps | timestamp |  |

##### loss_ledger
| Column | Type | Modifiers / Notes |
| ------ | ------ | ------ |
| id | bigint | PK |
| requisition_id | foreignId | FK → transfer_requisitions.id |
| requisition_item_id | foreignId | FK → transfer_requisition_items.id |
| variant_id | foreignId | FK → product_variants.id |
| warehouse_id | foreignId | FK → warehouses.id (Warehouse bearing loss) |
| lost_base_qty | integer | Missing/unaccounted units |
| damaged_base_qty | integer | Physical damaged units |
| unit_cost_price | decimal(15,4) | Cost price per base unit at time of loss (High precision) |
| total_financial_loss | decimal(15,4) | Computed monetary loss (High precision) |
| loss_category | string | E.g., "Damaged in Transit", "Short Shipment", "Spoiled" |
| recorded_by | foreignId | FK → users.id |
| recorded_at | timestamp |  |
| timestamps | timestamp |  |

##### users
| Column | Type | Modifiers / Notes |
| ------ | ------ | ------ |
| id | bigint | PK |
| name | string |  |
| email | string | Unique index |
| password | string |  |
| role | string | Enum/String: admin, branch_manager, warehouse_staff, auditor |
| timestamps | timestamp |  |

##### user_warehouse (Pivot)
| Column | Type | Modifiers / Notes |
| ------ | ------ | ------ |
| user_id | foreignId | FK → users.id (cascade delete) |
| warehouse_id | foreignId | FK → warehouses.id (cascade delete) |

**Primary Key:** `(user_id, warehouse_id)`

---

#### 2. Access Control & Authorization (RBAC)
Authorization is governed by standard **Laravel Policies** bound to Filament resources.
##### User Roles
* **admin**: Full system visibility across all branches, global pricing matrices, user management, and manual override capabilities.
* **branch_manager**: Full operational control over assigned warehouse(s). Can create requisitions, negotiate counter-offers, approve dispatches, sign off on receiving losses, and manage stock adjustments.
* **warehouse_staff**: Operational role for assigned warehouse(s). Can create draft requisitions, pack dispatches, and execute scan-to-receive entries. Cannot edit price matrices or override loss entries.
* **auditor**: Read-only global access to multi-warehouse stock ledgers, requisition histories, STN records, and financial loss write-offs.

---

#### 3. Web-Routed STN QR Code Pipeline
##### Signed Web URL Routing
Instead of static JSON payloads, the STN QR Code embeds a secure, signed URL using `APP_URL`. When scanned by any smartphone camera or handheld device, it opens Filament directly:
```
https://your-app-domain.com/transfers/scan/42?signature=a8f9c2e7d6b3c4a1...
```
##### 1. Web Route Definition with Custom Exception Recovery (`routes/web.php`)
```php
use App\\Filament\\Resources\\TransferRequisitionResource;
use Illuminate\\Support\\Facades\\Route;
use Illuminate\\Http\\Request;

Route::middleware(['auth'])->group(function () {
    Route::get('/transfers/scan/{transferRequisition}', function (Request $request, \App\\Models\\TransferRequisition $transferRequisition) {
        if (! $request->hasValidSignature()) {
            return redirect()->route('filament.admin.pages.dashboard')
                ->with('danger', 'The scanned Stock Transfer Note signature is invalid or has expired.');
        }

        if (! auth()->user()->hasAccessToWarehouse($transferRequisition->to_warehouse_id)) {
            return redirect()->route('filament.admin.pages.dashboard')
                ->with('danger', 'You do not have access authorization for this destination warehouse.');
        }

        return redirect(
            TransferRequisitionResource::getUrl('view', [
                'record' => $transferRequisition->id,
                'scan' => 1,
            ])
        );
    })->name('stn.scan')->middleware('throttle:20,1');
});
```

##### 2. PDF Manifest QR Generation Logic
When rendering the Stock Transfer Note PDF, generate a signed 30-day temporary URL:
```php
use Illuminate\\Support\\Facades\\URL;
use SimpleSoftwareIO\\QrCode\\Facades\\QrCode;

$scanUrl = URL::temporarySignedRoute(
    'stn.scan',
    now()->addDays(30),
    ['transferRequisition' => $requisition->id]
);

$qrCodeSvg = QrCode::size(140)->generate($scanUrl);
```

---

#### 4. Core Service Logic (`InventoryService`)
All inventory mutations run inside pessimistic database transactions.

```php
namespace App\\Services;

use App\\Models\\InTransit;
use App\\Models\\LossLedger;
use App\\Models\\ProductVariant;
use App\\Models\\StockMovement;
use App\\Models\\TransferRequisition;
use App\\Models\\WarehouseStock;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Database\\QueryException;
use Exception;

class InventoryService
{
    /**
     * Record an atomic stock movement and update the warehouse_stock cache model.
     * Patched to handle firstOrCreate concurrency race conditions safely.
     */
    public function recordMovement(
        int $variantId,
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
            $variantId, $warehouseId, $type, $baseQuantity,
            $unitName, $unitRatio, $referenceType, $referenceId,
            $referenceCode, $relatedMovementId
        ) {
            // Guardrail 2: Catch duplicate insert collisions during parallel execution
            try {
                $stock = WarehouseStock::firstOrCreate(
                    ['variant_id' => $variantId, 'warehouse_id' => $warehouseId],
                    ['on_hand_quantity' => 0, 'reserved_quantity' => 0]
                );
            } catch (QueryException $e) {
                // Secondary check: retrieve row directly on constraint trip
                $stock = WarehouseStock::where('variant_id', $variantId)
                    ->where('warehouse_id', $warehouseId)
                    ->firstOrFail();
            }
            
            $stockRow = WarehouseStock::where('id', $stock->id)->lockForUpdate()->first();

            if ($baseQuantity < 0 && ($stockRow->on_hand_quantity + $baseQuantity) < 0) {
                throw new Exception("Insufficient stock available for Variant ID {$variantId} at Warehouse ID {$warehouseId}.");
            }

            $stockRow->on_hand_quantity += $baseQuantity;
            $stockRow->save();

            return StockMovement::create([
                'variant_id' => $variantId,
                'warehouse_id' => $warehouseId,
                'type' => $type,
                'quantity' => $baseQuantity,
                'unit_name_used' => $unitName ?? 'Base Unit',
                'unit_ratio_used' => $unitRatio,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_code' => $referenceCode,
                'related_movement_id' => $relatedMovementId,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Lock allocated stock when a requisition is mutually confirmed.
     */
    public function lockStockForRequisition(int $requisitionId): void
    {\n        DB::transaction(function () use ($requisitionId) {
            $requisition = TransferRequisition::with('items')->findOrFail($requisitionId);

            foreach ($requisition->items as $item) {
                $neededBaseQty = $item->approved_base_qty ?? $item->requested_base_qty;

                // Guardrail 5: Assert positive requested inventory balance
                if ($neededBaseQty <= 0) {
                    throw new Exception("Requisition item quantities must be strictly positive integers.");
                }

                $stock = WarehouseStock::where('variant_id', $item->variant_id)
                    ->where('warehouse_id', $requisition->from_warehouse_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (($stock->on_hand_quantity - $stock->reserved_quantity) < $neededBaseQty) {
                    throw new Exception("Insufficient unreserved stock for variant {$item->variant_id} at source.");
                }

                $stock->reserved_quantity += $neededBaseQty;
                $stock->save();
            }

            $requisition->update(['status' => 'confirmed']);
        });
    }

    /**
     * Dispatch requisition: Release locked stock and move items into Virtual In-Transit.
     * Patched to resolve dynamic substitute variants.
     */
    public function dispatchTransfer(int $requisitionId): void
    {\n        DB::transaction(function () use ($requisitionId) {
            $requisition = TransferRequisition::with('items')->findOrFail($requisitionId);

            if ($requisition->status !== 'confirmed') {
                throw new Exception("Requisition must be confirmed before dispatch.");
            }

            foreach ($requisition->items as $item) {
                $dispatchQty = $item->approved_base_qty ?? $item->requested_base_qty;

                if ($dispatchQty <= 0) {
                    throw new Exception("Dispatch quantity must be strictly greater than zero.");
                }

                // Guardrail 4: Substitute Variant Resolution
                $actualVariantId = $item->substitute_variant_id ?? $item->variant_id;

                $stock = WarehouseStock::where('variant_id', $item->variant_id) // Reservation was locked on original
                    ->where('warehouse_id', $requisition->from_warehouse_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // If substitute is active, physical stock adjustment applies to substitute row
                if ($actualVariantId !== $item->variant_id) {
                    $subStock = WarehouseStock::firstOrCreate(
                        ['variant_id' => $actualVariantId, 'warehouse_id' => $requisition->from_warehouse_id],
                        ['on_hand_quantity' => 0, 'reserved_quantity' => 0]
                    );
                    $subStockRow = WarehouseStock::where('id', $subStock->id)->lockForUpdate()->first();
                    
                    if ($subStockRow->on_hand_quantity < $dispatchQty) {
                        throw new Exception("Insufficient physical stock for substituted variant {$actualVariantId} at origin.");
                    }
                    
                    // Release reservation on original row, deduct physical stock from substituted row
                    $stock->reserved_quantity -= $dispatchQty;
                    $stock->save();

                    $subStockRow->on_hand_quantity -= $dispatchQty;
                    $subStockRow->save();
                } else {
                    $stock->reserved_quantity -= $dispatchQty;
                    $stock->on_hand_quantity -= $dispatchQty;
                    $stock->save();
                }

                StockMovement::create([
                    'variant_id' => $actualVariantId,
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
                    'requisition_id' => $requisition->id,
                    'requisition_item_id' => $item->id,
                    'variant_id' => $actualVariantId,
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

    /**
     * Scan-to-Receive pipeline with loss accounting and omitted items safety rules.
     * Patched to support substitute variants and capture transit leak vulnerabilities.
     */
    public function scanToReceive(int $requisitionId, array $receivedItemsData): void
    {\n        DB::transaction(function () use ($requisitionId, $receivedItemsData) {
            $requisition = TransferRequisition::with('items')->findOrFail($requisitionId);

            $hasLossOrDamage = false;

            foreach ($requisition->items as $item) {
                // Guardrail 4: Substitute Variant Resolution
                $actualVariantId = $item->substitute_variant_id ?? $item->variant_id;
                $ratio = $item->approved_unit_ratio ?? $item->requested_unit_ratio;
                $expectedBase = $item->shipped_base_qty;

                // Guardrail 6: Scanned Receipt Loss Integrity & Omitted Items
                if (! isset($receivedItemsData[$item->id])) {
                    // Item was completely missing/omitted during intake
                    $goodBase = 0;
                    $damagedBase = 0;
                } else {
                    $entry = $receivedItemsData[$item->id];
                    $goodBase = $entry['good_qty'] * $ratio;
                    $damagedBase = $entry['damaged_qty'] * $ratio;
                }

                $lostBase = max(0, $expectedBase - ($goodBase + $damagedBase));

                if ($goodBase > 0) {
                    $this->recordMovement(
                        variantId: $actualVariantId,
                        warehouseId: $requisition->to_warehouse_id,
                        type: 'transit_in',
                        baseQuantity: $goodBase,
                        unitName: $item->approved_unit_name ?? $item->requested_unit_name,
                        unitRatio: $ratio,
                        referenceType: TransferRequisition::class,
                        referenceId: $requisition->id,
                        referenceCode: $requisition->reference_code
                    );
                }

                if ($damagedBase > 0 || $lostBase > 0) {
                    $hasLossOrDamage = true;
                    $variant = ProductVariant::findOrFail($actualVariantId);

                    LossLedger::create([
                        'requisition_id' => $requisition->id,
                        'requisition_item_id' => $item->id,
                        'variant_id' => $actualVariantId,
                        'warehouse_id' => $requisition->to_warehouse_id,
                        'lost_base_qty' => $lostBase,
                        'damaged_base_qty' => $damagedBase,
                        'unit_cost_price' => $variant->cost_price,
                        'total_financial_loss' => ($lostBase + $damagedBase) * $variant->cost_price,
                        'loss_category' => isset($receivedItemsData[$item->id]) ? ($entry['loss_category'] ?? 'Transit Variance') : 'Omitted From Intake',
                        'recorded_by' => auth()->id(),
                        'recorded_at' => now(),
                    ]);
                }

                $item->update([
                    'received_good_base_qty' => $goodBase,
                    'received_damaged_base_qty' => $damagedBase,
                ]);

                InTransit::where('requisition_item_id', $item->id)->update([
                    'status' => 'cleared',
                ]);
            }

            $finalStatus = $hasLossOrDamage ? 'closed_with_loss' : 'completed';

            $requisition->update([
                'status' => $finalStatus,
                'received_by' => auth()->id(),
                'completed_at' => now(),
            ]);
        });
    }
}
```

---

#### 5. Filament v5 Architecture & File Structure
```
app/Filament/
├── Pages/
│   └── Dashboard.php                     ← Scope filters with role access rules
├── Resources/
│   ├── ProductResource.php
│   ├── ProductResource/
│   │   ├── Pages/
│   │   │   ├── ListProducts.php
│   │   │   ├── CreateProduct.php
│   │   │   └── EditProduct.php
│   │   └── RelationManagers/
│   │       ├── VariantsRelationManager.php  ← Micro-pricing step precision
│   │       └── ConversionsRelationManager.php
│   ├── TransferRequisitionResource.php
│   ├── TransferRequisitionResource/
│   │   ├── Pages/
│   │   │   ├── ListTransferRequisitions.php
│   │   │   ├── CreateTransferRequisition.php
│   │   │   ├── ViewTransferRequisition.php   ← Signed Scan listener launcher
│   │   │   └── EditTransferRequisition.php
│   │   └── Schemas/
│   │       └── TransferRequisitionForm.php
│   ├── InTransitResource.php             ← Eager loaded read-only monitors
│   ├── LossLedgerResource.php            ← Footer tallies & export bulk action
│   └── StockMovementResource.php          ← Eager loaded ledger histories
└── Widgets/
    ├── InventoryStatsWidget.php          ← Cached high-performance aggregators
    └── PendingRequisitionsWidget.php     ← Location-scoped action tables
```

---

#### 6. System Feature Matrix
##### 1. Multi-Variant Product Catalog
* **Hierarchical Product Mapping:** Product → Variants → Conversions → Dynamic Prices.
* **Base-Unit Engine:** Internal math strictly tracks Base Units; UI handles multi-unit conversions (1 Box = 24 Pcs).
* **Dual Display Catalog:** Toggle between high-density table view and grid catalog layout (`contentGrid()`).
##### 2. Inter-Warehouse Requisitions & Two-Way Negotiations
* **Requisition Wizard:** Create requests selecting custom packaging units while inspecting available source stock.
* **Negotiation Loop:** Fulfiller and requestor adjust quantities, swap UoMs, or introduce substitute variants.
* **Pessimistic Reservations:** Reserves allocated stock at origin warehouse (`reserved_quantity`) upon mutual confirmation.
##### 3. Dispatch, Logistics & Scan-to-Receive
* **Virtual In-Transit Bucket:** 3-Stage stock pipeline: Origin Stock → Virtual In-Transit → Destination Stock.
* **Signed STN Web QR Routing:** Scanning physical STN document opens `APP_URL` and auto-launches the **Scan-to-Receive Modal**.
* **Loss Ledger Writing:** Auto-computes good vs. damaged receiving tallies and writes write-off entries to `loss_ledger`.

---

#### 7. Build Order (16 Stages)
1. **Environment Setup & Core Rules:** Bootstrap Laravel 13, install Filament v5, establish the 10 core audit guardrails (`00-revised.md`).
2. **High-Precision Migrations:** Create 13 tables enforcing decimal(15,4) precision for unit pricing, composite unique indexes for concurrency control, and soft-delete columns on core entities (`01-revised.md`).
3. **Eloquent Model Maps & Observers:** Write mass-assignment protections and attribute casts. Wire up soft-delete observers to return reserved stock on confirmed transfer trashing (`02-revised.md`).
4. **RBAC Security Boundaries:** Set up user roles, user warehouse pivots, and policy interceptors on scanning URLs (`03-revised.md`).
5. **Core Transactional Engine:** Implement `InventoryService` classes with concurrency retries, substitute variant checking, and omitted receiving loss accounting (`04-revised.md`).
6. **Product Catalog UI & Image Handling:** Set up `ProductResource` with eager loading queries, multi-image uploads, and sub-cent pricing input fields (`05-revised.md`).
7. **Packaging & Local Pricing Matrix:** Code conversions ratios and custom branch-scoped price lists in `ProductPriceResource` (`06-revised.md`).
8. **Warehouse Operations & Note Audits:** Configure branch toggles and slide-over stock adjusters requiring strict 15-character minimum audit logs.
9. **Multi-Step Requisition Wizard:** Program step-by-step requisitions featuring dynamic, live source branch stock indicators during item selection (`07-revised.md`).
10. **Counter-Offer Negotiation Forms:** Configure state-machine actions allowing fulfilling branches to propose alternate quantities, packaging ratios, or substitute variant IDs.
11. **Stock Reservation & Shipping Execution:** Connect mutual review confirmations to pessimistic reservation locks and dispatch actions in `InventoryService` (`07-revised.md`).
12. **Printable STN & Signed QR Route:** Create printable PDFs rendering 30-day signed scan URLs as scannable barcodes (`08-revised.md`).
13. **Auto-Triggering Scan-to-Receive Modal:** Build scan-sensitive interfaces auto-mounting receiving modal windows when targeted by QR parameters (`09-revised.md`).
14. **Read-Only Auditor Portals:** Construct immutable tracking panels for `InTransitResource`, `StockMovementResource`, and high-precision decimal `LossLedgerResource` with footer sum aggregations (`10-revised.md`).
15. **Performance Engineering & Security Headers:** Configure eagerness properties to solve N+1 listing loops, compound key database indexes, and response security headers (`12-revised.md`).
16. **Role-Scoped Dashboard Widgets:** Replace default dashboard listings with location-scoped widgets and aggregate cards caching metrics for 300 seconds to protect performance (`13-revised.md`).
