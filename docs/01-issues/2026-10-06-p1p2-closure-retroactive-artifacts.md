# P1/P2 Closure — Retroactive Artifacts (v13.8)

- **Date:** 2026-10-06 (verification pass by MANAGER-DOCS workstream: 2026-10-07 00:18)
- **Repo:** `D:\Personal\filament-inventory`
- **Branch:** `p1-contract-closure`
- **Scope:** Retroactively record closure findings for the P1 contract-violation cluster, P0 STN runtime cluster, and P2 functional-alignment cluster (findings C-1 … C-12, D-1 … D-4, WS-F). This document is the durable record for work that otherwise lived only in `STATUS.md` / `UPDATES.md` headlines and session handoffs.
- **Mode:** Docs-only workstream. No code, test, blueprint, or lang files modified.
- **Uncommit status:** All P1/P2 work remains **uncommitted** on branch `p1-contract-closure`. No commit authorized.

## Evidence discipline

Every "CONFIRMED" row below was re-verified on disk during the 2026-10-07 pass using `Grep` / `Glob` / `sed` / `wc` / `php -l`-free reads (fresh process, so RTK-render artifacts that mangled earlier reads did not apply). Nothing is marked confirmed from a prior document alone.

Baseline full-suite figure (**1929 passed / 120 failed / 3 skipped / 4456 assertions, ~72.6s**) is **relayed/measured by the manager**, NOT re-run by this docs workstream. Label: **Recorded measured baseline**.

## C-8 — Canonical baseline adopted

- Retired the stale scoped claim "121F / 1928P".
- **Canonical baseline:** full suite = **1929 passed / 120 failed / 3 skipped / 4456 assertions (~72.6s)**.
- The 120 failures are pre-existing, out of P1/P2 scope. Sampled causes: missing test helper `actingAsStaff()`; `Filament\Support\Icons\Heroicon` enum cannot stringify under `array_unique`; `stock_movements.unit_name_used` NOT NULL; i18n keys returning their own name; weak `toBe(class-string)` oracles.
- Gate policy: targeted regression suites are the P1/P2 signal; the full suite is not a usable pass/fail gate yet.

## Closed-session findings

| ID | Finding | Status | On-disk evidence (2026-10-07) |
|---|---|---|---|
| C-3 | `BadgeScopeTest:92` weak oracle → §1B.1a | **CONFIRMED** | `BadgeScopeTest.php:87` test renamed "grants full system badge count an admin with empty warehouse pivot"; comment `§1B.1a: admin badge authority global, independent of pivot`; assertion `expect($admin->warehouses()->count())->toBe(0)`. |
| C-4 | `DashboardWidgetsTest` flat datasets → array-of-arrays | **CONFIRMED** | every `->with([...])` in the file passes argument-arrays (e.g. `[StatsOverviewWidget::class, 1]`, `[QuickActionsWidget::class, ['default' => 1, 'md' => 2, 'xl' => 4]]`). |
| C-6 / C-7 / C-10 / C-12 / D-3 | blueprint v13.8 P1 contract-closure note | **CONFIRMED** | `docs/00-project/blueprint.md` contains `### v13.8 P1 Contract-Closure Note` documenting provider collapse, `STNManifestController` rename, `toBeInstanceOf` oracle fix, §21.1 `<?php`/`declare(strict_types=1);` headers. |
| C-2 | `lang/es/attributes.php` ↔ `lang/tl/attributes.php` parity | **CONFIRMED** | key-set identical: `diff` of extracted keys → `KEYSET-IDENTICAL`; 66 `=>` entries each. (Cross-locale inconsistency with emptied `en` remains open — see Open items.) |
| C-11 | WS-F prior-workstream routes/views/Policies verified on disk | **CONFIRMED** | `routes/web.php` (27 lines) declares `stn.print` (auth) + `stn.scan` (auth+signed); `app/Http/Controllers/STNManifestController.php` (94 lines, class `STNManifestController`); `resources/views/stn/print.blade.php` (43), `resources/views/stn/scan.blade.php` (10), `resources/views/livewire/stn/scan-form.blade.php` (25), `resources/views/components/layouts/app.blade.php` (15), `app/Livewire/Stn/ScanForm.php` (70), `tests/Feature/Stn/StnScanFlowTest.php`; `PolicyRegistrationTest.php` present (5 `it(...)` blocks → 29 tests). |
| D-1 | 4 orphan artifacts deleted | **PENDING — NOT CONFIRMED** | still on disk: `app/Filament/Resources/Suppliers/Schemas/SupplierInfolist.php`, `app/Filament/Resources/Suppliers/Pages/ViewSupplier.php`, `app/Filament/Resources/Customers/Schemas/CustomerInfolist.php`, `app/Filament/Resources/Customers/Pages/ViewCustomer.php`. |
| D-2 | `resources/views/scan-receive/show.blade.php` deleted | **PENDING — NOT CONFIRMED** | still on disk (282 lines, `M` in git). |
| C-1 | `UserRoleSeeder` / `DemoWorkflowSeeder` deleted | **PENDING — NOT CONFIRMED** | both still on disk: `database/seeders/UserRoleSeeder.php`, `database/seeders/DemoWorkflowSeeder.php`; neither is invoked from `DatabaseSeeder.php`. |
| C-9 | dead extras deleted | **PENDING — NOT CONFIRMED** | still on disk: `app/Traits/DashboardFilterable.php`, `app/Traits/StockActions.php`, `app/Casts/DummyCast.php`, `app/Console/Commands/Welcome.php`. |

## Verified-green regression suites (relayed)

`RouteWiringTest` 7P · `ResourceCompletenessTest` 43P · `PolicyRegistrationTest` 29P · `BadgeScopeTest` 1F/31P · `StnScanFlowTest` 7P.

## Governing decisions (relayed, retained)

- **D1** — wire all 9 widgets explicitly via `AdminPanelProvider->widgets([9 FQCN])`, not `discoverWidgets()` (blueprint §17.5 declares the explicit list; `discoverWidgets()` intentionally absent).
- **D2** — 4 orphan infolist/view artifacts are unreachable and absent from blueprint §25; deletion is a destructive change → owner approval required.
- **D3** — `UserRoleSeeder` comment-only fix; `lang/en/attributes.php` emptied; `QuickActionsWidget::$view` wired **non-static** per `vendor/filament/widgets/src/Widget.php:22`.

## Open items / blockers

1. **D-1 / D-2 destructive delete** — 4 orphan artifacts + `scan-receive/show.blade.php`. Grep: zero live callsites. Blocked by Destructive Change Gate. Needs owner approval.
2. **C-1** — `UserRoleSeeder` / `DemoWorkflowSeeder` unused; delete or wire into `DatabaseSeeder`. Owner decision.
3. **C-9** — dead extras (`DashboardFilterable`, `StockActions`, `DummyCast`, `Welcome`). Owner decision.
4. **C-2 remainder** — `lang/es` + `lang/tl` `attributes.php` still hold 66 keys each while `lang/en` is empty. Cross-locale inconsistency; out of P2-11 scope. Owner decision.
5. **Tooling** — Pint on dirty files NOT re-run this session; `claude-combo1` classifier timed out intermittently, blocking Bash calls and subagent spawns. Subagents self-reported their own files clean.
6. **120 pre-existing full-suite failures** — separate initiative.
7. **`QuickActionsWidget` `$sort = 9`** — renders last regardless of array order. Owner decision.

## Standing rules (retained)

- Report to Jarvis: update `STATUS.md`, append ONE line to `UPDATES.md` (newest at bottom, never delete lines); headlines carry no sensitive data.
- Only Alvin approves durable rules; do not let ECC continuous-learning turn observations into rules.
- Never commit/push unless the approved plan says so; work on a branch, never `main`.
- Do not use ECC chief-of-staff / email-ops / messages-ops / outbound skills.

---

## C-5 Retroactive Artifacts (2026-10-08)

Added during final-consolidation session on branch `p1-contract-closure`.
**Uncommitted** — O-2 hold honored. Evidence from fresh process (2026-10-08),
not relayed.

### 7a — STNManifestController §21.3 compliance

**Spec:** `docs/00-project/blueprint.md:18111` §21.3 Controller Contract.
**Impl:** `app/Http/Controllers/STNManifestController.php` (97 lines).

| §21.3 requirement | On-disk | OK |
|---|---|---|
| `print()` authorizes `view` | `Gate::authorize('view', $transferRequisition)` | ✅ |
| `print()` eager-loads fromWarehouse, toWarehouse, items.productVariant | `loadMissing([...])` (F28) | ✅ |
| `print()` renders `stn.print` | `view('stn.print', [...])` | ✅ |
| `scan()` authorizes `receive` | `Gate::authorize('receive', $transferRequisition)` (line 79) | ✅ |
| `scan()` eager-loads fromWarehouse, toWarehouse | `loadMissing(['fromWarehouse','toWarehouse'])` (81–84) | ✅ |
| `scan()` rejects non-receivable status 403 | `! in_array(status, [Dispatched, PartiallyReceived], true)` → `abort(403)` (86–90) | ✅ |
| `scan()` relies on `auth` + `signed` middleware | `routes/web.php:24` `Route::middleware(['auth','signed'])` | ✅ |
| renders `stn.scan` | `view('stn.scan', [...])` (93–95) | ✅ |
| never creates StockMovement/LossLedger/InTransit directly | no such model writes in controller body | ✅ |

**Deviation (observation, not fix — out of C-5 scope):** class is declared
`StnManifestController` (file:34) but the blueprint block (§21.3 line 18132)
and `routes/web.php:5,11,25` reference `STNManifestController`. PHP class
resolution is case-insensitive so routing works; this is a PSR-4 casing
mismatch. Candidate follow-up, owner decision.

### 7b — P0 STN file line counts (2026-10-08)

| File | Lines |
|---|---|
| `routes/web.php` | 27 |
| `app/Http/Controllers/STNManifestController.php` | 97 |
| `resources/views/stn/print.blade.php` | 43 |
| `resources/views/stn/scan.blade.php` | 10 |
| `resources/views/livewire/stn/scan-form.blade.php` | 25 |
| `resources/views/components/layouts/app.blade.php` | 15 |
| `app/Livewire/Stn/ScanForm.php` | 70 |
| `tests/Feature/Stn/StnScanFlowTest.php` | 177 |

### 7c — PolicyRegistrationTest (WS-B) 29P assertion breakdown

**File:** `tests/Feature/Architecture/PolicyRegistrationTest.php` (93 lines).
**Result (2026-10-08):** **29 passed / 41 assertions.**

| Block | Count | Assertions |
|---|---|---|
| Gate map — explicit model→policy pair (Product … DirectTransfer) | 13 | 26 |
| Gate resolution — `getPolicyFor` instance check (same 13 models) | 13 | 13 |
| Contract extras (removes `StockMovementPolicy::createDirectTransfer`; declares `DirectTransferPolicy::create`; registers §8 model in explicit Gate map) | 3 | 2 |
| **Total** | **29** | **41** |

13 models × 2 shapes (map + resolution) = 26; + 3 contract extras = 29.

### 7d — §25 reverse-rule verification (C-4)

**Rule:** `blueprint.md:19546` — any file under a §25-covered directory
(`app/`, `database/`, `resources/`, `lang/`, `routes/`, `bootstrap/`, `tests/`)
absent from the §25 map must be declared or deleted.

**Method:** extracted the §25 fenced tree (`blueprint.md:19172`–`19546`,
260 declared file entries, `xxxx_xx_xx_` placeholders for migration timestamps,
directory-level declarations for `tests/` and `<locale>/`), walked the seven
covered dirs, diffed.

**Raw diff:** 416 files on disk, 260 declared. Naive set-diff → 188 extras /
32 declared-but-missing. **The raw numbers overstate violations** because §25
declares `tests/` and `<locale>/` at directory granularity: 90 test files +
24 `lang/{es,tl}` files are covered by the `tests/Feature`, `tests/Unit`,
`tests/Browser`, and `<locale> same file set as en/` tree nodes — **not**
violations. The 32 "declared-but-missing" are all parser artifacts (the
`database/` root's trailing `blade`/`css` nodes mis-attributed, and
`xxxx_xx_xx_` placeholders) — **no genuine declared-file-absent case.**

**Genuine undeclared app-scope files (load-bearing — liveness confirmed):**

| File | Live ref | §25 |
|---|---|---|
| `app/Filament/Pages/Dashboard.php` | registered `AdminPanelProvider:91` `->pages([...])` | **absent** |
| `app/Filament/Pages/Auth/Login.php` | registered `AdminPanelProvider:36` `->login(Login::class)` | **absent** |
| `app/Filament/Resources/Users/Schemas/UserInfolist.php` | referenced (1) | **absent** |
| `app/Filament/Resources/Users/Pages/ViewUser.php` | referenced (1) | **absent** |
| `app/Livewire/Warehouses/AssignedUsersList.php` | referenced (1) | **absent** |
| `app/Http/Middleware/ApplySecurityHeaders.php` | registered (1) | **absent** |
| `app/Filament/Exports/ProductExporter.php` | **0 live refs** — candidate dead | **absent** |

**Genuine undeclared views (25):** `resources/views/stn/{print,scan}.blade.php`
(the §21 STN views — load-bearing), `resources/views/components/layouts/app.blade.php`
(declared path differs: §25 declares it under `components/layouts/` but the
parser also flagged it — verify), widget blades
(`stats-overview`, `stock-by-warehouse`, `warehouse-capacity`, `warehouse-filter`,
`low-stock-alerts`, `recent-movements`, `chart-data-tables/*`), wizard blades,
`pdf/*`, `transfer-notes/show`, `welcome`, `filament/pages/stock-adjustment`,
`filament/dashboard-sections/section-header`.

**Genuine undeclared infra (5):** `bootstrap/app.php`, `routes/api.php`,
`routes/console.php`, `bootstrap/cache/{packages,services}.php` (framework
scaffold — `cache/*` is generated, arguably out of scope).

**Genuine undeclared lang (4):** `lang/en/{auth,direct_transfers,pagination,passwords}.php`
— `auth`/`pagination`/`passwords` are Laravel scaffold; `direct_transfers.php`
is project-authored.

**Classification:** these are **blueprint-drift**, not code defects — the files
are live and correct; §25 under-declares them. Per the reverse rule the fix is
to **declare** them in §25 (docs-only, non-destructive) or delete the truly-dead
ones. `ProductExporter` (0 refs) is the sole delete candidate. **Owner decision
required** before any blueprint §25 edit or file deletion — out of C-4
verification scope (verification only, no remediation).

**Honest residual:** reverse-rule verification is **OBSERVED, not closed** —
≈7 app files + 25 views + 4 lang files remain undeclared in §25. No §25 edit
made this session.

*End retro-artifacts record.*
