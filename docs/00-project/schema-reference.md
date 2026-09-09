# Inventory & transfer module — schema reference

Generated from the migration set in `database/migrations/`. Covers 13 tables: 10 new tables, 1 pivot, 1 altered core table (`users`), plus a history table (`product_variant_prices`).

---

## Table of contents

- [products](#products)
- [product_variants](#product_variants)
- [product_variant_prices](#product_variant_prices)
- [product_variant_unit_conversions](#product_variant_unit_conversions)
- [warehouses](#warehouses)
- [stock_movements](#stock_movements)
- [transfer_requisitions](#transfer_requisitions)
- [transfer_requisition_items](#transfer_requisition_items)
- [transfer_requisition_item_revisions](#transfer_requisition_item_revisions)
- [in_transits](#in_transits)
- [loss_ledgers](#loss_ledgers)
- [users (altered)](#users-altered)
- [user_warehouse (pivot)](#user_warehouse-pivot)
- [Relationship diagram](#relationship-diagram)
- [Enum reference](#enum-reference)

---

## products

Product family — the umbrella under which sellable variants live.

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | bigint (PK) | No | — | |
| `name` | string | No | — | e.g. "Arabica Specialty Coffee" |
| `category` | string | Yes | — | |
| `deleted_at` | timestamp | Yes | — | soft deletes |
| `created_at` / `updated_at` | timestamp | Yes | — | |

**Relationships**
- `hasMany` → `product_variants` (`product_id`)

---

## product_variants

A specific sellable SKU under a product.

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | bigint (PK) | No | — | |
| `product_id` | FK → products.id | No | — | `cascadeOnDelete` |
| `sku` | string, unique | No | — | e.g. `PROD-COF-500G` |
| `barcode` | string, unique | Yes | — | scanner GTIN |
| `name` | string | No | — | e.g. "500g Whole Bean" |
| `base_unit_name` | string | No | — | lowest non-divisible unit (`gram`, `piece`) |
| `reorder_point` | integer | No | `0` | safety threshold, in base units |
| `attributes` | json | Yes | — | e.g. `{"roast": "Medium"}` |
| `images` | json | Yes | — | |
| `deleted_at` | timestamp | Yes | — | soft deletes |
| `created_at` / `updated_at` | timestamp | Yes | — | |

**Indexes:** `(product_id, sku)`

**Relationships**
- `belongsTo` → `products` (`product_id`)
- `hasMany` → `product_variant_unit_conversions`
- `hasMany` → `product_variant_prices`
- `hasOne` → `product_variant_prices` where `is_current = true` (`currentPrice`)
- `hasMany` → `stock_movements`

> `cost_price` / `sale_price` do **not** live here — see `product_variant_prices`.

---

## product_variant_prices

History table for variant pricing. Many rows per variant over time; exactly one may be flagged current.

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | bigint (PK) | No | — | |
| `product_variant_id` | FK → product_variants.id | No | — | `cascadeOnDelete` |
| `cost_price` | decimal(15,4) | No | `0.0000` | |
| `sale_price` | decimal(15,4) | No | `0.0000` | |
| `effective_from` | timestamp | No | current time | |
| `is_current` | boolean | No | `true` | app must unset the prior row |
| `set_by` | FK → users.id | Yes | — | `nullOnDelete` |
| `notes` | text | Yes | — | e.g. reason for price change |
| `created_at` / `updated_at` | timestamp | Yes | — | |

**Indexes:** `(product_variant_id, effective_from)`

**DB-level constraint:** a unique index guarantees at most one `is_current = true` row per `product_variant_id`.
- PostgreSQL: native partial unique index (`WHERE is_current = true`)
- MySQL/MariaDB: emulated via a generated column (`current_variant_key`) that collapses non-current rows to `NULL`, then a unique index on that column

**Relationships**
- `belongsTo` → `product_variants` (`product_variant_id`)
- `belongsTo` → `users` (`set_by`)

---

## product_variant_unit_conversions

Packaging conversions for a variant (e.g. 1 Box = 24 pieces).

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | bigint (PK) | No | — | |
| `product_variant_id` | FK → product_variants.id | No | — | `cascadeOnDelete` |
| `unit_name` | string | No | — | e.g. `Box`, `Pallet` |
| `base_unit_ratio` | integer | No | — | e.g. 1 Box = 24 pcs → `24` |
| `is_default_purchase` | boolean | No | `false` | |
| `is_default_transfer` | boolean | No | `false` | |
| `created_at` / `updated_at` | timestamp | Yes | — | |

**Indexes:** unique on `(product_variant_id, unit_name)` — prevents duplicate unit definitions per variant

**Relationships**
- `belongsTo` → `product_variants` (`product_variant_id`)

---

## warehouses

Physical stock locations / branches.

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | bigint (PK) | No | — | |
| `code` | string, unique | No | — | e.g. `WH-MNL` |
| `name` | string | No | — | |
| `location` | string | Yes | — | |
| `is_active` | boolean | No | `true` | |
| `created_at` / `updated_at` | timestamp | Yes | — | |

**Relationships**
- `hasMany` → `stock_movements`
- `hasMany` → `transfer_requisitions` as origin (`from_warehouse_id`)
- `hasMany` → `transfer_requisitions` as destination (`to_warehouse_id`)
- `belongsToMany` → `users` (via `user_warehouse`)

---

## stock_movements

Source-of-truth ledger for every unit of stock moving in or out.

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | bigint (PK) | No | — | |
| `product_variant_id` | FK → product_variants.id | No | — | `cascadeOnDelete` |
| `warehouse_id` | FK → warehouses.id | No | — | `cascadeOnDelete` |
| `type` | string | No | — | `StockMovementType` enum |
| `quantity` | integer | No | — | signed, base units (+in / −out) |
| `unit_name_used` | string | No | — | packaging label at time of transaction |
| `unit_ratio_used` | integer | No | `1` | |
| `related_movement_id` | FK → stock_movements.id (self) | Yes | — | `nullOnDelete`; links transfer pairs |
| `reference_type` | string | Yes | — | polymorphic source model |
| `reference_id` | string | Yes | — | polymorphic source key (string to support bigint or UUID/ULID) |
| `reference_code` | string | Yes | — | e.g. `DTR-20260908-XXXX` |
| `created_by` | FK → users.id | Yes | — | `nullOnDelete` |
| `created_at` / `updated_at` | timestamp | Yes | — | |

**Indexes:** `(product_variant_id, warehouse_id)`, `(reference_type, reference_id)`, `type`, `created_at`

**Relationships**
- `belongsTo` → `product_variants` (`product_variant_id`)
- `belongsTo` → `warehouses`
- `belongsTo` → `stock_movements` (self, `related_movement_id`)
- `belongsTo` → `users` (`created_by`)
- `morphTo` → polymorphic `reference`

---

## transfer_requisitions

A request to move stock from one warehouse to another.

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | bigint (PK) | No | — | |
| `reference_code` | string, unique | No | — | |
| `from_warehouse_id` | FK → warehouses.id | No | — | `restrictOnDelete` |
| `to_warehouse_id` | FK → warehouses.id | No | — | `restrictOnDelete` |
| `status` | string | No | `draft` | `TransferRequisitionStatus` enum |
| `requested_by` | FK → users.id | No | — | |
| `approved_by` | FK → users.id | Yes | — | |
| `dispatched_by` | FK → users.id | Yes | — | |
| `received_by` | FK → users.id | Yes | — | |
| `requested_at` | timestamp | Yes | — | |
| `approved_at` | timestamp | Yes | — | |
| `dispatched_at` | timestamp | Yes | — | |
| `completed_at` | timestamp | Yes | — | |
| `notes` | text | Yes | — | |
| `deleted_at` | timestamp | Yes | — | soft deletes |
| `created_at` / `updated_at` | timestamp | Yes | — | |

**Indexes:** `status`, `(from_warehouse_id, to_warehouse_id)`

**Relationships**
- `belongsTo` → `warehouses` (`from_warehouse_id`, `to_warehouse_id`)
- `belongsTo` → `users` (`requested_by`, `approved_by`, `dispatched_by`, `received_by`)
- `hasMany` → `transfer_requisition_items`
- `hasMany` → `in_transits`
- `hasMany` → `loss_ledgers`

---

## transfer_requisition_items

Line items within a transfer requisition.

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | bigint (PK) | No | — | |
| `transfer_requisition_id` | FK → transfer_requisitions.id | No | — | `cascadeOnDelete` |
| `product_variant_id` | FK → product_variants.id | No | — | |
| `substitute_product_variant_id` | FK → product_variants.id | Yes | — | negotiated swap SKU |
| `requested_unit_name` | string | No | — | |
| `requested_unit_ratio` | integer | No | — | |
| `requested_qty` | integer | No | — | |
| `requested_base_qty` | integer | No | — | base-unit cache |
| `approved_unit_name` | string | Yes | — | |
| `approved_unit_ratio` | integer | Yes | — | |
| `approved_qty` | integer | Yes | — | |
| `approved_base_qty` | integer | Yes | — | |
| `shipped_base_qty` | integer | No | `0` | |
| `received_good_base_qty` | integer | No | `0` | |
| `received_damaged_base_qty` | integer | No | `0` | |
| `received_qty` | integer | Yes | — | confirmed count at receipt |
| `notes` | text | Yes | — | |
| `created_at` / `updated_at` | timestamp | Yes | — | |

**Indexes:** `transfer_requisition_id`

**Relationships**
- `belongsTo` → `transfer_requisitions`
- `belongsTo` → `product_variants` (`product_variant_id`, `substitute_product_variant_id`)
- `hasMany` → `transfer_requisition_item_revisions`
- `hasMany` → `in_transits`
- `hasMany` → `loss_ledgers`

---

## transfer_requisition_item_revisions

Negotiation log for a line item — every proposed change, who made it, and its outcome.

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | bigint (PK) | No | — | |
| `transfer_requisition_item_id` | FK → transfer_requisition_items.id | No | — | `cascadeOnDelete` |
| `user_id` | FK → users.id | No | — | |
| `product_variant_id` | FK → product_variants.id | No | — | |
| `substitute_product_variant_id` | FK → product_variants.id | Yes | — | |
| `proposed_unit_name` | string | No | — | |
| `proposed_qty` | integer | No | — | |
| `proposed_base_qty` | integer | No | — | |
| `negotiation_reason` | text | Yes | — | |
| `side` | string | No | — | `NegotiationSide` enum (`fulfiller` / `requestor`) |
| `status` | string | No | `pending` | `RevisionStatus` enum |
| `responds_to_revision_id` | FK → transfer_requisition_item_revisions.id (self) | Yes | — | `nullOnDelete`; null = opening proposal |
| `responded_at` | timestamp | Yes | — | when status left `pending` |
| `created_at` / `updated_at` | timestamp | Yes | — | |

**Indexes:** `transfer_requisition_item_id`, `(transfer_requisition_item_id, status)`

**Relationships**
- `belongsTo` → `transfer_requisition_items`
- `belongsTo` → `users`
- `belongsTo` → `product_variants` (`product_variant_id`, `substitute_product_variant_id`)
- `belongsTo` → `transfer_requisition_item_revisions` (self, `responds_to_revision_id` → `respondsTo`)
- `hasMany` → `transfer_requisition_item_revisions` (self, inverse → `counters`)

---

## in_transits

Dispatched-but-not-yet-fully-received stock for a transfer item.

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | bigint (PK) | No | — | |
| `transfer_requisition_id` | FK → transfer_requisitions.id | No | — | `cascadeOnDelete` |
| `transfer_requisition_item_id` | FK → transfer_requisition_items.id | No | — | `cascadeOnDelete` |
| `product_variant_id` | FK → product_variants.id | No | — | |
| `dispatched_base_qty` | integer | No | — | |
| `dispatched_at` | timestamp | No | — | |
| `status` | string | No | `in_transit` | `InTransitStatus` enum |
| `created_at` / `updated_at` | timestamp | Yes | — | |

**Indexes:** `(transfer_requisition_id, status)`

**Relationships**
- `belongsTo` → `transfer_requisitions`
- `belongsTo` → `transfer_requisition_items`
- `belongsTo` → `product_variants` (`product_variant_id`)

---

## loss_ledgers

Financial write-off records for shortfall or damage during a transfer.

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | bigint (PK) | No | — | |
| `transfer_requisition_id` | FK → transfer_requisitions.id | No | — | |
| `transfer_requisition_item_id` | FK → transfer_requisition_items.id | Yes | — | |
| `product_variant_id` | FK → product_variants.id | No | — | |
| `warehouse_id` | FK → warehouses.id | No | — | destination site bearing the loss |
| `lost_base_qty` | integer | No | `0` | |
| `damaged_base_qty` | integer | No | `0` | |
| `unit_cost_price` | decimal(15,4) | No | — | snapshot of variant cost at incident time |
| `total_financial_loss` | decimal(15,4) | No | — | |
| `loss_category` | string | No | `shortfall` | |
| `recorded_by` | FK → users.id | Yes | — | |
| `recorded_at` | timestamp | No | current time | |
| `created_at` / `updated_at` | timestamp | Yes | — | |

**Indexes:** `(warehouse_id, recorded_at)`

**Relationships**
- `belongsTo` → `transfer_requisitions`
- `belongsTo` → `transfer_requisition_items`
- `belongsTo` → `product_variants` (`product_variant_id`)
- `belongsTo` → `warehouses`
- `belongsTo` → `users` (`recorded_by`)

> `unit_cost_price` is sourced from the variant's current `product_variant_prices` row at write time — `ProductVariant.cost_price` no longer exists.

---

## users (altered)

Adds RBAC support to the existing `users` table.

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `role` | string | No | `warehouse_staff` | inserted after `password` |

**Relationships**
- `belongsToMany` → `warehouses` (via `user_warehouse`)
- Referenced by `product_variant_prices.set_by`, `stock_movements.created_by`, `transfer_requisitions.*_by`, `transfer_requisition_item_revisions.user_id`, `loss_ledgers.recorded_by`

---

## user_warehouse (pivot)

Many-to-many assignment of users to warehouses.

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `user_id` | FK → users.id | No | — | `cascadeOnDelete`, part of composite PK |
| `warehouse_id` | FK → warehouses.id | No | — | `cascadeOnDelete`, part of composite PK |

**Primary key:** composite `(user_id, warehouse_id)`

---

## Relationship diagram

```
products ──< product_variants ──< product_variant_prices
                    │        └──< product_variant_unit_conversions
                    │        └──< stock_movements >── warehouses
                    │
                    └──< transfer_requisition_items >── transfer_requisitions >── warehouses (from/to)
                                │        │                        │
                                │        └──< in_transits          ├──< in_transits
                                │        └──< loss_ledgers          └──< loss_ledgers
                                │
                                └──< transfer_requisition_item_revisions (self-referencing thread)

users ──< user_warehouse >── warehouses
users ──< (requested_by / approved_by / dispatched_by / received_by) transfer_requisitions
users ──< (created_by) stock_movements
users ──< (set_by) product_variant_prices
users ──< (recorded_by) loss_ledgers
users ──< (user_id) transfer_requisition_item_revisions
```

---

## Enum reference

| Enum | Backing column(s) | Cases |
|---|---|---|
| `TransferRequisitionStatus` | `transfer_requisitions.status` | `draft`, `requested`, `under_review_fulfiller`, `under_review_requestor`, `confirmed`, `dispatched`, `partially_received`, `completed`, `closed_with_loss`, `cancelled` |
| `InTransitStatus` | `in_transits.status` | `in_transit`, `partially_received`, `cleared` |
| `StockMovementType` | `stock_movements.type` | `receive`, `ship`, `transfer_out`, `transfer_in`, `transit_out`, `transit_in`, `adjustment`, `loss` |
| `NegotiationSide` | `transfer_requisition_item_revisions.side` | `fulfiller`, `requestor` |
| `RevisionStatus` | `transfer_requisition_item_revisions.status` | `pending`, `accepted`, `rejected`, `superseded` |

All five are string columns backed by PHP enums (Laravel 13 native enum casting) rather than DB-level `enum()` columns, so adding a new case never requires a schema migration.
