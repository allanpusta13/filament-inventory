# The Vibe Coding Standard
### Stack: Laravel 13 · FilamentPHP v5 · Pest v4 · Standalone Playwright

> A reusable, stack-scoped project standard: Discovery → Plan → Build → Test → Deploy → Document. The Standard defines **what happens and when**. The Guideline explains **why**. `CLAUDE.md` defines **how the AI coding agent behaves and enforces project-specific rules**.

---
## 🧭 Vibe Coding Standard

This project uses three synchronized documents as its Vibe Coding Standard:

- `docs/00-project/vibe-coding/standard.md` — process standard: **WHAT + WHEN**.
- `docs/00-project/vibe-coding/guideline.md` — reasoning standard: **WHY + HOW TO THINK**.
- `CLAUDE.md` — AI project contract: **HOW THE AI BEHAVES + ENFORCES**.

`CLAUDE.md` is the AI-facing contract and must remain aligned with the actual project.
The Standard and Guideline are canonical supporting documents and must not be duplicated elsewhere.

Do not create alternate copies of these documents in other directories.

---

## ⚖️ Council Operating Rule

For **every individual task in Phases 0–3**, run the Council review before marking that task complete.

### Council perspectives

1. **Product/PM** — Does this satisfy the requirement, with no unnecessary scope?
2. **Security** — Does this introduce or leave an authentication, authorization, validation, data-exposure, upload, dependency, or abuse risk?
3. **Architecture** — Does this fit the existing stack and project structure without unnecessary duplication or coupling?
4. **QA** — Is the result testable, verifiable, and consistent with the testing strategy?
5. **Skeptic** — What could a critical reviewer challenge that the other perspectives missed?

### Council decision

**TASK → EXECUTE/PRODUCE → VERIFY → COUNCIL REVIEW → DECIDE**

- If no genuine issue exists, mark the task complete and continue.
- If the Council identifies a correctable issue, correct it, re-verify, and re-audit.
- If the Council identifies ambiguity, conflicting requirements, scope uncertainty, a material tradeoff, or a project-owner decision, **stop and ask the user**.
- Do not ask permission to execute an already-defined requirement.

This per-task Council gate is mandatory only for Phases 0–3. It may be used in Phases 4–5 at the project's discretion.

---

## 📚 Tolaria Vault — Documentation Library

Tolaria Vault is the project's documentation library. It is a place to store useful project records for the owner's reference.

It is **not** part of the AI's decision authority, execution workflow, or source-of-truth hierarchy.

### Standard structure

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

### Documentation output

When meaningful project documentation is produced, store it in the appropriate Tolaria Vault location.

- `architecture-decisions/` — non-obvious architectural decisions and tradeoffs.
- `plans/` — approved task plans.
- `screenshots/` — selected visual evidence from browser/UI verification.
- `prompts/` — task prompt references.
- `followups/` — relevant follow-up prompts; do not save a file when the prompt is strictly `continue`.
- `01-issues/` — individual issue documentation.
- `02-research/` — framework, package, and technical research.
- `03-daily-logs/` — meaningful session summaries for larger tasks.
- `04-changelog/CHANGELOG.md` — concise record of meaningful project changes, fixes, additions, removals, and milestones.

Do not create alternative documentation directories when a document belongs in the Vault.

The Vault is documentation storage. Its contents do not override current user instructions, the PRD, approved specifications, `CLAUDE.md`, or the Vibe Coding Standard.

---

## 🧭 Decision Authority

The agent operates under **strict autonomy**.

### Agent may decide

- Direct implementation details clearly implied by the approved requirements.
- Routine code structure that does not alter architecture, product behavior, security posture, scope, or dependencies.
- Test implementation details when the expected behavior is already defined.

### Agent must stop and ask

- Product behavior or requirement interpretation is ambiguous.
- Two requirements conflict.
- An architectural choice has multiple materially different solutions.
- A security decision materially changes protection or access behavior.
- Scope would expand beyond the requested feature.
- A new Composer/npm dependency is needed.
- A destructive or irreversible operation is proposed.
- A migration would drop/irreversibly alter data.
- Existing files would be deleted.
- The requested implementation conflicts with `CLAUDE.md`, the PRD, or an approved spec.

### No-guessing hierarchy

**Known → follow.**  
**Clearly implied implementation detail → decide.**  
**Ambiguous → ask.**  
**Conflicting → ask.**  
**Material architectural/product/security decision → ask.**  
**Destructive/irreversible → ask.**

---

# 📦 Stack

| Package | Version | Notes |
|---|---|---|
| PHP | `^8.4` | Project baseline |
| Laravel | `^13.0` | Application framework |
| Filament | `^5.6` | Filament v5; use the Schemas API |
| Livewire | `^4.0` | Filament v5 stack |
| Alpine.js | `^3.x` | Filament/Livewire frontend dependency |
| Tailwind CSS | `^4.x` | Project styling |
| Pest | `^4.0` | Unit + Feature testing |
| Playwright | `latest` | Standalone E2E testing only |

### Standard testing architecture

- **Pest v4:** Unit and Feature tests.
- **Standalone Playwright:** browser E2E, critical user flows, cross-browser, and responsive checks.
- Do **not** use Pest Browser as a second standard browser-testing architecture.
- Do not duplicate the same flow in Pest Browser and Playwright.

---

## 🤖 AI Operating Directory

The project separates AI working material from the Tolaria Vault documentation library.

```text
docs/00-project/
├── ai/
│   ├── references/
│   └── plans/
│       ├── pending/
│       └── approved/
├── vibe-coding/
│   ├── standard.md
│   └── guideline.md
└── plans/
```

- `ai/references/` — supporting project-specific context for the AI.
- `ai/plans/pending/` — non-trivial implementation plans awaiting user approval.
- `ai/plans/approved/` — approved implementation plans currently being executed or retained as execution history.
- `plans/` — durable owner/reference project plans in the Tolaria Vault.

Do not treat `ai/plans/` and `plans/` as the same workflow.

### Non-Trivial Work Gate

For non-trivial work, the AI must:

1. Complete the required Discovery.
2. Create a task-level implementation plan.
3. Council-audit the plan.
4. Save it under `docs/00-project/ai/plans/pending/`.
5. Present it to the user.
6. Wait for explicit user approval before implementation.
7. Record the approved plan under `docs/00-project/ai/plans/approved/`.
8. Implement only the approved scope.
9. Stop and request approval if a material deviation appears.
10. Verify the result with evidence.

Approval is conversational. A plan file marked `APPROVED` is not, by itself, user approval.

This gate applies to work that materially affects product behavior, architecture, security, data, dependencies, scope, or multiple parts of the application. Routine changes clearly implied by an approved requirement do not need a new plan.

# 🔍 Phase 0: Discovery

> Council-audit every individual task before marking it complete.

### 0.1 Validate the problem
Identify the target user, current problem, existing alternatives, and riskiest assumption.

### 0.2 Confirm stack fit
Confirm whether Filament is appropriate for the admin/back-office portion. If the product requires a highly custom customer-facing experience, plan the appropriate frontend separately.

### 0.3 Environment baseline
Confirm PHP 8.4+, Composer 2.x, Node 20+, and the selected database before scaffolding.

### 0.4 AI documentation baseline
For version-sensitive framework, package, SDK, API, setup, configuration, or integration work, use Context7 to retrieve current version-specific documentation before implementation. Context7 is a standard AI reference tool, not a substitute for local code inspection, tests, security review, or user approval.

### 0.5 Local environment baseline
EnvKit is the standard local PHP development environment for supported platforms. Confirm the project site, PHP version, database, and required services before implementation. If the target platform cannot use EnvKit, document the approved alternative in the project contract.

When EnvKit MCP is available:
- read-only inspection and diagnostics may be performed autonomously
- destructive or consequential environment actions require explicit user approval unless already covered by an approved plan
- local environment control never authorizes production or staging changes

---

# 🎯 Phase 1: Plan

> Council-audit every individual task before marking it complete.

### 1.1 Write a Full PRD

Include:
- Problem Statement
- Target Users
- Core Features (MVP only)
- User Flow
- Success Metrics
- Roadmap (MVP → v1 → v2)

Keep product requirements separate from implementation details.

### 1.2 Create `CLAUDE.md`

Create the agent contract for the planned stack. It must contain:
- Actual/projected stack
- Filament v5 rules
- Directory conventions
- Code style
- Testing architecture
- Security rules
- Agent behavior
- Council rules
- Commands
- Package decision rules

If an existing `CLAUDE.md` exists, inspect it before replacing anything.

### 1.3 Ultra Plan

Break the project into sequential phases and small tasks. Each task should have:
- Goal
- Scope
- Inputs
- Expected output
- Verification
- Stop conditions where relevant

### 1.4 Spec-Driven Development

For each feature, define:
- Model + migration
- Filament Schema
- Table
- Authorization
- Edge cases
- Expected behavior
- Test expectations

Do not write implementation merely because a spec is incomplete.

### 1.5 UI/UX Design Brief

Define:
- Navigation
- Table layout
- Forms
- Status conventions
- Responsive behavior
- Empty/loading/error states where relevant

### 1.6 Data Model & Relationships Map

Define entities, relationships, indexes, constraints, and important data ownership/access boundaries before migrations.

### 1.7 Package Decision Checklist — HARD GATE

**Default answer: no package.**

For every package category, ask whether the PRD contains a concrete requirement:

- Authorization beyond simple gate + Policies
- Audit trail
- Excel/CSV exports
- Generated PDFs
- API/mobile/device tokens
- SSO/OAuth
- Off-site backups
- AI/agents/embeddings/search
- Rich media/file library
- Configurable application settings
- Maps/geolocation/calendar

A package is approved only when:
1. A concrete requirement needs it.
2. The package is the appropriate solution.
3. Its maintenance/security/cost implications are acceptable.
4. The user has approved the material dependency decision.

The resulting approved package list becomes the Phase 2 install list.

---

# 🚀 Phase 2: Build

> Council-audit every individual task before marking it complete.

## 2A. Foundation

### 2.1 Laravel
Scaffold Laravel 13 and confirm the actual installed PHP/Laravel versions.

### 2.2 Filament
Install and configure Filament v5.

### 2.3 Pest
Install/configure Pest v4 and Laravel integration.

### 2.4 Playwright
Install standalone Playwright and configure `playwright.config.ts`.

### 2.5 Environment/database
Configure `.env`, database, and baseline migrations.

### 2.6 Synchronize `CLAUDE.md`
Update `CLAUDE.md` to reflect the **actual** installed stack and approved packages. The file is a living project contract, not a static template.

---

## 2B. Auth & Users

### 2.7 Panel authentication
Configure Filament authentication according to the PRD.

### 2.8 Panel access
Configure Filament panel access using the project's approved authorization approach.

### 2.9 Policies
Create and wire a Policy for every Resource that requires authorization.

Do not assume every Policy has exactly five methods. Implement **all abilities actually used by the Resource**, including relevant abilities such as:
- `viewAny`
- `view`
- `create`
- `update`
- `delete`
- `deleteAny`
- `restore`
- `restoreAny`
- `forceDelete`
- `forceDeleteAny`

Use only the abilities applicable to the Resource.

---

## 2C. Core Resources

Repeat per entity:

### 2.10 Migration + Eloquent Model
Include relationships, casts, factories, indexes, constraints, and mass-assignment protection.

### 2.11 Filament Resource
Create the Resource using Filament v5 conventions.

### 2.12 Schema
Use the Filament v5 **Schemas API**.

### 2.13 Table
Configure columns, filters, sorting, searching, row actions, and bulk actions.

### 2.14 RelationManagers
Add only when the feature requires nested/related management.

### 2.15 Custom Pages/actions/widgets
Add only when required by the approved specification.

---

## 2D. Integration

### 2.16 MCP and AI-controlled environment
Use MCP when it provides an approved project capability. EnvKit MCP is the standard local environment integration when EnvKit is in use.

The agent may autonomously use safe/read-only EnvKit MCP operations such as service status, diagnostics, logs, and environment inspection. Require explicit user approval for destructive or consequential actions unless the action is already within an approved non-trivial implementation plan.

For any custom application MCP server, keep changes within the approved MCP scope and stop before adding unapproved dependencies or capabilities.

Context7 is the standard documentation MCP/reference tool for version-sensitive external dependency work. Use it before implementation when current library/framework documentation is material to the task.

### 2.17 Database finalization
Finalize indexes, constraints, seeders, and development/demo data.

### 2.18 Queues/Jobs
Use queues/jobs for work that is explicitly appropriate for asynchronous execution, such as imports, exports, notifications, or other long-running work.

---

## 🔐 2E. Security Pass — Continuous

Repeat after **every feature**, not only at the end.

### 2.19 Authentication
Verify authentication boundaries, session behavior, protected routes, and relevant login/recovery protections.

### 2.20 Authorization
Verify panel access, Resource Policies, record-level access, action/bulk-action authorization, and server-side enforcement.

**UI visibility is not authorization.**

### 2.21 Record-level access
Verify users cannot access, mutate, export, or infer records outside their permitted scope.

### 2.22 Mass assignment
Review `$fillable` / `$guarded` on every Model touched.

### 2.23 Input validation
Review request/form/schema validation, type constraints, ownership checks, and unsafe input paths.

### 2.24 File upload/storage security
Review MIME/type and size validation, storage disks, filenames/paths, visibility, and access controls.

### 2.25 Dependency security
Run `composer audit` and `npm audit` when applicable, especially after dependency changes.

### 2.26 Rate limiting/abuse protection
Review authentication and public-facing endpoints/actions for appropriate rate limiting and abuse controls.

---

## 🐛 2F. Debugging

### 2.27 Debug an Error

When fixing an error:
- Identify the root cause.
- Modify only directly relevant files.
- Do not perform unrelated refactors.
- Verify the fix.
- Stop if the fix requires a new dependency, destructive schema change, architectural change, or other material decision.

---

# 🧪 Phase 3: Test & Refine

> Council-audit every individual task before marking it complete.

### 3.1 Unit tests — Pest v4
`tests/Unit/`

Test non-trivial domain/model/service logic in isolation.

### 3.2 Feature tests — Pest v4
`tests/Feature/`

Test application behavior and Filament Resources, including:
- list/rendering behavior
- create
- edit
- delete
- relevant bulk actions
- Policy enforcement
- validation
- important edge cases

Use Filament's Livewire testing helpers where appropriate.

### 3.3 Playwright E2E — critical paths
`tests/e2e/`

Test complete user workflows that genuinely require a browser.

### 3.4 Playwright — cross-browser/responsive
Run the approved E2E suite against the required browser matrix and responsive viewports.

The matrix may be reduced when the project's actual supported-browser requirements justify it.

### 3.5 Dead-code/quality cleanup
Remove unused imports, unreachable code, and artifacts created by the completed feature. Do not add a dependency solely for cleanup without approval.

### 3.6 Git hygiene
Create focused, meaningful commits appropriate to the project's workflow.

### 3.7 Hooks/guardrails
Configure pre-commit/CI checks only when approved by the project. Do not introduce hook-related packages without the Package Decision Checklist and user approval.

---

# 📦 Phase 4: Deploy & Monitor

Council per-step review is optional.

### 4.1 CI/CD
Run the project's approved test, style, and build checks before deployment.

### 4.2 Environment configuration
Use separate environment configuration and appropriate production caches/build steps.

### 4.3 Staging
Use staging for final production-like verification where appropriate.

### 4.4 Monitoring & logging
Configure error tracking, application logging, queue monitoring, and uptime monitoring according to project requirements.

Do not assume a particular monitoring vendor or package without a project decision.

### 4.5 Rollback plan
Document a practical rollback strategy, including database implications for migrations.

---

# 📚 Phase 5: Document & Grow

Council per-step review is optional.

### 5.1 README
Document setup, environment requirements, database setup, tests, and common development commands.

### 5.2 Architecture documentation
Document how Resources, Schemas, Policies, models, jobs, integrations, and other major components fit together.

### 5.3 Turn a task into a skill
Capture reusable implementation knowledge from important features.

### 5.5 Documentation Library

Store meaningful project documentation in the Tolaria Vault according to its documentation type. Keep the changelog concise and focused on meaningful project-level changes rather than individual code edits.

### 5.4 Retro & next iteration
Record:
- What took longer than expected and why
- What should change next time
- Highest-leverage next improvement

Feed useful lessons into the next project's Phase 0.

---

# ✅ Definition of Done

A Phase 0–3 task is complete only when:

1. The requested scope is implemented.
2. Verification has been performed.
3. The applicable Security Pass has been considered.
4. The Council has reviewed it from all five perspectives.
5. No unresolved ambiguity remains.
6. Required tests pass.
7. No unrelated scope was introduced.
8. `CLAUDE.md` remains consistent with the actual project when the task changes project reality.

**Evidence Before Done:** never claim a task is complete solely because code was written. State what was verified.
