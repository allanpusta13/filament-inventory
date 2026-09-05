# Multi-Warehouse Inventory System — Blueprint v3
**Stack:** Laravel 13 + FilamentPHP v5

> **Lineage:** v1 = basic flat-product system (superseded). v2 = adopted
> `docs/00-project/master-sidebar-resource-map-v5.md` with gaps closed (variants, requisitions,
> loss ledger, 4-role RBAC — see `docs/00-project/map-gap-closure.md`). **v3 (this doc)** adds
> Review & Verify wizard steps and the printable/scannable Stock Transfer Note
> (STN) manifest system from `docs/00-project/transfer-enhancements-guide.md`, with council-
> mandated fixes applied. This is the current, largest scope. If this is more
> than intended, v1 is the fallback for a genuinely basic build.

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
| timestamps | | |

### `product_variants`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| product_id | FK → products | |
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

### `warehouses`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| code | string, unique | e.g. WH-MNL |
| name | string | |
| location | string, nullable | |
| is_active | boolean, default true | |
| timestamps | | |

### `stock_movements` (source of truth — no stored balance table)
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| variant_id | FK → product_variants | |
| warehouse_id | FK → warehouses | |
| type | enum: receive, ship, transfer_in, transfer_out, adjustment, loss | |
| quantity | integer, **signed**, base units | + in, − out |
| related_movement_id | FK → stock_movements, nullable | links transfer pairs — this is the join used to render the Direct Transfer manifest |
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
| status | enum: draft, requested, under_review_fulfiller, under_review_requestor, confirmed, dispatched, completed, closed_with_loss, cancelled | |
| requested_by | FK → users | |
| approved_by | FK → users, nullable | |
| dispatched_by | FK → users, nullable | |
| received_by | FK → users, nullable | **new in v3** — who confirmed receipt (scan or manual) |
| requested_at | timestamp | |
| dispatched_at | timestamp, nullable | |
| completed_at | timestamp, nullable | |
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
| approved_qty | integer, nullable | |
| approved_base_qty | integer, nullable | |
| received_qty | integer, nullable | **new in v3** — actual quantity confirmed at receipt |
| timestamps | | |

### `requisition_item_revisions`
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
| negotiation_reason | text, nullable | |
| timestamps | | |

### `loss_ledgers`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| requisition_id | FK → transfer_requisitions | |
| variant_id | FK → product_variants | |
| warehouse_id | FK → warehouses | |
| lost_base_qty | integer | |
| damaged_base_qty | integer, default 0 | |
| unit_cost_price | decimal(12,4) | |
| total_financial_loss | decimal(14,4) | |
| loss_category | string, default 'shortfall' | |
| timestamps | | |

### `users` (extended)
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

| Group | Resource | Visibility |
|---|---|---|
| Home | Dashboard | All |
| **CATALOG** | ProductResource | All |
| **OPERATIONS** | TransferRequisitionResource | All (scoped) |
| | DirectTransferResource | All (scoped) |
| **AUDIT LEDGERS** | InTransitResource | Admin, Auditor, Branch Manager |
| | StockMovementResource | Admin, Auditor, Branch Manager |
| | LossLedgerResource | Admin, Auditor |
| **SYSTEM ADMIN** | WarehouseResource | Admin only |
| | UserResource | Admin only |

---

## 4. Core Services

### `InventoryService`
- `recordMovement()` — the one primitive every mutation goes through
- `currentQuantity()` — SUM lookup
- `executeDirectTransfer()` — instant two-leg transfer
- `dispatchRequisition()` — moves stock out of origin, marks `dispatched`
- `receiveRequisition(TransferRequisition $requisition, array $receivedQuantities, ?int $receivedBy = null)` — moves stock into destination, detects shortfall, triggers loss recording, marks `completed`/`closed_with_loss`. **This is the single method called by both the manual "Confirm Receipt" table action AND the scan-to-receive flow — no duplicate logic path.**
- `ship()` — guarded single-warehouse deduction

### `LossLedgerService`
- `record()` — creates a `LossLedger` row from a requisition item shortfall

Full bodies in `docs/00-project/map-gap-closure.md`.

---

## 5. Transfer Requisition State Machine

```
draft → requested → under_review_fulfiller ⇄ under_review_requestor → confirmed → dispatched → completed
                                                                                              ↘ closed_with_loss
requested/under_review_* → cancelled (either party)
```

Dispatch and receive both go through `InventoryService`. Receipt can be triggered two ways (Section 6): manual table action, or QR scan — both funnel into the same `receiveRequisition()` call.

---

## 6. v3 Additions: Review & Verify + STN Manifest + Scan-to-Receive

### 6.1 Review & Verify Wizard Step

Both `TransferRequisitionForm` and `DirectTransferForm` gain a final wizard step rendering a live summary (via reactive `$get()` Placeholders) before submission — item lines, computed base quantities, origin/destination, and (for Direct Transfer) the audit compliance note. Purely a UX safeguard; no new data captured.

**Council-mandated fix applied:** `DirectTransferForm.php` must import `Filament\Schemas\Schema` (not `Filament\Forms\Form`) to match the rest of the v5 codebase — the original enhancement doc had this wrong in one file.

### 6.2 Printable STN Manifest

**For Transfer Requisitions:** `STNManifestController::print()` renders `pdf.stn-manifest` from the real `TransferRequisition` record — metadata, item table (with substitution badges), and a QR code linking to a 30-day signed scan-to-receive URL.

**For Direct Transfers (new in v3):** since `DirectTransferResource` has no persisted "document" model — only the linked pair of `StockMovement` rows (`transfer_out` + `transfer_in` via `related_movement_id`) — the manifest needs its **own controller method and its own Blade template** (`pdf.direct-transfer-manifest`), sourcing warehouse/variant/quantity/operator directly from the movement pair rather than reusing the requisition template:

```php
public function printDirectTransfer(Request $request, StockMovement $movement)
{
    abort_unless($movement->type === 'transfer_out', 404);

    $user = auth()->user();
    $inLeg = StockMovement::where('related_movement_id', $movement->id)->first();

    if (!$user->canAccessWarehouse($movement->warehouse) && !$user->canAccessWarehouse($inLeg->warehouse)) {
        abort(403);
    }

    return view('pdf.direct-transfer-manifest', [
        'outLeg' => $movement->load('variant', 'warehouse', 'creator'),
        'inLeg' => $inLeg->load('warehouse'),
    ]);
}
```

No QR/scan-to-receive on Direct Transfers — they're instant and atomic already, there's nothing pending to "receive."

**Signature blocks are physical-paper backup only** (council condition #4) — the printed sign-off lines are not the system of record; the scan (or manual confirm action) is. The manifest should say so explicitly in a footer note.

### 6.3 Scan-to-Receive (built in full, per your decision)

**Route:**
```php
Route::get('/stn/{transferRequisition}/scan', [ScanReceiptController::class, 'show'])
    ->name('stn.scan')
    ->middleware(['signed', 'auth']);
```

**Controller — lands on a confirm page, does not auto-execute (council condition #2):**
```php
class ScanReceiptController extends Controller
{
    public function show(Request $request, TransferRequisition $requisition)
    {
        abort_unless($request->hasValidSignature(), 403, 'Invalid or expired scan link.');

        $user = auth()->user();
        if (!$user->canAccessWarehouse($requisition->toWarehouse)) {
            abort(403, 'You are not authorized to receive at this warehouse.');
        }

        abort_unless($requisition->status === 'dispatched', 409, 'This requisition is not awaiting receipt.');

        return view('scan.confirm-receipt', [
            'requisition' => $requisition->load('items.variant', 'fromWarehouse', 'toWarehouse'),
        ]);
    }

    public function confirm(Request $request, TransferRequisition $requisition, InventoryService $service)
    {
        abort_unless($request->hasValidSignature(), 403);
        $user = auth()->user();
        abort_unless($user->canAccessWarehouse($requisition->toWarehouse), 403);

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.item_id' => 'required|exists:requisition_items,id',
            'items.*.received_qty' => 'required|integer|min:0',
        ]);

        $receivedQuantities = collect($validated['items'])
            ->mapWithKeys(fn ($row) => [$row['item_id'] => (int) $row['received_qty']])
            ->toArray();

        $service->receiveRequisition($requisition, $receivedQuantities, receivedBy: $user->id);

        return redirect()->route('stn.scan', $requisition)->with('status', 'Receipt confirmed.');
    }
}
```

The confirm page (`scan.confirm-receipt`) shows a form pre-filled with expected quantities per item (same UI pattern as the manual "Confirm Receipt" Filament action from the gap closure) and posts to a `confirm` route using the same signed URL. Requires the scanning device to have an authenticated session with `branch_manager` (or `admin`) role — a plain warehouse_staff scan is rejected by `canAccessWarehouse()` unless staff are also granted this action explicitly (your call if that's needed later).

**QR generation** (`simplesoftwareio/simple-qrcode`, approved):
```bash
composer require simplesoftwareio/simple-qrcode
```
```php
$signedUrl = URL::temporarySignedRoute('stn.scan', now()->addDays(30), ['transferRequisition' => $requisition->id]);
$qrCodeSvg = QrCode::size(120)->generate($signedUrl);
```

---

## 7. Filament v5 File Structure

```
app/Filament/Resources/{Name}/
├── {Name}Resource.php
├── Pages/
├── Schemas/           ← Filament\Schemas\Schema everywhere, including DirectTransferForm
├── Tables/
├── Infolists/          (TransferRequisitions only)
└── RelationManagers/
```

Plus, outside Filament:
```
app/Http/Controllers/
├── STNManifestController.php       (print requisition + print direct transfer)
└── ScanReceiptController.php       (show confirm page + process confirm)

resources/views/
├── pdf/stn-manifest.blade.php
├── pdf/direct-transfer-manifest.blade.php
└── scan/confirm-receipt.blade.php
```

---

## 8. Build Order (17 stages)

1. Laravel 13 + Filament v5 install, admin panel, first admin user
2. Migrations: products, product_variants, product_unit_conversions, warehouses, stock_movements, user role column, user_warehouse
3. Models + relationships, `ProductVariant::onHandQuantity()/reservedQuantity()/availableQuantity()`
4. `UserRole` enum + helper methods on `User`
5. `InventoryService` v1 (recordMovement, currentQuantity, executeDirectTransfer, ship)
6. `ProductResource` + Variants/Conversions relation managers
7. `WarehouseResource` + WarehouseStocks (computed) + Users relation managers
8. `UserResource` + Warehouses relation manager
9. `DirectTransferResource` — wizard with Routing → Allocation → **Review & Verify** steps (Schema import fixed)
10. Migrations: transfer_requisitions (+ `received_by`), requisition_items (+ `received_qty`), requisition_item_revisions + models
11. `TransferRequisitionResource` — wizard with Routing → Manifest → **Review & Verify** steps, Infolist, Revisions relation manager
12. Extend `InventoryService`: `dispatchRequisition()`, `receiveRequisition()` (now accepting `receivedBy`); migration + model + `LossLedgerService`
13. `InTransitResource` with manual "Confirm Receipt" action; `StockMovementResource`; `LossLedgerResource`
14. RBAC scoping pass across all resources
15. `STNManifestController::print()` for requisitions + `pdf.stn-manifest.blade.php`, wired to `ViewTransferRequisition` header action
16. `STNManifestController::printDirectTransfer()` + `pdf.direct-transfer-manifest.blade.php`, wired to `DirectTransferResource` table row action; install `simplesoftwareio/simple-qrcode`
17. `ScanReceiptController` (show + confirm), `stn.scan` signed route, `scan/confirm-receipt.blade.php`, QR embedded in the requisition manifest

---

## 9. Notes

- Prompts in `prompts/` cover v1 only. A v3-matching prompt set (17 stages) would need to be written separately — say the word and I'll generate it.
- `docs/00-project/map-gap-closure.md` still holds the full reasoning/code for the v2 fixes (Schema API, derived stock, canonical service, loss ledger, RBAC, in-transit closure) — nothing there is superseded by v3, only extended.
- Four council conditions from the v3 review are folded directly into this doc: Schema import fix (6.1), scan lands on confirm page not auto-execute (6.3), Direct Transfer manifest uses its own template sourced from the StockMovement pair (6.2), signatures documented as backup not source of truth (6.2).