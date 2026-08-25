---
name: plan-council-code-test
description: Use for any non-trivial coding task in this project — a plan is written first, reviewed by a multi-perspective council before any code is written, then implemented and verified against the FULL test suite (backend and frontend) plus a visual check (Playwright) and design audit for UI changes, then cleaned up and committed, before moving to the next task. Triggers on requests to implement, build, add, fix, or change functionality in the codebase. Works for Laravel backend with any frontend (FilamentPHP, Livewire, Angular, React, etc.).
---

# Plan → Council → Code → Test → Commit Loop

Follow these steps in order, for one scoped task at a time. Do not skip a step because the task looks small — the loop exists specifically to catch the mistakes that "obviously fine" changes tend to hide.

A task should be small enough to plan, review, code, and test in one pass (roughly 2–15 minutes of focused work). If a request is bigger than that, break it into multiple tasks and run the whole loop once per task.

---

## Step 0 — Check for Déjà Vu

Before writing a plan, check whether this decision has already been made. Search `docs/00-project/architecture-decisions/` and `docs/00-project/plans/` for prior work touching the same area, and re-read the relevant sections of `CLAUDE.md`.

- If a prior ADR or plan already settled this question, **use that decision** — reference it in Step 1 rather than re-deriving or re-litigating it from scratch.
- If the council already denied a similar approach before, don't propose it again without addressing why it was denied last time.
- Only re-open a settled decision if something has genuinely changed (new requirement, discovered problem) — and say explicitly what changed and why the old decision no longer holds.

This step exists because each task runs in a fresh, isolated subagent context by design (see the "don't overwhelm Claude" reasoning behind this whole loop) — that isolation is a feature for keeping context small, but without this check it also means settled decisions can get silently re-derived, re-argued, or contradicted by a later task that never saw the earlier one.

---

## Step 1 — Plan

If this task touches an area of the codebase you haven't recently worked in — or you're diagnosing a bug that seems to span multiple files — query the Graphify knowledge graph first (`/graphify query`, `/graphify path`, `/graphify explain`) instead of reading through files one at a time to build up an understanding. Graphify's graph already has the structural relationships (call graphs, "god nodes," cross-file connections) extracted at a fraction of the token cost of reading the raw files. If `graphify-out/` doesn't exist yet or looks stale relative to recent changes, run `/graphify .` to (re)build it before relying on it. This isn't a substitute for reading the specific files you're about to change — it's for understanding how the area fits together *before* you commit to a plan.

**Ultrathink and brainstorm before settling on an approach.** Don't write down the first plan that comes to mind — actually think through the problem space (ultrathink) and consider more than one way this task could be implemented (brainstorm) before committing to the plan you write down. This matters most for anything with a real design decision buried in it (a new pattern, a tricky edge case, more than one reasonable way to structure something) — a task that's genuinely mechanical doesn't need much of this, but don't skip it just because a task *looks* simple at first glance.

Write a short implementation plan for this one task:
- What will change, and why.
- Exact files to be created/modified.
- Expected behavior after the change.
- Anything the plan deliberately does *not* cover (to keep scope tight).

Do not write any code in this step.

---

## Step 2 — Council Review

Before any code is written, spawn 2–3 reviewer subagents in parallel to review the plan from distinct perspectives relevant to this project, for example:

- **Architecture** — does this fit the existing structure (single-restaurant, role-gated Admin Panel, derived-stock model, etc.)? Any better approach?
- **Security / Access Control** — does this respect role-based restrictions (admin/cashier/kitchen_staff)? Any risk of a route or query being reachable by a role that shouldn't have it?
- **Testing / Ops** — is this actually testable as planned? Any edge cases, race conditions, or migration risks?

Each reviewer returns a clear verdict: **APPROVE** or **DENY**, with specific reasons.

- If **any** reviewer denies → go to Step 2a.
- If **all** reviewers approve → save the approved plan to `docs/00-project/plans/` (see Step 2b), then go to Step 3.

Do not proceed to code with an unresolved objection, even a minor one — fold it into the plan or explicitly note why it's being overruled and get explicit user sign-off before continuing.

### Step 2a — Revise

Update the plan to address the denying reviewer's feedback. Return to Step 2 for re-review with the revised plan. Repeat until the council approves.

### Step 2b — Save the Approved Plan

Once the council approves, write the final plan to `docs/00-project/plans/` before writing any code. Name the file after the current prompt number and task, e.g. `03-02-inventory-transactions-migration.md`. Include:

- The plan itself, as approved (not an earlier draft that was denied and revised).
- A short council summary: which perspectives reviewed it, and any notable feedback that shaped the final version — even feedback that was addressed rather than causing an outright DENY.

This is what's actually built, and should read as a record of that — if the implementation in Step 3 has to deviate from this plan for a real reason, treat that as returning to Step 2, and update this file to reflect the revised, re-approved plan rather than leaving a stale record.

---

## Step 3 — Code

Implement exactly the approved plan — nothing more, nothing speculative. One subagent per task, working in its own isolated context so it only has what this task needs.

If, while coding, something requires deviating from the approved plan, stop and flag it rather than silently improvising — a meaningful deviation should go back through Step 2.

---

## Step 4 — Test

Run the full test suite for the project. This includes:

- **Backend (Laravel)**: use **Pest** (or PHPUnit) to run the entire backend test suite: `php artisan test`.
- **Frontend**: run the project's frontend test runner(s) as appropriate:
  - For **Livewire** components: use Pest's Livewire testing helpers (`livewire()->test(...)`) or the built-in Laravel HTTP tests for endpoints that render Livewire.
  - For **Angular**: use Karma/Jasmine or Jest to run unit/component tests.
  - For **FilamentPHP**: use Pest (or PHPUnit) for backend logic and Livewire testing for Filament resources/forms.
  - For **React/Vue**: use the project's chosen test runner (e.g., Vitest, Jest) with the respective testing library.
- **UI/End-to-end tests**: the project uses **Pest v4's built-in browser testing** (which leverages Laravel Dusk under the hood but with a simpler `visit()` API) for user-facing flows. Run any relevant browser tests: `php artisan test --testsuite=Browser` (or the specific test files in `tests/Browser/`). These tests render real HTML and work regardless of the frontend stack because they interact through the browser.

**Important**: Run the **entire** test suite each time — not just the tests directly related to the task. This catches regressions early.

- `php artisan test` — full backend suite.
- frontend test command (e.g., `npm run test` for Angular/React, or `php artisan test` if using Pest for Livewire tests) — run if the task touched frontend code.

**Design check (UI-touching tasks only):** functional correctness (tests passing) is necessary but not sufficient for a UI task — it doesn't catch generic, templated-looking design, and it doesn't catch a page that's functionally wired correctly but visually broken. For any task that adds or changes a page/component's visual design, once the browser test confirms it renders and functions correctly:

1. **Use the Playwright browser skill** to actually navigate to the affected page and take a screenshot — confirm what's rendering matches what was intended, rather than inferring it from the code you just wrote. This is especially important for any UI work where "the code looks right" and "it actually renders right" have turned out to diverge before.
2. **Run `/impeccable audit`** (or `/impeccable critique` for more open-ended feedback) against the same page, once `PRODUCT.md` exists.

If the Playwright screenshot and the Impeccable audit disagree (one looks fine, the other flags something), investigate the disagreement specifically rather than averaging it into "probably fine" — they check different things: Playwright confirms something is actually rendering as coded; Impeccable judges whether the result looks good. Treat flagged issues from either as equivalent to a failing test — see Step 5.

Skip this whole check for purely backend tasks or non-visual logic changes.

**Both the pre-existing tests and any new tests written for this task must pass, across all relevant suites.** A green result on new tests alone is not sufficient — this task is only done when everything relevant is green, confirming nothing else regressed.

- If **any** test fails (old or new, any suite), or the Playwright screenshot doesn't match intent, or Impeccable flags unresolved design issues → go to Step 5.
- If all tests pass and the visual/design check is clean → go to Step 6.

---

## Step 5 — Fix

Diagnose the root cause of the failure (don't just patch the symptom) and fix it. For design/rendering issues flagged by Playwright or Impeccable specifically, prefer composition-level fixes (CSS/Tailwind classes, layout structure, which UI primitives you combine and how) over deep hacks into third-party UI libraries. If a Playwright screenshot revealed something isn't rendering as coded (not just a style preference), diagnose why before assuming a styling tweak will fix it — that gap is often a wiring problem, not a CSS problem. Return to Step 4 and re-run everything again — the full test suite(s) and, for UI tasks, the Playwright screenshot + Impeccable check — not just the previously failing item, since a fix can introduce a new regression elsewhere. Repeat until everything is green and clean across all tests and checks.

---

## Step 6 — Next

Report a short summary: files changed, what was implemented, tests added, and confirmation the full suite passes. Mark this task complete.

---

## Step 7 — Cleanup & Commit

Before moving to the next task:

- **Cleanup**: remove any scratch files, debug output, commented-out old code, and unused imports introduced while working the task. Run `vendor/bin/pint --format agent` on changed PHP files and the project's frontend linter (e.g., `npm run lint -- --fix` for JS/TS projects, or equivalent) on changed frontend files. Re-run the full suite(s) once more after cleanup — a cleanup pass can break something too, so it isn't exempt from Step 4's rule.
- **Commit**: stage only the files belonging to this task and commit with a clear, scoped message describing what changed and why (not "wip" or "fixes"). One commit per task, not one giant commit at the end of the prompt file.
- Do not commit if the full suite isn't green, or if the approved plan (`docs/00-project/plans/...`) doesn't match what was actually built — fix that first.

Once committed, return to Step 0 for the next task.

---

## Notes

- The council in Step 2 is reviewing the *plan*, not the code — it should never see or evaluate an implementation, since none exists yet at that point.
- "All tests" in Step 4 means the whole project's test suites, every time, not an incremental subset — this is what catches regressions early instead of at the end of a long session.
- If a task can't be scoped small enough to fit this loop cleanly, that's a signal to split the plan further, not to skip steps.
- Every approved plan lives in `docs/00-project/plans/` (Step 2b) — this is what makes the loop auditable after the fact: anyone can see what was actually approved and why, without having to reconstruct it from commit history or chat logs.
- Step 0 and Step 2b work together deliberately: Step 2b is what makes the record exist, Step 0 is what makes sure it actually gets consulted next time. Skipping Step 0 defeats the point of writing plans down in the first place.
- Step 7's one-commit-per-task discipline mirrors the rest of the loop: a task small enough to plan, review, code, and test in one pass is also small enough to review as a single, coherent commit. Don't batch several tasks' changes into one commit just to save time — that undoes the traceability Steps 2b and 7 are both building toward.