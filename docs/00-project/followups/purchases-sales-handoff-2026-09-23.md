# Purchases / Sales Handoff — 2026-09-23

Date: 2026-09-23
Lane: feat/purchases-sales + feat/purchases-sales-followup
Spec: docs/00-project/blueprint-addendum-purchases-sales.md (Addendum v11.1)
Contract: armada/REQUIREMENTS-purchases-sales.md (APPROVED)
Stack: Laravel 13 + Filament v5

## 1. Goal

Implement / reconcile / verify Purchases and Sales Addendum v11.1
(spec: docs/00-project/blueprint-addendum-purchases-sales.md)
on the Laravel 13 + Filament v5 stack.

Scope of the addendum:

- PurchaseOrder and SalesOrder resources, schemas, policies, tests.
- AdminReviewFilters + period / warehouse gates on both order tables.
- A8 consolidation across 25 sites (22 done, 3 NEEDS-RULING).
- 5 new viewAdminReview policy methods.
- GuardsOutstandingQuantity / PolicyAudit / concurrency-marker tests.
- Grid-namespace fix.
- Full en / es / tl i18n parity.
- Phase 0 audit + completion report docs.

## 2. Key discovery

Implementation already existed in-history (commits 8f8563f + 32a0218).
Voyage premise flipped from greenfield to reconcile-and-verify.

Contract separation (do not conflate):

- Separate contract: armada/REQUIREMENTS-purchases-sales.md (APPROVED).
  This is the purchases-sales lane contract.
- v11.0 lane: armada/REQUIREMENTS.md + armada/state/active.json left untouched.
  Do not modify the v11.0 lane files for purchases-sales work.

## 3. Decisions by owner

- Proceed with reconcile (not rebuild).
- Playwright fully deferred (suite broken, fix later;
  purchases-sales.spec.ts never written).
- Proceed with PRs.
- Merge done by owner (agent does not merge).

## 4. Done

Lane branch feat/purchases-sales:

- 1b39d2a reconcile.
- 958d85d auth cleanup.
- PR #2 opened from feat/purchases-sales.
- PR #2 merged as befa082.

Followup branch feat/purchases-sales-followup, PR #3 (owner merging):

- AdminReviewFilters + period / warehouse gates on both order tables.
- A8 consolidation 22/25 sites with 5 new viewAdminReview policy methods.
- GuardsOutstandingQuantity / PolicyAudit / concurrency-marker tests.
- Grid-namespace fix.
- Full en / es / tl i18n parity.
- Phase 0 audit + completion report docs.

Pest results:

- Lane: 1478 passed / 0 failed.
- Final: 1514 passed / 0 failed / 7 skipped + 1 risky (pre-existing markers).

## 5. Open

Merge / git:

- PR #3 merge (owner action) + post-merge git pull on local master.
- TODO.md line still open (one line item remains).
- Delete remote feat branches after merge
  (feat/purchases-sales, feat/purchases-sales-followup).

5 undecided questions (need owner ruling, none approved yet):

1. Ledger mixed / split question.
2. setPrice composition question.
3. Transfer hardening follow-up question.
4. Filter extension to parent tables question.
5. Filter hardening question.

3 NEEDS-RULING consolidation sites (pinned by tests/Unit/PolicyAuditTest.php):

1. app/Filament/Resources/DirectTransferResource.php lines 35-43.
2. app/Filament/Resources/TransferRequisitionResource.php line 69.
3. app/Actions/StockActions.php line 245 /
   app/Concerns/DashboardFilterable.php line 26
   (counted as one joint site).

Testing:

- Playwright fix pass still required (suite broken; deferred by decision).
- purchases-sales.spec.ts was never written.

## 6. Environment notes

- No tmux on Windows (armada voyage ship cannot boot).
  WSL has tmux 3.4 but no PHP toolchain.
  Future voyage work must run inline via task subagents
  in the target worktree, never the main checkout.
- Subagent backends flaky: corvette, e2e-runner, explorer, caravel
  model mappings fail; general + clipper work reliably.
  Prefer general / clipper backends for dispatch.
- tests/playwright/.auth/user.json must never be committed
  (was committed once, then removed via git rm --cached).
  Keep it gitignored and local-only.

## 7. Resume steps

1. Pull master (post-merge): git pull origin master (or main, confirm remote default).
2. Confirm PR #3 merged + CI green on the merge commit.
3. Run vendor/bin/pest and confirm parity with final baseline
   (1514 passed / 0 failed / 7 skipped + 1 risky).
4. Then pick up either the Playwright fix pass or the 5 rulings /
   3 NEEDS-RULING sites, per owner priority.

Key refs:

- armada/REQUIREMENTS-purchases-sales.md
- docs/00-project/plans/purchases-sales-completion-report.md
- docs/00-project/plans/purchases-sales-phase0-audit.md
- tests/Unit/PolicyAuditTest.php (pins 2 ruling-pending files)
- docs/00-project/blueprint-addendum-purchases-sales.md (spec v11.1)
- TODO.md (one open line item)
