# Handoff: Filament `TextEntry::boolean()` fix + i18n catalogue completion

2026-10-05 · Fix a render-time crash in a Filament v5 infolist, then close the translation-key gap the prior blueprint handoff left open.

## Objective

1. Stop `GET /admin/purchase-orders/{id}` throwing `BadMethodCallException: Method Filament\Infolists\Components\TextEntry::boolean does not exist`, and purge the anti-pattern from the blueprint so it cannot be re-copied.
2. Add the F31 tooltip translation keys missing from `lang/en` (en catalogue only — es/tl deferred by owner).

Success = Pest `TranslationCompletenessTest` green (en keys resolve) + regression guard green + blueprint correct. **No test has actually been executed yet — see Blocked.**

## Current state

Repo: `D:\Personal\devswarm\repos\0\c1231b76\bugfix-textentry-boolean`, branch `bugfix/textentry-boolean` (source `master`). Working tree has 2 uncommitted files. Two commits landed this session:

- `ac89de5` — runtime fix bundle (4 files)
- `5281272` — blueprint amendment (1 file)

`vendor/` is NOT installed, so nothing under Pest has run. All verification so far is static (grep, `php -l`, standalone PHP scripts mirroring test logic).

## Decisions (and why)

- **TextEntry kept as TextEntry, not converted to IconEntry**: it sits in a labelled `Section` beside text rows; icon-only breaks visual consistency. (`WarehouseInfolist::is_active` is the canonical pattern to copy — `formatStateUsing()` + `->color()`.)
- **`common.yes`/`common.no` used, not `common.active`/`inactive`**: Yes/No toggle ≠ Active/Inactive status. Semantically wrong to reuse.
- **Regression guard = static source scan, not schema-walk**: `$component->hasAttribute('boolean')` / `getComponents()` could not be verified (no `vendor/`), so a deterministic line-based scanner was chosen instead.
- **es/tl NOT touched**: owner said "skip this for now, complete en first". They still lack all 5 keys; no test enforces cross-locale parity anyway (see below).
- **`actions.more` added to `lang/en/actions.php`**: no code references it, but blueprint §0A.2a declares it canonical. Added to keep en consistent with its own contract.

## Dead ends — do not retry

- **Regex `Text(Entry|Column)::make\([^;]*?->boolean\(\)` (multiline)**: `[^;]` spans newlines and swallows unrelated entries — flags `IconColumn` usages. Rewritten as a line-based chain parser (see Artifacts).
- **Embedding the literal `->boolean()` in an inline code comment**: the guard's own regex matched it, so the comment self-tripped the test. Comment rephrased to `boolean()` (no arrow) — do not reintroduce the literal in a `Text*` chain context.
- **Schema-walk regression guard**: rejected, unverifiable Filament v5 internals (no vendor).
- **Running Pest via sibling worktree `D:/Personal/filament-inventory`**: that worktree also has no `vendor/`. Dead end.

## Artifacts

- `app/Filament/Resources/PurchaseOrders/Schemas/PurchaseOrderInfolist.php` (lines 40-47): **final, committed `ac89de5`** — `update_cost_price` now `->badge()->formatStateUsing(fn (bool $state): string => $state ? __('common.yes') : __('common.no'))->color(fn (bool $state): string => $state ? 'success' : 'danger')`.
- `lang/en/common.php`: **final, committed `ac89de5`** — added `'yes' => 'Yes'`, `'no' => 'No'`.
- `tests/Feature/Architecture/TextBooleanScopeTest.php`: **final, committed `ac89de5`** — line-based scan; validated via standalone PHP mirror (flags broken TextEntry/TextColumn; spares IconEntry/IconColumn; no adjacent-entry conflation; ignores comments; real tree 0).
- `docs/00-project/ai/plans/approved/textentry-boolean-fix.md`: **final, committed `ac89de5`** — approved plan record; marks Pest as BLOCKED.
- `docs/00-project/blueprint.md`: **final, committed `5281272`** — §0A.2a (added yes/no), §7A.3 + §7G.4 (`badge()->boolean()` → working pattern), §7O.8 rule 11, §12 coverage list.
- `lang/en/actions.php`: **UNCOMMITTED** — added `'more' => 'More'`.
- `lang/en/resources.php`: **UNCOMMITTED** — added `'more_actions' => 'More actions'` to products, transfer_requisitions, purchase_orders, sales_orders `actions` arrays (4 sites).
- `app/Filament/Resources/Products/Schemas/ProductInfolist.php`: **deliberately NOT changed** — the prior brief claimed it was a carrier, but line 82 is `IconEntry`, where `->boolean()` is valid.

## Verbatim essentials

- Exact crash: `BadMethodCallException - Internal Server Error` / `Method Filament\Infolists\Components\TextEntry::boolean does not exist.`
- The 4 missing keys (call sites): `resources.products.actions.more_actions` (`ProductsTable.php:156`), `resources.transfer_requisitions.actions.more_actions` (`TransferRequisitionsTable.php:444`), `resources.purchase_orders.actions.more_actions` (`PurchaseOrdersTable.php:229`), `resources.sales_orders.actions.more_actions` (`SalesOrdersTable.php:303`).
- Canonical value from blueprint §0A.2a: `'more_actions' => 'More actions'`; also `'more' => 'More'`.
- Test that matters: `tests/Unit/Lang/TranslationCompletenessTest.php` — **en-only** audit. Walks `app/**`+`resources/**` for `__()`/`@lang`/`trans` literals, fails on any key absent from `lang/en`. It does NOT compare locales.
- `TranslationKeyParityTest` **does not exist** — only a "recommended test name" in blueprint §0A.15. Do not invoke it.
- Blueprint §0A.2a preamble: "Every `__()` key referenced by this blueprint MUST resolve."
- Env: `composer.lock` PRESENT; `phpunit.xml`, `.env.example` PRESENT; `vendor/` and `.env` ABSENT.

## Working preferences

- Ultra-terse caveman style; terse user-facing text; code/commits written normally.
- Lazy-senior / YAGNI stance: minimal diff, challenge unnecessary scope, deletion over addition.
- Evidence-honesty is a hard gate (project CLAUDE.md): never claim a test/command ran unless it did. State BLOCKED explicitly.
- A `GateGuard` hook requires presenting facts (importers, affected API, data schemas, verbatim instruction) before first Bash/Edit/Write on a path. Expect it on every new file.
- Commits: conventional format, scoped types (`fix(...)`, `docs(...)`).

## Open items

- **Next step**: run `composer install --no-interaction`, then `cp .env.example .env && php artisan key:generate`, then `./vendor/bin/pest --filter=TranslationCompleteness` and `--filter=TextBooleanScope`.
- **Then**: commit the two uncommitted en files (suggested `fix(i18n): add F31 tooltip keys to en catalogue`).
- **Blocked**: all test execution — `vendor/` absent AND the Bash safety classifier was returning "free-sonnet is temporarily unavailable" on every Bash call at handoff time. Unblocks when composer install can run.
- **Unresolved question** (not decided this thread, do not assume): whether/how to backfill es/tl for the 5 keys, and whether to create the `TranslationKeyParityTest` the blueprint claims exists.
- **Known pre-existing failure** (unrelated): `tests/Feature/Architecture/ResourceCompletenessTest.php:123-129` asserts `view()->exists('stn.print'|'stn.scan'|'livewire.stn.scan-form')`; those views never existed in any commit. Expect 3 failures in a full suite run; not caused by this work.

## Suggested opening prompt

`Read docs/03-daily-logs/2026-10-05-textentry-boolean-i18n-handoff.md, then run composer install and execute the Pest tests listed under Open items → Next step. Report actual results (pass/fail counts). The Bash classifier was down last session, so retry install if it errors.`
