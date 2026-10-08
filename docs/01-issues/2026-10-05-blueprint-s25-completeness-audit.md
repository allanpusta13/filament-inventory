# Blueprint §25 Completeness Audit — Verify Every Declared File Exists

- **Date:** 2026-10-05
- **Repo:** `D:\Personal\filament-inventory`
- **Scope:** blueprint §25 file map — existence + content contract (class exists, correct base/contracts, declared methods)
- **Mode:** read-only audit. **No repository files modified.** No fixes applied — findings only.
- **Locale scope:** `lang/en` only. `lang/es` and `lang/tl` deliberately NOT audited (out of scope per task).
- **Verdict:** **FAIL** — 8 declared files missing; 4 runtime-fatal route defects.

## Method

14 independent workstreams (A–N), each a subagent, read every file on disk. Content verified —
never trust filename or directory listing alone. A file named correctly but empty/stubbed counts as failure.
Aggregation (Workstream O) performed by the orchestrator after all A–N returned
(subagents cannot spawn subagents, so no separate aggregator process ran).

All 14 workstreams confirmed **zero file modifications** (read-only: Read/Grep/Glob/`php -l`).

## Summary counts

| WS | Category | Declared | Present | Divergent | Missing | Empty (correct) | Verdict |
|---|---|---|---|---|---|---|---|
| A | HTTP controller + Livewire component | 2 | 0 | 1 | 1 | 0 | FAIL |
| B | Domain services | 6 | 6 | 0 | 0 | 0 | PASS |
| C | Providers + `bootstrap/providers.php` | 6 | 4 | 2 | 2 | 0 | FAIL |
| D | Policies | 13 | 13 | 0 | 0 | 0 | PASS |
| E | Events / Listeners / Notifications | 8 / 8 / 8 | 9 / 9 / 9 | 0 | 0 | 0 | PASS |
| F | Enums + Exceptions | 17 | 17 | 0 | 0 | 0 | PASS |
| G | Models | 21 | 21 | 0 | 0 | 0 | PASS |
| H | Observers / Support / Helpers | 4 | 4 | 1 | 0 | 0 | PASS (1 doc-only) |
| I | Resources (Products → Suppliers/Customers) | 38 | 38 | 3 | 0 | 0 | WARN |
| J | Resources (DirectTransfers/InTransits/StockMovements/LossLedgers) | 20 | 20 | 0 | 0 | 0 | PASS |
| K | Resources (PurchaseOrders/SalesOrders) | 16 | 16 | 0 | 0 | 0 | PASS |
| L | Resources (StockMovements/LossLedgers) | 10 | 10 | 0 | 0 | 0 | PASS |
| M | Support / Widgets / DB / Views / lang | 81 | 77 | 8 | 4 | 3 | FAIL |
| N | Routes + Architecture test suites | 2 routes / 8 suites | 1 route / 8 suites | — | 1 route | 0 | FAIL |

## ❌ Missing files (8)

| Declared path (§25) | Evidence |
|---|---|
| `app/Providers/AuthServiceProvider.php` | absent on disk. `$policies` map does not exist; replaced by 13 `Gate::policy()` calls at `app/Providers/AppServiceProvider.php:151-187` |
| `app/Providers/EventServiceProvider.php` | absent on disk. `$listen` map does not exist; replaced by 9 `Event::listen()` calls at `AppServiceProvider.php:204-212` |
| `app/Http/Controllers/StnController.php` | exists renamed as `app/Http/Controllers/STNManifestController.php` (class also renamed; line 32) |
| `app/Livewire/Stn/ScanForm.php` | absent. `app/Livewire/` holds only `Warehouses/AssignedUsersList.php`. Grep hits for "ScanForm" are docblock text only |
| `resources/views/components/layouts/app.blade.php` | absent. `resources/views/components/` empty. Two blades `@extends('filament::layouts.app')` |
| `resources/views/livewire/stn/scan-form.blade.php` | absent. `app/Livewire/Stn/` absent too |
| `resources/views/stn/print.blade.php` | absent. `resources/views/stn/` directory absent. Real nearest: `resources/views/pdf/stn-manifest.blade.php` |
| `resources/views/stn/scan.blade.php` | absent. Real nearest: `resources/views/scan-receive/show.blade.php` |

## 🔴 Runtime-critical defects

1. **`routes/web.php:5`** — `use App\Http\Controllers\ScanReceiptController;` — **class does not exist**.
2. **`routes/web.php:15`** — route `stn.print-direct` targets `[STNManifestController::class, 'printDirectTransfer']`.
   **Method does not exist** — controller exposes only `print()` (`:44`) and `scan()` (`:75`). Any request fatals.
3. **Route `stn.scan` is never registered**, yet it is referenced by:
   - `app/Filament/Resources/TransferRequisitions/Tables/TransferRequisitionsTable.php:328`
     (`URL::temporarySignedRoute('stn.scan', …)`) → `RouteNotFoundException`.
   - `resources/views/scan-receive/show.blade.php:85` POSTs to unregistered `route('stn.scan.receive')`.
4. **`STNManifestController::print()` returns `view('stn.print')` (`:54`); `scan()` returns `view('stn.scan')` (`:91`)** —
   both views absent → 500 even once routing is fixed.
5. **`scan()` and `InventoryService::scanToReceive()` (`app/Services/InventoryService.php:488`) are fully implemented
   but unreachable** — zero production callers. `scan()` has correct authorization (`Gate::authorize('receive', …)`),
   correct state guard (must be `Dispatched`/`PartiallyReceived` else `abort(403)`), and a view — but nothing routes to it.
   The missing `ScanForm` Livewire component was the intended entry point.

Note: `print()` authorization posture is sound — `Gate::authorize('view', $transferRequisition)` (`:46`) on route-model binding.

## ⚠️ Divergent files

| Path | Divergence |
|---|---|
| `app/Filament/Widgets/QuickActionsWidget.php:27` | `$view = 'filament.widgets.quick-actions'` **commented out**; no `getView()` override → widget renders no view. Its blade (`quick-actions.blade.php`, 479 B) exists but is unwired |
| `app/Filament/Pages/Dashboard.php` `getWidgets()` | returns **4 of 9** widgets (`StatsOverview`, `LowStockAlerts`, `RecentMovements`, `ActiveInTransit`). Orphaned: `SalesRevenueTrend`, `SalesVsPurchases`, `TopSellingVariants`, `PendingFulfillment`, `QuickActions` |
| `bootstrap/providers.php` | registers 4 providers (App, AdminPanel, Inventory, `Fruitcake\LaravelDebugbar`) — blueprint mandates 5 including Auth + Event |
| `app/Providers/Filament/AdminPanelProvider.php` | `->discoverPages(in: app_path('Filament/Pages'), …)` at line 90 (blueprint says discoverPages intentionally absent); `->widgets([])` empty at line 96 (9 widgets commented out) |
| `app/Support/GeneratesReferenceCodes.php` | `final class` + `public static generateReferenceCode()` (`:134`/`:144`), **not a trait**. §2 prose says "trait"; §2's own snippet is also a class — blueprint self-contradicts |
| `lang/en/attributes.php` | holds **17 keys** (`:14-30`); §0A.2a declares it an empty structural placeholder. File's own docblock says no standalone keys — self-contradictory |
| `app/Filament/Resources/Users/UserResource.php` | declares none of `getModelLabel` / `getPluralModelLabel` / `getNavigationLabel`; all 4 sibling Resources declare all three (e.g. `SupplierResource.php:42,47,52`) |
| `app/Filament/Resources/Suppliers/SupplierResource.php:79-84` | `getPages()` registers only index/create/edit and class has no `infolist()` → `SupplierInfolist` + `ViewSupplier` unreachable |
| `app/Filament/Resources/Customers/CustomerResource.php:79-84` | same defect → `CustomerInfolist` + `ViewCustomer` unreachable |
| 4 migrations (`2026_09_21_*`) | `suppliers`, `customers`, `purchase_orders`, `purchase_order_items` define `up()` but **no `down()`** (verified: file ends at line 24-25). Note: 22 other migrations do have `down()` — not all files, just these 4 |
| `database/seeders/UserRoleSeeder.php:47` | claims `DemoWorkflowSeeder` runs after it — false; neither seeder is invoked (no `->call()` in `DatabaseSeeder`) |

## ❌ Test suite — broken oracle + failures

Run result: **21 failed, 134 passed (262 assertions)**, 19.26s.

| Test | Failure | Evidence |
|---|---|---|
| `tests/Feature/Architecture/PolicyRegistrationTest.php:15` | **Broken oracle** — `toBe($policy)` compares a Policy **object** (from `Gate::getPolicyFor()`) against a **class-string**. All 13/13 pairs fail regardless of actual wiring. This suite **cannot detect a mis-wired policy**. Fix: `toBeInstanceOf` |
| `tests/Feature/Architecture/BadgeScopeTest.php:92` | `expect(admin()->warehouses()->count())` = 1, expected 0 |
| `tests/Feature/Architecture/BadgeScopeTest.php:281` | `QueryException: in_transits has no column named cleared_at` — schema/test mismatch |
| `tests/Feature/Architecture/RouteWiringTest.php:18,31,53` | `stn.scan` missing → `Route::has` false, no key, `RouteNotFoundException` |
| `tests/Feature/Architecture/ResourceCompletenessTest.php:124` | 3× — `stn.print` / `stn.scan` / `livewire.stn.scan-form` views absent |

**Genuinely passing suites:** `RuntimeWiringTest` (36P), `WarehouseScopeTest` (9P), `ServiceResolutionTest` (15P),
`FormSyntaxTest` (2P), `TextBooleanScopeTest` (1P), `FormFieldSpanTest` (1P). No `expect(true)->toBeTrue()` placeholders anywhere.

## ✅ Verified correct (content-inspected, not filename-trusted)

- **Enums (9)** — all string-backed, implement `HasLabel` + `HasColor` + `HasIcon`; `getLabel()` uses `__()`;
  all 42 translation keys resolve in `lang/en/enums.php`; key set matches cases exactly.
- **Exceptions (8)** — `DomainErrorException` abstract over `\DomainException` with `final translationKey(): string` (`:45`)
  and `final context(): array` (`:51`), promoted `readonly`; concrete exceptions carry resolvable keys;
  `DomainRuleViolationException`/`NegotiationNotAllowedException` intentionally key-carried by throw-site.
- **Models (21)** — all present; `Warehouse` relations correct (HasMany at `:124`, `:132`).
- **Services (6)** — exact match to blueprint §6.1–§6.6 public surface, zero missing/extra methods:
  `GuardsOutstandingQuantity` (3 assert methods, plain class not trait), `InventoryService`
  (`recordMovement`, `directTransfer`, `dispatchTransfer`, `scanToReceive`, `recordLoss`, `adjustment`),
  `NegotiationService` (6), `TransferRequisitionService` (ctor + `confirm` + `cancelRequisition`),
  `PurchaseService` (ctor + 3), `SalesService` (ctor + 4). Promoted `private readonly` ctor injection in TRS/Purchase/Sales.
  No dead code or duplicated logic between services.
- **Observers (2)** — `ProductObserver` deleting guard (`:29-39`), `ProductVariantObserver` base-unit `firstOrCreate` (`:78-90`),
  byte-match blueprint §3.19; registered at `AppServiceProvider.php:134-135`.
- **Helpers** — `format_money()` global at `app/Helpers.php:177` `(mixed $state, int $precision = 4): string`,
  `function_exists`-guarded (`:167`), autoloaded via `composer.json` `autoload.files` (`:50-52`). 7 call sites.
- **Events (8)** — all `implements ShouldDispatchAfterCommit`.
- **Listeners (8)** — all `implements ShouldQueue`; every `handle()` param type matches its paired event; no cross-wiring.
- **Notifications (8)** — all `via()` returns exactly `['database']`; every message flows through `__()`; zero hardcoded literals;
  all referenced keys resolve in `lang/en/notifications.php`.
- **Filament Resources** — all declared Resources + Pages/Schemas/Tables present with correct namespaces;
  `DirectTransferResource::$model = DirectTransfer::class` (`:34`); Repeater `minItems(1)` (`:77`/`:142`);
  `contentGrid` (`:76`); `defaultPaginationPageOption(12)` (`:95`); `WarehouseResource` scope correct
  (non-admin/non-auditor constrained to assigned warehouse ids, `:79-84`).
- **Widgets (8 of 9)** — real data logic present (`DB::`, `Cache::remember`), correct base classes, `$sort` 1–9, `canView()`.
- **Migrations** — both critical indexes present: `product_variant_prices` partial unique index on `is_current = true`;
  `stock_movement_idempotency_keys` unique index `idempotency_requisition_checksum_unique`.
- **Factories (21)** — all present, non-empty (679–2502 B), correct states (`UserFactory`: unverified/warehouseStaff/admin/auditor/branchManager).
- **DatabaseSeeder** — uses factories, calls `InventoryService::adjustment()` (`:144`, `:148`), no direct `StockMovement` insert.
- **`lang/en` (14 of 15)** — `actions` 3, `common` 23, `dashboard` 5, `enums` 9, `errors` 33, `forms` 0 ✓, `navigation` 1,
  `notifications` 9, `resources` 12, `stn` 2, `tables` 0 ✓, `validation` 112, `widgets` 0 ✓, `wizards` 4.

## Undeclared extras (supersets — deliberate, documented in-code)

- `app/Events/PurchaseOrderCancelled.php` + `app/Listeners/NotifyPurchaseOrderCancelled.php` +
  `app/Notifications/PurchaseOrderCancelledNotification.php` — §22.1/§22.3a/§22.3b omit them, §6.4 fires the event;
  wired at `AppServiceProvider.php:210`, rationale `:198-200`.
- `app/Policies/TransferRequisitionItemRevisionPolicy.php` — 14 policies vs 13 declared; unregistered.
- `database/seeders/DemoWorkflowSeeder.php` + `database/seeders/UserRoleSeeder.php` — 3 seeders vs 1 declared; never invoked.
- `lang/en/{auth,pagination,passwords,direct_transfers}.php` — 19 lang files vs 15 declared.
- `tests/Feature/Architecture/TextBooleanScopeTest.php` + `FormFieldSpanTest.php` — 10 suites vs 8 declared.
- 26 migrations vs 23 declared.
- Dead extras (zero consumers, not §25 failures): `app/Traits/DashboardFilterable.php` (dead + broken import),
  `app/Traits/StockActions.php` (dead + stale API refs), `app/Casts/DummyCast.php` (placeholder),
  `app/Console/Commands/Welcome.php` (starter-kit).
- Orphan blades (zero references): `stats-overview`, `low-stock-alerts`, `recent-movements`,
  `stock-by-warehouse`, `warehouse-capacity`, `warehouse-filter`, `chart-data-tables/*`.
- Orphaned Resource artifacts: `Suppliers/Schemas/SupplierInfolist.php`, `Suppliers/Pages/ViewSupplier.php`,
  `Customers/Schemas/CustomerInfolist.php`, `Customers/Pages/ViewCustomer.php`,
  `TransferRequisitions/Schemas/RevisionsForm.php`, `TransferRequisitions/Tables/RevisionsTable.php`.

## Recommended remediation order

1. **`routes/web.php`** — remove the dead `ScanReceiptController` import; remove or repair the `stn.print-direct` route;
   register `stn.scan` behind `auth` + `signed`. This alone stops the fatal 500s on shipped UI.
2. **STN views + Livewire** — create `resources/views/stn/{print,scan}.blade.php` and `Livewire\Stn\ScanForm` + its view
   (or repoint the real `pdf/stn-manifest.blade.php` and `scan-receive/show.blade.php`). This makes `scanToReceive()` reachable.
3. **Fix `PolicyRegistrationTest.php:15`** — change `toBe` to `toBeInstanceOf`; today the suite masks whether any policy is wired.
4. **Providers** — register the relocated policies/events in the two declared provider files, or amend §25
   (Laravel 11+ dropped auto-discovery of Auth/Event providers, so their absence is conventional for this stack).
5. **Widgets** — uncomment `QuickActionsWidget::$view`; decide whether the 5 dashboard-orphaned widgets are intended.
6. **`lang/en/attributes.php`** — resolve the empty-canonical vs 17-keys contradiction against §0A.2a.
7. **§25 doc sync** — reconcile renames and extras so the blueprint matches the repository.

## Machine-readable checklist

```json
{
  "audit": "blueprint-s25",
  "date": "2026-10-05",
  "overall": "FAIL",
  "files_modified": 0,
  "locale_scope": "lang/en only (es, tl NOT audited)",
  "counts": {
    "enums": {"d": 9, "p": 9}, "exceptions": {"d": 8, "p": 8},
    "providers": {"d": 5, "p": 3}, "policies": {"d": 13, "p": 13},
    "events": {"d": 8, "p": 9}, "listeners": {"d": 8, "p": 9}, "notifications": {"d": 8, "p": 9},
    "models": {"d": 21, "p": 21}, "resources": {"d": 12, "p": 12},
    "widgets": {"d": 9, "p": 9}, "services": {"d": 6, "p": 6},
    "controllers": {"d": 1, "p": 0}, "livewire": {"d": 1, "p": 0},
    "views": {"d": 9, "p": 5}, "migrations": {"d": 23, "p": 26},
    "factories": {"d": 21, "p": 21}, "seeders": {"d": 1, "p": 3},
    "lang_en": {"d": 15, "p": 19}, "arch_tests": {"d": 8, "p": 10}
  },
  "missing": [
    "app/Providers/AuthServiceProvider.php",
    "app/Providers/EventServiceProvider.php",
    "app/Http/Controllers/StnController.php",
    "app/Livewire/Stn/ScanForm.php",
    "resources/views/components/layouts/app.blade.php",
    "resources/views/livewire/stn/scan-form.blade.php",
    "resources/views/stn/print.blade.php",
    "resources/views/stn/scan.blade.php"
  ],
  "critical": [
    "routes/web.php:5 imports nonexistent ScanReceiptController",
    "routes/web.php:15 route targets nonexistent STNManifestController@printDirectTransfer",
    "stn.scan route undefined but referenced by TransferRequisitionsTable:328",
    "STNManifestController returns missing views stn.print (line 54) and stn.scan (line 91)",
    "scan() and InventoryService::scanToReceive() unreachable - zero callers"
  ],
  "divergent": [
    "QuickActionsWidget $view commented out at :27",
    "Dashboard::getWidgets returns 4 of 9 widgets",
    "bootstrap/providers.php registers 4 not 5",
    "AdminPanelProvider discoverPages present + widgets([]) empty",
    "GeneratesReferenceCodes is final class not trait",
    "lang/en/attributes.php holds 17 keys vs empty-canonical",
    "UserResource missing 3 label methods",
    "Supplier/Customer infolist+view orphaned (getPages incomplete)",
    "4 migrations missing down() (suppliers, customers, purchase_orders, purchase_order_items)",
    "DemoWorkflowSeeder/UserRoleSeeder never invoked"
  ],
  "tests": {
    "summary": "21 failed, 134 passed, 262 assertions",
    "broken_oracle": "PolicyRegistrationTest.php:15 toBe against class-string",
    "failing_suites": ["PolicyRegistration 13F", "ResourceCompleteness 3F", "RouteWiring 3F", "BadgeScope 2F"]
  },
  "passing_workstreams": ["B", "D", "E", "F", "G", "H", "J", "K", "L"]
}
```

## Limitations

- `lang/es` and `lang/tl` were **not** audited (explicitly out of scope). Reported observation:
  each locale has 12 files vs `lang/en`'s 19 — both missing `auth`, `dashboard`, `direct_transfers`,
  `pagination`, `passwords`, `stn`, `wizards`. This is **not** an audited finding.
- Several subagents hit a session-wide rendering artifact where the Read/Bash lens dropped PHP keywords
  (`function`, `if`, `=>`, operators). Their structural verdicts came from a keyword-free PHP parser cross-checked
  with `php -l` and `ls`; the orchestrator's own verification pass (`Read`, `Grep`, `Glob`) was unaffected.
- No fix was applied and no pre-existing file was modified. This document is the only write, and it is the audit's deliverable.
