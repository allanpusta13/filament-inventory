# The Vibe Coding Standard — Guideline
### Why the Vibe Coding Standard works, how to use it, and when flexibility is appropriate

This guideline explains the reasoning behind `docs/00-project/vibe-coding/standard.md`. The Standard defines **what and when**; this document explains **why and how to think**; `CLAUDE.md` defines **AI behavior and enforcement**.

---
# Vibe Coding Standard — Document Map

The standard consists of three synchronized artifacts:

1. `docs/00-project/vibe-coding/standard.md` — **WHAT + WHEN**
2. `docs/00-project/vibe-coding/guideline.md` — **WHY + HOW TO THINK**
3. `CLAUDE.md` — **AI CONTRACT + ENFORCEMENT**

Keep exactly one canonical copy of the Standard and Guideline.
Do not duplicate them elsewhere in the project.

---

## Standard AI Tooling

### Why Context7 is part of the standard

Version-sensitive dependency work is a high-risk source of plausible but incorrect implementation. Context7 provides current, version-specific library documentation to the AI coding workflow.

The rule is intentionally scoped: Context7 is required when the task depends on external framework/package/API documentation whose version matters. It does not replace repository inspection, tests, official source review, or the Council.

The agent should identify the installed or approved version and retrieve matching documentation where available. When documentation materially affects a decision, preserve the relevant source/version in the plan or research record.

### Why EnvKit is part of the standard

The local development environment is part of the agent's operating context. EnvKit provides a standardized PHP development stack and MCP interface for supported platforms, allowing environment inspection and controlled local operations to participate in the same workflow as code changes.

This does not mean unrestricted automation. The standard deliberately separates **safe observation** from **consequential mutation**:

- safe/read-only environment operations may be autonomous
- destructive or consequential operations require explicit approval unless already covered by an approved plan
- production and staging remain outside EnvKit's local authority

This boundary preserves the project's strict autonomy model while avoiding unnecessary approval prompts for diagnostics and routine inspection.

## AI Operating Directory

The AI operating directory exists to separate **AI working state** from the project's durable documentation library.

## Why separate AI plans from project plans?

The two plan directories serve different purposes:

- `docs/00-project/ai/plans/` is an execution workflow: the AI creates a plan, the user reviews it, approval gates implementation, and the approved plan becomes the execution record.
- `docs/00-project/plans/` is durable project documentation for the owner/reference.

This avoids turning the general documentation Vault into an AI control mechanism while still giving the AI a predictable place to maintain pending and approved implementation plans.

## Why require approval for non-trivial work?

Strict autonomy does not mean asking permission for every line of code. It means giving the agent autonomy over routine implementation while reserving material decisions for the project owner.

The Non-Trivial Work Gate catches the point where a task changes from:

**"implement the known requirement"**

to:

**"decide what the product, architecture, security model, data model, dependency set, or scope should be."**

That distinction preserves speed without allowing the AI to make silent high-impact decisions.

## Approval model

The plan is the review surface.

A good plan makes the following visible before implementation:

- objective
- scope
- governing requirements
- discovery findings
- implementation tasks
- verification
- material decisions
- stop conditions

The user approves the plan in conversation. The file records the approved state; it does not manufacture approval.

## Plan lifecycle

```text
PENDING
   ↓
CHANGES_REQUESTED ↔ PENDING
   ↓
APPROVED
   ↓
IN_PROGRESS
   ↓
COMPLETED
```

`ABANDONED` may be used when the work will not proceed.

An approval applies only to the approved plan and scope. It does not authorize later material deviations.

## `continue` does not bypass the gate

A continuation request is an instruction to continue an already-authorized workflow. It is not itself approval for a non-trivial plan that has not yet been approved.

# The Council

Every individual task in Phases 0–3 receives a Council review before completion.

The five perspectives are:

- **Product/PM:** Is the result actually what the product requires?
- **Security:** What could expose, authorize, validate, or trust something incorrectly?
- **Architecture:** Does the implementation fit the project without unnecessary complexity?
- **QA:** Can the expected behavior be verified reliably?
- **Skeptic:** What assumption or failure mode has everyone else overlooked?

The Council is a **decision gate**, not a requirement for five people or five separate passes.

### Council operating model

The agent may proceed when the requirement is clear and the implementation is routine.

The agent must stop when the Council surfaces:
- genuine ambiguity
- conflicting requirements
- material product decisions
- architectural tradeoffs
- material security decisions
- scope expansion
- new dependency decisions
- destructive/irreversible operations

This preserves speed without turning uncertainty into silent assumptions.

---

# Decision Authority

The project's autonomy model is intentionally strict.

### The agent owns

Routine implementation choices that are clearly implied by approved requirements.

### The user owns

Product behavior, material architecture, security posture, dependency choices, scope, destructive actions, and unresolved tradeoffs.

### The core rule

> **Do not guess where the project owner must decide.**

The useful hierarchy is:

**Known → follow**  
**Clearly implied → decide**  
**Ambiguous → ask**  
**Conflicting → ask**  
**Material decision → ask**  
**Destructive/irreversible → ask**

---

# Why the Six Phases Exist

The Vibe Coding Standard runs:

**Discovery → Plan → Build → Test → Deploy → Document**

Each transition catches a different failure mode.

- **Discovery before Plan** prevents building the wrong thing.
- **Plan before Build** prevents scope and dependency bloat.
- **Build with continuous security** catches security problems while the code is still fresh.
- **Test before Deploy** catches regressions before users do.
- **Deploy before Document** prioritizes getting a reliable product shipped.
- **Document closes the loop** so lessons feed future projects.

The phases are not bureaucracy. They are a sequence for making expensive mistakes earlier and cheaper.

---

# Phase 0: Discovery

## Why validate the problem?

Technical competence cannot compensate for solving the wrong problem.

The goal is not to prove an idea is good. It is to identify:
- who has the problem
- whether the problem is meaningful
- what existing alternatives do
- which assumption could kill the idea

### Flexibility

For a real project, do not skip problem validation. Stack/environment checks can be shortened when the same team has already validated the exact stack and environment.

---

# Phase 1: Plan

Phase 1 creates the stable context the agent needs before modifying the codebase.

## PRD

The PRD answers **what should exist** without prematurely dictating implementation.

## `CLAUDE.md`

`CLAUDE.md` translates project decisions into operational rules for the coding agent.

It is not a duplicate of the Vibe Coding Standard. It is an enforcement contract.

## Ultra Plan and Spec

Breaking the work into small tasks makes the Council review meaningful. A Council cannot reliably audit a vague instruction such as "build the app."

---

# Step 1.7: Package Decision Checklist

This is a hard gate because dependency bloat is cheap to create and expensive to remove.

The default answer is **no package**.

A package should exist because a concrete project requirement needs it, not because a Laravel/Filament tutorial commonly installs it.

The same principle applies to:
- permission systems
- audit logging
- exports
- PDFs
- tokens
- SSO
- backups
- AI
- media libraries
- settings
- maps/calendar plugins
- developer tooling and hook packages

### Authorization sizing

Simple internal applications often need a small number of fixed roles with developer-managed Policies.

A permissions package becomes more defensible when the product genuinely needs many granular permissions or non-technical administrators to manage access dynamically.

The decision belongs to the project owner when the tradeoff is material.

---

# Phase 2: Build

The internal sequence is:

**Foundation → Auth → Resources → Integration → Security → Debugging**

This sequence reduces rework.

### Why Auth before Resources?

Authorization should not be bolted onto Resources after the fact. Resources and actions should be designed with their access boundaries in mind.

### Why Policies?

UI visibility is not security. Server-side authorization must protect the actual operation and record.

Do not hard-code the assumption that every Resource has exactly five policy methods. The correct abilities depend on the Resource and the actions it exposes.

---

# Continuous Security Pass

The security pass is repeated after every feature because security is easier to reason about while the relevant change is fresh.

The standard eight areas are:

1. Authentication
2. Authorization
3. Record-level access
4. Mass assignment
5. Input validation
6. File upload/storage security
7. Dependency security
8. Rate limiting/abuse protection

This is deliberately broader than a Resource-only checklist.

### Important principle

> **A hidden button is not an authorization boundary.**

The server must enforce access independently of whether the UI shows an action.

---

# Phase 3: Test & Refine

The standard testing architecture is intentionally simple:

**Pest Unit → Pest Feature → standalone Playwright E2E**

## Pest

Use Pest for:
- Unit tests
- Feature/application tests
- Filament Resource behavior through Livewire testing helpers

## Playwright

Use standalone Playwright for:
- critical browser workflows
- real user journeys
- cross-browser checks
- responsive browser checks

### Why not standardize Pest Browser too?

Pest v4 supports browser testing, but maintaining two browser-testing approaches creates unnecessary overlap.

This Blueprint deliberately chooses one browser architecture:

> **Pest for Unit/Feature; Playwright for E2E.**

A project can deviate only through an explicit project decision.

---

# Scope Discipline

The agent should not "improve" unrelated code while completing a task.

Unrelated refactoring:
- increases review surface
- makes regressions harder to attribute
- obscures the requested change
- weakens the Council's ability to determine whether scope was respected

If an unrelated improvement is discovered, mention it rather than silently implementing it.

---

# Deviation Protocol

A project may intentionally deviate from this Blueprint.

A deviation should record:

1. What rule is being changed?
2. Why does the project need the deviation?
3. What alternative was considered?
4. What tradeoff does the deviation introduce?
5. Does `CLAUDE.md` need updating?

A deviation is not a failure. **An undocumented deviation is.**

---

# `CLAUDE.md` as a Living Contract

Step 1.2 creates the initial agent contract.

Step 2.6 synchronizes it with reality.

After that, whenever the actual stack or approved architecture changes, `CLAUDE.md` should be updated as part of the same change.

This prevents the agent from following stale instructions.

---

# Evidence Before Done

A task is not complete merely because the agent wrote code.

Completion should be based on evidence such as:
- passing tests
- successful commands
- verified UI behavior
- migration/schema verification
- confirmed policy behavior
- successful build/lint checks

The agent should state what was actually verified.

---

# How to Use This in Practice

1. Start with Phase 0.
2. Complete Phase 1 before significant implementation.
3. Put the approved `CLAUDE.md` in the project root and the canonical Vibe Coding Standard/Guideline in `docs/00-project/vibe-coding/`.
4. Begin agentic implementation from Phase 2.
5. Council-audit every Phase 0–3 task.
6. Let the agent proceed on clear implementation details.
7. Stop for user input on material product, architecture, security, dependency, scope, or destructive decisions.
8. Re-run the Package Decision Checklist whenever a new dependency is proposed.
9. Repeat the Security Pass after every feature.
10. Complete Phase 3 before treating the feature/project as production-ready.
11. Use Phases 4–5 according to project maturity.
12. Feed the retro into the next project's Discovery phase.

The goal is not maximum ceremony.

The goal is **high autonomy where the requirements are clear, and zero guessing where the owner must decide.**
---

# Tolaria Vault

Tolaria Vault is a **documentation library** for project reference.

Its purpose is to give the project owner a centralized place to find useful records such as plans, decisions, research, screenshots, issues, follow-ups, daily logs, and the changelog.

It is intentionally separate from the AI operating model.

### What the Vault is

- A storage location for project documentation.
- A place where the AI may write useful documentation produced during development.
- A historical reference for the project owner.
- A convenient record of what was planned, decided, researched, changed, tested, or recorded.

### What the Vault is not

The Vault is not:

- an AI decision engine
- an approval mechanism
- an enforcement layer
- a replacement for `CLAUDE.md`
- a replacement for the Vibe Coding Standard
- a replacement for the PRD or specification
- an authority that overrides current requirements
- a requirement for the AI to consult before making ordinary implementation decisions

The AI may **write documentation to the Vault**, but the Vault does not govern the AI.

### Documentation structure

```text
docs/
├── 00-project/
│   ├── architecture-decisions/
│   ├── plans/
│   ├── screenshots/
│   ├── prompts/
│   └── followups/
├── 01-issues/
├── 02-research/
├── 03-daily-logs/
└── 04-changelog/
    └── CHANGELOG.md
```

### Documentation principle

Do not document every tiny action. The goal is useful project history, not a second Git log.

Use the appropriate document type:

- **Plan** — what was intended.
- **ADR** — why an important architectural decision was made.
- **Research** — what was investigated.
- **Screenshot** — selected visual evidence.
- **Issue** — what problem was tracked.
- **Followup** — relevant continuation prompt.
- **Daily log** — meaningful session history.
- **Changelog** — what meaningfully changed.

The changelog should summarize meaningful additions, changes, fixes, removals, and milestones. It should not become a list of every file edit or commit.

### Documentation safety

Documentation must not intentionally contain secrets, credentials, tokens, or sensitive production data.

Tolaria Vault is reference material. Historical documentation may become outdated and should never silently override current project requirements.
