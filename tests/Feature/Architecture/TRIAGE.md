# WS-G Failure Triage (C-15)

**Status:** read-only boundary artifact. No failure listed here was fixed in this
workstream. Every cluster below is escalated, not remediated.

**Evidence source:** `triage-junit.xml` (generated this session,
`php artisan test --log-junit triage-junit.xml`).
Top-level `phpunit.xml` suite: `tests="2053" assertions="4463" errors="78"
failures="40" skipped="3" time="74.096"`. **118 failed / 3 skipped / 1932 passed.**

**Superseded source:** the user-supplied `test-output.txt` (149F / 1878P) is
**stale** — its embedded SQL timestamps read `2026-10-03`, it predates C-2/C-3/C-4,
and it shows `BadgeScopeTest:92` failing without the `detach()` fix. Do not triage
from it.

**Baseline for delta:** pre-P2.5 = 1929P / 120F / 3S. Post-change = 1932P / 118F / 3S.
Net **+3P / −2F**. The two retired failures are `BadgeScopeTest` (C-3) and
`DashboardWidgetsTest` (C-4); both are GREEN on disk and absent from the failing
list above.

---

## Post-fix state (2026-10-08, C-1 regeneration)

**Evidence source:** `triage-junit-current.xml`
(`php artisan test --log-junit triage-junit-current.xml`).
Top-level suite: `tests="2049" assertions="4650" errors="16" failures="10"
skipped="3" time="138.08"`. Headline: **2020 passed / 26 failed / 3 skipped.**

**Solo-run counts** (per-file `php artisan test <file>`, authoritative — JUnit
`<testcase>` name attribution is unreliable when a Pest dataset test errors):

| Cluster | File | Solo F/E | Class | Notes |
|---|---|---|---|---|
| 5 | `tests/Unit/Notifications/NotificationContractTest.php` | 14 | test-drift | 5a TypeError int-vs-string (7) + 5b DatasetArgumentsMismatch (7) |
| 3 | `tests/Unit/Listeners/Filament/Support/Concerns/ScopesNavigationBadgesTest.php` | 4 | mixed | 3a empty-pivot (2), 3b unknown-role enum (2) |
| 11 | `tests/Unit/Factories/{Catalog,Operations,Purchasing,Sales}FactoryTest.php` | 5 | test-drift | factory value-asserts (bcmul 4dp, float precision, null-vs-default) |
| 10 | `tests/Feature/Filament/Resources/BatchCResourceTest.php` | 2 | **app-bug** | `WarehouseForm.php:69` `Livewire::helperText()` does not exist (Filament v5) |
| — | `tests/Unit/Enums/TransferRequisitionStatusTest.php` | 1 | boundary | distinct-icon: 9 actual vs 10 asserted (deliberate enum pairing) |

**Clusters retired since the 118F baseline** (mechanically fixed, C-3/FIX-APPLICATION
+ residual clusters): 1 (Heroicon stringify — 9), 2 (NOT NULL columns — 70),
3c (Gate instance-vs-class-string — 10), 4 (`ReferenceCodeFactoryTest` `::new()` — 12),
6 (`actingAsStaff()` helper — 3), 7 (UniqueConstraint — 2), 9 (CarbonImmutable — 2),
10-partial (products i18n key `fields.name` → `fields.variant_name`).

**Note on `InTransitStatusTest`:** present in JUnit failure list, but a solo run is
**15P/0F**. JUnit attribution artifact, not a live failure.

Category key:

| Tag | Meaning |
|---|---|
| **test-drift** | Test oracle / dataset wiring is wrong; app behavior may be correct. |
| **app-bug** | Application code or schema violates a documented contract. |
| **blueprint-drift** | Code and approved blueprint disagree; which side is canonical is an owner decision. |
| **boundary** | Needs a product / architecture / dependency / destructive decision before any fix. |

---

## 1. Enum `Heroicon` stringify — 9 errors — test-drift

**Files (1 error each):** `tests/Unit/Enums/{InTransitStatus,LossCategory,NegotiationSide,PurchaseOrderStatus,RevisionStatus,SalesOrderStatus,StockMovementType,TransferRequisitionStatus,UserRole}Test.php`

**Symptom:** `Error: Object of class Filament\Support\Icons\Heroicon could not be
converted to string` — e.g. `InTransitStatusTest.php:89`,
`LossCategoryTest.php:94`.

**Cause:** the tests call `array_unique()` / string comparison over the icon
accessor, which returns the `Heroicon` **enum** (correct per the enum tests'
own "never a raw string" case), not a `string`. The oracle treats it as a string.

**Why test-drift:** the sibling case in the same files asserts the accessor
returns a `Heroicon` enum case — that case passes. The failing case is
inconsistent with it. Matches the `STATUS.md` baseline note ("`Heroicon` enum
cannot stringify under `array_unique`").

**Fix direction (not applied):** compare `Heroicon` cases directly (or map to
`->value`) before `array_unique`. Pure test defect — no owner decision — but
remediation is out of WS-G scope.

---

## 2. NOT NULL constraint failures — 70 errors — app-bug (schema/contract drift)

Three distinct columns. All are `Illuminate\Database\QueryException:
SQLSTATE[23000] ... NOT NULL constraint failed`.

| Column | Errors | Migration | Test files |
|---|---|---|---|
| `stock_movements.unit_name_used` | ~60 | `2026_09_09_070750_create_stock_movements_table.php:21` | `InventoryServiceTest`, `NegotiationServiceTest`, `TransferRequisitionServiceTest`, `StockMovementIdempotencyKeyTest`, `DirectTransferTest` |
| `transfer_requisition_items.requested_base_qty` | ~8 | `2026_09_09_071135_create_transfer_requisition_items_table.php:24` | `InventoryServiceTest` |
| `direct_transfers.transferred_by` | 2 | `2026_09_24_000001_create_direct_transfers_table.php:18` | `DirectTransferTest` |

**Cause (observed):** the columns are declared `NOT NULL` (no default) in the
migrations. The failing service paths build the model without setting them —
`InventoryService` sets `unit_name_used` from `$item->approved_unit_name`
(lines 454/675) and `$line->unit_name` (329/343), but the test reach those paths
with the source attribute null.

**Why app-bug (not test-drift):** the factories DO populate the columns
(`StockMovementFactory` `'unit_name_used' => 'pc'`,
`TransferRequisitionItemFactory` `'requested_base_qty' => $qty`,
`DirectTransferFactory` `'transferred_by' => User::factory()`). The failures are
in **service** code paths (`dispatchTransfer()`, `directTransfer()`,
`recordMovement()`), which do not fall back to factory defaults. Schema-vs-service
contract gap, not a fixture gap.

**Boundary note:** whether the fix is (a) a service-side guard/default, (b) a
nullable-or-defaulted migration, or (c) tightening the callers is an owner
decision. `stock_movements.unit_name_used` alone is ~60 of 118 failures — highest
leverage — but any migration touching a NOT NULL column is a **destructive-change
candidate** and requires approval.

---

## 3. `UserFactory::admin()` / role-enum — 4 failures + 2 errors — mixed

**Files:** `tests/Unit/Listeners/Filament/Support/Concerns/ScopesNavigationBadgesTest.php`
(2e/2f), `tests/Feature/Filament/Resources/BadgeBearingResourceTest.php` (0e/4f —
one of which, `:635`, is this family).

**3a — empty-pivot premise — test-drift (already fixed once, elsewhere):**
`ScopesNavigationBadgesTest:113` `Failed asserting that 1 is identical to 0` and
`:92` array-mismatch share the **same root cause C-3 fixed in `BadgeScopeTest`** —
`UserFactory::admin()`'s `afterCreating` hook (`UserFactory.php:70-71`) attaches a
`Warehouse`, so the "empty pivot" premise is false. `BadgeBearingResourceTest:635`
fails identically. **Proven fix pattern:** `detach()` before the assert
(`BadgeScopeTest.php:92`).

**3b — unknown role enum — test-drift:**
`ScopesNavigationBadgesTest` "unknown role rather than widening":
`ValueError: "unregistered_role" is not a valid backing value for enum App\Enums\UserRole`
(`HasAttributes.php:1317` via `app/Models/User.php:80`,
`app/Filament/Support/Concerns/ScopesNavigationBadges.php:126`). The test injects a
role string the enum rejects before the resolver can no-op. Oracle must build the
user without the enum cast.

**3c — Gate resolves to instance not class-string — test-drift:**
`BadgeBearingResourceTest :136, :292, :411`, `BatchBResourceTest :81, :142, :202`,
`BatchCResourceTest :61`, `ProductsResourceTest :83` —
`Failed asserting that App\Policies\XPolicy Object #NNNN () is identical to
'App\Policies\XPolicy'`. Oracle uses `toBe(class-string)` where the Gate returns an
instantiated policy. `PolicyRegistrationTest` (29P) already hardened this oracle
elsewhere — same fix applies. Same weak-oracle class recorded in the `STATUS.md`
baseline.

---

## 4. `ReferenceCodeFactoryTest` — 12 errors — test-drift / app-model-drift

**Symptom:** `BadMethodCallException: Call to undefined method
App\Models\{TransferRequisition,PurchaseOrder,SalesOrder,DirectTransfer}::new()`
(lines 720–818, 12 cases).

**Cause:** the test calls a static `Model::new()` constructor that does not exist on
these models — either a helper the models never had, or removed by a refactor.
**Boundary:** if `::new()` is an intended model API this is app-drift (missing
method); if not, the oracle is wrong. Owner call.

---

## 5. `NotificationContractTest` — 14 errors — test-drift

**File:** `tests/Unit/Notifications/NotificationContractTest.php`.

**5a — TypeError int-vs-string (7):** `:164` `Argument #2 ($id) must be of type int,
string given` (dataset "TransferConfirmed" etc.). Test passes the reference-code
**string** where the closure declares `int $id`.

**5b — DatasetArgumentsMismatch (7):** `Pest\Exceptions\DatasetArgumentsMismatch`
lines 1833–1851 — the Pest flat-vs-array-of-arrays dataset bug (same family as
C-4): test expects N args, dataset provides M.

Both pure oracle wiring. No app dependency.

---

## 6. `actingAsStaff()` helper missing — 3 errors — test-drift

**Files:** `tests/Unit/Services/InventoryServiceTest.php:291`,
`tests/Unit/Services/NegotiationServiceTest.php`,
`tests/Unit/Services/TransferRequisitionServiceTest.php`.

`Error: Call to undefined function actingAsStaff()`. Recorded in the `STATUS.md`
baseline. Helper referenced but not defined / not autoloaded.

---

## 7. `UniqueConstraintViolationException` — 2 errors — factory/observer drift

**Files:** `tests/Unit/Factories/CatalogFactoryTest.php`,
`tests/Unit/Models/ProductVariantUnitConversionTest.php`.
`product_variant_unit_conversions.product_variant_id` unique collision — factory or
observer generates a duplicate. Test-drift or genuine observer bug; needs per-case
inspection. **Boundary-lite.**

---

## 8. Notification recipient scope — 7 failures — mixed

**File:** `tests/Unit/Listeners/ListenerRecipientScopeTest.php` (0e/7f).

**Symptom:** `NotificationFake: The unexpected [App\Notifications\XNotification]
notification was sent. Failed asserting that actual size 1 matches expected size 0.`
at `:226, :253, :307, :373, :401, :429` (+1).

**Cause:** the "does not notify auditors even when warehouse-assigned" cases expect
**0** but one is sent. **Same family as 3a** — `UserFactory::admin()/auditor()`
attach a warehouse, so an actor/recipient is in scope when the test assumed not.
**Boundary:** confirm whether the intended rule is "auditors are never notified for
this event" (then app-bug in the listener) or "auditor fixture is contaminated"
(test-drift). Given 3a's proven factory cause, **test-drift is the leading
hypothesis**, but a listener-side scoping bug is not excluded by the evidence alone.

---

## 9. `Carbon` vs `CarbonImmutable` — 2 failures — test-drift

**File:** `tests/Unit/Models/InTransitTest.php:73, :81`.
`Failed asserting that an instance of class Carbon\CarbonImmutable is an instance of
class Illuminate\Support\Carbon`. Model casts return `CarbonImmutable`; oracle
asserts `Illuminate\Support\Carbon`. Oracle drift.

---

## 10. Translation key returns its own name — 1 failure — boundary (real defect)

**File:** `tests/Feature/ProductsResourceTest.php:194`.
`Expecting 'resources.products.fields.name' not to be 'resources.products.fields.name'`.
The `products` translation namespace is missing `fields.name`. **A real i18n gap**,
same class as the baseline "i18n keys returning their own name" note — a product
defect, not a broken oracle. Sibling translation-key sweeps in
`BadgeBearingResourceTest` / `BatchBResourceTest` **pass**, so the gap is specific
to `products`.

---

## 11. Remaining single failures (factory value-asserts, model asserts)

One failure each, `ExpectationFailedException`, not root-caused from JUnit alone:
`OperationsFactoryTest`, `SalesFactoryTest`, `LossLedgerTest`,
`ProductVariantPriceTest`, `PurchaseOrderTest`, `SalesOrderTest`,
`TransferRequisitionItemRevisionTest`, `TransferRequisitionTest`;
`PurchasingFactoryTest` (2). Each needs the per-case assertion line.
**Boundary-lite** — enumerated, not diagnosed.

---

## 12. `ConcurrencyTest` — 1 error — app-bug (candidate)

**File:** `tests/Feature/Concurrency/ConcurrencyTest.php`.
`App\Exceptions\InvalidDocumentStateException` raised under the concurrent path.
Distinct from the DB-noise clusters; likely a genuine state-machine race or a guard
firing where the test expected success. **Needs inspection** — listed, not diagnosed.

---

## Net delta (STEP 6)

| Metric | Pre-P2.5 | Post-change | Delta |
|---|---|---|---|
| Passed | 1929 | 1932 | **+3** |
| Failed | 120 | 118 | **−2** |
| Skipped | 3 | 3 | 0 |

Retired failures: `BadgeScopeTest:92` (C-3), `DashboardWidgetsTest` dataset
mechanics (C-4). No failure silently absorbed — the count moved only by the two
fixes applied in the C-2/C-3/C-4 workstream.

---

## Boundary escalation summary (STEP 7)

Items that **cannot** be resolved without an owner decision, ranked:

1. **`stock_movements.unit_name_used` NOT NULL (~60 failures).** Highest leverage.
   Any fix likely touches a migration → **destructive-change gate**.
2. **`transfer_requisition_items.requested_base_qty` NOT NULL (~8) +
   `direct_transfers.transferred_by` NOT NULL (2).** Same schema-vs-service gap as #1.
3. **`ReferenceCodeFactoryTest` `Model::new()` (12).** Decide: missing model API
   (app-drift) or wrong oracle (test-drift).
4. **`ListenerRecipientScopeTest` audience rule (7).** Decide intended recipient
   policy before blaming fixture or listener.
5. **`resources.products.fields.name` translation (1).** Real missing-translation
   defect; needs the string authored.
6. **`ConcurrencyTest` `InvalidDocumentStateException` (1).** Needs inspection; may
   be a real state-machine defect.

Remaining clusters (1, 5, 6, 9, 11) are test-oracle drift — mechanically fixable
without a product decision, but **out of WS-G scope per the standing "no post-triage
fixes" instruction.**

**Prior-step blockers still standing:**

- **STEP 2** — `git rm` of the 13 confirmed-dead files remains **BLOCKED** by the
  Destructive Change Gate (classifier denied). The atomic C-10 strip of the two
  `RevisionsForm.php` test-exclusion lines (`FormSyntaxTest.php:48`,
  `FormFieldSpanTest.php:32`) is correctly **held with the deletion** — stripping
  while the file lives would break those suites.
- **Pint** on dirty files not re-run this session (shell classifier timeouts).
