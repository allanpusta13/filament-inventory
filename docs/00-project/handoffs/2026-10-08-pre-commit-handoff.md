# Pre-Commit Handoff — P0–P2.5 Closure

## Date
2026-10-08

## Branch state
- Current branch: `p1-contract-closure` (historical name — contains P0–P2.5, not just P1)
- HEAD: `e9b51f1`
- Clean? **No** — working tree holds all P0–P2.5 work uncommitted (74 modified, 14 deleted, 13 legitimate untracked)

## What this branch contains
- **P0**: STN receipt cluster (5 defects closed) — `routes/web.php` `stn.print`/`stn.scan`, STN views, `InventoryService::scanToReceive()` reachable
- **P1**: Contract restoration (providers → `AppServiceProvider::registerPolicies()/registerEventListeners()`, `StnController` → `STNManifestController` rename, `PolicyRegistrationTest` oracle `toBe` → `toBeInstanceOf`, §21.1 PHP-block `<?php` + `declare(strict_types=1);` headers)
- **P2**: Functional alignment (9 widgets wired in `AdminPanelProvider`, `Dashboard::getWidgets()`, `UserResource` labels, 4 migrations `down()`, i18n `lang/en/attributes.php` emptied)
- **P2.5**: Council closure (13 dead-file deletions, `ProductExporter.php` deletion, blueprint §25 "Untracked Extras (Declared)", §27 v13.9 change table, `ScanForm.php` restore)
- **Blueprint**: v13.9 (bumped from v13.8)

## Suite status
- **2046P / 0F / 3S (4675 assertions)** — exact post-council-closure baseline

## Untracked files to be committed
- `app/Livewire/Stn/ScanForm.php`
- `database/migrations/2026_10_06_100000_add_cleared_at_to_in_transits_table.php`
- `database/migrations/2026_10_08_000001_make_direct_transfers_transferred_by_nullable.php`
- `docs/00-project/followups/2026-10-06-p2-cluster-closure-handoff.md`
- `docs/01-issues/2026-10-05-blueprint-s25-completeness-audit.md`
- `docs/01-issues/2026-10-06-p1p2-closure-retroactive-artifacts.md`
- `resources/views/components/layouts/app.blade.php`
- `resources/views/livewire/stn/scan-form.blade.php`
- `resources/views/stn/print.blade.php`
- `resources/views/stn/scan.blade.php`
- `tests/Feature/Architecture/DIAGNOSIS.md`
- `tests/Feature/Architecture/TRIAGE.md`
- `tests/Feature/Stn/StnScanFlowTest.php`

## Deleted files to be committed
1. `app/Casts/DummyCast.php`
2. `app/Console/Commands/Welcome.php`
3. `app/Filament/Exports/ProductExporter.php`
4. `app/Filament/Resources/Customers/Pages/ViewCustomer.php`
5. `app/Filament/Resources/Customers/Schemas/CustomerInfolist.php`
6. `app/Filament/Resources/Suppliers/Pages/ViewSupplier.php`
7. `app/Filament/Resources/Suppliers/Schemas/SupplierInfolist.php`
8. `app/Filament/Resources/TransferRequisitions/Schemas/RevisionsForm.php`
9. `app/Filament/Resources/TransferRequisitions/Tables/RevisionsTable.php`
10. `app/Traits/DashboardFilterable.php`
11. `app/Traits/StockActions.php`
12. `database/seeders/DemoWorkflowSeeder.php`
13. `database/seeders/UserRoleSeeder.php`
14. `resources/views/scan-receive/show.blade.php`

## Known risks
- Untracked files fragile (`ScanForm.php` loss proved it silent — never git-tracked, invisible to `git status`)
- Classifier gates `rm`/`git rm` in this environment (auto-mode denial)
- Branch name historical — commit message carries accurate narrative
- 3 AI brand-naming council docs (`docs/00-project/chatgpt.md`, `deepseek.md`, `gemini.md`) were present untracked; owner deleted them before commit — excluded from commit scope (unrelated to P0–P2.5 engineering)

## Next phase
- Phase 1: Cleanup (scratch files, verification)
- Phase 2: Commit (single coherent commit)
- Phase 3: Merge `master` (`--no-ff`); end on `master`

## Constraints
- No push. No rebase. No force-push. No history rewrite.
- Source branch preserved (no delete, no rename).
- Final branch: `master`.
