# Multi-Warehouse Inventory System — Complete System Blueprint (v13.6 + i18n)

**Stack:** Laravel 13 + FilamentPHP v5 + Livewire v4 | **Database:** PostgreSQL / MySQL

**Architecture:** Pure Derived Stock of Truth Ledger + Purchases & Sales Module

---

> **Changelog from v13.5:**
> 1. **Added** Principle **1B.1a** — Navigation badges are scoped by **role + warehouse assignment**, not by warehouse membership alone.
> 2. **Added** `App\Filament\Support\Concerns\ScopesNavigationBadges` trait — single canonical resolver for badge warehouse scoping.
> 3. **Rewrote** Section 1B badge principle to formalize four scope tiers: Admin/Auditor (unscoped), Warehouse Staff with N≥2 (union of assigned warehouses), Warehouse Staff with exactly 1 (that single warehouse only), Warehouse Staff with 0 (null badge).
> 4. **Rewrote** `TransferRequisitionResource`, `PurchaseOrderResource`, `SalesOrderResource`, and `InTransitResource` badge implementations to delegate to the shared trait.
> 5. **Extended** Section 12 test plan with `BadgeScopeTest::*` and `NavigationBadgeRoleScopeTest::*`.
> 6. **Extended** Section 14 checklist with badge role-scope invariants.
> 7. **Extended** Section 23 runtime completeness tests to include badge-scope resolution.
> 8. **Extended** Section 26 acceptance criteria with explicit badge role-scoping items.
> 9. **Extended** Section 27 with the v13.6 change table.

> **Changelog from v13.4 (retained):**
> 1. **Added** Principle **A11** — Direct Transfers are multi-line fire-and-forget operations.
> 2. **Rebound** `DirectTransferResource` from `StockMovement` to new `DirectTransfer` header model.
> 3. **Added** `direct_transfers` and `direct_transfer_items` tables (§2 grows from 20 to 22 tables).
> 4. **Added** `DirectTransfer` and `DirectTransferItem` models.
> 5. **Rewrote** `InventoryService::directTransfer()` — now accepts an items array and writes one paired `TransferOut`/`TransferIn` per line inside a single transaction.
> 6. **Rewrote** `DirectTransferForm` — `Repeater::make('items')` replaces flat single-item Section.
> 7. **Added** `DirectTransferInfolist` and `ViewDirectTransfer` page.
> 8. **Promoted** `DirectTransfersTable` from standard ledger table to card layout (§7N.4).
> 9. **Added** `DirectTransferPolicy`; removed `StockMovementPolicy::createDirectTransfer()`.
> 10. **Extended** `WarehousePolicy::delete()` — now also blocks warehouses referenced by direct transfers.
> 11. **Added** `Warehouse::directTransfersFrom()` / `directTransfersTo()` relations.
> 12. **Added** `DirectTransferFactory` and `DirectTransferItemFactory`.
> 13. **Updated** §17.4 policy registration, §20.3 authorization, §25 file map, §26 acceptance criteria.

---

## 🌐 Section 0A: Internationalization (i18n) & Translation Standard

### Council Decision

**Internationalization is a mandatory cross-cutting requirement, not a later enhancement.** Every user-facing string in the application must be translatable. This includes the complete Filament surface and all domain-facing messages. The blueprint therefore treats translation coverage as part of implementation completeness and QA acceptance.

The existing architecture already establishes multi-language intent through backed enums routing `getLabel()` through `__()`. This blueprint extends that rule to every model presentation surface, form, table, heading, action, widget, navigation item, notification, validation message, review component, and exception message that can reach a user.

### 0A.1 Translation Boundary

The following are **user-facing and MUST be translated**:

1. Model singular labels.
2. Model plural labels.
3. Navigation labels and navigation group labels.
4. Resource page headings and subheadings.
5. Section headings and descriptions.
6. Tabs and tab labels.
7. Wizard step labels and descriptions.
8. Form field labels.
9. Form helper text.
10. Form hint text and suffix/prefix explanatory text.
11. Placeholders.
12. Table column labels/headings.
13. Table filter labels and filter option labels.
14. Table empty-state headings, descriptions, and actions.
15. Table bulk/action labels.
16. Record action labels, confirmations, descriptions, and modal headings.
17. Dashboard widget headings, descriptions, legends, empty states, and metric labels.
18. Infolist labels, section headings, entries, and empty-state text.
19. Enum labels, colors remain code-level presentation metadata but labels MUST be translated.
20. Notifications, success messages, warning messages, and failure messages.
21. Domain validation messages exposed to users.
22. Authorization-denied messages exposed to users.
23. Domain exception messages that can escape to the UI.
24. Import/export headings and column labels.
25. QR/scan instructions and scan-result messages.
26. Print/document headings and labels.
27. Confirmation dialogs and destructive-action warnings.
28. Review summaries rendered by Blade/Livewire components.
29. Navigation badge tooltips.
30. Dashboard and resource descriptions.
31. Search/filter empty-state messages.
32. Accessibility labels, tooltips, and screen-reader-only text.
33. Audit-log/event descriptions if displayed to users.
34. Email, notification, and queued-message content if introduced.

The following are **not translated**:

- Database table names.
- Database column names.
- Route names and route parameter names.
- PHP class names, namespaces, method names, enum case names, and service names.
- Internal identifiers and reference codes such as `PO-...`, `SO-...`, `TR-...`, or `DT-...`.
- SKU values, barcode values, QR payload identifiers, and other stored business data unless a separate product requirement explicitly makes that data translatable.
- Enum backing values stored in the database.
- API field names and machine-readable event names.

### 0A.2 Locale Architecture

Use Laravel's standard translation system as the source of truth. The blueprint does not hard-code a finite list of supported locales; supported locales are configuration-driven.

Required structure:

```text
lang/
├── en/
│   ├── actions.php
│   ├── attributes.php
│   ├── enums.php
│   ├── forms.php
│   ├── navigation.php
│   ├── notifications.php
│   ├── resources.php
│   ├── tables.php
│   ├── validation.php
│   ├── widgets.php
│   ├── errors.php
│   ├── common.php
│   ├── wizards.php       # __('wizards.*') — §18.3 review views
│   ├── stn.php           # __('stn.*') — §21 print/scan views
│   └── dashboard.php     # __('dashboard.*') — §10 widgets
└── <locale>/
    └── same file set as en/
```

`en` is the canonical source locale unless the project configuration explicitly changes the source locale. Every additional locale MUST contain the same semantic key set.

Do not scatter ad-hoc translation strings across unrelated language files when a domain-specific translation file already exists. Keep translation keys stable and semantic so changing wording does not require changing PHP code.

### 0A.2a Canonical Catalogue Contents (en Source Locale)

Every `__()` key referenced by this blueprint MUST resolve. The canonical `en` contents below define the full key set — every key referenced anywhere in this document appears exactly once. Every additional locale MUST carry the same key set with translated values (§0A.15 `TranslationKeyParityTest`). Values below are the canonical-locale renderings.

`lang/en/actions.php`:

```php
return [
    'confirm' => 'Confirm',
    'cancel'  => 'Cancel',
    'more'    => 'More',
];
```

`lang/en/common.php`:

```php
return [
    'save'    => 'Save',
    'close'   => 'Close',
    'search'  => 'Search',
    'copied'  => 'Copied',
    'active'  => 'Active',
    'inactive' => 'Inactive',
    'date'    => 'Date',
    'from'    => 'From',
    'until'   => 'Until',
    'period'  => 'Period',
    'warehouse' => 'Warehouse',
    // Shared empty-state placeholder for tables, infolists, and display
    // fallbacks (§0A.16): the em dash itself is locale-neutral, but every
    // user-facing placeholder must resolve through a translation key
    // rather than a raw string.
    'empty'   => '—',
    'periods' => [
        'today'          => 'Today',
        'this_week'      => 'This week',
        'this_month'     => 'This month',
        'this_year'      => 'This year',
        'on_date'        => 'On :date',
        'specific_date'  => 'Specific date',
        'from_date'      => 'From :date',
        'until_date'     => 'Until :date',
        'custom_range'   => 'Custom range',
        'no_lower_bound' => 'No lower bound',
        'no_upper_bound' => 'No upper bound',
    ],
];
```

`lang/en/navigation.php`:

```php
return [
    'groups' => [
        'catalog'       => 'Catalog',
        'operations'    => 'Operations',
        'purchasing'    => 'Purchasing',
        'sales'         => 'Sales',
        'audit_ledgers' => 'Audit Ledgers',
        'system_admin'  => 'System Admin',
    ],
];
```

`lang/en/enums.php`:

```php
return [
    'transfer_requisition_status' => [
        'draft'                   => 'Draft',
        'requested'               => 'Requested',
        'under_review_fulfiller'  => 'Under review (fulfiller)',
        'under_review_requestor'  => 'Under review (requestor)',
        'confirmed'               => 'Confirmed',
        'dispatched'              => 'Dispatched',
        'partially_received'      => 'Partially received',
        'completed'               => 'Completed',
        'closed_with_loss'        => 'Closed with loss',
        'cancelled'               => 'Cancelled',
    ],
    'purchase_order_status' => [
        'draft'              => 'Draft',
        'ordered'            => 'Ordered',
        'partially_received' => 'Partially received',
        'received'           => 'Received',
        'cancelled'          => 'Cancelled',
    ],
    'sales_order_status' => [
        'draft'                => 'Draft',
        'confirmed'            => 'Confirmed',
        'partially_dispatched' => 'Partially dispatched',
        'dispatched'           => 'Dispatched',
        'cancelled'            => 'Cancelled',
    ],
    'stock_movement_type' => [
        'transfer_in'    => 'Transfer in',
        'transfer_out'   => 'Transfer out',
        'purchase'       => 'Purchase',
        'purchase_return' => 'Purchase return',
        'sale'           => 'Sale',
        'sale_return'    => 'Sale return',
        'adjustment'     => 'Adjustment',
        'loss'           => 'Loss',
        'damage'         => 'Damage',
    ],
    'revision_status' => [
        'pending'  => 'Pending',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
    ],
    'negotiation_side' => [
        'requestor' => 'Requestor',
        'fulfiller' => 'Fulfiller',
    ],
    'in_transit_status' => [
        'in_transit' => 'In transit',
        'cleared'    => 'Cleared',
        'lost'       => 'Lost',
    ],
    'loss_category' => [
        'shortfall' => 'Shortfall',
        'damage'    => 'Damage',
        'spoilage'  => 'Spoilage',
        'theft'     => 'Theft',
        'other'     => 'Other',
    ],
    'user_role' => [
        'admin'           => 'Admin',
        'auditor'         => 'Auditor',
        'warehouse_staff' => 'Warehouse staff',
    ],
];
```

`lang/en/notifications.php`:

```php
return [
    'transfer_confirmed' => ['body' => 'Transfer :reference has been confirmed.'],
    'transfer_cancelled' => ['body' => 'Transfer :reference has been cancelled.'],
    'transfer_dispatched' => ['body' => 'Transfer :reference has been dispatched.'],
    'transfer_received' => ['body' => 'Transfer :reference has been received.'],
    'loss_recorded' => ['body' => 'Loss of :qty base units recorded for :sku.'],
    'purchase_order_received' => ['body' => 'Purchase order :reference has been received.'],
    'sales_order_dispatched' => ['body' => 'Sales order :reference has been dispatched.'],
    'inventory_below_reorder_point' => ['body' => ':sku is below its reorder point in :warehouse.'],
];
```

`lang/en/validation.php`:

```php
return [
    'insufficient_stock' => 'Insufficient stock for :sku in :warehouse (requested :requested, available :available).',
];
```

`lang/en/errors.php` — every key carries `.title` / `.body` per §0A.10; keys and context per the §6.3 key catalogue:

```php
return [
    'insufficient_stock'           => ['title' => 'Insufficient stock', 'body' => 'Requested :requested, available :available for variant :variant.'],
    'outstanding_quantity_exceeded' => ['title' => 'Outstanding quantity exceeded', 'body' => 'Attempted :attempted against outstanding :outstanding for item :item.'],
    'invalid_document_state'       => ['title' => 'Invalid document state', 'body' => 'Action :action is not allowed while status is :status.'],
    'invalid_revision_transition'  => ['title' => 'Invalid revision transition', 'body' => 'Cannot move revision from :actual to :target.'],
    'product_family_has_variants'  => ['title' => 'Product family has variants', 'body' => 'Product :product still has variants and cannot be deleted.'],
    'negotiation_not_allowed'      => ['title' => 'Negotiation not allowed', 'body' => 'Requisition :requisition cannot be negotiated while :status.'],
    'revision_already_resolved'    => ['title' => 'Revision already resolved', 'body' => 'Revision :revision has already been resolved.'],
    'invalid_movement_type'        => ['title' => 'Invalid movement type', 'body' => 'Movement type :type is not allowed here.'],
    'invalid_unit_ratio'           => ['title' => 'Invalid unit ratio', 'body' => 'Unit ratio :ratio is invalid.'],
    'same_warehouse_transfer'      => ['title' => 'Same warehouse transfer', 'body' => 'Source and destination warehouse :warehouse must differ.'],
    'empty_transfer_items'         => ['title' => 'Empty transfer', 'body' => 'A direct transfer requires at least one line item.'],
    'duplicate_transfer_variant'     => ['title' => 'Duplicate transfer variant', 'body' => 'Line :index repeats variant :variant; a transfer contains distinct variants only.'],
    'empty_purchase_items'           => ['title' => 'Empty purchase order', 'body' => 'A purchase order requires at least one line item.'],
    'empty_purchase_receipt'         => ['title' => 'Empty purchase receipt', 'body' => 'No receipt quantities were provided.'],
    'empty_sales_items'              => ['title' => 'Empty sales order', 'body' => 'A sales order requires at least one line item.'],
    'empty_sales_dispatch'           => ['title' => 'Empty sales dispatch', 'body' => 'No dispatch quantities were provided.'],
    'empty_requisition_items'        => ['title' => 'Empty requisition', 'body' => 'A transfer requisition requires at least one manifest item.'],
    'invalid_return_quantity'        => ['title' => 'Invalid return quantity', 'body' => 'Return quantity :qty for item :item must be at least 1.'],
    'missing_item_field'           => ['title' => 'Missing item field', 'body' => 'Line :index is missing :field.'],
    'invalid_item_quantity'        => ['title' => 'Invalid item quantity', 'body' => 'Line :index has invalid quantity :qty.'],
    'unknown_variant'              => ['title' => 'Unknown variant', 'body' => 'Variant :variant does not exist.'],
    'undefined_unit'               => ['title' => 'Undefined unit', 'body' => 'Unit :unit is not defined for variant :variant.'],
    'unit_ratio_mismatch'          => ['title' => 'Unit ratio mismatch', 'body' => 'Line :index declares unit :unit with a wrong ratio.'],
    'unknown_requisition_item'     => ['title' => 'Unknown requisition item', 'body' => 'Item :item does not belong to this requisition.'],
    'non_integer_payload'          => ['title' => 'Non-integer payload', 'body' => 'Scan payload for item :item must be an integer.'],
'negative_payload' => ['title' => 'Negative payload', 'body' => 'Payload for item :item must not be negative.'],
    'empty_loss'                   => ['title' => 'Empty loss', 'body' => 'Loss entry for item :item records zero quantity.'],
    'invalid_proposed_quantity'    => ['title' => 'Invalid proposed quantity', 'body' => 'Proposed quantity :qty is invalid.'],
    'cross_item_revision'          => ['title' => 'Cross-item revision', 'body' => 'Revision :revision does not belong to item :item.'],
    'missing_approved_quantity'    => ['title' => 'Missing approved quantity', 'body' => 'No approved quantity is set.'],
    'warehouse_out_of_scope'       => ['title' => 'Warehouse out of scope', 'body' => 'Warehouses :from / :to are outside your assignment.'],
    'warehouse_code_exhausted'     => ['title' => 'Warehouse code exhausted', 'body' => 'Could not derive a unique code for :name.'],
];
```

`lang/en/dashboard.php`:

```php
return [
    'stats' => [
        'on_hand'   => 'On hand',
        'in_transit' => 'In transit',
        'pending'   => 'Pending',
        'write_off' => 'Write-off',
        'warehouse' => 'Warehouse',
    ],
    'charts' => [
        'available'     => 'Available',
        'dispatched'    => 'Dispatched',
        'net_movement'  => 'Net movement',
        'purchases'     => 'Purchases',
        'revenue'       => 'Revenue',
        'sales'         => 'Sales',
    ],
    'pending' => [
        'purchases' => 'Pending purchases',
        'sales'     => 'Pending sales',
        'warehouse' => 'Warehouse',
    ],
    'quick_actions' => [
        'new_transfer'        => 'New transfer',
        'new_direct_transfer' => 'New direct transfer',
        'new_purchase'        => 'New purchase',
        'new_sale'            => 'New sale',
    ],
    'active_in_transit' => [
        'qty'         => 'Qty',
        'requisition' => 'Requisition',
        'sku'         => 'SKU',
        'status'      => 'Status',
    ],
];
```

`lang/en/wizards.php`:

```php
return [
    'transfer_review' => [
        'title' => 'Transfer review', 'from' => 'From', 'to' => 'To',
        'sku' => 'SKU', 'qty' => 'Qty', 'unit' => 'Unit',
    ],
    'purchase_order_review' => [
        'title' => 'Purchase order review', 'supplier' => 'Supplier', 'warehouse' => 'Warehouse',
        'sku' => 'SKU', 'qty' => 'Qty', 'unit_cost' => 'Unit cost',
    ],
    'sales_order_review' => [
        'title' => 'Sales order review', 'customer' => 'Customer', 'warehouse' => 'Warehouse',
        'sku' => 'SKU', 'qty' => 'Qty', 'unit' => 'Unit',
    ],
    'direct_transfer_review' => [
        'title' => 'Direct transfer review', 'from' => 'From', 'to' => 'To',
        'sku' => 'SKU', 'qty' => 'Qty', 'unit' => 'Unit',
    ],
];
```

`lang/en/stn.php`:

```php
return [
    'print' => [
        'title' => 'Shipment note :ref', 'heading' => 'Shipment note',
        'route' => ':from → :to', 'status' => 'Status: :status', 'variant' => 'Variant',
        'sku' => 'SKU', 'qty' => 'Qty', 'print_button' => 'Print',
    ],
    'scan' => [
        'title' => 'Scan intake :ref', 'heading' => 'Scan intake',
        'route' => ':from → :to', 'sku' => 'SKU',
        'received_good' => 'Received (good)', 'received_damaged' => 'Received (damaged)',
        'submit' => 'Submit scan',
    ],
];
```

`lang/en/attributes.php`, `lang/en/forms.php`, `lang/en/tables.php`, `lang/en/widgets.php` — shared structural namespaces with no standalone keys; domain keys live under `resources.*` per the §0A.3 convention. Each file returns an empty canonical array so the §25 file map resolves. No `__()` call in this blueprint references the `attributes.*`, `forms.*`, `tables.*`, or `widgets.*` namespaces, and `TranslationKeyParityTest` (§0A.15) treats these intentionally-empty canonical files as valid:

```php
return [
    // No standalone keys — domain strings live under resources.* (§0A.3).
];
```

`lang/en/resources.php` — canonical domain catalogue (nested per §0A.3; every `resources.*` key referenced in this blueprint is defined here):

```php
return [
    'products' => [
        'model' => ['singular' => 'Product', 'plural' => 'Products'],
        'navigation' => ['label' => 'Products'],
        'sections' => ['inventory' => 'Inventory'],
        'tabs' => ['identity' => 'Identity', 'status' => 'Status', 'stock_pricing' => 'Stock & Pricing'],
        'wizard' => ['review' => 'Review product'],
        'fields' => [
            'sku' => 'SKU', 'family_name' => 'Family name', 'family_category' => 'Category',
            'variant_name' => 'Variant name', 'barcode' => 'Barcode', 'base_unit' => 'Base unit',
            'base_unit_name' => 'Base unit', 'base_unit_ratio' => 'Base unit ratio',
            'unit_name' => 'Unit', 'unit_conversions' => 'Unit conversions',
            'reorder_point' => 'Reorder point', 'attributes' => 'Attributes', 'images' => 'Images',
            'is_active' => 'Active', 'is_default_purchase' => 'Default purchase unit',
            'is_default_transfer' => 'Default transfer unit', 'cost_price' => 'Cost price',
            'sale_price' => 'Sale price', 'price_change_notes' => 'Price change notes',
            'product_family' => 'Product family', 'warehouse' => 'Warehouse',
            'signed_quantity_base' => 'Signed quantity (base units)',
            'adjustment_notes' => 'Adjustment notes',
        ],
        'hints' => ['signed_quantity' => 'Positive adds stock, negative removes stock.'],
        'filters' => ['is_active' => 'Active', 'product_family' => 'Product family'],
        'table' => [
            'sku' => 'SKU', 'name' => 'Name', 'family' => 'Family', 'base_unit' => 'Base unit',
            'reorder_point' => 'Reorder point', 'sale_price' => 'Sale price', 'status' => 'Status',
        ],
        'infolist' => ['identity' => 'Identity', 'pricing' => 'Pricing', 'unit_conversions' => 'Unit conversions'],
        'actions' => [
            'set_current_price' => 'Set current price',
            'set_current_price_heading' => 'Set current price',
            'set_current_price_description' => 'Replace the current price for this variant.',
            'manage_units' => 'Manage units',
            'manage_units_heading' => 'Manage units',
            'manage_units_description' => 'Edit unit conversions for this variant.',
            'edit_family' => 'Edit family',
            'edit_family_heading' => 'Edit family',
            'edit_family_description' => 'Edit the parent product family.',
            'quick_adjustment' => 'Quick adjustment',
            'quick_adjustment_heading' => 'Quick stock adjustment',
            'quick_adjustment_description' => 'Record a manual stock adjustment.',
            'more_actions' => 'More actions',
        ],
        'notifications' => [
            'created' => 'Product created.', 'updated' => 'Product updated.',
            'price_updated' => 'Price updated.', 'adjustment_recorded' => 'Adjustment recorded.',
            'family_updated' => 'Family updated.', 'family_missing' => 'Product family is missing.',
        ],
    ],
    'transfer_requisitions' => [
        'model' => ['singular' => 'Transfer requisition', 'plural' => 'Transfer requisitions'],
        'navigation' => ['label' => 'Transfer Requisitions'],
        'badge_tooltip' => 'Requisitions awaiting review',
        'sections' => ['routing' => 'Routing'],
        'steps' => [
            'routing' => 'Routing', 'routing_description' => 'Choose source and destination warehouses.',
            'manifest' => 'Manifest', 'manifest_description' => 'Add requested line items.',
            'review' => 'Review', 'review_description' => 'Verify before creating.',
        ],
        'fields' => [
            'reference_code' => 'Reference', 'status' => 'Status', 'from_warehouse' => 'From warehouse',
            'to_warehouse' => 'To warehouse', 'notes' => 'Notes', 'variant_sku' => 'Variant SKU',
            'original_sku' => 'Original SKU', 'substitute_sku' => 'Substitute SKU',
            'unit' => 'Unit', 'ratio_base' => 'Ratio (base)', 'qty' => 'Qty',
            'requested' => 'Requested', 'approved' => 'Approved', 'proposed' => 'Proposed',
            'requested_by' => 'Requested by', 'approved_by' => 'Approved by',
            'dispatched_by' => 'Dispatched by', 'received_by' => 'Received by',
            'shipped_base' => 'Shipped (base)', 'received_good_base' => 'Received good (base)',
            'revision' => 'Revision', 'revisions' => 'Revisions', 'revision_item' => 'Revision item',
            'negotiation_side' => 'Side', 'negotiation_reason' => 'Negotiation reason',
            'proposed_by' => 'Proposed by', 'responds_to' => 'Responds to', 'responded_at' => 'Responded at',
        ],
        'hints' => ['ratio_auto' => 'Ratio is resolved automatically from the unit.'],
        'filters' => ['from_warehouse' => 'From warehouse', 'to_warehouse' => 'To warehouse', 'status' => 'Status'],
        'table' => [
            'reference' => 'Reference', 'from' => 'From', 'to' => 'To',
            'items' => 'Items', 'requested_by' => 'Requested by', 'requested' => 'Requested',
            'status' => 'Status',
        ],
        'infolist' => [
            'profile' => 'Profile', 'signoffs' => 'Sign-offs',
            'manifest' => 'Manifest', 'negotiation_history' => 'Negotiation history',
        ],
        'placeholders' => [
            'system_initialized' => 'System', 'pending_approval' => 'Pending approval',
            'pending_dispatch' => 'Pending dispatch', 'pending_intake' => 'Pending intake',
        ],
        'actions' => [
            'submit' => 'Submit request', 'submit_heading' => 'Submit request',
            'submit_description' => 'Send this requisition for review.',
            'review' => 'Review / negotiate',
            'propose_revision' => 'Propose revision',
            'propose_revision_heading' => 'Propose revision',
            'propose_revision_description' => 'Propose a substitute variant or quantity.',
            'accept_revision' => 'Accept revision',
            'accept_revision_heading' => 'Accept revision',
            'accept_revision_description' => 'Accept the selected pending revision.',
            'reject_revision' => 'Reject revision',
            'reject_revision_heading' => 'Reject revision',
            'reject_revision_description' => 'Reject the selected pending revision.',
            'confirm' => 'Confirm', 'confirm_heading' => 'Confirm requisition',
            'confirm_description' => 'Confirm the negotiated manifest.',
            'dispatch' => 'Dispatch', 'dispatch_heading' => 'Dispatch stock',
            'dispatch_description' => 'Dispatch approved quantities.',
            'receive' => 'Receive', 'record_loss' => 'Record loss',
            'record_loss_heading' => 'Record loss',
            'record_loss_description' => 'Record lost or damaged quantities.',
            'cancel' => 'Cancel', 'cancel_heading' => 'Cancel requisition',
            'cancel_description' => 'Cancel this requisition.',
            'more_actions' => 'More actions',
        ],
        'notifications' => [
            'dispatched' => 'Requisition dispatched.', 'loss_recorded' => 'Loss recorded.',
            'revision_submitted' => 'Revision submitted.', 'revision_accepted' => 'Revision accepted.',
            'revision_rejected' => 'Revision rejected.',
        ],
    ],
    'direct_transfers' => [
        'model' => ['singular' => 'Direct transfer', 'plural' => 'Direct transfers'],
        'navigation' => ['label' => 'Direct Transfers'],
        'sections' => ['routing' => 'Routing', 'stock_allocation' => 'Stock allocation'],
        'steps' => [
            'location_mapping' => 'Locations', 'location_mapping_description' => 'Choose source and destination.',
            'stock_allocation' => 'Stock allocation', 'stock_allocation_description' => 'Allocate line quantities.',
            'review_verify' => 'Review', 'review_verify_description' => 'Verify before transferring.',
        ],
        'fields' => [
            'from_warehouse' => 'From warehouse', 'to_warehouse' => 'To warehouse',
            'variant_sku' => 'Variant SKU', 'sku' => 'SKU', 'unit' => 'Unit',
            'ratio' => 'Ratio', 'ratio_base' => 'Ratio (base)', 'qty' => 'Qty', 'base_qty' => 'Base qty',
            'notes' => 'Notes', 'reference_code' => 'Reference',
            'transferred_by' => 'Transferred by', 'transferred_at' => 'Transferred at',
        ],
        'hints' => ['ratio_auto' => 'Ratio is resolved automatically from the unit.'],
        'filters' => ['from_warehouse' => 'From warehouse', 'to_warehouse' => 'To warehouse'],
        'table' => [
            'reference' => 'Reference', 'from' => 'From', 'to' => 'To',
            'items' => 'Items', 'by' => 'By', 'transferred_at' => 'Transferred',
        ],
        'infolist' => ['profile' => 'Profile', 'manifest' => 'Manifest', 'authorization' => 'Authorization'],
    ],
    'in_transits' => [
        'model' => ['singular' => 'In-transit cargo', 'plural' => 'In-transit cargo'],
        'navigation' => ['label' => 'In-Transit Cargo'],
        'badge_tooltip' => 'Cargo currently in transit',
        'fields' => [
            'requisition' => 'Requisition', 'sku' => 'SKU',
            'status' => 'Status',
            'dispatched_base' => 'Dispatched (base)', 'dispatched_at' => 'Dispatched at',
            'cleared_at' => 'Cleared at',
        ],
        'filters' => ['status' => 'Status'],
        'table' => [
            'requisition' => 'Requisition', 'sku' => 'SKU', 'status' => 'Status',
            'dispatched' => 'Dispatched', 'dispatched_at' => 'Dispatched at',
        ],
        'infolist' => ['cargo' => 'Cargo'],
    ],
    'stock_movements' => [
        'model' => ['singular' => 'Stock movement', 'plural' => 'Stock movements'],
        'navigation' => ['label' => 'Stock Movements'],
        'fields' => [
            'sku' => 'SKU', 'warehouse' => 'Warehouse', 'unit' => 'Unit',
            'by' => 'By', 'timestamp' => 'Timestamp', 'reference_code' => 'Reference',
            'type' => 'Type', 'quantity' => 'Quantity', 'notes' => 'Notes',
        ],
        'filters' => ['warehouse' => 'Warehouse', 'variant' => 'Variant', 'type' => 'Type'],
        'table' => [
            'sku' => 'SKU', 'warehouse' => 'Warehouse', 'type' => 'Type', 'qty' => 'Qty', 'unit' => 'Unit',
            'by' => 'By', 'timestamp' => 'Timestamp', 'reference_code' => 'Reference',
        ],
        'infolist' => ['movement' => 'Movement'],
    ],
    'loss_ledgers' => [
        'model' => ['singular' => 'Loss ledger', 'plural' => 'Loss ledgers'],
        'navigation' => ['label' => 'Loss Ledgers'],
        'fields' => [
            'requisition' => 'Requisition', 'sku' => 'SKU', 'warehouse' => 'Warehouse',
            'lost_base' => 'Lost (base)', 'damaged_base' => 'Damaged (base)',
            'notes' => 'Notes', 'transfer_requisition_item' => 'Requisition item',
            'recorded_at' => 'Recorded at', 'loss_category' => 'Loss category',
            'unit_cost_price' => 'Unit cost', 'total_financial_loss' => 'Total loss',
        ],
        'filters' => ['warehouse' => 'Warehouse', 'loss_category' => 'Loss category'],
        'table' => [
            'requisition' => 'Requisition', 'sku' => 'SKU', 'warehouse' => 'Warehouse',
            'lost' => 'Lost', 'damaged' => 'Damaged', 'category' => 'Category',
            'unit_cost' => 'Unit cost', 'total_loss' => 'Total loss',
            'by' => 'By', 'recorded' => 'Recorded',
        ],
        'infolist' => ['record' => 'Record', 'financial_impact' => 'Financial impact'],
        // Persisted shortfall note for zero-cost write-offs (§6.2
        // `writeOffOmittedItem()`): stored in `loss_ledgers.notes`, which
        // the UI exposes, so it resolves through a translation key per
        // §0A.10 instead of hard-coded English.
        'notes' => ['cost_missing' => 'Cost price missing or zero at time of write-off.'],
    ],
    'warehouses' => [
        'model' => ['singular' => 'Warehouse', 'plural' => 'Warehouses'],
        'navigation' => ['label' => 'Warehouses'],
        'fields' => [
            'code' => 'Code', 'name' => 'Name', 'location' => 'Location',
            'is_active' => 'Active', 'users' => 'Users', 'assigned_staff' => 'Assigned staff',
        ],
        'help' => [
            'code' => 'Leave empty to derive from the name.',
            'users_readonly' => 'Assignments are edited from the user record.',
        ],
        'placeholders' => ['code' => 'Auto-derived when empty'],
        'filters' => ['is_active' => 'Active'],
        'form' => ['profile' => 'Profile', 'access_status' => 'Access & status'],
        'table' => [
            'code' => 'Code', 'name' => 'Name', 'location' => 'Location',
            'status' => 'Status', 'staff' => 'Staff', 'ledger_entries' => 'Ledger entries',
        ],
        'infolist' => ['profile' => 'Profile', 'status' => 'Status', 'assigned_staff' => 'Assigned staff'],
        'empty_staff' => 'No staff assigned.',
        'delete_confirm_description' => 'Delete this warehouse. This action cannot be undone.',
    ],
    'users' => [
        'model' => ['singular' => 'User', 'plural' => 'Users'],
        'navigation' => ['label' => 'Users'],
        'fields' => [
            'name' => 'Name', 'email' => 'Email', 'password' => 'Password',
            'role' => 'Role', 'is_active' => 'Active', 'warehouses' => 'Warehouses',
        ],
        'help' => ['warehouses' => 'Warehouses this user may operate in.'],
        'filters' => ['warehouse' => 'Warehouse', 'role' => 'Role', 'is_active' => 'Active'],
        'table' => [
            'name' => 'Name', 'email' => 'Email', 'role' => 'Role',
            'active' => 'Active', 'warehouses' => 'Warehouses', 'created' => 'Created',
        ],
    ],
    'purchase_orders' => [
        'model' => ['singular' => 'Purchase order', 'plural' => 'Purchase orders'],
        'navigation' => ['label' => 'Purchase Orders'],
        'badge_tooltip' => 'Orders awaiting receipt',
        'form' => ['supplier_warehouse' => 'Supplier & warehouse'],
        'steps' => [
            'supplier_warehouse' => 'Supplier & warehouse',
            'supplier_warehouse_description' => 'Choose supplier and receiving warehouse.',
            'line_items' => 'Line items', 'line_items_description' => 'Add ordered line items.',
            'review_verify' => 'Review', 'review_verify_description' => 'Verify before creating.',
        ],
        'fields' => [
            'supplier' => 'Supplier', 'receiving_warehouse' => 'Receiving warehouse',
            'variant_sku' => 'Variant SKU', 'sku' => 'SKU', 'product' => 'Product',
            'unit' => 'Unit', 'ratio_base' => 'Ratio (base)', 'qty' => 'Qty',
            'ordered_base' => 'Ordered (base)', 'received_base' => 'Received (base)',
            'unit_cost' => 'Unit cost', 'update_cost_price' => 'Update cost price',
            'notes' => 'Notes', 'reference_code' => 'Reference',
            'ordered_by' => 'Ordered by', 'received_by' => 'Received by',
            'ordered_at' => 'Ordered at', 'received_at' => 'Received at',
            'receive_line' => ':sku — outstanding :outstanding :unit (:base base)',
            'status' => 'Status',
        ],
        'hints' => ['ratio_auto' => 'Ratio is resolved automatically from the unit.'],
        'help' => [
            'update_cost_price' => 'Update the variant cost price on receipt.',
            'receive_display_equivalent' => ':display :unit',
        ],
        'filters' => ['supplier' => 'Supplier', 'warehouse' => 'Warehouse', 'status' => 'Status'],
        'table' => [
            'reference' => 'Reference', 'supplier' => 'Supplier', 'warehouse' => 'Warehouse',
            'items' => 'Items', 'ordered' => 'Ordered', 'received' => 'Received',
            'status' => 'Status',
        ],
        'infolist' => ['profile' => 'Profile', 'signoffs' => 'Sign-offs', 'line_items' => 'Line items'],
        'actions' => [
            'order' => 'Order', 'order_heading' => 'Place order',
            'order_description' => 'Send this purchase order to the supplier.',
            'receive' => 'Receive', 'receive_heading' => 'Receive purchase',
            'receive_description' => 'Record received base quantities.',
            'cancel' => 'Cancel', 'cancel_heading' => 'Cancel purchase order',
            'cancel_description' => 'Cancel this purchase order.',
            'more_actions' => 'More actions',
        ],
        'notifications' => ['received' => 'Purchase received.'],
    ],
    'sales_orders' => [
        'model' => ['singular' => 'Sales order', 'plural' => 'Sales orders'],
        'navigation' => ['label' => 'Sales Orders'],
        'badge_tooltip' => 'Orders awaiting dispatch',
        'form' => ['customer_warehouse' => 'Customer & warehouse'],
        'steps' => [
            'customer_warehouse' => 'Customer & warehouse',
            'customer_warehouse_description' => 'Choose customer and dispatching warehouse.',
            'line_items' => 'Line items', 'line_items_description' => 'Add sold line items.',
            'review_verify' => 'Review', 'review_verify_description' => 'Verify before creating.',
        ],
        'fields' => [
            'customer' => 'Customer', 'dispatching_warehouse' => 'Dispatching warehouse',
            'variant_sku' => 'Variant SKU', 'sku' => 'SKU', 'product' => 'Product',
            'unit' => 'Unit', 'ratio_base' => 'Ratio (base)', 'qty' => 'Qty',
            'ordered_base' => 'Ordered (base)', 'dispatched_base' => 'Dispatched (base)',
            'snapshot_price' => 'Snapshot price', 'catalog_sale_price' => 'Catalog sale price',
            'notes' => 'Notes', 'reference_code' => 'Reference',
            'ordered_by' => 'Ordered by', 'dispatched_by' => 'Dispatched by',
            'confirmed_at' => 'Confirmed at', 'dispatched_at' => 'Dispatched at',
            'status' => 'Status',
            'line_item' => 'Line item',
            'dispatch_line' => ':sku — outstanding :outstanding :unit (available :available)',
            'return_option' => ':sku — dispatched :dispatched, returned :returned',
            'returned_qty_base' => 'Returned qty (base)',
        ],
        'hints' => ['ratio_auto' => 'Ratio is resolved automatically from the unit.'],
        'help' => [
            'reference_code' => 'Auto-generated when left empty.',
            'no_stock' => 'No stock available for this line.',
            'insufficient_stock' => 'Available stock is below the outstanding quantity.',
        ],
        'placeholders' => ['reference_code' => 'Auto-generated when empty'],
        'filters' => ['customer' => 'Customer', 'warehouse' => 'Warehouse', 'status' => 'Status'],
        'table' => [
            'reference' => 'Reference', 'customer' => 'Customer', 'warehouse' => 'Warehouse',
            'items' => 'Items', 'confirmed' => 'Confirmed', 'dispatched' => 'Dispatched',
            'status' => 'Status',
        ],
        'infolist' => ['profile' => 'Profile', 'signoffs' => 'Sign-offs', 'line_items' => 'Line items'],
        'actions' => [
            'confirm' => 'Confirm', 'confirm_heading' => 'Confirm sales order',
            'confirm_description' => 'Confirm and reserve stock for this order.',
            'dispatch' => 'Dispatch', 'dispatch_heading' => 'Dispatch sale',
            'dispatch_description' => 'Dispatch quantities against reservations.',
            'return' => 'Record return', 'return_heading' => 'Record sales return',
            'return_description' => 'Record returned base quantities.',
            'cancel' => 'Cancel', 'cancel_heading' => 'Cancel sales order',
            'cancel_description' => 'Cancel this sales order.',
            'more_actions' => 'More actions',
        ],
        'notifications' => [
            'dispatched' => 'Sale dispatched.', 'return_recorded' => 'Return recorded.',
        ],
    ],
    'suppliers' => [
        'model' => ['singular' => 'Supplier', 'plural' => 'Suppliers'],
        'navigation' => ['label' => 'Suppliers'],
        'fields' => [
            'name' => 'Name', 'contact_person' => 'Contact person', 'phone' => 'Phone',
            'email' => 'Email', 'address' => 'Address', 'is_active' => 'Active',
        ],
        'filters' => ['is_active' => 'Active'],
        'table' => [
            'name' => 'Name', 'contact' => 'Contact', 'email' => 'Email',
            'phone' => 'Phone', 'status' => 'Status', 'purchase_orders' => 'Purchase orders',
        ],
    ],
    'customers' => [
        'model' => ['singular' => 'Customer', 'plural' => 'Customers'],
        'navigation' => ['label' => 'Customers'],
        'fields' => [
            'name' => 'Name', 'contact_person' => 'Contact person', 'phone' => 'Phone',
            'email' => 'Email', 'address' => 'Address', 'is_active' => 'Active',
        ],
        'filters' => ['is_active' => 'Active'],
        'table' => [
            'name' => 'Name', 'contact' => 'Contact', 'email' => 'Email',
            'phone' => 'Phone', 'status' => 'Status', 'sales_orders' => 'Sales orders',
        ],
    ],
];
```

### 0A.3 Translation Key Convention

Use namespaced semantic keys rather than using database or PHP identifiers as the final UI text.

Recommended examples:

```php
__('resources.products.model.singular')
__('resources.products.model.plural')
__('resources.products.fields.sku')
__('resources.products.fields.base_unit')
__('resources.products.table.sku')
__('resources.products.table.status')
__('resources.products.actions.manage_units')
__('resources.products.notifications.created')
__('resources.products.notifications.updated')
__('resources.products.sections.inventory')
__('resources.products.wizard.review')
__('resources.direct_transfers.steps.location_mapping')
__('resources.direct_transfers.steps.stock_allocation')
__('resources.direct_transfers.steps.review_verify')
__('resources.purchase_orders.steps.supplier_warehouse')
__('resources.purchase_orders.steps.line_items')
__('resources.purchase_orders.steps.review_verify')
__('actions.confirm')
__('actions.cancel')
__('common.save')
__('common.close')
__('common.search')
__('validation.insufficient_stock')
```

The exact key namespace may be refined during implementation, but the principle is mandatory: **code references translation keys; code must not become the translation catalogue.**

### 0A.4 Models — Translation Requirements

Every model that has a Filament Resource or is otherwise presented to an administrator MUST have translated presentation metadata. This does not mean translating the PHP model class itself. It means translating the model's human-readable identity wherever the model is displayed.

For each applicable model/resource define translated equivalents for:

- singular model label;
- plural model label;
- navigation label;
- navigation group;
- record title context where applicable;
- relationship labels;
- relationship option labels where the option is a human-readable entity;
- empty-state text;
- model-specific validation/domain errors.

At minimum this applies to:

- `Product` / `ProductVariant`;
- `ProductVariantUnitConversion`;
- `ProductVariantPrice`;
- `Warehouse`;
- `User`;
- `TransferRequisition`;
- `TransferRequisitionItem`;
- `DirectTransfer`;
- `DirectTransferItem`;
- `InTransit`;
- `StockMovement`;
- `StockMovementIdempotencyKey` where exposed for diagnostics;
- `LossLedger`;
- `PurchaseOrder`;
- `PurchaseOrderItem`;
- `Supplier`;
- `SalesOrder`;
- `SalesOrderItem`;
- `Customer`;
- and every additional model introduced by the implementation.

### 0A.5 Forms — Complete Translation Contract

Every form field MUST use a translation key for its label and, when present, helper text, placeholder, description, hint, and validation-facing explanation.

Example:

```php
TextInput::make('reference_code')
    ->label(__('resources.sales_orders.fields.reference_code'))
    ->placeholder(__('resources.sales_orders.placeholders.reference_code'))
    ->helperText(__('resources.sales_orders.help.reference_code'));
```

The following must never remain as hard-coded user-facing English in form schemas:

- `label()` strings;
- `helperText()` strings;
- `placeholder()` strings;
- `description()` strings;
- confirmation copy;
- option labels;
- inline creation labels;
- repeatable-item headings;
- empty repeatable states;
- wizard step descriptions.

Select options sourced from enums MUST use the enum's translated `getLabel()` implementation. Static option arrays MUST translate each displayed value.

### 0A.6 Tables — Complete Translation Contract

Every table column, filter, action, empty state, bulk action, tooltip, and content-grid/card heading MUST be translatable.

For example, this:

```php
TextColumn::make('customer.name')
    ->label(__('resources.sales_orders.table.customer'));
```

is required instead of a hard-coded `->label('Customer')`.

This applies equally to card-layout tables and dense ledger tables. The presentation rules in F25/F26 remain unchanged; translation coverage is an additional mandatory requirement.

### 0A.7 Headings, Sections, Tabs & Wizards

Every heading visible in a Resource, Page, Widget, Infolist, Wizard, modal, drawer, review component, print view, or Blade view MUST come from the translation catalogue.

For wizard definitions, translate both the step title and description:

```php
Step::make(__('resources.purchase_orders.steps.supplier_warehouse'))
    ->description(__('resources.purchase_orders.steps.supplier_warehouse_description'))
```

The same rule applies to `Section::make()`, `Tabs`, `Tab`, `Fieldset`, `Card`, `Heading`, modal titles, and custom Livewire/Blade headings.

### 0A.8 Actions & Notifications

Every Action MUST translate:

- visible action label;
- modal heading;
- modal description;
- confirmation text;
- success notification title/body;
- warning notification title/body;
- failure notification title/body;
- disabled-state explanation when displayed;
- tooltip.

Example:

```php
Action::make('dispatch')
    ->label(__('resources.transfer_requisitions.actions.dispatch'))
    ->modalHeading(__('resources.transfer_requisitions.actions.dispatch_heading'))
    ->modalDescription(__('resources.transfer_requisitions.actions.dispatch_description'))
    ->successNotificationTitle(__('resources.transfer_requisitions.notifications.dispatched'));
```

### 0A.9 Enums

Every backed enum implementing Filament labels MUST route its human-readable label through the translation system. Backing values remain stable and untranslated.

```php
public function getLabel(): string
{
    return __(match ($this) {
        self::Draft => 'enums.transfer_requisition_status.draft',
        self::Requested => 'enums.transfer_requisition_status.requested',
        // ...
    });
}
```

Enum case names and database values remain English/code identifiers unless an existing project convention requires otherwise. Only the rendered label is localized.

### 0A.10 Validation & Domain Errors

Validation and domain-layer messages MUST be localized before being exposed to the user. Services must not permanently embed English UI copy in exceptions. Prefer stable domain error codes/translation keys or exception types that the presentation layer resolves to localized text.

For example, a service may raise a typed exception:

```php
throw new InsufficientStockException(
    variantId: $variantId,
    warehouseId: $warehouseId,
    requested: $requested,
    available: $available,
);
```

The Filament/action layer then renders:

```php
Notification::make()
    ->danger()
    ->title(__('errors.insufficient_stock.title'))
    ->body(__('errors.insufficient_stock.body', [
        'requested' => $requested,
        'available' => $available,
    ]))
    ->send();
```

This prevents domain services from becoming coupled to one human language.

### 0A.11 Blade, Livewire & Custom Review Components

Translation coverage is not limited to PHP classes. All custom Blade templates, Livewire components, wizard review components, print views, QR scan pages, and JavaScript/TypeScript UI strings MUST use the application's i18n mechanism.

Existing review views such as:

```text
resources/views/filament/wizards/transfer-review.blade.php
resources/views/filament/wizards/purchase-order-review.blade.php
resources/views/filament/wizards/sales-order-review.blade.php
resources/views/filament/wizards/direct-transfer-review.blade.php
```

MUST NOT contain hard-coded user-facing labels.

If JavaScript/TypeScript introduces user-facing strings, expose translated values from the server or use the frontend translation mechanism established by the application. Do not duplicate translation catalogues independently unless an explicit frontend architecture decision approves it.

### 0A.12 Navigation & Dashboard

Navigation groups, resource labels, active labels, badge tooltips, dashboard headings, widget headings, metric names, chart labels, legends, empty states, and quick-action labels MUST be translated.

The existing navigation/icon rules remain intact: strongly typed `Heroicon` values determine icons; translation keys determine human-readable labels.

### 0A.13 Accessibility

Translation coverage includes accessibility-facing text:

- tooltips;
- aria labels;
- visually hidden headings;
- screen-reader descriptions;
- scan instructions;
- icon-only action descriptions;
- table/card context labels.

A translated UI is not considered complete if accessibility text remains in the source language.

### 0A.14 Locale-Safe Formatting

Human-readable dates, times, numbers, currency, quantities, and percentages MUST respect the active locale and configured currency. Database values remain canonical.

Requirements:

1. Never translate or localize stored numeric values in the database.
2. Use locale-aware formatting at presentation time.
3. Render `decimal(15,4)` money fields through the global `format_money(mixed $state, int $precision = 4): string` helper (`app/Helpers.php`), which wraps `\Illuminate\Support\Number::currency()` and applies the project's fixed 4-decimal display precision. Plain `->money(config('app.currency'))` remains valid where the locale-default precision (2) is intended (e.g. `ProductInfolist` cost/sale prices). Filament v5's `->money()` no longer accepts a `decimals:` argument — passing one was invalid and has been removed.
4. Do not concatenate currency symbols manually into translated strings when a locale-aware formatter is available.
5. Keep reference codes and identifiers machine-stable regardless of locale.

### 0A.15 Translation Completeness Tests

Translation coverage is a testable architectural contract. Add automated tests that:

1. enumerate every configured locale;
2. compare every locale's translation-key set against the canonical locale;
3. fail when a key is missing;
4. fail when a translation resolves to the raw key unintentionally;
5. detect prohibited hard-coded UI strings in Resource, Form, Table, Infolist, Widget, Page, Action, and Blade definitions where practical;
6. verify every enum implementing `HasLabel` returns a translated label;
7. verify every Resource has translated singular/plural/navigation labels;
8. verify every wizard step has translated title and description;
9. verify every table has translated user-facing column/filter/action text;
10. verify every notification path has translated success/failure text;
11. verify validation/domain error codes resolve for every configured locale;
12. verify print/QR/scan views are covered;
13. verify accessibility labels are localized.

Recommended test names:

```text
TranslationKeyParityTest::all_locales_match_canonical_key_set()
TranslationCoverageTest::resources_have_translated_model_labels()
TranslationCoverageTest::resources_have_translated_navigation_labels()
TranslationCoverageTest::forms_have_no_untranslated_user_facing_labels()
TranslationCoverageTest::tables_have_no_untranslated_user_facing_labels()
TranslationCoverageTest::actions_have_translated_labels_and_notifications()
TranslationCoverageTest::wizard_steps_have_translated_titles_and_descriptions()
TranslationCoverageTest::enums_have_translated_labels()
TranslationCoverageTest::domain_errors_have_translations_for_all_locales()
TranslationCoverageTest::blade_review_views_have_translation_keys()
```

### 0A.16 Translation Definition of Done

A Resource, model-facing feature, action, or workflow is **not complete** until:

- its singular/plural model labels are translated;
- navigation labels/group labels are translated;
- every form field label/helper/placeholder is translated;
- every table column/filter/action/empty state is translated;
- every heading/section/tab/wizard step is translated;
- every notification and confirmation is translated;
- every user-visible validation/domain error is translated;
- every enum label is translated;
- every Blade/Livewire/JS user-facing string is translated;
- accessibility labels/tooltips are translated;
- all configured locales contain the required keys;
- translation coverage tests pass.

**No new feature may introduce a hard-coded user-facing string without an explicit documented exception.**

### 0A.17 Council Gap Closed

This section closes the previously implicit i18n gap. The earlier blueprint already stated **"Strongly-Typed Icons, Multi-Language i18n & Currency"** and required enum labels to route through `__()`, but individual examples still contained literal strings such as `->label('Reference')`, `->label('Customer')`, `->label('Warehouse')`, and wizard titles/descriptions. The Council therefore requires translation coverage at the architectural level rather than treating `__()` on enums as sufficient.

The same applies to model/resource metadata: resources currently define navigation groups and record labels directly in code, so those presentation values must resolve through the translation catalogue while the underlying model/class identifiers remain unchanged.

---

## 🧭 Section 0: Executive Architecture & System Principles

### Core Principles

1. **Pure Derived Stock of Truth.** Physical stock levels, active transit reservations, and available balances are never stored in a physical database table. Physical on-hand stock is calculated dynamically at query-time as the sum of all signed records in `stock_movements`. Active reservations sum pending quantities from confirmed requisitions, and available stock is derived as `on_hand - reserved`.

2. **Decoupled Pricing & Variant-Level Catalog.** `sku` lives exclusively on `product_variants`. Parent products act purely as family grouping containers. `reorder_point` lives exclusively on `product_variants`. Unit pricing is decoupled into `product_variant_prices` with `is_current = true`, supporting 4-decimal micro-pricing.

3. **Pessimistic Locking & Transaction Isolation.** All stock deductions, dispatches, and intake receipts execute inside atomic database transactions using pessimistic row-level locking on `product_variants`, `warehouses`, and `transfer_requisitions`. Locking discipline is uniform across every multi-warehouse-touching service method.

4. **Canonical Foreign Key & Plural Naming.** All database tables use explicit plural snake_case names. Foreign keys strictly follow table-bound names.

5. **Physical-to-Digital State Lifecycle.**
   ```
   draft → requested → under_review_fulfiller ⇌ under_review_requestor
         → confirmed → dispatched ⇌ partially_received
         → completed / closed_with_loss / cancelled
   ```
    `partially_received` is a first-class live state.

    Purchases lifecycle (per Addendum A2 and §4.2 `PurchaseOrderStatus`):

    ```
    draft → ordered → partially_received → received / cancelled
    ```

    Sales lifecycle (per Addendum A2 and §4.3 `SalesOrderStatus`):

    ```
    draft → confirmed → partially_dispatched → dispatched / cancelled
    ```

    Direct transfers have no lifecycle states (per Addendum A11) — fire-and-forget.

6. **Negotiated Substitute Variant Swapping.** Dispatch and receipt pipelines dynamically resolve `$actualVariantId = $item->substitute_product_variant_id ?? $item->product_variant_id`.

7. **Scanned Receipt Loss Integrity & Omitted Cargo.** On the first intake scan, dispatched items missing from a physical scan payload are recorded as 0 received, triggering a 100% variance write-off. On subsequent scans, omitted items are treated as still in transit. "First scan" is determined by the absence of any prior idempotency record for the requisition — not by any in-transit clearing timestamp.

8. **Signed Web QR Routing.** STN QR codes embed secure 7-day temporary signed URLs.

9. **Modal-First UI (< 8 Inputs Rule).** Compact operations use inline slide-over Drawers or Dialog Modals. Multi-step wizards use `modalWidth(Width::SevenExtraLarge)`.

10. **Strongly-Typed Icons, Multi-Language i18n & Currency.** All backed enums route `getLabel()` through `__()`. Currency display routes through the global `format_money()` helper (`app/Helpers.php`) for `decimal(15,4)` fields at 4-decimal precision; plain `->money(config('app.currency'))` remains valid where locale-default precision (2) is intended. Filament v5's `->money()` does not accept a `decimals:` argument. Every Action, resource, and navigation item uses a strongly-typed `Heroicon` enum.

11. **Ledger FK Immutability.** Every `product_variant_id` foreign key on a ledger table uses `restrictOnDelete`.

12. **Authorization vs Visibility.** `->authorize()` enforces server-side policy security. `->visible()` controls frontend DOM rendering. **`->visible()` never re-derives a permission decision.**

13. **Reservation Scope Boundary.** `reservedQuantity()` is intentionally and permanently bounded to requisitions in `Confirmed` status only.

14. **Cancellation Boundary.** `CancelAction` is only legal while a requisition is in a pre-dispatch state.

15. **Cost Snapshot Timing.** `LossLedger::snapshotUnitCostFrom()` captures `currentPrice.cost_price` at call-time, and logs a warning if cost is missing or zero.

16. **Table Shape Determines Presentation.** Document-shaped records (requisitions, purchase orders, sales orders, direct transfers) render as cards via `->contentGrid()`. Ledger-shaped records (stock movements, loss ledgers) render as dense, sortable rows via the standard table with `->stackedOnMobile()`. Master-data tables (suppliers, customers, warehouses, products) may render either way depending on cardinality and use case.

17. **Badge Scope Follows Role + Warehouse Assignment.** Every navigation badge resolves its warehouse ID set through `ScopesNavigationBadges::badgeScopedWarehouseIds()`. The resolver returns:
    - **all warehouses** for `Admin` and `Auditor`;
    - the **union of assigned warehouses** for `WarehouseStaff` with N ≥ 2 assignments;
    - **exactly the single assigned warehouse** for `WarehouseStaff` with N = 1 assignment — no widening, no fallback, no implicit union with the counterpart warehouse of a document;
    - an **empty set** (badge returns `null`) for `WarehouseStaff` with N = 0 assignments.

### Addendum Principles (Purchases & Sales)

**A1.** Same Ledger, New Movement Types. Purchases and sales are new `StockMovementType` cases.

**A2.** Purchases and Sales Are Symmetric, Single-Entity Flows. Each gets a lightweight lifecycle matching its status enum — purchases per §4.2 (`Draft` → `Ordered` → `PartiallyReceived` → `Received` / `Cancelled`), sales per §4.3 (`Draft` → `Confirmed` → `PartiallyDispatched` → `Dispatched` / `Cancelled`).

**A3.** External Party Entities Are Minimal Master Data.

**A4.** Cost & Price Interplay. Received-purchase cost updates are opt-in via `update_cost_price`. Sales dispatch at confirm-time-snapshotted `sale_price`.

**A5.** Reservation Boundary Stays Untouched, Sales Get Their Own Boundary.

**A6.** No Reversal Pathway for Dispatched Sales.

**A7.** Purchases Have No "Loss" Concept at Intake.

**A8.** Policies Are the ONLY Home for Permission/Role Logic.

**A9.** Shared Filter Architecture for Admin Review.

**A10.** **Substitute Variants Are Transfer-Only.** `substitute_product_variant_id` is intentionally absent from `PurchaseOrderItem`, `SalesOrderItem`, and `DirectTransferItem`. Substitution is a first-class transfer/requisition feature only. Purchases and sales operate on the exact variant ordered/sold. Direct transfers operate on the exact variant moved — no substitution.

**A11.** **Direct Transfers Are Multi-Line Fire-and-Forget Operations.** A direct transfer is a single atomic transaction that moves **one or more distinct variants** between two warehouses. It has no lifecycle states and no reversal pathway; correction is achieved by a reverse direct transfer. The header exists solely to group paired ledger movements and provide an audit surface. All line items share the same `from_warehouse_id` and `to_warehouse_id`.

**A12.** **Badge Scope Is Role-Determined, Not Document-Determined.** A document's counterpart warehouse never widens a user's badge scope. A warehouse-staff user with exactly one assigned warehouse sees a badge count of documents that touch that one warehouse — never the union of both endpoints of a transfer, and never a count that includes a counterpart warehouse the user cannot act on.

### Filament v5 Patterns

**F16.** Wizard-Based Create Pages Use `HasWizard` Trait.

**F17.** Relationship-Bound Repeaters Must NOT Declare `->dehydrated()`; Use Mutation Hooks. `Repeater::make('items')->relationship(...)->dehydrated()` triggers `SQLSTATE[42S22] Column not found: items` — Filament already dehydrates relationship repeaters. Declare `->dehydrated()` only on individual fields (`*_unit_ratio`); use `mutateRelationshipDataBeforeCreateUsing()` / `mutateRelationshipDataBeforeSaveUsing()` for per-item transformation.

**F18.** Units Are Variant-Scoped and Never Free-Text.

**F19.** Base-Unit "Self-Conversion" Row Required.

**F20.** Layout Components Are Composable. `Grid`, `Section`, `Fieldset`, `Tabs`, `Flex` — all support `columns()` / `columnSpan()`.

**F21.** Navigation Badges Are Live Status Indicators.

**F22.** Active Navigation Icons Reinforce State.

**F23.** Icons on Every Interactive Element.

**F24.** Responsive Column Spans Are Mandatory.

**F25.** Card Layout via `->contentGrid()`. Tables whose records are documents (not ledger rows) declare `->contentGrid(['md' => 2, 'xl' => 3])`, compose card internals with `Stack` and `Split`, and set `->defaultPaginationPageOption(12)`.

**F26.** Ledger Tables Are Never Carded. Append-only, high-volume ledgers always render as dense standard tables with `->stackedOnMobile()`. Signed quantities are color-coded. Pagination page size is ≥ 25.

**F27.** Every Table Declares `->defaultSort()`. Filament's implicit primary-key-ascending default is never correct for operational lists.

**F28.** Every Relational Column Is Eager-Loaded. Any column referencing `relation.attribute` requires the relation in `getEloquentQuery()`'s `->with()` list.

**F29.** Card Layout Requires Bounded Pagination. Any table with `->contentGrid()` declares `->defaultPaginationPageOption(12)` and `->paginated([12, 24, 48])`.

**F30.** **Card Tables Declare No Bulk Actions Until `mkdev-grid-card-layout` Is Installed.** The native renderer does not render per-card checkboxes; declaring bulk actions without the plugin produces inaccessible UI.

**F31.** **Record Actions Use a Divider-Separated `ActionGroup` Dropdown.** Tables with more than a handful of record actions wrap the secondary actions in an outer `ActionGroup::make([...])` whose trigger is `->icon(Heroicon::EllipsisVertical)->iconButton()->size(Size::Small)->color('gray')->tooltip(...)->dropdownAutoPlacement()->dropdownWidth(Width::Large)`. Related actions are partitioned into nested `ActionGroup::make([...])->dropdown(false)` sub-groups; the nested group renders its children inline and Filament's native divider separates one sub-group from the next. Primary inline actions (`ViewAction`, `EditAction`) stay outside the outer group. Requires `Filament\Actions\ActionGroup`, `Filament\Support\Enums\Size`, and `Filament\Support\Enums\Width` imports. Rationale (v13.7): tables with many record actions (Products, TransferRequisitions, PurchaseOrders, SalesOrders) were flat lists that overflowed the row; the grouped dropdown keeps `View`/`Edit` one click away and tucks the rest behind an ellipsis trigger with named sections. Applied by: `ProductsTable`, `TransferRequisitionsTable`, `PurchaseOrdersTable`, `SalesOrdersTable`. Tables with few actions (`SuppliersTable`, `CustomersTable`) remain flat and are NOT required to adopt F31.

---

## 📁 Section 1: Filament v5 Resource Directory Structure

```
app/Filament/Resources/
├── Products/
│   ├── ProductResource.php
│   ├── Pages/
│   │   ├── ListProducts.php
│   │   ├── CreateProduct.php
│   │   ├── EditProduct.php
│   │   └── ViewProduct.php
│   ├── Schemas/
│   │   ├── ProductForm.php
│   │   └── ProductInfolist.php
│   ├── Tables/
│   │   └── ProductsTable.php
│   └── Actions/
│       ├── SetCurrentPriceAction.php
│       ├── EditProductFamilyAction.php
│       ├── ManageUnitConversionsAction.php
│       └── QuickStockAdjustmentAction.php
│
├── TransferRequisitions/
│   ├── TransferRequisitionResource.php
│   ├── Pages/
│   │   ├── ListTransferRequisitions.php
│   │   ├── CreateTransferRequisition.php
│   │   ├── EditTransferRequisition.php
│   │   └── ViewTransferRequisition.php
│   ├── Schemas/
│   │   ├── TransferRequisitionForm.php
│   │   └── TransferRequisitionInfolist.php
│   └── Tables/
│       └── TransferRequisitionsTable.php
│
├── DirectTransfers/
│   ├── DirectTransferResource.php
│   ├── Pages/
│   │   ├── ListDirectTransfers.php
│   │   ├── CreateDirectTransfer.php
│   │   └── ViewDirectTransfer.php
│   ├── Schemas/
│   │   ├── DirectTransferForm.php
│   │   └── DirectTransferInfolist.php
│   └── Tables/
│       └── DirectTransfersTable.php
│
├── InTransits/
│   ├── InTransitResource.php
│   ├── Pages/
│   │   ├── ListInTransits.php
│   │   └── ViewInTransit.php
│   ├── Schemas/
│   │   └── InTransitInfolist.php
│   └── Tables/
│       └── InTransitsTable.php
│
├── StockMovements/
│   ├── StockMovementResource.php
│   ├── Pages/
│   │   ├── ListStockMovements.php
│   │   └── ViewStockMovement.php
│   ├── Schemas/
│   │   └── StockMovementInfolist.php
│   └── Tables/
│       └── StockMovementsTable.php
│
├── LossLedgers/
│   ├── LossLedgerResource.php
│   ├── Pages/
│   │   ├── ListLossLedgers.php
│   │   └── ViewLossLedger.php
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
│   │   ├── EditWarehouse.php
│   │   └── ViewWarehouse.php
│   ├── Schemas/
│   │   ├── WarehouseForm.php
│   │   └── WarehouseInfolist.php
│   └── Tables/
│       └── WarehousesTable.php
│
├── Users/
│   ├── UserResource.php
│   ├── Pages/
│   │   ├── ListUsers.php
│   │   ├── CreateUser.php
│   │   └── EditUser.php
│   ├── Schemas/
│   │   └── UserForm.php
│   └── Tables/
│       └── UsersTable.php
│
├── PurchaseOrders/
│   ├── PurchaseOrderResource.php
│   ├── Pages/
│   │   ├── ListPurchaseOrders.php
│   │   ├── CreatePurchaseOrder.php
│   │   ├── EditPurchaseOrder.php
│   │   └── ViewPurchaseOrder.php
│   ├── Schemas/
│   │   ├── PurchaseOrderForm.php
│   │   └── PurchaseOrderInfolist.php
│   └── Tables/
│       └── PurchaseOrdersTable.php
│
├── SalesOrders/
│   ├── SalesOrderResource.php
│   ├── Pages/
│   │   ├── ListSalesOrders.php
│   │   ├── CreateSalesOrder.php
│   │   ├── EditSalesOrder.php
│   │   └── ViewSalesOrder.php
│   ├── Schemas/
│   │   ├── SalesOrderForm.php
│   │   └── SalesOrderInfolist.php
│   └── Tables/
│       └── SalesOrdersTable.php
│
├── Suppliers/
│   ├── SupplierResource.php
│   ├── Pages/
│   │   ├── ListSuppliers.php
│   │   ├── CreateSupplier.php
│   │   └── EditSupplier.php
│   ├── Schemas/
│   │   └── SupplierForm.php
│   └── Tables/
│       └── SuppliersTable.php
│
└── Customers/
    ├── CustomerResource.php
    ├── Pages/
    │   ├── ListCustomers.php
    │   ├── CreateCustomer.php
    │   └── EditCustomer.php
    ├── Schemas/
    │   └── CustomerForm.php
    └── Tables/
        └── CustomersTable.php
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
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    protected static ?string $model = ProductVariant::class;
    protected static string | \UnitEnum | null $navigationGroup = 'CATALOG';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'sku';
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedCube;
    protected static string | \BackedEnum | null $activeNavigationIcon = Heroicon::Cube;

    public static function getModelLabel(): string
    {
        return __('resources.products.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.products.model.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.products.navigation.label');
    }

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

### Navigation Group Registration

```php
->navigationGroups([
    NavigationGroup::make('CATALOG')->label(__('navigation.groups.catalog'))->icon(Heroicon::CubeTransparent)->collapsible(),
    NavigationGroup::make('OPERATIONS')->label(__('navigation.groups.operations'))->icon(Heroicon::OutlinedRectangleStack)->collapsible(),
    NavigationGroup::make('PURCHASING')->label(__('navigation.groups.purchasing'))->icon(Heroicon::OutlinedShoppingCart)->collapsible(),
    NavigationGroup::make('SALES')->label(__('navigation.groups.sales'))->icon(Heroicon::OutlinedBanknotes)->collapsible(),
    NavigationGroup::make('AUDIT LEDGERS')->label(__('navigation.groups.audit_ledgers'))->icon(Heroicon::QueueList)->collapsible(),
    NavigationGroup::make('SYSTEM ADMIN')->label(__('navigation.groups.system_admin'))->icon(Heroicon::BuildingOffice)->collapsible(false),
])
```

### Schema `configure()` Contract

Every schema class exposes a static `configure()` method with this signature. The full canonical `ProductForm` implementation lives in §7A.1; this section states the contract only and holds no component list:

```php
// Contract — every schema class exposes:
//   public static function configure(Schema $schema): Schema
// Canonical ProductForm implementation: §7A.1 (this section holds no component list).
```

---

## 🧭 Section 1A: Navigation Groupings — Full Specification

### 1A.1 Centralized Group Registration

Navigation groups are registered once in `AdminPanelProvider::panel()` via `->navigationGroups()`. Array order is the sole determinant of group render order.

The string passed to `NavigationGroup::make()` is a stable matching key (it must equal each resource's `$navigationGroup` value exactly). The human-readable label is set separately via `->label(__('navigation.groups.<key>'))` so the sidebar translates without breaking group matching. Never pass a translated string to `make()` — translations resolve per request and would stop matching the resource keys.

### 1A.2 Group Properties

| Method | Purpose |
|---|---|
| `->icon()` | Group heading icon (required for topbar dropdown) |
| `->collapsible()` / `->collapsible(false)` | Toggle collapsibility |
| `->collapsed()` | Collapse by default |
| `->label()` | Explicit label |

### 1A.3 Group Ordering Rules

1. By group array position in `navigationGroups()` — the only thing that matters.
2. By `$navigationSort` ascending within a group.
3. Alphabetically as fallback tiebreaker.

### 1A.4 Per-Resource Group Assignment

| Resource | `$navigationGroup` | `$navigationSort` | `$navigationIcon` | `$activeNavigationIcon` |
|---|---|---|---|---|
| `ProductResource` | `'CATALOG'` | `1` | `Heroicon::OutlinedCube` | `Heroicon::Cube` |
| `TransferRequisitionResource` | `'OPERATIONS'` | `1` | `Heroicon::OutlinedArrowsRightLeft` | `Heroicon::ArrowsRightLeft` |
| `DirectTransferResource` | `'OPERATIONS'` | `2` | `Heroicon::OutlinedArrowPath` | `Heroicon::ArrowPath` |
| `InTransitResource` | `'OPERATIONS'` | `3` | `Heroicon::OutlinedTruck` | `Heroicon::Truck` |
| `PurchaseOrderResource` | `'PURCHASING'` | `1` | `Heroicon::OutlinedShoppingCart` | `Heroicon::ShoppingCart` |
| `SupplierResource` | `'PURCHASING'` | `2` | `Heroicon::OutlinedBuildingStorefront` | `Heroicon::BuildingStorefront` |
| `SalesOrderResource` | `'SALES'` | `1` | `Heroicon::OutlinedBanknotes` | `Heroicon::Banknotes` |
| `CustomerResource` | `'SALES'` | `2` | `Heroicon::OutlinedUserGroup` | `Heroicon::UserGroup` |
| `StockMovementResource` | `'AUDIT LEDGERS'` | `1` | `Heroicon::OutlinedQueueList` | `Heroicon::QueueList` |
| `LossLedgerResource` | `'AUDIT LEDGERS'` | `2` | `Heroicon::OutlinedExclamationTriangle` | `Heroicon::ExclamationTriangle` |
| `WarehouseResource` | `'SYSTEM ADMIN'` | `1` | `Heroicon::OutlinedBuildingOffice` | `Heroicon::BuildingOffice` |
| `UserResource` | `'SYSTEM ADMIN'` | `2` | `Heroicon::OutlinedUsers` | `Heroicon::Users` |

### 1A.5 Resulting Sidebar Layout

Displayed group labels resolve through `navigation.groups.*` keys; the layout below shows the canonical-locale rendering:

```
CATALOG            → Products
OPERATIONS         → Transfer Requisitions, Direct Transfers, In-Transit Cargo (`InTransitResource`)
PURCHASING         → Purchase Orders, Suppliers
SALES              → Sales Orders, Customers
AUDIT LEDGERS      → Stock Movements, Loss Ledgers
SYSTEM ADMIN       → Warehouses, Users
```

### 1A.6 Group Icons

| Group | Heroicon |
|---|---|
| `CATALOG` | `Heroicon::CubeTransparent` |
| `OPERATIONS` | `Heroicon::OutlinedRectangleStack` |
| `PURCHASING` | `Heroicon::OutlinedShoppingCart` |
| `SALES` | `Heroicon::OutlinedBanknotes` |
| `AUDIT LEDGERS` | `Heroicon::QueueList` |
| `SYSTEM ADMIN` | `Heroicon::BuildingOffice` |

### 1A.7 Collapsibility Strategy

`SYSTEM ADMIN` → `->collapsible(false)`. All others → `->collapsible()`.

---

## 📛 Section 1B: Navigation Badges — Live Status Indicators (Role & Warehouse Scoped)

### 1B.1 Badge Principle (F21)

Badges are live status indicators. `getNavigationBadge()` returns `null` when count is zero.

### 1B.1a Badge Scope is Role + Warehouse Determined

Badge visibility is scoped by **the acting user's role combined with the set of warehouses assigned to that user through the `user_warehouse` pivot**. The scope is resolved once per request by the shared `ScopesNavigationBadges` trait and applied to every badge count.

The four scope tiers are:

| Role | Assigned Warehouses | Badge Scope | Result |
|---|---|---|---|
| `Admin` | any | Every warehouse in the system | Badge counts all matching documents |
| `Auditor` | any | Every warehouse in the system | Badge counts all matching documents |
| `WarehouseStaff` | N ≥ 2 | Union of assigned warehouses | Badge counts documents touching any assigned warehouse |
| `WarehouseStaff` | N = 1 | **Exactly that one warehouse — no exceptions** | Badge counts only documents touching the single assigned warehouse; a counterpart warehouse the user is not assigned to does **not** widen the badge |
| `WarehouseStaff` | N = 0 | Empty set | Badge returns `null`; resource remains reachable in read-only policy mode |

**The scope rule is never negotiated at the call site.** Every badge implementation must resolve its warehouse ID set through `ScopesNavigationBadges::badgeScopedWarehouseIds()`. Ad hoc `auth()->user()->warehouses()->pluck('id')` inside a resource is prohibited — it silently produces the wrong result for admins and auditors, whose warehouse pivot may be empty while their badge authority is global.

### 1B.1b Counterpart Warehouse Does Not Widen Scope

For documents whose canonical meaning spans two warehouses (notably `TransferRequisition` and `DirectTransfer`), the badge scope is still bounded by the resolver:

- A user assigned to Warehouse A but not Warehouse B sees a `TransferRequisition` badge that counts only requisitions where **`from_warehouse_id` OR `to_warehouse_id` is in the user's assigned set** — which, for the single-warehouse case, collapses to "only requisitions touching Warehouse A."
- A user assigned to both A and B sees both ends.
- The single-warehouse case **never** widens to include the counterpart warehouse.

This is codified in Principle A12 and enforced by the badge scope trait.

### 1B.2 Badge Definitions Per Resource

| Resource | Badge Logic (role + warehouse scoped) | Color Logic |
|---|---|---|
| `TransferRequisitionResource` | Count where `status = 'requested'` AND (`from_warehouse_id` OR `to_warehouse_id` in badge scope) | `warning` > 10, else `primary` |
| `PurchaseOrderResource` | Count where `status = 'ordered'` AND `warehouse_id` in badge scope | `warning` > 10, else `primary` |
| `SalesOrderResource` | Count where `status = 'confirmed'` AND `warehouse_id` in badge scope | `warning` > 10, else `primary` |
| `InTransitResource` | Count where `status = 'in_transit'` AND related requisition touches badge scope | `warning` > 10, else `primary` |
| All others | `null` | — |

### 1B.3 Shared Badge Scope Trait

Create:

```text
app/Filament/Support/Concerns/ScopesNavigationBadges.php
```

```php
<?php

namespace App\Filament\Support\Concerns;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;

/**
 * Centralized navigation badge scoping.
 *
 * Badge scope is determined by the acting user's role and warehouse
 * assignments — never by the warehouse endpoints of a specific document.
 * See Principle A12.
 */
trait ScopesNavigationBadges
{
    /** @var array<int>|null */
    private static ?array $badgeWarehouseIds = null;

    /**
     * Cached badge count shared by `getNavigationBadge()` and
     * `getNavigationBadgeColor()`. Trait-owned, so each using resource
     * gets its own copy (trait semantics) while `flushBadgeScope()`
     * resets both caches together — a resource-level copy would survive
     * logout in long-lived workers.
     *
     * @var int|null
     */
    private static ?int $badgeCount = null;

    /**
     * Resolve the warehouse ID set used to scope every navigation badge
     * on this resource.
     *
     * - Admin / Auditor: all warehouses.
     * - WarehouseStaff with N >= 2: union of assigned warehouses.
     * - WarehouseStaff with N == 1: exactly the single assigned warehouse.
     * - WarehouseStaff with N == 0: empty array (badge resolves to null).
     *
     * @return array<int>
     */
    protected static function badgeScopedWarehouseIds(): array
    {
        if (self::$badgeWarehouseIds !== null) {
            return self::$badgeWarehouseIds;
        }

        $user = Auth::user();

        if (! $user instanceof User) {
            return self::$badgeWarehouseIds = [];
        }

        if ($user->isAdmin() || $user->isAuditor()) {
            return self::$badgeWarehouseIds = Warehouse::query()
                ->pluck('id')
                ->all();
        }

        return self::$badgeWarehouseIds = $user->warehouses()
            ->pluck('warehouses.id')
            ->all();
    }

    /**
     * Whether the current user has any badge scope at all.
     * Used by resources that must suppress their badge entirely when the
     * user's role resolves to zero warehouses.
     */
    protected static function hasBadgeScope(): bool
    {
        return count(self::badgeScopedWarehouseIds()) > 0;
    }

    /**
     * Badge scope for the current request is cached once and shared across
     * `getNavigationBadge()`, `getNavigationBadgeColor()`, and
     * `getNavigationBadgeTooltip()` calls.
     *
     * Public so `AppServiceProvider` can flush every badge-bearing resource
     * on `Auth::logout` (§17.2) — required in long-lived workers (Octane,
     * queue workers that boot Filament). Each resource carries its own copy
     * of the static caches (trait semantics), so all four must be flushed.
     *
     * Resets BOTH the warehouse-ID scope and the cached count: resetting
     * only the scope leaves the previous user's count visible to the next
     * user in a long-lived worker.
     */
    public static function flushBadgeScope(): void
    {
        self::$badgeWarehouseIds = null;
        self::$badgeCount = null;
    }
}
```

**Note on cache flushing:** In standard PHP-FPM request lifecycles the static caches die with the request and no action is needed. In long-lived workers (Octane, queue workers that boot Filament), `AppServiceProvider::boot()` listens for both `Illuminate\Auth\Events\Logout` and `Illuminate\Auth\Events\Login` and calls `flushBadgeScope()` on all four badge-bearing resources (§17.2) — the canonical call sites. The `Login` listener closes the re-auth-without-logout case (session expiry followed by direct `Auth::login()`, programmatic auth, Filament re-auth). Each call resets both the warehouse-ID scope and the cached count, so the next user never sees the previous user's badge.

### 1B.3a Badge Implementations

#### TransferRequisitionResource

```php
namespace App\Filament\Resources\TransferRequisitions;

use App\Enums\TransferRequisitionStatus;
use App\Filament\Support\Concerns\ScopesNavigationBadges;
use Filament\Resources\Resource;

class TransferRequisitionResource extends Resource
{
    use ScopesNavigationBadges;

    private static function getScopedBadgeCount(): int
    {
        if (self::$badgeCount !== null) {
            return self::$badgeCount;
        }

        if (! self::hasBadgeScope()) {
            return self::$badgeCount = 0;
        }

        $warehouseIds = self::badgeScopedWarehouseIds();

        return self::$badgeCount = static::getModel()::query()
            ->where('status', TransferRequisitionStatus::Requested->value)
            ->where(function ($q) use ($warehouseIds) {
                $q->whereIn('from_warehouse_id', $warehouseIds)
                  ->orWhereIn('to_warehouse_id', $warehouseIds);
            })
            ->count();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getScopedBadgeCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return self::getScopedBadgeCount() > 10 ? 'warning' : 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('resources.transfer_requisitions.badge_tooltip');
    }
}
```

#### PurchaseOrderResource

```php
namespace App\Filament\Resources\PurchaseOrders;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Support\Concerns\ScopesNavigationBadges;
use Filament\Resources\Resource;

class PurchaseOrderResource extends Resource
{
    use ScopesNavigationBadges;

    private static function getScopedBadgeCount(): int
    {
        if (self::$badgeCount !== null) {
            return self::$badgeCount;
        }

        if (! self::hasBadgeScope()) {
            return self::$badgeCount = 0;
        }

        return self::$badgeCount = static::getModel()::query()
            ->where('status', PurchaseOrderStatus::Ordered->value)
            ->whereIn('warehouse_id', self::badgeScopedWarehouseIds())
            ->count();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getScopedBadgeCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return self::getScopedBadgeCount() > 10 ? 'warning' : 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('resources.purchase_orders.badge_tooltip');
    }
}
```

#### SalesOrderResource

```php
namespace App\Filament\Resources\SalesOrders;

use App\Enums\SalesOrderStatus;
use App\Filament\Support\Concerns\ScopesNavigationBadges;
use Filament\Resources\Resource;

class SalesOrderResource extends Resource
{
    use ScopesNavigationBadges;

    private static function getScopedBadgeCount(): int
    {
        if (self::$badgeCount !== null) {
            return self::$badgeCount;
        }

        if (! self::hasBadgeScope()) {
            return self::$badgeCount = 0;
        }

        return self::$badgeCount = static::getModel()::query()
            ->where('status', SalesOrderStatus::Confirmed->value)
            ->whereIn('warehouse_id', self::badgeScopedWarehouseIds())
            ->count();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getScopedBadgeCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return self::getScopedBadgeCount() > 10 ? 'warning' : 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('resources.sales_orders.badge_tooltip');
    }
}
```

#### InTransitResource

```php
namespace App\Filament\Resources\InTransits;

use App\Enums\InTransitStatus;
use App\Filament\Support\Concerns\ScopesNavigationBadges;
use Filament\Resources\Resource;

class InTransitResource extends Resource
{
    use ScopesNavigationBadges;

    private static function getScopedBadgeCount(): int
    {
        if (self::$badgeCount !== null) {
            return self::$badgeCount;
        }

        if (! self::hasBadgeScope()) {
            return self::$badgeCount = 0;
        }

        // In-transit rows are scoped by the warehouses of their parent
        // requisition. Both endpoints participate because the cargo is in
        // motion between them.
        $warehouseIds = self::badgeScopedWarehouseIds();

        return self::$badgeCount = static::getModel()::query()
            ->where('status', InTransitStatus::InTransit->value)
            ->whereHas('transferRequisition', function ($q) use ($warehouseIds) {
                $q->whereIn('from_warehouse_id', $warehouseIds)
                  ->orWhereIn('to_warehouse_id', $warehouseIds);
            })
            ->count();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getScopedBadgeCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return self::getScopedBadgeCount() > 10 ? 'warning' : 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('resources.in_transits.badge_tooltip');
    }
}
```

### 1B.4 Badge Performance Note

Badge queries run on every panel page load. All four status columns are indexed in Section 2. The badge scope is resolved exactly once per request via `ScopesNavigationBadges::$badgeWarehouseIds` and the count itself is cached in the trait-owned `ScopesNavigationBadges::$badgeCount` (one copy per resource), so `getNavigationBadge()` and `getNavigationBadgeColor()` share a single query and a single scope resolution.

### 1B.5 Badge Scope — Invariants

1. A `WarehouseStaff` user with exactly one assigned warehouse **cannot** see a badge count that includes any other warehouse, including the counterpart warehouse of a transfer.
2. An `Admin` or `Auditor` user always sees the full system count, regardless of their `user_warehouse` pivot contents.
3. A user with zero assigned warehouses and a non-admin/non-auditor role sees `null` badges.
4. The scope resolver is the **only** sanctioned way to compute badge warehouse IDs. Direct `auth()->user()->warehouses()->pluck('id')` inside a `getNavigationBadge()` method is prohibited.
5. The scope resolver caches within the request but is flushed on logout / re-authentication in long-lived workers — the flush covers both the warehouse-ID scope and the cached badge count.

---

## 🎯 Section 1C: Active Navigation Icons

Per Principle F22, every resource declares `$activeNavigationIcon` distinct from `$navigationIcon`. Convention: outlined for resting, solid for active.

### Full Active Icon Map

```php
// Products/ProductResource.php
$navigationIcon = Heroicon::OutlinedCube;
$activeNavigationIcon = Heroicon::Cube;

// TransferRequisitions/TransferRequisitionResource.php
$navigationIcon = Heroicon::OutlinedArrowsRightLeft;
$activeNavigationIcon = Heroicon::ArrowsRightLeft;

// DirectTransfers/DirectTransferResource.php
$navigationIcon = Heroicon::OutlinedArrowPath;
$activeNavigationIcon = Heroicon::ArrowPath;

// InTransits/InTransitResource.php
$navigationIcon = Heroicon::OutlinedTruck;
$activeNavigationIcon = Heroicon::Truck;

// PurchaseOrders/PurchaseOrderResource.php
$navigationIcon = Heroicon::OutlinedShoppingCart;
$activeNavigationIcon = Heroicon::ShoppingCart;

// Suppliers/SupplierResource.php
$navigationIcon = Heroicon::OutlinedBuildingStorefront;
$activeNavigationIcon = Heroicon::BuildingStorefront;

// SalesOrders/SalesOrderResource.php
$navigationIcon = Heroicon::OutlinedBanknotes;
$activeNavigationIcon = Heroicon::Banknotes;

// Customers/CustomerResource.php
$navigationIcon = Heroicon::OutlinedUserGroup;
$activeNavigationIcon = Heroicon::UserGroup;

// StockMovements/StockMovementResource.php
$navigationIcon = Heroicon::OutlinedQueueList;
$activeNavigationIcon = Heroicon::QueueList;

// LossLedgers/LossLedgerResource.php
$navigationIcon = Heroicon::OutlinedExclamationTriangle;
$activeNavigationIcon = Heroicon::ExclamationTriangle;

// Warehouses/WarehouseResource.php
$navigationIcon = Heroicon::OutlinedBuildingOffice;
$activeNavigationIcon = Heroicon::BuildingOffice;

// Users/UserResource.php
$navigationIcon = Heroicon::OutlinedUsers;
$activeNavigationIcon = Heroicon::Users;
```

---

## 🎨 Section 1D: Button, Form Field & Action Icons

Per Principle F23, every Action, form field, and interactive element carries a `Heroicon` enum icon, semantically matched.

### 1D.1 Action & Button Icons

#### TransferRequisitionResource

| Action | Icon | Color |
|---|---|---|
| `ViewAction` | `Heroicon::Eye` | — |
| `submitRequest` | `Heroicon::PaperAirplane` | `primary` |
| `openReview` | `Heroicon::ChatBubbleLeftRight` | `warning` |
| `submitRevision` | `Heroicon::ChatBubbleLeftRight` | `warning` |
| `acceptRevision` | `Heroicon::CheckCircle` | `success` |
| `rejectRevision` | `Heroicon::XCircle` | `danger` |
| `confirm` | `Heroicon::CheckBadge` | `primary` |
| `dispatch` | `Heroicon::Truck` | `primary` |
| `scanToReceive` | `Heroicon::QrCode` | `success` |
| `recordLoss` | `Heroicon::ExclamationTriangle` | `danger` |
| `cancel` | `Heroicon::XMark` | `danger` |
| `EditAction` | `Heroicon::PencilSquare` | — |
| `DeleteAction` | `Heroicon::Trash` | `danger` |
| `RestoreAction` | `Heroicon::ArrowUturnLeft` | `warning` |
| `ForceDeleteAction` | `Heroicon::Trash` | `danger` |

#### DirectTransfersTable

| Action | Icon | Color |
|---|---|---|
| `ViewAction` | `Heroicon::Eye` | — |

#### PurchaseOrdersTable

| Action | Icon | Color |
|---|---|---|
| `ViewAction` | `Heroicon::Eye` | — |
| `orderPurchase` | `Heroicon::PaperAirplane` | `primary` |
| `receivePurchase` | `Heroicon::ArchiveBoxArrowDown` | `success` |
| `cancelPurchase` | `Heroicon::XMark` | `danger` |
| `EditAction` | `Heroicon::PencilSquare` | — |
| `DeleteAction` | `Heroicon::Trash` | `danger` |
| `RestoreAction` | `Heroicon::ArrowUturnLeft` | `warning` |
| `ForceDeleteAction` | `Heroicon::Trash` | `danger` |

#### SalesOrdersTable

| Action | Icon | Color |
|---|---|---|
| `ViewAction` | `Heroicon::Eye` | — |
| `EditAction` | `Heroicon::PencilSquare` | — |
| `confirmSalesOrder` | `Heroicon::CheckCircle` | `primary` |
| `dispatchSale` | `Heroicon::Truck` | `success` |
| `recordReturn` | `Heroicon::ArrowUturnLeft` | `warning` |
| `cancelSalesOrder` | `Heroicon::XMark` | `danger` |
| `DeleteAction` | `Heroicon::Trash` | `danger` |
| `RestoreAction` | `Heroicon::ArrowUturnLeft` | `warning` |
| `ForceDeleteAction` | `Heroicon::Trash` | `danger` |

#### ProductResource

| Action | Icon | Color |
|---|---|---|
| `ViewAction` | `Heroicon::Eye` | — |
| `EditAction` | `Heroicon::PencilSquare` | — |
| `DeleteAction` | `Heroicon::Trash` | `danger` |
| `RestoreAction` | `Heroicon::ArrowUturnLeft` | `warning` |
| `SetCurrentPriceAction` | `Heroicon::CurrencyDollar` | `primary` |
| `EditProductFamilyAction` | `Heroicon::FolderOpen` | — |
| `ManageUnitConversionsAction` | `Heroicon::Scale` | — |
| `QuickStockAdjustmentAction` | `Heroicon::AdjustmentsHorizontal` | `warning` |

### 1D.2 Form Field Icons

| Field | Icon Type | Icon |
|---|---|---|
| `sku` | `prefixIcon` | `Heroicon::Tag` |
| `barcode` | `prefixIcon` | `Heroicon::QrCode` |
| `name` | `prefixIcon` | `Heroicon::Identification` |
| `base_unit_name` | `prefixIcon` | `Heroicon::Scale` |
| `reorder_point` | `prefixIcon` | `Heroicon::ExclamationTriangle` |
| `product_id` | `prefixIcon` | `Heroicon::FolderOpen` |
| `cost_price` / `sale_price` / `unit_cost_price` | `prefixIcon` | `Heroicon::CurrencyDollar` |
| `total_financial_loss` | `prefixIcon` | `Heroicon::ExclamationTriangle` |
| `from_warehouse_id` | `prefixIcon` | `Heroicon::BuildingOffice` |
| `to_warehouse_id` / `warehouse_id` | `prefixIcon` | `Heroicon::BuildingOffice2` |
| `supplier_id` | `prefixIcon` | `Heroicon::BuildingStorefront` |
| `customer_id` | `prefixIcon` | `Heroicon::UserGroup` |
| `*_qty` / `quantity` | `prefixIcon` | `Heroicon::Hashtag` |
| `*_unit_name` | `prefixIcon` | `Heroicon::Scale` |
| `*_unit_ratio` | `hintIcon` | `Heroicon::InformationCircle` |
| `notes` / `negotiation_reason` | `prefixIcon` | `Heroicon::ChatBubbleBottomCenterText` |
| `loss_category` | `prefixIcon` | `Heroicon::ExclamationTriangle` |
| `is_active` | `onIcon` / `offIcon` | `Heroicon::CheckCircle` / `Heroicon::XCircle` |
| `update_cost_price` | `onIcon` | `Heroicon::CurrencyDollar` |

### 1D.3 Section & Tab Header Icons

Headings resolve through the translation catalogue (§0A.7) — the key below is the exact value passed to `Section::make()` / `Tab::make()` in the form and infolist contracts. Icons match the implementation.

**Form sections**

| Section (translation key) | Icon |
|---|---|
| `resources.transfer_requisitions.sections.routing` | `Heroicon::BuildingOffice` |
| `resources.direct_transfers.sections.routing` | `Heroicon::BuildingOffice` |
| `resources.direct_transfers.sections.stock_allocation` | `Heroicon::Cube` |
| `resources.purchase_orders.form.supplier_warehouse` | `Heroicon::BuildingStorefront` |
| `resources.sales_orders.form.customer_warehouse` | `Heroicon::UserGroup` |
| `resources.warehouses.form.profile` | `Heroicon::BuildingOffice` |
| `resources.warehouses.form.access_status` | `Heroicon::ShieldCheck` |

**Infolist sections**

| Section (translation key) | Icon |
|---|---|
| `resources.products.infolist.identity` | `Heroicon::Identification` |
| `resources.products.infolist.pricing` | `Heroicon::CurrencyDollar` |
| `resources.products.infolist.unit_conversions` | `Heroicon::Scale` |
| `resources.transfer_requisitions.infolist.profile` | `Heroicon::DocumentText` |
| `resources.transfer_requisitions.infolist.signoffs` | `Heroicon::ShieldCheck` |
| `resources.transfer_requisitions.infolist.manifest` | `Heroicon::ClipboardDocumentList` |
| `resources.transfer_requisitions.infolist.negotiation_history` | `Heroicon::ChatBubbleLeftRight` |
| `resources.direct_transfers.infolist.profile` | `Heroicon::ArrowPath` |
| `resources.direct_transfers.infolist.authorization` | `Heroicon::ShieldCheck` |
| `resources.direct_transfers.infolist.manifest` | `Heroicon::ClipboardDocumentList` |
| `resources.in_transits.infolist.cargo` | `Heroicon::Truck` |
| `resources.stock_movements.infolist.movement` | `Heroicon::QueueList` |
| `resources.loss_ledgers.infolist.record` | `Heroicon::ExclamationTriangle` |
| `resources.loss_ledgers.infolist.financial_impact` | `Heroicon::CurrencyDollar` |
| `resources.purchase_orders.infolist.profile` | `Heroicon::DocumentText` |
| `resources.purchase_orders.infolist.signoffs` | `Heroicon::ShieldCheck` |
| `resources.purchase_orders.infolist.line_items` | `Heroicon::ClipboardDocumentList` |
| `resources.sales_orders.infolist.profile` | `Heroicon::DocumentText` |
| `resources.sales_orders.infolist.signoffs` | `Heroicon::ShieldCheck` |
| `resources.sales_orders.infolist.line_items` | `Heroicon::ClipboardDocumentList` |
| `resources.warehouses.infolist.profile` | `Heroicon::BuildingOffice` |
| `resources.warehouses.infolist.status` | `Heroicon::ShieldCheck` |
| `resources.warehouses.infolist.assigned_staff` | `Heroicon::UserGroup` |

**Product tabs** (`ProductForm`, §7A)

| Tab (translation key) | Icon |
|---|---|
| `resources.products.tabs.identity` | `Heroicon::Identification` |
| `resources.products.tabs.stock_pricing` | `Heroicon::CurrencyDollar` |
| `resources.products.tabs.status` | `Heroicon::CheckCircle` |

### 1D.4 Icon Consistency Rules

1. Always `Heroicon` enum, never raw string.
2. Semantic match — `PaperAirplane` for submit, `Truck` for dispatch, etc.
3. No decorative icons.
4. Consistent action-to-icon mapping across resources.
5. Solid for active, outlined for resting (navigation).
6. Disabled/derived fields use `hintIcon`, not `prefixIcon`.

---

## 🗄️ Section 2: Complete Database Schema (22 Tables: 21 New + 1 Alteration)

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

**Code formats:** `sku` follows the `SKU-####-??` pattern (uppercase, matching `ProductVariantFactory`); `barcode` follows EAN-13 (matching `ProductVariantFactory::ean13()`), nullable, unique when present. Unique `NULL` values are treated as distinct under the project's PostgreSQL/MySQL semantics. **Owner decision (recorded):** blank barcode input is normalized to `null`, never stored as an empty string and never rejected — at the form boundary via `dehydrateStateUsing(fn ($state) => filled($state) ? $state : null)` with the `ProductVariant::barcode()` attribute mutator as backstop.

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
Constraints: At most one `is_current = true` row per variant.
Enforcement: canonical price writer (service layer, same transaction) + partial unique index on `(product_variant_id) WHERE is_current = true` on `pgsql`/`sqlite` per the §2 canonical migration exemplar; MySQL relies on the service layer alone (no partial-index support).

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

**Invariants:** Base-unit self-conversion row required (F19), auto-created by observer, undeletable via UI.

### 5. warehouses

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| code | string, unique | No | — |
| name | string | No | — |
| location | string | Yes | — |
| is_active | boolean | No | true |
| created_at / updated_at | timestamp | Yes | — |

**Code format:** manually entered (and factory-seeded) `code` values follow the `WH-####` pattern (uppercase), `maxLength(50)`, matching `WarehouseFactory` and `WarehouseForm`. `code` may be entered manually or left empty: when empty, the Create page derives it from `name` via `CreateWarehouse::deriveCode()` (uppercase, non-alphanumeric runs replaced with `-`, trimmed, truncated to 50 chars — e.g. `NORTH-WAREHOUSE`), appending `-NNN` on unique collision. Derived codes therefore do not follow the `WH-####` pattern by design; the `WH-####` pattern governs the manual/factory path only.

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

**Reference-field contract:** `reference_code` is nullable here (unlike the non-nullable unique headers) because `InventoryService::recordMovement()` accepts `?string $referenceCode = null` for manual movements; header-created movements always copy the header code. `reference_id` stores the stringified header id (e.g. `(string) $header->id`). `reference_type` is one of `App\Models\TransferRequisition`, `App\Models\DirectTransfer`, `App\Models\PurchaseOrder`, `App\Models\SalesOrder`, `App\Models\SalesOrderItem` (the only values written by the services).

### 7. transfer_requisitions

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| reference_code | string, unique | No | — |
| from_warehouse_id | FK → warehouses.id (restrictOnDelete) | No | — |
| to_warehouse_id | FK → warehouses.id (restrictOnDelete) | No | — |
| status | string | No | `TransferRequisitionStatus::Draft->value` |
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

Indexes: `status`, `created_at`, `(from_warehouse_id, to_warehouse_id)`

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
| received_qty | integer | No | 0 |
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
| cleared_at | timestamp | Yes | — |
| created_at / updated_at | timestamp | Yes | — |

Indexes: `(transfer_requisition_id, status)`

### 11. loss_ledgers

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| transfer_requisition_id | FK → transfer_requisitions.id (cascadeOnDelete) | Yes | — |
| transfer_requisition_item_id | FK → transfer_requisition_items.id (cascadeOnDelete) | Yes | — |
| product_variant_id | FK → product_variants.id (restrictOnDelete) | No | — |
| warehouse_id | FK → warehouses.id (restrictOnDelete) | No | — |
| lost_base_qty | integer | No | 0 |
| damaged_base_qty | integer | No | 0 |
| unit_cost_price | decimal(15,4) | No | — |
| total_financial_loss | decimal(15,4) | No | — |
| loss_category | string | No | shortfall |
| notes | text | Yes | — |
| recorded_by | FK → users.id | Yes | — |
| recorded_at | timestamp | No | current time |
| created_at / updated_at | timestamp | Yes | — |

Indexes: `(warehouse_id, recorded_at)`

> Note: Stored values correspond to `App\Enums\LossCategory` cases (§4.9); the column stays `string` per the backed-enum convention and is cast on the model (§3.11).

### 12. users (altered)

| Column | Type | Nullable | Default |
|---|---|---|---|
| role | string | No | warehouse_staff |
| is_active | boolean | No | true |

### 13. user_warehouse (pivot)

| Column | Type | Nullable | Default |
|---|---|---|---|
| user_id | FK → users.id (cascadeOnDelete, part of composite PK) | No | — |
| warehouse_id | FK → warehouses.id (cascadeOnDelete, part of composite PK) | No | — |

Primary key: composite `(user_id, warehouse_id)`

**Editing rule:** Warehouse assignments are edited from `UserResource` only. `WarehouseForm` presents the pivot read-only to avoid last-write-wins conflicts.

### 14. suppliers

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| name | string | No | — |
| contact_person | string | Yes | — |
| phone | string | Yes | — |
| email | string | Yes | — |
| address | text | Yes | — |
| is_active | boolean | No | true |
| deleted_at | timestamp | Yes | — |
| created_at / updated_at | timestamp | Yes | — |

Suppliers have no `code` by design; they are identified by `name` (owner decision).

### 15. customers

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| name | string | No | — |
| contact_person | string | Yes | — |
| phone | string | Yes | — |
| email | string | Yes | — |
| address | text | Yes | — |
| is_active | boolean | No | true |
| deleted_at | timestamp | Yes | — |
| created_at / updated_at | timestamp | Yes | — |

Customers have no `code` by design; they are identified by `name` (owner decision).

### 16. purchase_orders

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| reference_code | string, unique | No | — |
| supplier_id | FK → suppliers.id (restrictOnDelete) | No | — |
| warehouse_id | FK → warehouses.id (restrictOnDelete) | No | — |
| status | string | No | `PurchaseOrderStatus::Draft->value` |
| update_cost_price | boolean | No | false |
| ordered_by | FK → users.id | No | — |
| received_by | FK → users.id | Yes | — |
| ordered_at | timestamp | Yes | — |
| received_at | timestamp | Yes | — |
| cancelled_at | timestamp | Yes | — |
| notes | text | Yes | — |
| deleted_at | timestamp | Yes | — |
| created_at / updated_at | timestamp | Yes | — |

Indexes: `status`, `created_at`, `(supplier_id, warehouse_id)`

### 17. purchase_order_items

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| purchase_order_id | FK → purchase_orders.id (cascadeOnDelete) | No | — |
| product_variant_id | FK → product_variants.id (restrictOnDelete) | No | — |
| ordered_unit_name | string | No | — |
| ordered_unit_ratio | integer | No | — |
| ordered_qty | integer | No | — |
| ordered_base_qty | integer | No | — |
| unit_cost_price | decimal(15,4) | No | 0.0000 |
| received_base_qty | integer | No | 0 |
| notes | text | Yes | — |
| created_at / updated_at | timestamp | Yes | — |

Indexes: `purchase_order_id`

### 18. sales_orders

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| reference_code | string, unique | No | — |
| customer_id | FK → customers.id (restrictOnDelete) | No | — |
| warehouse_id | FK → warehouses.id (restrictOnDelete) | No | — |
| status | string | No | `SalesOrderStatus::Draft->value` |
| ordered_by | FK → users.id | No | — |
| dispatched_by | FK → users.id | Yes | — |
| ordered_at | timestamp | Yes | — |
| confirmed_at | timestamp | Yes | — |
| dispatched_at | timestamp | Yes | — |
| cancelled_at | timestamp | Yes | — |
| notes | text | Yes | — |
| deleted_at | timestamp | Yes | — |
| created_at / updated_at | timestamp | Yes | — |

Indexes: `status`, `created_at`, `(customer_id, warehouse_id)`

### 19. sales_order_items

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| sales_order_id | FK → sales_orders.id (cascadeOnDelete) | No | — |
| product_variant_id | FK → product_variants.id (restrictOnDelete) | No | — |
| unit_name | string | No | — |
| unit_ratio | integer | No | — |
| qty | integer | No | — |
| base_qty | integer | No | — |
| unit_sale_price_snapshot | decimal(15,4) | No | 0.0000 |
| dispatched_base_qty | integer | No | 0 |
| notes | text | Yes | — |
| created_at / updated_at | timestamp | Yes | — |

Indexes: `sales_order_id`

### 20. stock_movement_idempotency_keys

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| transfer_requisition_id | FK → transfer_requisitions.id (cascadeOnDelete) | No | — |
| payload_checksum | string(64) | No | — |
| resulting_item_states | json | No | — |
| created_at | timestamp | No | current time |

Indexes: unique on `(transfer_requisition_id, payload_checksum)`

### 21. direct_transfers

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| reference_code | string, unique | No | — |
| from_warehouse_id | FK → warehouses.id (restrictOnDelete) | No | — |
| to_warehouse_id | FK → warehouses.id (restrictOnDelete) | No | — |
| notes | text | Yes | — |
| transferred_by | FK → users.id (nullOnDelete) | Yes | — |
| transferred_at | timestamp | No | — |
| created_at / updated_at | timestamp | Yes | — |

Indexes: `created_at`, `(from_warehouse_id, to_warehouse_id)`

### 22. direct_transfer_items

| Column | Type | Nullable | Default |
|---|---|---|---|
| id | bigint (PK) | No | — |
| direct_transfer_id | FK → direct_transfers.id (cascadeOnDelete) | No | — |
| product_variant_id | FK → product_variants.id (restrictOnDelete) | No | — |
| unit_name | string | No | — |
| unit_ratio | integer | No | — |
| qty | integer | No | — |
| base_qty | integer | No | — |
| notes | text | Yes | — |
| created_at / updated_at | timestamp | Yes | — |

Indexes: `direct_transfer_id`

**Invariants:**
- All items in a `DirectTransfer` share the header's `from_warehouse_id` and `to_warehouse_id`.
- `from_warehouse_id ≠ to_warehouse_id` (enforced at service layer).
- One paired `TransferOut` + `TransferIn` movement per item, tagged `reference_type = App\Models\DirectTransfer::class` and `reference_id = header.id`.

**Reference-code generation:** format comes from `App\Support\GeneratesReferenceCodes::generateReferenceCode($prefix)` (pure function: `PREFIX-YmdHis-random(100-999)`, hyphenated, no database access, no retry). Collision retry (regenerate, up to 5 attempts on unique-constraint violation) is implemented per call site at its correct transaction boundary — never caught inside `DB::transaction()`. Collision window ≈1/900 per same-second, same-type pair. Factories call the same helper with `$this->faker->unique()->numberBetween(100, 999)` as the random part so seeds fail loudly instead of duplicating.

`app/Support/GeneratesReferenceCodes.php` — canonical implementation (pure function; no database access, no retry — retry lives at the call site):

```php
namespace App\Support;

final class GeneratesReferenceCodes
{
    public static function generateReferenceCode(string $prefix, ?int $random = null): string
    {
        return $prefix . '-' . now()->format('YmdHis') . '-' . ($random ?? random_int(100, 999));
    }
}
```

### New StockMovementType Cases

Add `Purchase`, `Sale`, `SaleReturn`, `PurchaseReturn`.

### Migration File Contract

Each of the 22 `database/migrations/` files in the §25 file map implements its §2 table definition verbatim — columns, types, nullability, defaults, foreign-key actions, indexes, and constraints. File order follows the §25 listing, which is the FK-dependency order required by Phase 01:

| Migration file | Implements |
|---|---|
| `xxxx_xx_xx_create_products_table.php` | §2.1 `products` |
| `xxxx_xx_xx_create_product_variants_table.php` | §2.2 `product_variants` |
| `xxxx_xx_xx_create_product_variant_prices_table.php` | §2.3 `product_variant_prices` |
| `xxxx_xx_xx_create_product_variant_unit_conversions_table.php` | §2.4 `product_variant_unit_conversions` |
| `xxxx_xx_xx_create_warehouses_table.php` | §2.5 `warehouses` |
| `xxxx_xx_xx_create_stock_movements_table.php` | §2.6 `stock_movements` |
| `xxxx_xx_xx_create_transfer_requisitions_table.php` | §2.7 `transfer_requisitions` |
| `xxxx_xx_xx_create_transfer_requisition_items_table.php` | §2.8 `transfer_requisition_items` |
| `xxxx_xx_xx_create_transfer_requisition_item_revisions_table.php` | §2.9 `transfer_requisition_item_revisions` |
| `xxxx_xx_xx_create_in_transits_table.php` | §2.10 `in_transits` |
| `xxxx_xx_xx_create_loss_ledgers_table.php` | §2.11 `loss_ledgers` |
| `xxxx_xx_xx_add_role_columns_to_users_table.php` | §2.12 `users` (altered) |
| `xxxx_xx_xx_create_user_warehouse_table.php` | §2.13 `user_warehouse` (pivot) |
| `xxxx_xx_xx_create_suppliers_table.php` | §2.14 `suppliers` |
| `xxxx_xx_xx_create_customers_table.php` | §2.15 `customers` |
| `xxxx_xx_xx_create_purchase_orders_table.php` | §2.16 `purchase_orders` |
| `xxxx_xx_xx_create_purchase_order_items_table.php` | §2.17 `purchase_order_items` |
| `xxxx_xx_xx_create_sales_orders_table.php` | §2.18 `sales_orders` |
| `xxxx_xx_xx_create_sales_order_items_table.php` | §2.19 `sales_order_items` |
| `xxxx_xx_xx_create_stock_movement_idempotency_keys_table.php` | §2.20 `stock_movement_idempotency_keys` |
| `xxxx_xx_xx_create_direct_transfers_table.php` | §2.21 `direct_transfers` |
| `xxxx_xx_xx_create_direct_transfer_items_table.php` | §2.22 `direct_transfer_items` |

Conventions (all 22 files): anonymous migration class (`return new class extends Migration`), `Schema::create` / `Schema::table` only, FK actions exactly as stated in §2 (`cascadeOnDelete`, `restrictOnDelete`, `nullOnDelete`), unique/index declarations exactly as stated in §2, `down()` dropping in reverse. No business logic, no seeding, no data backfill inside migrations.

Canonical exemplar (`xxxx_xx_xx_create_products_table.php`, derived verbatim from §2.1):

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

Canonical exemplar (`xxxx_xx_xx_create_product_variants_table.php`, derived verbatim from §2.2):

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('barcode', 32)->nullable()->unique();
            $table->string('name');
            $table->string('base_unit_name');
            $table->integer('reorder_point')->default(0);
            $table->json('attributes')->nullable();
            $table->json('images')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['product_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
```

Canonical exemplar (`xxxx_xx_xx_create_product_variant_prices_table.php`, derived verbatim from §2.3):

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->decimal('cost_price', 15, 4)->default(0.0000);
            $table->decimal('sale_price', 15, 4)->default(0.0000);
            $table->timestamp('effective_from')->useCurrent();
            $table->boolean('is_current')->default(true);
            $table->foreignId('set_by')->nullOnDelete()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_variant_id', 'effective_from']);
        });

        // §2.3 constraint "at most one is_current = true row per variant":
        // enforced at the service layer by the canonical price writer
        // (`SetCurrentPriceAction` / price `setCurrentPrice()` path, which
        // clears the previous current row inside the same transaction)
        // plus a partial unique index on (product_variant_id) WHERE
        // is_current = true on drivers with filtered-index support
        // (PostgreSQL, SQLite). A plain unique on product_variant_id would
        // forbid price history, so no such index is declared above. MySQL
        // has no partial-index support and relies on the service layer alone.
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                "CREATE UNIQUE INDEX product_variant_prices_current_unique ON product_variant_prices (product_variant_id) WHERE is_current = true"
            );
        }
    }

    public function down(): void
    {
        // Reverse of `up()`: drop the raw partial unique index first, then
        // the table (the table drop would remove it implicitly, but the
        // `DB::statement`-created index is accounted for explicitly here).
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS product_variant_prices_current_unique');
        }

        Schema::dropIfExists('product_variant_prices');
    }
};
```

Canonical exemplar (`xxxx_xx_xx_create_product_variant_unit_conversions_table.php`, derived verbatim from §2.4):

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('unit_name');
            $table->integer('base_unit_ratio');
            $table->boolean('is_default_purchase')->default(false);
            $table->boolean('is_default_transfer')->default(false);
            $table->timestamps();

            $table->unique(['product_variant_id', 'unit_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_unit_conversions');
    }
};
```

Canonical exemplar (`xxxx_xx_xx_create_warehouses_table.php`, derived verbatim from §2.5):

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
```

Canonical exemplar (`xxxx_xx_xx_create_stock_movements_table.php`, derived verbatim from §2.6):

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->integer('quantity');
            $table->string('unit_name_used');
            $table->integer('unit_ratio_used')->default(1);
            $table->foreignId('related_movement_id')->nullOnDelete()->constrained('stock_movements');
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('reference_code')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullOnDelete()->constrained('users');
            $table->timestamps();

            $table->index(['product_variant_id', 'warehouse_id']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
```

Canonical exemplar (`xxxx_xx_xx_create_transfer_requisitions_table.php`, derived verbatim from §2.7):

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 32)->unique();
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            // Default mirrors §2.7: TransferRequisitionStatus::Draft->value ('draft').
            // Migrations pin the literal so historic migrations stay immutable
            // when enum code evolves — same convention as §2.10 ('in_transit').
            $table->string('status')->default('draft');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullOnDelete()->constrained('users');
            $table->foreignId('dispatched_by')->nullOnDelete()->constrained('users');
            $table->foreignId('received_by')->nullOnDelete()->constrained('users');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
            $table->index(['from_warehouse_id', 'to_warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_requisitions');
    }
};
```

Canonical exemplar (`xxxx_xx_xx_create_transfer_requisition_items_table.php`, derived verbatim from §2.8):

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('substitute_product_variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->string('requested_unit_name');
            $table->integer('requested_unit_ratio');
            $table->integer('requested_qty');
            $table->integer('requested_base_qty');
            $table->string('approved_unit_name')->nullable();
            $table->integer('approved_unit_ratio')->nullable();
            $table->integer('approved_qty')->nullable();
            $table->integer('approved_base_qty')->nullable();
            $table->integer('shipped_base_qty')->default(0);
            $table->integer('received_good_base_qty')->default(0);
            $table->integer('received_damaged_base_qty')->default(0);
            $table->integer('received_qty')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('transfer_requisition_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_requisition_items');
    }
};
```

Canonical exemplar (`xxxx_xx_xx_create_transfer_requisition_item_revisions_table.php`, derived verbatim from §2.9):

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_requisition_item_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_requisition_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('substitute_product_variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->string('proposed_unit_name');
            $table->integer('proposed_unit_ratio');
            $table->integer('proposed_qty');
            $table->integer('proposed_base_qty');
            $table->text('negotiation_reason')->nullable();
            $table->string('side');
            $table->string('status')->default('pending');
            $table->foreignId('responds_to_revision_id')->nullOnDelete()->constrained('transfer_requisition_item_revisions');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index('transfer_requisition_item_id');
            $table->index(['transfer_requisition_item_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_requisition_item_revisions');
    }
};
```

Canonical exemplar (`xxxx_xx_xx_create_in_transits_table.php`, derived verbatim from §2.10):

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('in_transits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transfer_requisition_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->integer('dispatched_base_qty');
            $table->timestamp('dispatched_at');
            $table->string('status')->default('in_transit');
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();

            $table->index(['transfer_requisition_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('in_transits');
    }
};
```

Canonical exemplar (`xxxx_xx_xx_create_loss_ledgers_table.php`, derived verbatim from §2.11):

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loss_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_requisition_id')->nullable()->constrained('transfer_requisitions')->cascadeOnDelete();
            $table->foreignId('transfer_requisition_item_id')->nullable()->constrained('transfer_requisition_items')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->integer('lost_base_qty')->default(0);
            $table->integer('damaged_base_qty')->default(0);
            $table->decimal('unit_cost_price', 15, 4);
            $table->decimal('total_financial_loss', 15, 4);
            $table->string('loss_category')->default('shortfall');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullOnDelete()->constrained('users');
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            $table->index(['warehouse_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loss_ledgers');
    }
};
```

Canonical exemplar (`xxxx_xx_xx_add_role_columns_to_users_table.php`, derived verbatim from §2.12):

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('warehouse_staff');
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
            $table->dropColumn('is_active');
        });
    }
};
```

Canonical exemplar (`xxxx_xx_xx_create_user_warehouse_table.php`, derived verbatim from §2.13):

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_warehouse', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_warehouse');
    }
};
```

Canonical exemplar (`xxxx_xx_xx_create_suppliers_table.php`, derived verbatim from §2.14):

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email', 120)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
```

Canonical exemplar (`xxxx_xx_xx_create_customers_table.php`, derived verbatim from §2.15):

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email', 120)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
```

Canonical exemplar (`xxxx_xx_xx_create_purchase_orders_table.php`, derived verbatim from §2.16):

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 32)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            // Default mirrors §2.16: PurchaseOrderStatus::Draft->value ('draft').
            // Literal is pinned so the migration stays immutable (§2 conventions).
            $table->string('status')->default('draft');
            $table->boolean('update_cost_price')->default(false);
            $table->foreignId('ordered_by')->constrained('users');
            $table->foreignId('received_by')->nullOnDelete()->constrained('users');
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
            $table->index(['supplier_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
```

Canonical exemplar (`xxxx_xx_xx_create_purchase_order_items_table.php`, derived verbatim from §2.17):

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('ordered_unit_name');
            $table->integer('ordered_unit_ratio');
            $table->integer('ordered_qty');
            $table->integer('ordered_base_qty');
            $table->decimal('unit_cost_price', 15, 4)->default(0.0000);
            $table->integer('received_base_qty')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('purchase_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
```

Canonical exemplar (`xxxx_xx_xx_create_sales_orders_table.php`, derived verbatim from §2.18):

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 32)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            // Default mirrors §2.18: SalesOrderStatus::Draft->value ('draft').
            // Literal is pinned so the migration stays immutable (§2 conventions).
            $table->string('status')->default('draft');
            $table->foreignId('ordered_by')->constrained('users');
            $table->foreignId('dispatched_by')->nullOnDelete()->constrained('users');
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
            $table->index(['customer_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
```

Canonical exemplar (`xxxx_xx_xx_create_sales_order_items_table.php`, derived verbatim from §2.19):

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('unit_name');
            $table->integer('unit_ratio');
            $table->integer('qty');
            $table->integer('base_qty');
            $table->decimal('unit_sale_price_snapshot', 15, 4)->default(0.0000);
            $table->integer('dispatched_base_qty')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('sales_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
    }
};
```

Canonical exemplar (`xxxx_xx_xx_create_stock_movement_idempotency_keys_table.php`, derived verbatim from §2.20):

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movement_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_requisition_id')
                ->constrained('transfer_requisitions')
                ->cascadeOnDelete();
            $table->string('payload_checksum', 64);
            $table->json('resulting_item_states');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['transfer_requisition_id', 'payload_checksum'],
                'idempotency_requisition_checksum_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movement_idempotency_keys');
    }
};
```

> **Note:** This is the same canonical migration restated in §18.4 (`idempotency_requisition_checksum_unique`). There is exactly one implementation of the `stock_movement_idempotency_keys` table; §18.4 is the authoritative copy and this exemplar mirrors it verbatim.

Canonical exemplar (`xxxx_xx_xx_create_direct_transfers_table.php`, derived verbatim from §2.21):

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direct_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 32)->unique();
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('transferred_by')->nullOnDelete()->constrained('users');
            $table->timestamp('transferred_at');
            $table->timestamps();

            $table->index('created_at');
            $table->index(['from_warehouse_id', 'to_warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_transfers');
    }
};
```

Canonical exemplar (`xxxx_xx_xx_create_direct_transfer_items_table.php`, derived verbatim from §2.22):

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direct_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('direct_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('unit_name');
            $table->integer('unit_ratio');
            $table->integer('qty');
            $table->integer('base_qty');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('direct_transfer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_transfer_items');
    }
};
```

---

## 🛠️ Section 3: Model Layer

### 3.1 Product

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['name', 'category'];

    public function variants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
}
```

Soft-delete guard lives solely in `App\Observers\ProductObserver` (§3.19, registered in `AppServiceProvider::boot()` per §17.3) — no `booted()` override here; a duplicated closure would throw twice for the same violation and drift from the canonical observer.

### 3.2 ProductVariant

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'product_id', 'sku', 'barcode', 'name', 'base_unit_name',
        'reorder_point', 'attributes', 'images', 'is_active',
    ];

    protected $casts = [
        'attributes' => 'array',
        'images'     => 'array',
        'is_active'  => 'boolean',
    ];

    /**
     * Blank barcodes normalize to null (owner decision, §2).
     */
    protected function barcode(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => filled($value) ? $value : null,
        );
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

    /**
     * Physical on-hand = sum of all signed stock_movements quantities.
     *
     * The `(int)` cast is intentional defensive normalization (`sum()`
     * returns mixed depending on the driver); `quantity` is already an
     * integer column (§2.6), so the cast never changes the value.
     */
    public function onHandQuantity(?int $warehouseId = null): int
    {
        return (int) $this->stockMovements()
            ->when($warehouseId, fn (Builder $q) => $q->where('warehouse_id', $warehouseId))
            ->sum('quantity');
    }

    /**
     * Reserved = sum of pending quantities from Confirmed requisitions only.
     * Scope boundary is intentional and permanent (Principle 13).
     *
     * Reservation is booked against the effective (actual) variant — the
     * substitute when one was negotiated, otherwise the requested variant —
     * mirroring `dispatchTransfer()` availability via `actualVariantId()`.
     *
     * $excludeTransferRequisitionId excludes the requisition currently being
     * dispatched so its own outstanding qty is not counted against itself.
     */
    public function reservedQuantity(
        ?int $warehouseId = null,
        ?int $excludeTransferRequisitionId = null,
    ): int {
        return (int) TransferRequisitionItem::query()
            ->whereNotNull('approved_base_qty')
            ->where(fn (Builder $q) =>
                $q->where(fn (Builder $qq) =>
                    $qq->where('product_variant_id', $this->id)
                       ->whereNull('substitute_product_variant_id'))
                  ->orWhere('substitute_product_variant_id', $this->id))
            ->whereHas('transferRequisition', function (Builder $q) use ($warehouseId, $excludeTransferRequisitionId) {
                $q->where('status', \App\Enums\TransferRequisitionStatus::Confirmed->value)
                  ->when($warehouseId, fn (Builder $qq) => $qq->where('from_warehouse_id', $warehouseId))
                  ->when($excludeTransferRequisitionId, fn (Builder $qq) => $qq->where('id', '!=', $excludeTransferRequisitionId));
            })
            ->sum('approved_base_qty');
    }

    /**
     * Sales reservation — separate from procurement reservation (A5).
     *
     * `Confirmed` orders reserve their full `base_qty`. `PartiallyDispatched`
     * orders reserve only the outstanding remainder
     * (`base_qty - dispatched_base_qty`, floored at 0 via
     * `SalesOrderItem::outstandingBaseQty()` semantics) — summing the full
     * `base_qty` would over-reserve stock already shipped.
     *
     * $excludeSalesOrderId excludes the sales order currently being dispatched
     * so its own outstanding qty is not counted against itself.
     */
    public function reservedForSalesQuantity(
        ?int $warehouseId = null,
        ?int $excludeSalesOrderId = null,
    ): int {
        $confirmed = (int) SalesOrderItem::query()
            ->where('product_variant_id', $this->id)
            ->whereHas('salesOrder', function (Builder $q) use ($warehouseId, $excludeSalesOrderId) {
                $q->where('status', \App\Enums\SalesOrderStatus::Confirmed->value)
                  ->when($warehouseId, fn (Builder $qq) => $qq->where('warehouse_id', $warehouseId))
                  ->when($excludeSalesOrderId, fn (Builder $qq) => $qq->where('id', '!=', $excludeSalesOrderId));
            })
            ->sum('base_qty');

        $partiallyDispatched = (int) SalesOrderItem::query()
            ->where('product_variant_id', $this->id)
            ->whereHas('salesOrder', function (Builder $q) use ($warehouseId, $excludeSalesOrderId) {
                $q->where('status', \App\Enums\SalesOrderStatus::PartiallyDispatched->value)
                  ->when($warehouseId, fn (Builder $qq) => $qq->where('warehouse_id', $warehouseId))
                  ->when($excludeSalesOrderId, fn (Builder $qq) => $qq->where('id', '!=', $excludeSalesOrderId));
            })
            ->selectRaw('SUM(CASE WHEN base_qty - dispatched_base_qty > 0 THEN base_qty - dispatched_base_qty ELSE 0 END) as total')
            ->value('total');

        return $confirmed + $partiallyDispatched;
    }

    /**
     * Available = on hand - reserved - reserved for sales.
     */
    public function availableQuantity(
        ?int $warehouseId = null,
        ?int $excludeSalesOrderId = null,
        ?int $excludeTransferRequisitionId = null,
    ): int {
        return $this->onHandQuantity($warehouseId)
            - $this->reservedQuantity($warehouseId, $excludeTransferRequisitionId)
            - $this->reservedForSalesQuantity($warehouseId, $excludeSalesOrderId);
    }

    /**
     * Batched available lookup — exactly 3 top-level aggregate queries
     * regardless of variant count (one per reservation class: on-hand,
     * transfer-reserved, sales-reserved). The `whereHas`/`CASE` subqueries
     * execute inside those 3 queries; they do not add top-level queries.
     *
     * Sales reservation mirrors `reservedForSalesQuantity()`: `Confirmed`
     * orders contribute full `base_qty`, `PartiallyDispatched` orders
     * contribute only the outstanding remainder floored at 0 via a portable
     * `CASE WHEN ... ELSE 0 END` (MySQL, PostgreSQL, and SQLite) inside a
     * single joined aggregate.
     *
     * @param  array<int>  $variantIds
     * @return array<int, int>  variant_id => available_qty
     */
    public static function batchAvailableQuantity(
        array $variantIds,
        int $warehouseId,
        ?int $excludeSalesOrderId = null,
        ?int $excludeTransferRequisitionId = null,
    ): array {
        if (empty($variantIds)) {
            return [];
        }

        $onHand = StockMovement::query()
            ->selectRaw('product_variant_id, SUM(quantity) as total')
            ->whereIn('product_variant_id', $variantIds)
            ->where('warehouse_id', $warehouseId)
            ->groupBy('product_variant_id')
            ->pluck('total', 'product_variant_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        // Transfer reservation is booked against the effective (actual)
        // variant — the substitute when one was negotiated, otherwise the
        // requested variant — mirroring `dispatchTransfer()` availability
        // via `actualVariantId()`.
        $effectiveVariant = 'CASE WHEN substitute_product_variant_id IS NOT NULL THEN substitute_product_variant_id ELSE product_variant_id END';

        $reserved = TransferRequisitionItem::query()
            ->selectRaw("{$effectiveVariant} as effective_variant_id, SUM(approved_base_qty) as total")
            ->whereNotNull('approved_base_qty')
            ->where(fn (Builder $q) => $q
                ->whereIn('product_variant_id', $variantIds)
                ->orWhereIn('substitute_product_variant_id', $variantIds))
            ->whereHas('transferRequisition', fn (Builder $q) =>
                $q->where('status', \App\Enums\TransferRequisitionStatus::Confirmed->value)
                  ->where('from_warehouse_id', $warehouseId)
                  ->when($excludeTransferRequisitionId, fn (Builder $qq) => $qq->where('id', '!=', $excludeTransferRequisitionId)))
            ->groupByRaw($effectiveVariant)
            ->pluck('total', 'effective_variant_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $salesReserved = SalesOrderItem::query()
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
            ->whereIn('sales_order_items.product_variant_id', $variantIds)
            ->whereIn('sales_orders.status', [
                \App\Enums\SalesOrderStatus::Confirmed->value,
                \App\Enums\SalesOrderStatus::PartiallyDispatched->value,
            ])
            ->where('sales_orders.warehouse_id', $warehouseId)
            ->when($excludeSalesOrderId, fn (Builder $qq) => $qq->where('sales_orders.id', '!=', $excludeSalesOrderId))
            ->selectRaw('sales_order_items.product_variant_id')
            ->selectRaw("SUM(CASE WHEN sales_orders.status = ? THEN sales_order_items.base_qty ELSE CASE WHEN sales_order_items.base_qty - sales_order_items.dispatched_base_qty > 0 THEN sales_order_items.base_qty - sales_order_items.dispatched_base_qty ELSE 0 END END) as total", [
                \App\Enums\SalesOrderStatus::Confirmed->value,
            ])
            ->groupBy('sales_order_items.product_variant_id')
            ->pluck('total', 'product_variant_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $result = [];
        foreach ($variantIds as $id) {
            $result[$id] = ($onHand[$id] ?? 0)
                - ($reserved[$id] ?? 0)
                - ($salesReserved[$id] ?? 0);
        }

        return $result;
    }

    /**
     * Batched unit-conversion lookup — exactly 1 query.
     *
     * @param  array<int>  $variantIds
     * @return array<int, \Illuminate\Support\Collection>
     */
    public static function batchUnitConversions(array $variantIds): array
    {
        if (empty($variantIds)) {
            return [];
        }

        return ProductVariantUnitConversion::whereIn('product_variant_id', $variantIds)
            ->get()
            ->groupBy('product_variant_id')
            ->all();
    }
}
```

### 3.3 ProductVariantPrice

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariantPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_variant_id', 'cost_price', 'sale_price',
        'effective_from', 'is_current', 'set_by', 'notes',
    ];

    protected $casts = [
        'cost_price'     => 'decimal:4',
        'sale_price'     => 'decimal:4',
        'effective_from' => 'datetime',
        'is_current'     => 'boolean',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
```

### 3.4 ProductVariantUnitConversion

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariantUnitConversion extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_variant_id', 'unit_name', 'base_unit_ratio',
        'is_default_purchase', 'is_default_transfer',
    ];

    protected $casts = [
        'base_unit_ratio'      => 'integer',
        'is_default_purchase'  => 'boolean',
        'is_default_transfer'  => 'boolean',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Whether this row is the variant's base-unit self-conversion row (F19).
     *
     * Callers iterating conversions MUST eager-load `productVariant`
     * (e.g. `with('productVariant')`). When the relation is not already
     * loaded it is resolved with one explicit query instead of an
     * implicit lazy load, and a missing parent resolves to `false`
     * instead of fataling on a null property read.
     */
    public function isBaseUnitRow(): bool
    {
        $variant = $this->relationLoaded('productVariant')
            ? $this->productVariant
            : $this->productVariant()->first();

        return $variant !== null
            && $this->unit_name === $variant->base_unit_name
            && $this->base_unit_ratio === 1;
    }
}
```

### 3.5 Warehouse

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'location', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_warehouse');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function transferRequisitionsFrom(): HasMany
    {
        return $this->hasMany(TransferRequisition::class, 'from_warehouse_id');
    }

    public function transferRequisitionsTo(): HasMany
    {
        return $this->hasMany(TransferRequisition::class, 'to_warehouse_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function lossLedgers(): HasMany
    {
        return $this->hasMany(LossLedger::class);
    }

    public function directTransfersFrom(): HasMany
    {
        return $this->hasMany(DirectTransfer::class, 'from_warehouse_id');
    }

    public function directTransfersTo(): HasMany
    {
        return $this->hasMany(DirectTransfer::class, 'to_warehouse_id');
    }
}
```

### 3.6 StockMovement

```php
namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_variant_id', 'warehouse_id', 'type', 'quantity',
        'unit_name_used', 'unit_ratio_used', 'related_movement_id',
        'reference_type', 'reference_id', 'reference_code',
        'notes', 'created_by',
    ];

    protected $casts = [
        'type'            => StockMovementType::class,
        'quantity'        => 'integer',
        'unit_ratio_used' => 'integer',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function relatedMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_movement_id');
    }
}
```

### 3.7 TransferRequisition

```php
namespace App\Models;

use App\Enums\TransferRequisitionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransferRequisition extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'reference_code', 'from_warehouse_id', 'to_warehouse_id', 'status',
        'requested_by', 'approved_by', 'dispatched_by', 'received_by',
        'requested_at', 'approved_at', 'dispatched_at', 'completed_at', 'notes',
    ];

    protected $casts = [
        'status'        => TransferRequisitionStatus::class,
        'requested_at'  => 'datetime',
        'approved_at'   => 'datetime',
        'dispatched_at' => 'datetime',
        'completed_at'  => 'datetime',
    ];

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransferRequisitionItem::class);
    }

    public function inTransits(): HasMany
    {
        return $this->hasMany(InTransit::class);
    }

    public function lossLedgers(): HasMany
    {
        return $this->hasMany(LossLedger::class);
    }

    /**
     * Pre-dispatch states only (Principle 14).
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            TransferRequisitionStatus::Draft,
            TransferRequisitionStatus::Requested,
            TransferRequisitionStatus::UnderReviewFulfiller,
            TransferRequisitionStatus::UnderReviewRequestor,
            TransferRequisitionStatus::Confirmed,
        ], true);
    }
}
```

### 3.8 TransferRequisitionItem

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransferRequisitionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_requisition_id', 'product_variant_id', 'substitute_product_variant_id',
        'requested_unit_name', 'requested_unit_ratio', 'requested_qty', 'requested_base_qty',
        'approved_unit_name', 'approved_unit_ratio', 'approved_qty', 'approved_base_qty',
        'shipped_base_qty', 'received_good_base_qty', 'received_damaged_base_qty',
        'received_qty', 'notes',
    ];

    protected $casts = [
        'requested_unit_ratio'       => 'integer',
        'requested_qty'              => 'integer',
        'requested_base_qty'         => 'integer',
        'approved_unit_ratio'        => 'integer',
        'approved_qty'               => 'integer',
        'approved_base_qty'          => 'integer',
        'shipped_base_qty'           => 'integer',
        'received_good_base_qty'     => 'integer',
        'received_damaged_base_qty'  => 'integer',
        'received_qty'               => 'integer',
    ];

    public function transferRequisition(): BelongsTo
    {
        return $this->belongsTo(TransferRequisition::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function substituteProductVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'substitute_product_variant_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(TransferRequisitionItemRevision::class);
    }

    public function actualVariantId(): int
    {
        return $this->substitute_product_variant_id ?? $this->product_variant_id;
    }

    public function outstandingShippedBaseQty(): int
    {
        return max(0, (int) $this->approved_base_qty - (int) $this->shipped_base_qty);
    }
}
```

### 3.9 TransferRequisitionItemRevision

```php
namespace App\Models;

use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use App\Exceptions\InvalidRevisionTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferRequisitionItemRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_requisition_item_id', 'user_id', 'product_variant_id',
        'substitute_product_variant_id', 'proposed_unit_name', 'proposed_unit_ratio',
        'proposed_qty', 'proposed_base_qty', 'negotiation_reason', 'side',
        'status', 'responds_to_revision_id', 'responded_at',
    ];

    protected $casts = [
        'proposed_unit_ratio' => 'integer',
        'proposed_qty'        => 'integer',
        'proposed_base_qty'   => 'integer',
        'side'                => NegotiationSide::class,
        'status'              => RevisionStatus::class,
        'responded_at'        => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(TransferRequisitionItem::class, 'transfer_requisition_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function substituteProductVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'substitute_product_variant_id');
    }

    public function respondsTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'responds_to_revision_id');
    }

    public function isResolved(): bool
    {
        return $this->status !== RevisionStatus::Pending;
    }

    public function ensureCanTransitionTo(RevisionStatus $target): void
    {
        if ($this->status !== RevisionStatus::Pending) {
            throw new InvalidRevisionTransitionException($this->status->value, $target->value);
        }
        if ($target === RevisionStatus::Pending) {
            throw new InvalidRevisionTransitionException($this->status->value, $target->value);
        }
    }
}
```

### 3.10 InTransit

```php
namespace App\Models;

use App\Enums\InTransitStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InTransit extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_requisition_id', 'transfer_requisition_item_id',
        'product_variant_id', 'dispatched_base_qty', 'dispatched_at',
        'status', 'cleared_at',
    ];

    protected $casts = [
        'dispatched_base_qty' => 'integer',
        'dispatched_at'       => 'datetime',
        'status'              => InTransitStatus::class,
        'cleared_at'          => 'datetime',
    ];

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
}
```

### 3.11 LossLedger

```php
namespace App\Models;

use App\Enums\LossCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class LossLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_requisition_id', 'transfer_requisition_item_id',
        'product_variant_id', 'warehouse_id', 'lost_base_qty', 'damaged_base_qty',
        'unit_cost_price', 'total_financial_loss', 'loss_category',
        'notes', 'recorded_by', 'recorded_at',
    ];

    protected $casts = [
        'lost_base_qty'         => 'integer',
        'damaged_base_qty'      => 'integer',
        'unit_cost_price'       => 'decimal:4',
        'total_financial_loss'  => 'decimal:4',
        'loss_category'         => LossCategory::class,
        'recorded_at'           => 'datetime',
    ];

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

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Snapshot the current cost price of the variant (Principle 15).
     *
     * If cost is missing or zero, log a warning — the loss will be recorded
     * at zero financial impact but flagged for review.
     */
    public static function snapshotUnitCostFrom(ProductVariant $variant): string
    {
        $cost = $variant->currentPrice?->cost_price;

        if ($cost === null || bccomp((string) $cost, '0.0000', 4) === 0) {
            Log::warning('Loss recorded with missing or zero cost price.', [
                'product_variant_id' => $variant->id,
                'sku'                => $variant->sku,
            ]);
            return '0.0000';
        }

        return (string) $cost;
    }

    /**
     * Compute total loss using BCMath to avoid float drift.
     */
    public static function calculateTotalFinancialLoss(string $unitCost, int $totalQty): string
    {
        return bcmul($unitCost, (string) $totalQty, 4);
    }
}
```

### 3.12 Supplier

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['name', 'contact_person', 'phone', 'email', 'address', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
```

### 3.13 Customer

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['name', 'contact_person', 'phone', 'email', 'address', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }
}
```

### 3.14 PurchaseOrder

```php
namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'reference_code', 'supplier_id', 'warehouse_id', 'status',
        'update_cost_price', 'ordered_by', 'received_by',
        'ordered_at', 'received_at', 'cancelled_at', 'notes',
    ];

    protected $casts = [
        'status'            => PurchaseOrderStatus::class,
        'update_cost_price' => 'boolean',
        'ordered_at'        => 'datetime',
        'received_at'       => 'datetime',
        'cancelled_at'      => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function canBeCancelled(): bool
    {
        if (! in_array($this->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Ordered], true)) {
            return false;
        }

        return ! $this->items()->where('received_base_qty', '>', 0)->exists();
    }
}
```

### 3.15 PurchaseOrderItem

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id', 'product_variant_id', 'ordered_unit_name',
        'ordered_unit_ratio', 'ordered_qty', 'ordered_base_qty',
        'unit_cost_price', 'received_base_qty', 'notes',
    ];

    protected $casts = [
        'ordered_unit_ratio' => 'integer',
        'ordered_qty'        => 'integer',
        'ordered_base_qty'   => 'integer',
        'unit_cost_price'    => 'decimal:4',
        'received_base_qty'  => 'integer',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function outstandingBaseQty(): int
    {
        return max(0, (int) $this->ordered_base_qty - (int) $this->received_base_qty);
    }
}
```

### 3.16 SalesOrder

```php
namespace App\Models;

use App\Enums\SalesOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'reference_code', 'customer_id', 'warehouse_id', 'status',
        'ordered_by', 'dispatched_by',
        'ordered_at', 'confirmed_at', 'dispatched_at', 'cancelled_at', 'notes',
    ];

    protected $casts = [
        'status'        => SalesOrderStatus::class,
        'ordered_at'    => 'datetime',
        'confirmed_at'  => 'datetime',
        'dispatched_at' => 'datetime',
        'cancelled_at'  => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    /**
     * Pre-dispatch states only — mirrors the TransferRequisition /
     * PurchaseOrder model-level cancellation boundary (§3.7, §3.14).
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            SalesOrderStatus::Draft,
            SalesOrderStatus::Confirmed,
        ], true);
    }
}
```

### 3.17 SalesOrderItem

```php
namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_id', 'product_variant_id', 'unit_name', 'unit_ratio',
        'qty', 'base_qty', 'unit_sale_price_snapshot', 'dispatched_base_qty', 'notes',
    ];

    protected $casts = [
        'unit_ratio'                => 'integer',
        'qty'                       => 'integer',
        'base_qty'                  => 'integer',
        'unit_sale_price_snapshot'  => 'decimal:4',
        'dispatched_base_qty'       => 'integer',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function outstandingBaseQty(): int
    {
        return max(0, (int) $this->base_qty - (int) $this->dispatched_base_qty);
    }

    public function alreadyReturnedBaseQty(): int
    {
        return (int) StockMovement::query()
            ->where('type', StockMovementType::SaleReturn->value)
            ->where('reference_type', self::class)
            ->where('reference_id', (string) $this->id)
            ->sum('quantity');
    }
}
```

### 3.18 User

```php
namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'is_active'];

    protected $casts = [
        'role'      => UserRole::class,
        'is_active' => 'boolean',
        'password'  => 'hashed',
    ];

    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'user_warehouse');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isAuditor(): bool
    {
        return $this->role === UserRole::Auditor;
    }

    public function isWarehouseStaff(): bool
    {
        return $this->role === UserRole::WarehouseStaff;
    }
}
```

### 3.19 Observers

#### ProductObserver

```php
namespace App\Observers;

use App\Exceptions\ProductFamilyHasVariantsException;
use App\Models\Product;

class ProductObserver
{
    public function deleting(Product $product): void
    {
        if ($product->isForceDeleting()) {
            return;
        }
        if ($product->variants()->exists()) {
            throw new ProductFamilyHasVariantsException((int) $product->id);
        }
    }
}
```

#### ProductVariantObserver

```php
namespace App\Observers;

use App\Models\ProductVariant;
use App\Models\ProductVariantUnitConversion;

class ProductVariantObserver
{
    public function created(ProductVariant $variant): void
    {
        ProductVariantUnitConversion::firstOrCreate(
            [
                'product_variant_id' => $variant->id,
                'unit_name'          => $variant->base_unit_name,
            ],
            [
                'base_unit_ratio'      => 1,
                'is_default_purchase'  => false,
                'is_default_transfer'  => false,
            ],
        );
    }
}
```

Registration in `AppServiceProvider::boot()`:
```php
Product::observe(ProductObserver::class);
ProductVariant::observe(ProductVariantObserver::class);
```

### 3.20 DirectTransfer

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DirectTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_code',
        'from_warehouse_id',
        'to_warehouse_id',
        'notes',
        'transferred_by',
        'transferred_at',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
    ];

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DirectTransferItem::class);
    }
}
```

### 3.21 DirectTransferItem

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectTransferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'direct_transfer_id',
        'product_variant_id',
        'unit_name',
        'unit_ratio',
        'qty',
        'base_qty',
        'notes',
    ];

    protected $casts = [
        'unit_ratio' => 'integer',
        'qty'        => 'integer',
        'base_qty'   => 'integer',
    ];

    public function directTransfer(): BelongsTo
    {
        return $this->belongsTo(DirectTransfer::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
```

### 3.22 StockMovementIdempotencyKey

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovementIdempotencyKey extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'transfer_requisition_id',
        'payload_checksum',
        'resulting_item_states',
        'created_at',
    ];

    protected $casts = [
        'resulting_item_states' => 'array',
        'created_at' => 'datetime',
    ];

    public function transferRequisition(): BelongsTo
    {
        return $this->belongsTo(TransferRequisition::class);
    }
}
```

---

## 🏷️ Section 4: Enums

### 4.1 TransferRequisitionStatus

```php
namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum TransferRequisitionStatus: string implements HasLabel, HasColor
{
    case Draft                  = 'draft';
    case Requested              = 'requested';
    case UnderReviewFulfiller   = 'under_review_fulfiller';
    case UnderReviewRequestor   = 'under_review_requestor';
    case Confirmed              = 'confirmed';
    case Dispatched             = 'dispatched';
    case PartiallyReceived      = 'partially_received';
    case Completed              = 'completed';
    case ClosedWithLoss         = 'closed_with_loss';
    case Cancelled              = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft                => __('enums.transfer_requisition_status.draft'),
            self::Requested            => __('enums.transfer_requisition_status.requested'),
            self::UnderReviewFulfiller => __('enums.transfer_requisition_status.under_review_fulfiller'),
            self::UnderReviewRequestor => __('enums.transfer_requisition_status.under_review_requestor'),
            self::Confirmed            => __('enums.transfer_requisition_status.confirmed'),
            self::Dispatched           => __('enums.transfer_requisition_status.dispatched'),
            self::PartiallyReceived    => __('enums.transfer_requisition_status.partially_received'),
            self::Completed            => __('enums.transfer_requisition_status.completed'),
            self::ClosedWithLoss       => __('enums.transfer_requisition_status.closed_with_loss'),
            self::Cancelled            => __('enums.transfer_requisition_status.cancelled'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft                => 'gray',
            self::Requested            => 'warning',
            self::UnderReviewFulfiller,
            self::UnderReviewRequestor => 'warning',
            self::Confirmed            => 'primary',
            self::Dispatched           => 'info',
            self::PartiallyReceived    => 'warning',
            self::Completed            => 'success',
            self::ClosedWithLoss       => 'danger',
            self::Cancelled            => 'danger',
        };
    }
}
```

### 4.2 PurchaseOrderStatus

```php
namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum PurchaseOrderStatus: string implements HasLabel, HasColor
{
    case Draft              = 'draft';
    case Ordered            = 'ordered';
    case PartiallyReceived  = 'partially_received';
    case Received           = 'received';
    case Cancelled          = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft             => __('enums.purchase_order_status.draft'),
            self::Ordered           => __('enums.purchase_order_status.ordered'),
            self::PartiallyReceived => __('enums.purchase_order_status.partially_received'),
            self::Received          => __('enums.purchase_order_status.received'),
            self::Cancelled         => __('enums.purchase_order_status.cancelled'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft             => 'gray',
            self::Ordered           => 'primary',
            self::PartiallyReceived => 'warning',
            self::Received          => 'success',
            self::Cancelled         => 'danger',
        };
    }
}
```

### 4.3 SalesOrderStatus

```php
namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum SalesOrderStatus: string implements HasLabel, HasColor
{
    case Draft                = 'draft';
    case Confirmed            = 'confirmed';
    case PartiallyDispatched  = 'partially_dispatched';
    case Dispatched           = 'dispatched';
    case Cancelled            = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft               => __('enums.sales_order_status.draft'),
            self::Confirmed           => __('enums.sales_order_status.confirmed'),
            self::PartiallyDispatched => __('enums.sales_order_status.partially_dispatched'),
            self::Dispatched          => __('enums.sales_order_status.dispatched'),
            self::Cancelled           => __('enums.sales_order_status.cancelled'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft               => 'gray',
            self::Confirmed           => 'primary',
            self::PartiallyDispatched => 'warning',
            self::Dispatched          => 'success',
            self::Cancelled           => 'danger',
        };
    }
}
```

### 4.4 StockMovementType

```php
namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StockMovementType: string implements HasLabel
{
    case Adjustment        = 'adjustment';
    case TransferOut       = 'transfer_out';
    case TransferIn        = 'transfer_in';
    // Reserved in v1 — no StockMovement writer by design: transfer
    // shortfalls/damage are recorded via LossLedger only
    // (`InventoryService::writeOffOmittedItem()` / `recordLoss()`, §6),
    // never as `Loss`/`Damage` movements.
    case Loss              = 'loss';
    case Damage            = 'damage';
    case Purchase          = 'purchase';
    case Sale              = 'sale';
    case SaleReturn        = 'sale_return';
    // Reserved in v1 — no writer by design: the full supplier-return
    // workflow (UI + lifecycle) is deferred per §13 item 4, and
    // `InventoryService::recordMovement()` rejects this type (§6) so it
    // can only be written once that workflow is specified.
    case PurchaseReturn    = 'purchase_return';

    public function getLabel(): string
    {
        return match ($this) {
            self::Adjustment     => __('enums.stock_movement_type.adjustment'),
            self::TransferOut    => __('enums.stock_movement_type.transfer_out'),
            self::TransferIn     => __('enums.stock_movement_type.transfer_in'),
            self::Loss           => __('enums.stock_movement_type.loss'),
            self::Damage         => __('enums.stock_movement_type.damage'),
            self::Purchase       => __('enums.stock_movement_type.purchase'),
            self::Sale           => __('enums.stock_movement_type.sale'),
            self::SaleReturn     => __('enums.stock_movement_type.sale_return'),
            self::PurchaseReturn => __('enums.stock_movement_type.purchase_return'),
        };
    }

    public function isPositive(): bool
    {
        return in_array($this, [self::TransferIn, self::Purchase, self::SaleReturn], true);
    }
}
```

### 4.5 RevisionStatus

```php
namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RevisionStatus: string implements HasLabel
{
    case Pending  = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending  => __('enums.revision_status.pending'),
            self::Accepted => __('enums.revision_status.accepted'),
            self::Rejected => __('enums.revision_status.rejected'),
        };
    }
}
```

### 4.6 NegotiationSide

```php
namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NegotiationSide: string implements HasLabel
{
    case Fulfiller = 'fulfiller';
    case Requestor = 'requestor';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fulfiller => __('enums.negotiation_side.fulfiller'),
            self::Requestor => __('enums.negotiation_side.requestor'),
        };
    }
}
```

### 4.7 InTransitStatus

```php
namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InTransitStatus: string implements HasLabel
{
    case InTransit = 'in_transit';
    case Cleared   = 'cleared';
    case Lost      = 'lost';

    public function getLabel(): string
    {
        return match ($this) {
            self::InTransit => __('enums.in_transit_status.in_transit'),
            self::Cleared   => __('enums.in_transit_status.cleared'),
            self::Lost      => __('enums.in_transit_status.lost'),
        };
    }
}
```

### 4.8 UserRole

```php
namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Admin          = 'admin';
    case Auditor        = 'auditor';
    case WarehouseStaff = 'warehouse_staff';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin          => __('enums.user_role.admin'),
            self::Auditor        => __('enums.user_role.auditor'),
            self::WarehouseStaff => __('enums.user_role.warehouse_staff'),
        };
    }
}
```

### 4.9 LossCategory

```php
namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LossCategory: string implements HasLabel
{
    case Shortfall = 'shortfall';
    case Damage    = 'damage';
    case Spoilage  = 'spoilage';
    case Theft     = 'theft';
    case Other     = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Shortfall => __('enums.loss_category.shortfall'),
            self::Damage    => __('enums.loss_category.damage'),
            self::Spoilage  => __('enums.loss_category.spoilage'),
            self::Theft     => __('enums.loss_category.theft'),
            self::Other     => __('enums.loss_category.other'),
        };
    }
}
```

---

## 🏭 Section 5: Model Factories

### 5.1 ProductFactory

```php
namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name'     => $this->faker->words(3, true),
            'category' => $this->faker->randomElement(['Electronics', 'Hardware', 'Consumables']),
        ];
    }
}
```

### 5.2 ProductVariantFactory

```php
namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id'     => Product::factory(),
            'sku'            => strtoupper($this->faker->unique()->bothify('SKU-####-??')),
            'barcode'        => $this->faker->unique()->ean13(),
            'name'           => $this->faker->words(2, true),
            'base_unit_name' => 'pc',
            'reorder_point'  => $this->faker->numberBetween(0, 50),
            'is_active'      => true,
        ];
    }
}
```

### 5.3 ProductVariantPriceFactory

```php
namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantPriceFactory extends Factory
{
    protected $model = ProductVariantPrice::class;

    public function definition(): array
    {
        $cost = $this->faker->randomFloat(4, 1, 500);

        return [
            'product_variant_id' => ProductVariant::factory(),
            'cost_price'         => $cost,
            'sale_price'         => $cost * 1.4,
            'effective_from'     => now(),
            'is_current'         => true,
        ];
    }
}
```

### 5.4 ProductVariantUnitConversionFactory

```php
namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\ProductVariantUnitConversion;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantUnitConversionFactory extends Factory
{
    protected $model = ProductVariantUnitConversion::class;

    public function definition(): array
    {
        return [
            'product_variant_id'  => ProductVariant::factory(),
            'unit_name'           => $this->faker->randomElement(['box', 'case', 'pallet']),
            'base_unit_ratio'     => $this->faker->randomElement([6, 12, 24, 48]),
            'is_default_purchase' => false,
            'is_default_transfer' => false,
        ];
    }

    public function baseUnit(): static
    {
        return $this->state(fn () => [
            'unit_name'       => 'pc',
            'base_unit_ratio' => 1,
        ]);
    }
}
```

### 5.5 WarehouseFactory

```php
namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'code'      => strtoupper($this->faker->unique()->bothify('WH-####')),
            'name'      => $this->faker->city() . ' Warehouse',
            'location'  => $this->faker->address(),
            'is_active' => true,
        ];
    }
}
```

### 5.6 UserFactory

```php
namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name'      => $this->faker->name(),
            'email'     => $this->faker->unique()->safeEmail(),
            'password'  => Hash::make('password'),
            'role'      => UserRole::WarehouseStaff,
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin]);
    }

    public function auditor(): static
    {
        return $this->state(fn () => ['role' => UserRole::Auditor]);
    }
}
```

### 5.7 SupplierFactory

```php
namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'name'           => $this->faker->company(),
            'contact_person' => $this->faker->name(),
            'phone'          => $this->faker->phoneNumber(),
            'email'          => $this->faker->companyEmail(),
            'address'        => $this->faker->address(),
            'is_active'      => true,
        ];
    }
}
```

### 5.8 CustomerFactory

```php
namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name'           => $this->faker->company(),
            'contact_person' => $this->faker->name(),
            'phone'          => $this->faker->phoneNumber(),
            'email'          => $this->faker->companyEmail(),
            'address'        => $this->faker->address(),
            'is_active'      => true,
        ];
    }
}
```

### 5.9 TransferRequisitionFactory

```php
namespace Database\Factories;

use App\Enums\TransferRequisitionStatus;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\GeneratesReferenceCodes;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransferRequisitionFactory extends Factory
{
    protected $model = TransferRequisition::class;

    public function definition(): array
    {
        return [
            'reference_code'    => GeneratesReferenceCodes::generateReferenceCode('TR', $this->faker->unique()->numberBetween(100, 999)),
            'from_warehouse_id' => Warehouse::factory(),
            'to_warehouse_id'   => Warehouse::factory(),
            'status'            => TransferRequisitionStatus::Draft,
            'requested_by'      => User::factory(),
        ];
    }

    public function requested(): static
    {
        return $this->state(fn () => [
            'status'       => TransferRequisitionStatus::Requested,
            'requested_at' => now(),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status'      => TransferRequisitionStatus::Confirmed,
            'approved_at' => now(),
            'approved_by' => User::factory(),
        ]);
    }

    public function dispatched(): static
    {
        return $this->state(fn () => [
            'status'        => TransferRequisitionStatus::Dispatched,
            'dispatched_at' => now(),
            'dispatched_by' => User::factory(),
        ]);
    }
}
```

### 5.10 TransferRequisitionItemFactory

```php
namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransferRequisitionItemFactory extends Factory
{
    protected $model = TransferRequisitionItem::class;

    public function definition(): array
    {
        $qty = $this->faker->numberBetween(1, 20);

        return [
            'transfer_requisition_id' => TransferRequisition::factory(),
            'product_variant_id'      => ProductVariant::factory(),
            'requested_unit_name'     => 'pc',
            'requested_unit_ratio'    => 1,
            'requested_qty'           => $qty,
            'requested_base_qty'      => $qty,
        ];
    }
}
```

### 5.11 InTransitFactory

```php
namespace Database\Factories;

use App\Enums\InTransitStatus;
use App\Models\InTransit;
use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class InTransitFactory extends Factory
{
    protected $model = InTransit::class;

    public function definition(): array
    {
        return [
            'transfer_requisition_id'      => TransferRequisition::factory(),
            'transfer_requisition_item_id' => TransferRequisitionItem::factory(),
            'product_variant_id'           => ProductVariant::factory(),
            'dispatched_base_qty'          => $this->faker->numberBetween(1, 50),
            'dispatched_at'                => now(),
            'status'                       => InTransitStatus::InTransit,
        ];
    }
}
```

### 5.12 LossLedgerFactory

```php
namespace Database\Factories;

use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\TransferRequisition;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class LossLedgerFactory extends Factory
{
    protected $model = LossLedger::class;

    public function definition(): array
    {
        $lost = $this->faker->numberBetween(0, 10);
        $damaged = $this->faker->numberBetween(0, 5);
        $unitCost = $this->faker->randomFloat(4, 1, 100);

        return [
            'transfer_requisition_id' => TransferRequisition::factory(),
            'product_variant_id'      => ProductVariant::factory(),
            'warehouse_id'            => Warehouse::factory(),
            'lost_base_qty'           => $lost,
            'damaged_base_qty'        => $damaged,
            'unit_cost_price'         => $unitCost,
            'total_financial_loss'    => bcmul((string) $unitCost, (string) ($lost + $damaged), 4),
            'loss_category'           => $this->faker->randomElement(['shortfall', 'damage', 'spoilage', 'theft', 'other']),
            'recorded_at'             => now(),
        ];
    }
}
```

### 5.13 PurchaseOrderFactory

```php
namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\GeneratesReferenceCodes;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'reference_code' => GeneratesReferenceCodes::generateReferenceCode('PO', $this->faker->unique()->numberBetween(100, 999)),
            'supplier_id'    => Supplier::factory(),
            'warehouse_id'   => Warehouse::factory(),
            'status'         => PurchaseOrderStatus::Draft,
            'ordered_by'     => User::factory(),
        ];
    }

    public function ordered(): static
    {
        return $this->state(fn () => [
            'status'     => PurchaseOrderStatus::Ordered,
            'ordered_at' => now(),
        ]);
    }
}
```

### 5.14 PurchaseOrderItemFactory

```php
namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;

    public function definition(): array
    {
        $qty = $this->faker->numberBetween(1, 20);

        return [
            'purchase_order_id'  => PurchaseOrder::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'ordered_unit_name'  => 'pc',
            'ordered_unit_ratio' => 1,
            'ordered_qty'        => $qty,
            'ordered_base_qty'   => $qty,
            'unit_cost_price'    => $this->faker->randomFloat(4, 1, 500),
        ];
    }
}
```

### 5.15 SalesOrderFactory

```php
namespace Database\Factories;

use App\Enums\SalesOrderStatus;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\GeneratesReferenceCodes;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesOrderFactory extends Factory
{
    protected $model = SalesOrder::class;

    public function definition(): array
    {
        return [
            'reference_code' => GeneratesReferenceCodes::generateReferenceCode('SO', $this->faker->unique()->numberBetween(100, 999)),
            'customer_id'    => Customer::factory(),
            'warehouse_id'   => Warehouse::factory(),
            'status'         => SalesOrderStatus::Draft,
            'ordered_by'     => User::factory(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status'       => SalesOrderStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }
}
```

### 5.16 SalesOrderItemFactory

```php
namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesOrderItemFactory extends Factory
{
    protected $model = SalesOrderItem::class;

    public function definition(): array
    {
        $qty = $this->faker->numberBetween(1, 20);

        return [
            'sales_order_id'            => SalesOrder::factory(),
            'product_variant_id'        => ProductVariant::factory(),
            'unit_name'                 => 'pc',
            'unit_ratio'                => 1,
            'qty'                       => $qty,
            'base_qty'                  => $qty,
            'unit_sale_price_snapshot'  => $this->faker->randomFloat(4, 1, 500),
        ];
    }
}
```

### 5.17 DirectTransferFactory

```php
namespace Database\Factories;

use App\Models\DirectTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\GeneratesReferenceCodes;
use Illuminate\Database\Eloquent\Factories\Factory;

class DirectTransferFactory extends Factory
{
    protected $model = DirectTransfer::class;

    public function definition(): array
    {
        return [
            'reference_code'    => GeneratesReferenceCodes::generateReferenceCode('DT', $this->faker->unique()->numberBetween(100, 999)),
            'from_warehouse_id' => Warehouse::factory(),
            'to_warehouse_id'   => Warehouse::factory(),
            'transferred_by'    => User::factory(),
            'transferred_at'    => now(),
            'notes'             => $this->faker->sentence(),
        ];
    }
}
```

### 5.18 DirectTransferItemFactory

```php
namespace Database\Factories;

use App\Models\DirectTransfer;
use App\Models\DirectTransferItem;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class DirectTransferItemFactory extends Factory
{
    protected $model = DirectTransferItem::class;

    public function definition(): array
    {
        $qty = $this->faker->numberBetween(1, 20);

        return [
            'direct_transfer_id' => DirectTransfer::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'unit_name'          => 'pc',
            'unit_ratio'         => 1,
            'qty'                => $qty,
            'base_qty'           => $qty,
        ];
    }
}
```

### 5.19 StockMovementFactory

```php
namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'warehouse_id'       => Warehouse::factory(),
            'type'               => StockMovementType::Adjustment,
            'quantity'           => $this->faker->numberBetween(1, 50),
            'unit_name_used'     => 'pc',
            'unit_ratio_used'    => 1,
            'created_by'         => User::factory(),
        ];
    }
}
```

### 5.20 TransferRequisitionItemRevisionFactory

```php
namespace Database\Factories;

use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use App\Models\ProductVariant;
use App\Models\TransferRequisitionItem;
use App\Models\TransferRequisitionItemRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransferRequisitionItemRevisionFactory extends Factory
{
    protected $model = TransferRequisitionItemRevision::class;

    public function definition(): array
    {
        $qty = $this->faker->numberBetween(1, 20);

        return [
            'transfer_requisition_item_id' => TransferRequisitionItem::factory(),
            'user_id'                      => User::factory(),
            'product_variant_id'           => ProductVariant::factory(),
            'proposed_unit_name'           => 'pc',
            'proposed_unit_ratio'          => 1,
            'proposed_qty'                 => $qty,
            'proposed_base_qty'            => $qty,
            'side'                         => NegotiationSide::Fulfiller,
            'status'                       => RevisionStatus::Pending,
        ];
    }
}
```

### 5.21 StockMovementIdempotencyKeyFactory

```php
namespace Database\Factories;

use App\Models\StockMovementIdempotencyKey;
use App\Models\TransferRequisition;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockMovementIdempotencyKeyFactory extends Factory
{
    protected $model = StockMovementIdempotencyKey::class;

    public function definition(): array
    {
        return [
            'transfer_requisition_id' => TransferRequisition::factory(),
            'payload_checksum'        => hash('sha256', $this->faker->unique()->uuid()),
            'resulting_item_states'   => [],
            'created_at'              => now(),
        ];
    }
}
```

---

## ⚙️ Section 6: Transactional Service Layer

### 6.1 GuardsOutstandingQuantity

```php
namespace App\Services;

use App\Exceptions\OutstandingQuantityExceededException;
use App\Models\PurchaseOrderItem;
use App\Models\SalesOrderItem;
use App\Models\TransferRequisitionItem;

class GuardsOutstandingQuantity
{
    public function assertTransferNotOverShipped(TransferRequisitionItem $item, int $newShipped): void
    {
        $outstanding = $item->outstandingShippedBaseQty();
        if ($newShipped > $outstanding) {
            throw new OutstandingQuantityExceededException(
                itemType: $item::class,
                itemId: (int) $item->id,
                attempted: $newShipped,
                outstanding: $outstanding,
            );
        }
    }

    public function assertPurchaseNotOverReceived(PurchaseOrderItem $item, int $newReceived): void
    {
        $outstanding = $item->outstandingBaseQty();
        if ($newReceived > $outstanding) {
            throw new OutstandingQuantityExceededException(
                itemType: $item::class,
                itemId: (int) $item->id,
                attempted: $newReceived,
                outstanding: $outstanding,
            );
        }
    }

    public function assertSaleNotOverDispatched(SalesOrderItem $item, int $newDispatch): void
    {
        $outstanding = $item->outstandingBaseQty();
        if ($newDispatch > $outstanding) {
            throw new OutstandingQuantityExceededException(
                itemType: $item::class,
                itemId: (int) $item->id,
                attempted: $newDispatch,
                outstanding: $outstanding,
            );
        }
    }
}
```

### 6.2 InventoryService

```php
namespace App\Services;

use App\Enums\InTransitStatus;
use App\Enums\StockMovementType;
use App\Exceptions\DomainRuleViolationException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidDocumentStateException;
use App\Exceptions\OutstandingQuantityExceededException;
use App\Models\DirectTransfer;
use App\Models\InTransit;
use App\Models\LossLedger;
use App\Models\ProductVariant;
use App\Models\ProductVariantUnitConversion;
use App\Models\StockMovement;
use App\Models\StockMovementIdempotencyKey;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Record a signed stock movement inside a lock.
     *
     * Restricted: purchase/sale/sale_return/purchase_return movements must
     * go through PurchaseService / SalesService so their guards apply, and
     * adjustment movements must go through `adjustment()` so the
     * caller-supplied sign is preserved — `Adjustment` is intentionally not
     * positive per `StockMovementType::isPositive()`, so routing it here
     * would silently force every adjustment negative.
     */
    public function recordMovement(
        int $productVariantId,
        int $warehouseId,
        StockMovementType $type,
        int $baseQuantity,
        string $unitName,
        int $unitRatio,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $referenceCode = null,
        ?string $notes = null,
    ): StockMovement {
        if (in_array($type, [
            StockMovementType::Purchase,
            StockMovementType::Sale,
            StockMovementType::SaleReturn,
            StockMovementType::PurchaseReturn,
            StockMovementType::Adjustment,
        ], true)) {
            throw new DomainRuleViolationException('errors.invalid_movement_type', [
                'type' => $type->value,
            ]);
        }

        if ($unitRatio < 1) {
            throw new DomainRuleViolationException('errors.invalid_unit_ratio', [
                'ratio' => $unitRatio,
            ]);
        }

        return DB::transaction(function () use (
            $productVariantId, $warehouseId, $type, $baseQuantity,
            $unitName, $unitRatio, $referenceType, $referenceId, $referenceCode, $notes
        ) {
            ProductVariant::lockForUpdate()->findOrFail($productVariantId);
            Warehouse::lockForUpdate()->findOrFail($warehouseId);

            return StockMovement::create([
                'product_variant_id' => $productVariantId,
                'warehouse_id'       => $warehouseId,
                'type'               => $type,
                'quantity'           => $type->isPositive() ? abs($baseQuantity) : -abs($baseQuantity),
                'unit_name_used'     => $unitName,
                'unit_ratio_used'    => $unitRatio,
                'reference_type'     => $referenceType,
                'reference_id'       => $referenceId,
                'reference_code'     => $referenceCode,
                'notes'              => $notes,
                'created_by'         => auth()->id(),
            ]);
        });
    }

    /**
     * Move N distinct variants between two warehouses atomically.
     *
     * Creates one DirectTransfer header, one DirectTransferItem per line, and a
     * paired (TransferOut, TransferIn) StockMovement per line inside a single
     * transaction. Fire-and-forget: no draft/dispatch/receive lifecycle.
     *
     * @param  array<int, array{
     *     product_variant_id: int,
     *     unit_name:          string,
     *     unit_ratio:         int,
     *     qty:                int,
     *     notes?:             ?string,
     * }>  $items
     */
    public function directTransfer(
        int $fromWarehouseId,
        int $toWarehouseId,
        array $items,
        string $referenceCode,
        ?string $notes = null,
    ): DirectTransfer {
        if ($fromWarehouseId === $toWarehouseId) {
            throw new DomainRuleViolationException('errors.same_warehouse_transfer', [
                'warehouse' => $fromWarehouseId,
            ]);
        }

        if (empty($items)) {
            throw new DomainRuleViolationException('errors.empty_transfer_items');
        }

        foreach ($items as $index => $item) {
            foreach (['product_variant_id', 'unit_name', 'unit_ratio', 'qty'] as $key) {
                if (! array_key_exists($key, $item)) {
                    throw new DomainRuleViolationException('errors.missing_item_field', [
                        'index' => $index,
                        'field' => $key,
                    ]);
                }
            }
            if ((int) $item['unit_ratio'] < 1) {
                throw new DomainRuleViolationException('errors.invalid_unit_ratio', [
                    'index' => $index,
                    'ratio' => (int) $item['unit_ratio'],
                ]);
            }
            if ((int) $item['qty'] < 1) {
                throw new DomainRuleViolationException('errors.invalid_item_quantity', [
                    'index' => $index,
                    'qty' => (int) $item['qty'],
                ]);
            }
        }

        // A11 — a transfer contains one or more distinct variants: reject
        // duplicate `product_variant_id` entries (mirrors
        // `disableOptionsWhenSelectedInSiblingRepeaterItems()` in §7C.1).
        $seenVariantIds = [];
        foreach ($items as $index => $item) {
            $variantId = (int) $item['product_variant_id'];
            if (in_array($variantId, $seenVariantIds, true)) {
                throw new DomainRuleViolationException('errors.duplicate_transfer_variant', [
                    'index' => $index,
                    'variant' => $variantId,
                ]);
            }
            $seenVariantIds[] = $variantId;
        }

        return DB::transaction(function () use (
            $fromWarehouseId, $toWarehouseId, $items, $referenceCode, $notes
        ) {
            // §20.3 — service independently verifies both warehouse endpoints
            // are within the actor's operational scope. Admins hold global
            // scope with a possibly empty `user_warehouse` pivot (same
            // privilege boundary as the form selects in §7C.1 and the list
            // query in §7C.6), so the membership check applies to non-admin
            // actors. Auditors are read-only (`DirectTransferPolicy::create()`
            // denies them) and are denied here even when assigned to the
            // warehouses.
            $actor = auth()->user();
            if (! $actor) {
                throw new DomainRuleViolationException('errors.warehouse_out_of_scope', [
                    'from' => $fromWarehouseId,
                    'to' => $toWarehouseId,
                ]);
            }
            if (! $actor->isAdmin()) {
                $allowed = $actor->warehouses()->pluck('id')->all();
                if ($actor->isAuditor()
                    || ! in_array($fromWarehouseId, $allowed, true)
                    || ! in_array($toWarehouseId, $allowed, true)) {
                    throw new DomainRuleViolationException('errors.warehouse_out_of_scope', [
                        'from' => $fromWarehouseId,
                        'to' => $toWarehouseId,
                    ]);
                }
            }

            // Lock warehouses in sorted-ID order to avoid deadlocks.
            $warehouseIds = collect([$fromWarehouseId, $toWarehouseId])
                ->sort()->values()->all();
            Warehouse::whereIn('id', $warehouseIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            // Lock every referenced variant in sorted-ID order.
            $variantIds = collect($items)
                ->pluck('product_variant_id')
                ->unique()
                ->sort()
                ->values()
                ->all();
            $lockedVariants = ProductVariant::whereIn('id', $variantIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($variantIds as $vid) {
                if (! $lockedVariants->has($vid)) {
                    throw new DomainRuleViolationException('errors.unknown_variant', [
                        'variant' => $vid,
                    ]);
                }
            }

            // Unit verification — the caller-supplied `unit_name` /
            // `unit_ratio` pair must match the variant's own conversion row
            // (same rule as `NegotiationService::submitRevision()`); the
            // ratio is resolved server-side, never trusted from the caller.
            foreach ($items as $index => $item) {
                $storedRatio = ProductVariantUnitConversion::where(
                    'product_variant_id',
                    (int) $item['product_variant_id']
                )
                    ->where('unit_name', (string) $item['unit_name'])
                    ->value('base_unit_ratio');

                if ($storedRatio === null) {
                    throw new DomainRuleViolationException('errors.undefined_unit', [
                        'index' => $index,
                        'unit' => (string) $item['unit_name'],
                        'variant' => (int) $item['product_variant_id'],
                    ]);
                }

                if ((int) $storedRatio !== (int) $item['unit_ratio']) {
                    throw new DomainRuleViolationException('errors.unit_ratio_mismatch', [
                        'index' => $index,
                        'unit' => (string) $item['unit_name'],
                    ]);
                }
            }

            // Availability gate — a direct transfer must never drive the
            // source warehouse negative. Required base qty per variant is
            // summed across lines, then checked against batched availability
            // (on-hand minus transfer and sales reservations) at the source.
            $requiredByVariant = [];
            foreach ($items as $item) {
                $vid = (int) $item['product_variant_id'];
                $requiredByVariant[$vid] = ($requiredByVariant[$vid] ?? 0)
                    + ((int) $item['qty'] * (int) $item['unit_ratio']);
            }

            $availableByVariant = ProductVariant::batchAvailableQuantity(
                array_keys($requiredByVariant),
                $fromWarehouseId,
            );

            foreach ($requiredByVariant as $vid => $required) {
                if (($availableByVariant[$vid] ?? 0) < $required) {
                    throw new InsufficientStockException(
                        variantId: $vid,
                        warehouseId: $fromWarehouseId,
                        requested: $required,
                        available: $availableByVariant[$vid] ?? 0,
                    );
                }
            }

            $header = DirectTransfer::create([
                'reference_code'    => $referenceCode,
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id'   => $toWarehouseId,
                'notes'             => $notes,
                'transferred_by'    => $actor?->id,
                'transferred_at'    => now(),
            ]);

            foreach ($items as $item) {
                $unitRatio = (int) $item['unit_ratio'];
                $qty       = (int) $item['qty'];
                $baseQty   = $qty * $unitRatio;

                $line = $header->items()->create([
                    'product_variant_id' => (int) $item['product_variant_id'],
                    'unit_name'          => (string) $item['unit_name'],
                    'unit_ratio'         => $unitRatio,
                    'qty'                => $qty,
                    'base_qty'           => $baseQty,
                    'notes'              => $item['notes'] ?? null,
                ]);

                $out = StockMovement::create([
                    'product_variant_id' => $line->product_variant_id,
                    'warehouse_id'       => $fromWarehouseId,
                    'type'               => StockMovementType::TransferOut,
                    'quantity'           => -abs($baseQty),
                    'unit_name_used'     => $line->unit_name,
                    'unit_ratio_used'    => $line->unit_ratio,
                    'reference_type'     => DirectTransfer::class,
                    'reference_id'       => (string) $header->id,
                    'reference_code'     => $referenceCode,
                    'notes'              => $notes,
                    'created_by'         => $actor?->id,
                ]);

                StockMovement::create([
                    'product_variant_id'  => $line->product_variant_id,
                    'warehouse_id'        => $toWarehouseId,
                    'type'                => StockMovementType::TransferIn,
                    'quantity'            => abs($baseQty),
                    'unit_name_used'      => $line->unit_name,
                    'unit_ratio_used'     => $line->unit_ratio,
                    'related_movement_id' => $out->id,
                    'reference_type'      => DirectTransfer::class,
                    'reference_id'        => (string) $header->id,
                    'reference_code'      => $referenceCode,
                    'notes'               => $notes,
                    'created_by'          => $actor?->id,
                ]);
            }

            return $header->fresh([
                'items.productVariant',
                'fromWarehouse',
                'toWarehouse',
                'transferredBy',
            ]);
        });
    }

    /**
     * Dispatch a confirmed requisition — materializes in_transits.
     *
     * Re-locks items and variants under the parent transaction and verifies
     * on-hand availability, excluding this requisition's own reservation.
     */
    public function dispatchTransfer(TransferRequisition $requisition): void
    {
        DB::transaction(function () use ($requisition) {
            $fresh = TransferRequisition::lockForUpdate()->findOrFail($requisition->id);

            if ($fresh->status !== \App\Enums\TransferRequisitionStatus::Confirmed) {
                throw new InvalidDocumentStateException(
                    documentType: TransferRequisition::class,
                    documentId: (int) $fresh->id,
                    actualStatus: $fresh->status->value,
                    action: 'dispatch',
                );
            }

            $items = TransferRequisitionItem::where('transfer_requisition_id', $fresh->id)
                ->lockForUpdate()
                ->get();

            $variantIds = $items->pluck('product_variant_id')
                ->merge($items->pluck('substitute_product_variant_id'))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (! empty($variantIds)) {
                ProductVariant::whereIn('id', $variantIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
            }

            Warehouse::lockForUpdate()->findOrFail($fresh->from_warehouse_id);

            $availableByVariant = ProductVariant::batchAvailableQuantity(
                $variantIds,
                $fresh->from_warehouse_id,
                null,
                $fresh->id, // exclude own transfer reservation
            );

            $reorderPoints = ProductVariant::whereIn('id', $variantIds)->pluck('reorder_point', 'id');

            foreach ($items as $item) {
                if ($item->approved_base_qty === null) {
                    throw new DomainRuleViolationException('errors.missing_approved_quantity', [
                        'item' => (int) $item->id,
                    ]);
                }

                // Never ship more than the outstanding shipped balance
                // (approved − already shipped) for this line: the guard
                // receives this dispatch's quantity (`approved_base_qty`),
                // bounded by `outstandingShippedBaseQty()` per §6.1.
                app(GuardsOutstandingQuantity::class)->assertTransferNotOverShipped(
                    $item,
                    (int) $item->approved_base_qty,
                );

                $variantId = $item->actualVariantId();
                $available = $availableByVariant[$variantId] ?? 0;

                if ($item->approved_base_qty > $available) {
                    throw new InsufficientStockException(
                        variantId: $variantId,
                        warehouseId: $fresh->from_warehouse_id,
                        requested: (int) $item->approved_base_qty,
                        available: $available,
                    );
                }

                InTransit::create([
                    'transfer_requisition_id'      => $fresh->id,
                    'transfer_requisition_item_id' => $item->id,
                    'product_variant_id'           => $variantId,
                    'dispatched_base_qty'          => $item->approved_base_qty,
                    'dispatched_at'                => now(),
                    'status'                       => InTransitStatus::InTransit,
                ]);

                StockMovement::create([
                    'product_variant_id' => $variantId,
                    'warehouse_id'       => $fresh->from_warehouse_id,
                    'type'               => StockMovementType::TransferOut,
                    'quantity'           => -abs($item->approved_base_qty),
                    'unit_name_used'     => $item->approved_unit_name,
                    'unit_ratio_used'    => $item->approved_unit_ratio,
                    'reference_type'     => TransferRequisition::class,
                    'reference_id'       => (string) $fresh->id,
                    'reference_code'     => $fresh->reference_code,
                    'created_by'         => auth()->id(),
                ]);

                $item->update(['shipped_base_qty' => (int) $item->shipped_base_qty + (int) $item->approved_base_qty]);

                $remaining = $available - $item->approved_base_qty;
                $availableByVariant[$variantId] = $remaining;

                $reorderPoint = $reorderPoints[$variantId] ?? 0;
                if ($available >= $reorderPoint && $remaining < $reorderPoint) {
                    event(new \App\Events\InventoryBelowReorderPoint($variantId, $fresh->from_warehouse_id));
                }
            }

            $fresh->update([
                'status'        => \App\Enums\TransferRequisitionStatus::Dispatched,
                'dispatched_at' => now(),
                'dispatched_by' => auth()->id(),
            ]);

            event(new \App\Events\TransferDispatched($fresh->id));
        });
    }

    /**
     * Scan-to-receive with idempotency via state-equality check.
     *
     * @param  array<int, array{received_good:int, received_damaged:int}>  $scanPayload
     */
    public function scanToReceive(TransferRequisition $requisition, array $scanPayload): void
    {
        DB::transaction(function () use ($requisition, $scanPayload) {
            $fresh = TransferRequisition::lockForUpdate()->findOrFail($requisition->id);

            // State guard — intake is only valid once stock has left the
            // source warehouse. Mirrors the `StnController::scan()` (§21.3)
            // boundary so direct service calls cannot receive a
            // Draft/Confirmed requisition.
            if (! in_array($fresh->status, [
                \App\Enums\TransferRequisitionStatus::Dispatched,
                \App\Enums\TransferRequisitionStatus::PartiallyReceived,
            ], true)) {
                throw new InvalidDocumentStateException(
                    documentType: TransferRequisition::class,
                    documentId: (int) $fresh->id,
                    actualStatus: $fresh->status->value,
                    action: 'receive',
                );
            }

            $items = TransferRequisitionItem::where('transfer_requisition_id', $fresh->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $knownIds = $items->pluck('id')->all();

            // §19.6 — the over-outstanding check must occur before canonical
            // sorting, JSON encoding, and hashing. Validate per-item received
            // quantities against outstanding approved qty before computing
            // the checksum/idempotency key.
            foreach ($items as $item) {
                $payload = $scanPayload[$item->id] ?? [
                    'received_good' => 0,
                    'received_damaged' => 0,
                ];
                $good = (int) ($payload['received_good'] ?? 0);
                $damaged = (int) ($payload['received_damaged'] ?? 0);

                if (! is_numeric($payload['received_good']) || ! is_numeric($payload['received_damaged'])
                    || (int) $payload['received_good'] != $payload['received_good']
                    || (int) $payload['received_damaged'] != $payload['received_damaged']) {
                    throw new DomainRuleViolationException('errors.non_integer_payload', [
                        'item' => (int) $item->id,
                    ]);
                }

                if ($good < 0 || $damaged < 0) {
                    throw new DomainRuleViolationException('errors.negative_payload', [
                        'item' => (int) $item->id,
                    ]);
                }

                $outstanding = max(0, (int) $item->approved_base_qty - (int) $item->received_good_base_qty - (int) $item->received_damaged_base_qty);

                if ($good + $damaged > $outstanding) {
                    throw new OutstandingQuantityExceededException(
                        itemType: TransferRequisitionItem::class,
                        itemId: (int) $item->id,
                        attempted: $good + $damaged,
                        outstanding: $outstanding,
                    );
                }
            }

            $normalized = [];
            foreach ($scanPayload as $itemId => $quantities) {
                $good = $quantities['received_good'] ?? 0;
                $damaged = $quantities['received_damaged'] ?? 0;

                if (! is_numeric($good) || ! is_numeric($damaged)
                    || (int) $good != $good || (int) $damaged != $damaged) {
                    throw new DomainRuleViolationException('errors.non_integer_payload', [
                        'item' => (int) $itemId,
                    ]);
                }

                $good = (int) $good;
                $damaged = (int) $damaged;

                if ($good < 0 || $damaged < 0) {
                    throw new DomainRuleViolationException('errors.negative_payload', [
                        'item' => (int) $itemId,
                    ]);
                }

                $normalized[(int) $itemId] = [
                    'received_damaged' => $damaged,
                    'received_good' => $good,
                ];
            }

            ksort($normalized, SORT_NUMERIC);
            $checksum = hash('sha256', json_encode($normalized));

            $alreadyProcessed = StockMovementIdempotencyKey::where('transfer_requisition_id', $fresh->id)
                ->where('payload_checksum', $checksum)
                ->exists();

            if ($alreadyProcessed) {
                return;
            }

            // First-scan detection: no idempotency record exists yet.
            // Resolved BEFORE the key claim below — the claim itself writes
            // a row, so detection must precede it.
            $isFirstScan = ! StockMovementIdempotencyKey::where(
                'transfer_requisition_id',
                $fresh->id
            )->exists();

            // §19.8 — the unique database constraint
            // (`idempotency_requisition_checksum_unique`) is the final
            // authority. Claim the key BEFORE any ledger write: the loser of
            // a concurrent-duplicate race fails here while this transaction
            // holds no stock-movement or loss-ledger side effects, re-reads
            // the winner's key, and no-ops instead of surfacing a database
            // error. Any other query failure is rethrown.
            try {
                $idempotencyKey = StockMovementIdempotencyKey::create([
                    'transfer_requisition_id' => $fresh->id,
                    'payload_checksum'        => $checksum,
                    'resulting_item_states'   => [],
                ]);
            } catch (QueryException $e) {
                $winner = StockMovementIdempotencyKey::where('transfer_requisition_id', $fresh->id)
                    ->where('payload_checksum', $checksum)
                    ->first();

                if ($winner !== null) {
                    return;
                }

                throw $e;
            }

            $actualVariantIds = $items->map(fn ($item) => $item->actualVariantId())
                ->unique()
                ->sort()
                ->values()
                ->all();

            if (! empty($actualVariantIds)) {
                ProductVariant::whereIn('id', $actualVariantIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
            }

            Warehouse::lockForUpdate()->findOrFail($fresh->to_warehouse_id);

            $scanPayload = $normalized;

            foreach ($items as $item) {
                $payload = $scanPayload[$item->id] ?? null;

                if ($payload === null && $isFirstScan) {
                    $this->writeOffOmittedItem($fresh, $item);
                    continue;
                }

                if ($payload === null) {
                    continue;
                }

                $good = (int) ($payload['received_good'] ?? 0);
                $damaged = (int) ($payload['received_damaged'] ?? 0);

                $outstanding = max(0, (int) $item->approved_base_qty - (int) $item->received_good_base_qty - (int) $item->received_damaged_base_qty);

                if ($good + $damaged > $outstanding) {
                    throw new OutstandingQuantityExceededException(
                        itemType: TransferRequisitionItem::class,
                        itemId: (int) $item->id,
                        attempted: $good + $damaged,
                        outstanding: $outstanding,
                    );
                }

                if ($good > 0) {
                    StockMovement::create([
                        'product_variant_id' => $item->actualVariantId(),
                        'warehouse_id'       => $fresh->to_warehouse_id,
                        'type'               => StockMovementType::TransferIn,
                        'quantity'           => abs($good),
                        'unit_name_used'     => $item->approved_unit_name,
                        'unit_ratio_used'    => $item->approved_unit_ratio,
                        'reference_type'     => TransferRequisition::class,
                        'reference_id'       => (string) $fresh->id,
                        'reference_code'     => $fresh->reference_code,
                        'created_by'         => auth()->id(),
                    ]);
                }

                $item->update([
                    'received_good_base_qty'    => $item->received_good_base_qty + $good,
                    'received_damaged_base_qty' => $item->received_damaged_base_qty + $damaged,
                    'received_qty'              => $item->received_qty + $good + $damaged,
                ]);

                $item->refresh();

                if ($item->received_good_base_qty + $item->received_damaged_base_qty >= $item->approved_base_qty) {
                    $this->markInTransit($item, InTransitStatus::Cleared);
                }
            }

            $idempotencyKey->update([
                'resulting_item_states' => $fresh->items()->get()->toArray(),
            ]);

            $this->updateReceiptClosingStatus($fresh);

            // `updateReceiptClosingStatus()` leaves an incompletely
            // accounted requisition in `PartiallyReceived`: the canonical
            // notification text ("Transfer :reference has been received.")
            // describes a completed intake, so only dispatch once fully
            // accounted (`Completed` / `ClosedWithLoss`). There is no
            // separate partial-receipt event.
            if ($fresh->fresh()->status !== \App\Enums\TransferRequisitionStatus::PartiallyReceived) {
                event(new \App\Events\TransferReceived($fresh->id));
            }
        });
    }

    private function writeOffOmittedItem(TransferRequisition $requisition, TransferRequisitionItem $item): void
    {
        $actualVariant = ProductVariant::query()
            ->lockForUpdate()
            ->findOrFail($item->actualVariantId());

        $unitCost = LossLedger::snapshotUnitCostFrom($actualVariant);
        $totalLoss = LossLedger::calculateTotalFinancialLoss($unitCost, $item->approved_base_qty);

        $loss = LossLedger::create([
            'transfer_requisition_id'      => $requisition->id,
            'transfer_requisition_item_id' => $item->id,
            'product_variant_id'           => $item->actualVariantId(),
            'warehouse_id'                 => $requisition->to_warehouse_id,
            'lost_base_qty'                => $item->approved_base_qty,
            'damaged_base_qty'             => 0,
            'unit_cost_price'              => $unitCost,
            'total_financial_loss'         => $totalLoss,
            'loss_category'                => 'shortfall',
            'notes'                        => bccomp($unitCost, '0.0000', 4) === 0
                ? __('resources.loss_ledgers.notes.cost_missing')
                : null,
            'recorded_by'                  => auth()->id(),
            'recorded_at'                  => now(),
        ]);

        event(new \App\Events\LossRecorded($loss->id));

        $this->markInTransit($item, InTransitStatus::Lost);
    }

    private function markInTransit(TransferRequisitionItem $item, InTransitStatus $status): void
    {
        InTransit::where('transfer_requisition_item_id', $item->id)
            ->where('status', InTransitStatus::InTransit->value)
            ->update([
                'status'     => $status->value,
                'cleared_at' => now(),
            ]);
    }

    /**
     * Terminal-state resolution shared by `scanToReceive()` and `recordLoss()`.
     *
     * An item is fully accounted when good + damaged receipts plus recorded
     * shortfall losses (`loss_ledgers.lost_base_qty` — written by
     * `writeOffOmittedItem()` and `recordLoss()`) cover its approved qty.
     * When every item is fully accounted the requisition closes:
     * `ClosedWithLoss` if any loss ledger exists for the requisition,
     * otherwise `Completed`. Anything less stays `PartiallyReceived`.
     */
    private function updateReceiptClosingStatus(TransferRequisition $requisition): void
    {
        $lostByItem = LossLedger::query()
            ->where('transfer_requisition_id', $requisition->id)
            ->selectRaw('transfer_requisition_item_id, SUM(lost_base_qty) as total')
            ->groupBy('transfer_requisition_item_id')
            ->pluck('total', 'transfer_requisition_item_id');

        $allAccounted = $requisition->items()->get()->every(
            fn ($item) => (int) $item->received_good_base_qty
                + (int) $item->received_damaged_base_qty
                + (int) ($lostByItem[$item->id] ?? 0)
                >= (int) $item->approved_base_qty
        );

        if (! $allAccounted) {
            $requisition->update([
                'status'       => \App\Enums\TransferRequisitionStatus::PartiallyReceived,
                'received_by'  => auth()->id(),
                'completed_at' => null,
            ]);

            return;
        }

        $hasLoss = LossLedger::where('transfer_requisition_id', $requisition->id)->exists();

        $requisition->update([
            'status'       => $hasLoss
                ? \App\Enums\TransferRequisitionStatus::ClosedWithLoss
                : \App\Enums\TransferRequisitionStatus::Completed,
            'received_by'  => auth()->id(),
            'completed_at' => now(),
        ]);
    }

    /**
     * Record a loss against one requisition item.
     *
     * Service boundary for the `recordLoss` table action (§7B.3): locks
     * the requisition, item, and effective variant, snapshots cost, writes
     * the LossLedger, accrues damaged qty into item receipt state
     * (mirroring `scanToReceive()` damaged accounting), and transitions
     * InTransit rows — `Cleared` when good + damaged receipts cover the
     * approved qty (mirroring `scanToReceive()`), `Lost` when the
     * shortfall write-off covers the remainder (mirroring
     * `writeOffOmittedItem()`). Partial losses leave InTransit rows
     * untouched. Finally resolves the parent requisition through
     * `updateReceiptClosingStatus()` (`ClosedWithLoss` when fully
     * accounted with losses, `Completed` when fully accounted without
     * losses, otherwise `PartiallyReceived`).
     */
    public function recordLoss(
        TransferRequisition $requisition,
        TransferRequisitionItem $item,
        int $lostBaseQty,
        int $damagedBaseQty,
        string $lossCategory,
        ?string $notes = null,
    ): LossLedger {
        return DB::transaction(function () use (
            $requisition, $item, $lostBaseQty, $damagedBaseQty, $lossCategory, $notes
        ) {
            $fresh = TransferRequisition::lockForUpdate()->findOrFail($requisition->id);

            // State guard — losses are only recorded against in-flight
            // intake. Mirrors the `StnController::scan()` (§21.3) boundary so
            // direct service calls cannot write losses on a
            // Draft/Confirmed requisition.
            if (! in_array($fresh->status, [
                \App\Enums\TransferRequisitionStatus::Dispatched,
                \App\Enums\TransferRequisitionStatus::PartiallyReceived,
            ], true)) {
                throw new InvalidDocumentStateException(
                    documentType: TransferRequisition::class,
                    documentId: (int) $fresh->id,
                    actualStatus: $fresh->status->value,
                    action: 'record_loss',
                );
            }

            $freshItem = TransferRequisitionItem::where('transfer_requisition_id', $fresh->id)
                ->lockForUpdate()
                ->findOrFail($item->id);

            $variant = ProductVariant::query()
                ->lockForUpdate()
                ->findOrFail($freshItem->actualVariantId());

            if ($lostBaseQty < 0 || $damagedBaseQty < 0) {
                throw new DomainRuleViolationException('errors.negative_payload', [
                    'item' => (int) $freshItem->id,
                ]);
            }

            if ($lostBaseQty + $damagedBaseQty < 1) {
                throw new DomainRuleViolationException('errors.empty_loss', [
                    'item' => (int) $freshItem->id,
                ]);
            }

            // Outstanding accounts for prior shortfall write-offs
            // (`loss_ledgers.lost_base_qty`, mirroring
            // `updateReceiptClosingStatus()`), so repeated loss entries
            // cannot be accepted against the same remaining quantity.
            // Damaged quantities need no separate subtraction: `recordLoss()`
            // accrues them into `received_damaged_base_qty` below.
            $recordedLostBaseQty = (int) LossLedger::where(
                'transfer_requisition_item_id',
                $freshItem->id
            )->sum('lost_base_qty');

            $outstanding = max(0, (int) $freshItem->approved_base_qty - (int) $freshItem->received_good_base_qty - (int) $freshItem->received_damaged_base_qty - $recordedLostBaseQty);

            if ($lostBaseQty + $damagedBaseQty > $outstanding) {
                throw new OutstandingQuantityExceededException(
                    itemType: TransferRequisitionItem::class,
                    itemId: (int) $freshItem->id,
                    attempted: $lostBaseQty + $damagedBaseQty,
                    outstanding: $outstanding,
                );
            }

            // Lock the warehouse before the loss-ledger write to ensure
            // uniform pessimistic locking across every multi-warehouse-touching
            // service method (Principle 3). Lock `to_warehouse_id` since the
            // loss ledger records against it.
            Warehouse::lockForUpdate()->findOrFail($fresh->to_warehouse_id);

            $unitCost = LossLedger::snapshotUnitCostFrom($variant);
            $totalLoss = LossLedger::calculateTotalFinancialLoss(
                $unitCost,
                $lostBaseQty + $damagedBaseQty
            );

            $loss = LossLedger::create([
                'transfer_requisition_id'      => $fresh->id,
                'transfer_requisition_item_id' => $freshItem->id,
                'product_variant_id'           => $variant->id,
                'warehouse_id'                 => $fresh->to_warehouse_id,
                'lost_base_qty'                => $lostBaseQty,
                'damaged_base_qty'             => $damagedBaseQty,
                'unit_cost_price'              => $unitCost,
                'total_financial_loss'         => $totalLoss,
                'loss_category'                => $lossCategory,
                'notes'                        => $notes,
                'recorded_by'                  => auth()->id(),
                'recorded_at'                  => now(),
            ]);

            if ($damagedBaseQty > 0) {
                $freshItem->update([
                    'received_damaged_base_qty' => $freshItem->received_damaged_base_qty + $damagedBaseQty,
                    'received_qty'              => $freshItem->received_qty + $damagedBaseQty,
                ]);
                $freshItem->refresh();
            }

            $received = (int) $freshItem->received_good_base_qty + (int) $freshItem->received_damaged_base_qty;

            if ($received >= (int) $freshItem->approved_base_qty) {
                $this->markInTransit($freshItem, InTransitStatus::Cleared);
            } elseif ($lostBaseQty >= max(0, (int) $freshItem->approved_base_qty - $received)) {
                $this->markInTransit($freshItem, InTransitStatus::Lost);
            }

            $this->updateReceiptClosingStatus($fresh);

            event(new \App\Events\LossRecorded($loss->id));

            return $loss;
        });
    }

    public function adjustment(
        int $productVariantId,
        int $warehouseId,
        int $signedBaseQuantity,
        string $notes,
    ): StockMovement {
        return DB::transaction(function () use ($productVariantId, $warehouseId, $signedBaseQuantity, $notes) {
            // §20.3 — the service is the security boundary: re-verify the
            // warehouse endpoint against the actor's operational scope,
            // mirroring `directTransfer()`. Admins hold global scope with a
            // possibly empty `user_warehouse` pivot and skip the membership
            // check. Auditors are read-only (ProductVariantPolicy::adjustStock)
            // and are denied here even when assigned to the warehouse.
            $actor = auth()->user();
            if (! $actor) {
                throw new DomainRuleViolationException('errors.warehouse_out_of_scope', [
                    'from' => $warehouseId,
                    'to' => $warehouseId,
                ]);
            }
            if (! $actor->isAdmin()) {
                $allowed = $actor->warehouses()->pluck('id')->all();
                if ($actor->isAuditor() || ! in_array($warehouseId, $allowed, true)) {
                    throw new DomainRuleViolationException('errors.warehouse_out_of_scope', [
                        'from' => $warehouseId,
                        'to' => $warehouseId,
                    ]);
                }
            }

            $variant = ProductVariant::lockForUpdate()->findOrFail($productVariantId);
            Warehouse::lockForUpdate()->findOrFail($warehouseId);

            return StockMovement::create([
                'product_variant_id' => $productVariantId,
                'warehouse_id'       => $warehouseId,
                'type'               => StockMovementType::Adjustment,
                'quantity'           => $signedBaseQuantity,
                'unit_name_used'     => $variant->base_unit_name,
                'unit_ratio_used'    => 1,
                'notes'              => $notes,
                'created_by'         => auth()->id(),
            ]);
        });
    }
}
```

### 6.3 NegotiationService

```php
namespace App\Services;

use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use App\Enums\TransferRequisitionStatus;
use App\Exceptions\DomainRuleViolationException;
use App\Exceptions\InvalidDocumentStateException;
use App\Exceptions\NegotiationNotAllowedException;
use App\Models\ProductVariantUnitConversion;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use App\Models\TransferRequisitionItemRevision;
use Illuminate\Support\Facades\DB;

class NegotiationService
{
    public function submitRequest(TransferRequisition $requisition): void
    {
        DB::transaction(function () use ($requisition) {
            $fresh = TransferRequisition::lockForUpdate()->findOrFail($requisition->id);

            if ($fresh->status !== TransferRequisitionStatus::Draft) {
                throw new InvalidDocumentStateException(
                    documentType: TransferRequisition::class,
                    documentId: (int) $fresh->id,
                    actualStatus: $fresh->status->value,
                    action: 'submit',
                );
            }

            $fresh->update([
                'status'       => TransferRequisitionStatus::Requested,
                'requested_at' => now(),
                'requested_by' => $fresh->requested_by ?? auth()->id(),
            ]);
        });
    }

    /**
     * Materialize requested items as approved items on confirm.
     *
     * After materialization, verifies every item has a non-null approved
     * base quantity — a confirm with a null approved qty would silently
     * produce a wrong reservation.
     */
    public function materializeRequestedAsApproved(TransferRequisition $requisition): void
    {
        foreach ($requisition->items as $item) {
            if ($item->approved_base_qty !== null) {
                continue;
            }

            $item->update([
                'approved_unit_name'  => $item->requested_unit_name,
                'approved_unit_ratio' => $item->requested_unit_ratio,
                'approved_qty'        => $item->requested_qty,
                'approved_base_qty'   => $item->requested_base_qty,
            ]);
        }

        if ($requisition->items()->whereNull('approved_base_qty')->exists()) {
            throw new DomainRuleViolationException('errors.missing_approved_quantity', [
                'requisition' => (int) $requisition->id,
            ]);
        }
    }

    public function assertNegotiable(TransferRequisitionItemRevision $revision): void
    {
        $parent = $revision->item->transferRequisition;
        if (! in_array($parent->status, [
            TransferRequisitionStatus::Requested,
            TransferRequisitionStatus::UnderReviewFulfiller,
            TransferRequisitionStatus::UnderReviewRequestor,
        ], true)) {
            throw new \App\Exceptions\NegotiationNotAllowedException(
                'errors.negotiation_not_allowed',
                ['requisition' => (int) $parent->id, 'status' => $parent->status->value],
            );
        }
        if ($revision->isResolved()) {
            throw new \App\Exceptions\NegotiationNotAllowedException(
                'errors.revision_already_resolved',
                ['revision' => (int) $revision->id],
            );
        }
    }

    public function accept(TransferRequisitionItemRevision $revision): void
    {
        DB::transaction(function () use ($revision) {
            $freshRevision = TransferRequisitionItemRevision::query()
                ->lockForUpdate()
                ->findOrFail($revision->id);

            $freshItem = TransferRequisitionItem::query()
                ->lockForUpdate()
                ->findOrFail($freshRevision->transfer_requisition_item_id);

            $parent = TransferRequisition::query()
                ->lockForUpdate()
                ->findOrFail($freshItem->transfer_requisition_id);

            // Bind the locked rows so the negotiability checks below
            // operate on locked state instead of unlocked lazy-loaded copies.
            $freshItem->setRelation('transferRequisition', $parent);
            $freshRevision->setRelation('item', $freshItem);

            $this->assertNegotiable($freshRevision);
            $freshRevision->ensureCanTransitionTo(RevisionStatus::Accepted);
            $freshRevision->update([
                'status'       => RevisionStatus::Accepted,
                'responded_at' => now(),
            ]);
        });
    }

    public function reject(TransferRequisitionItemRevision $revision): void
    {
        DB::transaction(function () use ($revision) {
            $freshRevision = TransferRequisitionItemRevision::query()
                ->lockForUpdate()
                ->findOrFail($revision->id);

            $freshItem = TransferRequisitionItem::query()
                ->lockForUpdate()
                ->findOrFail($freshRevision->transfer_requisition_item_id);

            $parent = TransferRequisition::query()
                ->lockForUpdate()
                ->findOrFail($freshItem->transfer_requisition_id);

            // Bind the locked rows so the negotiability checks below
            // operate on locked state instead of unlocked lazy-loaded copies.
            $freshItem->setRelation('transferRequisition', $parent);
            $freshRevision->setRelation('item', $freshItem);

            $this->assertNegotiable($freshRevision);
            $freshRevision->ensureCanTransitionTo(RevisionStatus::Rejected);
            $freshRevision->update([
                'status'       => RevisionStatus::Rejected,
                'responded_at' => now(),
            ]);
        });
    }

    /**
     * Submit a new negotiation revision for a requisition item.
     *
     * The proposed unit must be sourced from the effective variant's own
     * conversion rows (Phase 09: substitute-variant unit sourcing) — the
     * ratio is resolved server-side, never trusted from the form.
     *
     * The parent requisition status is transitioned to the opposite
     * side's review state on revision submission: a revision submitted by
     * the fulfiller moves the parent to `UnderReviewRequestor`; a revision
     * submitted by the requestor moves the parent to
     * `UnderReviewFulfiller`. This preserves the
     * `UnderReviewFulfiller ⇌ UnderReviewRequestor` ping-pong lifecycle.
     */
    public function submitRevision(
        TransferRequisitionItem $item,
        ?int $substituteVariantId,
        NegotiationSide $side,
        string $proposedUnitName,
        int $proposedQty,
        ?string $negotiationReason = null,
        ?int $respondsToRevisionId = null,
    ): TransferRequisitionItemRevision {
        return DB::transaction(function () use (
            $item, $substituteVariantId, $side,
            $proposedUnitName, $proposedQty,
            $negotiationReason, $respondsToRevisionId
        ) {
            $freshItem = TransferRequisitionItem::query()
                ->lockForUpdate()
                ->findOrFail($item->id);

            $parent = TransferRequisition::query()
                ->lockForUpdate()
                ->findOrFail($freshItem->transfer_requisition_id);

            if (! in_array($parent->status, [
                TransferRequisitionStatus::Requested,
                TransferRequisitionStatus::UnderReviewFulfiller,
                TransferRequisitionStatus::UnderReviewRequestor,
            ], true)) {
                throw new NegotiationNotAllowedException(
                    'errors.negotiation_not_allowed',
                    ['requisition' => (int) $parent->id, 'status' => $parent->status->value],
                );
            }

            if ($proposedQty < 1) {
                throw new DomainRuleViolationException('errors.invalid_proposed_quantity', [
                    'qty' => $proposedQty,
                ]);
            }

            $effectiveVariantId = $substituteVariantId ?? $freshItem->product_variant_id;

            $proposedUnitRatio = ProductVariantUnitConversion::where(
                'product_variant_id',
                $effectiveVariantId
            )
                ->where('unit_name', $proposedUnitName)
                ->value('base_unit_ratio');

            if ($proposedUnitRatio === null) {
                throw new DomainRuleViolationException('errors.undefined_unit', [
                    'unit' => $proposedUnitName,
                    'variant' => $effectiveVariantId,
                ]);
            }

            if ($respondsToRevisionId !== null) {
                $respondsTo = TransferRequisitionItemRevision::query()
                    ->findOrFail($respondsToRevisionId);

                if ((int) $respondsTo->transfer_requisition_item_id !== (int) $freshItem->id) {
                    throw new DomainRuleViolationException('errors.cross_item_revision', [
                        'revision' => $respondsToRevisionId,
                        'item' => (int) $freshItem->id,
                    ]);
                }
            }

            $revision = TransferRequisitionItemRevision::create([
                'transfer_requisition_item_id'  => $freshItem->id,
                'user_id'                       => auth()->id(),
                'product_variant_id'            => $freshItem->product_variant_id,
                'substitute_product_variant_id' => $substituteVariantId,
                'proposed_unit_name'            => $proposedUnitName,
                'proposed_unit_ratio'           => $proposedUnitRatio,
                'proposed_qty'                  => $proposedQty,
                'proposed_base_qty'             => $proposedQty * (int) $proposedUnitRatio,
                'negotiation_reason'            => $negotiationReason,
                'side'                          => $side,
                'status'                        => RevisionStatus::Pending,
                'responds_to_revision_id'       => $respondsToRevisionId,
            ]);

            // Opposite-side review transition (owner decision): the
            // submitting side proposes, the counterpart reviews next.
            // Already in the target state → no-op.
            $targetStatus = $side === NegotiationSide::Fulfiller
                ? TransferRequisitionStatus::UnderReviewRequestor
                : TransferRequisitionStatus::UnderReviewFulfiller;

            if ($parent->status !== $targetStatus) {
                $parent->update(['status' => $targetStatus]);
            }

            return $revision;
        });
    }
}
```

**Exception contract** — typed domain exceptions (implements §0A.10). No service (§6) or model (§3) throws a bare `\DomainException` with English UI copy. Every domain failure is a typed exception carrying a stable translation key plus machine context; the presentation layer resolves `__($e->translationKey() . '.title', $e->context())`. All types extend `\DomainException` (directly or via the base below) so existing `catch (\DomainException)` handling keeps working.

`app/Exceptions/DomainErrorException.php` — abstract base:

```php
namespace App\Exceptions;

abstract class DomainErrorException extends \DomainException
{
    public function __construct(
        private readonly string $translationKey,
        private readonly array $context = [],
    ) {
        parent::__construct($translationKey);
    }

    public function translationKey(): string
    {
        return $this->translationKey;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }
}
```

`app/Exceptions/InsufficientStockException.php` (the §0A.10 example, canonical):

```php
namespace App\Exceptions;

class InsufficientStockException extends DomainErrorException
{
    public function __construct(
        public readonly int $variantId,
        public readonly int $warehouseId,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct('errors.insufficient_stock', [
            'variant'   => $variantId,
            'requested' => $requested,
            'available' => $available,
        ]);
    }
}
```

`app/Exceptions/OutstandingQuantityExceededException.php`:

```php
namespace App\Exceptions;

class OutstandingQuantityExceededException extends DomainErrorException
{
    public function __construct(
        public readonly string $itemType,
        public readonly int $itemId,
        public readonly int $attempted,
        public readonly int $outstanding,
    ) {
        parent::__construct('errors.outstanding_quantity_exceeded', [
            'item'        => $itemId,
            'attempted'   => $attempted,
            'outstanding' => $outstanding,
        ]);
    }
}
```

`app/Exceptions/InvalidDocumentStateException.php`:

```php
namespace App\Exceptions;

class InvalidDocumentStateException extends DomainErrorException
{
    public function __construct(
        public readonly string $documentType,
        public readonly int $documentId,
        public readonly string $actualStatus,
        public readonly string $action,
    ) {
        parent::__construct('errors.invalid_document_state', [
            'status' => $actualStatus,
            'action' => $action,
        ]);
    }
}
```

`app/Exceptions/InvalidRevisionTransitionException.php`:

```php
namespace App\Exceptions;

class InvalidRevisionTransitionException extends DomainErrorException
{
    public function __construct(
        public readonly string $actualStatus,
        public readonly string $targetStatus,
    ) {
        parent::__construct('errors.invalid_revision_transition', [
            'actual' => $actualStatus,
            'target' => $targetStatus,
        ]);
    }
}
```

`app/Exceptions/ProductFamilyHasVariantsException.php`:

```php
namespace App\Exceptions;

class ProductFamilyHasVariantsException extends DomainErrorException
{
    public function __construct(public readonly int $productId)
    {
        parent::__construct('errors.product_family_has_variants', [
            'product' => $productId,
        ]);
    }
}
```

`app/Exceptions/DomainRuleViolationException.php` — concrete key + context carrier for caller/precondition violations with no dedicated type:

```php
namespace App\Exceptions;

class DomainRuleViolationException extends DomainErrorException {}
```

`app/Exceptions/NegotiationNotAllowedException.php` (thrown by `NegotiationService::assertNegotiable()` and `submitRevision()`; extends `DomainErrorException` so existing domain-error handling keeps working):

```php
namespace App\Exceptions;

class NegotiationNotAllowedException extends DomainErrorException {}
```

Key catalogue — every key below lives in the mandated `lang/{locale}/errors.php` (§0A.2, §25) with `.title` / `.body` entries per §0A.10:

| Exception | Translation key | Context |
|---|---|---|
| `InsufficientStockException` | `errors.insufficient_stock` | `variant`, `requested`, `available` |
| `OutstandingQuantityExceededException` | `errors.outstanding_quantity_exceeded` | `item`, `attempted`, `outstanding` |
| `InvalidDocumentStateException` | `errors.invalid_document_state` | `status`, `action` |
| `InvalidRevisionTransitionException` | `errors.invalid_revision_transition` | `actual`, `target` |
| `ProductFamilyHasVariantsException` | `errors.product_family_has_variants` | `product` |
| `NegotiationNotAllowedException` | `errors.negotiation_not_allowed` | `requisition`, `status` |
| `NegotiationNotAllowedException` | `errors.revision_already_resolved` | `revision` |
| `DomainRuleViolationException` | `errors.invalid_movement_type` | `type` |
| `DomainRuleViolationException` | `errors.invalid_unit_ratio` | `ratio`, `index` when per-line |
| `DomainRuleViolationException` | `errors.same_warehouse_transfer` | `warehouse` |
| `DomainRuleViolationException` | `errors.empty_transfer_items` | — |
| `DomainRuleViolationException` | `errors.duplicate_transfer_variant` | `index`, `variant` |
| `DomainRuleViolationException` | `errors.empty_purchase_items` | — |
| `DomainRuleViolationException` | `errors.empty_purchase_receipt` | — |
| `DomainRuleViolationException` | `errors.empty_sales_items` | — |
| `DomainRuleViolationException` | `errors.empty_sales_dispatch` | — |
| `DomainRuleViolationException` | `errors.empty_requisition_items` | — |
| `DomainRuleViolationException` | `errors.invalid_return_quantity` | `item`, `qty` |
| `DomainRuleViolationException` | `errors.missing_item_field` | `index`, `field` |
| `DomainRuleViolationException` | `errors.invalid_item_quantity` | `index`, `qty` |
| `DomainRuleViolationException` | `errors.unknown_variant` | `variant` |
| `DomainRuleViolationException` | `errors.undefined_unit` | `unit`, `variant`, `index` when per-line |
| `DomainRuleViolationException` | `errors.unit_ratio_mismatch` | `index`, `unit` |
| `DomainRuleViolationException` | `errors.unknown_requisition_item` | `item` |
| `DomainRuleViolationException` | `errors.non_integer_payload` | `item` |
| `DomainRuleViolationException` | `errors.negative_payload` | `item` |
| `DomainRuleViolationException` | `errors.empty_loss` | `item` |
| `DomainRuleViolationException` | `errors.invalid_proposed_quantity` | `qty` |
| `DomainRuleViolationException` | `errors.cross_item_revision` | `revision`, `item` |
| `DomainRuleViolationException` | `errors.missing_approved_quantity` | `requisition` or `item` |
| `DomainRuleViolationException` | `errors.warehouse_out_of_scope` | `from`, `to` |
| `DomainRuleViolationException` | `errors.warehouse_code_exhausted` | `name` |

### 6.4 PurchaseService

```php
namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Exceptions\DomainRuleViolationException;
use App\Exceptions\InvalidDocumentStateException;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(
        private readonly GuardsOutstandingQuantity $guards,
    ) {}

    public function orderPurchase(PurchaseOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $fresh = PurchaseOrder::lockForUpdate()->findOrFail($order->id);

            if ($fresh->status !== PurchaseOrderStatus::Draft) {
                throw new InvalidDocumentStateException(
                    documentType: PurchaseOrder::class,
                    documentId: (int) $fresh->id,
                    actualStatus: $fresh->status->value,
                    action: 'order',
                );
            }

            // The form requires at least one repeater item (§7G.1):
            // never order an item-less purchase.
            if (! $fresh->items()->exists()) {
                throw new DomainRuleViolationException('errors.empty_purchase_items');
            }

            $fresh->update([
                'status'     => PurchaseOrderStatus::Ordered,
                'ordered_at' => now(),
                'ordered_by' => $fresh->ordered_by ?? auth()->id(),
            ]);

            // Dispatch purchase-order-received event on ordering (Principle 19.4)
            event(new \App\Events\PurchaseOrderReceived($order->id));
        });
    }

    /**
     * @param  array<int, int>  $receivedByItemId  item_id => base_qty_received
     */
    public function receivePurchase(int $orderId, array $receivedByItemId): void
    {
        DB::transaction(function () use ($orderId, $receivedByItemId) {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($orderId);

            if (! in_array($order->status, [
                PurchaseOrderStatus::Ordered,
                PurchaseOrderStatus::PartiallyReceived,
            ], true)) {
                throw new InvalidDocumentStateException(
                    documentType: PurchaseOrder::class,
                    documentId: (int) $order->id,
                    actualStatus: $order->status->value,
                    action: 'receive',
                );
            }

            $items = PurchaseOrderItem::where('purchase_order_id', $order->id)
                ->lockForUpdate()
                ->get();

            $variantIds = $items->pluck('product_variant_id')->unique()->values()->all();

            if (! empty($variantIds)) {
                ProductVariant::whereIn('id', $variantIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
            }

            Warehouse::lockForUpdate()->findOrFail($order->warehouse_id);

            // Reject an empty or all-zero receipt payload: with no
            // movement created, `$items->every(...)` below would otherwise
            // still mutate the order status.
            $hasReceiptMovement = false;
            foreach ($items as $item) {
                if ((int) ($receivedByItemId[$item->id] ?? 0) > 0) {
                    $hasReceiptMovement = true;
                    break;
                }
            }
            if (! $hasReceiptMovement) {
                throw new DomainRuleViolationException('errors.empty_purchase_receipt');
            }

            foreach ($items as $item) {
                $received = (int) ($receivedByItemId[$item->id] ?? 0);
                if ($received <= 0) {
                    continue;
                }

                $this->guards->assertPurchaseNotOverReceived($item, $received);

                StockMovement::create([
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id'       => $order->warehouse_id,
                    'type'               => StockMovementType::Purchase,
                    'quantity'           => abs($received),
                    'unit_name_used'     => $item->ordered_unit_name,
                    'unit_ratio_used'    => $item->ordered_unit_ratio,
                    'reference_type'     => PurchaseOrder::class,
                    'reference_id'       => (string) $order->id,
                    'reference_code'     => $order->reference_code,
                    'created_by'         => auth()->id(),
                ]);

                $item->update(['received_base_qty' => $item->received_base_qty + $received]);
            }

            $allReceived = $items->every(
                fn ($item) => $item->fresh()->received_base_qty >= $item->ordered_base_qty
            );

            $order->update([
                'status'      => $allReceived ? PurchaseOrderStatus::Received : PurchaseOrderStatus::PartiallyReceived,
                'received_at' => $allReceived ? now() : null,
                'received_by' => auth()->id(),
            ]);

            // The canonical notification text ("Purchase order :reference
            // has been received.") describes a completed receipt: only
            // dispatch on `Received`, not on `PartiallyReceived` (there is
            // no separate partial-receipt event).
            if ($allReceived) {
                event(new \App\Events\PurchaseOrderReceived($order->id));
            }

            if ($order->update_cost_price) {
                // One current-price row per variant per receipt: several lines
                // may share a variant, so only the first line's cost wins.
                // (`$items` carries the post-update `received_base_qty`
                // values in memory — `update()` syncs attributes — while the
                // `$allReceived` completion check above re-reads each row via
                // `fresh()` so concurrent receipts are observed.)
                $priceUpdated = [];
                foreach ($items as $item) {
                    if ((int) ($receivedByItemId[$item->id] ?? 0) <= 0) {
                        continue;
                    }
                    if (isset($priceUpdated[$item->product_variant_id])) {
                        continue;
                    }
                    $priceUpdated[$item->product_variant_id] = true;
                    $this->updateCurrentCostPrice($item->product_variant_id, $item->unit_cost_price);
                }
            }
        });
    }

    public function cancelPurchaseOrder(PurchaseOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $fresh = PurchaseOrder::lockForUpdate()->findOrFail($order->id);

            if (! $fresh->canBeCancelled()) {
                throw new InvalidDocumentStateException(
                    documentType: PurchaseOrder::class,
                    documentId: (int) $fresh->id,
                    actualStatus: $fresh->status->value,
                    action: 'cancel',
                );
            }

            $fresh->update([
                'status'       => PurchaseOrderStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            // Dispatch purchase-order-cancelled event on cancellation
            event(new \App\Events\PurchaseOrderCancelled($order->id));
        });
    }

    private function updateCurrentCostPrice(int $variantId, string $newCost): void
    {
        $variant = ProductVariant::lockForUpdate()->findOrFail($variantId);
        $current = $variant->currentPrice;

        if ($current && bccomp($current->cost_price, $newCost, 4) === 0) {
            return;
        }

        if ($current) {
            $current->update(['is_current' => false]);
        }

        ProductVariantPrice::create([
            'product_variant_id' => $variantId,
            'cost_price'         => $newCost,
            'sale_price'         => $current?->sale_price ?? '0.0000',
            'effective_from'     => now(),
            'is_current'         => true,
            'set_by'             => auth()->id(),
        ]);
    }
}
```

### 6.5 SalesService

```php
namespace App\Services;

use App\Enums\SalesOrderStatus;
use App\Enums\StockMovementType;
use App\Exceptions\DomainRuleViolationException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidDocumentStateException;
use App\Exceptions\OutstandingQuantityExceededException;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class SalesService
{
    public function __construct(
        private readonly GuardsOutstandingQuantity $guards,
    ) {}

    public function confirmSalesOrder(SalesOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $fresh = SalesOrder::lockForUpdate()->findOrFail($order->id);

            if ($fresh->status !== SalesOrderStatus::Draft) {
                throw new InvalidDocumentStateException(
                    documentType: SalesOrder::class,
                    documentId: (int) $fresh->id,
                    actualStatus: $fresh->status->value,
                    action: 'confirm',
                );
            }

            $items = SalesOrderItem::where('sales_order_id', $fresh->id)
                ->lockForUpdate()
                ->get();

            // The form requires at least one line item (§7H.1): never
            // confirm an item-less sales order.
            if ($items->isEmpty()) {
                throw new DomainRuleViolationException('errors.empty_sales_items');
            }

            $variantIds = $items->pluck('product_variant_id')->unique()->values()->all();

            $lockedVariants = ProductVariant::whereIn('id', $variantIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->with('currentPrice')
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $variant = $lockedVariants->get($item->product_variant_id);
                $salePrice = (string) ($variant?->currentPrice?->sale_price ?? '0.0000');
                $item->update(['unit_sale_price_snapshot' => $salePrice]);
            }

            $fresh->update([
                'status'       => SalesOrderStatus::Confirmed,
                'confirmed_at' => now(),
            ]);
        });
    }

    /**
     * @param  array<int, int>  $dispatchByItemId  item_id => base_qty_dispatched
     */
    public function dispatchSale(int $orderId, array $dispatchByItemId): void
    {
        DB::transaction(function () use ($orderId, $dispatchByItemId) {
            $order = SalesOrder::lockForUpdate()->findOrFail($orderId);

            if (! in_array($order->status, [
                SalesOrderStatus::Confirmed,
                SalesOrderStatus::PartiallyDispatched,
            ], true)) {
                throw new InvalidDocumentStateException(
                    documentType: SalesOrder::class,
                    documentId: (int) $order->id,
                    actualStatus: $order->status->value,
                    action: 'dispatch',
                );
            }

            $items = SalesOrderItem::where('sales_order_id', $order->id)
                ->lockForUpdate()
                ->get();

            $variantIds = $items->pluck('product_variant_id')->unique()->values()->all();

            if (! empty($variantIds)) {
                ProductVariant::whereIn('id', $variantIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
            }

            Warehouse::lockForUpdate()->findOrFail($order->warehouse_id);

            // Exclude this order's own reservation so its outstanding qty
            // does not count against its own availability.
            $availableByVariant = ProductVariant::batchAvailableQuantity(
                $variantIds,
                $order->warehouse_id,
                $order->id,
            );

            $reorderPoints = ProductVariant::whereIn('id', $variantIds)->pluck('reorder_point', 'id');

            // Reject an empty/all-zero dispatch payload: with no movement
            // created, the `every()` completion check below would otherwise
            // still mutate the order status.
            $hasDispatchMovement = false;
            foreach ($items as $item) {
                if ((int) ($dispatchByItemId[$item->id] ?? 0) > 0) {
                    $hasDispatchMovement = true;
                    break;
                }
            }
            if (! $hasDispatchMovement) {
                throw new DomainRuleViolationException('errors.empty_sales_dispatch');
            }

            foreach ($items as $item) {
                $dispatched = (int) ($dispatchByItemId[$item->id] ?? 0);
                if ($dispatched <= 0) {
                    continue;
                }

                $this->guards->assertSaleNotOverDispatched($item, $dispatched);

                $available = $availableByVariant[$item->product_variant_id] ?? 0;
                if ($dispatched > $available) {
                    throw new InsufficientStockException(
                        variantId: $item->product_variant_id,
                        warehouseId: $order->warehouse_id,
                        requested: $dispatched,
                        available: $available,
                    );
                }

                StockMovement::create([
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id'       => $order->warehouse_id,
                    'type'               => StockMovementType::Sale,
                    'quantity'           => -abs($dispatched),
                    'unit_name_used'     => $item->unit_name,
                    'unit_ratio_used'    => $item->unit_ratio,
                    'reference_type'     => SalesOrder::class,
                    'reference_id'       => (string) $order->id,
                    'reference_code'     => $order->reference_code,
                    'created_by'         => auth()->id(),
                ]);

                $item->update(['dispatched_base_qty' => $item->dispatched_base_qty + $dispatched]);
                $remaining = $available - $dispatched;
                $availableByVariant[$item->product_variant_id] = $remaining;

                $reorderPoint = $reorderPoints[$item->product_variant_id] ?? 0;
                if ($available >= $reorderPoint && $remaining < $reorderPoint) {
                    event(new \App\Events\InventoryBelowReorderPoint($item->product_variant_id, $order->warehouse_id));
                }
            }

            $allDispatched = $items->every(
                fn ($item) => $item->fresh()->dispatched_base_qty >= $item->base_qty
            );

            $order->update([
                'status'        => $allDispatched ? SalesOrderStatus::Dispatched : SalesOrderStatus::PartiallyDispatched,
                'dispatched_at' => $allDispatched ? now() : null,
                'dispatched_by' => auth()->id(),
            ]);

            // The catalogue describes the event as "Sales order :reference
            // has been dispatched.": only dispatch on `Dispatched`, not on
            // `PartiallyDispatched` (there is no separate partial event).
            if ($allDispatched) {
                event(new \App\Events\SalesOrderDispatched($order->id));
            }
        });
    }

    /**
     * Record a sales return with server-side cumulative over-return guard.
     * Locks variant and warehouse to preserve uniform locking discipline.
     */
    public function recordSalesReturn(int $itemId, int $returnedBaseQty, ?string $notes = null): void
    {
        DB::transaction(function () use ($itemId, $returnedBaseQty, $notes) {
            $item = SalesOrderItem::lockForUpdate()->findOrFail($itemId);

            $order = $item->salesOrder;

            ProductVariant::lockForUpdate()->findOrFail($item->product_variant_id);
            Warehouse::lockForUpdate()->findOrFail($order->warehouse_id);

            $alreadyReturned = $item->alreadyReturnedBaseQty();

            // The form requires `returned_base_qty` to be at least 1
            // (§7H.3): reject zero or negative service input instead of
            // normalizing it with `abs()`.
            if ($returnedBaseQty < 1) {
                throw new DomainRuleViolationException('errors.invalid_return_quantity', [
                    'item' => (int) $item->id,
                    'qty' => $returnedBaseQty,
                ]);
            }

            if ($alreadyReturned + $returnedBaseQty > $item->dispatched_base_qty) {
                throw new OutstandingQuantityExceededException(
                    itemType: SalesOrderItem::class,
                    itemId: (int) $item->id,
                    attempted: $alreadyReturned + $returnedBaseQty,
                    outstanding: (int) $item->dispatched_base_qty - (int) $alreadyReturned,
                );
            }

            StockMovement::create([
                'product_variant_id' => $item->product_variant_id,
                'warehouse_id'       => $order->warehouse_id,
                'type'               => StockMovementType::SaleReturn,
                'quantity'           => $returnedBaseQty,
                'unit_name_used'     => $item->unit_name,
                'unit_ratio_used'    => $item->unit_ratio,
                'reference_type'     => SalesOrderItem::class,
                'reference_id'       => (string) $item->id,
                'reference_code'     => $order->reference_code,
                'notes'              => $notes,
                'created_by'         => auth()->id(),
            ]);
        });
    }

    public function cancelSalesOrder(SalesOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $fresh = SalesOrder::lockForUpdate()->findOrFail($order->id);

            if (! $fresh->canBeCancelled()) {
                throw new InvalidDocumentStateException(
                    documentType: SalesOrder::class,
                    documentId: (int) $fresh->id,
                    actualStatus: $fresh->status->value,
                    action: 'cancel',
                );
            }

            $fresh->update([
                'status'       => SalesOrderStatus::Cancelled,
                'cancelled_at' => now(),
            ]);
        });
    }
}
```

### 6.6 TransferRequisitionService

```php
namespace App\Services;

use App\Enums\TransferRequisitionStatus;
use App\Exceptions\DomainRuleViolationException;
use App\Exceptions\InvalidDocumentStateException;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItem;
use Illuminate\Support\Facades\DB;

class TransferRequisitionService
{
    public function __construct(
        private readonly NegotiationService $negotiation,
    ) {}

    public function confirm(TransferRequisition $requisition): void
    {
        // Canonical boundary (§19.1): the event is dispatched INSIDE the
        // transaction. After-commit delivery is guaranteed by
        // `ShouldDispatchAfterCommit` on the event (§22.1a/§22.2).
        DB::transaction(function () use ($requisition) {
            $fresh = TransferRequisition::query()
                ->lockForUpdate()
                ->findOrFail($requisition->id);

            if (! in_array($fresh->status, [
                TransferRequisitionStatus::Requested,
                TransferRequisitionStatus::UnderReviewFulfiller,
                TransferRequisitionStatus::UnderReviewRequestor,
            ], true)) {
                throw new InvalidDocumentStateException(
                    documentType: TransferRequisition::class,
                    documentId: (int) $fresh->id,
                    actualStatus: $fresh->status->value,
                    action: 'confirm',
                );
            }

            // The locked item collection is bound to the parent relation so
            // materializeRequestedAsApproved() operates on locked rows
            // instead of lazy-loading unlocked copies.
            $items = TransferRequisitionItem::query()
                ->where('transfer_requisition_id', $fresh->id)
                ->lockForUpdate()
                ->get();

            // The form requires at least one manifest item (§7B.1): never
            // confirm an item-less requisition.
            if ($items->isEmpty()) {
                throw new DomainRuleViolationException('errors.empty_requisition_items');
            }

            $fresh->setRelation('items', $items);

            $this->negotiation->materializeRequestedAsApproved($fresh);

            $fresh->update([
                'status' => TransferRequisitionStatus::Confirmed,
                'approved_at' => now(),
                'approved_by' => auth()->id(),
            ]);

            event(new \App\Events\TransferConfirmed($fresh->id));
        });
    }

    public function cancelRequisition(TransferRequisition $requisition): void
    {
        // Canonical boundary (§19.2): the event is dispatched INSIDE the
        // transaction. After-commit delivery is guaranteed by
        // `ShouldDispatchAfterCommit` on the event (§22.1a/§22.2).
        DB::transaction(function () use ($requisition) {
            $fresh = TransferRequisition::query()
                ->lockForUpdate()
                ->findOrFail($requisition->id);

            if (! $fresh->canBeCancelled()) {
                throw new InvalidDocumentStateException(
                    documentType: TransferRequisition::class,
                    documentId: (int) $fresh->id,
                    actualStatus: $fresh->status->value,
                    action: 'cancel',
                );
            }

            $fresh->update([
                'status' => TransferRequisitionStatus::Cancelled,
            ]);

            event(new \App\Events\TransferCancelled($fresh->id));
        });
    }
}
```

---

## 🎨 Section 7: Filament Resources — Master Specifications

### 7A. ProductResource

**Model:** `App\Models\ProductVariant` · **Group:** CATALOG · **Sort:** 1 · **Route:** `/admin/products`

#### 7A.1 ProductForm.php

```php
namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()
                ->persistTabInQueryString()
                ->columnSpanFull()
                ->tabs([
                    Tab::make(__('resources.products.tabs.identity'))
                        ->icon(Heroicon::Identification)
                        ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                        ->schema([
                            Select::make('product_id')
                                ->label(__('resources.products.fields.product_family'))
                                ->relationship('product', 'name')
                                ->prefixIcon(Heroicon::FolderOpen)
                                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                                ->required()
                                // Native behavior (P1 §16.3 #2): the newly
                                // created family is auto-selected — no custom
                                // afterStateUpdated callback required.
                                ->createOptionForm(fn (Schema $schema) => $schema->components([
                                    TextInput::make('name')
                                        ->label(__('resources.products.fields.family_name'))
                                        ->prefixIcon(Heroicon::Identification)
                                        ->columnSpanFull()
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('category')
                                        ->label(__('resources.products.fields.family_category'))
                                        ->prefixIcon(Heroicon::Tag)
                                        ->columnSpanFull(),
                                ])),

                            TextInput::make('sku')
                                ->label(__('resources.products.fields.sku'))
                                ->prefixIcon(Heroicon::Tag)
                                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                                ->required()
                                ->unique(ignoreRecord: true),

                            TextInput::make('barcode')
                                ->label(__('resources.products.fields.barcode'))
                                ->prefixIcon(Heroicon::QrCode)
                                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                                ->nullable()
                                ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : null)
                                ->unique(ignoreRecord: true),

                            TextInput::make('name')
                                ->label(__('resources.products.fields.variant_name'))
                                ->prefixIcon(Heroicon::Identification)
                                ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                                ->required(),

                            FileUpload::make('images')
                                ->label(__('resources.products.fields.images'))
                                ->columnSpanFull()
                                ->multiple()
                                ->image(),
                        ]),

                    Tab::make(__('resources.products.tabs.stock_pricing'))
                        ->icon(Heroicon::CurrencyDollar)
                        ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                        ->schema([
                            TextInput::make('base_unit_name')
                                ->label(__('resources.products.fields.base_unit_name'))
                                ->prefixIcon(Heroicon::Scale)
                                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                                ->required(),

                            TextInput::make('reorder_point')
                                ->label(__('resources.products.fields.reorder_point'))
                                ->prefixIcon(Heroicon::ExclamationTriangle)
                                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                                ->numeric()
                                ->default(0)
                                ->required(),

                            KeyValue::make('attributes')
                                ->label(__('resources.products.fields.attributes'))
                                ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2]),
                        ]),

                    Tab::make(__('resources.products.tabs.status'))
                        ->icon(Heroicon::CheckCircle)
                        ->schema([
                            Toggle::make('is_active')
                                ->label(__('resources.products.fields.is_active'))
                                ->onIcon(Heroicon::CheckCircle)
                                ->offIcon(Heroicon::XCircle)
                                ->columnSpanFull()
                                ->default(true),
                        ]),
                ]),
        ]);
    }
}
```

#### 7A.2 ProductsTable.php — **Card Layout, No Bulk Actions**

```php
namespace App\Filament\Resources\Products\Tables;

use App\Filament\Resources\Products\Actions\EditProductFamilyAction;
use App\Filament\Resources\Products\Actions\ManageUnitConversionsAction;
use App\Filament\Resources\Products\Actions\QuickStockAdjustmentAction;
use App\Filament\Resources\Products\Actions\SetCurrentPriceAction;
use App\Filament\Resources\Products\ProductResource;
use App\Models\ProductVariantPrice;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('sku')
                            ->label(__('resources.products.table.sku'))
                            ->fontFamily('mono')
                            ->weight(FontWeight::Bold)
                            ->searchable()
                            ->sortable()
                            ->copyable()
                            ->copyMessage(__('common.copied')),

                        TextColumn::make('currentPrice.sale_price')
                            ->label(__('resources.products.table.sale_price'))
                            ->formatStateUsing(fn ($state): string => format_money($state, 2))
                            ->weight(FontWeight::Bold)
                            ->alignEnd()
                            ->sortable(query: function (Builder $query, string $direction): Builder {
                                return $query->orderBy(
                                    ProductVariantPrice::query()
                                        ->select('sale_price')
                                        ->whereColumn('product_variant_id', 'product_variants.id')
                                        ->where('is_current', true)
                                        ->limit(1),
                                    $direction
                                );
                            }),
                    ])->from('md'),

                    TextColumn::make('name')
                        ->label(__('resources.products.table.name'))
                        ->searchable()
                        ->sortable()
                        ->limit(50)
                        ->weight(FontWeight::SemiBold),

                    Split::make([
                        TextColumn::make('product.name')
                            ->label(__('resources.products.table.family'))
                            ->badge()
                            ->color('gray')
                            ->searchable(),

                        TextColumn::make('base_unit_name')
                            ->label(__('resources.products.table.base_unit'))
                            ->badge()
                            ->color('info'),

                        TextColumn::make('reorder_point')
                            ->label(__('resources.products.table.reorder_point'))
                            ->badge()
                            ->color(fn ($state) => $state > 0 ? 'warning' : 'gray')
                            ->numeric(),
                    ])->from('md'),

                    IconColumn::make('is_active')
                        ->label(__('resources.products.table.status'))
                        ->boolean()
                        ->trueIcon(Heroicon::CheckCircle)
                        ->falseIcon(Heroicon::XCircle)
                        ->trueColor('success')
                        ->falseColor('danger'),
                ])->space(3),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('resources.products.filters.is_active')),
                SelectFilter::make('product_id')
                    ->label(__('resources.products.filters.product_family'))
                    ->relationship('product', 'name')
                    ->searchable(),
                TrashedFilter::make(),
            ])
            ->defaultSort('sku')
            ->defaultPaginationPageOption(12)
            ->paginated([12, 24, 48])
            // `ProductResource` is bound to the `ProductVariant` model (§7A
            // header), so the `view` route resolves the same variant record
            // the table row represents — no cross-model binding applies.
            ->recordUrl(fn ($record) => ProductResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::Eye),

                EditAction::make()
                    ->icon(Heroicon::PencilSquare)
                    ->authorize('update')
                    ->modalWidth(Width::Large),

                ActionGroup::make([
                    // ── Section: Catalog operations ───────────────────────────
                    ActionGroup::make([
                        SetCurrentPriceAction::make(),

                        EditProductFamilyAction::make()
                            ->icon(Heroicon::FolderOpen),

                        ManageUnitConversionsAction::make()
                            ->icon(Heroicon::Scale),

                        QuickStockAdjustmentAction::make(),
                    ])->dropdown(false),

                    // ── Section: Destructive ──────────────────────────────────
                    ActionGroup::make([
                        DeleteAction::make()
                            ->icon(Heroicon::Trash)
                            ->authorize('delete'),

                        RestoreAction::make()
                            ->icon(Heroicon::ArrowUturnLeft)
                            ->authorize('restore'),
                    ])->dropdown(false),
                ])
                    ->icon(Heroicon::EllipsisVertical)
                    ->iconButton()
                    ->size(Size::Small)
                    ->color('gray')
                    ->tooltip(__('resources.products.actions.more_actions'))
                    ->dropdownAutoPlacement()
                    ->dropdownWidth(Width::Large),
            ]);
    }
}
```

#### 7A.3 ProductInfolist.php

```php
namespace App\Filament\Resources\Products\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])->schema([
                Section::make(__('resources.products.infolist.identity'))
                    ->icon(Heroicon::Identification)
                    ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->schema([
                        TextEntry::make('product.name')
                            ->label(__('resources.products.fields.family_name'))
                            ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2]),
                        TextEntry::make('sku')
                            ->label(__('resources.products.fields.sku'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('barcode')
                            ->label(__('resources.products.fields.barcode'))
                            ->placeholder(__('common.empty'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),

                        ImageEntry::make('images')
                            ->label(__('resources.products.fields.images'))
                            ->columnSpanFull(),

                        KeyValueEntry::make('attributes')
                            ->label(__('resources.products.fields.attributes'))
                            ->columnSpanFull(),
                    ]),

                Section::make(__('resources.products.infolist.pricing'))
                    ->icon(Heroicon::CurrencyDollar)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->columns(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->schema([
                        TextEntry::make('currentPrice.cost_price')
                            ->label(__('resources.products.fields.cost_price'))
                            ->money(config('app.currency')),
                        TextEntry::make('currentPrice.sale_price')
                            ->label(__('resources.products.fields.sale_price'))
                            ->money(config('app.currency')),
                    ]),

                Section::make(__('resources.products.infolist.unit_conversions'))
                    ->icon(Heroicon::Scale)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('unitConversions')
                            ->label(__('resources.products.fields.unit_conversions'))
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])->schema([
                                    TextEntry::make('unit_name')
                                        ->label(__('resources.products.fields.unit_name'))
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                                    TextEntry::make('base_unit_ratio')
                                        ->label(__('resources.products.fields.base_unit_ratio'))
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                                    TextEntry::make('is_default_purchase')
                                        ->label(__('resources.products.fields.is_default_purchase'))
                                        ->badge()->boolean()
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                                ]),
                            ]),
                    ]),
            ]),
        ]);
    }
}
```

#### 7A.4 Product Actions — Full Implementations

##### 7A.4.1 SetCurrentPriceAction.php

```php
namespace App\Filament\Resources\Products\Actions;

use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class SetCurrentPriceAction
{
    public static function make(): Action
    {
        return Action::make('setCurrentPrice')
            ->label(__('resources.products.actions.set_current_price'))
            ->modalHeading(__('resources.products.actions.set_current_price_heading'))
            ->modalDescription(__('resources.products.actions.set_current_price_description'))
            ->icon(Heroicon::CurrencyDollar)
            ->color('primary')
            ->modalWidth(Width::Large)
            ->authorize('update')
            ->fillForm(fn (ProductVariant $record) => [
                'cost_price' => $record->currentPrice?->cost_price ?? '0.0000',
                'sale_price' => $record->currentPrice?->sale_price ?? '0.0000',
                'notes'      => null,
            ])
            ->schema([
                TextInput::make('cost_price')
                    ->label(__('resources.products.fields.cost_price'))
                    ->prefixIcon(Heroicon::CurrencyDollar)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->numeric()
                    ->step(0.0001)
                    ->minValue(0)
                    ->required(),

                TextInput::make('sale_price')
                    ->label(__('resources.products.fields.sale_price'))
                    ->prefixIcon(Heroicon::CurrencyDollar)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->numeric()
                    ->step(0.0001)
                    ->minValue(0)
                    ->required(),

                TextInput::make('notes')
                    ->label(__('resources.products.fields.price_change_notes'))
                    ->prefixIcon(Heroicon::ChatBubbleBottomCenterText)
                    ->columnSpanFull()
                    ->maxLength(500),
            ])
            ->action(function (array $data, ProductVariant $record) {
                $persisted = false;

                DB::transaction(function () use ($data, $record, &$persisted) {
                    $variant = ProductVariant::lockForUpdate()->findOrFail($record->id);
                    $current = $variant->currentPrice;

                    // No-op guard: identical cost and sale price produces no new row.
                    if ($current
                        && bccomp((string) $current->cost_price, (string) $data['cost_price'], 4) === 0
                        && bccomp((string) $current->sale_price, (string) $data['sale_price'], 4) === 0) {
                        return;
                    }

                    if ($current) {
                        $current->update(['is_current' => false]);
                    }

                    ProductVariantPrice::create([
                        'product_variant_id' => $variant->id,
                        'cost_price'         => $data['cost_price'],
                        'sale_price'         => $data['sale_price'],
                        'effective_from'     => now(),
                        'is_current'         => true,
                        'set_by'             => auth()->id(),
                        'notes'              => $data['notes'] ?? null,
                    ]);

                    $persisted = true;
                });

                // The no-op guard above commits no row — notify only when a
                // new `ProductVariantPrice` row was actually persisted.
                if (! $persisted) {
                    return;
                }

                Notification::make()
                    ->title(__('resources.products.notifications.price_updated'))
                    ->success()
                    ->send();
            })
            ->successNotificationTitle(null);
    }
}
```

##### 7A.4.2 EditProductFamilyAction.php

```php
namespace App\Filament\Resources\Products\Actions;

use App\Models\ProductVariant;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class EditProductFamilyAction
{
    public static function make(): Action
    {
        return Action::make('editProductFamily')
            ->label(__('resources.products.actions.edit_family'))
            ->modalHeading(__('resources.products.actions.edit_family_heading'))
            ->modalDescription(__('resources.products.actions.edit_family_description'))
            ->icon(Heroicon::FolderOpen)
            ->modalWidth(Width::Large)
            ->authorize(function (ProductVariant $record): bool {
                // This action mutates the parent `Product`, not the variant —
                // so the `ProductPolicy::update` ability governs, not
                // `ProductVariantPolicy::update`.
                $product = $record->product;

                return $product
                    ? auth()->user()->can('update', $product)
                    : auth()->user()->can('update', $record);
            })
            ->fillForm(fn (ProductVariant $record) => [
                'family_name'     => $record->product?->name,
                'family_category' => $record->product?->category,
            ])
            ->schema([
                TextInput::make('family_name')
                    ->label(__('resources.products.fields.family_name'))
                    ->prefixIcon(Heroicon::Identification)
                    ->columnSpanFull()
                    ->required()
                    ->maxLength(255),

                TextInput::make('family_category')
                    ->label(__('resources.products.fields.family_category'))
                    ->prefixIcon(Heroicon::Tag)
                    ->columnSpanFull()
                    ->maxLength(255),
            ])
            ->action(function (array $data, ProductVariant $record) {
                $product = $record->product;

                if (! $product) {
                    Notification::make()
                        ->title(__('resources.products.notifications.family_missing'))
                        ->danger()
                        ->send();
                    return;
                }

                // Server-side re-check: UI visibility is not authorization.
                \Illuminate\Support\Facades\Gate::authorize('update', $product);

                $product->update([
                    'name'     => $data['family_name'],
                    'category' => $data['family_category'] ?: null,
                ]);

                Notification::make()
                    ->title(__('resources.products.notifications.family_updated'))
                    ->success()
                    ->send();
            });
    }
}
```

##### 7A.4.3 QuickStockAdjustmentAction.php

```php
namespace App\Filament\Resources\Products\Actions;

use App\Models\ProductVariant;
use App\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class QuickStockAdjustmentAction
{
    public static function make(): Action
    {
        return Action::make('quickStockAdjustment')
            ->label(__('resources.products.actions.quick_adjustment'))
            ->modalHeading(__('resources.products.actions.quick_adjustment_heading'))
            ->modalDescription(__('resources.products.actions.quick_adjustment_description'))
            ->icon(Heroicon::AdjustmentsHorizontal)
            ->color('warning')
            ->modalWidth(Width::Large)
            // Operational ability — ProductVariantPolicy::adjustStock (admin OR
            // assigned warehouse staff). Must NOT be 'update', which is
            // admin-only catalog management (§8.2).
            ->authorize('adjustStock')
            ->schema(fn (ProductVariant $record) => [
                Select::make('warehouse_id')
                    ->label(__('resources.products.fields.warehouse'))
                    ->prefixIcon(Heroicon::BuildingOffice2)
                    ->columnSpanFull()
                    // Scoped to assigned warehouses for every non-admin,
                    // non-auditor role — auditors are read-only and never
                    // adjust stock (ProductVariantPolicy::adjustStock denies
                    // them at `->authorize('adjustStock')` above, and
                    // InventoryService::adjustment() re-verifies at the
                    // service boundary in §6.2).
                    ->options(fn () => auth()->user()->isAdmin()
                        ? \App\Models\Warehouse::query()->pluck('name', 'id')
                        : auth()->user()->warehouses()->pluck('name', 'id'))
                    ->default(fn () => auth()->user()->warehouses()->count() === 1
                        ? auth()->user()->warehouses()->first()->id
                        : null)
                    ->required(),

                TextInput::make('signed_base_quantity')
                    ->label(__('resources.products.fields.signed_quantity_base'))
                    ->hintIcon(Heroicon::InformationCircle)
                    ->hint(__('resources.products.hints.signed_quantity'))
                    ->prefixIcon(Heroicon::Hashtag)
                    ->columnSpanFull()
                    ->numeric()
                    ->required(),

                Textarea::make('notes')
                    ->label(__('resources.products.fields.adjustment_notes'))
                    // ->prefixIcon(Heroicon::ChatBubbleBottomCenterText)
                    ->columnSpanFull()
                    ->required()
                    ->minLength(15),
            ])
            ->action(function (array $data, ProductVariant $record) {
                app(InventoryService::class)->adjustment(
                    productVariantId:  $record->id,
                    warehouseId:       (int) $data['warehouse_id'],
                    signedBaseQuantity: (int) $data['signed_base_quantity'],
                    notes:             (string) $data['notes'],
                );

                Notification::make()
                    ->title(__('resources.products.notifications.adjustment_recorded'))
                    ->success()
                    ->send();
            });
    }
}
```

##### 7A.4.4 ManageUnitConversionsAction.php

```php
namespace App\Filament\Resources\Products\Actions;

use App\Models\ProductVariant;
use App\Models\ProductVariantUnitConversion;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class ManageUnitConversionsAction
{
    public static function make(): Action
    {
        return Action::make('manageUnitConversions')
            ->label(__('resources.products.actions.manage_units'))
            ->modalHeading(__('resources.products.actions.manage_units_heading'))
            ->modalDescription(__('resources.products.actions.manage_units_description'))
            ->icon(Heroicon::Scale)
            ->modalWidth(Width::SevenExtraLarge)
            ->authorize('update')
            ->fillForm(fn (ProductVariant $record) => [
                'unitConversions' => $record->unitConversions
                    ->map->only(['unit_name', 'base_unit_ratio', 'is_default_purchase', 'is_default_transfer'])
                    ->toArray(),
            ])
            ->schema(fn (ProductVariant $record) => [
                // `$record` here is the page record (the parent
                // `ProductVariant`) auto-captured by the enclosing arrow
                // function (`fn` captures outer scope implicitly and
                // forbids a `use` clause) — never the
                // repeater item. Repeater-level delete guard: in Filament
                // v5, `Get $get` inside a `deleteAction()` closure resolves
                // against the repeater item's own schema container, so
                // `$get('unit_name')` is the item's unit name and the
                // comparison below correctly hides the delete button on the
                // variant's base-unit row. This is a UI hint only — the
                // authoritative guard is the `->action()` re-check below,
                // which skips the base-unit row both when deleting prior
                // rows and when recreating from incoming state, so the
                // base-unit self-conversion row (F19) cannot be removed
                // through this action regardless of UI state.
                Repeater::make('unitConversions')
                    ->schema([
                        TextInput::make('unit_name')
                            ->label(__('resources.products.fields.unit_name'))
                            ->required()
                            ->disabled(fn (Get $get): bool => $get('unit_name') === $record->base_unit_name),

                        TextInput::make('base_unit_ratio')
                            ->label(__('resources.products.fields.base_unit_ratio'))
                            ->numeric()
                            ->required()
                            ->disabled(fn (Get $get): bool => $get('unit_name') === $record->base_unit_name),

                        Toggle::make('is_default_purchase')
                            ->label(__('resources.products.fields.is_default_purchase')),

                        Toggle::make('is_default_transfer')
                            ->label(__('resources.products.fields.is_default_transfer')),
                    ])
                    ->columns(4)
                    // `Repeater::deleteAction()` receives the repeater's
                    // built-in delete button, which is the canonical
                    // Filament v5 unified `Filament\Actions\Action` — the
                    // same action class used by every record/header action
                    // in this blueprint (§7B.3, §18.2a).
                    ->deleteAction(
                        fn (Action $action) => $action->visible(
                            fn (Get $get): bool => $get('unit_name') !== $record->base_unit_name
                        ),
                    ),
            ])
            ->action(function (array $data, ProductVariant $record) {
                $baseName = $record->base_unit_name;
                $incoming = collect($data['unitConversions'] ?? []);

                // Authoritative guard: the base-unit self-conversion row
                // (F19) is identified via `isBaseUnitRow()` and is never
                // removed here. Conversions are loaded with `productVariant`
                // eager-loaded per the `isBaseUnitRow()` contract. For
                // well-formed data this deletes exactly the non-base rows.
                $record->unitConversions()->with('productVariant')->get()
                    ->reject(fn (ProductVariantUnitConversion $conversion): bool => $conversion->isBaseUnitRow())
                    ->each(fn (ProductVariantUnitConversion $conversion) => $conversion->delete());

                foreach ($incoming as $conv) {
                    if (($conv['unit_name'] ?? null) === $baseName) {
                        continue;
                    }

                    // Explicit allow-list: the repeater is unbound (no
                    // `->relationship()`), so only the four conversion
                    // attributes mirrored by `->fillForm()` travel forward.
                    // `product_variant_id` is never read from incoming state
                    // — it is injected by the `unitConversions()` HasMany
                    // relation on `create()`.
                    $record->unitConversions()->create(
                        collect($conv)->only(['unit_name', 'base_unit_ratio', 'is_default_purchase', 'is_default_transfer'])->toArray()
                    );
                }
            });
    }
}
```

> Ownership note: the thin `ProductResource` class lives only in §18.1a. This section (§7A) owns the `ProductForm` (§7A.1), `ProductsTable` (§7A.2), `ProductInfolist` (§7A.3), and Product Actions (§7A.4) contracts for the Products domain section.


---

### 7B. TransferRequisitionResource

**Model:** `App\Models\TransferRequisition` · **Group:** OPERATIONS · **Sort:** 1 · **Route:** `/admin/transfer-requisitions`

**Record title:** `reference_code` (matching `DirectTransferResource::$recordTitleAttribute`).

#### 7B.1 TransferRequisitionForm.php

```php
namespace App\Filament\Resources\TransferRequisitions\Schemas;

use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use App\Models\ProductVariant;
use App\Models\ProductVariantUnitConversion;
use App\Models\TransferRequisition;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class TransferRequisitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ...self::getRoutingFields(),
            ...self::getMaterialManifestFields(),
        ]);
    }

    public static function getRoutingFields(): array
    {
        return [
            Section::make(__('resources.transfer_requisitions.sections.routing'))
                ->icon(Heroicon::BuildingOffice)
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                ->schema([
                    Select::make('from_warehouse_id')
                        ->label(__('resources.transfer_requisitions.fields.from_warehouse'))
                        ->prefixIcon(Heroicon::BuildingOffice)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->options(fn () => auth()->user()->isAdmin() || auth()->user()->isAuditor()
                        ? \App\Models\Warehouse::query()->pluck('name', 'id')
                        : auth()->user()->warehouses()->pluck('name', 'id'))
                        ->default(fn () => auth()->user()->warehouses()->count() === 1
                            ? auth()->user()->warehouses()->first()->id
                            : null)
                        ->required(),

                    Select::make('to_warehouse_id')
                        ->label(__('resources.transfer_requisitions.fields.to_warehouse'))
                        ->prefixIcon(Heroicon::BuildingOffice2)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->options(fn () => auth()->user()->isAdmin() || auth()->user()->isAuditor()
                        ? \App\Models\Warehouse::query()->pluck('name', 'id')
                        : auth()->user()->warehouses()->pluck('name', 'id'))
                        ->required()
                        ->different('from_warehouse_id'),

                    Textarea::make('notes')
                        ->label(__('resources.transfer_requisitions.fields.notes'))
                        // ->prefixIcon(Heroicon::ChatBubbleBottomCenterText)
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function getMaterialManifestFields(): array
    {
        return [
            Repeater::make('items')
                ->relationship()
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                ->schema([
                    Select::make('product_variant_id')
                        ->label(__('resources.transfer_requisitions.fields.variant_sku'))
                        ->relationship('productVariant', 'sku')
                        ->prefixIcon(Heroicon::Tag)
                        ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                        ->live()
                        ->afterStateUpdated(function ($set) {
                            $set('requested_unit_name', null);
                            $set('requested_unit_ratio', null);
                        }),

                    Select::make('requested_unit_name')
                        ->label(__('resources.transfer_requisitions.fields.unit'))
                        ->prefixIcon(Heroicon::Scale)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->options(function (Get $get) {
                            $variantId = $get('product_variant_id');
                            if (! $variantId) {
                                return [];
                            }
                            return ProductVariantUnitConversion::where('product_variant_id', $variantId)
                                ->orderByDesc('base_unit_ratio')
                                ->pluck('unit_name', 'unit_name')
                                ->toArray();
                        })
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Get $get, $set, $state) {
                            $ratio = ProductVariantUnitConversion::where('product_variant_id', $get('product_variant_id'))
                                ->where('unit_name', $state)
                                ->value('base_unit_ratio');
                            $set('requested_unit_ratio', $ratio ?? 1);
                        }),

                    TextInput::make('requested_unit_ratio')
                        ->label(__('resources.transfer_requisitions.fields.ratio_base'))
                        ->hintIcon(Heroicon::InformationCircle)
                        ->hint(__('resources.transfer_requisitions.hints.ratio_auto'))
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->numeric()
                        ->disabled()
                        ->dehydrated()
                        ->required(),

                    TextInput::make('requested_qty')
                        ->label(__('resources.transfer_requisitions.fields.qty'))
                        ->prefixIcon(Heroicon::Hashtag)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                ])
                ->minItems(1)
                ->required()
                ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                    $data['requested_base_qty'] = (int) $data['requested_qty'] * (int) $data['requested_unit_ratio'];
                    return $data;
                })
                ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                    $data['requested_base_qty'] = (int) $data['requested_qty'] * (int) $data['requested_unit_ratio'];
                    return $data;
                }),
        ];
    }

    /**
     * Negotiation revision modal fields (Phase 09).
     *
     * Used by the `submitRevision` table action. The proposed unit is
     * sourced from the substitute variant's own conversion rows when a
     * substitute is chosen, otherwise from the item's original variant —
     * the ratio itself is re-resolved server-side by
     * `NegotiationService::submitRevision()`.
     *
     * @return array<int, mixed>
     */
    public static function getRevisionFields(TransferRequisition $requisition): array
    {
        // Eager-load every relation the option/afterStateUpdated closures
        // below traverse so each modal render issues a bounded set of queries.
        $requisition->loadMissing(['items.productVariant', 'items.revisions']);

        return [
            Select::make('transfer_requisition_item_id')
                ->label(__('resources.transfer_requisitions.fields.revision_item'))
                ->prefixIcon(Heroicon::ClipboardDocumentList)
                ->columnSpanFull()
                ->options(fn () => $requisition->items
                    ->mapWithKeys(fn ($item) => [
                        $item->id => $item->productVariant->sku,
                    ])
                    ->toArray())
                ->required()
                ->live(),

            Select::make('substitute_product_variant_id')
                ->label(__('resources.transfer_requisitions.fields.substitute_sku'))
                ->prefixIcon(Heroicon::Tag)
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->options(fn () => ProductVariant::query()
                    ->where('is_active', true)
                    ->orderBy('sku')
                    ->pluck('sku', 'id'))
                ->searchable()
                ->live()
                ->afterStateUpdated(function ($set) {
                    $set('proposed_unit_name', null);
                    $set('proposed_unit_ratio', null);
                }),

            Select::make('side')
                ->label(__('resources.transfer_requisitions.fields.negotiation_side'))
                ->prefixIcon(Heroicon::ChatBubbleLeftRight)
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->options(NegotiationSide::class)
                ->required(),

            Select::make('proposed_unit_name')
                ->label(__('resources.transfer_requisitions.fields.unit'))
                ->prefixIcon(Heroicon::Scale)
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->options(function (Get $get) use ($requisition) {
                    $item = $requisition->items->firstWhere(
                        'id',
                        (int) $get('transfer_requisition_item_id')
                    );
                    $variantId = $get('substitute_product_variant_id')
                        ?? $item?->product_variant_id;
                    if (! $variantId) {
                        return [];
                    }
                    return ProductVariantUnitConversion::where('product_variant_id', $variantId)
                        ->orderByDesc('base_unit_ratio')
                        ->pluck('unit_name', 'unit_name')
                        ->toArray();
                })
                ->required()
                ->live()
                ->afterStateUpdated(function (Get $get, $set, $state) use ($requisition) {
                    $item = $requisition->items->firstWhere(
                        'id',
                        (int) $get('transfer_requisition_item_id')
                    );
                    $variantId = $get('substitute_product_variant_id')
                        ?? $item?->product_variant_id;
                    $ratio = $variantId
                        ? ProductVariantUnitConversion::where('product_variant_id', $variantId)
                            ->where('unit_name', $state)
                            ->value('base_unit_ratio')
                        : null;
                    $set('proposed_unit_ratio', $ratio ?? 1);
                }),

            TextInput::make('proposed_unit_ratio')
                ->label(__('resources.transfer_requisitions.fields.ratio_base'))
                ->hintIcon(Heroicon::InformationCircle)
                ->hint(__('resources.transfer_requisitions.hints.ratio_auto'))
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->numeric()
                ->disabled()
                ->dehydrated()
                ->required(),

            TextInput::make('proposed_qty')
                ->label(__('resources.transfer_requisitions.fields.qty'))
                ->prefixIcon(Heroicon::Hashtag)
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->numeric()
                ->minValue(1)
                ->required(),

            Select::make('responds_to_revision_id')
                ->label(__('resources.transfer_requisitions.fields.responds_to'))
                ->prefixIcon(Heroicon::ChatBubbleLeftRight)
                ->columnSpanFull()
                ->options(function (Get $get) use ($requisition) {
                    $itemId = (int) $get('transfer_requisition_item_id');
                    if (! $itemId) {
                        return [];
                    }
                    $item = $requisition->items->firstWhere('id', $itemId);
                    if (! $item) {
                        return [];
                    }
                    return $item->revisions
                        ->where('status', RevisionStatus::Pending)
                        ->mapWithKeys(fn ($revision) => [
                            $revision->id => "{$revision->proposed_qty} {$revision->proposed_unit_name}",
                        ])
                        ->toArray();
                }),

            Textarea::make('negotiation_reason')
                ->label(__('resources.transfer_requisitions.fields.negotiation_reason'))
                // ->prefixIcon(Heroicon::ChatBubbleBottomCenterText)
                ->columnSpanFull(),
        ];
    }
}
```

**Note:** `reference_code` has no editable form field; it is system-generated as `TR-...` in `CreateTransferRequisition::mutateFormDataBeforeCreate()`.

#### 7B.2 CreateTransferRequisition.php

```php
namespace App\Filament\Resources\TransferRequisitions\Pages;

use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use App\Filament\Resources\TransferRequisitions\Schemas\TransferRequisitionForm;
use App\Support\GeneratesReferenceCodes;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class CreateTransferRequisition extends CreateRecord
{
    use HasWizard;

    protected static string $resource = TransferRequisitionResource::class;

    /**
     * @return array<Step>
     */
    protected function getSteps(): array
    {
        return [
            Step::make(__('resources.transfer_requisitions.steps.routing'))
                ->description(__('resources.transfer_requisitions.steps.routing_description'))
                ->icon(Heroicon::BuildingOffice)
                ->schema(TransferRequisitionForm::getRoutingFields()),

            Step::make(__('resources.transfer_requisitions.steps.manifest'))
                ->description(__('resources.transfer_requisitions.steps.manifest_description'))
                ->icon(Heroicon::ClipboardDocumentList)
                ->schema(TransferRequisitionForm::getMaterialManifestFields()),

            Step::make(__('resources.transfer_requisitions.steps.review'))
                ->description(__('resources.transfer_requisitions.steps.review_description'))
                ->icon(Heroicon::CheckCircle)
                ->schema([
                    View::make('filament.wizards.transfer-review')
                        ->viewData(fn (Get $get): array => [
                            'state' => [
                                'from_warehouse_id' => $get('from_warehouse_id'),
                                'to_warehouse_id' => $get('to_warehouse_id'),
                                'items' => $get('items') ?? [],
                                'notes' => $get('notes'),
                            ],
                        ])
                        ->columnSpanFull(),
                ]),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reference_code'] = $data['reference_code']
            ?? GeneratesReferenceCodes::generateReferenceCode('TR');
        $data['requested_by'] = auth()->id();
        $data['status'] = \App\Enums\TransferRequisitionStatus::Draft->value;
        return $data;
    }

    /**
     * Generate reference code with collision retry at the call site
     * (per §2: up to 5 attempts on unique-constraint violation, outside DB transaction).
     */
    protected function generateReferenceCodeWithRetry(string $prefix): string
    {
        $maxAttempts = 5;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $code = GeneratesReferenceCodes::generateReferenceCode($prefix);
            return $code;
        }
        throw new \DomainRuleViolationException('errors.reference_code_exhausted', [
            'prefix' => $prefix,
            'attempts' => $maxAttempts,
        ]);
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Mirror `CreateDirectTransfer` (§7C.2): explicitly authorize via
        // the policy before accepting wizard submission. UI visibility is
        // never the boundary; `->strictAuthorization()` (§17.5) is the
        // backstop, not the call site.
        Gate::authorize('create', \App\Models\TransferRequisition::class);

        return parent::handleRecordCreation($data);
    }

    public function getMaxContentWidth(): ?string
    {
        return Width::SevenExtraLarge->value;
    }
}
```

#### 7B.3 TransferRequisitionsTable.php — **Card Layout, No Bulk Actions**

```php
namespace App\Filament\Resources\TransferRequisitions\Tables;

use App\Enums\LossCategory;
use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use App\Enums\TransferRequisitionStatus;
use App\Filament\Resources\TransferRequisitions\Schemas\TransferRequisitionForm;
use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItemRevision;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class TransferRequisitionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('reference_code')
                            ->label(__('resources.transfer_requisitions.table.reference'))
                            ->fontFamily('mono')
                            ->weight(FontWeight::Bold)
                            ->searchable()
                            ->sortable()
                            ->copyable()
                            ->copyMessage(__('common.copied')),

                        TextColumn::make('status')
                            ->label(__('resources.transfer_requisitions.table.status'))
                            ->badge()
                            ->alignEnd()
                            ->sortable(),
                    ])->from('md'),

                    Split::make([
                        TextColumn::make('fromWarehouse.name')
                            ->label(__('resources.transfer_requisitions.table.from'))
                            ->icon(Heroicon::BuildingOffice)
                            ->iconColor('gray')
                            ->searchable(),

                        TextColumn::make('toWarehouse.name')
                            ->label(__('resources.transfer_requisitions.table.to'))
                            ->icon(Heroicon::BuildingOffice2)
                            ->iconColor('gray')
                            ->searchable(),
                    ])->from('md'),

                    Split::make([
                        TextColumn::make('items_count')
                            ->label(__('resources.transfer_requisitions.table.items'))
                            ->counts('items')
                            ->badge()
                            ->color('gray')
                            ->numeric(),

                        TextColumn::make('requestedBy.name')
                            ->label(__('resources.transfer_requisitions.table.requested_by'))
                            ->icon(Heroicon::User)
                            ->iconColor('gray')
                            ->placeholder(__('common.empty')),

                        TextColumn::make('requested_at')
                            ->label(__('resources.transfer_requisitions.table.requested'))
                            ->dateTime('M j, Y')
                            ->sortable()
                            ->placeholder(__('common.empty'))
                            ->alignEnd(),
                    ])->from('lg'),
                ])->space(3),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('resources.transfer_requisitions.filters.status'))
                    ->options(TransferRequisitionStatus::class),
                SelectFilter::make('from_warehouse_id')
                    ->label(__('resources.transfer_requisitions.filters.from_warehouse'))
                    ->relationship('fromWarehouse', 'name')
                    ->searchable(),
                SelectFilter::make('to_warehouse_id')
                    ->label(__('resources.transfer_requisitions.filters.to_warehouse'))
                    ->relationship('toWarehouse', 'name')
                    ->searchable(),
                TrashedFilter::make(),
                \App\Filament\Support\Filters\AdminReviewFilters::period('requested_at')
                    ->visible(fn (): bool => auth()->user()?->can('viewAuditFilters', \App\Models\TransferRequisition::class) ?? false),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(12)
            ->paginated([12, 24, 48])
            ->recordUrl(fn (TransferRequisition $record) => TransferRequisitionResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::Eye),

                EditAction::make()
                    ->icon(Heroicon::PencilSquare)
                    ->authorize('update')
                    ->visible(fn (TransferRequisition $record) => $record->status === TransferRequisitionStatus::Draft)
                    ->modalWidth(Width::Large),

                ActionGroup::make([
                    // ── Section: Submission ───────────────────────────────────
                    ActionGroup::make([
                        Action::make('submitRequest')
                            ->label(__('resources.transfer_requisitions.actions.submit'))
                            ->modalHeading(__('resources.transfer_requisitions.actions.submit_heading'))
                            ->modalDescription(__('resources.transfer_requisitions.actions.submit_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::PaperAirplane)
                            ->color('primary')
                            ->authorize('submitRequest')
                            ->visible(fn (TransferRequisition $record) => $record->status === TransferRequisitionStatus::Draft)
                            ->requiresConfirmation()
                            ->action(fn (TransferRequisition $record) => app(\App\Services\NegotiationService::class)->submitRequest($record)),
                    ])->dropdown(false),

                    // ── Section: Review & negotiation ─────────────────────────
                    ActionGroup::make([
                        Action::make('openReview')
                            ->label(__('resources.transfer_requisitions.actions.review'))
                            ->icon(Heroicon::ChatBubbleLeftRight)
                            ->color('warning')
                            ->authorize('negotiate')
                            ->visible(fn (TransferRequisition $record) => in_array($record->status, [
                                TransferRequisitionStatus::Requested,
                                TransferRequisitionStatus::UnderReviewFulfiller,
                                TransferRequisitionStatus::UnderReviewRequestor,
                            ], true))
                            // Negotiation states are NOT editable: TransferRequisitionPolicy::update
                            // permits Draft only, so this links to the view page (authorized via
                            // ::view for warehouse members) where the submitRevision / acceptRevision /
                            // rejectRevision modals live as ViewTransferRequisition::getHeaderActions()
                            // header actions (§18.2a) — never to the edit route, which would 403
                            // for these states.
                            ->url(fn (TransferRequisition $record) => TransferRequisitionResource::getUrl('view', ['record' => $record])),

                        Action::make('submitRevision')
                            ->label(__('resources.transfer_requisitions.actions.propose_revision'))
                            ->modalHeading(__('resources.transfer_requisitions.actions.propose_revision_heading'))
                            ->modalDescription(__('resources.transfer_requisitions.actions.propose_revision_description'))
                            ->icon(Heroicon::ChatBubbleLeftRight)
                            ->color('warning')
                            ->authorize('negotiate')
                            ->visible(fn (TransferRequisition $record) => in_array($record->status, [
                                TransferRequisitionStatus::Requested,
                                TransferRequisitionStatus::UnderReviewFulfiller,
                                TransferRequisitionStatus::UnderReviewRequestor,
                            ], true))
                            ->modalWidth(Width::FourExtraLarge)
                            ->schema(fn (TransferRequisition $record) => TransferRequisitionForm::getRevisionFields($record))
                            ->action(function (array $data, TransferRequisition $record) {
                                $item = $record->items()->findOrFail((int) $data['transfer_requisition_item_id']);

                                app(\App\Services\NegotiationService::class)->submitRevision(
                                    item: $item,
                                    substituteVariantId: $data['substitute_product_variant_id'] ?? null,
                                    side: NegotiationSide::from($data['side']),
                                    proposedUnitName: (string) $data['proposed_unit_name'],
                                    proposedQty: (int) $data['proposed_qty'],
                                    negotiationReason: $data['negotiation_reason'] ?? null,
                                    respondsToRevisionId: $data['responds_to_revision_id'] ?? null,
                                );

                                Notification::make()
                                    ->title(__('resources.transfer_requisitions.notifications.revision_submitted'))
                                    ->success()
                                    ->send();
                            }),

                        Action::make('acceptRevision')
                            ->label(__('resources.transfer_requisitions.actions.accept_revision'))
                            ->modalHeading(__('resources.transfer_requisitions.actions.accept_revision_heading'))
                            ->modalDescription(__('resources.transfer_requisitions.actions.accept_revision_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::CheckCircle)
                            ->color('success')
                            ->authorize('acceptRevision')
                            ->visible(fn (TransferRequisition $record) => in_array($record->status, [
                                TransferRequisitionStatus::Requested,
                                TransferRequisitionStatus::UnderReviewFulfiller,
                                TransferRequisitionStatus::UnderReviewRequestor,
                            ], true) && $record->items->flatMap(fn ($item) => $item->revisions)
                                ->contains(fn ($revision) => $revision->status === RevisionStatus::Pending))
                            ->schema(fn (TransferRequisition $record) => [
                                Select::make('revision_id')
                                    ->label(__('resources.transfer_requisitions.fields.revision'))
                                    ->prefixIcon(Heroicon::CheckCircle)
                                    ->columnSpanFull()
                                    ->options(fn () => $record->items
                                        ->flatMap(fn ($item) => $item->revisions
                                            ->where('status', RevisionStatus::Pending)
                                            ->mapWithKeys(fn ($revision) => [
                                                $revision->id => "{$item->productVariant->sku}: {$revision->proposed_qty} {$revision->proposed_unit_name}",
                                            ]))
                                        ->toArray())
                                    ->required(),
                            ])
                            ->action(function (array $data, TransferRequisition $record) {
                                $revision = TransferRequisitionItemRevision::query()
                                    ->findOrFail((int) $data['revision_id']);

                                abort_unless(
                                    (int) $revision->item->transfer_requisition_id === (int) $record->id,
                                    403,
                                );

                                app(\App\Services\NegotiationService::class)->accept($revision);

                                Notification::make()
                                    ->title(__('resources.transfer_requisitions.notifications.revision_accepted'))
                                    ->success()
                                    ->send();
                            })
                            ->requiresConfirmation(),

                        Action::make('rejectRevision')
                            ->label(__('resources.transfer_requisitions.actions.reject_revision'))
                            ->modalHeading(__('resources.transfer_requisitions.actions.reject_revision_heading'))
                            ->modalDescription(__('resources.transfer_requisitions.actions.reject_revision_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::XCircle)
                            ->color('danger')
                            ->authorize('rejectRevision')
                            ->visible(fn (TransferRequisition $record) => in_array($record->status, [
                                TransferRequisitionStatus::Requested,
                                TransferRequisitionStatus::UnderReviewFulfiller,
                                TransferRequisitionStatus::UnderReviewRequestor,
                            ], true) && $record->items->flatMap(fn ($item) => $item->revisions)
                                ->contains(fn ($revision) => $revision->status === RevisionStatus::Pending))
                            ->schema(fn (TransferRequisition $record) => [
                                Select::make('revision_id')
                                    ->label(__('resources.transfer_requisitions.fields.revision'))
                                    ->prefixIcon(Heroicon::XCircle)
                                    ->columnSpanFull()
                                    ->options(fn () => $record->items
                                        ->flatMap(fn ($item) => $item->revisions
                                            ->where('status', RevisionStatus::Pending)
                                            ->mapWithKeys(fn ($revision) => [
                                                $revision->id => "{$item->productVariant->sku}: {$revision->proposed_qty} {$revision->proposed_unit_name}",
                                            ]))
                                        ->toArray())
                                    ->required(),
                            ])
                            ->action(function (array $data, TransferRequisition $record) {
                                $revision = TransferRequisitionItemRevision::query()
                                    ->findOrFail((int) $data['revision_id']);

                                abort_unless(
                                    (int) $revision->item->transfer_requisition_id === (int) $record->id,
                                    403,
                                );

                                app(\App\Services\NegotiationService::class)->reject($revision);

                                Notification::make()
                                    ->title(__('resources.transfer_requisitions.notifications.revision_rejected'))
                                    ->success()
                                    ->send();
                            })
                            ->requiresConfirmation(),
                    ])->dropdown(false),

                    // ── Section: Fulfillment ──────────────────────────────────
                    ActionGroup::make([
                        Action::make('confirm')
                            ->label(__('resources.transfer_requisitions.actions.confirm'))
                            ->modalHeading(__('resources.transfer_requisitions.actions.confirm_heading'))
                            ->modalDescription(__('resources.transfer_requisitions.actions.confirm_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::CheckBadge)
                            ->color('primary')
                            ->authorize('confirm')
                            ->visible(fn (TransferRequisition $record) => in_array($record->status, [
                                TransferRequisitionStatus::Requested,
                                TransferRequisitionStatus::UnderReviewFulfiller,
                                TransferRequisitionStatus::UnderReviewRequestor,
                            ], true))
                            ->action(fn (TransferRequisition $record) => app(\App\Services\TransferRequisitionService::class)->confirm($record))
                            ->requiresConfirmation(),

                        Action::make('dispatch')
                            ->label(__('resources.transfer_requisitions.actions.dispatch'))
                            ->modalHeading(__('resources.transfer_requisitions.actions.dispatch_heading'))
                            ->modalDescription(__('resources.transfer_requisitions.actions.dispatch_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::Truck)
                            ->color('primary')
                            ->authorize('dispatch')
                            ->visible(fn (TransferRequisition $record) => $record->status === TransferRequisitionStatus::Confirmed)
                            ->action(fn (TransferRequisition $record) => app(\App\Services\InventoryService::class)->dispatchTransfer($record))
                            ->requiresConfirmation(),

                        Action::make('scanToReceive')
                            ->label(__('resources.transfer_requisitions.actions.receive'))
                            ->icon(Heroicon::QrCode)
                            ->color('success')
                            ->authorize('receive')
                            ->visible(fn (TransferRequisition $record) => in_array($record->status, [
                                TransferRequisitionStatus::Dispatched,
                                TransferRequisitionStatus::PartiallyReceived,
                            ], true))
                            ->url(fn (TransferRequisition $record) => \Illuminate\Support\Facades\URL::temporarySignedRoute(
                                'stn.scan',
                                now()->addDays(7),
                                ['transferRequisition' => $record->getKey()],
                            )),
                    ])->dropdown(false),

                    // ── Section: Loss & cancellation ──────────────────────────
                    ActionGroup::make([
                        Action::make('recordLoss')
                            ->label(__('resources.transfer_requisitions.actions.record_loss'))
                            ->modalHeading(__('resources.transfer_requisitions.actions.record_loss_heading'))
                            ->modalDescription(__('resources.transfer_requisitions.actions.record_loss_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::ExclamationTriangle)
                            ->color('danger')
                            ->authorize('recordLoss')
                            ->visible(fn (TransferRequisition $record) => in_array($record->status, [
                                TransferRequisitionStatus::Dispatched,
                                TransferRequisitionStatus::PartiallyReceived,
                            ], true))
                            ->modalWidth(Width::Large)
                            ->schema(fn (TransferRequisition $record) => [
                                Select::make('transfer_requisition_item_id')
                                    // Loss-modal item picker — loss/ledger label namespace,
                                    // never the revision-negotiation `revision_item` key.
                                    // Requires `resources.loss_ledgers.fields.transfer_requisition_item`
                                    // in the translation catalog (§0A.2a).
                                    ->label(__('resources.loss_ledgers.fields.transfer_requisition_item'))
                                    ->prefixIcon(Heroicon::ClipboardDocumentList)
                                    ->columnSpanFull()
                                    ->options(fn () => $record->items
                                        ->mapWithKeys(fn ($item) => [
                                            $item->id => $item->productVariant->sku,
                                        ])
                                        ->toArray())
                                    ->required(),

                                TextInput::make('lost_base_qty')
                                    ->label(__('resources.loss_ledgers.fields.lost_base'))
                                    ->prefixIcon(Heroicon::Hashtag)
                                    ->columnSpan(['default' => 1, 'md' => 1])
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->required(),

                                TextInput::make('damaged_base_qty')
                                    ->label(__('resources.loss_ledgers.fields.damaged_base'))
                                    ->prefixIcon(Heroicon::Hashtag)
                                    ->columnSpan(['default' => 1, 'md' => 1])
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->required(),

                                Select::make('loss_category')
                                    ->label(__('resources.loss_ledgers.fields.loss_category'))
                                    ->prefixIcon(Heroicon::ExclamationTriangle)
                                    ->columnSpanFull()
                                    ->options(LossCategory::class)
                                    ->required(),

                                Textarea::make('notes')
                                    ->label(__('resources.loss_ledgers.fields.notes'))
                                    // ->prefixIcon(Heroicon::ChatBubbleBottomCenterText)
                                    ->columnSpanFull(),
                            ])
                            ->action(function (array $data, TransferRequisition $record) {
                                app(\App\Services\InventoryService::class)->recordLoss(
                                    $record,
                                    $record->items()->findOrFail((int) $data['transfer_requisition_item_id']),
                                    (int) $data['lost_base_qty'],
                                    (int) $data['damaged_base_qty'],
                                    (string) $data['loss_category'],
                                    $data['notes'] ?? null,
                                );

                                Notification::make()
                                    ->title(__('resources.transfer_requisitions.notifications.loss_recorded'))
                                    ->success()
                                    ->send();
                            })
                            ->requiresConfirmation(),

                        Action::make('cancel')
                            ->label(__('resources.transfer_requisitions.actions.cancel'))
                            ->modalHeading(__('resources.transfer_requisitions.actions.cancel_heading'))
                            ->modalDescription(__('resources.transfer_requisitions.actions.cancel_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::XMark)
                            ->color('danger')
                            ->authorize('cancel')
                            ->visible(fn (TransferRequisition $record) => $record->canBeCancelled())
                            ->action(fn (TransferRequisition $record) => app(\App\Services\TransferRequisitionService::class)->cancelRequisition($record))
                            ->requiresConfirmation(),
                    ])->dropdown(false),

                    // ── Section: Destructive ──────────────────────────────────
                    ActionGroup::make([
                        DeleteAction::make()
                            ->icon(Heroicon::Trash)
                            ->authorize('delete')
                            ->visible(fn (TransferRequisition $record) => in_array($record->status, [
                                TransferRequisitionStatus::Draft,
                                TransferRequisitionStatus::Cancelled,
                            ], true)),

                        RestoreAction::make()
                            ->icon(Heroicon::ArrowUturnLeft)
                            ->authorize('restore'),

                        ForceDeleteAction::make()
                            ->icon(Heroicon::Trash)
                            ->authorize('forceDelete')
                            ->visible(fn () => auth()->user()->isAdmin()),
                    ])->dropdown(false),
                ])
                    ->icon(Heroicon::EllipsisVertical)
                    ->iconButton()
                    ->size(Size::Small)
                    ->color('gray')
                    ->tooltip(__('resources.transfer_requisitions.actions.more_actions'))
                    ->dropdownAutoPlacement()
                    ->dropdownWidth(Width::Large),
            ]);
    }
}
```

#### 7B.4 TransferRequisitionInfolist.php

```php
namespace App\Filament\Resources\TransferRequisitions\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class TransferRequisitionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])->schema([
                Section::make(__('resources.transfer_requisitions.infolist.profile'))
                    ->icon(Heroicon::DocumentText)
                    ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->schema([
                        TextEntry::make('reference_code')
                            ->label(__('resources.transfer_requisitions.fields.reference_code'))
                            ->weight(FontWeight::Bold)
                            ->size('lg')
                            ->copyable()
                            ->color('primary')
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),

                        TextEntry::make('status')
                            ->label(__('resources.transfer_requisitions.fields.status'))
                            ->badge()
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),

                        TextEntry::make('fromWarehouse.name')
                            ->label(__('resources.transfer_requisitions.fields.from_warehouse'))
                            ->icon(Heroicon::BuildingOffice)
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),

                        TextEntry::make('toWarehouse.name')
                            ->label(__('resources.transfer_requisitions.fields.to_warehouse'))
                            ->icon(Heroicon::BuildingOffice2)
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),

                        TextEntry::make('notes')
                            ->label(__('resources.transfer_requisitions.fields.notes'))
                            ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                            ->placeholder(__('common.empty')),
                    ]),

                Section::make(__('resources.transfer_requisitions.infolist.signoffs'))
                    ->icon(Heroicon::ShieldCheck)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->schema([
                        TextEntry::make('requestedBy.name')->label(__('resources.transfer_requisitions.fields.requested_by'))->icon(Heroicon::User)->placeholder(__('resources.transfer_requisitions.placeholders.system_initialized')),
                        TextEntry::make('approvedBy.name')->label(__('resources.transfer_requisitions.fields.approved_by'))->icon(Heroicon::Check)->placeholder(__('resources.transfer_requisitions.placeholders.pending_approval')),
                        TextEntry::make('dispatchedBy.name')->label(__('resources.transfer_requisitions.fields.dispatched_by'))->icon(Heroicon::Truck)->placeholder(__('resources.transfer_requisitions.placeholders.pending_dispatch')),
                        TextEntry::make('receivedBy.name')->label(__('resources.transfer_requisitions.fields.received_by'))->icon(Heroicon::QrCode)->placeholder(__('resources.transfer_requisitions.placeholders.pending_intake')),
                    ]),

                Section::make(__('resources.transfer_requisitions.infolist.manifest'))
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->table([
                                TableColumn::make(__('resources.transfer_requisitions.fields.original_sku')),
                                TableColumn::make(__('resources.transfer_requisitions.fields.substitute_sku')),
                                TableColumn::make(__('resources.transfer_requisitions.fields.requested')),
                                TableColumn::make(__('resources.transfer_requisitions.fields.approved')),
                                TableColumn::make(__('resources.transfer_requisitions.fields.shipped_base')),
                                TableColumn::make(__('resources.transfer_requisitions.fields.received_good_base')),
                            ])
                            ->schema([
                                TextEntry::make('productVariant.sku')
                                    ->weight(FontWeight::Bold),

                                TextEntry::make('substituteProductVariant.sku')
                                    ->badge()
                                    ->color('warning')
                                    ->placeholder(__('common.empty')),

                                TextEntry::make('requested_qty')
                                    ->state(fn ($record) => "{$record->requested_qty} {$record->requested_unit_name}"),

                                TextEntry::make('approved_qty')
                                    ->state(fn ($record) => $record->approved_qty
                                        ? "{$record->approved_qty} {$record->approved_unit_name}"
                                        : __('common.empty')),

                                TextEntry::make('shipped_base_qty')
                                    ->numeric()
                                    ->alignEnd(),

                                TextEntry::make('received_good_base_qty')
                                    ->numeric()
                                    ->alignEnd(),
                            ]),
                    ]),

                Section::make(__('resources.transfer_requisitions.infolist.negotiation_history'))
                    ->icon(Heroicon::ChatBubbleLeftRight)
                    ->columnSpanFull()
                    ->visible(fn ($record) => $record->items
                        ->flatMap(fn ($item) => $item->revisions)
                        ->isNotEmpty())
                    ->schema([
                        // Nested RepeatableEntry: one entry per item, each
                        // rendering its HasMany `revisions` (`items.revisions`
                        // and `items.revisions.user` are eager-loaded in
                        // `TransferRequisitionResource::getEloquentQuery()`).
                        // The outer `items` level stays a bare `->schema([...])`
                        // (its cell embeds the nested `revisions` table, which
                        // does not size well inside a table cell); the inner
                        // `revisions` level uses `->table([...])->schema([...])`
                        // like the flat line-item tables.
                        RepeatableEntry::make('items')
                            ->schema([
                                TextEntry::make('productVariant.sku')
                                    ->label(__('resources.transfer_requisitions.fields.original_sku'))
                                    ->weight(FontWeight::Bold),

                                RepeatableEntry::make('revisions')
                                    ->label(__('resources.transfer_requisitions.fields.revisions'))
                                    ->table([
                                        TableColumn::make(__('resources.transfer_requisitions.fields.negotiation_side')),
                                        TableColumn::make(__('resources.transfer_requisitions.fields.status')),
                                        TableColumn::make(__('resources.transfer_requisitions.fields.proposed')),
                                        TableColumn::make(__('resources.transfer_requisitions.fields.proposed_by')),
                                        TableColumn::make(__('resources.transfer_requisitions.fields.responded_at')),
                                        TableColumn::make(__('resources.transfer_requisitions.fields.negotiation_reason')),
                                    ])
                                    ->schema([
                                        TextEntry::make('side')->badge(),
                                        TextEntry::make('status')->badge(),
                                        TextEntry::make('proposed_qty')
                                            ->state(fn ($record) => "{$record->proposed_qty} {$record->proposed_unit_name}"),
                                        TextEntry::make('user.name')
                                            ->icon(Heroicon::User)
                                            ->placeholder(__('common.empty')),
                                        TextEntry::make('responded_at')
                                            ->dateTime('M j, Y H:i')
                                            ->placeholder(__('resources.transfer_requisitions.placeholders.pending_approval')),
                                        TextEntry::make('negotiation_reason')
                                            ->placeholder(__('common.empty')),
                                    ]),
                            ]),
                    ]),
            ]),
        ]);
    }
}
```

> Ownership note: the thin `TransferRequisitionResource` class lives only in §18.1a. This section (§7B) owns the `TransferRequisitionForm` (§7B.1), `CreateTransferRequisition` (§7B.2), `TransferRequisitionsTable` (§7B.3), and `TransferRequisitionInfolist` (§7B.4) contracts for the Transfer Requisitions domain section.

---

### 7C. DirectTransferResource

**Model:** `App\Models\DirectTransfer` · **Group:** OPERATIONS · **Sort:** 2 · **Route:** `/admin/direct-transfers`

#### 7C.1 DirectTransferForm.php

```php
namespace App\Filament\Resources\DirectTransfers\Schemas;

use App\Models\ProductVariant;
use App\Models\ProductVariantUnitConversion;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class DirectTransferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ...self::getLocationMappingFields(),
            ...self::getStockAllocationFields(),
        ]);
    }

    public static function getLocationMappingFields(): array
    {
        return [
            Section::make(__('resources.direct_transfers.sections.routing'))
                ->icon(Heroicon::BuildingOffice)
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                ->schema([
                    Select::make('from_warehouse_id')
                        ->label(__('resources.direct_transfers.fields.from_warehouse'))
                        ->prefixIcon(Heroicon::BuildingOffice)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->options(fn () => auth()->user()->isAdmin() || auth()->user()->isAuditor()
                        ? \App\Models\Warehouse::query()->pluck('name', 'id')
                        : auth()->user()->warehouses()->pluck('name', 'id'))
                        ->default(fn () => auth()->user()->warehouses()->count() === 1
                            ? auth()->user()->warehouses()->first()->id
                            : null)
                        ->required(),

                    Select::make('to_warehouse_id')
                        ->label(__('resources.direct_transfers.fields.to_warehouse'))
                        ->prefixIcon(Heroicon::BuildingOffice2)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->options(fn () => auth()->user()->isAdmin() || auth()->user()->isAuditor()
                        ? \App\Models\Warehouse::query()->pluck('name', 'id')
                        : auth()->user()->warehouses()->pluck('name', 'id'))
                        ->required()
                        ->different('from_warehouse_id'),
                ]),
        ];
    }

    public static function getStockAllocationFields(): array
    {
        return [
            Section::make(__('resources.direct_transfers.sections.stock_allocation'))
                ->icon(Heroicon::Cube)
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                ->schema([
                    Repeater::make('items')
                        ->columnSpanFull()
                        ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                        ->schema([
                            Select::make('product_variant_id')
                                ->label(__('resources.direct_transfers.fields.variant_sku'))
                                ->prefixIcon(Heroicon::Tag)
                                ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                                ->options(fn () => ProductVariant::query()
                                    ->where('is_active', true)
                                    ->orderBy('sku')
                                    ->pluck('sku', 'id'))
                                ->searchable()
                                ->required()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->live()
                                ->afterStateUpdated(function ($set) {
                                    $set('unit_name', null);
                                    $set('unit_ratio', null);
                                }),

                            Select::make('unit_name')
                                ->label(__('resources.direct_transfers.fields.unit'))
                                ->prefixIcon(Heroicon::Scale)
                                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                                ->options(function (Get $get) {
                                    $variantId = $get('product_variant_id');
                                    if (! $variantId) {
                                        return [];
                                    }
                                    return ProductVariantUnitConversion::where('product_variant_id', $variantId)
                                        ->orderByDesc('base_unit_ratio')
                                        ->pluck('unit_name', 'unit_name')
                                        ->toArray();
                                })
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Get $get, $set, $state) {
                                    $ratio = ProductVariantUnitConversion::where('product_variant_id', $get('product_variant_id'))
                                        ->where('unit_name', $state)
                                        ->value('base_unit_ratio');
                                    $set('unit_ratio', $ratio ?? 1);
                                }),

                            TextInput::make('unit_ratio')
                                ->label(__('resources.direct_transfers.fields.ratio_base'))
                                ->hintIcon(Heroicon::InformationCircle)
                                ->hint(__('resources.direct_transfers.hints.ratio_auto'))
                                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                                ->numeric()
                                ->disabled()
                                ->dehydrated()
                                ->required(),

                            TextInput::make('qty')
                                ->label(__('resources.direct_transfers.fields.qty'))
                                ->prefixIcon(Heroicon::Hashtag)
                                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                                ->numeric()
                                ->minValue(1)
                                ->required(),
                        ])
                        ->minItems(1)
                        ->required(),

                    Textarea::make('notes')
                        ->label(__('resources.direct_transfers.fields.notes'))
                        // ->prefixIcon(Heroicon::ChatBubbleBottomCenterText)
                        ->columnSpanFull()
                        ->required()
                        ->minLength(15),
                ]),
        ];
    }
}
```

**Note:** `reference_code` has no editable form field; it is system-generated as `DT-...` in `CreateDirectTransfer::handleRecordCreation()`.

#### 7C.2 CreateDirectTransfer.php

```php
namespace App\Filament\Resources\DirectTransfers\Pages;

use App\Filament\Resources\DirectTransfers\DirectTransferResource;
use App\Filament\Resources\DirectTransfers\Schemas\DirectTransferForm;
use App\Support\GeneratesReferenceCodes;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class CreateDirectTransfer extends CreateRecord
{
    use HasWizard;

    protected static string $resource = DirectTransferResource::class;

    /**
     * @return array<Step>
     */
    protected function getSteps(): array
    {
        return [
            Step::make(__('resources.direct_transfers.steps.location_mapping'))
                ->description(__('resources.direct_transfers.steps.location_mapping_description'))
                ->icon(Heroicon::BuildingOffice)
                ->schema(DirectTransferForm::getLocationMappingFields()),

            Step::make(__('resources.direct_transfers.steps.stock_allocation'))
                ->description(__('resources.direct_transfers.steps.stock_allocation_description'))
                ->icon(Heroicon::Cube)
                ->schema(DirectTransferForm::getStockAllocationFields()),

            Step::make(__('resources.direct_transfers.steps.review_verify'))
                ->description(__('resources.direct_transfers.steps.review_verify_description'))
                ->icon(Heroicon::CheckCircle)
                ->schema([
                    View::make('filament.wizards.direct-transfer-review')
                        ->viewData(fn (Get $get): array => [
                            'state' => [
                                'from_warehouse_id' => $get('from_warehouse_id'),
                                'to_warehouse_id' => $get('to_warehouse_id'),
                                'items' => $get('items') ?? [],
                                'notes' => $get('notes'),
                            ],
                        ])
                        ->columnSpanFull(),
                ]),
        ];
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // §20.3 — the Create page explicitly authorizes via the policy before
        // accepting wizard submission. UI visibility is never the boundary;
        // the service re-verifies both warehouse endpoints (§6.2).
        Gate::authorize('create', \App\Models\DirectTransfer::class);

        $referenceCode = $data['reference_code']
            ?? $this->generateReferenceCodeWithRetry('DT');

        return app(\App\Services\InventoryService::class)->directTransfer(
            fromWarehouseId: (int) $data['from_warehouse_id'],
            toWarehouseId:   (int) $data['to_warehouse_id'],
            items:           $data['items'] ?? [],
            referenceCode:   $referenceCode,
            notes:           $data['notes'],
        );
    }

    /**
     * Generate reference code with collision retry at the call site
     * (per §2: up to 5 attempts on unique-constraint violation, outside DB transaction).
     */
    protected function generateReferenceCodeWithRetry(string $prefix): string
    {
        $maxAttempts = 5;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $code = GeneratesReferenceCodes::generateReferenceCode($prefix);
            return $code;
        }
        throw new \DomainRuleViolationException('errors.reference_code_exhausted', [
            'prefix' => $prefix,
            'attempts' => $maxAttempts,
        ]);
    }

    public function getMaxContentWidth(): ?string
    {
        return Width::SevenExtraLarge->value;
    }
}
```

#### 7C.3 ViewDirectTransfer.php

```php
namespace App\Filament\Resources\DirectTransfers\Pages;

use App\Filament\Resources\DirectTransfers\DirectTransferResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDirectTransfer extends ViewRecord
{
    protected static string $resource = DirectTransferResource::class;
}
```

#### 7C.4 DirectTransfersTable.php — **Card Layout, No Bulk Actions**

```php
namespace App\Filament\Resources\DirectTransfers\Tables;

use App\Filament\Resources\DirectTransfers\DirectTransferResource;
use App\Models\DirectTransfer;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DirectTransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('reference_code')
                            ->label(__('resources.direct_transfers.table.reference'))
                            ->fontFamily('mono')
                            ->weight(FontWeight::Bold)
                            ->searchable()
                            ->sortable()
                            ->copyable(),

                        TextColumn::make('transferred_at')
                            ->label(__('resources.direct_transfers.table.transferred_at'))
                            ->dateTime('M j, Y H:i')
                            ->sortable()
                            ->alignEnd(),
                    ])->from('md'),

                    Split::make([
                        TextColumn::make('fromWarehouse.name')
                            ->label(__('resources.direct_transfers.table.from'))
                            ->icon(Heroicon::BuildingOffice)
                            ->iconColor('gray'),

                        TextColumn::make('toWarehouse.name')
                            ->label(__('resources.direct_transfers.table.to'))
                            ->icon(Heroicon::BuildingOffice2)
                            ->iconColor('gray'),
                    ])->from('md'),

                    Split::make([
                        TextColumn::make('items_count')
                            ->label(__('resources.direct_transfers.table.items'))
                            ->counts('items')
                            ->badge()
                            ->color('gray')
                            ->numeric(),

                        TextColumn::make('transferredBy.name')
                            ->label(__('resources.direct_transfers.table.by'))
                            ->icon(Heroicon::User)
                            ->iconColor('gray')
                            ->placeholder(__('common.empty'))
                            ->alignEnd(),
                    ])->from('lg'),
                ])->space(3),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->filters([
                SelectFilter::make('from_warehouse_id')
                    ->label(__('resources.direct_transfers.filters.from_warehouse'))
                    ->relationship('fromWarehouse', 'name')
                    ->searchable(),

                SelectFilter::make('to_warehouse_id')
                    ->label(__('resources.direct_transfers.filters.to_warehouse'))
                    ->relationship('toWarehouse', 'name')
                    ->searchable(),

                \App\Filament\Support\Filters\AdminReviewFilters::period('transferred_at')
                    ->visible(fn (): bool => auth()->user()?->can('viewAuditFilters', \App\Models\DirectTransfer::class) ?? false),
            ])
            ->defaultSort('transferred_at', 'desc')
            ->defaultPaginationPageOption(12)
            ->paginated([12, 24, 48])
            ->recordUrl(fn (DirectTransfer $r) => DirectTransferResource::getUrl('view', ['record' => $r]))
            ->recordActions([
                ViewAction::make()->icon(Heroicon::Eye),
            ]);
    }
}
```

#### 7C.5 DirectTransferInfolist.php

```php
namespace App\Filament\Resources\DirectTransfers\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class DirectTransferInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])->schema([
                Section::make(__('resources.direct_transfers.infolist.profile'))
                    ->icon(Heroicon::ArrowPath)
                    ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->schema([
                        TextEntry::make('reference_code')
                            ->label(__('resources.direct_transfers.fields.reference_code'))
                            ->weight(FontWeight::Bold)
                            ->size('lg')
                            ->copyable()
                            ->color('primary'),

                        TextEntry::make('transferred_at')
                            ->label(__('resources.direct_transfers.fields.transferred_at'))
                            ->dateTime('M j, Y H:i'),

                        TextEntry::make('fromWarehouse.name')
                            ->label(__('resources.direct_transfers.fields.from_warehouse'))
                            ->icon(Heroicon::BuildingOffice),

                        TextEntry::make('toWarehouse.name')
                            ->label(__('resources.direct_transfers.fields.to_warehouse'))
                            ->icon(Heroicon::BuildingOffice2),

                        TextEntry::make('notes')
                            ->label(__('resources.direct_transfers.fields.notes'))
                            ->columnSpanFull()
                            ->placeholder(__('common.empty')),
                    ]),

                Section::make(__('resources.direct_transfers.infolist.authorization'))
                    ->icon(Heroicon::ShieldCheck)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->schema([
                        TextEntry::make('transferredBy.name')
                            ->label(__('resources.direct_transfers.fields.transferred_by'))
                            ->icon(Heroicon::User)
                            ->placeholder(__('common.empty')),
                    ]),

                Section::make(__('resources.direct_transfers.infolist.manifest'))
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->table([
                                TableColumn::make(__('resources.direct_transfers.fields.sku')),
                                TableColumn::make(__('resources.direct_transfers.fields.qty')),
                                TableColumn::make(__('resources.direct_transfers.fields.ratio')),
                                TableColumn::make(__('resources.direct_transfers.fields.base_qty')),
                                TableColumn::make(__('resources.direct_transfers.fields.notes')),
                            ])
                            ->schema([
                                TextEntry::make('productVariant.sku')
                                    ->weight(FontWeight::Bold),

                                TextEntry::make('qty')
                                    ->state(fn ($record) => "{$record->qty} {$record->unit_name}"),

                                TextEntry::make('unit_ratio')
                                    ->numeric()
                                    ->alignEnd(),

                                TextEntry::make('base_qty')
                                    ->numeric()
                                    ->alignEnd(),

                                TextEntry::make('notes')
                                    ->placeholder(__('common.empty')),
                            ]),
                    ]),
            ]),
        ]);
    }
}
```

#### 7C.6 DirectTransferResource.php

```php
namespace App\Filament\Resources\DirectTransfers;

use App\Filament\Resources\DirectTransfers\Pages\CreateDirectTransfer;
use App\Filament\Resources\DirectTransfers\Pages\ListDirectTransfers;
use App\Filament\Resources\DirectTransfers\Pages\ViewDirectTransfer;
use App\Filament\Resources\DirectTransfers\Schemas\DirectTransferForm;
use App\Filament\Resources\DirectTransfers\Schemas\DirectTransferInfolist;
use App\Filament\Resources\DirectTransfers\Tables\DirectTransfersTable;
use App\Models\DirectTransfer;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DirectTransferResource extends Resource
{
    protected static ?string $model = DirectTransfer::class;
    protected static string | \UnitEnum | null $navigationGroup = 'OPERATIONS';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'reference_code';
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedArrowPath;
    protected static string | \BackedEnum | null $activeNavigationIcon = Heroicon::ArrowPath;

    public static function getModelLabel(): string
    {
        return __('resources.direct_transfers.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.direct_transfers.model.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.direct_transfers.navigation.label');
    }

    public static function form(Schema $schema): Schema
    {
        return DirectTransferForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DirectTransfersTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DirectTransferInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['fromWarehouse', 'toWarehouse', 'transferredBy', 'items.productVariant'])
            ->withCount('items')
            ->when(
                ! auth()->user()->isAdmin() && ! auth()->user()->isAuditor(),
                function (Builder $q) {
                    // Intentional AND-scope (§20.1): a direct transfer moves
                    // stock between two warehouses, so a non-privileged user
                    // must be assigned to BOTH endpoints to list it. A
                    // single-warehouse user therefore sees zero direct
                    // transfers by design — not a bug.
                    $ids = auth()->user()->warehouses()->pluck('id')->all();
                    $q->whereIn('from_warehouse_id', $ids)
                      ->whereIn('to_warehouse_id', $ids);
                }
            );
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListDirectTransfers::route('/'),
            'create' => CreateDirectTransfer::route('/create'),
            'view'   => ViewDirectTransfer::route('/{record}'),
        ];
    }
}
```

> Single source of truth: `§7C` owns the `DirectTransferResource` definition for the Direct Transfers domain section. §18.1a records only the file contract and references this section — the class body is not repeated there.

---

### 7D. InTransitResource

**Model:** `App\Models\InTransit` · **Group:** OPERATIONS · **Sort:** 3

**Record title:** `$recordTitleAttribute = null` — `InTransit` has no own code column; rows are identified via `transferRequisition.reference_code` plus variant SKU (already the first table columns). **Owner decision (recorded):** keep `null`, no schema change; revisit only if global search over in-transit rows is required, which would need a stored/generated column and a new schema decision.

#### 7D.1 InTransitInfolist.php

```php
namespace App\Filament\Resources\InTransits\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class InTransitInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])->schema([
                Section::make(__('resources.in_transits.infolist.cargo'))
                    ->icon(Heroicon::Truck)
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'md' => 3, 'xl' => 3])
                    ->schema([
                        TextEntry::make('transferRequisition.reference_code')
                            ->label(__('resources.in_transits.fields.requisition'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('productVariant.sku')
                            ->label(__('resources.in_transits.fields.sku'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('dispatched_base_qty')
                            ->label(__('resources.in_transits.fields.dispatched_base'))
                            ->numeric()
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('dispatched_at')
                            ->label(__('resources.in_transits.fields.dispatched_at'))
                            ->dateTime('M j, Y H:i')
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('status')
                            ->label(__('resources.in_transits.fields.status'))
                            ->badge()
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('cleared_at')
                            ->label(__('resources.in_transits.fields.cleared_at'))
                            ->dateTime('M j, Y H:i')
                            ->placeholder(__('common.empty'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                    ]),
            ]),
        ]);
    }
}
```

#### 7D.2 InTransitsTable.php — **Standard Table + `stackedOnMobile()`**

```php
namespace App\Filament\Resources\InTransits\Tables;

use App\Enums\InTransitStatus;
use App\Filament\Resources\InTransits\InTransitResource;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InTransitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transferRequisition.reference_code')
                    ->label(__('resources.in_transits.table.requisition'))
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('productVariant.sku')
                    ->label(__('resources.in_transits.table.sku'))
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('dispatched_base_qty')
                    ->label(__('resources.in_transits.table.dispatched'))
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('dispatched_at')
                    ->label(__('resources.in_transits.table.dispatched_at'))
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->visibleFrom('md'),

                TextColumn::make('status')
                    ->label(__('resources.in_transits.table.status'))
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                // `SelectFilter::options()` accepts any `HasLabel` enum —
                // `HasColor` is not required. `InTransitStatus` labels resolve
                // through `__()` (§4.7), so the filter renders translated
                // options without further configuration.
                SelectFilter::make('status')
                    ->label(__('resources.in_transits.filters.status'))
                    ->options(InTransitStatus::class),
            ])
            ->defaultSort('dispatched_at', 'desc')
            ->stackedOnMobile()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->recordUrl(fn ($record) => InTransitResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()->icon(Heroicon::Eye),
            ]);
    }
}
```

> Ownership note: the thin `InTransitResource` class lives only in §18.1a. This section (§7D) owns the `InTransitInfolist` (§7D.1) and `InTransitsTable` (§7D.2) contracts for the In-Transit domain section.

---

### 7E. StockMovementResource

**Model:** `App\Models\StockMovement` · **Group:** AUDIT LEDGERS · **Sort:** 1

#### 7E.1 StockMovementInfolist.php

```php
namespace App\Filament\Resources\StockMovements\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class StockMovementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'md' => 2, 'xl' => 2])->schema([
                Section::make(__('resources.stock_movements.infolist.movement'))
                    ->icon(Heroicon::QueueList)
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->schema([
                        TextEntry::make('created_at')->label(__('resources.stock_movements.fields.timestamp'))->dateTime('M j, Y H:i')
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('type')->badge()
                            ->label(__('resources.stock_movements.fields.type'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('productVariant.sku')->label(__('resources.stock_movements.fields.sku'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('warehouse.name')->label(__('resources.stock_movements.fields.warehouse'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('quantity')->numeric()
                            ->label(__('resources.stock_movements.fields.quantity'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('unit_name_used')->label(__('resources.stock_movements.fields.unit'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('reference_code')->label(__('resources.stock_movements.fields.reference_code'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('createdBy.name')->label(__('resources.stock_movements.fields.by'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('notes')->columnSpanFull()
                            ->label(__('resources.stock_movements.fields.notes')),
                    ]),
            ]),
        ]);
    }
}
```

#### 7E.2 StockMovementsTable.php — **Standard Table + `stackedOnMobile()`**

```php
namespace App\Filament\Resources\StockMovements\Tables;

use App\Enums\StockMovementType;
use App\Filament\Resources\StockMovements\StockMovementResource;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('resources.stock_movements.table.timestamp'))
                    ->dateTime('M j, Y H:i')
                    ->sortable(),

                TextColumn::make('productVariant.sku')
                    ->label(__('resources.stock_movements.table.sku'))
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.code')
                    // Ledger tables show the warehouse `code` for density; all other resources show `warehouse.name`.
                    ->label(__('resources.stock_movements.table.warehouse'))
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->visibleFrom('md'),

                TextColumn::make('type')
                    ->label(__('resources.stock_movements.table.type'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('quantity')
                    ->label(__('resources.stock_movements.table.qty'))
                    ->numeric()
                    ->alignEnd()
                    ->sortable()
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->weight('bold'),

                TextColumn::make('unit_name_used')
                    ->label(__('resources.stock_movements.table.unit'))
                    ->state(fn ($record) => $record->unit_ratio_used > 1
                        ? "{$record->unit_name_used} (×{$record->unit_ratio_used})"
                        : $record->unit_name_used)
                    ->visibleFrom('lg'),

                TextColumn::make('reference_code')
                    ->label(__('resources.stock_movements.table.reference_code'))
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable()
                    ->visibleFrom('md'),

                TextColumn::make('createdBy.name')
                    ->label(__('resources.stock_movements.table.by'))
                    ->visibleFrom('xl'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('resources.stock_movements.filters.type'))
                    ->options(StockMovementType::class),
                \App\Filament\Support\Filters\AdminReviewFilters::warehouse()
                    ->label(__('resources.stock_movements.filters.warehouse')),
                SelectFilter::make('product_variant_id')
                    ->label(__('resources.stock_movements.filters.variant'))
                    ->relationship('productVariant', 'sku')
                    ->searchable(),
                \App\Filament\Support\Filters\AdminReviewFilters::period('created_at')
                    ->visible(fn (): bool => auth()->user()?->can('viewAuditFilters', \App\Models\StockMovement::class) ?? false),
            ])
            ->defaultSort('created_at', 'desc')
            ->stackedOnMobile()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->recordUrl(fn ($record) => StockMovementResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()->icon(Heroicon::Eye),
            ]);
    }
}
```

> Ownership note: the thin `StockMovementResource` class lives only in §18.1a. This section (§7E) owns the `StockMovementInfolist` (§7E.1) and `StockMovementsTable` (§7E.2) contracts for the Stock Movements domain section.

---

### 7F. LossLedgerResource

**Model:** `App\Models\LossLedger` · **Group:** AUDIT LEDGERS · **Sort:** 2

#### 7F.1 LossLedgerInfolist.php

```php
namespace App\Filament\Resources\LossLedgers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class LossLedgerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'md' => 2, 'xl' => 2])->schema([
                Section::make(__('resources.loss_ledgers.infolist.record'))
                    ->icon(Heroicon::ExclamationTriangle)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->schema([
                        TextEntry::make('recorded_at')->label(__('resources.loss_ledgers.fields.recorded_at'))->dateTime('M j, Y H:i')->columnSpanFull(),
                        TextEntry::make('transferRequisition.reference_code')->label(__('resources.loss_ledgers.fields.requisition'))->columnSpanFull(),
                        TextEntry::make('productVariant.sku')->label(__('resources.loss_ledgers.fields.sku'))->columnSpanFull(),
                        TextEntry::make('warehouse.name')->label(__('resources.loss_ledgers.fields.warehouse'))->columnSpanFull(),
                        TextEntry::make('loss_category')->label(__('resources.loss_ledgers.fields.loss_category'))->badge()->columnSpanFull(),
                    ]),

                Section::make(__('resources.loss_ledgers.infolist.financial_impact'))
                    ->icon(Heroicon::CurrencyDollar)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->schema([
                        TextEntry::make('lost_base_qty')->label(__('resources.loss_ledgers.fields.lost_base'))->numeric()->columnSpanFull(),
                        TextEntry::make('damaged_base_qty')->label(__('resources.loss_ledgers.fields.damaged_base'))->numeric()->columnSpanFull(),
                        TextEntry::make('unit_cost_price')
                            ->label(__('resources.loss_ledgers.fields.unit_cost_price'))
                            ->formatStateUsing(fn ($state): string => format_money($state))->columnSpanFull(),
                        TextEntry::make('total_financial_loss')
                            ->label(__('resources.loss_ledgers.fields.total_financial_loss'))
                            ->formatStateUsing(fn ($state): string => format_money($state))
                            ->weight('bold')->columnSpanFull(),
                    ]),
            ]),
        ]);
    }
}
```

#### 7F.2 LossLedgersTable.php — **Standard Table + `stackedOnMobile()`**

```php
namespace App\Filament\Resources\LossLedgers\Tables;

use App\Enums\LossCategory;
use App\Filament\Resources\LossLedgers\LossLedgerResource;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LossLedgersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('recorded_at')
                    ->label(__('resources.loss_ledgers.table.recorded'))
                    ->dateTime('M j, Y H:i')
                    ->sortable(),

                TextColumn::make('transferRequisition.reference_code')
                    ->label(__('resources.loss_ledgers.table.requisition'))
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('productVariant.sku')
                    ->label(__('resources.loss_ledgers.table.sku'))
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.code')
                    ->label(__('resources.loss_ledgers.table.warehouse'))
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->visibleFrom('md'),

                TextColumn::make('lost_base_qty')
                    ->label(__('resources.loss_ledgers.table.lost'))
                    ->numeric()->alignEnd()->color('warning'),

                TextColumn::make('damaged_base_qty')
                    ->label(__('resources.loss_ledgers.table.damaged'))
                    ->numeric()->alignEnd()->color('danger'),

                TextColumn::make('loss_category')
                    ->label(__('resources.loss_ledgers.table.category'))
                    ->badge()
                    ->visibleFrom('md'),

                TextColumn::make('unit_cost_price')
                    ->label(__('resources.loss_ledgers.table.unit_cost'))
                    ->formatStateUsing(fn ($state): string => format_money($state))
                    ->visibleFrom('lg'),

                TextColumn::make('total_financial_loss')
                    ->label(__('resources.loss_ledgers.table.total_loss'))
                    ->formatStateUsing(fn ($state): string => format_money($state))
                    ->weight('bold')
                    ->alignEnd()
                    ->summarize(
                        Sum::make()
                            ->formatStateUsing(fn ($state): string => format_money($state))
                    ),

                TextColumn::make('recordedBy.name')
                    ->label(__('resources.loss_ledgers.table.by'))
                    ->visibleFrom('xl'),
            ])
            ->filters([
                // `SelectFilter::options()` accepts any `HasLabel` enum —
                // `HasColor` is not required. `LossCategory` labels resolve
                // through `__()` (§4.9), so the filter renders translated
                // options without further configuration.
                SelectFilter::make('loss_category')
                    ->label(__('resources.loss_ledgers.filters.loss_category'))
                    ->options(LossCategory::class),
                \App\Filament\Support\Filters\AdminReviewFilters::warehouse()
                    ->label(__('resources.loss_ledgers.filters.warehouse')),
                \App\Filament\Support\Filters\AdminReviewFilters::period('recorded_at')
                    ->visible(fn (): bool => auth()->user()?->can('viewAuditFilters', \App\Models\LossLedger::class) ?? false),
            ])
            ->defaultSort('recorded_at', 'desc')
            ->stackedOnMobile()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->recordUrl(fn ($record) => LossLedgerResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()->icon(Heroicon::Eye),
            ]);
    }
}
```

> Ownership note: the thin `LossLedgerResource` class lives only in §18.1a. This section (§7F) owns the `LossLedgerInfolist` (§7F.1) and `LossLedgersTable` (§7F.2) contracts for the Loss Ledgers domain section.

---

### 7G. PurchaseOrderResource

**Model:** `App\Models\PurchaseOrder` · **Group:** PURCHASING · **Sort:** 1

**Record title:** `reference_code` (matching `DirectTransferResource::$recordTitleAttribute`).

#### 7G.1 PurchaseOrderForm.php

```php
namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Models\ProductVariantUnitConversion;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ...self::getSupplierWarehouseFields(),
            ...self::getLineItemsFields(),
            ...self::getReviewFields(),
        ]);
    }

    public static function getSupplierWarehouseFields(): array
    {
        return [
            Section::make(__('resources.purchase_orders.form.supplier_warehouse'))
                ->icon(Heroicon::BuildingStorefront)
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                ->schema([
                    Select::make('supplier_id')
                        ->label(__('resources.purchase_orders.fields.supplier'))
                        ->relationship('supplier', 'name')
                        ->prefixIcon(Heroicon::BuildingStorefront)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm(fn (Schema $schema) => \App\Filament\Resources\Suppliers\Schemas\SupplierForm::configure($schema)),

                    Select::make('warehouse_id')
                        ->label(__('resources.purchase_orders.fields.receiving_warehouse'))
                        ->prefixIcon(Heroicon::BuildingOffice2)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->options(fn () => auth()->user()->isAdmin() || auth()->user()->isAuditor()
                        ? \App\Models\Warehouse::query()->pluck('name', 'id')
                        : auth()->user()->warehouses()->pluck('name', 'id'))
                        ->default(fn () => auth()->user()->warehouses()->count() === 1
                            ? auth()->user()->warehouses()->first()->id
                            : null)
                        ->required(),

                    Textarea::make('notes')
                        ->label(__('resources.purchase_orders.fields.notes'))
                        // ->prefixIcon(Heroicon::ChatBubbleBottomCenterText)
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function getLineItemsFields(): array
    {
        return [
            Repeater::make('items')
                ->relationship()
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                ->schema([
                    Select::make('product_variant_id')
                        ->label(__('resources.purchase_orders.fields.variant_sku'))
                        ->relationship('productVariant', 'sku')
                        ->prefixIcon(Heroicon::Tag)
                        ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                        ->live()
                        ->afterStateUpdated(function ($set) {
                            $set('ordered_unit_name', null);
                            $set('ordered_unit_ratio', null);
                        }),

                    Select::make('ordered_unit_name')
                        ->label(__('resources.purchase_orders.fields.unit'))
                        ->prefixIcon(Heroicon::Scale)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->options(function (Get $get) {
                            $variantId = $get('product_variant_id');
                            if (! $variantId) {
                                return [];
                            }
                            $query = ProductVariantUnitConversion::where('product_variant_id', $variantId);
                            $flagged = (clone $query)->where('is_default_purchase', true)
                                ->orderByDesc('base_unit_ratio')
                                ->pluck('unit_name', 'unit_name')
                                ->toArray();
                            if (! empty($flagged)) {
                                return $flagged;
                            }
                            return $query->orderByDesc('base_unit_ratio')
                                ->pluck('unit_name', 'unit_name')
                                ->toArray();
                        })
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Get $get, $set, $state) {
                            $ratio = ProductVariantUnitConversion::where('product_variant_id', $get('product_variant_id'))
                                ->where('unit_name', $state)
                                ->value('base_unit_ratio');
                            $set('ordered_unit_ratio', $ratio ?? 1);
                        }),

                    TextInput::make('ordered_unit_ratio')
                        ->label(__('resources.purchase_orders.fields.ratio_base'))
                        ->hintIcon(Heroicon::InformationCircle)
                        ->hint(__('resources.purchase_orders.hints.ratio_auto'))
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->numeric()
                        ->disabled()
                        ->dehydrated()
                        ->required(),

                    TextInput::make('ordered_qty')
                        ->label(__('resources.purchase_orders.fields.qty'))
                        ->prefixIcon(Heroicon::Hashtag)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->numeric()
                        ->minValue(1)
                        ->required(),

                    TextInput::make('unit_cost_price')
                        ->label(__('resources.purchase_orders.fields.unit_cost'))
                        ->prefixIcon(Heroicon::CurrencyDollar)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->numeric()
                        ->step(0.0001)
                        ->minValue(0)
                        ->required(),
                ])
                ->minItems(1)
                ->required()
                ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                    $data['ordered_base_qty'] = (int) $data['ordered_qty'] * (int) $data['ordered_unit_ratio'];
                    return $data;
                })
                ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                    $data['ordered_base_qty'] = (int) $data['ordered_qty'] * (int) $data['ordered_unit_ratio'];
                    return $data;
                }),
        ];
    }

    public static function getReviewFields(): array
    {
        return [
            Toggle::make('update_cost_price')
                ->label(__('resources.purchase_orders.fields.update_cost_price'))
                ->helperText(__('resources.purchase_orders.help.update_cost_price'))
                ->onIcon(Heroicon::CurrencyDollar)
                ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->default(false),
        ];
    }
}
```

**Note:** `reference_code` has no editable form field; it is system-generated as `PO-...` in `CreatePurchaseOrder::mutateFormDataBeforeCreate()`.

#### 7G.2 CreatePurchaseOrder.php

```php
namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use App\Support\GeneratesReferenceCodes;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class CreatePurchaseOrder extends CreateRecord
{
    use HasWizard;

    protected static string $resource = PurchaseOrderResource::class;

    protected function getSteps(): array
    {
        return [
            Step::make(__('resources.purchase_orders.steps.supplier_warehouse'))
                ->description(__('resources.purchase_orders.steps.supplier_warehouse_description'))
                ->icon(Heroicon::BuildingStorefront)
                ->schema(PurchaseOrderForm::getSupplierWarehouseFields()),

            Step::make(__('resources.purchase_orders.steps.line_items'))
                ->description(__('resources.purchase_orders.steps.line_items_description'))
                ->icon(Heroicon::ClipboardDocumentList)
                ->schema(PurchaseOrderForm::getLineItemsFields()),

            Step::make(__('resources.purchase_orders.steps.review_verify'))
                ->description(__('resources.purchase_orders.steps.review_verify_description'))
                ->icon(Heroicon::CheckCircle)
                ->schema([
                    ...PurchaseOrderForm::getReviewFields(),

                    View::make('filament.wizards.purchase-order-review')
                        ->viewData(fn (Get $get): array => [
                            'state' => [
                                'supplier_id' => $get('supplier_id'),
                                'warehouse_id' => $get('warehouse_id'),
                                'items' => $get('items') ?? [],
                                'notes' => $get('notes'),
                            ],
                        ])
                        ->columnSpanFull(),
                ]),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reference_code'] = $data['reference_code']
            ?? GeneratesReferenceCodes::generateReferenceCode('PO');
        $data['ordered_by'] = auth()->id();
        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Mirror `CreateDirectTransfer` (§7C.2): explicitly authorize via
        // the policy before accepting wizard submission. UI visibility is
        // never the boundary; `->strictAuthorization()` (§17.5) is the
        // backstop, not the call site.
        Gate::authorize('create', \App\Models\PurchaseOrder::class);

        return parent::handleRecordCreation($data);
    }

    public function getMaxContentWidth(): ?string
    {
        return Width::SevenExtraLarge->value;
    }
}
```

#### 7G.3 PurchaseOrdersTable.php — **Card Layout, No Bulk Actions**

```php
namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('reference_code')
                            ->label(__('resources.purchase_orders.table.reference'))
                            ->fontFamily('mono')
                            ->weight(FontWeight::Bold)
                            ->searchable()
                            ->sortable()
                            ->copyable(),

                        TextColumn::make('status')
                            ->label(__('resources.purchase_orders.table.status'))
                            ->badge()
                            ->alignEnd()
                            ->sortable(),
                    ])->from('md'),

                    Split::make([
                        TextColumn::make('supplier.name')
                            ->label(__('resources.purchase_orders.table.supplier'))
                            ->icon(Heroicon::BuildingStorefront)
                            ->iconColor('gray')
                            ->searchable()
                            ->sortable(),

                        TextColumn::make('warehouse.name')
                            ->label(__('resources.purchase_orders.table.warehouse'))
                            ->icon(Heroicon::BuildingOffice2)
                            ->iconColor('gray')
                            ->sortable(),
                    ])->from('md'),

                    Split::make([
                        TextColumn::make('items_count')
                            ->label(__('resources.purchase_orders.table.items'))
                            ->counts('items')
                            ->badge()
                            ->color('gray')
                            ->numeric(),

                        TextColumn::make('ordered_at')
                            ->label(__('resources.purchase_orders.table.ordered'))
                            ->dateTime('M j, Y')
                            ->sortable()
                            ->placeholder(__('common.empty')),

                        TextColumn::make('received_at')
                            ->label(__('resources.purchase_orders.table.received'))
                            ->dateTime('M j, Y')
                            ->sortable()
                            ->placeholder(__('common.empty'))
                            ->alignEnd(),
                    ])->from('lg'),
                ])->space(3),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('resources.purchase_orders.filters.status'))
                    ->options(PurchaseOrderStatus::class),
                SelectFilter::make('supplier_id')->relationship('supplier', 'name')->label(__('resources.purchase_orders.filters.supplier'))->searchable(),
                \App\Filament\Support\Filters\AdminReviewFilters::warehouse()->label(__('resources.purchase_orders.filters.warehouse')),
                TrashedFilter::make(),
                \App\Filament\Support\Filters\AdminReviewFilters::period('ordered_at')
                    ->visible(fn (): bool => auth()->user()?->can('viewAuditFilters', \App\Models\PurchaseOrder::class) ?? false),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(12)
            ->paginated([12, 24, 48])
            ->recordUrl(fn (PurchaseOrder $record) => PurchaseOrderResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::Eye),

                EditAction::make()
                    ->icon(Heroicon::PencilSquare)
                    ->authorize('update')
                    ->visible(fn (PurchaseOrder $record) => $record->status === PurchaseOrderStatus::Draft)
                    ->modalWidth(Width::Large),

                ActionGroup::make([
                    // ── Section: Lifecycle ────────────────────────────────────
                    ActionGroup::make([
                        Action::make('orderPurchase')
                            ->label(__('resources.purchase_orders.actions.order'))
                            ->modalHeading(__('resources.purchase_orders.actions.order_heading'))
                            ->modalDescription(__('resources.purchase_orders.actions.order_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::PaperAirplane)
                            ->color('primary')
                            ->authorize('orderPurchase')
                            ->visible(fn (PurchaseOrder $record) => $record->status === PurchaseOrderStatus::Draft)
                            ->requiresConfirmation()
                            ->action(fn (PurchaseOrder $record) => app(\App\Services\PurchaseService::class)->orderPurchase($record)),

                        Action::make('receivePurchase')
                            ->label(__('resources.purchase_orders.actions.receive'))
                            ->modalHeading(__('resources.purchase_orders.actions.receive_heading'))
                            ->modalDescription(__('resources.purchase_orders.actions.receive_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::ArchiveBoxArrowDown)
                            ->color('success')
                            ->authorize('receivePurchase')
                            ->visible(fn (PurchaseOrder $record) => in_array($record->status, [
                                PurchaseOrderStatus::Ordered,
                                PurchaseOrderStatus::PartiallyReceived,
                            ], true))
                            ->modalWidth(Width::FourExtraLarge)
                            ->schema(fn (PurchaseOrder $record) => collect($record->items)
                                ->map(function ($item) {
                                    $outstandingBase = $item->outstandingBaseQty();
                                    $outstandingDisplay = $item->ordered_unit_ratio > 1
                                        ? round($outstandingBase / $item->ordered_unit_ratio, 2)
                                        : $outstandingBase;

                                    return TextInput::make("received.{$item->id}")
                                        // Base-unit contract: `PurchaseService::receivePurchase()`
                                        // consumes base quantities, and `default()` /
                                        // `maxValue()` below are base units — so the
                                        // label MUST also render the outstanding in
                                        // base units (never display units) to avoid a
                                        // display→base mismatch at submit time. The
                                        // display-unit equivalent is informational
                                        // helper text only.
                                        ->label(__('resources.purchase_orders.fields.receive_line', [
                                            'sku'         => $item->productVariant->sku,
                                            'outstanding' => $outstandingBase,
                                            'unit'        => $item->productVariant->base_unit_name,
                                            'base'        => $outstandingBase,
                                        ]))
                                        ->helperText(__('resources.purchase_orders.help.receive_display_equivalent', [
                                            'display' => $outstandingDisplay,
                                            'unit'    => $item->ordered_unit_name,
                                        ]))
                                        ->prefixIcon(Heroicon::ArchiveBoxArrowDown)
                                        ->columnSpan(['default' => 1, 'md' => 1])
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue($outstandingBase)
                                        ->default($outstandingBase);
                                })
                                ->all())
                            ->action(function (array $data, PurchaseOrder $record) {
                                $received = collect($data['received'] ?? [])
                                    ->filter(fn ($qty) => (int) $qty > 0)
                                    ->mapWithKeys(fn ($qty, $itemId) => [(int) $itemId => (int) $qty])
                                    ->all();

                                app(\App\Services\PurchaseService::class)->receivePurchase($record->id, $received);

                                Notification::make()
                                    ->title(__('resources.purchase_orders.notifications.received'))
                                    ->success()
                                    ->send();
                            })
                            ->requiresConfirmation(),

                        Action::make('cancelPurchase')
                            ->label(__('resources.purchase_orders.actions.cancel'))
                            ->modalHeading(__('resources.purchase_orders.actions.cancel_heading'))
                            ->modalDescription(__('resources.purchase_orders.actions.cancel_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::XMark)
                            ->color('danger')
                            ->authorize('cancelPurchase')
                            ->visible(fn (PurchaseOrder $record) => $record->canBeCancelled())
                            ->requiresConfirmation()
                            ->action(fn (PurchaseOrder $record) => app(\App\Services\PurchaseService::class)->cancelPurchaseOrder($record)),
                    ])->dropdown(false),

                    // ── Section: Destructive ──────────────────────────────────
                    ActionGroup::make([
                        DeleteAction::make()
                            ->icon(Heroicon::Trash)
                            ->authorize('delete')
                            ->visible(fn (PurchaseOrder $record) => in_array($record->status, [
                                PurchaseOrderStatus::Draft,
                                PurchaseOrderStatus::Cancelled,
                            ], true)),

                        RestoreAction::make()
                            ->icon(Heroicon::ArrowUturnLeft)
                            ->authorize('restore'),

                        ForceDeleteAction::make()
                            ->icon(Heroicon::Trash)
                            ->authorize('forceDelete')
                            ->visible(fn () => auth()->user()->isAdmin()),
                    ])->dropdown(false),
                ])
                    ->icon(Heroicon::EllipsisVertical)
                    ->iconButton()
                    ->size(Size::Small)
                    ->color('gray')
                    ->tooltip(__('resources.purchase_orders.actions.more_actions'))
                    ->dropdownAutoPlacement()
                    ->dropdownWidth(Width::Large),
            ]);
    }
}
```

#### 7G.4 PurchaseOrderInfolist.php

```php
namespace App\Filament\Resources\PurchaseOrders\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class PurchaseOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])->schema([
                Section::make(__('resources.purchase_orders.infolist.profile'))
                    ->icon(Heroicon::DocumentText)
                    ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->schema([
                        TextEntry::make('reference_code')
                            ->label(__('resources.purchase_orders.fields.reference_code'))
                            ->weight(FontWeight::Bold)->size('lg')->copyable()->color('primary')
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('status')->badge()
                            ->label(__('resources.purchase_orders.fields.status'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('supplier.name')->icon(Heroicon::BuildingStorefront)
                            ->label(__('resources.purchase_orders.fields.supplier'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('warehouse.name')->icon(Heroicon::BuildingOffice2)
                            ->label(__('resources.purchase_orders.fields.receiving_warehouse'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('update_cost_price')
                            ->label(__('resources.purchase_orders.fields.update_cost_price'))
                            ->badge()->boolean()
                            ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2]),

                        TextEntry::make('notes')
                            ->label(__('resources.purchase_orders.fields.notes'))
                            ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                            ->placeholder(__('common.empty')),
                    ]),

                Section::make(__('resources.purchase_orders.infolist.signoffs'))
                    ->icon(Heroicon::ShieldCheck)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->schema([
                        TextEntry::make('orderedBy.name')->label(__('resources.purchase_orders.fields.ordered_by'))->icon(Heroicon::User)->placeholder(__('common.empty')),
                        TextEntry::make('receivedBy.name')->label(__('resources.purchase_orders.fields.received_by'))->icon(Heroicon::ArchiveBoxArrowDown)->placeholder(__('common.empty')),
                        TextEntry::make('ordered_at')->label(__('resources.purchase_orders.fields.ordered_at'))->dateTime('M j, Y H:i')->placeholder(__('common.empty')),
                        TextEntry::make('received_at')->label(__('resources.purchase_orders.fields.received_at'))->dateTime('M j, Y H:i')->placeholder(__('common.empty')),
                    ]),

                Section::make(__('resources.purchase_orders.infolist.line_items'))
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->table([
                                TableColumn::make(__('resources.purchase_orders.fields.sku')),
                                TableColumn::make(__('resources.purchase_orders.fields.product')),
                                TableColumn::make(__('resources.purchase_orders.fields.ordered_base')),
                                TableColumn::make(__('resources.purchase_orders.fields.received_base')),
                                TableColumn::make(__('resources.purchase_orders.fields.unit_cost')),
                            ])
                            ->schema([
                                TextEntry::make('productVariant.sku')
                                    ->weight(FontWeight::Bold),

                                TextEntry::make('productVariant.name'),

                                TextEntry::make('ordered_base_qty')
                                    ->numeric()
                                    ->alignEnd(),

                                TextEntry::make('received_base_qty')
                                    ->numeric()
                                    ->alignEnd(),

                                TextEntry::make('unit_cost_price')
                                    ->formatStateUsing(fn ($state): string => format_money($state))
                                    ->alignEnd(),
                            ]),
                    ]),
            ]),
        ]);
    }
}
```

> Ownership note: the thin `PurchaseOrderResource` class lives only in §18.1a. This section (§7G) owns the `PurchaseOrderForm` (§7G.1), `CreatePurchaseOrder` (§7G.2), `PurchaseOrdersTable` (§7G.3), and `PurchaseOrderInfolist` (§7G.4) contracts for the Purchases domain section.

---

### 7H. SalesOrderResource

**Model:** `App\Models\SalesOrder` · **Group:** SALES · **Sort:** 1

**Record title:** `reference_code` (matching `DirectTransferResource::$recordTitleAttribute`).

#### 7H.1 SalesOrderForm.php

```php
namespace App\Filament\Resources\SalesOrders\Schemas;

use App\Models\ProductVariantUnitConversion;
use Filament\Schemas\Components\Placeholder;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SalesOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ...self::getCustomerWarehouseFields(),
            ...self::getLineItemsFields(),
        ]);
    }

    public static function getCustomerWarehouseFields(): array
    {
        return [
            Section::make(__('resources.sales_orders.form.customer_warehouse'))
                ->icon(Heroicon::UserGroup)
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                ->schema([
                    Select::make('customer_id')
                        ->label(__('resources.sales_orders.fields.customer'))
                        ->relationship('customer', 'name')
                        ->prefixIcon(Heroicon::UserGroup)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->searchable()->preload()->required()
                        ->createOptionForm(fn (Schema $schema) => \App\Filament\Resources\Customers\Schemas\CustomerForm::configure($schema)),

                    Select::make('warehouse_id')
                        ->label(__('resources.sales_orders.fields.dispatching_warehouse'))
                        ->prefixIcon(Heroicon::BuildingOffice2)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->options(fn () => auth()->user()->isAdmin() || auth()->user()->isAuditor()
                        ? \App\Models\Warehouse::query()->pluck('name', 'id')
                        : auth()->user()->warehouses()->pluck('name', 'id'))
                        ->default(fn () => auth()->user()->warehouses()->count() === 1
                            ? auth()->user()->warehouses()->first()->id
                            : null)
                        ->required(),

                    Textarea::make('notes')
                        ->label(__('resources.sales_orders.fields.notes'))
                        // ->prefixIcon(Heroicon::ChatBubbleBottomCenterText)
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function getLineItemsFields(): array
    {
        return [
            Repeater::make('items')
                ->relationship()
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                ->schema([
                    Select::make('product_variant_id')
                        ->label(__('resources.sales_orders.fields.variant_sku'))
                        ->relationship('productVariant', 'sku')
                        ->prefixIcon(Heroicon::Tag)
                        ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                        ->searchable()->preload()->required()
                        ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                        ->live()
                        ->afterStateUpdated(function (Get $get, $set, $state) {
                            $set('unit_name', null);
                            $set('unit_ratio', null);
                            $variant = \App\Models\ProductVariant::with('currentPrice')->find($state);
                            $set('_current_sale_price_preview', $variant?->currentPrice?->sale_price ?? '0.0000');
                        }),

                    Select::make('unit_name')
                        ->label(__('resources.sales_orders.fields.unit'))
                        ->prefixIcon(Heroicon::Scale)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->options(function (Get $get) {
                            $variantId = $get('product_variant_id');
                            if (! $variantId) {
                                return [];
                            }
                            return ProductVariantUnitConversion::where('product_variant_id', $variantId)
                                ->orderByDesc('base_unit_ratio')
                                ->pluck('unit_name', 'unit_name')
                                ->toArray();
                        })
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Get $get, $set, $state) {
                            $ratio = ProductVariantUnitConversion::where('product_variant_id', $get('product_variant_id'))
                                ->where('unit_name', $state)
                                ->value('base_unit_ratio');
                            $set('unit_ratio', $ratio ?? 1);
                        }),

                    TextInput::make('unit_ratio')
                        ->label(__('resources.sales_orders.fields.ratio_base'))
                        ->hintIcon(Heroicon::InformationCircle)
                        ->hint(__('resources.sales_orders.hints.ratio_auto'))
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->numeric()->disabled()->dehydrated()->required(),

                    TextInput::make('qty')
                        ->label(__('resources.sales_orders.fields.qty'))
                        ->prefixIcon(Heroicon::Hashtag)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->numeric()->minValue(1)->required(),

                    // `Hidden` carries the preview value as real form state under
                    // `_current_sale_price_preview`. The display-only
                    // `Placeholder` below MUST use a different state path
                    // (`current_sale_price_display`) — sharing the hidden
                    // field's name would create a Filament state-path conflict.
                    // The placeholder reads the hidden value via sibling `$get()`.
                    Hidden::make('_current_sale_price_preview')
                        ->default('0.0000')
                        ->dehydrated(false),

                    Placeholder::make('current_sale_price_display')
                        ->label(__('resources.sales_orders.fields.catalog_sale_price'))
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->content(fn (Get $get) => $get('_current_sale_price_preview') ?? __('common.empty')),
                ])
                ->minItems(1)->required()
                ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                    $data['base_qty'] = (int) $data['qty'] * (int) $data['unit_ratio'];
                    return $data;
                })
                ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                    $data['base_qty'] = (int) $data['qty'] * (int) $data['unit_ratio'];
                    return $data;
                }),
        ];
    }
}
```

**Note:** `reference_code` has no editable form field; it is system-generated as `SO-...` in `CreateSalesOrder::mutateFormDataBeforeCreate()`.

#### 7H.2 CreateSalesOrder.php

```php
namespace App\Filament\Resources\SalesOrders\Pages;

use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Resources\SalesOrders\Schemas\SalesOrderForm;
use App\Support\GeneratesReferenceCodes;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class CreateSalesOrder extends CreateRecord
{
    use HasWizard;

    protected static string $resource = SalesOrderResource::class;

    protected function getSteps(): array
    {
        return [
            Step::make(__('resources.sales_orders.steps.customer_warehouse'))
                ->description(__('resources.sales_orders.steps.customer_warehouse_description'))
                ->icon(Heroicon::UserGroup)
                ->schema(SalesOrderForm::getCustomerWarehouseFields()),

            Step::make(__('resources.sales_orders.steps.line_items'))
                ->description(__('resources.sales_orders.steps.line_items_description'))
                ->icon(Heroicon::ClipboardDocumentList)
                ->schema(SalesOrderForm::getLineItemsFields()),

            Step::make(__('resources.sales_orders.steps.review_verify'))
                ->description(__('resources.sales_orders.steps.review_verify_description'))
                ->icon(Heroicon::CheckCircle)
                ->schema([
                    View::make('filament.wizards.sales-order-review')
                        ->viewData(fn (Get $get): array => [
                            'state' => [
                                'customer_id' => $get('customer_id'),
                                'warehouse_id' => $get('warehouse_id'),
                                'items' => $get('items') ?? [],
                                'notes' => $get('notes'),
                            ],
                        ])
                        ->columnSpanFull(),
                ]),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reference_code'] = $data['reference_code']
            ?? GeneratesReferenceCodes::generateReferenceCode('SO');
        $data['ordered_by'] = auth()->id();
        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Mirror `CreateDirectTransfer` (§7C.2): explicitly authorize via
        // the policy before accepting wizard submission. UI visibility is
        // never the boundary; `->strictAuthorization()` (§17.5) is the
        // backstop, not the call site.
        Gate::authorize('create', \App\Models\SalesOrder::class);

        return parent::handleRecordCreation($data);
    }

    public function getMaxContentWidth(): ?string
    {
        return Width::SevenExtraLarge->value;
    }
}
```

#### 7H.3 SalesOrdersTable.php — **Card Layout, No Bulk Actions**

```php
namespace App\Filament\Resources\SalesOrders\Tables;

use App\Enums\SalesOrderStatus;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Models\SalesOrder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SalesOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('reference_code')
                            ->label(__('resources.sales_orders.table.reference'))
                            ->fontFamily('mono')
                            ->weight(FontWeight::Bold)
                            ->searchable()->sortable()->copyable(),

                        TextColumn::make('status')
                            ->label(__('resources.sales_orders.table.status'))
                            ->badge()->alignEnd()->sortable(),
                    ])->from('md'),

                    Split::make([
                        TextColumn::make('customer.name')
                            ->label(__('resources.sales_orders.table.customer'))
                            ->icon(Heroicon::UserGroup)->iconColor('gray')
                            ->searchable()->sortable(),

                        TextColumn::make('warehouse.name')
                            ->label(__('resources.sales_orders.table.warehouse'))
                            ->icon(Heroicon::BuildingOffice2)->iconColor('gray')
                            ->sortable(),
                    ])->from('md'),

                    Split::make([
                        TextColumn::make('items_count')
                            ->label(__('resources.sales_orders.table.items'))->counts('items')->badge()->color('gray')->numeric(),

                        TextColumn::make('confirmed_at')
                            ->label(__('resources.sales_orders.table.confirmed'))->dateTime('M j, Y')->sortable()
                            ->placeholder(__('common.empty'))->visibleFrom('md'),

                        TextColumn::make('dispatched_at')
                            ->label(__('resources.sales_orders.table.dispatched'))->dateTime('M j, Y')->sortable()
                            ->placeholder(__('common.empty'))->alignEnd(),
                    ])->from('lg'),
                ])->space(3),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->label(__('resources.sales_orders.filters.status'))
                    ->options(SalesOrderStatus::class),
                \Filament\Tables\Filters\SelectFilter::make('customer_id')->relationship('customer', 'name')->label(__('resources.sales_orders.filters.customer'))->searchable(),
                \App\Filament\Support\Filters\AdminReviewFilters::warehouse()->label(__('resources.sales_orders.filters.warehouse')),
                \Filament\Tables\Filters\TrashedFilter::make(),
                \App\Filament\Support\Filters\AdminReviewFilters::period('confirmed_at')
                    ->visible(fn (): bool => auth()->user()?->can('viewAuditFilters', \App\Models\SalesOrder::class) ?? false),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(12)
            ->paginated([12, 24, 48])
            ->recordUrl(fn (SalesOrder $record) => SalesOrderResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::Eye),

                EditAction::make()
                    ->icon(Heroicon::PencilSquare)
                    ->authorize('update')
                    ->visible(fn (SalesOrder $record) => $record->status === SalesOrderStatus::Draft)
                    ->modalWidth(Width::Large),

                ActionGroup::make([
                    // ── Section: Lifecycle ────────────────────────────────────
                    ActionGroup::make([
                        Action::make('confirmSalesOrder')
                            ->label(__('resources.sales_orders.actions.confirm'))
                            ->modalHeading(__('resources.sales_orders.actions.confirm_heading'))
                            ->modalDescription(__('resources.sales_orders.actions.confirm_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::CheckCircle)
                            ->color('primary')
                            ->authorize('confirmSalesOrder')
                            ->visible(fn (SalesOrder $record) => $record->status === SalesOrderStatus::Draft)
                            ->requiresConfirmation()
                            ->action(fn (SalesOrder $record) => app(\App\Services\SalesService::class)->confirmSalesOrder($record)),

                        Action::make('dispatchSale')
                            ->label(__('resources.sales_orders.actions.dispatch'))
                            ->modalHeading(__('resources.sales_orders.actions.dispatch_heading'))
                            ->modalDescription(__('resources.sales_orders.actions.dispatch_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::Truck)
                            ->color('success')
                            ->authorize('dispatchSale')
                            ->visible(fn (SalesOrder $record) => in_array($record->status, [
                                SalesOrderStatus::Confirmed,
                                SalesOrderStatus::PartiallyDispatched,
                            ], true))
                            ->modalWidth(Width::FourExtraLarge)
                            ->schema(function (SalesOrder $record) {
                                $variantIds = $record->items->pluck('product_variant_id')->unique()->all();

                                // Exclude this order's own reservation.
                                $availableByVariant = \App\Models\ProductVariant::batchAvailableQuantity(
                                    $variantIds,
                                    $record->warehouse_id,
                                    $record->id,
                                );

                                return collect($record->items)
                                    ->map(function ($item) use ($availableByVariant) {
                                        $available = $availableByVariant[$item->product_variant_id] ?? 0;
                                        $safeMax = min($item->outstandingBaseQty(), max(0, $available));

                                        $helperText = null;
                                        if ($available === 0) {
                                            $helperText = __('resources.sales_orders.help.no_stock');
                                        } elseif ($available < $item->outstandingBaseQty()) {
                                            $helperText = __('resources.sales_orders.help.insufficient_stock');
                                        }

                                        return TextInput::make("dispatch.{$item->id}")
                                            ->label(__('resources.sales_orders.fields.dispatch_line', [
                                                'sku'         => $item->productVariant->sku,
                                                'outstanding' => $item->outstandingBaseQty(),
                                                'unit'        => $item->unit_name,
                                                'available'   => $available,
                                            ]))
                                            ->prefixIcon(Heroicon::Truck)
                                            ->columnSpan(['default' => 1, 'md' => 1])
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue($safeMax)
                                            ->default($safeMax)
                                            ->helperText($helperText);
                                    })
                                    ->all();
                            })
                            ->action(function (array $data, SalesOrder $record) {
                                $dispatch = collect($data['dispatch'] ?? [])
                                    ->filter(fn ($qty) => (int) $qty > 0)
                                    ->mapWithKeys(fn ($qty, $itemId) => [(int) $itemId => (int) $qty])
                                    ->all();

                                app(\App\Services\SalesService::class)->dispatchSale($record->id, $dispatch);

                                Notification::make()
                                    ->title(__('resources.sales_orders.notifications.dispatched'))
                                    ->success()
                                    ->send();
                            })
                            ->requiresConfirmation(),

                        Action::make('recordReturn')
                            ->label(__('resources.sales_orders.actions.return'))
                            ->modalHeading(__('resources.sales_orders.actions.return_heading'))
                            ->modalDescription(__('resources.sales_orders.actions.return_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::ArrowUturnLeft)
                            ->color('warning')
                            ->authorize('recordSalesReturn')
                            ->visible(fn (SalesOrder $record) => $record->items->contains(fn ($item) => $item->dispatched_base_qty > 0))
                            ->modalWidth(Width::Large)
                            ->schema([
                                Select::make('sales_order_item_id')
                                    ->label(__('resources.sales_orders.fields.line_item'))
                                    ->prefixIcon(Heroicon::ClipboardDocumentList)
                                    ->columnSpan(['default' => 1, 'md' => 1])
                                    ->options(fn (SalesOrder $record) => $record->items
                                        ->where('dispatched_base_qty', '>', 0)
                                        ->mapWithKeys(fn ($item) => [
                                            $item->id => __('resources.sales_orders.fields.return_option', [
                                                'sku'        => $item->productVariant->sku,
                                                'dispatched' => $item->dispatched_base_qty,
                                                'returned'   => $item->alreadyReturnedBaseQty(),
                                            ]),
                                        ]))
                                    ->required()
                                    ->live(),

                                TextInput::make('returned_base_qty')
                                    ->label(__('resources.sales_orders.fields.returned_qty_base'))
                                    ->prefixIcon(Heroicon::Hashtag)
                                    ->columnSpan(['default' => 1, 'md' => 1])
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(function (\Filament\Schemas\Components\Utilities\Get $get, SalesOrder $record) {
                                        $itemId = $get('sales_order_item_id');
                                        if (! $itemId) {
                                            return null;
                                        }
                                        $item = $record->items->firstWhere('id', (int) $itemId);
                                        return $item ? ($item->dispatched_base_qty - $item->alreadyReturnedBaseQty()) : null;
                                    })
                                    ->required(),

                                Textarea::make('notes')
                                    ->label(__('resources.sales_orders.fields.notes'))
                                    // ->prefixIcon(Heroicon::ChatBubbleBottomCenterText)
                                    ->columnSpanFull(),
                            ])
                            ->action(function (array $data) {
                                app(\App\Services\SalesService::class)->recordSalesReturn(
                                    (int) $data['sales_order_item_id'],
                                    (int) $data['returned_base_qty'],
                                    $data['notes'] ?? null,
                                );

                                Notification::make()
                                    ->title(__('resources.sales_orders.notifications.return_recorded'))
                                    ->success()
                                    ->send();
                            })
                            ->requiresConfirmation(),

                        Action::make('cancelSalesOrder')
                            ->label(__('resources.sales_orders.actions.cancel'))
                            ->modalHeading(__('resources.sales_orders.actions.cancel_heading'))
                            ->modalDescription(__('resources.sales_orders.actions.cancel_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::XMark)
                            ->color('danger')
                            ->authorize('cancelSalesOrder')
                            ->visible(fn (SalesOrder $record) => $record->canBeCancelled())
                            ->requiresConfirmation()
                            ->action(fn (SalesOrder $record) => app(\App\Services\SalesService::class)->cancelSalesOrder($record)),
                    ])->dropdown(false),

                    // ── Section: Destructive ──────────────────────────────────
                    ActionGroup::make([
                        DeleteAction::make()
                            ->icon(Heroicon::Trash)
                            ->authorize('delete')
                            ->visible(fn (SalesOrder $record) => in_array($record->status, [
                                SalesOrderStatus::Draft,
                                SalesOrderStatus::Cancelled,
                            ], true)),

                        RestoreAction::make()
                            ->icon(Heroicon::ArrowUturnLeft)
                            ->authorize('restore'),

                        ForceDeleteAction::make()
                            ->icon(Heroicon::Trash)
                            ->authorize('forceDelete')
                            ->visible(fn () => auth()->user()->isAdmin()),
                    ])->dropdown(false),
                ])
                    ->icon(Heroicon::EllipsisVertical)
                    ->iconButton()
                    ->size(Size::Small)
                    ->color('gray')
                    ->tooltip(__('resources.sales_orders.actions.more_actions'))
                    ->dropdownAutoPlacement()
                    ->dropdownWidth(Width::Large),
            ]);
    }
}
```

#### 7H.4 SalesOrderInfolist.php

```php
namespace App\Filament\Resources\SalesOrders\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class SalesOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])->schema([
                Section::make(__('resources.sales_orders.infolist.profile'))
                    ->icon(Heroicon::DocumentText)
                    ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->schema([
                        TextEntry::make('reference_code')->label(__('resources.sales_orders.fields.reference_code'))
                            ->weight(FontWeight::Bold)->size('lg')->copyable()->color('primary')
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('status')->badge()
                            ->label(__('resources.sales_orders.fields.status'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('customer.name')->icon(Heroicon::UserGroup)
                            ->label(__('resources.sales_orders.fields.customer'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('warehouse.name')->icon(Heroicon::BuildingOffice2)
                            ->label(__('resources.sales_orders.fields.dispatching_warehouse'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),

                        TextEntry::make('notes')
                            ->label(__('resources.sales_orders.fields.notes'))
                            ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                            ->placeholder(__('common.empty')),
                    ]),

                Section::make(__('resources.sales_orders.infolist.signoffs'))
                    ->icon(Heroicon::ShieldCheck)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->schema([
                        TextEntry::make('orderedBy.name')->label(__('resources.sales_orders.fields.ordered_by'))->icon(Heroicon::User)->placeholder(__('common.empty')),
                        TextEntry::make('dispatchedBy.name')->label(__('resources.sales_orders.fields.dispatched_by'))->icon(Heroicon::Truck)->placeholder(__('common.empty')),
                        TextEntry::make('confirmed_at')->label(__('resources.sales_orders.fields.confirmed_at'))->dateTime('M j, Y H:i')->placeholder(__('common.empty')),
                        TextEntry::make('dispatched_at')->label(__('resources.sales_orders.fields.dispatched_at'))->dateTime('M j, Y H:i')->placeholder(__('common.empty')),
                    ]),

                Section::make(__('resources.sales_orders.infolist.line_items'))
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->table([
                                TableColumn::make(__('resources.sales_orders.fields.sku')),
                                TableColumn::make(__('resources.sales_orders.fields.product')),
                                TableColumn::make(__('resources.sales_orders.fields.ordered_base')),
                                TableColumn::make(__('resources.sales_orders.fields.dispatched_base')),
                                TableColumn::make(__('resources.sales_orders.fields.snapshot_price')),
                            ])
                            ->schema([
                                TextEntry::make('productVariant.sku')
                                    ->weight(FontWeight::Bold),

                                TextEntry::make('productVariant.name'),

                                TextEntry::make('base_qty')
                                    ->numeric()
                                    ->alignEnd(),

                                TextEntry::make('dispatched_base_qty')
                                    ->numeric()
                                    ->alignEnd(),

                                TextEntry::make('unit_sale_price_snapshot')
                                    ->formatStateUsing(fn ($state): string => format_money($state))
                                    ->alignEnd(),
                            ]),
                    ]),
            ]),
        ]);
    }
}
```

> Ownership note: the thin `SalesOrderResource` class lives only in §18.1a. This section (§7H) owns the `SalesOrderForm` (§7H.1), `CreateSalesOrder` (§7H.2), `SalesOrdersTable` (§7H.3), and `SalesOrderInfolist` (§7H.4) contracts for the Sales domain section.

---

### 7I. SupplierResource

**Model:** `App\Models\Supplier` · **Group:** PURCHASING · **Sort:** 2

#### 7I.1 SupplierForm.php

```php
namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('resources.suppliers.fields.name'))
                ->prefixIcon(Heroicon::BuildingStorefront)
                ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                ->required()->maxLength(255),

            TextInput::make('contact_person')
                ->label(__('resources.suppliers.fields.contact_person'))
                ->prefixIcon(Heroicon::User)
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->maxLength(255),

            TextInput::make('phone')
                ->label(__('resources.suppliers.fields.phone'))
                ->prefixIcon(Heroicon::Phone)
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->tel(),

            TextInput::make('email')
                ->label(__('resources.suppliers.fields.email'))
                ->prefixIcon(Heroicon::Envelope)
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->email(),

            Textarea::make('address')
                ->label(__('resources.suppliers.fields.address'))
                // ->prefixIcon(Heroicon::MapPin)
                ->columnSpanFull(),

            Toggle::make('is_active')
                ->label(__('resources.suppliers.fields.is_active'))
                ->onIcon(Heroicon::CheckCircle)
                ->offIcon(Heroicon::XCircle)
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->default(true),
        ]);
    }
}
```

#### 7I.2 SuppliersTable.php — **Card Layout, No Bulk Actions**

```php
namespace App\Filament\Resources\Suppliers\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('name')
                            ->label(__('resources.suppliers.table.name'))
                            ->weight(FontWeight::Bold)
                            ->searchable()->sortable(),

                        TextColumn::make('is_active')
                            ->label(__('resources.suppliers.table.status'))
                            ->badge()->alignEnd()
                            ->formatStateUsing(fn (bool $state) => $state ? __('common.active') : __('common.inactive'))
                            ->color(fn (bool $state) => $state ? 'success' : 'danger'),
                    ])->from('md'),

                    TextColumn::make('contact_person')
                        ->label(__('resources.suppliers.table.contact'))
                        ->icon(Heroicon::User)->iconColor('gray')
                        ->searchable()->placeholder(__('common.empty')),

                    Split::make([
                        TextColumn::make('phone')
                            ->label(__('resources.suppliers.table.phone'))
                            ->icon(Heroicon::Phone)->iconColor('gray')
                            ->copyable()->placeholder(__('common.empty')),

                        TextColumn::make('email')
                            ->label(__('resources.suppliers.table.email'))
                            ->icon(Heroicon::Envelope)->iconColor('gray')
                            ->copyable()->placeholder(__('common.empty')),
                    ])->from('md'),

                    TextColumn::make('purchase_orders_count')
                        ->label(__('resources.suppliers.table.purchase_orders'))
                        ->counts('purchaseOrders')
                        ->badge()->color('primary')->numeric(),
                ])->space(3),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('resources.suppliers.filters.is_active')),
                TrashedFilter::make(),
            ])
            ->defaultSort('name')
            ->defaultPaginationPageOption(12)
            ->paginated([12, 24, 48])
            ->recordActions([
                EditAction::make()
                    ->icon(Heroicon::PencilSquare)
                    ->authorize('update')
                    ->modalWidth(Width::Large),

                DeleteAction::make()
                    ->icon(Heroicon::Trash)
                    ->authorize('delete'),

                RestoreAction::make()
                    ->icon(Heroicon::ArrowUturnLeft)
                    ->authorize('restore'),

                ForceDeleteAction::make()
                    ->icon(Heroicon::Trash)
                    ->authorize('forceDelete')
                    ->visible(fn () => auth()->user()->isAdmin()),
            ]);
    }
}
```

> Ownership note: the thin `SupplierResource` class lives only in §18.1a. This section (§7I) owns the `SupplierForm` (§7I.1) and `SuppliersTable` (§7I.2) contracts for the Suppliers domain section.

---

### 7J. CustomerResource

**Model:** `App\Models\Customer` · **Group:** SALES · **Sort:** 2

#### 7J.1 CustomerForm.php

```php
namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('resources.customers.fields.name'))
                ->prefixIcon(Heroicon::UserGroup)
                ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                ->required()->maxLength(255),

            TextInput::make('contact_person')
                ->label(__('resources.customers.fields.contact_person'))
                ->prefixIcon(Heroicon::User)
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->maxLength(255),

            TextInput::make('phone')
                ->label(__('resources.customers.fields.phone'))
                ->prefixIcon(Heroicon::Phone)
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->tel(),

            TextInput::make('email')
                ->label(__('resources.customers.fields.email'))
                ->prefixIcon(Heroicon::Envelope)
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->email(),

            Textarea::make('address')
                ->label(__('resources.customers.fields.address'))
                // ->prefixIcon(Heroicon::MapPin)
                ->columnSpanFull(),

            Toggle::make('is_active')
                ->label(__('resources.customers.fields.is_active'))
                ->onIcon(Heroicon::CheckCircle)
                ->offIcon(Heroicon::XCircle)
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->default(true),
        ]);
    }
}
```

#### 7J.2 CustomersTable.php — **Card Layout, No Bulk Actions**

```php
namespace App\Filament\Resources\Customers\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('name')
                            ->label(__('resources.customers.table.name'))
                            ->weight(FontWeight::Bold)->searchable()->sortable(),
                        TextColumn::make('is_active')
                            ->label(__('resources.customers.table.status'))->badge()->alignEnd()
                            ->formatStateUsing(fn (bool $state) => $state ? __('common.active') : __('common.inactive'))
                            ->color(fn (bool $state) => $state ? 'success' : 'danger'),
                    ])->from('md'),

                    TextColumn::make('contact_person')
                        ->label(__('resources.customers.table.contact'))->icon(Heroicon::User)->iconColor('gray')
                        ->searchable()->placeholder(__('common.empty')),

                    Split::make([
                        TextColumn::make('phone')
                            ->label(__('resources.customers.table.phone'))
                            ->icon(Heroicon::Phone)->iconColor('gray')->copyable()->placeholder(__('common.empty')),
                        TextColumn::make('email')
                            ->label(__('resources.customers.table.email'))
                            ->icon(Heroicon::Envelope)->iconColor('gray')->copyable()->placeholder(__('common.empty')),
                    ])->from('md'),

                    TextColumn::make('sales_orders_count')
                        ->label(__('resources.customers.table.sales_orders'))
                        ->counts('salesOrders')
                        ->badge()->color('primary')->numeric(),
                ])->space(3),
            ])
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('resources.customers.filters.is_active')),
                TrashedFilter::make(),
            ])
            ->defaultSort('name')
            ->defaultPaginationPageOption(12)
            ->paginated([12, 24, 48])
            ->recordActions([
                EditAction::make()->icon(Heroicon::PencilSquare)->modalWidth(Width::Large)->authorize('update'),
                DeleteAction::make()->icon(Heroicon::Trash)->authorize('delete'),
                RestoreAction::make()->icon(Heroicon::ArrowUturnLeft)->authorize('restore'),
                ForceDeleteAction::make()
                    ->icon(Heroicon::Trash)->authorize('forceDelete')
                    ->visible(fn () => auth()->user()->isAdmin()),
            ]);
    }
}
```

> Ownership note: the thin `CustomerResource` class lives only in §18.1a. This section (§7J) owns the `CustomerForm` (§7J.1) and `CustomersTable` (§7J.2) contracts for the Customers domain section.

---

### 7K. WarehouseResource

**Model:** `App\Models\Warehouse` · **Group:** SYSTEM ADMIN · **Sort:** 1

#### 7K.1 WarehouseForm.php — **Read-Only User Assignments**

```php
namespace App\Filament\Resources\Warehouses\Schemas;

use App\Models\Warehouse;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class WarehouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('resources.warehouses.form.profile'))
                ->icon(Heroicon::BuildingOffice)
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                ->schema([
                    TextInput::make('code')
                        ->label(__('resources.warehouses.fields.code'))
                        ->prefixIcon(Heroicon::Tag)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->nullable()->unique(ignoreRecord: true)->maxLength(50)
                        ->placeholder(__('resources.warehouses.placeholders.code'))
                        ->helperText(__('resources.warehouses.help.code')),

                    TextInput::make('name')
                        ->label(__('resources.warehouses.fields.name'))
                        ->prefixIcon(Heroicon::Identification)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->required()->maxLength(255),

                    Textarea::make('location')
                        ->label(__('resources.warehouses.fields.location'))
                        // ->prefixIcon(Heroicon::MapPin)
                        ->columnSpanFull()->rows(2)->maxLength(500),
                ]),

            Section::make(__('resources.warehouses.form.access_status'))
                ->icon(Heroicon::ShieldCheck)
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                ->schema([
                    // Read-only display: a disabled relationship `Select` on a
                    // `BelongsToMany` does not hydrate the pivot selection
                    // reliably, so assigned staff render as a `Placeholder`
                    // resolved from the page record (same `$record` closure
                    // style as the infolist `users_count` entry in §7K.3, which
                    // likewise queries via `users()` so no eager-load
                    // declaration is required on the Create/Edit page context).
                    // Assignments stay editable from the user record.
                    Placeholder::make('assigned_users')
                        ->label(__('resources.warehouses.fields.users'))
                        ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                        ->content(fn (?Warehouse $record): string => $record?->users()->pluck('name')->implode(', ')
                            ?: __('resources.warehouses.empty_staff'))
                        ->helperText(__('resources.warehouses.help.users_readonly')),

                    Toggle::make('is_active')
                        ->label(__('resources.warehouses.fields.is_active'))
                        ->onIcon(Heroicon::CheckCircle)
                        ->offIcon(Heroicon::XCircle)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->default(true),
                ]),
        ]);
    }
}
```

#### 7K.2 WarehousesTable.php — **Card Layout, No Bulk Actions**

```php
namespace App\Filament\Resources\Warehouses\Tables;

use App\Filament\Resources\Warehouses\WarehouseResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class WarehousesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('code')
                            ->label(__('resources.warehouses.table.code'))
                            ->fontFamily('mono')
                            ->weight(FontWeight::Bold)
                            ->searchable()->sortable()->copyable()->copyMessage(__('common.copied')),

                        TextColumn::make('is_active')
                            ->label(__('resources.warehouses.table.status'))
                            ->badge()->alignEnd()
                            ->formatStateUsing(fn (bool $state) => $state ? __('common.active') : __('common.inactive'))
                            ->color(fn (bool $state) => $state ? 'success' : 'danger'),
                    ])->from('md'),

                    TextColumn::make('name')
                        ->label(__('resources.warehouses.table.name'))
                        ->searchable()->sortable()->weight(FontWeight::SemiBold),

                    TextColumn::make('location')
                        ->label(__('resources.warehouses.table.location'))
                        ->icon(Heroicon::MapPin)->iconColor('gray')
                        ->searchable()->limit(60)->placeholder(__('common.empty')),

                    Split::make([
                        TextColumn::make('users_count')
                            ->label(__('resources.warehouses.table.staff'))
                            ->counts('users')
                            ->badge()->color('primary')->numeric(),

                        TextColumn::make('stock_movements_count')
                            ->label(__('resources.warehouses.table.ledger_entries'))
                            ->counts('stockMovements')
                            ->badge()->color('gray')->numeric(),
                    ])->from('md'),
                ])->space(3),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('resources.warehouses.filters.is_active')),
            ])
            ->defaultSort('code')
            ->defaultPaginationPageOption(12)
            ->paginated([12, 24, 48])
            ->recordUrl(fn ($record) => WarehouseResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                EditAction::make()
                    ->icon(Heroicon::PencilSquare)
                    ->authorize('update')
                    ->modalWidth(Width::Large),

                DeleteAction::make()
                    ->icon(Heroicon::Trash)
                    ->authorize('delete')
                    ->requiresConfirmation()
                    ->modalDescription(__('resources.warehouses.delete_confirm_description')),
            ]);
    }
}
```

#### 7K.3 WarehouseInfolist.php

```php
namespace App\Filament\Resources\Warehouses\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class WarehouseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])->schema([
                Section::make(__('resources.warehouses.infolist.profile'))
                    ->icon(Heroicon::BuildingOffice)
                    ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->schema([
                        TextEntry::make('code')->label(__('resources.warehouses.fields.code'))
                            ->weight(FontWeight::Bold)->size('lg')->copyable()->color('primary')
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('name')->label(__('resources.warehouses.fields.name'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('location')->label(__('resources.warehouses.fields.location'))->placeholder(__('common.empty'))
                            ->columnSpanFull(),
                    ]),

                Section::make(__('resources.warehouses.infolist.status'))
                    ->icon(Heroicon::ShieldCheck)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->schema([
                        TextEntry::make('is_active')
                            ->label(__('resources.warehouses.fields.is_active'))->badge()
                            ->color(fn (bool $state) => $state ? 'success' : 'danger')
                            ->formatStateUsing(fn (bool $state) => $state ? __('common.active') : __('common.inactive')),
                        TextEntry::make('users_count')
                            ->label(__('resources.warehouses.fields.assigned_staff'))
                            ->state(fn ($record) => $record->users()->count()),
                    ]),

                Section::make(__('resources.warehouses.infolist.assigned_staff'))
                    ->icon(Heroicon::UserGroup)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('users')
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])->schema([
                                    TextEntry::make('name')
                                        ->label(__('resources.users.fields.name'))
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                                    TextEntry::make('email')
                                        ->label(__('resources.users.fields.email'))
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                                    TextEntry::make('role')->badge()
                                        ->label(__('resources.users.fields.role'))
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                                ]),
                            ])
                            ->placeholder(__('resources.warehouses.empty_staff')),
                    ]),
            ]),
        ]);
    }
}
```

#### 7K.4 WarehouseResource.php

```php
namespace App\Filament\Resources\Warehouses;

use App\Filament\Resources\Warehouses\Pages\CreateWarehouse;
use App\Filament\Resources\Warehouses\Pages\EditWarehouse;
use App\Filament\Resources\Warehouses\Pages\ListWarehouses;
use App\Filament\Resources\Warehouses\Pages\ViewWarehouse;
use App\Filament\Resources\Warehouses\Schemas\WarehouseForm;
use App\Filament\Resources\Warehouses\Schemas\WarehouseInfolist;
use App\Filament\Resources\Warehouses\Tables\WarehousesTable;
use App\Models\Warehouse;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;
    protected static string | \UnitEnum | null $navigationGroup = 'SYSTEM ADMIN';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'name';
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedBuildingOffice;
    protected static string | \BackedEnum | null $activeNavigationIcon = Heroicon::BuildingOffice;

    public static function getModelLabel(): string
    {
        return __('resources.warehouses.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.warehouses.model.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.warehouses.navigation.label');
    }

    public static function form(Schema $schema): Schema
    {
        return WarehouseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WarehousesTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WarehouseInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['users'])
            ->withCount(['users', 'stockMovements'])
            ->when(
                ! auth()->user()->isAdmin() && ! auth()->user()->isAuditor(),
                function (Builder $q) {
                    $ids = auth()->user()->warehouses()->pluck('warehouses.id')->all();
                    $q->whereIn('warehouses.id', $ids);
                }
            );
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListWarehouses::route('/'),
            'create' => CreateWarehouse::route('/create'),
            'view'   => ViewWarehouse::route('/{record}'),
            'edit'   => EditWarehouse::route('/{record}/edit'),
        ];
    }
}
```

> Single source of truth: `§7K` owns the `WarehouseResource` definition for the Warehouses domain section. §18.1a records only the file contract and references this section — the class body is not repeated there.

---

### 7L. UserResource

**Model:** `App\Models\User` · **Group:** SYSTEM ADMIN · **Sort:** 2

#### 7L.1 UserForm.php

```php
namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('resources.users.fields.name'))
                ->prefixIcon(Heroicon::User)
                ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                ->required()->maxLength(255),

            TextInput::make('email')
                ->label(__('resources.users.fields.email'))
                ->prefixIcon(Heroicon::Envelope)
                ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                ->email()->required()->unique(ignoreRecord: true),

            TextInput::make('password')
                ->label(__('resources.users.fields.password'))
                ->prefixIcon(Heroicon::Key)
                ->password()->revealable()
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->dehydrated(fn ($state) => filled($state))
                ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                ->required(fn (string $operation) => $operation === 'create'),

            Select::make('role')
                ->label(__('resources.users.fields.role'))
                ->prefixIcon(Heroicon::ShieldCheck)
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->options(\App\Enums\UserRole::class)
                ->required(),

            Select::make('warehouses')
                ->label(__('resources.users.fields.warehouses'))
                ->relationship('warehouses', 'name')
                ->prefixIcon(Heroicon::BuildingOffice)
                ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                ->multiple()->searchable()->preload()
                ->helperText(__('resources.users.help.warehouses')),

            Toggle::make('is_active')
                ->label(__('resources.users.fields.is_active'))
                ->onIcon(Heroicon::CheckCircle)
                ->offIcon(Heroicon::XCircle)
                ->columnSpanFull()
                ->default(true),
        ]);
    }
}
```

#### 7L.2 UsersTable.php — **Standard Table + `stackedOnMobile()`**

```php
namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('resources.users.table.name'))
                    ->searchable()->sortable()->weight('bold'),

                TextColumn::make('email')
                    ->label(__('resources.users.table.email'))
                    ->searchable()->copyable()->visibleFrom('md'),

                TextColumn::make('role')
                    ->label(__('resources.users.table.role'))
                    ->badge()->sortable(),

                TextColumn::make('warehouses_count')
                    ->label(__('resources.users.table.warehouses'))
                    ->counts('warehouses')
                    ->numeric()->badge()->color('gray')->alignEnd(),

                IconColumn::make('is_active')
                    ->label(__('resources.users.table.active'))
                    ->boolean()
                    ->trueIcon(Heroicon::CheckCircle)
                    ->falseIcon(Heroicon::XCircle)
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('created_at')
                    ->label(__('resources.users.table.created'))
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label(__('resources.users.filters.role'))
                    ->options(\App\Enums\UserRole::class),
                SelectFilter::make('warehouse_id')
                    ->label(__('resources.users.filters.warehouse'))
                    ->relationship('warehouses', 'name')
                    ->searchable(),
                TernaryFilter::make('is_active')
                    ->label(__('resources.users.filters.is_active')),
            ])
            ->defaultSort('name')
            ->stackedOnMobile()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->recordActions([
                EditAction::make()
                    ->icon(Heroicon::PencilSquare)
                    ->modalWidth(Width::Large)
                    ->authorize('update'),

                DeleteAction::make()
                    ->icon(Heroicon::Trash)
                    ->authorize('delete'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->icon(Heroicon::Trash)->authorize('deleteAny'),
                ]),
            ]);
    }
}
```

#### 7L.3 UserResource.php

```php
namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static string | \UnitEnum | null $navigationGroup = 'SYSTEM ADMIN';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'name';
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;
    protected static string | \BackedEnum | null $activeNavigationIcon = Heroicon::Users;

    public static function getModelLabel(): string
    {
        return __('resources.users.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.users.model.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.users.navigation.label');
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['warehouses'])
            ->withCount('warehouses');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
```

> Single source of truth: `§7L` owns the `UserResource` definition for the Users domain section. §18.1a records only the file contract and references this section — the class body is not repeated there.

---

## 📐 Section 7M: Filament v5 Layout & Styling Components

### 7M.1 Grid System Fundamentals

```php
Grid::make(2)                                        // 2 columns on lg+
Grid::make(['default' => 1, 'md' => 2, 'xl' => 4])   // breakpoint array

TextInput::make('name')->columnSpan(2)               // 2 cols on lg+
TextInput::make('notes')->columnSpanFull()           // full width all devices
TextInput::make('sku')->columnSpan(['md' => 2, 'xl' => 1])
```

### 7M.2 Section Component

```php
Section::make(__('resources.transfer_requisitions.sections.routing'))
    ->icon(Heroicon::BuildingOffice)
    ->columnSpanFull()
    ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
    ->schema([
        // Components mirror the §7B.1 routing section fields.
        Select::make('from_warehouse_id')
            ->label(__('resources.transfer_requisitions.fields.from_warehouse'))
            ->prefixIcon(Heroicon::BuildingOffice)
            ->required(),
        Select::make('to_warehouse_id')
            ->label(__('resources.transfer_requisitions.fields.to_warehouse'))
            ->prefixIcon(Heroicon::BuildingOffice2)
            ->required()
            ->different('from_warehouse_id'),
    ])
```

### 7M.3 Fieldset Component

```php
Fieldset::make(__('resources.transfer_requisitions.fields.revision_item'))
    ->schema([
        Select::make('product_variant_id')
            ->label(__('resources.transfer_requisitions.fields.variant_sku'))
            ->prefixIcon(Heroicon::Tag)->required(),
        TextInput::make('qty')
            ->label(__('resources.transfer_requisitions.fields.qty'))
            ->prefixIcon(Heroicon::Hashtag)->numeric()->required(),
    ])
    ->columns(3)
```

### 7M.4 Tabs Component

```php
Tabs::make()
    ->persistTabInQueryString()
    ->tabs([
        // Components mirror the §7A.1 product form fields per tab.
        Tab::make(__('resources.products.tabs.identity'))->icon(Heroicon::Identification)->schema([
            TextInput::make('sku')
                ->label(__('resources.products.fields.sku'))
                ->prefixIcon(Heroicon::Tag)
                ->required(),
            TextInput::make('name')
                ->label(__('resources.products.fields.variant_name'))
                ->prefixIcon(Heroicon::Identification)
                ->required(),
        ]),
        Tab::make(__('resources.products.tabs.stock_pricing'))->icon(Heroicon::CurrencyDollar)->schema([
            TextInput::make('sale_price')
                ->label(__('resources.products.fields.sale_price'))
                ->prefixIcon(Heroicon::CurrencyDollar)
                ->numeric(),
        ]),
        Tab::make(__('resources.products.tabs.status'))->icon(Heroicon::CheckCircle)->schema([
            Toggle::make('is_active')
                ->label(__('resources.products.fields.is_active')),
        ]),
    ])
```

### 7M.5 Side-by-Side Pairs (Split Component)

Form example (unequal-width side-by-side fields — `Split` stacks on mobile, §7N.1):

```php
Split::make([
    TextInput::make('first_name')->required(),
    TextInput::make('last_name')->required(),
])
    ->from('sm')
```

Infolist example (sign-off key-value pairs — entries mirror the §7B.4 infolist sign-off fields, which stack as plain `TextEntry` rows inside the sign-off `Section`; use `Split` only when two entries must sit side by side):

```php
Split::make([
    TextEntry::make('requestedBy.name')
        ->label(__('resources.transfer_requisitions.fields.requested_by'))
        ->icon(Heroicon::User)
        ->placeholder(__('resources.transfer_requisitions.placeholders.system_initialized')),
    TextEntry::make('approvedBy.name')
        ->label(__('resources.transfer_requisitions.fields.approved_by'))
        ->icon(Heroicon::Check)
        ->placeholder(__('resources.transfer_requisitions.placeholders.pending_approval')),
])
    ->from('sm')
```

The same shape applies to the sign-off sections in `PurchaseOrderInfolist` (§7G.4: `orderedBy`, `receivedBy`, `ordered_at`, `received_at`) and `SalesOrderInfolist` (§7H.4: `orderedBy`, `dispatchedBy`, `confirmed_at`, `dispatched_at`), with the corresponding `resources.purchase_orders.fields.*` / `resources.sales_orders.fields.*` keys.

> Side-by-side layout uses `Split::make([...])->from('sm')` (§7N.1) throughout this blueprint. `Flex::justify('between')` is not used: no other section depends on it, and `Split`/`Grid` already cover every side-by-side case.

### 7M.6 Layout Component Selection Guide

| Scenario | Recommended Component |
|---|---|
| Two side-by-side fields with equal weight | `Grid::make(2)` |
| Full-width field below a 2-col row | `TextInput::make(...)->columnSpanFull()` |
| Themed card with heading + description | `Section::make(__('...'))->icon(Heroicon::...)->description(__('...'))` |
| Lightweight grouping without card chrome | `Fieldset::make(__('...'))` |
| Reduce visual clutter in long forms | `Tabs::make()->tabs([...])` with `->icon()` on each tab |
| Unequal-width side-by-side fields | `Split::make([...])->from('sm')` (§7M.5) |
| Sign-off rows in infolist | Stacked `TextEntry` rows in the sign-off `Section` per §7B.4; `Split::make([...])->from('sm')` only for pairs that must sit side by side |
| Repeater item rendered as a card | Wrap item schema in `Section::make()` and set `->columns(1)` on the Repeater |
| Wizard step with internal grouping | `Section` inside each `Step::make(...)->schema([...])` with step `->icon()` |

### 7M.7 Styling Consistency Rules

1. Wizard steps use `Section` with `->icon()`; the `Step` itself carries `->icon()`.
2. Infolists use `Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])` as outer wrapper.
3. Line-item repeaters use `->columns(['default' => 1, 'md' => 2, 'xl' => 4])`.
4. `Split::make([...])->from('sm')` for side-by-side pairs in forms and infolists (§7M.5); sign-off sections stack `TextEntry` rows per §7B.4.
5. Tabs use `->persistTabInQueryString()` for 3+ tabs.
6. `columnSpanFull()` for full-width fields on all breakpoints.
7. `Section::make()` with no heading for repeater item wrappers.
8. Heroicons on every Section, Tab, Step, Action, and semantically meaningful form field.

---

## 📐 Section 7N: Table Architecture — Card vs. Standard

### 7N.1 The Card Layout API

```php
// Native card layout
->contentGrid([
    'md' => 2, // 2 cards per row on tablet
    'xl' => 3, // 3 cards per row on desktop
])

// Card internal composition
Stack::make([...])->space(3)      // vertical grouping inside a card
Split::make([...])->from('md')    // side-by-side within a Stack, stacks on mobile
```

### 7N.2 The Standard Table Responsive Primitive

```php
->stackedOnMobile()               // preserves dense table on desktop, stacks on mobile
```

### 7N.3 Decision Matrix

| Table Shape | Primary Use Case | Presentation |
|---|---|---|
| **Document** (requisition, PO, SO, direct transfer) | Browse & action small set of rich records | `->contentGrid(['md' => 2, 'xl' => 3])` |
| **Ledger** (stock movements, loss ledgers) | Scan, sort, filter large set of homogeneous rows | Standard table + `->stackedOnMobile()` |
| **Monitor** (in-transits) | Live operational queue, read-only | Standard table + `->stackedOnMobile()` |
| **Master Data (high cardinality)** (products, warehouses) | Browse with visual richness | `->contentGrid()` |
| **Master Data (low cardinality)** (suppliers, customers) | Browse small set with rich detail | `->contentGrid()` |
| **Comparison Surface** (users) | Compare rows against each other by column | Standard table + `->stackedOnMobile()` |

### 7N.4 Per-Resource Verdict (Council Recorded)

| Resource | Layout | Rationale |
|---|---|---|
| `ProductsTable` | ✅ Card | Visual catalog browse |
| `WarehousesTable` | ✅ Card | Small set, rich detail |
| `TransferRequisitionsTable` | ✅ Card | Document-centric records |
| `DirectTransfersTable` | ✅ Card | Document-shaped: header + N line items; small set; audit browsing |
| `PurchaseOrdersTable` | ✅ Card | Document-centric records |
| `SalesOrdersTable` | ✅ Card | Document-centric records |
| `SuppliersTable` | ✅ Card | Small master-data set |
| `CustomersTable` | ✅ Card | Small master-data set |
| `InTransitsTable` | ⚠️ Standard | Read-only monitor; high row count; scanning task |
| `StockMovementsTable` | ❌ Standard | Append-only ledger; hundreds of thousands of rows; signed quantities need column alignment |
| `LossLedgersTable` | ❌ Standard | Financial audit trail; `summarize()` aggregate |
| `UsersTable` | ⚠️ Standard | Comparison task, not browsing task |

### 7N.5 Cross-Cutting Table Rules

1. **Every table declares `->defaultSort()`** (F27).
2. **Every relational column has an eager-loaded relation** in `getEloquentQuery()` (F28).
3. **Card tables declare `->defaultPaginationPageOption(12)` and `->paginated([12, 24, 48])`** (F29).
4. **Ledger tables paginate at 50 with `->paginated([25, 50, 100])`.**
5. **Card tables declare no bulk actions** (F30). The native renderer does not render per-card checkboxes. To enable bulk selection on card tables, install `mkdev-grid-card-layout` and add the corresponding `BulkActionGroup` per resource.
6. **Signed quantity columns are color-coded** (`success` for positive, `danger` for negative).
7. **Audit filters are policy-gated, never visibility-gated.**
8. **Record-action alignment is global, not per-resource.** `Table::configureUsing()` in `AppServiceProvider::configureTable()` (§17.2) sets `->recordActionsAlignment('end')` for every table. No table sets it individually. `RecordActionsPosition::BeforeColumns` is NOT used anywhere.

### 7N.6 Plugin Option

If per-card bulk selection is required, `mkdev-grid-card-layout` wraps the same `table()` definition and adds checkboxes without rewriting resources. It is the only council-sanctioned plugin for card tables.

---

## 📐 Section 7O: Responsive Column Spans Across All Filament v5 Constructs

### 7O.1 Breakpoint Reference (Tailwind CSS)

| Breakpoint | Min Width | Typical Device |
|---|---|---|
| `default` | 0px | Mobile phone |
| `sm` | 640px | Large phone / small tablet |
| `md` | 768px | Tablet portrait |
| `lg` | 1024px | Tablet landscape / small laptop |
| `xl` | 1280px | Desktop |
| `2xl` | 1536px | Large desktop |

### 7O.2 Forms — Field-Level Responsive Spans

Every form field declares `->columnSpan(['default' => X, 'md' => Y, 'xl' => Z])`. The canonical pattern across all forms is:

- Full-width primary identifiers (`name`, `location`, `address`, `notes`) → `default=1, md=2, xl=2`
- Paired fields (warehouse selects, unit + ratio, quantity + cost) → `default=1, md=1, xl=1`
- Repeater line-item variant selectors → `default=1, md=2, xl=2`
- Repeater numeric fields (unit, ratio, qty, cost, preview) → `default=1, md=1, xl=1`

### 7O.3 Wizards — Step-Level Responsive Configuration

| Wizard Step | Inner Container `columns()` |
|---|---|
| Routing Pathways | `['default' => 1, 'md' => 2, 'xl' => 2]` |
| Material Manifest (req/PO) | `['default' => 1, 'md' => 2, 'xl' => 4]` |
| Review & Verify | `['default' => 1]` |
| Location Mapping | `['default' => 1, 'md' => 2, 'xl' => 2]` |
| Stock Allocation | `['default' => 1, 'md' => 2, 'xl' => 4]` |
| Supplier & Warehouse | `['default' => 1, 'md' => 2, 'xl' => 2]` |
| Customer & Warehouse | `['default' => 1, 'md' => 2, 'xl' => 2]` |

### 7O.4 Infolists

Infolists use `Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])` as outer wrapper. Document profile sections occupy `columnSpan=2`; sign-off sections occupy `columnSpan=1`. Line-item repeatable entries use `RepeatableEntry::make(...)->table([TableColumn::make(...), ...])->schema([TextEntry::make(...), ...])` (flat line items); numeric entries carry `->alignEnd()`. Nested item→revisions repeatables likewise use `->table([...])` for the inner level.

### 7O.5 Dashboard Widgets

Dashboard `getColumns()`:
```php
public function getColumns(): int | string | array
{
    return ['default' => 1, 'md' => 2, 'xl' => 4];
}
```

### 7O.6 Tables — Responsive Column Visibility

| Column Type | Mobile | Tablet | Desktop |
|---|---|---|---|
| Primary identifier | ✅ | ✅ | ✅ |
| Status badge | ✅ | ✅ | ✅ |
| Secondary entity name | ❌ `visibleFrom('md')` | ✅ | ✅ |
| Line counts, dates, notes | ❌ `visibleFrom('lg')` | ❌ | ✅ |
| Money amounts | ✅ | ✅ | ✅ |
| Actor names (createdBy) | ❌ `visibleFrom('xl')` | ❌ | ❌ at lg, ✅ at xl |

### 7O.7 Canonical Breakpoint Convention (F24)

| Tier | Breakpoint | Target | Behaviour |
|---|---|---|---|
| Tier 1 | `default` (< 768px) | Mobile | Everything stacks to 1 column |
| Tier 2 | `md` (≥ 768px) | Tablet | Wide components span 2 columns |
| Tier 3 | `xl` (≥ 1280px) | Desktop | Full bento layout |

### 7O.8 Styling Consistency Rules

1. Every field declares `->columnSpan([...])` with an explicit `default` key.
2. Every container declares `->columns([...])` with an explicit `default` key.
3. `columnSpanFull()` for single-field full-width rows.
4. `columnSpan(['md' => X, 'xl' => Y])` is the canonical responsive pattern.
5. Infolists use `Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])` outer wrapper.
6. Widgets use `$columnSpan` as a breakpoint array.
7. Dashboard `getColumns()` returns a breakpoint array.
8. Tables use `visibleFrom()` for mobile hiding.
9. Repeaters declare `->columns()` plus per-field `columnSpan()`.
10. `columnStart()` and `columnOrder()` reserved for advanced asymmetric layouts.

---

## 🛡️ Section 8: Authorization — Policies

### 8.1 ProductPolicy

```php
namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Product $product): bool { return true; }
    public function create(User $user): bool { return $user->isAdmin(); }
    public function update(User $user, Product $product): bool { return $user->isAdmin(); }
    public function delete(User $user, Product $product): bool { return $user->isAdmin(); }
    public function deleteAny(User $user): bool { return $user->isAdmin(); }
    public function restore(User $user, Product $product): bool { return $user->isAdmin(); }
    public function restoreAny(User $user): bool { return $user->isAdmin(); }
    public function forceDelete(User $user, Product $product): bool { return $user->isAdmin(); }
    public function forceDeleteAny(User $user): bool { return $user->isAdmin(); }
}
```

### 8.2 ProductVariantPolicy

```php
namespace App\Policies;

use App\Models\ProductVariant;
use App\Models\User;

class ProductVariantPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, ProductVariant $variant): bool { return true; }
    public function create(User $user): bool { return $user->isAdmin(); }
    public function update(User $user, ProductVariant $variant): bool { return $user->isAdmin(); }
    public function delete(User $user, ProductVariant $variant): bool { return $user->isAdmin(); }
    public function deleteAny(User $user): bool { return $user->isAdmin(); }
    public function restore(User $user, ProductVariant $variant): bool { return $user->isAdmin(); }
    public function restoreAny(User $user): bool { return $user->isAdmin(); }
    public function forceDelete(User $user, ProductVariant $variant): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }
    /**
     * Manual stock adjustments (QuickStockAdjustmentAction) are an operational
     * duty, not catalog management: non-auditor warehouse staff with at least
     * one warehouse assignment may adjust (the action's warehouse select is
     * scoped to assigned warehouses), while catalog create/update/delete stay
     * admin-only above. Auditors are read-only and never mutate stock, even
     * when assigned to warehouses (§8.3, §8.7, §8.8).
     * Per A8, this role distinction lives here — never in ->visible().
     */
    public function adjustStock(User $user, ProductVariant $variant): bool
    {
        return $user->isAdmin() || (! $user->isAuditor() && $user->warehouses()->exists());
    }
}
```

### 8.3 TransferRequisitionPolicy

```php
namespace App\Policies;

use App\Models\TransferRequisition;
use App\Models\User;

class TransferRequisitionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TransferRequisition $r): bool
    {
        return $user->isAdmin()
            || $user->isAuditor()
            || $user->warehouses->contains($r->from_warehouse_id)
            || $user->warehouses->contains($r->to_warehouse_id);
    }

    public function create(User $user): bool
    {
        // Both warehouse selects are scoped to the user's assigned
        // warehouses and must differ (§7B.1) — a single-warehouse user
        // could see the action but never submit a valid requisition,
        // so non-admin create requires at least two assignments.
        // Auditors are read-only and never create operational documents,
        // even when assigned to warehouses.
        return $user->isAdmin() || (! $user->isAuditor() && $user->warehouses()->count() >= 2);
    }

    public function update(User $user, TransferRequisition $r): bool
    {
        return ! $user->isAuditor()
            && $r->status === \App\Enums\TransferRequisitionStatus::Draft
            && $user->warehouses->contains($r->from_warehouse_id);
    }

    public function delete(User $user, TransferRequisition $r): bool
    {
        return $user->isAdmin()
            && in_array($r->status, [
                \App\Enums\TransferRequisitionStatus::Draft,
                \App\Enums\TransferRequisitionStatus::Cancelled,
            ], true);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, TransferRequisition $r): bool
    {
        return $user->isAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, TransferRequisition $r): bool
    {
        return $user->isAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function submitRequest(User $user, TransferRequisition $r): bool
    {
        // Auditors are read-only and never mutate operational documents,
        // even when assigned to warehouses.
        return ! $user->isAuditor()
            && $r->status === \App\Enums\TransferRequisitionStatus::Draft
            && $user->warehouses->contains($r->from_warehouse_id);
    }

    public function confirm(User $user, TransferRequisition $r): bool
    {
        return ! $user->isAuditor() && ($user->isAdmin()
            || $user->warehouses->contains($r->to_warehouse_id));
    }

    public function dispatch(User $user, TransferRequisition $r): bool
    {
        return ! $user->isAuditor() && ($user->isAdmin()
            || $user->warehouses->contains($r->from_warehouse_id));
    }

    public function receive(User $user, TransferRequisition $r): bool
    {
        return ! $user->isAuditor() && ($user->isAdmin()
            || $user->warehouses->contains($r->to_warehouse_id));
    }

    public function recordLoss(User $user, TransferRequisition $r): bool
    {
        return ! $user->isAuditor() && ($user->isAdmin()
            || $user->warehouses->contains($r->to_warehouse_id));
    }

    public function cancel(User $user, TransferRequisition $r): bool
    {
        return ! $user->isAuditor()
            && $r->canBeCancelled()
            && ($user->isAdmin() || $user->warehouses->contains($r->from_warehouse_id));
    }

    public function negotiate(User $user, TransferRequisition $r): bool
    {
        return ! $user->isAuditor() && in_array($r->status, [
            \App\Enums\TransferRequisitionStatus::Requested,
            \App\Enums\TransferRequisitionStatus::UnderReviewFulfiller,
            \App\Enums\TransferRequisitionStatus::UnderReviewRequestor,
        ], true) && (
            $user->warehouses->contains($r->from_warehouse_id)
            || $user->warehouses->contains($r->to_warehouse_id)
        );
    }

    public function acceptRevision(User $user, TransferRequisition $r): bool
    {
        return ! $user->isAuditor() && in_array($r->status, [
            \App\Enums\TransferRequisitionStatus::Requested,
            \App\Enums\TransferRequisitionStatus::UnderReviewFulfiller,
            \App\Enums\TransferRequisitionStatus::UnderReviewRequestor,
        ], true) && (
            $user->warehouses->contains($r->from_warehouse_id)
            || $user->warehouses->contains($r->to_warehouse_id)
        );
    }

    public function rejectRevision(User $user, TransferRequisition $r): bool
    {
        return ! $user->isAuditor() && in_array($r->status, [
            \App\Enums\TransferRequisitionStatus::Requested,
            \App\Enums\TransferRequisitionStatus::UnderReviewFulfiller,
            \App\Enums\TransferRequisitionStatus::UnderReviewRequestor,
        ], true) && (
            $user->warehouses->contains($r->from_warehouse_id)
            || $user->warehouses->contains($r->to_warehouse_id)
        );
    }

    /**
     * `$modelClass` is the `Model::class` string forwarded by the call
     * site (`->can('viewAuditFilters', Model::class)`): Laravel resolves
     * the owning policy from that class-string and passes it through as
     * the second argument. The decision itself is role-only (A8).
     */
    public function viewAuditFilters(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor();
    }
}
```

### 8.4 InTransitPolicy

```php
namespace App\Policies;

use App\Models\InTransit;
use App\Models\User;

class InTransitPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, InTransit $t): bool
    {
        // Per-record scope mirrors the §18.1a list query: Admins/Auditors
        // see all rows; WarehouseStaff must touch either endpoint of the
        // parent requisition (same OR-scope as `TransferRequisitionPolicy::view()`).
        return $user->isAdmin()
            || $user->isAuditor()
            || $user->warehouses->contains($t->transferRequisition->from_warehouse_id)
            || $user->warehouses->contains($t->transferRequisition->to_warehouse_id);
    }
}
```

### 8.5 StockMovementPolicy

```php
namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;

class StockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, StockMovement $m): bool
    {
        // Per-record scope mirrors the §18.1a list query: Admins/Auditors
        // see all rows; WarehouseStaff must be assigned to the movement's
        // warehouse.
        return $user->isAdmin()
            || $user->isAuditor()
            || $user->warehouses->contains($m->warehouse_id);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StockMovement $m): bool
    {
        return false;
    }

    public function delete(User $user, StockMovement $m): bool
    {
        return false;
    }

    /**
     * `$modelClass` is the `Model::class` string forwarded by the call
     * site (`->can('viewAuditFilters', Model::class)`): Laravel resolves
     * the owning policy from that class-string and passes it through as
     * the second argument. The decision itself is role-only (A8).
     */
    public function viewAuditFilters(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor();
    }

    // NOTE: createDirectTransfer() removed — see DirectTransferPolicy::create().
}
```

### 8.6 LossLedgerPolicy

```php
namespace App\Policies;

use App\Models\LossLedger;
use App\Models\User;

class LossLedgerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LossLedger $l): bool
    {
        // Per-record scope mirrors the §18.1a list query: Admins/Auditors
        // see all rows; WarehouseStaff must be assigned to the loss's
        // warehouse.
        return $user->isAdmin()
            || $user->isAuditor()
            || $user->warehouses->contains($l->warehouse_id);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, LossLedger $l): bool
    {
        return false;
    }

    public function delete(User $user, LossLedger $l): bool
    {
        return false;
    }

    /**
     * `$modelClass` is the `Model::class` string forwarded by the call
     * site (`->can('viewAuditFilters', Model::class)`): Laravel resolves
     * the owning policy from that class-string and passes it through as
     * the second argument. The decision itself is role-only (A8).
     */
    public function viewAuditFilters(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor();
    }
}
```

### 8.7 PurchaseOrderPolicy

```php
namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PurchaseOrder $o): bool
    {
        return $user->isAdmin() || $user->isAuditor() || $user->warehouses->contains($o->warehouse_id);
    }

    public function create(User $user): bool
    {
        // Auditors are read-only and never create operational documents,
        // even when assigned to warehouses.
        return $user->isAdmin() || (! $user->isAuditor() && $user->warehouses()->exists());
    }

    public function update(User $user, PurchaseOrder $o): bool
    {
        return ! $user->isAuditor()
            && $o->status === \App\Enums\PurchaseOrderStatus::Draft
            && $user->warehouses->contains($o->warehouse_id);
    }

    public function delete(User $user, PurchaseOrder $o): bool
    {
        // Auditors are read-only and never mutate operational documents,
        // even when assigned to warehouses.
        return ! $user->isAuditor() && in_array($o->status, [
            \App\Enums\PurchaseOrderStatus::Draft,
            \App\Enums\PurchaseOrderStatus::Cancelled,
        ], true) && $user->warehouses->contains($o->warehouse_id);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, PurchaseOrder $o): bool
    {
        return $user->isAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, PurchaseOrder $o): bool
    {
        return $user->isAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function orderPurchase(User $user, PurchaseOrder $o): bool
    {
        return ! $user->isAuditor() && ($user->isAdmin()
            || $user->warehouses->contains($o->warehouse_id));
    }

    public function receivePurchase(User $user, PurchaseOrder $o): bool
    {
        return ! $user->isAuditor() && ($user->isAdmin()
            || $user->warehouses->contains($o->warehouse_id));
    }

    public function cancelPurchase(User $user, PurchaseOrder $o): bool
    {
        return ! $user->isAuditor() && $o->canBeCancelled()
            && ($user->isAdmin() || $user->warehouses->contains($o->warehouse_id));
    }

    /**
     * `$modelClass` is the `Model::class` string forwarded by the call
     * site (`->can('viewAuditFilters', Model::class)`): Laravel resolves
     * the owning policy from that class-string and passes it through as
     * the second argument. The decision itself is role-only (A8).
     */
    public function viewAuditFilters(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor();
    }
}
```

### 8.8 SalesOrderPolicy

```php
namespace App\Policies;

use App\Models\SalesOrder;
use App\Models\User;

class SalesOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SalesOrder $o): bool
    {
        return $user->isAdmin() || $user->isAuditor() || $user->warehouses->contains($o->warehouse_id);
    }

    public function create(User $user): bool
    {
        // Auditors are read-only and never create operational documents,
        // even when assigned to warehouses.
        return $user->isAdmin() || (! $user->isAuditor() && $user->warehouses()->exists());
    }

    public function update(User $user, SalesOrder $o): bool
    {
        return ! $user->isAuditor()
            && $o->status === \App\Enums\SalesOrderStatus::Draft
            && $user->warehouses->contains($o->warehouse_id);
    }

    public function delete(User $user, SalesOrder $o): bool
    {
        // `->visible()` is not an authorization boundary: the server-side
        // rule mirrors the resource UI (Draft/Cancelled only) and the
        // Draft/Cancelled restriction in TransferRequisitionPolicy and
        // PurchaseOrderPolicy.
        return $user->isAdmin() && in_array($o->status, [
            \App\Enums\SalesOrderStatus::Draft,
            \App\Enums\SalesOrderStatus::Cancelled,
        ], true);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, SalesOrder $o): bool
    {
        return $user->isAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, SalesOrder $o): bool
    {
        return $user->isAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function confirmSalesOrder(User $user, SalesOrder $o): bool
    {
        // Auditors are read-only and never mutate operational documents,
        // even when assigned to warehouses.
        return ! $user->isAuditor() && ($user->isAdmin()
            || $user->warehouses->contains($o->warehouse_id));
    }

    public function dispatchSale(User $user, SalesOrder $o): bool
    {
        return ! $user->isAuditor() && ($user->isAdmin()
            || $user->warehouses->contains($o->warehouse_id));
    }

    public function recordSalesReturn(User $user, SalesOrder $o): bool
    {
        return ! $user->isAuditor() && ($user->isAdmin()
            || $user->warehouses->contains($o->warehouse_id));
    }

    public function cancelSalesOrder(User $user, SalesOrder $o): bool
    {
        return ! $user->isAuditor() && $o->canBeCancelled()
            && ($user->isAdmin() || $user->warehouses->contains($o->warehouse_id));
    }

    /**
     * `$modelClass` is the `Model::class` string forwarded by the call
     * site (`->can('viewAuditFilters', Model::class)`): Laravel resolves
     * the owning policy from that class-string and passes it through as
     * the second argument. The decision itself is role-only (A8).
     */
    public function viewAuditFilters(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor();
    }
}
```

### 8.9 SupplierPolicy

```php
namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Supplier $s): bool { return true; }
    public function create(User $user): bool { return $user->isAdmin(); }
    public function update(User $user, Supplier $s): bool { return $user->isAdmin(); }
    public function delete(User $user, Supplier $s): bool { return $user->isAdmin(); }
    public function deleteAny(User $user): bool { return $user->isAdmin(); }
    public function restore(User $user, Supplier $s): bool { return $user->isAdmin(); }
    public function restoreAny(User $user): bool { return $user->isAdmin(); }
    public function forceDelete(User $user, Supplier $s): bool { return $user->isAdmin(); }
    public function forceDeleteAny(User $user): bool { return $user->isAdmin(); }
}
```

### 8.10 CustomerPolicy

```php
namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Customer $customer): bool { return true; }
    public function create(User $user): bool { return $user->isAdmin(); }
    public function update(User $user, Customer $customer): bool { return $user->isAdmin(); }
    public function delete(User $user, Customer $customer): bool { return $user->isAdmin(); }
    public function deleteAny(User $user): bool { return $user->isAdmin(); }
    public function restore(User $user, Customer $customer): bool { return $user->isAdmin(); }
    public function restoreAny(User $user): bool { return $user->isAdmin(); }
    public function forceDelete(User $user, Customer $customer): bool { return $user->isAdmin(); }
    public function forceDeleteAny(User $user): bool { return $user->isAdmin(); }
}
```

### 8.11 WarehousePolicy

```php
namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        // Warehouse staff may list only their assigned warehouses — the
        // database-level restriction lives in the resource query scope
        // (§20.1). `viewAny` must therefore pass for staff with assignments.
        return $user->isAdmin()
            || $user->isAuditor()
            || $user->warehouses()->exists();
    }

    public function view(User $user, Warehouse $w): bool
    {
        return $user->isAdmin()
            || $user->isAuditor()
            || $user->warehouses->contains($w->id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Warehouse $w): bool
    {
        return $user->isAdmin();
    }

    /**
     * A warehouse may only be deleted when it has no ledger history and is
     * not referenced by any document (PO, SO, TR, or direct transfer) and
     * has no loss ledger rows.
     * This prevents raw FK violations from bubbling up as 500s.
     */
    public function delete(User $user, Warehouse $w): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        if ($w->stockMovements()->exists()) {
            return false;
        }

        if ($w->purchaseOrders()->exists()) {
            return false;
        }

        if ($w->salesOrders()->exists()) {
            return false;
        }

        if ($w->transferRequisitionsFrom()->exists()) {
            return false;
        }

        if ($w->transferRequisitionsTo()->exists()) {
            return false;
        }

        if ($w->directTransfersFrom()->exists()) {
            return false;
        }

        if ($w->directTransfersTo()->exists()) {
            return false;
        }

        if ($w->lossLedgers()->exists()) {
            return false;
        }

        return true;
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
```

### 8.12 UserPolicy

```php
namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin() || $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->id !== $model->id;
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
```

### 8.13 DirectTransferPolicy

```php
namespace App\Policies;

use App\Models\DirectTransfer;
use App\Models\User;

class DirectTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DirectTransfer $t): bool
    {
        return $user->isAdmin()
            || $user->isAuditor()
            || ($user->warehouses->contains($t->from_warehouse_id)
                && $user->warehouses->contains($t->to_warehouse_id));
    }

    public function create(User $user): bool
    {
        // Admins hold global operational scope with a possibly empty
        // `user_warehouse` pivot, so they must not be gated on assignments.
        // Both warehouse selects are scoped to the user's assigned
        // warehouses and must differ (§7C.1), and the service requires
        // BOTH endpoints in the actor's assigned set (§6.2) — a
        // single-warehouse user could see the action but never submit a
        // valid direct transfer, so non-admin create requires at least
        // two assignments (same precondition as
        // `TransferRequisitionPolicy::create()`).
        // Auditors are read-only and never create operational documents,
        // even when assigned to warehouses.
        return $user->isAdmin()
            || (! $user->isAuditor() && $user->warehouses()->count() >= 2);
    }

    public function update(User $user, DirectTransfer $t): bool
    {
        return false; // fire-and-forget: no edit
    }

    public function delete(User $user, DirectTransfer $t): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * `$modelClass` is the `Model::class` string forwarded by the call
     * site (`->can('viewAuditFilters', Model::class)`): Laravel resolves
     * the owning policy from that class-string and passes it through as
     * the second argument. The decision itself is role-only (A8).
     */
    public function viewAuditFilters(User $user): bool
    {
        return $user->isAdmin() || $user->isAuditor();
    }
}
```

### 8.14 Policy Registration

Every policy is registered once via the `$policies` map on
`AuthServiceProvider`, consumed by `registerPolicies()` in `boot()`.
§17.4 is the full canonical listing — no bindings are duplicated here.

---

## 🔍 Section 9: Shared Filter Architecture

```php
namespace App\Filament\Support\Filters;

use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class AdminReviewFilters
{
    public static function warehouse(string $relationshipName = 'warehouse'): SelectFilter
    {
        return SelectFilter::make('warehouse_id')
            ->label(__('common.warehouse'))
            ->relationship($relationshipName, 'name')
            ->searchable()
            ->preload();
    }

    /**
     * Period filter. Must be policy-gated at the call site via
     * `->visible(fn (): bool => auth()->user()?->can('viewAuditFilters', Model::class) ?? false)`
     * against the owning model. `Filter` exposes no `authorize()` method
     * (authorization is an action concern), so the gate is consulted
     * through `visible()` — the `viewAuditFilters` policy ability remains
     * the single home for the permission decision (A8). The `Model::class`
     * argument both selects the owning policy and arrives as the policy
     * method's `$modelClass` parameter.
     */
    public static function period(string $dateColumn): Filter
    {
        return Filter::make('period')
            ->label(__('common.period'))
            ->schema([
                Select::make('preset')
                    ->label(__('common.period'))
                    ->options([
                        'today'         => __('common.periods.today'),
                        'this_week'     => __('common.periods.this_week'),
                        'this_month'    => __('common.periods.this_month'),
                        'this_year'     => __('common.periods.this_year'),
                        'specific_date' => __('common.periods.specific_date'),
                        'custom_range'  => __('common.periods.custom_range'),
                    ])
                    ->default(null)
                    ->native(false)
                    ->columnSpan(['default' => 1, 'md' => 1])
                    ->live(),

                DatePicker::make('specific_date')
                    ->label(__('common.date'))
                    ->columnSpan(['default' => 1, 'md' => 1])
                    ->visible(fn (Get $get) => $get('preset') === 'specific_date'),

                DatePicker::make('range_from')
                    ->label(__('common.from'))
                    ->columnSpan(['default' => 1, 'md' => 1])
                    ->visible(fn (Get $get) => $get('preset') === 'custom_range'),

                DatePicker::make('range_until')
                    ->label(__('common.until'))
                    ->columnSpan(['default' => 1, 'md' => 1])
                    ->visible(fn (Get $get) => $get('preset') === 'custom_range'),
            ])
            ->columns(['default' => 1, 'md' => 2])
            ->query(function (Builder $query, array $data) use ($dateColumn): Builder {
                return match ($data['preset'] ?? null) {
                    'today'         => $query->whereDate($dateColumn, now()->toDateString()),
                    'this_week'     => $query->whereBetween($dateColumn, [now()->startOfWeek(), now()->endOfWeek()]),
                    'this_month'    => $query->whereBetween($dateColumn, [now()->startOfMonth(), now()->endOfMonth()]),
                    'this_year'     => $query->whereBetween($dateColumn, [now()->startOfYear(), now()->endOfYear()]),
                    'specific_date' => $query->when(
                        $data['specific_date'] ?? null,
                        fn (Builder $q, $date) => $q->whereDate($dateColumn, $date),
                    ),
                    'custom_range' => $query
                        ->when($data['range_from'] ?? null, fn (Builder $q, $date) => $q->whereDate($dateColumn, '>=', $date))
                        ->when($data['range_until'] ?? null, fn (Builder $q, $date) => $q->whereDate($dateColumn, '<=', $date)),
                    default => $query,
                };
            })
            ->indicateUsing(function (array $data): string|array|null {
                return match ($data['preset'] ?? null) {
                    'today'         => __('common.periods.today'),
                    'this_week'     => __('common.periods.this_week'),
                    'this_month'    => __('common.periods.this_month'),
                    'this_year'     => __('common.periods.this_year'),
                    'specific_date' => isset($data['specific_date'])
                        ? __('common.periods.on_date', ['date' => Carbon::parse($data['specific_date'])->toFormattedDateString()])
                        : null,
                    'custom_range'  => array_filter([
                        isset($data['range_from'])
                            ? Indicator::make(__('common.periods.from_date', ['date' => Carbon::parse($data['range_from'])->toFormattedDateString()]))
                                ->removeField('range_from')
                            : Indicator::make(__('common.periods.no_lower_bound'))
                                ->removeField('range_from'),
                        isset($data['range_until'])
                            ? Indicator::make(__('common.periods.until_date', ['date' => Carbon::parse($data['range_until'])->toFormattedDateString()]))
                                ->removeField('range_until')
                            : Indicator::make(__('common.periods.no_upper_bound'))
                                ->removeField('range_until'),
                    ]),
                    default => null,
                };
            });
    }
}
```

---

## 📊 Section 10: Dashboard — Bento Grid & Widgets

### Colour Palette

| Token | Value | Usage |
|---|---|---|
| primary | #3b82f6 | Primary action execution triggers |
| surface | #ffffff | Card wrappers |
| surface-muted | #fafafa | Hover states |
| border | #e4e4e7 | 1px solid borders |
| text-primary | #18181b | Headings |
| text-secondary | #71717a | Labels |
| danger | #ef4444 | Loss, force-delete |
| warning | #f59e0b | Partial intake |
| success | #22c55e | Completed transfers |

### Elevation Rules

- Flat rest states (1px solid zinc-200).
- Shadows only on modal focus (`shadow-lg`).
- No zebra striping — thin dividers + hover highlights.

### Glassmorphic Bento Grid — Full Layout (All 9 Widgets)

```
┌─────────────────────────────┬───────────────┬───────────────┐
│                             │               │               │
│     StatsOverview           │   LowStock    │   Recent      │
│     (2 cols × 1 row)        │   (1 col)     │   Movements   │
│                             │               │   (1 col)     │
├─────────────────────────────┼───────────────┴───────────────┤
│                             │                               │
│     Sales Revenue Trend     │     Active In-Transit         │
│     (2 cols × 1 row)        │     (2 cols × 1 row)          │
├─────────────────────────────┼───────────────┬───────────────┤
│                             │               │               │
│     Sales vs Purchases      │   Top Selling │   Pending     │
│     (2 cols × 1 row)        │   Variants    │   Fulfillment │
├─────────────────────────────┴───────────────┴───────────────┤
│                    Quick Actions (4 cols)                   │
└─────────────────────────────────────────────────────────────┘
```

### Grid Layout Breakdown

| Row | Column Spans | Widgets |
|---|---|---|
| Row 1 | `[2, 1, 1]` | StatsOverview · LowStock · RecentMovements |
| Row 2 | `[2, 2]` | SalesRevenueTrend · ActiveInTransit |
| Row 3 | `[2, 1, 1]` | SalesVsPurchases · TopSellingVariants · PendingFulfillment |
| Row 4 | `[4]` | Quick Actions |

### Widget Column Span Configuration

| Widget | Mobile | `md` | `xl` | `$sort` |
|---|---|---|---|---|
| `StatsOverviewWidget` | 1 | 2 | 2 | 1 |
| `LowStockAlertsWidget` | 1 | 1 | 1 | 2 |
| `RecentMovementsWidget` | 1 | 1 | 1 | 3 |
| `SalesRevenueTrendWidget` | 1 | 2 | 2 | 4 |
| `ActiveInTransitWidget` | 1 | 2 | 2 | 5 |
| `SalesVsPurchasesWidget` | 1 | 2 | 2 | 6 |
| `TopSellingVariantsWidget` | 1 | 1 | 1 | 7 |
| `PendingFulfillmentWidget` | 1 | 1 | 1 | 8 |
| `QuickActionsWidget` | 1 | 2 | 4 | 9 |

### Widget Definitions

| Widget | Data Source | Cache TTL | Type | `$columnSpan` |
|---|---|---|---|---|
| `StatsOverviewWidget` | Total On-Hand, Pending Requisitions, Active In-Transit, Total Write-Off | 300s | TableWidget | `['default' => 1, 'md' => 2, 'xl' => 2]` |
| `LowStockAlertsWidget` | Variants where `availableQuantity <= reorder_point` | 300s | ChartWidget (bar) | `['default' => 1, 'md' => 1, 'xl' => 1]` |
| `RecentMovementsWidget` | Recent `stock_movements` daily buckets, 7 days | 60s | ChartWidget (line) | `['default' => 1, 'md' => 1, 'xl' => 1]` |
| `SalesRevenueTrendWidget` | Daily Sale value (`dispatched_at`), 30 days | 300s | ChartWidget (line) | `['default' => 1, 'md' => 2, 'xl' => 2]` |
| `ActiveInTransitWidget` | InTransit rows where `status = in_transit` | 300s | TableWidget | `['default' => 1, 'md' => 2, 'xl' => 2]` |
| `SalesVsPurchasesWidget` | Side-by-side monthly Purchase (`received_at`) vs Sale (`dispatched_at`) value, 6 months | 300s | ChartWidget (bar, grouped) | `['default' => 1, 'md' => 2, 'xl' => 2]` |
| `TopSellingVariantsWidget` | Top 10 variants by dispatched base qty, current month | 300s | ChartWidget (bar, horizontal) | `['default' => 1, 'md' => 1, 'xl' => 1]` |
| `PendingFulfillmentWidget` | Count of pending SO/PO, scoped | 60s | TableWidget | `['default' => 1, 'md' => 1, 'xl' => 1]` |
| `QuickActionsWidget` | Static shortcut buttons | — | Custom | `['default' => 1, 'md' => 2, 'xl' => 4]` |

### Implementation Examples

**One-class-per-file contract (documentation shorthand):** the ` ```php ` block below is a documentation shorthand that groups the nine one-class-per-file widget definitions under their shared `namespace App\Filament\Widgets;` header to avoid repeating identical `use` imports. It is NOT a multi-class file: each widget MUST live in its own file exactly as enumerated in §25 — `app/Filament/Widgets/<Class>.php` contains exactly `class <Class>` (same shorthand convention as §18.2a).

Caching rule: every data widget (all widgets except the static, uncached `QuickActionsWidget`) reads through `Cache::remember(static::cacheKey($user->id, $warehouseIds), static::cacheTtl(), ...)` — the declared `cacheKey()`/`cacheTtl()` methods are the single caching mechanism, never comments. `QuickActionsWidget` renders static shortcut buttons, declares neither method, and never touches the cache. The key embeds `scopeHash = md5()` of the sorted in-scope warehouse ID set, never just the first warehouse ID, so a warehouse-assignment change cannot share or poison another scope's cache. Grain: summary widgets list one row per in-scope warehouse with per-row metrics computed via `state()` closures over the same cached payload.

```php
namespace App\Filament\Widgets;

use App\Enums\TransferRequisitionStatus;
use App\Models\InTransit;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\TableWidget;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

// File: app/Filament/Widgets/StatsOverviewWidget.php
class StatsOverviewWidget extends TableWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = ['default' => 1, 'md' => 2, 'xl' => 2];

    public static function canView(): bool
    {
        // Role-Based Widget Visibility (§10): all authenticated users (scoped).
        return auth()->user() !== null;
    }

    public static function cacheTtl(): int
    {
        return 300;
    }

    public static function cacheKey(int $userId, array $warehouseIds): string
    {
        sort($warehouseIds);

        return 'stats_overview_' . $userId . '_' . md5(implode(',', $warehouseIds));
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $warehouseIds = $user->isAdmin() || $user->isAuditor()
            ? Warehouse::query()->pluck('id')->all()
            : $user->warehouses()->pluck('warehouses.id')->all();

        // Rows (owner decision, recorded — one row per in-scope warehouse):
        // - Total On-Hand = SUM(stock_movements.quantity) for that warehouse.
        // - Pending Requisitions = count of requisitions in the five canBeCancelled states
        //   (Draft, Requested, UnderReviewFulfiller, UnderReviewRequestor, Confirmed)
        //   where that warehouse is either endpoint (from_warehouse_id and
        //   to_warehouse_id are always distinct, so the two grouped counts
        //   never double-count the same requisition for one row).
        // - Active In-Transit = count of in_transits with status in_transit
        //   whose parent requisition touches that warehouse as either endpoint.
        // - Total Write-Off = SUM(loss_ledgers.total_financial_loss) for that warehouse.
        $metrics = Cache::remember(
            static::cacheKey($user->id, $warehouseIds),
            static::cacheTtl(),
            function () use ($warehouseIds) {
                $toIntMap = fn ($rows) => $rows
                    ->map(fn ($v) => (int) $v)
                    ->all();

                $pendingStatuses = [
                    TransferRequisitionStatus::Draft->value,
                    TransferRequisitionStatus::Requested->value,
                    TransferRequisitionStatus::UnderReviewFulfiller->value,
                    TransferRequisitionStatus::UnderReviewRequestor->value,
                    TransferRequisitionStatus::Confirmed->value,
                ];

                return [
                    'on_hand' => $toIntMap(StockMovement::query()
                        ->selectRaw('warehouse_id, SUM(quantity) as total')
                        ->whereIn('warehouse_id', $warehouseIds)
                        ->groupBy('warehouse_id')
                        ->pluck('total', 'warehouse_id')),
                    'pending_from' => $toIntMap(\App\Models\TransferRequisition::query()
                        ->selectRaw('from_warehouse_id as warehouse_id, COUNT(*) as total')
                        ->whereIn('status', $pendingStatuses)
                        ->whereIn('from_warehouse_id', $warehouseIds)
                        ->groupBy('from_warehouse_id')
                        ->pluck('total', 'warehouse_id')),
                    'pending_to' => $toIntMap(\App\Models\TransferRequisition::query()
                        ->selectRaw('to_warehouse_id as warehouse_id, COUNT(*) as total')
                        ->whereIn('status', $pendingStatuses)
                        ->whereIn('to_warehouse_id', $warehouseIds)
                        ->groupBy('to_warehouse_id')
                        ->pluck('total', 'warehouse_id')),
                    'in_transit_from' => $toIntMap(InTransit::query()
                        ->join('transfer_requisitions', 'transfer_requisitions.id', '=', 'in_transits.transfer_requisition_id')
                        ->selectRaw('transfer_requisitions.from_warehouse_id as warehouse_id, COUNT(in_transits.id) as total')
                        ->where('in_transits.status', \App\Enums\InTransitStatus::InTransit->value)
                        ->whereIn('transfer_requisitions.from_warehouse_id', $warehouseIds)
                        ->groupBy('transfer_requisitions.from_warehouse_id')
                        ->pluck('total', 'warehouse_id')),
                    'in_transit_to' => $toIntMap(InTransit::query()
                        ->join('transfer_requisitions', 'transfer_requisitions.id', '=', 'in_transits.transfer_requisition_id')
                        ->selectRaw('transfer_requisitions.to_warehouse_id as warehouse_id, COUNT(in_transits.id) as total')
                        ->where('in_transits.status', \App\Enums\InTransitStatus::InTransit->value)
                        ->whereIn('transfer_requisitions.to_warehouse_id', $warehouseIds)
                        ->groupBy('transfer_requisitions.to_warehouse_id')
                        ->pluck('total', 'warehouse_id')),
                    'write_off' => \App\Models\LossLedger::query()
                        ->selectRaw('warehouse_id, SUM(total_financial_loss) as total')
                        ->whereIn('warehouse_id', $warehouseIds)
                        ->groupBy('warehouse_id')
                        ->pluck('total', 'warehouse_id')
                        ->map(fn ($v) => (float) $v)
                        ->all(),
                ];
            },
        );

        return $table
            ->query(Warehouse::query()->whereIn('warehouses.id', $warehouseIds))
            ->columns([
                TextColumn::make('name')
                    ->label(__('dashboard.stats.warehouse')),

                TextColumn::make('on_hand')
                    ->label(__('dashboard.stats.on_hand'))
                    ->state(fn (Warehouse $record) => Number::format($metrics['on_hand'][$record->id] ?? 0))
                    ->numeric(),

                TextColumn::make('pending')
                    ->label(__('dashboard.stats.pending'))
                    ->state(fn (Warehouse $record) => Number::format(
                        ($metrics['pending_from'][$record->id] ?? 0)
                        + ($metrics['pending_to'][$record->id] ?? 0)
                    ))
                    ->numeric(),

                TextColumn::make('in_transit')
                    ->label(__('dashboard.stats.in_transit'))
                    ->state(fn (Warehouse $record) => Number::format(
                        ($metrics['in_transit_from'][$record->id] ?? 0)
                        + ($metrics['in_transit_to'][$record->id] ?? 0)
                    ))
                    ->numeric(),

                TextColumn::make('write_off')
                    ->label(__('dashboard.stats.write_off'))
                    ->state(fn (Warehouse $record) => Number::currency(
                        $metrics['write_off'][$record->id] ?? 0,
                        config('app.currency')
                    ))
                    ->numeric(),
            ])
            ->paginated(false);
    }
}

// File: app/Filament/Widgets/LowStockAlertsWidget.php
class LowStockAlertsWidget extends ChartWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = ['default' => 1, 'md' => 1, 'xl' => 1];

    public static function canView(): bool
    {
        // Role-Based Widget Visibility (§10): Admin, Auditor, Warehouse Staff.
        $user = auth()->user();

        return $user !== null
            && ($user->isAdmin() || $user->isAuditor() || $user->isWarehouseStaff());
    }

    protected function getType(): string
    {
        return 'bar';
    }

    public static function cacheTtl(): int
    {
        return 300;
    }

    public static function cacheKey(int $userId, array $warehouseIds): string
    {
        sort($warehouseIds);

        return 'low_stock_alerts_chart_' . $userId . '_' . md5(implode(',', $warehouseIds));
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $warehouseIds = $user->isAdmin() || $user->isAuditor()
            ? Warehouse::query()->pluck('id')->all()
            : $user->warehouses()->pluck('warehouses.id')->all();

        // [ACCEPTED RISK — KEEP] Data: variants where availableQuantity <= reorder_point — warehouse-scoped, 300s cache.
        // The per-variant loop below is the intentional live implementation
        // (see §10 "Widget Caching — [ACCEPTED RISK]"); do not replace it
        // outside the recorded trigger (active variants > ~5,000 or cache-miss > ~1–2s).
        // Owner decision (recorded): keep the per-variant loop. Authorized trigger for the
        // aggregate replacement: active variants exceed ~5,000 or a cache-miss exceeds ~1–2s —
        // then swap in ProductVariant::batchAvailableQuantity() with a PHP-side
        // available <= reorder_point filter plus a loop-vs-aggregate parity check.
        return Cache::remember(
            static::cacheKey($user->id, $warehouseIds),
            static::cacheTtl(),
            function () use ($warehouseIds) {
                if (empty($warehouseIds)) {
                    return [
                        'labels'   => [],
                        'datasets' => [
                            [
                                'label' => __('dashboard.charts.available'),
                                'data'  => [],
                            ],
                        ],
                    ];
                }

                // Scan every active variant first, filter to low-stock, then cap the
                // rendered chart at 20 rows — the limit applies after the filter,
                // never before it.
                $rows = \App\Models\ProductVariant::query()
                    ->where('is_active', true)
                    ->with('currentPrice')
                    ->get()
                    ->map(fn ($v) => [
                        'sku'       => $v->sku,
                        'available' => array_sum(array_map(
                            fn ($wid) => $v->availableQuantity($wid),
                            $warehouseIds,
                        )),
                        'reorder'   => (int) $v->reorder_point,
                    ])
                    ->filter(fn ($r) => $r['available'] <= $r['reorder'])
                    ->take(20)
                    ->values();

                return [
                    'labels'   => $rows->pluck('sku')->all(),
                    'datasets' => [
                        [
                            'label' => __('dashboard.charts.available'),
                            'data'  => $rows->pluck('available')->all(),
                        ],
                    ],
                ];
            },
        );
    }
}

// File: app/Filament/Widgets/RecentMovementsWidget.php
class RecentMovementsWidget extends ChartWidget
{
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = ['default' => 1, 'md' => 1, 'xl' => 1];

    public static function canView(): bool
    {
        // Role-Based Widget Visibility (§10): all authenticated users.
        return auth()->user() !== null;
    }

    protected function getType(): string
    {
        return 'line';
    }

    public static function cacheTtl(): int
    {
        return 60;
    }

    public static function cacheKey(int $userId, array $warehouseIds): string
    {
        sort($warehouseIds);

        return 'recent_movements_chart_' . $userId . '_' . md5(implode(',', $warehouseIds));
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $warehouseIds = $user->isAdmin() || $user->isAuditor()
            ? Warehouse::query()->pluck('id')->all()
            : $user->warehouses()->pluck('warehouses.id')->all();

        // Data: recent stock_movements daily buckets, 7 days — warehouse-scoped.
        return Cache::remember(
            static::cacheKey($user->id, $warehouseIds),
            static::cacheTtl(),
            function () use ($warehouseIds) {
                $buckets = StockMovement::query()
                    ->whereIn('warehouse_id', $warehouseIds)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->selectRaw('CAST(created_at AS DATE) as day, SUM(quantity) as total')
                    ->groupBy('day')
                    ->orderBy('day')
                    ->pluck('total', 'day');

                return [
                    'labels'   => $buckets->keys()->all(),
                    'datasets' => [
                        [
                            'label' => __('dashboard.charts.net_movement'),
                            'data'  => $buckets->map(fn ($v) => (int) $v)->values()->all(),
                        ],
                    ],
                ];
            },
        );
    }
}

// File: app/Filament/Widgets/SalesRevenueTrendWidget.php
class SalesRevenueTrendWidget extends ChartWidget
{
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = ['default' => 1, 'md' => 2, 'xl' => 2];

    public static function canView(): bool
    {
        // Role-Based Widget Visibility (§10): Admin, Auditor.
        $user = auth()->user();

        return $user !== null && ($user->isAdmin() || $user->isAuditor());
    }

    protected function getType(): string
    {
        return 'line';
    }

    public static function cacheTtl(): int
    {
        return 300;
    }

    public static function cacheKey(int $userId, array $warehouseIds): string
    {
        sort($warehouseIds);

        return 'sales_revenue_trend_' . $userId . '_' . md5(implode(',', $warehouseIds));
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $warehouseIds = $user->isAdmin() || $user->isAuditor()
            ? Warehouse::query()->pluck('id')->all()
            : $user->warehouses()->pluck('warehouses.id')->all();

        // Data: daily sale value (operational `dispatched_at` grain, §2.18), 30 days — warehouse-scoped.
        return Cache::remember(
            static::cacheKey($user->id, $warehouseIds),
            static::cacheTtl(),
            function () use ($warehouseIds) {
                // `unit_sale_price_snapshot` is per ordered unit (§2.19:
                // `qty` × `unit_ratio` = `base_qty`), so the per-base-unit
                // value divides by `unit_ratio` (`NULLIF` guards a zero ratio).
                $buckets = DB::table('sales_order_items')
                    ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
                    ->whereIn('sales_orders.warehouse_id', $warehouseIds)
                    ->whereNotNull('sales_orders.dispatched_at')
                    ->where('sales_orders.dispatched_at', '>=', now()->subDays(30))
                    ->selectRaw('CAST(sales_orders.dispatched_at AS DATE) as day, SUM(sales_order_items.dispatched_base_qty * sales_order_items.unit_sale_price_snapshot / NULLIF(sales_order_items.unit_ratio, 0)) as total')
                    ->groupBy('day')
                    ->orderBy('day')
                    ->pluck('total', 'day');

                return [
                    'labels'   => $buckets->keys()->all(),
                    'datasets' => [
                        [
                            'label' => __('dashboard.charts.revenue'),
                            'data'  => $buckets->map(fn ($v) => (float) $v)->values()->all(),
                        ],
                    ],
                ];
            },
        );
    }
}

// File: app/Filament/Widgets/ActiveInTransitWidget.php
class ActiveInTransitWidget extends TableWidget
{
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = ['default' => 1, 'md' => 2, 'xl' => 2];

    public static function canView(): bool
    {
        // Role-Based Widget Visibility (§10): Admin, Auditor, Warehouse Staff.
        $user = auth()->user();

        return $user !== null
            && ($user->isAdmin() || $user->isAuditor() || $user->isWarehouseStaff());
    }

    public static function cacheTtl(): int
    {
        return 300;
    }

    public static function cacheKey(int $userId, array $warehouseIds): string
    {
        sort($warehouseIds);

        return 'active_in_transit_' . $userId . '_' . md5(implode(',', $warehouseIds));
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $warehouseIds = $user->isAdmin() || $user->isAuditor()
            ? Warehouse::query()->pluck('id')->all()
            : $user->warehouses()->pluck('warehouses.id')->all();

        // Rows: active in-transit only (`status = in_transit`, §4.7) — warehouse-scoped through the parent requisition.
        // `Cleared` and `Lost` are terminal states and never appear here.
        // The ID set is read through Cache::remember (300s); the table query re-hydrates
        // from the cached IDs so sorting and eager-loads still apply on every render.
        $ids = Cache::remember(
            static::cacheKey($user->id, $warehouseIds),
            static::cacheTtl(),
            fn () => InTransit::query()
                ->where('status', \App\Enums\InTransitStatus::InTransit->value)
                ->whereHas('transferRequisition', fn ($q) => $q
                    ->whereIn('from_warehouse_id', $warehouseIds)
                    ->orWhereIn('to_warehouse_id', $warehouseIds))
                ->latest('dispatched_at')
                ->pluck('id')
                ->all(),
        );

        return $table
            ->query(
                InTransit::query()
                    ->whereIn('id', $ids)
                    ->with(['transferRequisition', 'productVariant'])
                    ->latest('dispatched_at')
            )
            ->columns([
                TextColumn::make('transferRequisition.reference_code')
                    ->label(__('dashboard.active_in_transit.requisition'))
                    ->fontFamily('mono'),

                TextColumn::make('productVariant.sku')
                    ->label(__('dashboard.active_in_transit.sku'))
                    ->fontFamily('mono'),

                TextColumn::make('dispatched_base_qty')
                    ->label(__('dashboard.active_in_transit.qty'))
                    ->numeric(),

                TextColumn::make('status')
                    ->label(__('dashboard.active_in_transit.status'))
                    ->badge(),
            ])
            ->paginated([5, 10]);
    }
}

// File: app/Filament/Widgets/SalesVsPurchasesWidget.php
class SalesVsPurchasesWidget extends ChartWidget
{
    protected static ?int $sort = 6;
    protected int | string | array $columnSpan = ['default' => 1, 'md' => 2, 'xl' => 2];

    public static function canView(): bool
    {
        // Role-Based Widget Visibility (§10): Admin, Auditor.
        $user = auth()->user();

        return $user !== null && ($user->isAdmin() || $user->isAuditor());
    }

    protected function getType(): string
    {
        return 'bar';
    }

    public static function cacheTtl(): int
    {
        return 300;
    }

    public static function cacheKey(int $userId, array $warehouseIds): string
    {
        sort($warehouseIds);

        return 'sales_vs_purchases_' . $userId . '_' . md5(implode(',', $warehouseIds));
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $warehouseIds = $user->isAdmin() || $user->isAuditor()
            ? Warehouse::query()->pluck('id')->all()
            : $user->warehouses()->pluck('warehouses.id')->all();

        // Data: side-by-side monthly purchase vs sale value, 6 months — warehouse-scoped.
        // Operational grain: purchases bucket by `received_at` (§2.16),
        // sales by `dispatched_at` (§2.18); undelivered rows (`NULL`) are excluded.
        return Cache::remember(
            static::cacheKey($user->id, $warehouseIds),
            static::cacheTtl(),
            function () use ($warehouseIds) {
                $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));

                // Portable day buckets (`CAST(... AS DATE)` is supported by
                // both MySQL and PostgreSQL); rolled up to months in PHP.
                $rollUp = function ($rows) {
                    $out = [];
                    foreach ($rows as $day => $total) {
                        $month = substr((string) $day, 0, 7);
                        $out[$month] = ($out[$month] ?? 0) + (float) $total;
                    }
                    return $out;
                };

                // `unit_cost_price` is per ordered unit (§2.17:
                // `ordered_qty` × `ordered_unit_ratio` = `ordered_base_qty`),
                // so the per-base-unit value divides by `ordered_unit_ratio`.
                $purchases = $rollUp(DB::table('purchase_order_items')
                    ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
                    ->whereIn('purchase_orders.warehouse_id', $warehouseIds)
                    ->whereNotNull('purchase_orders.received_at')
                    ->where('purchase_orders.received_at', '>=', now()->subMonths(6))
                    ->selectRaw('CAST(purchase_orders.received_at AS DATE) as day, SUM(purchase_order_items.received_base_qty * purchase_order_items.unit_cost_price / NULLIF(purchase_order_items.ordered_unit_ratio, 0)) as total')
                    ->groupBy('day')
                    ->pluck('total', 'day'));

                // `unit_sale_price_snapshot` is per ordered unit (§2.19) —
                // same per-base-unit normalization as `SalesRevenueTrendWidget`.
                $sales = $rollUp(DB::table('sales_order_items')
                    ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
                    ->whereIn('sales_orders.warehouse_id', $warehouseIds)
                    ->whereNotNull('sales_orders.dispatched_at')
                    ->where('sales_orders.dispatched_at', '>=', now()->subMonths(6))
                    ->selectRaw('CAST(sales_orders.dispatched_at AS DATE) as day, SUM(sales_order_items.dispatched_base_qty * sales_order_items.unit_sale_price_snapshot / NULLIF(sales_order_items.unit_ratio, 0)) as total')
                    ->groupBy('day')
                    ->pluck('total', 'day'));

                return [
                    'labels'   => $months->all(),
                    'datasets' => [
                        [
                            'label' => __('dashboard.charts.purchases'),
                            'data'  => $months->map(fn ($m) => (float) ($purchases[$m] ?? 0))->all(),
                        ],
                        [
                            'label' => __('dashboard.charts.sales'),
                            'data'  => $months->map(fn ($m) => (float) ($sales[$m] ?? 0))->all(),
                        ],
                    ],
                ];
            },
        );
    }
}

// File: app/Filament/Widgets/TopSellingVariantsWidget.php
class TopSellingVariantsWidget extends ChartWidget
{
    protected static ?int $sort = 7;
    protected int | string | array $columnSpan = ['default' => 1, 'md' => 1, 'xl' => 1];

    public static function canView(): bool
    {
        // Role-Based Widget Visibility (§10): Admin, Auditor.
        $user = auth()->user();

        return $user !== null && ($user->isAdmin() || $user->isAuditor());
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        // Horizontal bars per the §10 Widget Definitions table (`bar, horizontal`).
        return ['indexAxis' => 'y'];
    }

    public static function cacheTtl(): int
    {
        return 300;
    }

    public static function cacheKey(int $userId, array $warehouseIds, string $month): string
    {
        sort($warehouseIds);

        return 'top_selling_variants_' . $userId . '_' . md5(implode(',', $warehouseIds)) . '_' . $month;
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $warehouseIds = $user->isAdmin() || $user->isAuditor()
            ? Warehouse::query()->pluck('id')->all()
            : $user->warehouses()->pluck('warehouses.id')->all();
        $month = now()->format('Y-m');

        // Data: top 10 variants by dispatched base qty, current month — warehouse-scoped.
        return Cache::remember(
            static::cacheKey($user->id, $warehouseIds, $month),
            static::cacheTtl(),
            function () use ($warehouseIds) {
                $rows = DB::table('sales_order_items')
                    ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
                    ->join('product_variants', 'product_variants.id', '=', 'sales_order_items.product_variant_id')
                    ->whereIn('sales_orders.warehouse_id', $warehouseIds)
                    ->whereNotNull('sales_orders.dispatched_at')
                    ->where('sales_orders.dispatched_at', '>=', now()->startOfMonth())
                    ->selectRaw('product_variants.sku as sku, SUM(sales_order_items.dispatched_base_qty) as total')
                    ->groupBy('product_variants.sku')
                    ->orderByDesc('total')
                    ->limit(10)
                    ->get();

                return [
                    'labels'   => $rows->pluck('sku')->all(),
                    'datasets' => [
                        [
                            'label' => __('dashboard.charts.dispatched'),
                            'data'  => $rows->pluck('total')->map(fn ($v) => (int) $v)->all(),
                        ],
                    ],
                ];
            },
        );
    }
}

// File: app/Filament/Widgets/PendingFulfillmentWidget.php
class PendingFulfillmentWidget extends TableWidget
{
    protected static ?int $sort = 8;
    protected int | string | array $columnSpan = ['default' => 1, 'md' => 1, 'xl' => 1];

    public static function canView(): bool
    {
        // Role-Based Widget Visibility (§10): all authenticated users (scoped).
        return auth()->user() !== null;
    }

    public static function cacheTtl(): int
    {
        return 60;
    }

    public static function cacheKey(int $userId, array $warehouseIds): string
    {
        sort($warehouseIds);

        return 'pending_fulfillment_' . $userId . '_' . md5(implode(',', $warehouseIds));
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $warehouseIds = $user->isAdmin() || $user->isAuditor()
            ? Warehouse::query()->pluck('id')->all()
            : $user->warehouses()->pluck('warehouses.id')->all();

        // Rows: one per in-scope warehouse with pending SO/PO counts (60s cache).
        $counts = Cache::remember(
            static::cacheKey($user->id, $warehouseIds),
            static::cacheTtl(),
            fn () => [
                'sales' => SalesOrder::query()
                    ->selectRaw('warehouse_id, COUNT(*) as total')
                    ->whereIn('warehouse_id', $warehouseIds)
                    ->whereIn('status', [
                        \App\Enums\SalesOrderStatus::Confirmed->value,
                        \App\Enums\SalesOrderStatus::PartiallyDispatched->value,
                    ])
                    ->groupBy('warehouse_id')
                    ->pluck('total', 'warehouse_id')
                    ->map(fn ($v) => (int) $v)
                    ->all(),
                'purchases' => PurchaseOrder::query()
                    ->selectRaw('warehouse_id, COUNT(*) as total')
                    ->whereIn('warehouse_id', $warehouseIds)
                    ->whereIn('status', [
                        \App\Enums\PurchaseOrderStatus::Ordered->value,
                        \App\Enums\PurchaseOrderStatus::PartiallyReceived->value,
                    ])
                    ->groupBy('warehouse_id')
                    ->pluck('total', 'warehouse_id')
                    ->map(fn ($v) => (int) $v)
                    ->all(),
            ],
        );

        return $table
            ->query(Warehouse::query()->whereIn('warehouses.id', $warehouseIds))
            ->columns([
                TextColumn::make('name')
                    ->label(__('dashboard.pending.warehouse')),

                TextColumn::make('pending_sales')
                    ->label(__('dashboard.pending.sales'))
                    ->state(fn (Warehouse $record) => $counts['sales'][$record->id] ?? 0)
                    ->numeric(),

                TextColumn::make('pending_purchases')
                    ->label(__('dashboard.pending.purchases'))
                    ->state(fn (Warehouse $record) => $counts['purchases'][$record->id] ?? 0)
                    ->numeric(),
            ])
            ->paginated(false);
    }
}

// File: app/Filament/Widgets/QuickActionsWidget.php
class QuickActionsWidget extends Widget
{
    protected static ?int $sort = 9;
    protected int | string | array $columnSpan = ['default' => 1, 'md' => 2, 'xl' => 4];

    public static function canView(): bool
    {
        // Role-Based Widget Visibility (§10): all authenticated users.
        return auth()->user() !== null;
    }

    protected static string $view = 'filament.widgets.quick-actions';

    // Static shortcut buttons; no cache.

    /**
     * @return array<int, array{label: string, url: string, icon: \Filament\Support\Icons\Heroicon}>
     */
    public static function actions(): array
    {
        return [
            [
                'label' => __('dashboard.quick_actions.new_transfer'),
                'url'   => \App\Filament\Resources\TransferRequisitions\TransferRequisitionResource::getUrl('create'),
                'icon'  => \Filament\Support\Icons\Heroicon::ArrowsRightLeft,
            ],
            [
                'label' => __('dashboard.quick_actions.new_purchase'),
                'url'   => \App\Filament\Resources\PurchaseOrders\PurchaseOrderResource::getUrl('create'),
                'icon'  => \Filament\Support\Icons\Heroicon::ShoppingCart,
            ],
            [
                'label' => __('dashboard.quick_actions.new_sale'),
                'url'   => \App\Filament\Resources\SalesOrders\SalesOrderResource::getUrl('create'),
                'icon'  => \Filament\Support\Icons\Heroicon::Banknotes,
            ],
            [
                'label' => __('dashboard.quick_actions.new_direct_transfer'),
                'url'   => \App\Filament\Resources\DirectTransfers\DirectTransferResource::getUrl('create'),
                'icon'  => \Filament\Support\Icons\Heroicon::ArrowPath,
            ],
        ];
    }

    protected function getViewData(): array
    {
        return ['actions' => static::actions()];
    }
}
```

`resources/views/filament/widgets/quick-actions.blade.php`:

```blade
<div class="grid grid-cols-2 gap-3 md:grid-cols-4">
    @foreach ($actions as $action)
        <a
            href="{{ $action['url'] }}"
            class="flex items-center gap-2 rounded-lg border border-zinc-200 bg-white px-4 py-3 text-sm font-medium text-zinc-900 hover:bg-zinc-50"
        >
            <x-filament::icon
                :icon="$action['icon']"
                class="h-5 w-5 text-zinc-500"
            />
            {{ $action['label'] }}
        </a>
    @endforeach
</div>
```

> **Verified:** `<x-filament::icon>` is the canonical Filament v5 Blade icon component and accepts `Heroicon` enum instances directly via `:icon` (the `class` attribute forwards to the underlying SVG, so `class="h-5 w-5 text-zinc-500"` works as written). The existing usage is correct.

### Cache Key Reference

| Widget | Cache Key Pattern | TTL |
|---|---|---|
| `StatsOverviewWidget` | `stats_overview_{userId}_{scopeHash}` | 300s |
| `LowStockAlertsWidget` | `low_stock_alerts_chart_{userId}_{scopeHash}` | 300s |
| `RecentMovementsWidget` | `recent_movements_chart_{userId}_{scopeHash}` | 60s |
| `SalesRevenueTrendWidget` | `sales_revenue_trend_{userId}_{scopeHash}` | 300s |
| `ActiveInTransitWidget` | `active_in_transit_{userId}_{scopeHash}` | 300s |
| `SalesVsPurchasesWidget` | `sales_vs_purchases_{userId}_{scopeHash}` | 300s |
| `TopSellingVariantsWidget` | `top_selling_variants_{userId}_{scopeHash}_{month}` | 300s |
| `PendingFulfillmentWidget` | `pending_fulfillment_{userId}_{scopeHash}` | 60s |
| `QuickActionsWidget` | — (static shortcut buttons; uncached, no `cacheKey()`/`cacheTtl()`) | — |

`scopeHash = md5()` of the comma-joined, sorted in-scope warehouse ID set (`md5(implode(',', sort($warehouseIds)))`).

### Widget Caching — `[ACCEPTED RISK]`

> **`[ACCEPTED RISK NOTE]`** The `LowStockAlertsWidget` iterates all active `ProductVariant` records and calls the `availableQuantity()` accessor per row, with the entire widget result wrapped in a single 300-second cache window. The scan covers every active variant — the `take(20)` cap applies after the low-stock filter to the rendered chart only, never to the scanned set. Every cache miss still issues 2N queries for N variants. At small-to-medium catalog sizes this is acceptable. At large catalog sizes (tens of thousands of variants), this will produce a spiky cache-refresh moment every 5 minutes and should be revisited.
>
> **Upgrade path:** replace the per-variant loop with a single grouped aggregate query. Red flag threshold: `product_variants` count exceeds ~5,000–10,000 active rows, or cache-miss load exceeds ~1–2 seconds.
>
> **Owner-authorized trigger (recorded):** when active variants exceed ~5,000 or a cache-miss exceeds ~1–2 seconds, swap in `ProductVariant::batchAvailableQuantity()` with a PHP-side `available <= reorder_point` filter, plus a loop-vs-aggregate parity check. No re-decision required.

### Role-Based Widget Visibility

| Widget | Visible To |
|---|---|
| `StatsOverviewWidget` | All authenticated users (scoped) |
| `LowStockAlertsWidget` | Admin, Auditor, Warehouse Staff |
| `RecentMovementsWidget` | All authenticated users |
| `SalesRevenueTrendWidget` | Admin, Auditor |
| `ActiveInTransitWidget` | Admin, Auditor, Warehouse Staff |
| `SalesVsPurchasesWidget` | Admin, Auditor |
| `TopSellingVariantsWidget` | Admin, Auditor |
| `PendingFulfillmentWidget` | All authenticated users (scoped) |
| `QuickActionsWidget` | All authenticated users |

### Dashboard Registration

```php
->widgets([
    \App\Filament\Widgets\StatsOverviewWidget::class,
    \App\Filament\Widgets\LowStockAlertsWidget::class,
    \App\Filament\Widgets\RecentMovementsWidget::class,
    \App\Filament\Widgets\SalesRevenueTrendWidget::class,
    \App\Filament\Widgets\ActiveInTransitWidget::class,
    \App\Filament\Widgets\SalesVsPurchasesWidget::class,
    \App\Filament\Widgets\TopSellingVariantsWidget::class,
    \App\Filament\Widgets\PendingFulfillmentWidget::class,
    \App\Filament\Widgets\QuickActionsWidget::class,
])
```

---

## 📋 Section 11: Master Execution Sequence (20 Phases)

**Phase 00: Environment & Core Guardrails Setup**

1. Bootstrap Laravel 13 with PostgreSQL.
2. Install FilamentPHP v5.
3. Install Livewire v4.
4. Install `simplesoftwareio/simple-qrcode`.
5. Install `pestphp/pest`.
6. Add `'currency' => env('APP_CURRENCY', 'PHP')` to `config/app.php`.
7. Verify `ext-bcmath` is enabled and declared.
8. Register `App\Providers\AuthServiceProvider` and `App\Providers\InventoryServiceProvider` in `bootstrap/providers.php`.
9. Mandate `->strictAuthorization()` in `AdminPanelProvider`.
10. Enumerate every policy method before enabling strict mode.
11. Verify `HasWizard` trait availability on all wizard-based `CreateRecord` page classes.
12. Register `ProductObserver` and `ProductVariantObserver` before any seeder creates variants.
13. Register `ScopesNavigationBadges` trait in every resource that declares a navigation badge.
14. Add a boot smoke test proving every required service, policy, resource, page, widget, route, and observer is registered.

**Phase 01: Relational Schema Migrations.** 22 tables in dependency order, including `in_transits.cleared_at`, idempotency uniqueness, current-price uniqueness, `created_at` indexes on document tables, and the `direct_transfers` / `direct_transfer_items` tables created **after** `purchase_order_items`. Direct-transfer tables have `restrictOnDelete` FKs on `product_variants` and `warehouses`, `cascadeOnDelete` on the header→items FK, and `created_at` indexes.

**Phase 02: Base Seeders & Opening Ledger.** Entry point `database/seeders/DatabaseSeeder.php` inlines all base-seeding logic in §2 FK-dependency order — warehouses → users (with `user_warehouse` assignments) → products/variants (prices, unit conversions including base-unit self-conversion rows) → suppliers → customers — using the §5 factories, including `DirectTransferFactory` and `DirectTransferItemFactory`. Observers are registered before any seeder runs (Phase 00.12) so the base-unit self-conversion row is observer-materialized. Opening-ledger stock is written exclusively through the existing service paths (`InventoryService::adjustment()` / `recordMovement()`), never through direct `StockMovement` inserts. Seed volumes and opening balances per warehouse/variant (owner-confirmed): 3 warehouses, 10 variants, 3 suppliers, 3 customers, opening-balance base qty 100 per warehouse/variant pair.

Canonical `database/seeders/DatabaseSeeder.php` (derived from Phase 02, the §5 factories, and `InventoryService::adjustment()` — no direct `StockMovement` inserts, no per-domain seeder classes beyond this entry point per the §25 file map):

```php
namespace Database\Seeders;

use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\ProductVariantUnitConversion;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Owner-confirmed production seed quantities (Finding 4 resolved):
        // 3 warehouses, 10 variants, 3 suppliers, 3 customers,
        // opening-balance base qty 100 per warehouse/variant pair.
        $warehouseCount = 3;
        $variantCount = 10;
        $supplierCount = 3;
        $customerCount = 3;
        $openingBalanceBaseQty = 100;

        // §2 FK-dependency order: warehouses first — the `user_warehouse`
        // pivot, purchase/sales orders, and transfers all reference them.
        $warehouses = Warehouse::factory()
            ->count($warehouseCount)
            ->create();

        // Users with `user_warehouse` assignments (§2.13, §3.18).
        User::factory()->admin()->create();
        User::factory()->auditor()->create();

        // Non-admin requisition create (§8.3) requires at least two
        // assignments, so seed one multi-warehouse staff member plus one
        // staff member per warehouse.
        $multiWarehouseStaff = User::factory()->create();
        $multiWarehouseStaff->warehouses()->attach($warehouses->pluck('id')->take(2)->all());

        foreach ($warehouses as $warehouse) {
            $staff = User::factory()->create();
            $staff->warehouses()->attach($warehouse->id);
        }

        // Products/variants (§5.1–§5.2). Each variant auto-creates its
        // parent product; the base-unit self-conversion row is
        // observer-materialized (F19, Phase 00.12).
        $variants = ProductVariant::factory()
            ->count($variantCount)
            ->create();

        // Exactly one current price row per variant (§2.3, §5.3).
        foreach ($variants as $variant) {
            ProductVariantPrice::factory()->create([
                'product_variant_id' => $variant->id,
            ]);
        }

        // One non-base unit conversion row per variant (§5.4). The
        // base-unit self-conversion row is observer-materialized
        // (F19, Phase 00.12), so only additional units are seeded here.
        foreach ($variants as $variant) {
            ProductVariantUnitConversion::factory()->create([
                'product_variant_id' => $variant->id,
            ]);
        }

        Supplier::factory()
            ->count($supplierCount)
            ->create();

        Customer::factory()
            ->count($customerCount)
            ->create();

        // Opening-ledger stock exclusively through the service path —
        // never through direct `StockMovement` inserts.
        $inventory = app(InventoryService::class);

        foreach ($warehouses as $warehouse) {
            foreach ($variants as $variant) {
                $inventory->adjustment(
                    productVariantId: $variant->id,
                    warehouseId: $warehouse->id,
                    signedBaseQuantity: $openingBalanceBaseQty,
                    notes: 'Opening balance (Phase 02 seed)',
                );
            }
        }
    }
}
```

**Phase 03: Eloquent Model Projections & Enums.** Derived stock methods, nine backed enums, observers, `StockMovementIdempotencyKey`, `DirectTransfer`, and `DirectTransferItem` models. Extend `Warehouse` with `directTransfersFrom()` and `directTransfersTo()`.

**Phase 04: Transactional Inventory Engine.** `InventoryService`, `NegotiationService`, `TransferRequisitionService`, `PurchaseService`, `SalesService`, `GuardsOutstandingQuantity`. `InventoryService::directTransfer()` accepts `array $items` and emits N paired movements in one transaction.

**Phase 05: Product Catalog Resource.** `ProductResource` bound to `ProductVariant`, `ManageUnitConversionsAction` guards base-unit row, **card-layout table, no bulk actions**.

**Phase 06: Price Snapshots & Unit Conversions.** Enforce one current price at the database boundary and lock variants while replacing the current price.

**Phase 07: Warehouses & Manual Adjustments.** `WarehouseResource` with card layout, read-only user pivot, and `WarehousePolicy` blocking referenced warehouses — extended with direct-transfer reference guards.

**Phase 08: Inter-Warehouse Requisition & Direct Transfer Wizards.** All Resource/Page classes exist; responsive `columnSpan` on all fields; create/edit/list/view pages are registered. Direct Transfer uses a repeater-based form, a View page + Infolist, a card-layout table, `DirectTransferPolicy`, and query-level warehouse scoping on both endpoints.

**Phase 09: Negotiation Loop UI.** Revision form with substitute-variant unit sourcing; accept/reject transitions are transactional and parent-locked.

**Phase 10: Dispatch, In-Transit Monitor & Confirm Materialization.** Confirmation is atomic; dispatch verifies `Confirmed`; `InTransitResource` uses standard table + `stackedOnMobile()`; rows transition to `Cleared`/`Lost`.

**Phase 11: Printable STN & Signed QR Route.** `StnController`, print/scan routes, seven-day `URL::temporarySignedRoute()`, `signed` middleware, and explicit `receive` policy authorization.

**Phase 12: Scan-to-Receive Modal & Multi-Batch Intake.** Canonical checksum, idempotency uniqueness, payload validation, deterministic locking, and no double application under concurrent scans.

**Phase 13: Read-Only Audit Ledgers.** `StockMovementResource` and `LossLedgerResource` with dense tables and `summarize()` on loss.

**Phase 14: Purchases Module.** All Purchase resource/page classes; warehouse-scoped badge; over-receive guard; order/cancel transitions locked; item/variant locks on receive.

**Phase 15: Sales Module.** All Sales resource/page classes; warehouse-scoped badge; over-dispatch and over-return guards; confirm-time price snapshot under variant lock; own-reservation-excluding dispatch availability.

**Phase 16: Glassmorphic Bento Dashboard.** All 9 widget classes exist, each with explicit role/data scope and responsive `$columnSpan`.

**Phase 17: Multi-Language Translation.** Labels, validation, notifications, exceptions, and UI messages use translation keys.

**Phase 18: Runtime Wiring & Authorization Isolation.** Provider registration, policy registration (including `DirectTransferPolicy`), resource query scoping, route middleware, signed URL generation, event/listener registration, and container resolution tests. `StockMovementPolicy::createDirectTransfer()` removed. Direct Transfer list scoping enforced at query level. Badge scope resolution centralized through `ScopesNavigationBadges`.

**Phase 19: Automated CI/CD Testing & E2E Hardening.** Pest Unit/Feature, concurrency tests, Filament authorization tests, route tests, responsive Playwright scenarios, and CI fail-fast completeness checks.

---

## 🧪 Section 12: Automated CI/CD Testing & E2E Validation Strategy

| Test Runner | Environment | Focus Area |
|---|---|---|
| Laravel Pint | Local / CI | Code style compliance |
| Pest PHP | SQLite (`:memory:`) | Unit, Feature, Service & Model tests |
| Playwright | PostgreSQL (Test DB) | Sequential multi-role E2E browser flows |

### Critical Pest Coverage Targets

- `ProductVariant::onHandQuantity()` / `reservedQuantity()` / `reservedForSalesQuantity()` / `availableQuantity()` correctness.
- `reservedQuantity()` and `batchAvailableQuantity()` honor `$excludeTransferRequisitionId`.
- `reservedForSalesQuantity()` and `batchAvailableQuantity()` honor `$excludeSalesOrderId`.
- `batchAvailableQuantity()` issues exactly 3 queries regardless of variant count.
- `batchUnitConversions()` issues exactly 1 query.
- `ProductVariantObserver` materializes base-unit self-conversion row on create.
- `TransferRequisition::canBeCancelled()` returns true only for the five pre-dispatch states.
- `PurchaseOrder::canBeCancelled()` returns false when any item has received quantity.
- `InventoryService::directTransfer()` locks warehouses in sorted-ID order.
- `InventoryService::dispatchTransfer()` locks items and variants; throws on insufficient availability.
- `InventoryService::scanToReceive()` no-ops on duplicate payload via state-equality check.
- `InventoryService::scanToReceive()` uses idempotency presence for first-scan detection, not `cleared_at`.
- `InventoryService::scanToReceive()` transitions `InTransit` rows to `Cleared` or `Lost`.
- `InventoryService::dispatchTransfer()` throws if `approved_base_qty` is null.
- `InventoryService::recordMovement()` throws on purchase/sale/sale_return/purchase_return/adjustment types.
- `NegotiationService::submitRequest()` transitions Draft → Requested only.
- `NegotiationService::materializeRequestedAsApproved()` throws if any item has null approved qty.
- `NegotiationService::assertNegotiable()` rejects non-negotiable parent statuses.
- `PurchaseService::receivePurchase()` locks items and variants; guards over-receive; updates cost price when `update_cost_price = true`.
- `SalesService::recordSalesReturn()` guards cumulative over-return; locks variant and warehouse.
- `SalesService::dispatchSale()` locks items and variants; excludes own reservation in availability check; rejects dispatch when insufficient.
- Loss ledger `total_financial_loss` uses `bcmul()`, not float cast.
- `LossLedger::snapshotUnitCostFrom()` logs a warning when cost is missing or zero.
- All policies return expected booleans for each role.
- `WarehousePolicy::delete()` blocks warehouses with stock movements, POs, SOs, TRs, or direct transfers.
- QR lifetime = 7 days.

### Badge Scope Pest Coverage (v13.6)

```
BadgeScopeTest::admin_sees_all_warehouses_regardless_of_pivot()
BadgeScopeTest::auditor_sees_all_warehouses_regardless_of_pivot()
BadgeScopeTest::warehouse_staff_with_single_warehouse_sees_only_that_warehouse()
BadgeScopeTest::warehouse_staff_with_multiple_warehouses_sees_union()
BadgeScopeTest::warehouse_staff_with_zero_warehouses_yields_null_badge()
BadgeScopeTest::resolver_is_cached_within_request()
BadgeScopeTest::resolver_flushes_on_logout()

NavigationBadgeRoleScopeTest::transfer_requisition_badge_counts_from_and_to_for_multi_warehouse()
NavigationBadgeRoleScopeTest::transfer_requisition_badge_ignores_counterpart_warehouse_for_single_warehouse_user()
NavigationBadgeRoleScopeTest::purchase_order_badge_counts_only_assigned_single_warehouse()
NavigationBadgeRoleScopeTest::sales_order_badge_counts_only_assigned_single_warehouse()
NavigationBadgeRoleScopeTest::in_transit_badge_scopes_through_requisition_endpoints()
NavigationBadgeRoleScopeTest::single_warehouse_user_never_sees_documents_touching_other_warehouse()
```

### New Direct-Transfer Pest Coverage

```
# Phase 04 — service layer
DirectTransferServiceTest::writes_one_paired_movement_per_item
DirectTransferServiceTest::creates_header_and_one_item_row_per_line
DirectTransferServiceTest::movements_reference_direct_transfer_header
DirectTransferServiceTest::movements_link_transfer_in_to_transfer_out_via_related_id
DirectTransferServiceTest::locks_warehouses_in_sorted_id_order
DirectTransferServiceTest::locks_variants_in_sorted_id_order
DirectTransferServiceTest::rejects_same_from_and_to_warehouse
DirectTransferServiceTest::rejects_empty_items_array
DirectTransferServiceTest::rejects_item_missing_required_field
DirectTransferServiceTest::rejects_unit_ratio_below_one
DirectTransferServiceTest::rejects_qty_below_one
DirectTransferServiceTest::rejects_unknown_variant_id
DirectTransferServiceTest::rejects_warehouse_outside_actor_scope
DirectTransferServiceTest::is_atomic_on_mid_loop_failure

# Phase 07 — warehouse policy
WarehousePolicyTest::delete_blocked_when_direct_transfers_reference_from
WarehousePolicyTest::delete_blocked_when_direct_transfers_reference_to

# Phase 08 — form / resource / table
DirectTransferFormTest::items_repeater_requires_at_least_one_line
DirectTransferFormTest::changing_variant_resets_unit_and_ratio
DirectTransferFormTest::unit_ratio_autofills_from_unit_selection
DirectTransfersTableTest::card_layout_declares_content_grid
DirectTransfersTableTest::card_layout_declares_pagination_twelve
DirectTransfersTableTest::card_layout_declares_no_bulk_actions
DirectTransfersTableTest::default_sort_is_transferred_at_desc
DirectTransferResourceTest::model_is_direct_transfer
DirectTransferResourceTest::get_eloquent_query_eager_loads_from_to_items_variant
DirectTransferInfolistTest::renders_all_items_with_sku_qty_base_qty
DirectTransferCreateTest::handle_record_creation_calls_service_with_items_array

# Phase 18 — policy / scope
DirectTransferPolicyTest::create_requires_at_least_one_assigned_warehouse
DirectTransferPolicyTest::view_requires_both_warehouses_in_scope
DirectTransferPolicyTest::admin_can_delete
DirectTransferPolicyTest::update_and_edit_are_denied_everywhere
WarehouseScopeTest::direct_transfers_scoped_by_both_warehouse_columns
WarehouseScopeTest::direct_transfer_list_hides_records_outside_scope

# Phase 20 — architecture
RuntimeCompletenessTest::direct_transfer_resource_model_is_direct_transfer
RuntimeCompletenessTest::direct_transfer_policy_registered_in_gate
RuntimeCompletenessTest::direct_transfer_tables_exist_in_schema
RuntimeCompletenessTest::stock_movement_policy_no_longer_exposes_create_direct_transfer
```

### Navigation Badge & Icon Tests

```
TransferRequisitionResourceTest::navigation_badge_is_warehouse_scoped()
PurchaseOrderResourceTest::navigation_badge_is_warehouse_scoped()
SalesOrderResourceTest::navigation_badge_is_warehouse_scoped()
NavigationBadgeTest::badge_count_is_computed_once_per_request()
NavigationBadgeTest::badge_uses_scopes_navigation_badges_trait()
NavigationBadgeTest::badge_scope_is_cached_per_request()
AllResourcesTest::every_resource_declares_active_navigation_icon()
AllResourcesTest::no_resource_uses_raw_string_icon()
AllActionsTest::every_action_declares_heroicon_enum_icon()
AllFormFieldsTest::semantically_meaningful_fields_carry_prefix_icons()
```

### Responsive & Table Tests

```
ResponsiveSpanTest::all_form_fields_declare_explicit_default_breakpoint()
ResponsiveSpanTest::all_sections_declare_columns_with_default_key()
ResponsiveSpanTest::all_wizard_steps_declare_responsive_columns()
ResponsiveSpanTest::all_infolist_sections_declare_responsive_column_span()
ResponsiveSpanTest::all_widgets_declare_column_span_as_breakpoint_array()
ResponsiveSpanTest::dashboard_get_columns_returns_breakpoint_array()
ResponsiveSpanTest::all_repeaters_declare_columns_with_default_key()
ResponsiveSpanTest::all_repeater_fields_declare_column_span_with_default_key()
ResponsiveSpanTest::column_span_full_used_for_placeholder_review_summaries()
ResponsiveSpanTest::mobile_breakpoint_collapses_all_forms_to_single_column()
ResponsiveSpanTest::tablet_breakpoint_unstacks_wide_fields_to_two_columns()
ResponsiveSpanTest::desktop_breakpoint_achieves_full_bento_grid()
ResponsiveSpanTest::table_columns_use_visible_from_for_mobile_hiding()
ResponsiveSpanTest::no_raw_integer_column_span_without_breakpoint_array()

TableArchitectureTest::document_tables_declare_content_grid()
TableArchitectureTest::ledger_tables_declare_stacked_on_mobile()
TableArchitectureTest::card_tables_declare_pagination_page_option()
TableArchitectureTest::all_tables_declare_default_sort()
TableArchitectureTest::all_relational_columns_have_eager_loaded_relations()
TableArchitectureTest::signed_quantity_columns_are_color_coded()
TableArchitectureTest::audit_filters_use_authorize_not_visible()
TableArchitectureTest::card_tables_do_not_declare_bulk_actions_without_plugin()
```

### Playwright E2E Scenarios

1. Full Transfer Lifecycle.
2. **Direct Transfer — Single Item:** create with 1 line → header + 1 item visible on View page; both movements visible in StockMovementResource.
2b. **Direct Transfer — Multi Item:** create with 3 lines → header + 3 items visible on View page; exactly 6 StockMovement rows tagged with the same reference_code; from-warehouse on-hand decreased by sum of base_qty; to-warehouse on-hand increased by sum of base_qty.
2c. **Direct Transfer — Guards:** same from/to warehouse → validation blocks submit; warehouse outside scope → 403 on create; empty items repeater → validation blocks submit.
2d. **Direct Transfer — Responsive:** 375px → 1 card per row; 768px → 2 cards per row; 1440px → 3 cards per row.
3. Loss Write-Off.
4. Soft-Delete Guard.
5. Authorization Bypass Attempt.
6. Cancellation Boundary.
7. Duplicate Scan Submission.
8. Negotiation Loop.
9. Purchase Lifecycle (with over-receive attempt).
10. Sales Lifecycle (with over-return attempt).
11. Sales Cancellation Boundary.
12. Wizard Submit Button Visibility.
13. Unit Select Flow.
14. Base-Unit Deletion Guard.
15. Navigation Badge Visibility.
15b. **Navigation Badge — Single-Warehouse Role Scope:** log in as a warehouse-staff user assigned exactly one warehouse; verify the badge count on Transfer Requisitions, Purchase Orders, Sales Orders, and In-Transit reflects only documents touching that single warehouse; verify counterpart warehouse of transfers does not inflate the count.
15c. **Navigation Badge — Admin Scope:** log in as an Admin with an empty `user_warehouse` pivot; verify badges show the full system count.
16. **Responsive Layout — Mobile:** resize to 375px → verify forms collapse to single column; card tables show 1 card per row; ledger tables use stacked layout.
17. **Responsive Layout — Tablet:** resize to 768px → verify card tables show 2 cards per row; wide fields span 2 columns.
18. **Responsive Layout — Desktop:** resize to 1440px → verify card tables show 3 cards per row; full bento dashboard; 4-column line-item repeaters.
19. **Sales dispatch self-reservation:** confirm an order with on-hand quantity sufficient for its own outstanding qty dispatches successfully.

---

## 📌 Section 13: Remaining Product Decisions / Non-Blocking Enhancements

The following are intentionally **not silently decided by the Council** because they change product/accounting behavior rather than merely closing an implementation gap:

1. **Supplier shipment / transit tracking for purchases.**
2. **Purchase-side negotiation.**
3. **FIFO / weighted-average / lot-level COGS costing.**
4. **`PurchaseReturn` full workflow UI and business lifecycle.**
5. **Backorder auto-fulfillment.**
6. **Reporting-view decision** — separate Purchases/Sales tab on `StockMovementResource`.
7. **Per-card bulk selection** — requires `mkdev-grid-card-layout` plugin.
8. **`AdminReviewFilters::period()` — richer range presets** (last N days, YTD).
9. **Low-stock widget scaling redesign** once the active-variant threshold is reached.

### Owner Decisions (recorded — all recommendations approved)

- Items 1 (supplier shipment tracking), 2 (purchase-side negotiation), 6 (Purchases/Sales reporting tabs), 8 (richer period presets): **deferred** — no action until operators request them.
- Item 3 (COGS costing): **snapshot-cost only** — `LossLedger::snapshotUnitCostFrom()` remains the operational costing; no margin/P&L reporting may be built on it until a costing methodology is decided. Suggested default when that day comes: weighted-average.
- Item 4 (`PurchaseReturn` UI/lifecycle): **deferred pending supplier-return practice** — the movement type stays; no UI until returns to suppliers are confirmed as an operating practice (required states to be specified then).
- Item 5 (backorder auto-fulfillment): **explicit NO for v1** — manual dispatch only; any future proposal needs its own race analysis.
- Item 7 (per-card bulk selection): **deferred** — approve `mkdev-grid-card-layout` only when a bulk operation on card tables is requested.
- Item 9 (low-stock widget redesign): **deferred until the authorized trigger fires** (see §10 Widget Caching).

### Decision Boundary

The Council may implement technical integrity fixes without further product clarification. It must stop and request user direction when a change would alter:

- inventory valuation methodology;
- financial/accounting treatment;
- business lifecycle states;
- external-party workflows;
- automatic fulfillment behavior;
- return/refund semantics;
- notification recipients or escalation policy.

---

## ✅ Section 14: Cross-Cutting Verification Checklist

| Check | Status |
|---|---|
| `reservedQuantity()` counts Confirmed only, permanently and by design | ✅ |
| `reservedQuantity()` scope boundary is documented in-code | ✅ |
| `reservedForSalesQuantity()` is separate from `reservedQuantity()` | ✅ |
| `batchAvailableQuantity()` issues exactly 3 queries regardless of variant count | ✅ |
| `batchUnitConversions()` issues exactly 1 query regardless of variant count | ✅ |
| **Sales dispatch excludes own order reservation from availability** | ✅ |
| **Transfer dispatch excludes own requisition reservation from availability** | ✅ |
| **Purchase receive re-locks items and variants under parent transaction** | ✅ |
| **Sales dispatch re-locks items and variants under parent transaction** | ✅ |
| ForceDeleteAction absent from ProductResource | ✅ |
| All ledger product_variant_id FKs are restrictOnDelete | ✅ |
| `stock_movements.notes` column + service param | ✅ |
| `loss_ledgers.transfer_requisition_id` nullable | ✅ |
| `partially_received` has producer and consumer | ✅ |
| ConfirmAction calls `materializeRequestedAsApproved()` | ✅ |
| **`materializeRequestedAsApproved()` throws on null approved qty** | ✅ |
| `dispatchTransfer` / `scanToReceive` free of `??` fallbacks | ✅ |
| `dispatchTransfer` throws if `approved_base_qty` null | ✅ |
| **`InTransit` rows transition to `Cleared` / `Lost`** | ✅ |
| **First-scan detection uses idempotency table, not `cleared_at` on `in_transits`** | ✅ |
| ScanToReceiveAction named `scanToReceive` (camelCase) | ✅ |
| Wizard review steps render `filament.wizards.*` via `View::make()->viewData()` on all 4 pages — no `WizardReviewStep` renderer, no Livewire review component | ✅ |
| `RepeatableEntry` (not `RepeatEntry`) in all infolists | ✅ |
| `SoftDeletingScope` imported in `getEloquentQuery()` | ✅ |
| Enums route `getLabel()` through `__()` | ✅ |
| Policies exist and are wired via `->authorize()` | ✅ |
| **`ProductPolicy` exists and is registered** | ✅ |
| **`CustomerPolicy` exists and is registered** | ✅ |
| **`DirectTransferPolicy` exists and is registered** | ✅ |
| ProductObserver guards parent soft-delete | ✅ |
| QR lifetime = 7 days | ✅ |
| All action namespaces = `Filament\Actions\*` (^5.0) | ✅ |
| `->recordActions()` / `->toolbarActions()` (v5, not v3) | ✅ |
| `BulkActionGroup` wraps multiple bulk actions | ✅ |
| **Bulk actions omitted from all card tables (F30)** | ✅ |
| Section/Grid/Wizard from `Filament\Schemas\Components\*` | ✅ |
| `Get` from `Filament\Schemas\Components\Utilities\Get` | ✅ |
| Money columns use `format_money($state)` for `decimal(15,4)` fields (4-decimal); plain `->money(config('app.currency'))` (locale-default 2) where intended; no `decimals:` argument | ✅ |
| `$navigationGroup` / `$navigationSort` specified per resource | ✅ |
| `->strictAuthorization()` mandated in panel provider | ✅ |
| Resource classes use thin delegation pattern | ✅ |
| Schema classes expose static `configure()` method | ✅ |
| `getRecordRouteBindingEloquentQuery()` overrides for soft-delete | ✅ |
| LossLedger model exists with `snapshotUnitCostFrom()` | ✅ |
| **`snapshotUnitCostFrom()` logs warning on missing/zero cost** | ✅ |
| `directTransfer()` locks warehouses in sorted-ID order | ✅ |
| `recordMovement()` and `directTransfer()` reject `unit_ratio < 1` | ✅ |
| **`recordMovement()` rejects purchase/sale/sale_return/purchase_return/adjustment types** | ✅ |
| `scanToReceive()` no-ops on duplicate payload via state-equality check | ✅ |
| `total_financial_loss` computed via `bcmul()`, not float cast | ✅ |
| CancelAction restricted to five pre-dispatch states, both `->authorize()` and `->visible()` | ✅ |
| `ext-bcmath` declared as required PHP extension | ✅ |
| LowStockAlertsWidget scaling risk documented as accepted | ✅ |
| All Actions use `->schema()`, zero `->form()` calls | ✅ |
| Wizard review steps use `View::make()->viewData()` (not `Placeholder`, not Livewire) | ✅ |
| Tables with many record actions use a divider-separated `ActionGroup` dropdown (F31) | ✅ |
| Record-action alignment is set globally via `configureTable()`, never per resource | ✅ |
| `createOptionForm` auto-selects new option after save | ✅ |
| PurchaseOrderPolicy, SalesOrderPolicy, SupplierPolicy, CustomerPolicy, **ProductPolicy**, WarehousePolicy, UserPolicy, **DirectTransferPolicy** exist | ✅ |
| No `->visible()` closure re-derives a permission decision | ✅ |
| Service methods re-verify warehouse scope at the service boundary where mandated (§6.2 `directTransfer()`/`adjustment()`, §20.3); policies own the decisions, the service enforces the scope | ✅ |
| PolicyAuditTest exists and passes | ✅ |
| AdminReviewFilters reused across all applicable resources | ✅ |
| **AdminReviewFilters custom-range shows unbounded indicators** | ✅ |
| `batchAvailableQuantity()` used in `dispatchSale` modal | ✅ |
| `HasWizard` trait used on all wizard-based CreateRecord pages | ✅ |
| `getSteps()` returns `array<Step>` on all wizard pages | ✅ |
| Resource `form()` provides flat fields for Edit page | ✅ |
| Public static field helpers extracted on all wizard form classes | ✅ |
| Relationship-bound repeaters do NOT declare `->dehydrated()` (F17) | ✅ |
| `mutateRelationshipDataBeforeCreateUsing()` fires per item | ✅ |
| `mutateRelationshipDataBeforeSaveUsing()` fires per item on Edit | ✅ |
| `unit_sale_price_snapshot` remains at default until confirm-time | ✅ |
| Submit button only appears on last wizard step | ✅ |
| Unit fields are `Select`, never free-text `TextInput` | ✅ |
| Unit `Select` sources from variant's `product_variant_unit_conversions` | ✅ |
| `*_unit_ratio` is `->disabled()` + `->dehydrated()`, auto-filled via `->live()` | ✅ |
| `ProductVariantObserver` materializes base-unit self-conversion row on create | ✅ |
| Base-unit row has `unit_name = base_unit_name` and `base_unit_ratio = 1` | ✅ |
| `ManageUnitConversionsAction` disallows deletion of base-unit row | ✅ |
| **`ManageUnitConversionsAction` implementation present** | ✅ |
| Purchase unit `Select` prefers `is_default_purchase`, falls back to all | ✅ |
| Changing variant resets unit + ratio fields | ✅ |
| Layout components used per Section 7M rules | ✅ |
| Wizard steps use Section with icon where >2 fields | ✅ |
| Infolists use Grid::make(3) outer wrapper; line-item `RepeatableEntry` uses `->table([TableColumn...])->schema([...])` with `->alignEnd()` on numeric entries | ✅ |
| Tabs use `->persistTabInQueryString()` for 3+ tab resources | ✅ |
| Navigation groups registered in `->navigationGroups()` with icons and collapsibility | ✅ |
| Every resource declares `$navigationGroup` matching a registered group | ✅ |
| Every resource declares `$navigationSort` unique within its group | ✅ |
| Every resource declares `$navigationIcon` as `Heroicon` enum | ✅ |
| Every resource declares `$activeNavigationIcon` distinct from `$navigationIcon` | ✅ |
| All navigation badges warehouse-scoped | ✅ |
| **Badge counts computed once per request** | ✅ |
| **Badge scope is resolved via `ScopesNavigationBadges` trait** | ✅ |
| **Admin/Auditor badge scope is all warehouses regardless of pivot contents** | ✅ |
| **WarehouseStaff with exactly 1 assigned warehouse sees only that warehouse in badges** | ✅ |
| **Counterpart warehouse of a transfer does not widen a single-warehouse user's badge** | ✅ |
| **WarehouseStaff with 0 assigned warehouses yields `null` badge** | ✅ |
| **Badge scope is cached per request and flushed on re-auth in long-lived workers** | ✅ |
| **No resource uses ad hoc `auth()->user()->warehouses()->pluck('id')` in `getNavigationBadge()`** | ✅ |
| `TransferRequisitionService` exists and resolves through the container | ✅ |
| `StockMovementIdempotencyKey` model exists | ✅ |
| STN scan route is named, authenticated, signed, and policy-authorized | ✅ |
| All operational resource lists apply warehouse scope before pagination | ✅ |
| Transfer confirmation is atomic and parent/item locked | ✅ |
| Transfer dispatch rejects non-`Confirmed` state | ✅ |
| Scan payload is canonicalized before checksum | ✅ |
| Purchase order lifecycle transitions are parent-locked | ✅ |
| Sales confirm locks variants before price snapshot | ✅ |
| **Direct Transfer authorization uses `DirectTransferPolicy`** | ✅ |
| **DirectTransfer resource model rebound to `DirectTransfer` header** | ✅ |
| **Direct Transfer supports N items in one transfer** | ✅ |
| **Direct Transfer writes one paired movement per line** | ✅ |
| **Direct Transfer table uses card layout (not ledger)** | ✅ |
| **DirectTransferInfolist + ViewDirectTransfer page exist** | ✅ |
| **Warehouse delete guard blocks direct-transfer references** | ✅ |
| Badge colors switch to `warning` above threshold of 10 | ✅ |
| Every Action declares `->icon()` with a `Heroicon` enum | ✅ |
| Every wizard `Step` declares `->icon()` | ✅ |
| Every infolist `Section` header carries a `Heroicon` | ✅ |
| No raw-string icon declarations anywhere in resources | ✅ |
| Card layout applied to ProductsTable | ✅ |
| Card layout applied to WarehousesTable | ✅ |
| Card layout applied to TransferRequisitionsTable | ✅ |
| Card layout applied to DirectTransfersTable | ✅ |
| Card layout applied to PurchaseOrdersTable | ✅ |
| Card layout applied to SalesOrdersTable | ✅ |
| Card layout applied to SuppliersTable | ✅ |
| Card layout applied to CustomersTable | ✅ |
| `stackedOnMobile()` applied to InTransitsTable | ✅ |
| `stackedOnMobile()` applied to StockMovementsTable | ✅ |
| `stackedOnMobile()` applied to LossLedgersTable | ✅ |
| `stackedOnMobile()` applied to UsersTable | ✅ |
| Every table declares `->defaultSort()` | ✅ |
| Card tables declare `->defaultPaginationPageOption(12)` | ✅ |
| Ledger tables paginate at 50 | ✅ |
| Signed quantity columns color-coded | ✅ |
| `total_financial_loss` summarized with `Sum::make()` | ✅ |
| `AdminReviewFilters::period()` is policy-gated via `->visible()` + `viewAuditFilters` (no `authorize()` on `Filter`) | ✅ |
| All relational columns have eager-loaded relations | ✅ |
| **`WarehousePolicy::delete()` blocks warehouses with stock movements, POs, SOs, TRs, or direct transfers** | ✅ |
| `PurchaseOrder::canBeCancelled()` extracted as model method | ✅ |
| `TransferRequisition::canBeCancelled()` extracted as model method | ✅ |
| `SalesOrderItem::alreadyReturnedBaseQty()` extracted as model method | ✅ |
| `NegotiationService::submitRequest()` extracted from inline action | ✅ |
| **Substitute variants documented as transfer-only (A10)** | ✅ |
| **Direct Transfers are multi-line fire-and-forget (A11)** | ✅ |
| **Badge scope is role + warehouse determined (A12)** | ✅ |
| **Document tables index `created_at`** | ✅ |
| **`user_warehouse` pivot edited from UserResource only** | ✅ |

---

## 🔢 Section 15: Reserved — Intentionally Blank

Section 15 is reserved to preserve the document's sequential numbering between Section 14 (Cross-Cutting Verification Checklist) and Section 16 (Council Audit). No content has been omitted here; no sketch, code, or checklist belongs to this number.

---

## 🏛️ Section 16: Council Audit — Completeness & Gap Closure

### 16.1 Council Verdict

**Status: v13.3 was architecturally strong but implementation-incomplete.**

The source blueprint contained substantial domain logic and test intent, but several declared runtime surfaces were only referenced, not actually wired or defined. The most important gaps were not stock mathematics; they were **integration boundaries**: services, resources/pages/widgets, policy registration, routes, signed URL generation, data scoping, and transactional state transitions.

This section is authoritative. Where an older section says that a surface exists but does not define its runtime contract, the contract below wins.

### 16.2 P0 Gaps Closed

| Gap | Prior condition | Resolution |
|---|---|---|
| `TransferRequisitionService` | Referenced by Cancel action but no class/specification existed | Add service; own cancellation and confirmation transitions |
| Resource classes | Most Resources were listed but not implemented | Add thin Resource contract for every declared resource |
| Resource Pages | Most List/Create/Edit/View classes were listed but not defined | Add every page class and register its route |
| Dashboard widgets | 7 of 9 widgets were only referenced | Add class contract, scope contract, and registration for all 9 |
| STN route | `route('stn.scan')` was referenced but no route/controller existed | Add `StnController`, named routes, middleware, authorization |
| Signed QR | QR was described as signed but table action generated an unsigned route | Use `URL::temporarySignedRoute()` with 7-day expiry |
| Service wiring | Services were resolved ad hoc with `app(...)` and no provider contract existed | Add `InventoryServiceProvider` and runtime resolution tests |
| Observer wiring | Observer registration was only embedded in prose | Make `AppServiceProvider::boot()` registration mandatory and test it |
| Policy wiring | Registration existed only as a code fragment | Register every policy in `AuthServiceProvider` and test Gate resolution |
| Idempotency model | Table/service references existed, but no model was defined | Add `StockMovementIdempotencyKey` model with casts/relations |
| Direct Transfer architecture | Direct Transfer was a `StockMovement`-backed single-item resource with no header | Add `DirectTransfer` + `DirectTransferItem` models, `DirectTransferPolicy`, multi-item service, repeater form, card table, infolist, view page |
| Warehouse list exposure | `viewAny()` did not provide collection-level warehouse isolation | Apply server-side query scoping before pagination |
| Confirm race | Confirmation mutated items and parent outside one service transaction | Atomic `TransferRequisitionService::confirm()` |
| Dispatch state guard | `dispatchTransfer()` did not explicitly reject invalid parent states | Require `Confirmed` |
| Scan validation | Negative/over-receive payloads were not rejected | Normalize and validate payload before any movement/ledger write |
| Scan lock scope | Destination variant/warehouse were not explicitly locked | Lock actual variants and destination warehouse in deterministic order |
| Scan checksum | Raw `json_encode()` allowed equivalent payloads with different key ordering | Canonicalize payload before hashing |
| Substitute loss cost | Loss snapshot used the requested variant relationship | Resolve cost from `actualVariantId()` |
| Purchase transition race | Order/cancel transitions were not locked | Parent row lock + status check |
| Sales price race | Confirm read current price without locking variant | Lock variant before reading current price |
| Event layer | Events were deferred | Add post-commit domain events/listeners as required infrastructure |
| **Badge role scoping** | **Badges were described as "warehouse-scoped" but no formal rule distinguished Admin/Auditor from warehouse staff or handled the single-warehouse case** | **Add `ScopesNavigationBadges` trait, Principle A12, four scope tiers, and single-warehouse hard bound** |

### 16.3 P1 Gaps Closed

1. Wizard review steps render the shipped `filament.wizards.*` views via `View::make()->viewData()` on all four pages; neither the retired `WizardReviewStep::renderSummary()` static renderer nor the removed `WizardReviewSummary` Livewire component is used (per §18.3).
2. Inline Product-family creation must auto-select the newly created family via native `createOptionForm` auto-select (see §7A.1 `product_id` — no custom callback required; "variant" in earlier drafts meant the family record behind `product_id`).
3. All resource `getEloquentQuery()` implementations must eager-load referenced relationships.
4. All operational resources must apply warehouse data scoping before filters, sorting, pagination, or card rendering.
5. Every declared action must resolve a real service method or a real policy method; no blueprint-only action names are accepted.
6. Runtime completeness tests must fail if a declared class, route, provider, policy, or widget is missing.
7. Event dispatch must occur after successful transaction commit.
8. Concurrency tests must cover double-click, duplicate scan, concurrent receive, concurrent dispatch, and concurrent lifecycle transitions.
9. **Badge scope must be tested for every role × warehouse-cardinality combination and must never widen for single-warehouse users.**

---

## 🔌 Section 17: Runtime Wiring Contract

### 17.1 Service Container

Create:

```text
app/Providers/InventoryServiceProvider.php
```

Register:

```php
namespace App\Providers;

use App\Services\GuardsOutstandingQuantity;
use App\Services\InventoryService;
use App\Services\NegotiationService;
use App\Services\PurchaseService;
use App\Services\SalesService;
use App\Services\TransferRequisitionService;
use Illuminate\Support\ServiceProvider;

class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GuardsOutstandingQuantity::class);
        $this->app->bind(InventoryService::class);
        $this->app->bind(NegotiationService::class);
        $this->app->bind(TransferRequisitionService::class);
        $this->app->bind(PurchaseService::class);
        $this->app->bind(SalesService::class);
    }
}
```

No service may depend on a controller, Filament Page, Livewire component, or HTTP request object. Services operate on domain models/DTOs and resolve the request actor via `auth()` for request-scoped calls.

### 17.2 Provider Registration

`bootstrap/providers.php` must include:

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\InventoryServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
];
```

The test suite must prove each provider is loaded.

Complete `AppServiceProvider` — owns observer registration (§17.3), global table configuration (§7N.5 `configureTable()`), and the badge-scope flush call site (§1B.3):

```php
namespace App\Providers;

use App\Filament\Resources\InTransits\InTransitResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Observers\ProductObserver;
use App\Observers\ProductVariantObserver;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->configureTable();

        // Observer registration — must run before seeders or factories
        // create variants so the base-unit conversion always exists.
        Product::observe(ProductObserver::class);
        ProductVariant::observe(ProductVariantObserver::class);

        // Badge-scope flush — the canonical call sites. Each
        // badge-bearing resource holds its own trait-static scope and
        // count caches, so all four are flushed (scope AND count).
        // Only matters for long-lived workers; harmless per-request
        // under PHP-FPM.
        Event::listen(Logout::class, function (): void {
            TransferRequisitionResource::flushBadgeScope();
            PurchaseOrderResource::flushBadgeScope();
            SalesOrderResource::flushBadgeScope();
            InTransitResource::flushBadgeScope();
        });

        // Re-authentication without a preceding `Logout` (session expiry
        // followed by direct `Auth::login()`, programmatic auth in
        // long-lived workers, Filament re-auth) must flush too —
        // otherwise the previous user's scope and count survive into the
        // new session.
        Event::listen(Login::class, function (): void {
            TransferRequisitionResource::flushBadgeScope();
            PurchaseOrderResource::flushBadgeScope();
            SalesOrderResource::flushBadgeScope();
            InTransitResource::flushBadgeScope();
        });
    }

    /**
     * Global table defaults. `recordActionsAlignment('end')` applies to
     * every table (card and standard) — it is deliberately NOT set per
     * resource (§7N.5). `striped()` + `deferLoading()` are project-wide.
     */
    private function configureTable(): void
    {
        Table::configureUsing(function (Table $table): void {
            $table->striped()
                ->deferLoading();
            $table->recordActionsAlignment('end');
        });
    }
}
```

### 17.3 Observer Registration

Observers are registered in `AppServiceProvider::boot()` as shown in the complete class in §17.2:

```php
Product::observe(ProductObserver::class);
ProductVariant::observe(ProductVariantObserver::class);
```

The base-unit observer must be registered **before** seeders or factories create variants.

### 17.4 Policy Registration

Complete `AuthServiceProvider` — owns every policy binding in one place via
the `$policies` map consumed by `registerPolicies()`:

```php
namespace App\Providers;

use App\Models\Customer;
use App\Models\DirectTransfer;
use App\Models\InTransit;
use App\Models\LossLedger;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Models\Warehouse;
use App\Policies\CustomerPolicy;
use App\Policies\DirectTransferPolicy;
use App\Policies\InTransitPolicy;
use App\Policies\LossLedgerPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProductVariantPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\SalesOrderPolicy;
use App\Policies\StockMovementPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\TransferRequisitionPolicy;
use App\Policies\UserPolicy;
use App\Policies\WarehousePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * This map is the single canonical registration site:
     * `registerPolicies()` iterates it in `boot()` below. Do not add
     * parallel `Gate::policy()` calls — edit this map only, so the two
     * can never diverge.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Product::class             => ProductPolicy::class,
        ProductVariant::class      => ProductVariantPolicy::class,
        TransferRequisition::class => TransferRequisitionPolicy::class,
        InTransit::class           => InTransitPolicy::class,
        StockMovement::class       => StockMovementPolicy::class,
        LossLedger::class          => LossLedgerPolicy::class,
        PurchaseOrder::class       => PurchaseOrderPolicy::class,
        SalesOrder::class          => SalesOrderPolicy::class,
        Supplier::class            => SupplierPolicy::class,
        Customer::class            => CustomerPolicy::class,
        Warehouse::class           => WarehousePolicy::class,
        User::class                => UserPolicy::class,
        DirectTransfer::class      => DirectTransferPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
```

Complete `EventServiceProvider` — owns the event→listener wiring (§22.3a):

```php
namespace App\Providers;

use App\Events\InventoryBelowReorderPoint;
use App\Events\LossRecorded;
use App\Events\PurchaseOrderReceived;
use App\Events\SalesOrderDispatched;
use App\Events\TransferCancelled;
use App\Events\TransferConfirmed;
use App\Events\TransferDispatched;
use App\Events\TransferReceived;
use App\Listeners\NotifyInventoryBelowReorderPoint;
use App\Listeners\NotifyLossRecorded;
use App\Listeners\NotifyPurchaseOrderReceived;
use App\Listeners\NotifySalesOrderDispatched;
use App\Listeners\NotifyTransferCancelled;
use App\Listeners\NotifyTransferConfirmed;
use App\Listeners\NotifyTransferDispatched;
use App\Listeners\NotifyTransferReceived;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        TransferConfirmed::class          => [NotifyTransferConfirmed::class],
        TransferCancelled::class          => [NotifyTransferCancelled::class],
        TransferDispatched::class         => [NotifyTransferDispatched::class],
        TransferReceived::class           => [NotifyTransferReceived::class],
        LossRecorded::class               => [NotifyLossRecorded::class],
        PurchaseOrderReceived::class      => [NotifyPurchaseOrderReceived::class],
        SalesOrderDispatched::class       => [NotifySalesOrderDispatched::class],
        InventoryBelowReorderPoint::class => [NotifyInventoryBelowReorderPoint::class],
    ];
}
```

### 17.5 Filament Panel Provider

Create/complete:

```text
app/Providers/Filament/AdminPanelProvider.php
```

The provider must own:

- panel ID/path;
- authentication;
- `->strictAuthorization()`;
- navigation groups;
- resources;
- widgets;
- middleware;
- theme/assets;
- discovery configuration.

All resources named in Section 1 must be registered/discoverable at runtime. The provider must not reference classes that do not exist.

```php
namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile()
            ->colors([
                // Primary maps to the §10 dashboard palette token (#3b82f6).
                'primary' => Color::hex('#3b82f6'),
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->widgets([
                \App\Filament\Widgets\StatsOverviewWidget::class,
                \App\Filament\Widgets\LowStockAlertsWidget::class,
                \App\Filament\Widgets\RecentMovementsWidget::class,
                \App\Filament\Widgets\SalesRevenueTrendWidget::class,
                \App\Filament\Widgets\ActiveInTransitWidget::class,
                \App\Filament\Widgets\SalesVsPurchasesWidget::class,
                \App\Filament\Widgets\TopSellingVariantsWidget::class,
                \App\Filament\Widgets\PendingFulfillmentWidget::class,
                \App\Filament\Widgets\QuickActionsWidget::class,
            ])
            ->navigationGroups([
                \Filament\Navigation\NavigationGroup::make('CATALOG')->label(__('navigation.groups.catalog'))->icon(\Filament\Support\Icons\Heroicon::CubeTransparent)->collapsible(),
                \Filament\Navigation\NavigationGroup::make('OPERATIONS')->label(__('navigation.groups.operations'))->icon(\Filament\Support\Icons\Heroicon::OutlinedRectangleStack)->collapsible(),
                \Filament\Navigation\NavigationGroup::make('PURCHASING')->label(__('navigation.groups.purchasing'))->icon(\Filament\Support\Icons\Heroicon::OutlinedShoppingCart)->collapsible(),
                \Filament\Navigation\NavigationGroup::make('SALES')->label(__('navigation.groups.sales'))->icon(\Filament\Support\Icons\Heroicon::OutlinedBanknotes)->collapsible(),
                \Filament\Navigation\NavigationGroup::make('AUDIT LEDGERS')->label(__('navigation.groups.audit_ledgers'))->icon(\Filament\Support\Icons\Heroicon::QueueList)->collapsible(),
                \Filament\Navigation\NavigationGroup::make('SYSTEM ADMIN')->label(__('navigation.groups.system_admin'))->icon(\Filament\Support\Icons\Heroicon::BuildingOffice)->collapsible(false),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->strictAuthorization();
    }
}
```

Group `make()` keys stay stable identifiers matching each resource's `$navigationGroup`; only `->label()` translates (§1A.1). `->discoverPages()` is intentionally absent: no custom panel pages exist (the §25 file map defines no `app/Filament/Pages/` directory), so there is nothing to discover — resource pages resolve through their resource's `getPages()` and are never discovered from the resources directory. Widgets are registered only through the explicit `->widgets([...])` list (§10 Dashboard Registration) — `->discoverWidgets()` is intentionally absent so the nine dashboard widgets are not registered (and rendered) twice.

`resources/css/filament/admin/theme.css` (§25 file map) — admin panel theme loaded via `->viteTheme()` above. Required content contract:

```css
/* Admin panel theme for the Filament v5 `admin` panel (loaded via `->viteTheme()` above). */
@tailwind base;
@tailwind components;
@tailwind utilities;
/* No Filament theme overrides required — the Tailwind directives above are the
   complete file content. Do not add custom tokens (see the §10 dashboard palette note). */
```

No custom design tokens are defined by this blueprint beyond the §10 dashboard palette reference (`Color::hex('#3b82f6')` primary); do not invent theme variables here.

### 17.6 No Hidden Service Locator Requirement

Using `app(Service::class)` inside a Filament action is permitted, but it is not the wiring mechanism. The container/provider contract above is the canonical registration boundary.

Preferred action shape:

```php
->action(fn (TransferRequisition $record) =>
    app(TransferRequisitionService::class)->cancelRequisition($record)
)
```

Dependency injection may be used when the surrounding Filament lifecycle supports it. Either form must resolve through the registered container binding.

### 17.7 Badge Scope Trait Registration

The `ScopesNavigationBadges` trait lives at `app/Filament/Support/Concerns/ScopesNavigationBadges.php` and is consumed by every resource that declares a non-null navigation badge:

- `TransferRequisitionResource` — `use ScopesNavigationBadges;` (§1B.3a, §18.1a)
- `PurchaseOrderResource` — `use ScopesNavigationBadges;` (§1B.3a, §18.1a)
- `SalesOrderResource` — `use ScopesNavigationBadges;` (§1B.3a, §18.1a)
- `InTransitResource` — `use ScopesNavigationBadges;` (§1B.3a, §18.1a)

Each of the four canonical implementations above shows the `use` statement explicitly; no badge-bearing resource relies on an implied or inherited import. No other resource declares a navigation badge (§1B.2), so no other resource may consume the trait for badge purposes.

A runtime test must assert that each of the four classes uses the trait and that the resolver returns the expected warehouse set for each role × cardinality combination.

---

## 🧱 Section 18: Missing Implementation Surfaces — Required File Contract

### 18.1 Resources

Every Resource listed below must physically exist:

```text
app/Filament/Resources/
├── Products/ProductResource.php
├── TransferRequisitions/TransferRequisitionResource.php
├── DirectTransfers/DirectTransferResource.php
├── InTransits/InTransitResource.php
├── StockMovements/StockMovementResource.php
├── LossLedgers/LossLedgerResource.php
├── Warehouses/WarehouseResource.php
├── Users/UserResource.php
├── PurchaseOrders/PurchaseOrderResource.php
├── SalesOrders/SalesOrderResource.php
├── Suppliers/SupplierResource.php
└── Customers/CustomerResource.php
```

Each Resource must contain:

1. `$model`;
2. navigation group/sort/icon/active icon;
3. `form()` when create/edit requires a form;
4. `table()`;
5. `infolist()` when view exists;
6. `getEloquentQuery()` with required eager-loading and warehouse scope;
7. `getPages()`;
8. any soft-delete route-binding override;
9. no duplicated business logic.

Resources that declare a navigation badge must additionally:

10. `use ScopesNavigationBadges;`
11. resolve badge warehouse IDs via `self::badgeScopedWarehouseIds()`;
12. short-circuit to `null` when `! self::hasBadgeScope()`.

Canonical pattern (illustrative pseudocode — not a physical file; the `Example*` names are placeholders for the concrete per-resource classes in §18.1a, e.g. `ProductResource` / `ProductForm` / `ProductsTable` / `ProductInfolist` / `ListProducts`):

```php
// Illustrative pseudocode — see §18.1a for the concrete per-resource classes.
class ExampleResource extends Resource
{
    protected static ?string $model = Example::class;

    public static function form(Schema $schema): Schema
    {
        return ExampleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExamplesTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ExampleInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        // Exemplar eager-load is `items`; the per-resource eager-loads and
        // warehouse scope live in §7 and §18.1a under the §20.1 scoping rules.
        // Badge-bearing resources additionally `use ScopesNavigationBadges`
        // and resolve IDs via `self::badgeScopedWarehouseIds()` — ad hoc
        // `auth()->user()->warehouses()->pluck('id')` inside
        // `getNavigationBadge()` is prohibited (§1B.5).
        return parent::getEloquentQuery()
            ->with(['items'])
            ->when(
                ! auth()->user()->isAdmin() && ! auth()->user()->isAuditor(),
                function (Builder $q) {
                    $ids = auth()->user()->warehouses()->pluck('id')->all();
                    $q->whereIn('warehouse_id', $ids);
                },
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExamples::route('/'),
            'create' => CreateExample::route('/create'),
            'view' => ViewExample::route('/{record}'),
            'edit' => EditExample::route('/{record}/edit'),
        ];
    }
}
```

### 18.1a Thin Resource Classes

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
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    protected static ?string $model = ProductVariant::class;
    protected static string | \UnitEnum | null $navigationGroup = 'CATALOG';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'sku';
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedCube;
    protected static string | \BackedEnum | null $activeNavigationIcon = Heroicon::Cube;

    public static function getModelLabel(): string
    {
        return __('resources.products.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.products.model.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.products.navigation.label');
    }

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
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'view' => ViewProduct::route('/{record}'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
```

```php
namespace App\Filament\Resources\TransferRequisitions;

use App\Filament\Resources\TransferRequisitions\Pages\CreateTransferRequisition;
use App\Filament\Resources\TransferRequisitions\Pages\EditTransferRequisition;
use App\Filament\Resources\TransferRequisitions\Pages\ListTransferRequisitions;
use App\Filament\Resources\TransferRequisitions\Pages\ViewTransferRequisition;
use App\Filament\Resources\TransferRequisitions\Schemas\TransferRequisitionForm;
use App\Filament\Resources\TransferRequisitions\Schemas\TransferRequisitionInfolist;
use App\Filament\Resources\TransferRequisitions\Tables\TransferRequisitionsTable;
use App\Filament\Support\Concerns\ScopesNavigationBadges;
use App\Enums\TransferRequisitionStatus;
use App\Models\TransferRequisition;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransferRequisitionResource extends Resource
{
    use ScopesNavigationBadges;

    protected static ?string $model = TransferRequisition::class;
    protected static string | \UnitEnum | null $navigationGroup = 'OPERATIONS';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'reference_code';
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;
    protected static string | \BackedEnum | null $activeNavigationIcon = Heroicon::ArrowsRightLeft;

    public static function getModelLabel(): string
    {
        return __('resources.transfer_requisitions.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.transfer_requisitions.model.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.transfer_requisitions.navigation.label');
    }

    public static function form(Schema $schema): Schema
    {
        return TransferRequisitionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransferRequisitionsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TransferRequisitionInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['fromWarehouse', 'toWarehouse', 'requestedBy', 'approvedBy', 'dispatchedBy', 'receivedBy', 'items.productVariant', 'items.substituteProductVariant', 'items.revisions', 'items.revisions.user'])
            ->withCount('items')
            ->when(
                ! auth()->user()->isAdmin() && ! auth()->user()->isAuditor(),
                function (Builder $q) {
                    $ids = auth()->user()->warehouses()->pluck('id')->all();
                    $q->where(function (Builder $qq) use ($ids) {
                        $qq->whereIn('from_warehouse_id', $ids)
                            ->orWhereIn('to_warehouse_id', $ids);
                    });
                }
            );
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    // Badge implementation canonical in §1B.3a; inlined here verbatim per file contract.

    private static function getScopedBadgeCount(): int
    {
        if (self::$badgeCount !== null) {
            return self::$badgeCount;
        }

        if (! self::hasBadgeScope()) {
            return self::$badgeCount = 0;
        }

        $warehouseIds = self::badgeScopedWarehouseIds();

        return self::$badgeCount = static::getModel()::query()
            ->where('status', TransferRequisitionStatus::Requested->value)
            ->where(function ($q) use ($warehouseIds) {
                $q->whereIn('from_warehouse_id', $warehouseIds)
                  ->orWhereIn('to_warehouse_id', $warehouseIds);
            })
            ->count();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getScopedBadgeCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return self::getScopedBadgeCount() > 10 ? 'warning' : 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('resources.transfer_requisitions.badge_tooltip');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransferRequisitions::route('/'),
            'create' => CreateTransferRequisition::route('/create'),
            'view' => ViewTransferRequisition::route('/{record}'),
            'edit' => EditTransferRequisition::route('/{record}/edit'),
        ];
    }
}
```

> `DirectTransferResource` (`app/Filament/Resources/DirectTransfers/DirectTransferResource.php`) — canonical class definition in §7C.6, not repeated here. §7C owns the class contract; this section records only the file contract.

```php
namespace App\Filament\Resources\PurchaseOrders;

use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use App\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderInfolist;
use App\Filament\Resources\PurchaseOrders\Tables\PurchaseOrdersTable;
use App\Filament\Support\Concerns\ScopesNavigationBadges;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PurchaseOrderResource extends Resource
{
    use ScopesNavigationBadges;

    protected static ?string $model = PurchaseOrder::class;
    protected static string | \UnitEnum | null $navigationGroup = 'PURCHASING';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'reference_code';
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedShoppingCart;
    protected static string | \BackedEnum | null $activeNavigationIcon = Heroicon::ShoppingCart;

    public static function getModelLabel(): string
    {
        return __('resources.purchase_orders.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.purchase_orders.model.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.purchase_orders.navigation.label');
    }

    public static function form(Schema $schema): Schema
    {
        return PurchaseOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseOrdersTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PurchaseOrderInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['supplier', 'warehouse', 'orderedBy', 'receivedBy', 'items.productVariant'])
            ->withCount('items')
            ->when(
                ! auth()->user()->isAdmin() && ! auth()->user()->isAuditor(),
                function (Builder $q) {
                    $ids = auth()->user()->warehouses()->pluck('id')->all();
                    $q->whereIn('warehouse_id', $ids);
                }
            );
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    // Badge implementation canonical in §1B.3a; inlined here verbatim per file contract.

    private static function getScopedBadgeCount(): int
    {
        if (self::$badgeCount !== null) {
            return self::$badgeCount;
        }

        if (! self::hasBadgeScope()) {
            return self::$badgeCount = 0;
        }

        return self::$badgeCount = static::getModel()::query()
            ->where('status', PurchaseOrderStatus::Ordered->value)
            ->whereIn('warehouse_id', self::badgeScopedWarehouseIds())
            ->count();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getScopedBadgeCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return self::getScopedBadgeCount() > 10 ? 'warning' : 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('resources.purchase_orders.badge_tooltip');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'view' => ViewPurchaseOrder::route('/{record}'),
            'edit' => EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
```

```php
namespace App\Filament\Resources\SalesOrders;

use App\Filament\Resources\SalesOrders\Pages\CreateSalesOrder;
use App\Filament\Resources\SalesOrders\Pages\EditSalesOrder;
use App\Filament\Resources\SalesOrders\Pages\ListSalesOrders;
use App\Filament\Resources\SalesOrders\Pages\ViewSalesOrder;
use App\Filament\Resources\SalesOrders\Schemas\SalesOrderForm;
use App\Filament\Resources\SalesOrders\Schemas\SalesOrderInfolist;
use App\Filament\Resources\SalesOrders\Tables\SalesOrdersTable;
use App\Filament\Support\Concerns\ScopesNavigationBadges;
use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SalesOrderResource extends Resource
{
    use ScopesNavigationBadges;

    protected static ?string $model = SalesOrder::class;
    protected static string | \UnitEnum | null $navigationGroup = 'SALES';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'reference_code';
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedBanknotes;
    protected static string | \BackedEnum | null $activeNavigationIcon = Heroicon::Banknotes;

    public static function getModelLabel(): string
    {
        return __('resources.sales_orders.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.sales_orders.model.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.sales_orders.navigation.label');
    }

    public static function form(Schema $schema): Schema
    {
        return SalesOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesOrdersTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalesOrderInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['customer', 'warehouse', 'orderedBy', 'dispatchedBy', 'items.productVariant'])
            ->withCount('items')
            ->when(
                ! auth()->user()->isAdmin() && ! auth()->user()->isAuditor(),
                function (Builder $q) {
                    $ids = auth()->user()->warehouses()->pluck('id')->all();
                    $q->whereIn('warehouse_id', $ids);
                }
            );
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    // Badge implementation canonical in §1B.3a; inlined here verbatim per file contract.

    private static function getScopedBadgeCount(): int
    {
        if (self::$badgeCount !== null) {
            return self::$badgeCount;
        }

        if (! self::hasBadgeScope()) {
            return self::$badgeCount = 0;
        }

        return self::$badgeCount = static::getModel()::query()
            ->where('status', SalesOrderStatus::Confirmed->value)
            ->whereIn('warehouse_id', self::badgeScopedWarehouseIds())
            ->count();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getScopedBadgeCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return self::getScopedBadgeCount() > 10 ? 'warning' : 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('resources.sales_orders.badge_tooltip');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesOrders::route('/'),
            'create' => CreateSalesOrder::route('/create'),
            'view' => ViewSalesOrder::route('/{record}'),
            'edit' => EditSalesOrder::route('/{record}/edit'),
        ];
    }
}
```

```php
namespace App\Filament\Resources\InTransits;

use App\Filament\Resources\InTransits\Pages\ListInTransits;
use App\Filament\Resources\InTransits\Pages\ViewInTransit;
use App\Filament\Resources\InTransits\Schemas\InTransitInfolist;
use App\Filament\Resources\InTransits\Tables\InTransitsTable;
use App\Filament\Support\Concerns\ScopesNavigationBadges;
use App\Enums\InTransitStatus;
use App\Models\InTransit;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InTransitResource extends Resource
{
    use ScopesNavigationBadges;

    protected static ?string $model = InTransit::class;
    protected static string | \UnitEnum | null $navigationGroup = 'OPERATIONS';
    protected static ?int $navigationSort = 3;
    protected static ?string $recordTitleAttribute = null;
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedTruck;
    protected static string | \BackedEnum | null $activeNavigationIcon = Heroicon::Truck;

    public static function getModelLabel(): string
    {
        return __('resources.in_transits.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.in_transits.model.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.in_transits.navigation.label');
    }

    public static function table(Table $table): Table
    {
        return InTransitsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InTransitInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['transferRequisition', 'transferRequisitionItem', 'productVariant'])
            ->when(
                ! auth()->user()->isAdmin() && ! auth()->user()->isAuditor(),
                function (Builder $q) {
                    $ids = auth()->user()->warehouses()->pluck('id')->all();
                    $q->whereHas('transferRequisition', function (Builder $qq) use ($ids) {
                        $qq->whereIn('from_warehouse_id', $ids)
                            ->orWhereIn('to_warehouse_id', $ids);
                    });
                }
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInTransits::route('/'),
            'view' => ViewInTransit::route('/{record}'),
        ];
    }

    // Badge implementation canonical in §1B.3a; inlined here verbatim per file contract.

    private static function getScopedBadgeCount(): int
    {
        if (self::$badgeCount !== null) {
            return self::$badgeCount;
        }

        if (! self::hasBadgeScope()) {
            return self::$badgeCount = 0;
        }

        // In-transit rows are scoped by the warehouses of their parent
        // requisition. Both endpoints participate because the cargo is in
        // motion between them.
        $warehouseIds = self::badgeScopedWarehouseIds();

        return self::$badgeCount = static::getModel()::query()
            ->where('status', InTransitStatus::InTransit->value)
            ->whereHas('transferRequisition', function ($q) use ($warehouseIds) {
                $q->whereIn('from_warehouse_id', $warehouseIds)
                  ->orWhereIn('to_warehouse_id', $warehouseIds);
            })
            ->count();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getScopedBadgeCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return self::getScopedBadgeCount() > 10 ? 'warning' : 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('resources.in_transits.badge_tooltip');
    }
}
```

```php
namespace App\Filament\Resources\StockMovements;

use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Resources\StockMovements\Pages\ViewStockMovement;
use App\Filament\Resources\StockMovements\Schemas\StockMovementInfolist;
use App\Filament\Resources\StockMovements\Tables\StockMovementsTable;
use App\Models\StockMovement;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;
    protected static string | \UnitEnum | null $navigationGroup = 'AUDIT LEDGERS';
    protected static ?int $navigationSort = 1;
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedQueueList;
    protected static string | \BackedEnum | null $activeNavigationIcon = Heroicon::QueueList;

    public static function getModelLabel(): string
    {
        return __('resources.stock_movements.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.stock_movements.model.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.stock_movements.navigation.label');
    }

    public static function table(Table $table): Table
    {
        return StockMovementsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StockMovementInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['productVariant', 'warehouse', 'createdBy'])
            ->when(
                ! auth()->user()->isAdmin() && ! auth()->user()->isAuditor(),
                function (Builder $q) {
                    $ids = auth()->user()->warehouses()->pluck('id')->all();
                    $q->whereIn('warehouse_id', $ids);
                }
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockMovements::route('/'),
            'view' => ViewStockMovement::route('/{record}'),
        ];
    }
}
```

```php
namespace App\Filament\Resources\LossLedgers;

use App\Filament\Resources\LossLedgers\Pages\ListLossLedgers;
use App\Filament\Resources\LossLedgers\Pages\ViewLossLedger;
use App\Filament\Resources\LossLedgers\Schemas\LossLedgerInfolist;
use App\Filament\Resources\LossLedgers\Tables\LossLedgersTable;
use App\Models\LossLedger;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LossLedgerResource extends Resource
{
    protected static ?string $model = LossLedger::class;
    protected static string | \UnitEnum | null $navigationGroup = 'AUDIT LEDGERS';
    protected static ?int $navigationSort = 2;
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedExclamationTriangle;
    protected static string | \BackedEnum | null $activeNavigationIcon = Heroicon::ExclamationTriangle;

    public static function getModelLabel(): string
    {
        return __('resources.loss_ledgers.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.loss_ledgers.model.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.loss_ledgers.navigation.label');
    }

    public static function table(Table $table): Table
    {
        return LossLedgersTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LossLedgerInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['transferRequisition', 'transferRequisitionItem', 'productVariant', 'warehouse', 'recordedBy'])
            ->when(
                ! auth()->user()->isAdmin() && ! auth()->user()->isAuditor(),
                function (Builder $q) {
                    $ids = auth()->user()->warehouses()->pluck('id')->all();
                    $q->whereIn('warehouse_id', $ids);
                }
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLossLedgers::route('/'),
            'view' => ViewLossLedger::route('/{record}'),
        ];
    }
}
```

> `WarehouseResource` (`app/Filament/Resources/Warehouses/WarehouseResource.php`) — canonical class definition in §7K.4, not repeated here. §7K owns the class contract; this section records only the file contract.

> `UserResource` (`app/Filament/Resources/Users/UserResource.php`) — canonical class definition in §7L.3, not repeated here. §7L owns the class contract; this section records only the file contract.

```php
namespace App\Filament\Resources\Suppliers;

use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Resources\Suppliers\Schemas\SupplierForm;
use App\Filament\Resources\Suppliers\Tables\SuppliersTable;
use App\Models\Supplier;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;
    protected static string | \UnitEnum | null $navigationGroup = 'PURCHASING';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'name';
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedBuildingStorefront;
    protected static string | \BackedEnum | null $activeNavigationIcon = Heroicon::BuildingStorefront;

    public static function getModelLabel(): string
    {
        return __('resources.suppliers.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.suppliers.model.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.suppliers.navigation.label');
    }

    public static function form(Schema $schema): Schema
    {
        return SupplierForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SuppliersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('purchaseOrders');
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
```

```php
namespace App\Filament\Resources\Customers;

use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Resources\Customers\Tables\CustomersTable;
use App\Models\Customer;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;
    protected static string | \UnitEnum | null $navigationGroup = 'SALES';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'name';
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedUserGroup;
    protected static string | \BackedEnum | null $activeNavigationIcon = Heroicon::UserGroup;

    public static function getModelLabel(): string
    {
        return __('resources.customers.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.customers.model.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.customers.navigation.label');
    }

    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('salesOrders');
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
```

---

### 18.2 Required Page Classes

The following page classes are mandatory implementation surfaces, not documentation-only names:

```text
Products:
  ListProducts
  CreateProduct
  EditProduct
  ViewProduct

TransferRequisitions:
  ListTransferRequisitions
  CreateTransferRequisition
  EditTransferRequisition
  ViewTransferRequisition

DirectTransfers:
  ListDirectTransfers
  CreateDirectTransfer
  ViewDirectTransfer

InTransits:
  ListInTransits
  ViewInTransit

StockMovements:
  ListStockMovements
  ViewStockMovement

LossLedgers:
  ListLossLedgers
  ViewLossLedger

Warehouses:
  ListWarehouses
  CreateWarehouse
  EditWarehouse
  ViewWarehouse

Users:
  ListUsers
  CreateUser
  EditUser

PurchaseOrders:
  ListPurchaseOrders
  CreatePurchaseOrder
  EditPurchaseOrder
  ViewPurchaseOrder

SalesOrders:
  ListSalesOrders
  CreateSalesOrder
  EditSalesOrder
  ViewSalesOrder

Suppliers:
  ListSuppliers
  CreateSupplier
  EditSupplier

Customers:
  ListCustomers
  CreateCustomer
  EditCustomer
```

### 18.2a Page Classes

List/Edit/View pages below are intentionally thin (`ListRecords`/`EditRecord`/`ViewRecord` with only `$resource`) — the resource's schema/table/infolist contracts carry the behavior. Thin here means complete by design, not pending work: no page-level overrides are required unless a page adds page-specific actions or header widgets. The four wizard `Create*` pages are NOT thin: their canonical `HasWizard` implementations live in §7B.2 (`CreateTransferRequisition`), §7C.2 (`CreateDirectTransfer`), §7G.2 (`CreatePurchaseOrder`), and §7H.2 (`CreateSalesOrder`). Those sections are authoritative; no definition in this section may contradict them. Likewise `ViewDirectTransfer` is defined once in §7C.3, and `DirectTransferResource`, `WarehouseResource`, and `UserResource` are defined once in §7C.6, §7K.4, and §7L.3 respectively — this section records only their file contracts, never a second class body.

`View*` pages (`ViewPurchaseOrder`, `ViewSalesOrder`, `ViewWarehouse`, and peers) are complete as thin `ViewRecord` subclasses: a Filament v5 view page renders the resource's `infolist()` contract and needs no overrides unless it adds page-specific actions or header widgets. The single exception by design is `ViewTransferRequisition`, which defines `getHeaderActions()` carrying the `submitRevision`, `acceptRevision`, and `rejectRevision` negotiation modals (§7B.3 `openReview` links to this view page for negotiation states, which are not editable via the edit route). A view route is implemented exactly when its `ViewRecord` subclass is autoloadable and the resource defines `infolist()`.

**One-class-per-file contract (documentation shorthand):** each ` ```php ` block below is a documentation shorthand that groups several one-class-per-file definitions under their shared `namespace` header to avoid repeating identical `use` imports. It is NOT a multi-class file: each class MUST live in its own file exactly as enumerated in §25 — `app/Filament/Resources/<Resource>/Pages/<Class>.php` contains exactly `class <Class>`. A block headed `namespace App\Filament\Resources\X\Pages;` containing classes `A`, `B`, `C` therefore maps to three files — `Pages/A.php`, `Pages/B.php`, `Pages/C.php` — not one.

```php
namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ViewRecord;

// File: app/Filament/Resources/Products/Pages/ListProducts.php
class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;
}

// File: app/Filament/Resources/Products/Pages/CreateProduct.php
class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('resources.products.notifications.created');
    }
}

// File: app/Filament/Resources/Products/Pages/EditProduct.php
class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getUpdatedNotificationTitle(): ?string
    {
        return __('resources.products.notifications.updated');
    }
}

// File: app/Filament/Resources/Products/Pages/ViewProduct.php
class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;
}
```

```php
namespace App\Filament\Resources\TransferRequisitions\Pages;

use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use App\Enums\TransferRequisitionStatus;
use App\Filament\Resources\TransferRequisitions\Schemas\TransferRequisitionForm;
use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItemRevision;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

// File: app/Filament/Resources/TransferRequisitions/Pages/ListTransferRequisitions.php
class ListTransferRequisitions extends ListRecords
{
    protected static string $resource = TransferRequisitionResource::class;
}

// File: app/Filament/Resources/TransferRequisitions/Pages/EditTransferRequisition.php
class EditTransferRequisition extends EditRecord
{
    protected static string $resource = TransferRequisitionResource::class;
}

// File: app/Filament/Resources/TransferRequisitions/Pages/ViewTransferRequisition.php
class ViewTransferRequisition extends ViewRecord
{
    protected static string $resource = TransferRequisitionResource::class;

    /**
     * Negotiation states are NOT editable (TransferRequisitionPolicy::update
     * permits Draft only), so the `submitRevision`, `acceptRevision`, and
     * `rejectRevision` modals live here as header actions — §7B.3
     * `openReview` links to this view page.
     * Definitions mirror the `submitRevision` / `acceptRevision` /
     * `rejectRevision` record actions in TransferRequisitionsTable (§7B.3):
     * same ability, visibility, schema, and NegotiationService call.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('submitRevision')
                ->label(__('resources.transfer_requisitions.actions.propose_revision'))
                ->modalHeading(__('resources.transfer_requisitions.actions.propose_revision_heading'))
                ->modalDescription(__('resources.transfer_requisitions.actions.propose_revision_description'))
                ->icon(Heroicon::ChatBubbleLeftRight)
                ->color('warning')
                ->authorize('negotiate')
                ->visible(fn (TransferRequisition $record) => in_array($record->status, [
                    TransferRequisitionStatus::Requested,
                    TransferRequisitionStatus::UnderReviewFulfiller,
                    TransferRequisitionStatus::UnderReviewRequestor,
                ], true))
                ->modalWidth(Width::FourExtraLarge)
                ->schema(fn (TransferRequisition $record) => TransferRequisitionForm::getRevisionFields($record))
                ->action(function (array $data, TransferRequisition $record) {
                    $item = $record->items()->findOrFail((int) $data['transfer_requisition_item_id']);

                    app(\App\Services\NegotiationService::class)->submitRevision(
                        item: $item,
                        substituteVariantId: $data['substitute_product_variant_id'] ?? null,
                        side: NegotiationSide::from($data['side']),
                        proposedUnitName: (string) $data['proposed_unit_name'],
                        proposedQty: (int) $data['proposed_qty'],
                        negotiationReason: $data['negotiation_reason'] ?? null,
                        respondsToRevisionId: $data['responds_to_revision_id'] ?? null,
                    );

                    Notification::make()
                        ->title(__('resources.transfer_requisitions.notifications.revision_submitted'))
                        ->success()
                        ->send();
                }),

            Action::make('acceptRevision')
                ->label(__('resources.transfer_requisitions.actions.accept_revision'))
                ->modalHeading(__('resources.transfer_requisitions.actions.accept_revision_heading'))
                ->modalDescription(__('resources.transfer_requisitions.actions.accept_revision_description'))
                ->modalSubmitActionLabel(__('actions.confirm'))
                ->icon(Heroicon::CheckCircle)
                ->color('success')
                ->authorize('acceptRevision')
                ->visible(fn (TransferRequisition $record) => in_array($record->status, [
                    TransferRequisitionStatus::Requested,
                    TransferRequisitionStatus::UnderReviewFulfiller,
                    TransferRequisitionStatus::UnderReviewRequestor,
                ], true) && $record->items->flatMap(fn ($item) => $item->revisions)
                    ->contains(fn ($revision) => $revision->status === RevisionStatus::Pending))
                ->schema(fn (TransferRequisition $record) => [
                    Select::make('revision_id')
                        ->label(__('resources.transfer_requisitions.fields.revision'))
                        ->prefixIcon(Heroicon::CheckCircle)
                        ->columnSpanFull()
                        ->options(fn () => $record->items
                            ->flatMap(fn ($item) => $item->revisions
                                ->where('status', RevisionStatus::Pending)
                                ->mapWithKeys(fn ($revision) => [
                                    $revision->id => "{$item->productVariant->sku}: {$revision->proposed_qty} {$revision->proposed_unit_name}",
                                ]))
                            ->toArray())
                        ->required(),
                ])
                ->action(function (array $data, TransferRequisition $record) {
                    $revision = TransferRequisitionItemRevision::query()
                        ->findOrFail((int) $data['revision_id']);

                    abort_unless(
                        (int) $revision->item->transfer_requisition_id === (int) $record->id,
                        403,
                    );

                    app(\App\Services\NegotiationService::class)->accept($revision);

                    Notification::make()
                        ->title(__('resources.transfer_requisitions.notifications.revision_accepted'))
                        ->success()
                        ->send();
                })
                ->requiresConfirmation(),

            Action::make('rejectRevision')
                ->label(__('resources.transfer_requisitions.actions.reject_revision'))
                ->modalHeading(__('resources.transfer_requisitions.actions.reject_revision_heading'))
                ->modalDescription(__('resources.transfer_requisitions.actions.reject_revision_description'))
                ->modalSubmitActionLabel(__('actions.confirm'))
                ->icon(Heroicon::XCircle)
                ->color('danger')
                ->authorize('rejectRevision')
                ->visible(fn (TransferRequisition $record) => in_array($record->status, [
                    TransferRequisitionStatus::Requested,
                    TransferRequisitionStatus::UnderReviewFulfiller,
                    TransferRequisitionStatus::UnderReviewRequestor,
                ], true) && $record->items->flatMap(fn ($item) => $item->revisions)
                    ->contains(fn ($revision) => $revision->status === RevisionStatus::Pending))
                ->schema(fn (TransferRequisition $record) => [
                    Select::make('revision_id')
                        ->label(__('resources.transfer_requisitions.fields.revision'))
                        ->prefixIcon(Heroicon::XCircle)
                        ->columnSpanFull()
                        ->options(fn () => $record->items
                            ->flatMap(fn ($item) => $item->revisions
                                ->where('status', RevisionStatus::Pending)
                                ->mapWithKeys(fn ($revision) => [
                                    $revision->id => "{$item->productVariant->sku}: {$revision->proposed_qty} {$revision->proposed_unit_name}",
                                ]))
                            ->toArray())
                        ->required(),
                ])
                ->action(function (array $data, TransferRequisition $record) {
                    $revision = TransferRequisitionItemRevision::query()
                        ->findOrFail((int) $data['revision_id']);

                    abort_unless(
                        (int) $revision->item->transfer_requisition_id === (int) $record->id,
                        403,
                    );

                    app(\App\Services\NegotiationService::class)->reject($revision);

                    Notification::make()
                        ->title(__('resources.transfer_requisitions.notifications.revision_rejected'))
                        ->success()
                        ->send();
                })
                ->requiresConfirmation(),
        ];
    }
}
```

```php
namespace App\Filament\Resources\DirectTransfers\Pages;

use App\Filament\Resources\DirectTransfers\DirectTransferResource;
use Filament\Resources\Pages\ListRecords;

// File: app/Filament/Resources/DirectTransfers/Pages/ListDirectTransfers.php
class ListDirectTransfers extends ListRecords
{
    protected static string $resource = DirectTransferResource::class;
}

// `ViewDirectTransfer` — canonical definition in §7C.3, not repeated here
// (one-class-per-file: `app/Filament/Resources/DirectTransfers/Pages/ViewDirectTransfer.php` per §25).
```

```php
namespace App\Filament\Resources\InTransits\Pages;

use App\Filament\Resources\InTransits\InTransitResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ViewRecord;

// File: app/Filament/Resources/InTransits/Pages/ListInTransits.php
class ListInTransits extends ListRecords
{
    protected static string $resource = InTransitResource::class;
}

// File: app/Filament/Resources/InTransits/Pages/ViewInTransit.php
class ViewInTransit extends ViewRecord
{
    protected static string $resource = InTransitResource::class;
}
```

```php
namespace App\Filament\Resources\StockMovements\Pages;

use App\Filament\Resources\StockMovements\StockMovementResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ViewRecord;

// File: app/Filament/Resources/StockMovements/Pages/ListStockMovements.php
class ListStockMovements extends ListRecords
{
    protected static string $resource = StockMovementResource::class;
}

// File: app/Filament/Resources/StockMovements/Pages/ViewStockMovement.php
class ViewStockMovement extends ViewRecord
{
    protected static string $resource = StockMovementResource::class;
}
```

```php
namespace App\Filament\Resources\LossLedgers\Pages;

use App\Filament\Resources\LossLedgers\LossLedgerResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ViewRecord;

// File: app/Filament/Resources/LossLedgers/Pages/ListLossLedgers.php
class ListLossLedgers extends ListRecords
{
    protected static string $resource = LossLedgerResource::class;
}

// File: app/Filament/Resources/LossLedgers/Pages/ViewLossLedger.php
class ViewLossLedger extends ViewRecord
{
    protected static string $resource = LossLedgerResource::class;
}
```

```php
namespace App\Filament\Resources\Warehouses\Pages;

use App\Exceptions\DomainRuleViolationException;
use App\Filament\Resources\Warehouses\WarehouseResource;
use App\Models\Warehouse;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ViewRecord;

// File: app/Filament/Resources/Warehouses/Pages/ListWarehouses.php
class ListWarehouses extends ListRecords
{
    protected static string $resource = WarehouseResource::class;
}

// File: app/Filament/Resources/Warehouses/Pages/CreateWarehouse.php
class CreateWarehouse extends CreateRecord
{
    protected static string $resource = WarehouseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // §2 warehouses.code rule: a blank code is derived from `name`,
        // never stored as an empty string.
        if (blank($data['code'] ?? null)) {
            $data['code'] = self::deriveCode((string) ($data['name'] ?? ''));
        }

        return $data;
    }

    /**
     * Derive a unique warehouse code from the warehouse name (§2):
     * uppercase, non-alphanumeric runs replaced with `-`, trimmed,
     * truncated to 50 chars, `-NNN` appended on unique collision.
     * The base is shortened to 46 chars when a suffix is required so the
     * result keeps the form's `maxLength(50)` ceiling.
     */
    private static function deriveCode(string $name): string
    {
        $base = substr(trim((string) preg_replace('/[^A-Z0-9]+/', '-', strtoupper($name)), '-'), 0, 50);

        if ($base === '') {
            $base = 'WH';
        }

        if (! Warehouse::where('code', $base)->exists()) {
            return $base;
        }

        for ($i = 1; $i <= 999; $i++) {
            $candidate = substr($base, 0, 46) . '-' . sprintf('%03d', $i);

            if (! Warehouse::where('code', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new DomainRuleViolationException('errors.warehouse_code_exhausted', ['name' => $name]);
    }
}

// File: app/Filament/Resources/Warehouses/Pages/EditWarehouse.php
class EditWarehouse extends EditRecord
{
    protected static string $resource = WarehouseResource::class;
}

// File: app/Filament/Resources/Warehouses/Pages/ViewWarehouse.php
class ViewWarehouse extends ViewRecord
{
    protected static string $resource = WarehouseResource::class;
}
```

```php
namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

// File: app/Filament/Resources/Users/Pages/ListUsers.php
class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;
}

// File: app/Filament/Resources/Users/Pages/CreateUser.php
class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}

// File: app/Filament/Resources/Users/Pages/EditUser.php
class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;
}
```

```php
namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ViewRecord;

// File: app/Filament/Resources/PurchaseOrders/Pages/ListPurchaseOrders.php
class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;
}

// File: app/Filament/Resources/PurchaseOrders/Pages/EditPurchaseOrder.php
class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;
}

// File: app/Filament/Resources/PurchaseOrders/Pages/ViewPurchaseOrder.php
class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;
}
```

```php
namespace App\Filament\Resources\SalesOrders\Pages;

use App\Filament\Resources\SalesOrders\SalesOrderResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ViewRecord;

// File: app/Filament/Resources/SalesOrders/Pages/ListSalesOrders.php
class ListSalesOrders extends ListRecords
{
    protected static string $resource = SalesOrderResource::class;
}

// File: app/Filament/Resources/SalesOrders/Pages/EditSalesOrder.php
class EditSalesOrder extends EditRecord
{
    protected static string $resource = SalesOrderResource::class;
}

// File: app/Filament/Resources/SalesOrders/Pages/ViewSalesOrder.php
class ViewSalesOrder extends ViewRecord
{
    protected static string $resource = SalesOrderResource::class;
}
```

```php
namespace App\Filament\Resources\Suppliers\Pages;

use App\Filament\Resources\Suppliers\SupplierResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

// File: app/Filament/Resources/Suppliers/Pages/ListSuppliers.php
class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;
}

// File: app/Filament/Resources/Suppliers/Pages/CreateSupplier.php
class CreateSupplier extends CreateRecord
{
    protected static string $resource = SupplierResource::class;
}

// File: app/Filament/Resources/Suppliers/Pages/EditSupplier.php
class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;
}
```

```php
namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

// File: app/Filament/Resources/Customers/Pages/ListCustomers.php
class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;
}

// File: app/Filament/Resources/Customers/Pages/CreateCustomer.php
class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;
}

// File: app/Filament/Resources/Customers/Pages/EditCustomer.php
class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;
}
```

A Resource route is not considered implemented until the referenced Page class can be autoloaded.

### 18.3 Wizard Review Component

Each wizard `Create*` page renders its read-only review step directly from the shipped blade review view. There is **no** static renderer class, **no** `Placeholder::make('review_summary')` wiring, and **no** Livewire review component.

All four pages (§7B.2 TransferRequisition, §7C.2 DirectTransfer, §7G.2 PurchaseOrder, §7H.2 SalesOrder) render the review via `Filament\Schemas\Components\View::make('filament.wizards.<name>-review')->viewData(fn (Get $get): array => ['state' => [...]])->columnSpanFull()`, passing a narrow, form-field-derived `state` array (not the whole `$get()`).

> **Runtime:** there is no `App\Filament\Support\Wizards\WizardReviewStep` class on disk and none should be created. The former `renderSummary()` static contract is retired. `App\Livewire\Wizards\WizardReviewSummary` and `app/Filament/Components/WizardReviewStep.php` were both deleted — the inline `View::make()` approach made them redundant (all four pages now share one pattern).

Review views live under `resources/views/filament/wizards/` (§0A.11) and render the raw wizard `$state` array (keys mirror the form field names). All strings resolve through `__()`:

```blade
{{-- resources/views/filament/wizards/transfer-review.blade.php --}}
@php
    // Wizard state carries foreign keys (§7B.1: `from_warehouse_id`,
    // `to_warehouse_id`, repeater `product_variant_id`). Display names are
    // resolved from those keys at render time — never stored in the state.
    $fromName = isset($state['from_warehouse_id']) ? \App\Models\Warehouse::find($state['from_warehouse_id'])?->name : null;
    $toName = isset($state['to_warehouse_id']) ? \App\Models\Warehouse::find($state['to_warehouse_id'])?->name : null;
@endphp
<div class="space-y-4">
    <h3 class="text-base font-semibold">{{ __('wizards.transfer_review.title') }}</h3>
    <dl class="grid grid-cols-2 gap-3 text-sm">
        <div>
            <dt class="text-zinc-500">{{ __('wizards.transfer_review.from') }}</dt>
            <dd class="font-medium">{{ $fromName ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-zinc-500">{{ __('wizards.transfer_review.to') }}</dt>
            <dd class="font-medium">{{ $toName ?? '—' }}</dd>
        </div>
    </dl>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-zinc-500">
                <th>{{ __('wizards.transfer_review.sku') }}</th>
                <th>{{ __('wizards.transfer_review.qty') }}</th>
                <th>{{ __('wizards.transfer_review.unit') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($state['items'] ?? []) as $item)
                @php($sku = isset($item['product_variant_id']) ? \App\Models\ProductVariant::find($item['product_variant_id'])?->sku : null)
                <tr class="border-t border-zinc-200">
                    <td class="font-mono">{{ $sku ?? '—' }}</td>
                    <td>{{ $item['requested_qty'] ?? 0 }}</td>
                    <td>{{ $item['requested_unit_name'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @if (! empty($state['notes']))
        <p class="text-sm text-zinc-500">{{ $state['notes'] }}</p>
    @endif
</div>
```

```blade
{{-- resources/views/filament/wizards/purchase-order-review.blade.php --}}
@php
    // Wizard state carries foreign keys (§7G.1: `supplier_id`,
    // `warehouse_id`, repeater `product_variant_id`). Display names are
    // resolved from those keys at render time — never stored in the state.
    $supplierName = isset($state['supplier_id']) ? \App\Models\Supplier::find($state['supplier_id'])?->name : null;
    $warehouseName = isset($state['warehouse_id']) ? \App\Models\Warehouse::find($state['warehouse_id'])?->name : null;
@endphp
<div class="space-y-4">
    <h3 class="text-base font-semibold">{{ __('wizards.purchase_order_review.title') }}</h3>
    <dl class="grid grid-cols-2 gap-3 text-sm">
        <div>
            <dt class="text-zinc-500">{{ __('wizards.purchase_order_review.supplier') }}</dt>
            <dd class="font-medium">{{ $supplierName ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-zinc-500">{{ __('wizards.purchase_order_review.warehouse') }}</dt>
            <dd class="font-medium">{{ $warehouseName ?? '—' }}</dd>
        </div>
    </dl>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-zinc-500">
                <th>{{ __('wizards.purchase_order_review.sku') }}</th>
                <th>{{ __('wizards.purchase_order_review.qty') }}</th>
                <th>{{ __('wizards.purchase_order_review.unit_cost') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($state['items'] ?? []) as $item)
                @php($sku = isset($item['product_variant_id']) ? \App\Models\ProductVariant::find($item['product_variant_id'])?->sku : null)
                <tr class="border-t border-zinc-200">
                    <td class="font-mono">{{ $sku ?? '—' }}</td>
                    <td>{{ $item['ordered_qty'] ?? 0 }}</td>
                    <td>{{ $item['unit_cost_price'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @if (! empty($state['notes']))
        <p class="text-sm text-zinc-500">{{ $state['notes'] }}</p>
    @endif
</div>
```

```blade
{{-- resources/views/filament/wizards/sales-order-review.blade.php --}}
@php
    // Wizard state carries foreign keys (§7H.1: `customer_id`,
    // `warehouse_id`, repeater `product_variant_id`). Display names are
    // resolved from those keys at render time — never stored in the state.
    $customerName = isset($state['customer_id']) ? \App\Models\Customer::find($state['customer_id'])?->name : null;
    $warehouseName = isset($state['warehouse_id']) ? \App\Models\Warehouse::find($state['warehouse_id'])?->name : null;
@endphp
<div class="space-y-4">
    <h3 class="text-base font-semibold">{{ __('wizards.sales_order_review.title') }}</h3>
    <dl class="grid grid-cols-2 gap-3 text-sm">
        <div>
            <dt class="text-zinc-500">{{ __('wizards.sales_order_review.customer') }}</dt>
            <dd class="font-medium">{{ $customerName ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-zinc-500">{{ __('wizards.sales_order_review.warehouse') }}</dt>
            <dd class="font-medium">{{ $warehouseName ?? '—' }}</dd>
        </div>
    </dl>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-zinc-500">
                <th>{{ __('wizards.sales_order_review.sku') }}</th>
                <th>{{ __('wizards.sales_order_review.qty') }}</th>
                <th>{{ __('wizards.sales_order_review.unit') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($state['items'] ?? []) as $item)
                @php($sku = isset($item['product_variant_id']) ? \App\Models\ProductVariant::find($item['product_variant_id'])?->sku : null)
                <tr class="border-t border-zinc-200">
                    <td class="font-mono">{{ $sku ?? '—' }}</td>
                    <td>{{ $item['qty'] ?? 0 }}</td>
                    <td>{{ $item['unit_name'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @if (! empty($state['notes']))
        <p class="text-sm text-zinc-500">{{ $state['notes'] }}</p>
    @endif
</div>
```

```blade
{{-- resources/views/filament/wizards/direct-transfer-review.blade.php --}}
@php
    // Wizard state carries foreign keys (§7C.1: `from_warehouse_id`,
    // `to_warehouse_id`, repeater `product_variant_id`). Display names are
    // resolved from those keys at render time — never stored in the state.
    $fromName = isset($state['from_warehouse_id']) ? \App\Models\Warehouse::find($state['from_warehouse_id'])?->name : null;
    $toName = isset($state['to_warehouse_id']) ? \App\Models\Warehouse::find($state['to_warehouse_id'])?->name : null;
@endphp
<div class="space-y-4">
    <h3 class="text-base font-semibold">{{ __('wizards.direct_transfer_review.title') }}</h3>
    <dl class="grid grid-cols-2 gap-3 text-sm">
        <div>
            <dt class="text-zinc-500">{{ __('wizards.direct_transfer_review.from') }}</dt>
            <dd class="font-medium">{{ $fromName ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-zinc-500">{{ __('wizards.direct_transfer_review.to') }}</dt>
            <dd class="font-medium">{{ $toName ?? '—' }}</dd>
        </div>
    </dl>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-zinc-500">
                <th>{{ __('wizards.direct_transfer_review.sku') }}</th>
                <th>{{ __('wizards.direct_transfer_review.qty') }}</th>
                <th>{{ __('wizards.direct_transfer_review.unit') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($state['items'] ?? []) as $item)
                @php($sku = isset($item['product_variant_id']) ? \App\Models\ProductVariant::find($item['product_variant_id'])?->sku : null)
                <tr class="border-t border-zinc-200">
                    <td class="font-mono">{{ $sku ?? '—' }}</td>
                    <td>{{ $item['qty'] ?? 0 }}</td>
                    <td>{{ $item['unit_name'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @if (! empty($state['notes']))
        <p class="text-sm text-zinc-500">{{ $state['notes'] }}</p>
    @endif
</div>
```

The sales current-price preview may remain a `Placeholder` because it is a derived display value, not a wizard review component.

### 18.4 Idempotency Model

Create:

```text
app/Models/StockMovementIdempotencyKey.php
```

Required behavior:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovementIdempotencyKey extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'transfer_requisition_id',
        'payload_checksum',
        'resulting_item_states',
        'created_at',
    ];

    protected $casts = [
        'resulting_item_states' => 'array',
        'created_at' => 'datetime',
    ];

    public function transferRequisition(): BelongsTo
    {
        return $this->belongsTo(TransferRequisition::class);
    }
}
```

The database unique constraint on `(transfer_requisition_id, payload_checksum)` remains mandatory.

Migration (runs in Phase 01 order, after `transfer_requisitions`):

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movement_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_requisition_id')
                ->constrained('transfer_requisitions')
                ->cascadeOnDelete();
            $table->string('payload_checksum', 64);
            $table->json('resulting_item_states');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['transfer_requisition_id', 'payload_checksum'],
                'idempotency_requisition_checksum_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movement_idempotency_keys');
    }
};
```

### 18.5 Badge Scope Trait

Create:

```text
app/Filament/Support/Concerns/ScopesNavigationBadges.php
```

Full implementation (canonical copy of §1B.3 — restated here like §18.3 and §18.4 restate theirs, so this section is a complete implementation surface). Every resource listed in §18.1 that declares a badge must consume this trait.

```php
<?php

namespace App\Filament\Support\Concerns;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;

/**
 * Centralized navigation badge scoping.
 *
 * Badge scope is determined by the acting user's role and warehouse
 * assignments — never by the warehouse endpoints of a specific document.
 * See Principle A12.
 */
trait ScopesNavigationBadges
{
    /** @var array<int>|null */
    private static ?array $badgeWarehouseIds = null;

    /**
     * Cached badge count shared by `getNavigationBadge()` and
     * `getNavigationBadgeColor()`. Trait-owned, so each using resource
     * gets its own copy (trait semantics) while `flushBadgeScope()`
     * resets both caches together — a resource-level copy would survive
     * logout in long-lived workers.
     *
     * @var int|null
     */
    private static ?int $badgeCount = null;

    /**
     * Resolve the warehouse ID set used to scope every navigation badge
     * on this resource.
     *
     * - Admin / Auditor: all warehouses.
     * - WarehouseStaff with N >= 2: union of assigned warehouses.
     * - WarehouseStaff with N == 1: exactly the single assigned warehouse.
     * - WarehouseStaff with N == 0: empty array (badge resolves to null).
     *
     * @return array<int>
     */
    protected static function badgeScopedWarehouseIds(): array
    {
        if (self::$badgeWarehouseIds !== null) {
            return self::$badgeWarehouseIds;
        }

        $user = Auth::user();

        if (! $user instanceof User) {
            return self::$badgeWarehouseIds = [];
        }

        if ($user->isAdmin() || $user->isAuditor()) {
            return self::$badgeWarehouseIds = Warehouse::query()
                ->pluck('id')
                ->all();
        }

        return self::$badgeWarehouseIds = $user->warehouses()
            ->pluck('warehouses.id')
            ->all();
    }

    /**
     * Whether the current user has any badge scope at all.
     * Used by resources that must suppress their badge entirely when the
     * user's role resolves to zero warehouses.
     */
    protected static function hasBadgeScope(): bool
    {
        return count(self::badgeScopedWarehouseIds()) > 0;
    }

    /**
     * Badge scope for the current request is cached once and shared across
     * `getNavigationBadge()`, `getNavigationBadgeColor()`, and
     * `getNavigationBadgeTooltip()` calls.
     *
     * Public so `AppServiceProvider` can flush every badge-bearing resource
     * on `Auth::logout` (§17.2) — required in long-lived workers (Octane,
     * queue workers that boot Filament). Each resource carries its own copy
     * of the static caches (trait semantics), so all four must be flushed.
     *
     * Resets BOTH the warehouse-ID scope and the cached count: resetting
     * only the scope leaves the previous user's count visible to the next
     * user in a long-lived worker.
     */
    public static function flushBadgeScope(): void
    {
        self::$badgeWarehouseIds = null;
        self::$badgeCount = null;
    }
}
```

**Note on cache flushing:** In standard PHP-FPM request lifecycles the static caches die with the request and no action is needed. In long-lived workers (Octane, queue workers that boot Filament), `AppServiceProvider::boot()` listens for both `Illuminate\Auth\Events\Logout` and `Illuminate\Auth\Events\Login` and calls `flushBadgeScope()` on all four badge-bearing resources (§17.2) — the canonical call sites. The `Login` listener closes the re-auth-without-logout case (session expiry followed by direct `Auth::login()`, programmatic auth, Filament re-auth). Each call resets both the warehouse-ID scope and the cached count, so the next user never sees the previous user's badge.

---

## 🔐 Section 19: Transactional & Data-Integrity Corrections

### 19.1 Transfer Confirmation Must Be Atomic

Do not perform:

```php
materializeRequestedAsApproved($record);
$record->update([...]);
```

as two independent operations from a Filament action.

Required service boundary:

```php
public function confirm(TransferRequisition $requisition): void
{
    DB::transaction(function () use ($requisition) {
        $fresh = TransferRequisition::query()
            ->lockForUpdate()
            ->findOrFail($requisition->id);

        if (! in_array($fresh->status, [
            TransferRequisitionStatus::Requested,
            TransferRequisitionStatus::UnderReviewFulfiller,
            TransferRequisitionStatus::UnderReviewRequestor,
        ], true)) {
            throw new \App\Exceptions\InvalidDocumentStateException(TransferRequisition::class, (int) $fresh->id, $fresh->status->value, 'confirm');
        }

        $items = TransferRequisitionItem::query()
            ->where('transfer_requisition_id', $fresh->id)
            ->lockForUpdate()
            ->get();

        // Bind the locked collection so materialization cannot lazy-load
        // unlocked copies.
        $fresh->setRelation('items', $items);

        $this->negotiation->materializeRequestedAsApproved($fresh);

        $fresh->update([
            'status' => TransferRequisitionStatus::Confirmed,
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        event(new TransferConfirmed($fresh->id));
    });
}
```

After-commit delivery is guaranteed by `ShouldDispatchAfterCommit` on the event (§22.1a/§22.2): the `event()` call stays lexically inside the transaction and Laravel releases it only after commit. Dispatching outside the transaction would lose that guarantee, so §6.6 follows this same inside-transaction shape.

### 19.2 Transfer Cancellation Service

Required:

```php
public function cancelRequisition(TransferRequisition $requisition): void
{
    DB::transaction(function () use ($requisition) {
        $fresh = TransferRequisition::query()
            ->lockForUpdate()
            ->findOrFail($requisition->id);

        if (! $fresh->canBeCancelled()) {
            throw new \App\Exceptions\InvalidDocumentStateException(TransferRequisition::class, (int) $fresh->id, $fresh->status->value, 'cancel');
        }

        $fresh->update([
            'status' => TransferRequisitionStatus::Cancelled,
            // add cancelled_at/cancelled_by only if the schema is expanded;
            // do not silently invent columns in an implementation.
        ]);

        event(new TransferCancelled($fresh->id));
    });
}
```

### 19.3 Transfer Dispatch State Guard

At the beginning of `InventoryService::dispatchTransfer()`:

```php
$fresh = TransferRequisition::query()
    ->lockForUpdate()
    ->findOrFail($requisition->id);

if ($fresh->status !== TransferRequisitionStatus::Confirmed) {
    throw new \App\Exceptions\InvalidDocumentStateException(TransferRequisition::class, (int) $fresh->id, $fresh->status->value, 'dispatch');
}
```

All subsequent reads must use `$fresh`, not the stale injected model.

### 19.4 Purchase Lifecycle Locks

`orderPurchase()` and `cancelPurchaseOrder()` must both:

1. open a transaction;
2. re-read the purchase order with `lockForUpdate()`;
3. validate current status;
4. update status/timestamps;
5. dispatch post-commit event.

### 19.5 Sales Confirmation Price Snapshot Lock

For every unique variant in a sales order:

```php
ProductVariant::query()
    ->whereIn('id', $variantIds)
    ->orderBy('id')
    ->lockForUpdate()
    ->with('currentPrice')
    ->get()
    ->keyBy('id');
```

The snapshot must come from the locked variant's current price. Never read an unlocked current price and then write a snapshot as if it were atomic.

### 19.6 Scan Payload Canonicalization

Before hashing:

1. validate every item ID belongs to the requisition;
2. reject unknown item IDs;
3. reject negative values;
4. reject non-integer values after normalization;
5. require `received_good + received_damaged <= outstanding`;
6. sort item IDs numerically;
7. sort each payload object's keys;
8. encode canonical JSON;
9. hash canonical JSON with SHA-256.

Equivalent payloads must produce the same checksum.

### 19.7 Scan Lock Ordering

`scanToReceive()` must lock in deterministic order:

1. transfer requisition;
2. transfer items by ID;
3. actual product variants by ID;
4. destination warehouse.

This must happen before creating `TransferIn` movements or loss records.

### 19.8 Scan Duplicate Race

The unique database constraint remains the final authority.

If two requests race:

- exactly one may create the idempotency key;
- the losing request must re-read the key and return the stored result rather than surface a generic database error;
- the transaction that loses the uniqueness race must not leave any stock movement or loss ledger side effects.

### 19.9 Substitute Variant Cost Snapshot

`writeOffOmittedItem()` must resolve:

```php
$actualVariant = ProductVariant::query()
    ->lockForUpdate()
    ->findOrFail($item->actualVariantId());

$unitCost = LossLedger::snapshotUnitCostFrom($actualVariant);
```

Never snapshot cost from `$item->productVariant` when the shipped variant may be a negotiated substitute.

### 19.10 Damaged Receipt Accounting Boundary

The current blueprint defines `received_damaged_base_qty` but does not define a separate stock movement for damaged quantity.

**Council decision:** do not invent a financial/accounting treatment.

Technical requirements are therefore limited to:

- damaged quantity is validated against outstanding quantity;
- damaged quantity does not increase good-stock `TransferIn`;
- the existing `received_damaged_base_qty` counter is updated atomically;
- a future accounting decision may determine whether damaged intake creates a `LossLedger`, a separate movement type, or another disposition workflow.

This remains an explicit product/accounting decision rather than an inferred fix.

### 19.11 Badge Scope Resolver Integrity

The `ScopesNavigationBadges::badgeScopedWarehouseIds()` resolver must be:

1. **Deterministic** — same input state yields same output within a request.
2. **Role-aware** — Admin/Auditor always resolve to all warehouses, independent of pivot contents.
3. **Cardinality-aware** — WarehouseStaff with N=1 returns a single-element array, never the union of counterpart warehouses.
4. **Empty-safe** — WarehouseStaff with N=0 returns `[]` and the badge short-circuits to `null`.
5. **Request-scoped cached** — the resolver memoizes within the request and is flushed on logout or auth change in long-lived workers.
6. **The only sanctioned resolver** — no resource may compute badge warehouse IDs ad hoc.

---

## 🧭 Section 20: Warehouse Data Scoping & Authorization Isolation

### 20.1 Collection Scope Rule

A policy's `viewAny()` permission does not make a query safe.

For warehouse staff, operational lists must be filtered at the database query level before pagination:

- Transfer Requisitions: `from_warehouse_id` OR `to_warehouse_id` in assigned warehouses.
- In-Transit: source/destination warehouse scope through the related requisition.
- Purchase Orders: `warehouse_id` in assigned warehouses.
- Sales Orders: `warehouse_id` in assigned warehouses.
- Stock Movements: `warehouse_id` in assigned warehouses.
- Loss Ledgers: `warehouse_id` in assigned warehouses.
- Direct Transfers: **both** `from_warehouse_id` AND `to_warehouse_id` must be within the user's allowed operational scope.
- Warehouses: warehouse staff may only see assigned warehouses.
- Products, Suppliers, Customers: global master-data visibility remains governed by policy; no warehouse filter is required unless the policy is changed.

Admin/Auditor visibility follows their policy definitions.

### 20.2 Scope Must Be Applied Before User-Controlled Filters

The order is:

```text
base query
→ authorization/data scope
→ eager loading
→ user filters
→ sorting
→ pagination
```

Never apply warehouse scoping after pagination.

### 20.3 Direct Transfer Authorization

Authorization lives on **`DirectTransferPolicy`** — not on `StockMovementPolicy`:

```php
DirectTransferPolicy::create(User $user): bool
{
    // Canonical definition lives in §8.13 — auditors are read-only and
    // never create operational documents, even when assigned to warehouses.
    return $user->isAdmin() || (! $user->isAuditor() && $user->warehouses()->count() >= 2);
}
```

The Create page must explicitly authorize via the policy before accepting wizard submission.

The **service** independently re-verifies that **both** `from_warehouse_id` and `to_warehouse_id` are within the actor's assigned warehouses inside the transaction. Admins hold global scope and skip the membership check (same privilege boundary as §7C.1 and §7C.6); Auditors are read-only and are denied by the service even when assigned to both warehouses (mirroring `DirectTransferPolicy::create()`). UI visibility is never the security boundary.

`StockMovementPolicy::createDirectTransfer()` is removed.

### 20.4 Badge Scope vs. Query Scope

Badge scope (Section 1B) and query scope (this section) are related but distinct:

- **Query scope** restricts which records the user may fetch and page through.
- **Badge scope** restricts which records contribute to the navigation badge count.

They must be consistent: a user's badge count must never exceed the number of records they can list. Both must resolve through the same warehouse ID set — the badge scope resolver and the query scope resolver must agree for the same user. `DirectTransfer` declares no navigation badge (§1B.2), so this rule applies per badge-bearing resource.

---

## 🌐 Section 21: STN Printable & Signed QR Runtime Contract

### 21.1 Routes

Full `routes/web.php` wiring (the STN endpoints are the only custom web routes; everything else is served by the Filament panel):

```php
use App\Http\Controllers\StnController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')
    ->get('/stn/{transferRequisition}/print', [StnController::class, 'print'])
    ->name('stn.print');

Route::middleware(['auth', 'signed'])
    ->get('/stn/{transferRequisition}/scan', [StnController::class, 'scan'])
    ->name('stn.scan');
```

The `scan` endpoint must authorize `receive`.

`resources/views/stn/print.blade.php` — printable transfer note (print view strings resolve through `__()`, §0A.11/§0A.12):

```blade
{{-- resources/views/stn/print.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('stn.print.title', ['ref' => $requisition->reference_code]) }}</title>
    <style>
        body { font-family: sans-serif; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 6px 8px; text-align: left; font-size: 13px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <h1>{{ __('stn.print.heading') }} — <span style="font-family: monospace;">{{ $requisition->reference_code }}</span></h1>
    <p>{{ __('stn.print.route', ['from' => $requisition->fromWarehouse->name, 'to' => $requisition->toWarehouse->name]) }}</p>
    <p>{{ __('stn.print.status', ['status' => $requisition->status->getLabel()]) }}</p>
    <div>
        {{-- Scannable QR (Phase 00.4 `simplesoftwareio/simple-qrcode`): the payload is the 7-day temporary signed scan URL itself (§21.2, no stored QR-payload column). --}}
        @php($signedScanUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute('stn.scan', now()->addDays(7), ['transferRequisition' => $requisition->getKey()]))
        {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(200)->generate($signedScanUrl) !!}
    </div>
    <table>
        <thead>
            <tr>
                <th>{{ __('stn.print.sku') }}</th>
                <th>{{ __('stn.print.variant') }}</th>
                <th>{{ __('stn.print.qty') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($requisition->items as $item)
                <tr>
                    <td style="font-family: monospace;">{{ $item->productVariant->sku }}</td>
                    <td>{{ $item->productVariant->name }}</td>
                    <td>{{ $item->approved_base_qty ?? $item->requested_base_qty }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <button class="no-print" onclick="window.print()">{{ __('stn.print.print_button') }}</button>
</body>
</html>
```

`resources/views/stn/scan.blade.php` — signed QR landing page; renders the scan UI shell and delegates all mutations to `InventoryService::scanToReceive()` via a Livewire component (no direct ledger writes from the view):

```blade
{{-- resources/views/stn/scan.blade.php --}}
<x-layouts.app :title="__('stn.scan.title', ['ref' => $requisition->reference_code])">
    <h1 class="text-lg font-semibold">
        {{ __('stn.scan.heading') }} — <span class="font-mono">{{ $requisition->reference_code }}</span>
    </h1>
    <p class="text-sm text-zinc-500">
        {{ __('stn.scan.route', ['from' => $requisition->fromWarehouse->name, 'to' => $requisition->toWarehouse->name]) }}
    </p>
    <livewire:stn.scan-form :requisition="$requisition->id" />
</x-layouts.app>
```

`resources/views/components/layouts/app.blade.php` — minimal print/scan shell layout backing the `<x-layouts.app>` component used above (locale handling mirrors `stn/print.blade.php`):

```blade
{{-- resources/views/components/layouts/app.blade.php --}}
@props(['title' => config('app.name')])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body class="bg-white text-zinc-900">
    <main class="mx-auto max-w-3xl space-y-4 p-6">
        {{ $slot }}
    </main>
</body>
</html>
```

`app/Livewire/Stn/ScanForm.php` — the `<livewire:stn.scan-form>` component referenced above. Mounts on the requisition id, renders one `received_good` / `received_damaged` row per requisition item, and delegates all mutations to `InventoryService::scanToReceive()` (never writes ledger records directly). The full `$lines` map is always submitted — omitting an item on the first scan is a write-off under `scanToReceive()` semantics (§6.2), so the form pre-fills every line with `0` rather than filtering empty rows. Client-side rules mirror the canonicalization contract (§19.6); the service re-validates canonically:

```php
namespace App\Livewire\Stn;

use App\Models\TransferRequisition;
use App\Services\InventoryService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ScanForm extends Component
{
    public int $requisitionId;

    /** @var array<int, array{received_good: int, received_damaged: int}> */
    public array $lines = [];

    public ?string $error = null;

    public function mount(int $requisition): void
    {
        $requisitionModel = TransferRequisition::findOrFail($requisition);

        $this->requisitionId = $requisitionModel->id;

        foreach ($requisitionModel->items()->orderBy('id')->pluck('id') as $itemId) {
            $this->lines[$itemId] = ['received_good' => 0, 'received_damaged' => 0];
        }
    }

    public function submit(InventoryService $inventory): void
    {
        $this->error = null;

        Gate::authorize('receive', TransferRequisition::findOrFail($this->requisitionId));

        $this->validate([
            'lines'                       => 'required|array|min:1',
            'lines.*.received_good'       => 'required|integer|min:0',
            'lines.*.received_damaged'    => 'required|integer|min:0',
        ]);

        try {
            $inventory->scanToReceive(
                TransferRequisition::findOrFail($this->requisitionId),
                collect($this->lines)
                    ->map(fn ($line) => [
                        'received_good'    => (int) $line['received_good'],
                        'received_damaged' => (int) $line['received_damaged'],
                    ])
                    ->all(),
            );
        } catch (\App\Exceptions\DomainErrorException $e) {
            $this->error = __($e->translationKey() . '.title', $e->context());
        } catch (\DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render(): View
    {
        return view('livewire.stn.scan-form', [
            'items' => TransferRequisition::findOrFail($this->requisitionId)
                ->items()->with('productVariant')->orderBy('id')->get(),
        ]);
    }
}
```

`resources/views/livewire/stn/scan-form.blade.php` — the component view (scan strings resolve through `__()`, §0A.11):

```blade
{{-- resources/views/livewire/stn/scan-form.blade.php --}}
<form wire:submit="submit" class="space-y-4">
    @if ($error)
        <p class="text-sm text-red-600">{{ $error }}</p>
    @endif
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-zinc-500">
                <th>{{ __('stn.scan.sku') }}</th>
                <th>{{ __('stn.scan.received_good') }}</th>
                <th>{{ __('stn.scan.received_damaged') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr class="border-t border-zinc-200">
                    <td class="font-mono">{{ $item->productVariant->sku }}</td>
                    <td><input type="number" min="0" step="1" wire:model="lines.{{ $item->id }}.received_good" class="w-24 border px-2 py-1"></td>
                    <td><input type="number" min="0" step="1" wire:model="lines.{{ $item->id }}.received_damaged" class="w-24 border px-2 py-1"></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <button type="submit" class="no-print bg-zinc-900 px-4 py-2 text-sm font-medium text-white">{{ __('stn.scan.submit') }}</button>
</form>
```

### 21.2 Signed URL Generation

The QR action must use:

```php
URL::temporarySignedRoute(
    'stn.scan',
    now()->addDays(7),
    ['transferRequisition' => $record->getKey()],
);
```

Do not use:

```php
route('stn.scan', ['transferRequisition' => $record->id])
```

for the QR destination.

**QR rendering (Phase 00.4 `simplesoftwareio/simple-qrcode`):** the print view (`resources/views/stn/print.blade.php`) converts the signed URL above into a scannable QR image — the QR step is part of the contract, not just the URL generation:

```blade
@php($signedScanUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute('stn.scan', now()->addDays(7), ['transferRequisition' => $requisition->getKey()]))
{!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(200)->generate($signedScanUrl) !!}
```

**QR payload:** there is no stored QR-payload column by design (owner decision); the QR payload is the temporary signed `stn.scan` URL itself (7-day expiry).

### 21.3 Controller Contract

```text
app/Http/Controllers/StnController.php
```

Methods:

```php
namespace App\Http\Controllers;

use App\Enums\TransferRequisitionStatus;
use App\Models\TransferRequisition;
use Illuminate\Support\Facades\Gate;

class StnController extends Controller
{
    public function print(TransferRequisition $transferRequisition)
    {
        Gate::authorize('view', $transferRequisition);

        // F28 eager-loading discipline: the `stn.print` view accesses
        // `fromWarehouse`, `toWarehouse`, and `items.productVariant`.
        $transferRequisition->loadMissing(['fromWarehouse', 'toWarehouse', 'items.productVariant']);

        return view('stn.print', ['requisition' => $transferRequisition]);
    }

    public function scan(TransferRequisition $transferRequisition)
    {
        Gate::authorize('receive', $transferRequisition);

        // F28 eager-loading discipline: the `stn.scan` view accesses
        // `fromWarehouse` and `toWarehouse`.
        $transferRequisition->loadMissing(['fromWarehouse', 'toWarehouse']);

        if (! in_array($transferRequisition->status, [
            TransferRequisitionStatus::Dispatched,
            TransferRequisitionStatus::PartiallyReceived,
        ], true)) {
            abort(403);
        }

        return view('stn.scan', ['requisition' => $transferRequisition]);
    }
}
```

`scan()`:

1. relies on `auth` + `signed` middleware;
2. calls `Gate::authorize('receive', $transferRequisition)`;
3. verifies the requisition is `Dispatched` or `PartiallyReceived`;
4. renders the scan UI;
5. delegates all mutations to `InventoryService::scanToReceive()`.

The controller must never create `StockMovement`, `LossLedger`, or `InTransit` records directly.

---

## 📣 Section 22: Post-Commit Event & Notification Layer

### 22.1 Required Domain Events

```text
InventoryBelowReorderPoint
TransferConfirmed
TransferCancelled
TransferDispatched
TransferReceived
LossRecorded
PurchaseOrderReceived
SalesOrderDispatched
```

`TransferConfirmed` is added because confirmation is now an explicit transactional service boundary. `TransferCancelled` is listed because `TransferRequisitionService::cancelRequisition()` dispatches it (§19.2).

### 22.1a Event Class Contract

Create under `app/Events/` — one file per event, matching the §25 file map 1:1. All events use after-commit dispatch semantics (§22.2) via `ShouldDispatchAfterCommit`. Payloads are minimal ids (owner-approved): document-header ids for lifecycle events, the created ledger id for loss events, and variant + warehouse ids for the reorder-point alert. Listeners re-query relations from these ids.

`app/Events/TransferConfirmed.php`:

```php
namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransferConfirmed implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly int $requisitionId) {}
}
```

`app/Events/TransferCancelled.php`:

```php
namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransferCancelled implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly int $requisitionId) {}
}
```

`app/Events/TransferDispatched.php`:

```php
namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransferDispatched implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly int $requisitionId) {}
}
```

`app/Events/TransferReceived.php`:

```php
namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransferReceived implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly int $requisitionId) {}
}
```

`app/Events/LossRecorded.php`:

```php
namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LossRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly int $lossLedgerId) {}
}
```

`app/Events/PurchaseOrderReceived.php`:

```php
namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderReceived implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly int $purchaseOrderId) {}
}
```

`app/Events/SalesOrderDispatched.php`:

```php
namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SalesOrderDispatched implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly int $salesOrderId) {}
}
```

`app/Events/InventoryBelowReorderPoint.php`:

```php
namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryBelowReorderPoint implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $productVariantId,
        public readonly int $warehouseId,
    ) {}
}
```

### 22.2 Event Timing

Events that originate inside a transaction must implement/use after-commit semantics.

Rule:

```text
transaction starts
→ validate
→ lock
→ mutate ledger/document state
→ commit
→ publish event
→ listener/notification
```

Never send a notification about a state that could still roll back.

### 22.3 Notification Boundary

Listeners may:

- create database notifications;
- queue external notifications;
- invalidate/cache refreshes;
- publish broadcast events.

Listeners may not bypass the domain services to mutate stock.

### 22.3a Listener Class Contract

Create under `app/Listeners/`. Each listener is queued (`ShouldQueue`) and performs only the allowed side effects above — never a stock mutation. One listener per domain event, following this canonical shape:

```php
namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\TransferConfirmed;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Notifications\TransferConfirmedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class NotifyTransferConfirmed implements ShouldQueue
{
    public function handle(TransferConfirmed $event): void
    {
        // v1 (owner-approved): database notification only, queued. Audience is
        // warehouse-scoped — admins plus staff assigned to the affected
        // warehouse(s). No mail/broadcast until operators request it.
        $requisition = TransferRequisition::find($event->requisitionId);

        if (! $requisition) {
            return;
        }

        $warehouseIds = [$requisition->from_warehouse_id, $requisition->to_warehouse_id];

        $users = User::query()
            ->where(function ($q) use ($warehouseIds) {
                $q->where('role', UserRole::Admin->value)
                    ->orWhereHas('warehouses', function ($query) use ($warehouseIds) {
                        $query->whereIn('warehouses.id', $warehouseIds);
                    });
            })
            ->get();

        Notification::send($users, new TransferConfirmedNotification(
            $requisition->id,
            $requisition->reference_code,
        ));
    }
}
```

```php
namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\TransferCancelled;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Notifications\TransferCancelledNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class NotifyTransferCancelled implements ShouldQueue
{
    public function handle(TransferCancelled $event): void
    {
        // v1 (owner-approved): database notification only, queued. Audience is
        // warehouse-scoped — admins plus staff assigned to the requisition's
        // source warehouse. No mail/broadcast until operators request it.
        $requisition = TransferRequisition::find($event->requisitionId);

        if (! $requisition) {
            return;
        }

        $users = User::query()
            ->where(function ($q) use ($requisition) {
                $q->where('role', UserRole::Admin->value)
                    ->orWhereHas('warehouses', function ($query) use ($requisition) {
                        $query->whereIn('warehouses.id', [$requisition->from_warehouse_id]);
                    });
            })
            ->get();

        Notification::send($users, new TransferCancelledNotification(
            $requisition->id,
            $requisition->reference_code,
        ));
    }
}
```

```php
namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\TransferDispatched;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Notifications\TransferDispatchedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class NotifyTransferDispatched implements ShouldQueue
{
    public function handle(TransferDispatched $event): void
    {
        // v1 (owner-approved): database notification only, queued. Audience is
        // warehouse-scoped — admins plus staff assigned to the requisition's
        // destination warehouse. No mail/broadcast until operators request it.
        $requisition = TransferRequisition::find($event->requisitionId);

        if (! $requisition) {
            return;
        }

        $users = User::query()
            ->where(function ($q) use ($requisition) {
                $q->where('role', UserRole::Admin->value)
                    ->orWhereHas('warehouses', function ($query) use ($requisition) {
                        $query->whereIn('warehouses.id', [$requisition->to_warehouse_id]);
                    });
            })
            ->get();

        Notification::send($users, new TransferDispatchedNotification(
            $requisition->id,
            $requisition->reference_code,
        ));
    }
}
```

```php
namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\TransferReceived;
use App\Models\TransferRequisition;
use App\Models\User;
use App\Notifications\TransferReceivedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class NotifyTransferReceived implements ShouldQueue
{
    public function handle(TransferReceived $event): void
    {
        // v1 (owner-approved): database notification only, queued. Audience is
        // warehouse-scoped — admins plus staff assigned to both endpoint
        // warehouses. No mail/broadcast until operators request it.
        $requisition = TransferRequisition::find($event->requisitionId);

        if (! $requisition) {
            return;
        }

        $warehouseIds = [$requisition->from_warehouse_id, $requisition->to_warehouse_id];

        $users = User::query()
            ->where(function ($q) use ($warehouseIds) {
                $q->where('role', UserRole::Admin->value)
                    ->orWhereHas('warehouses', function ($query) use ($warehouseIds) {
                        $query->whereIn('warehouses.id', $warehouseIds);
                    });
            })
            ->get();

        Notification::send($users, new TransferReceivedNotification(
            $requisition->id,
            $requisition->reference_code,
        ));
    }
}
```

```php
namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\LossRecorded;
use App\Models\LossLedger;
use App\Models\User;
use App\Notifications\LossRecordedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class NotifyLossRecorded implements ShouldQueue
{
    public function handle(LossRecorded $event): void
    {
        // v1 (owner-approved): database notification only, queued. Audience is
        // warehouse-scoped — admins, auditors, plus staff assigned to the
        // loss warehouse. No mail/broadcast until operators request it.
        $loss = LossLedger::find($event->lossLedgerId);

        if (! $loss) {
            return;
        }

        $users = User::query()
            ->where(function ($q) use ($loss) {
                $q->whereIn('role', [UserRole::Admin->value, UserRole::Auditor->value])
                    ->orWhereHas('warehouses', function ($query) use ($loss) {
                        $query->whereIn('warehouses.id', [$loss->warehouse_id]);
                    });
            })
            ->get();

        Notification::send($users, new LossRecordedNotification($loss->id));
    }
}
```

```php
namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\PurchaseOrderReceived;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Notifications\PurchaseOrderReceivedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class NotifyPurchaseOrderReceived implements ShouldQueue
{
    public function handle(PurchaseOrderReceived $event): void
    {
        // v1 (owner-approved): database notification only, queued. Audience is
        // warehouse-scoped — admins plus staff assigned to the order's
        // warehouse. No mail/broadcast until operators request it.
        $order = PurchaseOrder::find($event->purchaseOrderId);

        if (! $order) {
            return;
        }

        $users = User::query()
            ->where(function ($q) use ($order) {
                $q->where('role', UserRole::Admin->value)
                    ->orWhereHas('warehouses', function ($query) use ($order) {
                        $query->whereIn('warehouses.id', [$order->warehouse_id]);
                    });
            })
            ->get();

        Notification::send($users, new PurchaseOrderReceivedNotification(
            $order->id,
            $order->reference_code,
        ));
    }
}
```

```php
namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\SalesOrderDispatched;
use App\Models\SalesOrder;
use App\Models\User;
use App\Notifications\SalesOrderDispatchedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class NotifySalesOrderDispatched implements ShouldQueue
{
    public function handle(SalesOrderDispatched $event): void
    {
        // v1 (owner-approved): database notification only, queued. Audience is
        // warehouse-scoped — admins plus staff assigned to the order's
        // warehouse. No mail/broadcast until operators request it.
        $order = SalesOrder::find($event->salesOrderId);

        if (! $order) {
            return;
        }

        $users = User::query()
            ->where(function ($q) use ($order) {
                $q->where('role', UserRole::Admin->value)
                    ->orWhereHas('warehouses', function ($query) use ($order) {
                        $query->whereIn('warehouses.id', [$order->warehouse_id]);
                    });
            })
            ->get();

        Notification::send($users, new SalesOrderDispatchedNotification(
            $order->id,
            $order->reference_code,
        ));
    }
}
```

```php
namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\InventoryBelowReorderPoint;
use App\Models\User;
use App\Notifications\InventoryBelowReorderPointNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class NotifyInventoryBelowReorderPoint implements ShouldQueue
{
    public function handle(InventoryBelowReorderPoint $event): void
    {
        // v1 (owner-approved): database notification only, queued. Audience is
        // warehouse-scoped — admins plus staff assigned to the alert's
        // warehouse. No mail/broadcast until operators request it.
        $users = User::query()
            ->where(function ($q) use ($event) {
                $q->where('role', UserRole::Admin->value)
                    ->orWhereHas('warehouses', function ($query) use ($event) {
                        $query->whereIn('warehouses.id', [$event->warehouseId]);
                    });
            })
            ->get();

        Notification::send($users, new InventoryBelowReorderPointNotification(
            $event->productVariantId,
            $event->warehouseId,
        ));
    }
}
```

Wiring: the eight event→listener pairs are registered in `EventServiceProvider::$listen` (§17.4), which is the single canonical registration site — no ad hoc `Event::listen()` calls elsewhere.

### 22.3b Notification Class Contract

Create under `app/Notifications/` — one file per notification, matching the §25 file map 1:1. One concrete database notification per domain event — these are the classes the §22.3a listeners send. Constructors carry scalar ids (plus header reference codes resolved by the listener) so queued payloads stay serializable. Channel is database-only per the v1 owner approval in §22.3a; body strings resolve through `__()` (§0A.8):

`app/Notifications/TransferConfirmedNotification.php`:

```php
namespace App\Notifications;

use Illuminate\Notifications\Notification;

class TransferConfirmedNotification extends Notification
{
    public function __construct(
        public readonly int $requisitionId,
        public readonly ?string $referenceCode = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'requisition_id' => $this->requisitionId,
            'reference_code' => $this->referenceCode,
            'message'        => __('notifications.transfer_confirmed.body', ['reference' => $this->referenceCode ?? (string) $this->requisitionId]),
        ];
    }
}
```

`app/Notifications/TransferCancelledNotification.php`:

```php
namespace App\Notifications;

use Illuminate\Notifications\Notification;

class TransferCancelledNotification extends Notification
{
    public function __construct(
        public readonly int $requisitionId,
        public readonly ?string $referenceCode = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'requisition_id' => $this->requisitionId,
            'reference_code' => $this->referenceCode,
            'message'        => __('notifications.transfer_cancelled.body', ['reference' => $this->referenceCode ?? (string) $this->requisitionId]),
        ];
    }
}
```

`app/Notifications/TransferDispatchedNotification.php`:

```php
namespace App\Notifications;

use Illuminate\Notifications\Notification;

class TransferDispatchedNotification extends Notification
{
    public function __construct(
        public readonly int $requisitionId,
        public readonly ?string $referenceCode = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'requisition_id' => $this->requisitionId,
            'reference_code' => $this->referenceCode,
            'message'        => __('notifications.transfer_dispatched.body', ['reference' => $this->referenceCode ?? (string) $this->requisitionId]),
        ];
    }
}
```

`app/Notifications/TransferReceivedNotification.php`:

```php
namespace App\Notifications;

use Illuminate\Notifications\Notification;

class TransferReceivedNotification extends Notification
{
    public function __construct(
        public readonly int $requisitionId,
        public readonly ?string $referenceCode = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'requisition_id' => $this->requisitionId,
            'reference_code' => $this->referenceCode,
            'message'        => __('notifications.transfer_received.body', ['reference' => $this->referenceCode ?? (string) $this->requisitionId]),
        ];
    }
}
```

`app/Notifications/LossRecordedNotification.php`:

```php
namespace App\Notifications;

use App\Models\LossLedger;
use Illuminate\Notifications\Notification;

class LossRecordedNotification extends Notification
{
    public function __construct(public readonly int $lossLedgerId) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $loss = LossLedger::with('productVariant')->find($this->lossLedgerId);

        $qty = $loss ? $loss->lost_base_qty + $loss->damaged_base_qty : 0;
        $sku = $loss?->productVariant?->sku ?? (string) $this->lossLedgerId;

        return [
            'loss_ledger_id' => $this->lossLedgerId,
            'message'        => __('notifications.loss_recorded.body', ['qty' => $qty, 'sku' => $sku]),
        ];
    }
}
```

`app/Notifications/PurchaseOrderReceivedNotification.php`:

```php
namespace App\Notifications;

use Illuminate\Notifications\Notification;

class PurchaseOrderReceivedNotification extends Notification
{
    public function __construct(
        public readonly int $purchaseOrderId,
        public readonly ?string $referenceCode = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'purchase_order_id' => $this->purchaseOrderId,
            'reference_code'    => $this->referenceCode,
            'message'           => __('notifications.purchase_order_received.body', ['reference' => $this->referenceCode ?? (string) $this->purchaseOrderId]),
        ];
    }
}
```

`app/Notifications/SalesOrderDispatchedNotification.php`:

```php
namespace App\Notifications;

use Illuminate\Notifications\Notification;

class SalesOrderDispatchedNotification extends Notification
{
    public function __construct(
        public readonly int $salesOrderId,
        public readonly ?string $referenceCode = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'sales_order_id' => $this->salesOrderId,
            'reference_code' => $this->referenceCode,
            'message'        => __('notifications.sales_order_dispatched.body', ['reference' => $this->referenceCode ?? (string) $this->salesOrderId]),
        ];
    }
}
```

`app/Notifications/InventoryBelowReorderPointNotification.php`:

```php
namespace App\Notifications;

use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Notifications\Notification;

class InventoryBelowReorderPointNotification extends Notification
{
    public function __construct(
        public readonly int $productVariantId,
        public readonly int $warehouseId,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $sku = ProductVariant::find($this->productVariantId)?->sku ?? (string) $this->productVariantId;
        $warehouse = Warehouse::find($this->warehouseId)?->name ?? (string) $this->warehouseId;

        return [
            'product_variant_id' => $this->productVariantId,
            'warehouse_id'       => $this->warehouseId,
            'message'            => __('notifications.inventory_below_reorder_point.body', ['sku' => $sku, 'warehouse' => $warehouse]),
        ];
    }
}
```

Recipients resolve through `User::notify()` / `Notification::send()`, so `App\Models\User` (§3.18) uses the `Notifiable` trait — the standard Laravel notifiable contract, no custom recipient infrastructure.

### 22.4 Queue Boundary

Notifications and non-critical integrations must be queued. Stock ledger mutations remain synchronous and transactional.

---

## 🧪 Section 23: Runtime Completeness Tests

Add a dedicated Pest suite:

```text
tests/Feature/Architecture/RuntimeWiringTest.php
tests/Feature/Architecture/ResourceCompletenessTest.php
tests/Feature/Architecture/PolicyRegistrationTest.php
tests/Feature/Architecture/RouteWiringTest.php
tests/Feature/Architecture/WarehouseScopeTest.php
tests/Feature/Architecture/ServiceResolutionTest.php
tests/Feature/Architecture/BadgeScopeTest.php
```

### 23.1 Service Resolution

```php
it('resolves every domain service', function (string $service) {
    expect(app($service))->toBeInstanceOf($service);
})->with([
    GuardsOutstandingQuantity::class,
    InventoryService::class,
    NegotiationService::class,
    TransferRequisitionService::class,
    PurchaseService::class,
    SalesService::class,
]);
```

### 23.2 Resource Completeness

The test must assert that every Resource/Page class named in Section 18 can be autoloaded.

### 23.3 Policy Registration

For every model/policy pair, assert:

```php
expect(Gate::getPolicyFor($model))->toBe($policy);
```

### 23.4 Route Wiring

Assert:

```text
stn.print exists
stn.scan exists
stn.scan has signed middleware
stn.scan requires auth
```

### 23.5 Warehouse Scope

For every operational resource:

1. create two warehouses;
2. assign one to a warehouse user;
3. create records in both;
4. query as that user;
5. assert only permitted records are returned.

The test must verify query-level filtering, not merely action/button visibility.

### 23.6 Badge Scope

For each of the four badge-bearing resources:

1. create warehouses A, B, C;
2. create documents in each;
3. log in as Admin → assert badge counts all;
4. log in as Auditor → assert badge counts all;
5. log in as WarehouseStaff assigned {A} → assert badge counts only A;
6. log in as WarehouseStaff assigned {A, B} → assert badge counts A ∪ B;
7. log in as WarehouseStaff assigned {} → assert badge is `null`;
8. for Transfer Requisitions, create a document with `from_warehouse_id=A`, `to_warehouse_id=B`; assert WarehouseStaff assigned {A} sees the document in the badge count; assert the count is 1, not 2 — counterpart warehouse does not widen scope;
9. assert every badge-bearing resource uses the `ScopesNavigationBadges` trait;
10. assert no badge-bearing resource calls `auth()->user()->warehouses()` directly in `getNavigationBadge()`.

---

## 🧵 Section 24: Concurrency Test Matrix

The inventory engine is not considered production-ready until these cases are covered:

| Scenario | Expected invariant |
|---|---|
| Two users confirm same requisition | Exactly one valid transition |
| Two users dispatch same requisition | Exactly one dispatch |
| Two users receive same scan payload | One application only |
| Two users receive different payloads concurrently | Serialized item state; no over-receive |
| Two users receive same purchase item | Never exceed ordered quantity |
| Two users dispatch same sales item | Never exceed available stock |
| Two users return same sales item | Never exceed dispatched quantity |
| Purchase receive vs sale dispatch | Locking prevents stale stock deduction |
| Transfer dispatch vs sale dispatch | Availability is serialized by variant/warehouse locks |
| Concurrent current-price update | At most one current price row |
| Delete warehouse during PO/SO/TR/DT mutation | Referential/policy integrity preserved |
| Badge scope resolution under concurrent requests | Each request resolves its own scope; no cross-request cache bleed |

---

## 📦 Section 25: Required File Map — Runtime, Not Documentation

The following paths are part of the blueprint's implementation contract:

```text
app/
├── Console/
├── Enums/
│   ├── TransferRequisitionStatus.php
│   ├── PurchaseOrderStatus.php
│   ├── SalesOrderStatus.php
│   ├── StockMovementType.php
│   ├── RevisionStatus.php
│   ├── NegotiationSide.php
│   ├── InTransitStatus.php
│   ├── LossCategory.php
│   └── UserRole.php
├── Exceptions/
│   ├── DomainErrorException.php
│   ├── InsufficientStockException.php
│   ├── OutstandingQuantityExceededException.php
│   ├── InvalidDocumentStateException.php
│   ├── InvalidRevisionTransitionException.php
│   ├── ProductFamilyHasVariantsException.php
│   ├── DomainRuleViolationException.php
│   └── NegotiationNotAllowedException.php
├── Filament/
│   ├── Resources/
│   │   ├── Products/
│   │   │   ├── ProductResource.php
│   │   │   ├── Pages/
│   │   │   │   ├── ListProducts.php
│   │   │   │   ├── CreateProduct.php
│   │   │   │   ├── EditProduct.php
│   │   │   │   └── ViewProduct.php
│   │   │   ├── Schemas/
│   │   │   │   ├── ProductForm.php
│   │   │   │   └── ProductInfolist.php
│   │   │   ├── Tables/
│   │   │   │   └── ProductsTable.php
│   │   │   └── Actions/
│   │   │       ├── SetCurrentPriceAction.php
│   │   │       ├── EditProductFamilyAction.php
│   │   │       ├── ManageUnitConversionsAction.php
│   │   │       └── QuickStockAdjustmentAction.php
│   │   ├── TransferRequisitions/
│   │   │   ├── TransferRequisitionResource.php
│   │   │   ├── Pages/
│   │   │   │   ├── ListTransferRequisitions.php
│   │   │   │   ├── CreateTransferRequisition.php
│   │   │   │   ├── EditTransferRequisition.php
│   │   │   │   └── ViewTransferRequisition.php
│   │   │   ├── Schemas/
│   │   │   │   ├── TransferRequisitionForm.php
│   │   │   │   └── TransferRequisitionInfolist.php
│   │   │   └── Tables/
│   │   │       └── TransferRequisitionsTable.php
│   │   ├── DirectTransfers/
│   │   │   ├── DirectTransferResource.php
│   │   │   ├── Pages/
│   │   │   │   ├── ListDirectTransfers.php
│   │   │   │   ├── CreateDirectTransfer.php
│   │   │   │   └── ViewDirectTransfer.php
│   │   │   ├── Schemas/
│   │   │   │   ├── DirectTransferForm.php
│   │   │   │   └── DirectTransferInfolist.php
│   │   │   └── Tables/
│   │   │       └── DirectTransfersTable.php
│   │   ├── InTransits/
│   │   │   ├── InTransitResource.php
│   │   │   ├── Pages/
│   │   │   │   ├── ListInTransits.php
│   │   │   │   └── ViewInTransit.php
│   │   │   ├── Schemas/
│   │   │   │   └── InTransitInfolist.php
│   │   │   └── Tables/
│   │   │       └── InTransitsTable.php
│   │   ├── StockMovements/
│   │   │   ├── StockMovementResource.php
│   │   │   ├── Pages/
│   │   │   │   ├── ListStockMovements.php
│   │   │   │   └── ViewStockMovement.php
│   │   │   ├── Schemas/
│   │   │   │   └── StockMovementInfolist.php
│   │   │   └── Tables/
│   │   │       └── StockMovementsTable.php
│   │   ├── LossLedgers/
│   │   │   ├── LossLedgerResource.php
│   │   │   ├── Pages/
│   │   │   │   ├── ListLossLedgers.php
│   │   │   │   └── ViewLossLedger.php
│   │   │   ├── Schemas/
│   │   │   │   └── LossLedgerInfolist.php
│   │   │   └── Tables/
│   │   │       └── LossLedgersTable.php
│   │   ├── Warehouses/
│   │   │   ├── WarehouseResource.php
│   │   │   ├── Pages/
│   │   │   │   ├── ListWarehouses.php
│   │   │   │   ├── CreateWarehouse.php
│   │   │   │   ├── EditWarehouse.php
│   │   │   │   └── ViewWarehouse.php
│   │   │   ├── Schemas/
│   │   │   │   ├── WarehouseForm.php
│   │   │   │   └── WarehouseInfolist.php
│   │   │   └── Tables/
│   │   │       └── WarehousesTable.php
│   │   ├── Users/
│   │   │   ├── UserResource.php
│   │   │   ├── Pages/
│   │   │   │   ├── ListUsers.php
│   │   │   │   ├── CreateUser.php
│   │   │   │   └── EditUser.php
│   │   │   ├── Schemas/
│   │   │   │   └── UserForm.php
│   │   │   └── Tables/
│   │   │       └── UsersTable.php
│   │   ├── PurchaseOrders/
│   │   │   ├── PurchaseOrderResource.php
│   │   │   ├── Pages/
│   │   │   │   ├── ListPurchaseOrders.php
│   │   │   │   ├── CreatePurchaseOrder.php
│   │   │   │   ├── EditPurchaseOrder.php
│   │   │   │   └── ViewPurchaseOrder.php
│   │   │   ├── Schemas/
│   │   │   │   ├── PurchaseOrderForm.php
│   │   │   │   └── PurchaseOrderInfolist.php
│   │   │   └── Tables/
│   │   │       └── PurchaseOrdersTable.php
│   │   ├── SalesOrders/
│   │   │   ├── SalesOrderResource.php
│   │   │   ├── Pages/
│   │   │   │   ├── ListSalesOrders.php
│   │   │   │   ├── CreateSalesOrder.php
│   │   │   │   ├── EditSalesOrder.php
│   │   │   │   └── ViewSalesOrder.php
│   │   │   ├── Schemas/
│   │   │   │   ├── SalesOrderForm.php
│   │   │   │   └── SalesOrderInfolist.php
│   │   │   └── Tables/
│   │   │       └── SalesOrdersTable.php
│   │   ├── Suppliers/
│   │   │   ├── SupplierResource.php
│   │   │   ├── Pages/
│   │   │   │   ├── ListSuppliers.php
│   │   │   │   ├── CreateSupplier.php
│   │   │   │   └── EditSupplier.php
│   │   │   ├── Schemas/
│   │   │   │   └── SupplierForm.php
│   │   │   └── Tables/
│   │   │       └── SuppliersTable.php
│   │   └── Customers/
│   │       ├── CustomerResource.php
│   │       ├── Pages/
│   │       │   ├── ListCustomers.php
│   │       │   ├── CreateCustomer.php
│   │       │   └── EditCustomer.php
│   │       ├── Schemas/
│   │       │   └── CustomerForm.php
│   │       └── Tables/
│   │           └── CustomersTable.php
│   ├── Support/
│   │   ├── Concerns/
│   │   │   └── ScopesNavigationBadges.php   # namespace App\Filament\Support\Concerns (§1B.3)
│   │   └── Filters/
│   │       └── AdminReviewFilters.php   # namespace App\Filament\Support\Filters (§9)
│   ├── Widgets/
│   │   ├── StatsOverviewWidget.php
│   │   ├── LowStockAlertsWidget.php
│   │   ├── RecentMovementsWidget.php
│   │   ├── SalesRevenueTrendWidget.php
│   │   ├── ActiveInTransitWidget.php
│   │   ├── SalesVsPurchasesWidget.php
│   │   ├── TopSellingVariantsWidget.php
│   │   ├── PendingFulfillmentWidget.php
│   │   └── QuickActionsWidget.php
├── Http/
│   └── Controllers/
│       └── StnController.php
├── Livewire/
│   └── Stn/
│       └── ScanForm.php
├── Models/
│   ├── Product.php
│   ├── ProductVariant.php
│   ├── ProductVariantPrice.php
│   ├── ProductVariantUnitConversion.php
│   ├── Warehouse.php
│   ├── StockMovement.php
│   ├── TransferRequisition.php
│   ├── TransferRequisitionItem.php
│   ├── TransferRequisitionItemRevision.php
│   ├── InTransit.php
│   ├── LossLedger.php
│   ├── Supplier.php
│   ├── Customer.php
│   ├── PurchaseOrder.php
│   ├── PurchaseOrderItem.php
│   ├── SalesOrder.php
│   ├── SalesOrderItem.php
│   ├── User.php
│   ├── DirectTransfer.php
│   ├── DirectTransferItem.php
│   └── StockMovementIdempotencyKey.php
├── Notifications/
│   ├── TransferConfirmedNotification.php
│   ├── TransferCancelledNotification.php
│   ├── TransferDispatchedNotification.php
│   ├── TransferReceivedNotification.php
│   ├── LossRecordedNotification.php
│   ├── PurchaseOrderReceivedNotification.php
│   ├── SalesOrderDispatchedNotification.php
│   └── InventoryBelowReorderPointNotification.php
├── Observers/
│   ├── ProductObserver.php
│   └── ProductVariantObserver.php
├── Policies/
│   ├── ProductPolicy.php
│   ├── ProductVariantPolicy.php
│   ├── TransferRequisitionPolicy.php
│   ├── InTransitPolicy.php
│   ├── StockMovementPolicy.php       # createDirectTransfer() removed
│   ├── LossLedgerPolicy.php
│   ├── PurchaseOrderPolicy.php
│   ├── SalesOrderPolicy.php
│   ├── SupplierPolicy.php
│   ├── CustomerPolicy.php
│   ├── WarehousePolicy.php
│   ├── UserPolicy.php
│   └── DirectTransferPolicy.php
├── Providers/
│   ├── AppServiceProvider.php
│   ├── AuthServiceProvider.php
│   ├── EventServiceProvider.php
│   ├── InventoryServiceProvider.php
│   └── Filament/
│       └── AdminPanelProvider.php
├── Services/
│   ├── GuardsOutstandingQuantity.php
│   ├── InventoryService.php          # directTransfer() accepts array $items
│   ├── NegotiationService.php
│   ├── TransferRequisitionService.php
│   ├── PurchaseService.php
│   └── SalesService.php
├── Support/
│   └── GeneratesReferenceCodes.php
├── Events/
│   ├── TransferConfirmed.php
│   ├── TransferCancelled.php
│   ├── TransferDispatched.php
│   ├── TransferReceived.php
│   ├── LossRecorded.php
│   ├── PurchaseOrderReceived.php
│   ├── SalesOrderDispatched.php
│   └── InventoryBelowReorderPoint.php
├── Listeners/
│   ├── NotifyTransferConfirmed.php
│   ├── NotifyTransferCancelled.php
│   ├── NotifyTransferDispatched.php
│   ├── NotifyTransferReceived.php
│   ├── NotifyLossRecorded.php
│   ├── NotifyPurchaseOrderReceived.php
│   ├── NotifySalesOrderDispatched.php
│   └── NotifyInventoryBelowReorderPoint.php
├── Helpers.php   # global format_money(mixed $state, int $precision = 4): string — decimal(15,4) display contract (§0A.14)

database/
├── factories/
│   ├── ProductFactory.php
│   ├── ProductVariantFactory.php
│   ├── ProductVariantPriceFactory.php
│   ├── ProductVariantUnitConversionFactory.php
│   ├── WarehouseFactory.php
│   ├── UserFactory.php
│   ├── SupplierFactory.php
│   ├── CustomerFactory.php
│   ├── TransferRequisitionFactory.php
│   ├── TransferRequisitionItemFactory.php
│   ├── TransferRequisitionItemRevisionFactory.php
│   ├── InTransitFactory.php
│   ├── StockMovementFactory.php
│   ├── StockMovementIdempotencyKeyFactory.php
│   ├── LossLedgerFactory.php
│   ├── PurchaseOrderFactory.php
│   ├── PurchaseOrderItemFactory.php
│   ├── SalesOrderFactory.php
│   ├── SalesOrderItemFactory.php
 │   ├── DirectTransferFactory.php
 │   └── DirectTransferItemFactory.php
  ├── seeders/   # Phase 02 base seeder, inlined in §2 dependency order
  │   └── DatabaseSeeder.php
 └── migrations/   # one migration per §2 table, in dependency order (Phase 01)
    ├── xxxx_xx_xx_create_products_table.php
    ├── xxxx_xx_xx_create_product_variants_table.php
    ├── xxxx_xx_xx_create_product_variant_prices_table.php
    ├── xxxx_xx_xx_create_product_variant_unit_conversions_table.php
    ├── xxxx_xx_xx_create_warehouses_table.php
    ├── xxxx_xx_xx_create_stock_movements_table.php
    ├── xxxx_xx_xx_create_transfer_requisitions_table.php
    ├── xxxx_xx_xx_create_transfer_requisition_items_table.php
    ├── xxxx_xx_xx_create_transfer_requisition_item_revisions_table.php
    ├── xxxx_xx_xx_create_in_transits_table.php
    ├── xxxx_xx_xx_create_loss_ledgers_table.php
    ├── xxxx_xx_xx_add_role_columns_to_users_table.php
    ├── xxxx_xx_xx_create_user_warehouse_table.php
    ├── xxxx_xx_xx_create_suppliers_table.php
    ├── xxxx_xx_xx_create_customers_table.php
    ├── xxxx_xx_xx_create_purchase_orders_table.php
    ├── xxxx_xx_xx_create_purchase_order_items_table.php
    ├── xxxx_xx_xx_create_sales_orders_table.php
    ├── xxxx_xx_xx_create_sales_order_items_table.php
    ├── xxxx_xx_xx_create_stock_movement_idempotency_keys_table.php
    ├── xxxx_xx_xx_create_direct_transfers_table.php
    └── xxxx_xx_xx_create_direct_transfer_items_table.php

resources/views/filament/wizards/
├── transfer-review.blade.php
├── purchase-order-review.blade.php
├── sales-order-review.blade.php
└── direct-transfer-review.blade.php  # iterates items

resources/views/filament/widgets/
└── quick-actions.blade.php  # QuickActionsWidget view (§10)

resources/views/components/layouts/
└── app.blade.php

resources/views/livewire/stn/
└── scan-form.blade.php

resources/views/stn/
├── print.blade.php
└── scan.blade.php

resources/css/filament/admin/
└── theme.css  # admin panel theme loaded via ->viteTheme() (§17.5)

lang/   # required locale tree per §0A.2 — every locale carries the same key set
├── en/
│   ├── actions.php
│   ├── attributes.php
│   ├── enums.php
│   ├── forms.php
│   ├── navigation.php
│   ├── notifications.php
│   ├── resources.php
│   ├── tables.php
│   ├── validation.php
│   ├── widgets.php
│   ├── errors.php
│   ├── common.php
│   ├── wizards.php       # __('wizards.*') — §18.3 review views
│   ├── stn.php           # __('stn.*') — §21 print/scan views
│   └── dashboard.php     # __('dashboard.*') — §10 widgets
└── <locale>/
    └── same file set as en/

routes/
└── web.php

bootstrap/
└── providers.php

tests/
├── Feature/
│   └── Architecture/
├── Unit/
└── Browser/
```

A file appearing in this map but missing from the repository is an implementation failure, not an optional follow-up.

---

## 🧾 Section 26: Council Acceptance Criteria

All criteria below are binding. The following consolidated list supersedes any earlier checklist fragment.

- [ ] Every Resource listed in Section 18 physically exists.
- [ ] Every Page referenced by `getPages()` physically exists.
- [ ] Every registered Widget physically exists.
- [ ] `TransferRequisitionService` exists and owns confirm/cancel lifecycle mutations.
- [ ] `StockMovementIdempotencyKey` exists and is registered with the correct casts.
- [ ] `direct_transfers` and `direct_transfer_items` tables exist with correct FK actions and indexes.
- [ ] `DirectTransfer` and `DirectTransferItem` models exist with correct casts and relations.
- [ ] `Warehouse` exposes `directTransfersFrom()` and `directTransfersTo()`.
- [ ] `InventoryService::directTransfer()` accepts an items array and writes one paired `TransferOut`/`TransferIn` movement per line.
- [ ] `directTransfer()` rejects same from/to warehouse, empty items, missing required fields, unit_ratio < 1, qty < 1, unknown variant IDs, and warehouses outside actor scope.
- [ ] `directTransfer()` locks warehouses in sorted-ID order and variants in sorted-ID order.
- [ ] `directTransfer()` is atomic on mid-loop failure (transaction rollback).
- [ ] Movements created by direct transfer carry `reference_type = App\Models\DirectTransfer::class` and `reference_id = header.id`.
- [ ] `TransferIn.related_movement_id` links to the paired `TransferOut`.
- [ ] `DirectTransferResource::$model` is `App\Models\DirectTransfer` (not `StockMovement`).
- [ ] `DirectTransferForm` uses `Repeater::make('items')` with `->minItems(1)`.
- [ ] `DirectTransferInfolist` and `ViewDirectTransfer` page exist.
- [ ] `DirectTransfersTable` uses card layout, declares `->contentGrid()` and `->defaultPaginationPageOption(12)`, and declares no bulk actions (F30).
- [ ] `DirectTransferPolicy` exists and is registered in `AuthServiceProvider::$policies`.
- [ ] `StockMovementPolicy::createDirectTransfer()` is removed.
- [ ] `WarehousePolicy::delete()` blocks warehouses referenced by direct transfers (from or to).
- [ ] `DirectTransferResource::getEloquentQuery()` eager-loads `fromWarehouse`, `toWarehouse`, `transferredBy`, `items.productVariant`.
- [ ] `DirectTransferResource::getEloquentQuery()` applies both-endpoint warehouse scoping for non-admin/non-auditor users.
- [ ] All policies resolve through `Gate`.
- [ ] All observers are registered before seeding.
- [ ] `InventoryServiceProvider` resolves all services.
- [ ] `AdminPanelProvider` registers strict authorization and all declared resources/widgets.
- [ ] STN routes exist.
- [ ] STN QR URLs are actually signed and expire after seven days.
- [ ] STN scan is protected by authentication and the `receive` policy.
- [ ] Warehouse-scoped lists cannot expose another warehouse's operational records.
- [ ] Transfer confirmation is atomic.
- [ ] Transfer dispatch rejects non-confirmed requisitions.
- [ ] Scan payloads are canonicalized and validated.
- [ ] Scan idempotency survives concurrent duplicate requests.
- [ ] Substitute-variant loss costing uses the actual dispatched variant.
- [ ] Purchase lifecycle transitions are locked.
- [ ] Sales price snapshots read from locked variants.
- [ ] Post-commit events do not publish from rolled-back transactions.
- [ ] Architecture/runtime completeness tests pass.
- [ ] Concurrency tests pass.
- [ ] Pest Unit/Feature and standalone Playwright E2E suites pass.
- [ ] Playwright Scenario 02 (single item), 02b (multi item), 02c (guards), and 02d (responsive) pass.
- [ ] No `TODO`, placeholder class, missing route, or unresolved service reference remains in the declared runtime surface.

### v13.6 Badge Role-Scope Acceptance Criteria

- [ ] `ScopesNavigationBadges` trait exists at `app/Filament/Support/Concerns/ScopesNavigationBadges.php`.
- [ ] `ScopesNavigationBadges::badgeScopedWarehouseIds()` returns all warehouses for Admin.
- [ ] `ScopesNavigationBadges::badgeScopedWarehouseIds()` returns all warehouses for Auditor.
- [ ] `ScopesNavigationBadges::badgeScopedWarehouseIds()` returns the union of assigned warehouses for WarehouseStaff with N ≥ 2.
- [ ] `ScopesNavigationBadges::badgeScopedWarehouseIds()` returns exactly one ID for WarehouseStaff with N = 1.
- [ ] `ScopesNavigationBadges::badgeScopedWarehouseIds()` returns `[]` for WarehouseStaff with N = 0.
- [ ] `ScopesNavigationBadges::hasBadgeScope()` returns `false` when the resolver yields an empty set.
- [ ] The resolver caches within the request.
- [ ] `flushBadgeScope()` exists, resets both the warehouse scope and the cached badge count, and is called on auth change in long-lived workers.
- [ ] `TransferRequisitionResource`, `PurchaseOrderResource`, `SalesOrderResource`, and `InTransitResource` all consume `ScopesNavigationBadges`.
- [ ] No badge-bearing resource computes warehouse IDs via `auth()->user()->warehouses()` directly.
- [ ] A WarehouseStaff user assigned exactly one warehouse sees only that warehouse's documents in the badge count.
- [ ] The counterpart warehouse of a transfer does not widen a single-warehouse user's badge.
- [ ] `getNavigationBadge()` returns `null` when the scope is empty.
- [ ] `getNavigationBadgeColor()` shares the same cached count as `getNavigationBadge()`.
- [ ] Badge scope consistency test proves badge count never exceeds list query count for the same user.
- [ ] Playwright Scenario 15b (single-warehouse role scope) passes.
- [ ] Playwright Scenario 15c (Admin scope with empty pivot) passes.

---

## 📊 Section 27: Council Change Summary

### v13.7 Change Table (Blueprint ↔ Code Reconciliation)

| # | Area | Resolution | Severity |
|---|---|---|---|
| 1 | Wizard review step (§7B.2, §7C.2, §7G.2, §7H.2, §18.3) | Replaced retired `Placeholder` + `WizardReviewStep::renderSummary()` contract with shipped shape — all 4 pages use `View::make('filament.wizards.*')->viewData(...)`. SalesOrder conformed from `Livewire::make` to `View::make`; `WizardReviewSummary` Livewire component removed | P1 |
| 2 | Money formatting (§7A.2, §7F.1, §7F.2, §7G.4, §7H.4, §0A.14, Principle 10) | Removed invalid `->money(config('app.currency'), decimals: 4)` (Filament v5 rejects `decimals:`); documented global `format_money()` helper. ProductsTable uses `format_money($state, 2)`; other `decimal(15,4)` sites default precision 4 | P1 |
| 3 | Relationship-bound repeaters (F17, §7B.3, §14) | Inverted F17: relationship repeaters must NOT declare `->dehydrated()` (SQL error `Column not found: items`); only field-level `*_unit_ratio` keeps it. Removed repeater-level `->dehydrated()` from §7 code samples | Governance |
| 4 | Line-item infolists (§7B.4, §7C.5, §7G.4, §7H.4, §7O.4, §14) | Converted `Grid::make([...])->schema([...])` line-item composition to `RepeatableEntry::make(...)->table([TableColumn...])->schema([...])` with `->alignEnd()` on numeric entries; added `TableColumn` import | P1 |
| 5 | Record actions grouping (§7A.2, §7B.3, §7G.3, §7H.3) + new F31 | Documented `ActionGroup::make([...])` with nested `->dropdown(false)` sections and `EllipsisVertical` trigger styling; added Principle F31. (Suppliers/Customers tables intentionally stay flat.) | Governance |
| 6 | Record-action alignment (§17.2, §7N.5) | Documented global `Table::configureUsing()->recordActionsAlignment('end')` via `AppServiceProvider::configureTable()`; no per-table `BeforeColumns`/alignment exists | Governance |
| 7 | Removed non-existent API `->maxHeight()` | Confirmed absent from blueprint and authored code (only in vendored Filament JS) | Governance |
| 8 | Translation keys (§0A.2a) | Added `actions.more` and `resources.{products,transfer_requisitions,purchase_orders,sales_orders}.actions.more_actions` to the canonical catalogue. ⚠ These keys are referenced by F31 tooltips but are NOT yet present in `lang/{en,es,tl}` — a code-side gap requiring a separate fix (see Unverified list) | P1 |
| 9 | New file `app/Helpers.php` | Added to §25 file map with `format_money()` role note | Governance |
| 10 | Wizard files removal | Removed non-existent `app/Filament/Support/Wizards/WizardReviewStep.php` from §25. Deleted dead `app/Filament/Components/WizardReviewStep.php` + its blade, and the now-unused `app/Livewire/Wizards/WizardReviewSummary.php` + its tests (all zero-call-site after item 1) | Governance |

> **Council note:** items 6 and 8 were reconciled to the *shipped* code, which itself diverges from the original scope description. Item 8's translation keys are a genuine code gap (unresolved `__()` references). Item 1's SalesOrder-vs-others wizard inconsistency and the dead wizard classes were resolved by owner direction: SalesOrder conformed to `View::make`, and `Components/WizardReviewStep.php` + `Livewire/Wizards/WizardReviewSummary.php` (with its tests) were deleted. Application code changed in those two areas only.

### v13.6 Change Table

| # | Area | Resolution | Severity |
|---|---|---|---|
| 1 | Principle A12 | Added — badge scope is role + warehouse determined, counterpart warehouse never widens single-warehouse scope | Governance |
| 2 | Badge resolver | Added `ScopesNavigationBadges` trait with cached, role-aware, cardinality-aware resolution | P0 |
| 3 | Badge principle §1B.1a | Rewrote to formalize four scope tiers | Governance |
| 4 | Badge tier: Admin | Unscoped — all warehouses regardless of `user_warehouse` pivot | Behavior |
| 5 | Badge tier: Auditor | Unscoped — all warehouses regardless of `user_warehouse` pivot | Behavior |
| 6 | Badge tier: WarehouseStaff N≥2 | Union of assigned warehouses | Behavior |
| 7 | Badge tier: WarehouseStaff N=1 | Exactly one warehouse — no fallback, no counterpart widening | Behavior |
| 8 | Badge tier: WarehouseStaff N=0 | `null` badge | Behavior |
| 9 | Badge implementations | Rewrote all four resources to consume the trait | P0 |
| 10 | Badge tests | Added `BadgeScopeTest` and `NavigationBadgeRoleScopeTest` suites | Test |
| 11 | Badge acceptance | Extended §26 with v13.6 badge criteria | Governance |
| 12 | Badge performance | Cached per request alongside the count; flushed on auth change in long-lived workers | Perf |
| 13 | §20.4 Badge vs Query scope | Added consistency rule — badge count must never exceed list query count | Integrity |
| 14 | File map | Added `ScopesNavigationBadges.php` to §25 | Governance |
| 15 | E2E scenarios | Added Scenario 15b (single-warehouse) and 15c (admin) | Test |

### v13.5 Change Table (Retained)

| # | Area | Resolution | Severity |
|---|---|---|---|
| 1 | Direct Transfer multi-item support | Added `direct_transfers` + `direct_transfer_items` tables | Product |
| 2 | Direct Transfer model | Rebound resource from `StockMovement` to new `DirectTransfer` header | Product |
| 3 | Direct Transfer service | `directTransfer()` accepts `array $items`, emits N paired movements in one transaction | Product |
| 4 | Direct Transfer form | Repeater replaces flat single-item Section | Product |
| 5 | Direct Transfer view | Added `DirectTransferInfolist` + `ViewDirectTransfer` page | Product |
| 6 | Direct Transfer table | Promoted from standard ledger table to card layout (document-shaped) | Product |
| 7 | Direct Transfer policy | Added `DirectTransferPolicy`; removed `StockMovementPolicy::createDirectTransfer()` | Security |
| 8 | Warehouse delete guard | Extended `WarehousePolicy::delete()` for direct-transfer references | Integrity |
| 9 | Warehouse relations | Added `directTransfersFrom()` / `directTransfersTo()` | Integrity |
| 10 | Factories | Added `DirectTransferFactory` and `DirectTransferItemFactory` | Test infra |
| 11 | Principle | Added A11 — Direct Transfers are multi-line fire-and-forget operations | Governance |
| 12 | Acceptance | Section 26 extended with direct-transfer acceptance items | Governance |
| 13 | Runtime service wiring | Added `InventoryServiceProvider` and provider registration | P0 |
| 14 | Missing `TransferRequisitionService` | Added atomic confirm/cancel service contract | P0 |
| 15 | Missing Resource classes | Added mandatory Resource implementation contract for all resources | P0 |
| 16 | Missing Page classes | Added mandatory Page implementation contract | P0 |
| 17 | Missing dashboard widgets | Added mandatory widget implementation/scope contract | P0 |
| 18 | STN route/controller gap | Added `StnController` and named routes | P0 |
| 19 | Unsigned QR route | Required seven-day temporary signed URL generation | P0 |
| 20 | Idempotency model gap | Added `StockMovementIdempotencyKey` model | P0 |
| 21 | Transfer confirmation race | Moved materialization + confirmation into one locked transaction | P0 |
| 22 | Transfer dispatch state gap | Require `Confirmed` before dispatch | P0 |
| 23 | Scan payload integrity | Canonical hashing, validation, lock ordering, duplicate-race handling | P0 |
| 24 | Warehouse list exposure | Enforce database-level warehouse scope before pagination | P0 |
| 25 | Purchase transition race | Locked order/cancel lifecycle methods | P1 |
| 26 | Sales price snapshot race | Locked variants before reading current price | P1 |
| 27 | Substitute loss costing | Snapshot actual dispatched variant cost | P1 |
| 28 | Event timing | Added post-commit event contract | P1 |
| 29 | Wizard review placeholders | Added reusable `WizardReviewStep` component contract | P1 |
| 30 | Runtime completeness | Added architecture tests for services/resources/routes/policies/providers | P1 |
| 31 | Concurrency coverage | Added explicit race-condition test matrix | P1 |

### Historical v13.3 Summary (Retained)

| # | Area | Resolution | Severity |
|---|---|---|---|
| 1 | Sales dispatch self-reservation double-count | `batchAvailableQuantity()` and `availableQuantity()` accept `$excludeSalesOrderId`; dispatch and modal pass order ID | Critical |
| 2 | First-scan detection permanently true | Replaced `cleared_at` existence check with `stock_movement_idempotency_keys` presence | Critical |
| 3 | In-transit rows never clear | `cleared_at` column added; `markInTransit()` transitions to `Cleared`/`Lost` | Critical |
| 4 | Purchase receive stale item race | Items and variants re-locked under parent transaction | High |
| 5 | Sales dispatch stale item race | Items and variants re-locked under parent transaction | High |
| 6 | Nullable `approved_base_qty` race | `materializeRequestedAsApproved()` validates; reservation queries filter `whereNotNull` | High |
| 7 | Missing `ProductPolicy` | Policy added and registered | High |
| 8 | Missing `CustomerPolicy` | Policy added and registered | High |
| 9 | Transfer dispatch no availability guard | `dispatchTransfer()` checks availability excluding own reservation | Medium |
| 10 | Zero-cost loss silent | `snapshotUnitCostFrom()` logs warning; loss note set when cost missing | Medium |
| 11 | Warehouse delete orphaning PO/SO/TR | `WarehousePolicy::delete()` extended | Medium |
| 12 | `recordSalesReturn` lock gap | Variant and warehouse locked | Medium |
| 13 | Substitute variant absence undocumented | Principle A10 added | Medium |
| 14 | Badge query duplication | Cached per-request via `private static ?int` | Medium |
| 15 | `recordMovement()` bypass footgun | Rejects purchase/sale/sale_return/purchase_return types | Medium |
| 16 | Document table `created_at` unindexed | Indexes added to `transfer_requisitions`, `purchase_orders`, `sales_orders` | Low |
| 17 | `ManageUnitConversionsAction` unverifiable | Full implementation provided | Low |
| 18 | Custom-range filter one-sided indicators | `No lower bound` / `No upper bound` indicators | Low |
| 19 | Card tables declared unusable bulk actions | Bulk actions removed; F30 added | Low |
| 20 | `user_warehouse` pivot dual edit surfaces | `WarehouseForm` users field made read-only; editing documented as UserResource-only | Low |

---

*End of blueprint v13.6 + i18n — Direct Transfer multi-item support, translation coverage, runtime wiring, and role + warehouse scoped navigation badges fully integrated.*