# WS-H Failure Diagnosis (CLUSTER-1 / 2 / 3)

**Status:** read-only diagnosis artifact. No failure listed here was fixed.
No application code, test, migration, factory, seeder, or blueprint file was
modified. Single write target of this session: this file.

**Scope:** the three handoff clusters from `tests/Feature/Architecture/TRIAGE.md`
(CLUSTER-1 NOT NULL violations, CLUSTER-2 notification recipient scope,
CLUSTER-3 concurrency race). Other TRIAGE clusters are out of scope here and
untouched.

**Method:** repository inspection + targeted read-only test runs. Every
mechanism below is labeled **Observed** (directly reproduced) or **Inferred**
(derived, not directly reproduced). Cross-referenced: migrations, factories,
service code, models, tests, and `docs/00-project/blueprint.md`.

**Evidence commands run (read-only):**

```
php artisan test tests/Unit/Services/InventoryServiceTest.php
php artisan test tests/Unit/Listeners/ListenerRecipientScopeTest.php
php artisan test tests/Feature/Concurrency/ConcurrencyTest.php
php artisan test tests/Unit/Models/DirectTransferTest.php
php artisan test tests/Unit/Services/NegotiationServiceTest.php tests/Unit/Services/TransferRequisitionServiceTest.php
```

**Gate:** `docs/00-project/blueprint.md` "Decision Boundary" (§13) — Council may
implement technical integrity fixes without product clarification; must stop for
user direction when a change would alter: inventory valuation methodology;
financial/accounting treatment; **business lifecycle states**; external-party
workflows; automatic fulfillment behavior; return/refund semantics;
**notification recipients or escalation policy**. The project `CLAUDE.md`
Destructive Change Gate additionally requires owner approval for any migration
that drops or irreversibly changes data or a column constraint.

---

## CLUSTER-1 — NOT NULL constraint violations

Three distinct columns. All are `Illuminate\Database\QueryException:
SQLSTATE[23000] Integrity constraint violation: 19 NOT NULL constraint failed`
on the SQLite test DB (`sqlite_testing`).

### 1a. `stock_movements.unit_name_used`

| Fact | Evidence | Label |
|---|---|---|
| Migration declares NOT NULL, no default | `create_stock_movements_table.php:21` `$table->string('unit_name_used');` | Observed |
| Blueprint matches migration (NOT NULL) | `blueprint.md:2224` `| unit_name_used | string | No | — |` | Observed |
| `transfer_requisition_items.approved_unit_name` is **nullable** | `create_transfer_requisition_items_table.php:25` `->nullable()` | Observed |
| Service copies nullable source into NOT NULL target | `InventoryService.php:454` and `:675` `'unit_name_used' => $item->approved_unit_name` | Observed |
| `directTransfer()` path uses `$line->unit_name` (non-null) | `InventoryService.php:329,343` — **not** the failing path | Observed |
| Service guards `approved_base_qty` null but not `approved_unit_name` | `InventoryService.php:413-417`; no parallel guard | Observed |
| Tests build items without `approved_unit_name`, expect dispatch success | `InventoryServiceTest` dispatch helper sets `approved_base_qty` only | Observed |
| Failure reproduced | `NOT NULL constraint failed: stock_movements.unit_name_used` on `transfer_out` insert | Observed |

**Root cause.** Schema-vs-service contract gap. The schema requires
`unit_name_used` (a stock-movement audit field) to be non-null, but
`dispatchTransfer()`/`scanToReceive()` source it from the *nullable*
`approved_unit_name`. When an item never went through negotiation
materialization, `approved_unit_name` is null and the insert fails.
(`NegotiationService::materializeRequestedAsApproved()` sets
`approved_unit_name` **and** `approved_base_qty` together.) Test fixtures that
hand-build Confirmed requisitions bypass materialization, so they hit the gap.

**Classification: BOUNDARY (owner decision).** Three fix sides, none canonical
from code alone:
- **Service guard**: reject dispatch when `approved_unit_name` is null (mirror
  the existing `approved_base_qty` guard) — but the failing tests assert
  *success*, so this adopts a new "approved_unit_name mandatory pre-dispatch"
  contract.
- **Service fallback**: write `$item->approvedUnitName ?? $variant->base_unit_name`
  — changes the audit semantics of the movement record.
- **Schema relaxation**: make `unit_name_used` nullable — contradicts
  `blueprint.md:2224` and is a migration change (Destructive Change Gate).

Which is correct is a *business lifecycle state* + audit-contract decision (§13).

**Count:** TRIAGE reports ~60. Highest-leverage column.

### 1b. `transfer_requisition_items.requested_base_qty`

| Fact | Evidence | Label |
|---|---|---|
| Migration declares NOT NULL, no default | `create_transfer_requisition_items_table.php:24` | Observed |
| Blueprint matches migration (NOT NULL) | `blueprint.md:2272` `| requested_base_qty | integer | No | — |` | Observed |
| Factory default is non-null | `TransferRequisitionItemFactory` `'requested_base_qty' => $qty` | Observed |
| Form derives it | `TransferRequisitionForm` `requested_qty * requested_unit_ratio` | Observed |
| Blueprint documents same derivation | `blueprint.md:8557` | Observed |
| `approved_base_qty` is the nullable column | `create_transfer_requisition_items_table.php:26` `->nullable()` | Observed |
| Tests pass `null` explicitly to force an un-materializable item | `NegotiationServiceTest`, `TransferRequisitionServiceTest` | Observed |
| Failure reproduced | `NOT NULL constraint failed: transfer_requisition_items.requested_base_qty` | Observed |

**Root cause.** Test-oracle drift. The tests use `requested_base_qty => null` as
a sentinel for "item cannot be materialized". But the nullable column that
actually models an un-materialized item is `approved_base_qty`. `requested_base_qty`
is the *input* side and is non-null by schema and by blueprint; the factory and
the Filament form both always compute it. The sentinel is wrong — the tests
should null `approved_base_qty` only.

**Classification: non-boundary (council-fixable).** Schema and blueprint agree;
no owner decision needed. Fix side: test/fixture — stop passing
`requested_base_qty => null`. An app-side mutator deriving `requested_base_qty`
is *not* required and would add unrequested behavior — not recommended.

**Count:** TRIAGE reports ~8.

### 1c. `direct_transfers.transferred_by`

| Fact | Evidence | Label |
|---|---|---|
| Migration declares NOT NULL + `restrictOnDelete` | `create_direct_transfers_table.php:18` `->constrained('users')->restrictOnDelete()` | Observed |
| Blueprint §2.21 says **nullable** + `nullOnDelete` | `blueprint.md:2496` `| transferred_by | FK → users.id (nullOnDelete) | Yes | — |` | Observed |
| Blueprint canonical exemplar says nullable + `nullOnDelete` | `blueprint.md:3227` `$table->foreignId('transferred_by')->nullOnDelete()->constrained('users');` | Observed |
| Factory populates it | `DirectTransferFactory` `'transferred_by' => User::factory()` | Observed |
| Test asserts nullable + null-on-delete | `DirectTransferTest` "allows transferred_by be null and nulls it on user delete" | Observed |
| Failure reproduced (1 NOT NULL case) | `NOT NULL constraint failed: direct_transfers.transferred_by` on insert with `transferred_by => null` | Observed |

**Root cause.** Blueprint-drift. Migration contradicts the approved blueprint
(§2.21 table **and** the canonical exemplar, both nullable/nullOnDelete). The
migration instead makes it NOT NULL + restrict. The test asserts the *blueprint*
contract.

Note: the second `DirectTransferTest` failure this session — `it casts
transferred_at` (`CarbonImmutable` vs `Illuminate\Support\Carbon`) — is **not**
a `transferred_by` NOT NULL failure; it belongs to the `Carbon` cast family.
So TRIAGE's "2" for this column is **Observed = 1** NOT NULL case.

**Classification: BOUNDARY (owner decision).** Correct side is clear (blueprint
§2.21), but the fix is a migration change (nullable + FK action) → Destructive
Change Gate. TRIAGE category: code-vs-approved-blueprint disagreement = owner
decision.

**Count:** TRIAGE says 2; **Observed** 1.

---

## CLUSTER-2 — Notification recipient scope (7 failures)

**File:** `tests/Unit/Listeners/ListenerRecipientScopeTest.php`.

| Fact | Evidence | Label |
|---|---|---|
| Auditor fixture has **no** warehouse afterCreating hook | `UserFactory::auditor()` sets role only | Observed |
| Test *deliberately* warehouse-assigns the auditor | test attaches `$auditor->warehouses()->attach($from->id)` before asserting | Observed |
| Test expects auditors excluded from non-loss events | 7 `assertNotSentTo($fx['auditor'], …)` cases | Observed |
| App listener recipient query is role-agnostic on the warehouse branch | 8 listeners: `->where('role', Admin)->orWhereHas('warehouses', …)` | Observed |
| Loss listener *does* include Auditor | `NotifyLossRecorded` `whereIn('role', [Admin, Auditor])->orWhereHas('warehouses', …)` — matches test's loss expectation | Observed |
| Blueprint §22.3a canonical listener is **identical role-agnostic code** | `blueprint.md:18385+` `NotifyTransferConfirmed` `->where('role', Admin)->orWhereHas('warehouses', …)` | Observed |
| Test file is self-described as **not** in blueprint §12/§25 | test docblock says authored at owner direction, not in §12/§25 | Observed |
| Failure reproduced | `unexpected [XNotification] notification sent. Failed asserting actual size 1 matches expected size 0` | Observed |

**Root cause.** The app listener matches the blueprint §22.3a canonical code
*verbatim*. The auditor is attached to a warehouse, so the role-agnostic
`orWhereHas('warehouses', …)` branch matches it and it is notified. The test
asserts the opposite for every non-loss event. This is **not** the
`UserFactory::admin()`-contamination family: the auditor fixture carries no
auto-attached warehouse; the test attaches it on purpose. Two side-by-side
sources disagree on policy, not on fixture mechanics:
- Blueprint §22.3a canonical code → warehouse-assigned auditors **are** notified.
- Test → auditors excluded except for loss events.

**Classification: BOUNDARY (owner decision).** §13 lists *notification
recipients or escalation policy* as an explicit must-stop item. Neither source
is derivable as canonical from code: the blueprint's *prose comment* on the
`NotifyTransferConfirmed` example reads "admins plus staff assigned to the
affected warehouse(s)" (auditor not named), while its *code* is role-agnostic
and would include a warehouse-assigned auditor. That prose-vs-code tension
inside §22.3a is itself the open question.

**Fix-side options (both require the owner's recipient decision first):**
- "Auditors never notified except loss" is policy → app-bug in all 8 listeners;
  add an explicit auditor exclusion to the warehouse branch. This **deviates
  from blueprint §22.3a canonical code** → material deviation.
- "Warehouse-assigned auditors are notified" is policy → test-drift; the 7
  oracles are wrong.

**Count:** TRIAGE 7; **Observed** 7.

---

## CLUSTER-3 — Concurrency race (1 failure)

**File:** `tests/Feature/Concurrency/ConcurrencyTest.php` — **Observed** the
duplicate-payload-no-op case fails with
`App\Exceptions\InvalidDocumentStateException`.

| Fact | Evidence | Label |
|---|---|---|
| First scan payload receives the full outstanding qty | test first-scan payload exhausts the requisition | Observed |
| After a full receipt `updateReceiptClosingStatus()` → `Completed` | `InventoryService.php:958-983` | Observed |
| Second identical scan throws at the **status guard** | `InventoryService.php:497-507` accepts only `Dispatched`/`PartiallyReceived`; `Completed` → throw | Observed |
| Guard precedes the idempotency checksum replay | guard `:497` vs checksum pre-check `:584-590` | Observed |
| Blueprint canonical `scanToReceive()` has the **same ordering** | `blueprint.md:6146-6160` (guard) vs `:6252` (checksum claim) | Observed |
| Blueprint acceptance criteria claim the **opposite** behavior | `blueprint.md:14856` and `:15106` "`scanToReceive()` no-ops on duplicate payload via state-equality check" | Observed |
| Failure reproduced | `InvalidDocumentStateException` at `InventoryService.php:501` | Observed |

**Root cause.** Blueprint-internal contradiction. The blueprint's own canonical
`scanToReceive()` code rejects a non-`Dispatched`/`PartiallyReceived`
requisition *before* it reaches the idempotency-key replay. But the same
blueprint's acceptance matrix asserts duplicate payloads no-op. Both cannot hold
once a full-receipt payload closes the requisition to `Completed`. The app
faithfully implements the canonical code, so it reproduces the contradiction
rather than a coding error.

**Classification: BOUNDARY (owner decision).** *Business lifecycle states* are a
§13 must-stop item. Resolution is a lifecycle/idempotency-ordering decision:
- Option A — idempotency replay precedes the status guard (duplicate scan
  no-ops after completion). Changes blueprint §6.2 canonical code.
- Option B — the guard stays first; the acceptance criterion at 14856/15106 is
  narrowed to "duplicate payload while still in a receivable state". The test
  is then test-drift (it uses an exhausting payload).

Either option edits either the blueprint canonical code or its acceptance
criteria — a material deviation pending owner direction.

**Count:** TRIAGE 1; **Observed** 1.

---

## Consolidated owner-decision list (BOUNDARY only)

1. **CLUSTER-1a `stock_movements.unit_name_used`** — should dispatch require a
   non-null `approved_unit_name` (guard), fall back to `base_unit_name`, or
   relax the column? Highest leverage (~60 failures).
2. **CLUSTER-1c `direct_transfers.transferred_by`** — confirm blueprint §2.21
   (nullable + `nullOnDelete`) as canonical and authorize the migration fix, or
   confirm the NOT NULL + restrict migration as canonical and change the test.
3. **CLUSTER-2 notification recipients** — are warehouse-assigned auditors
   notified for non-loss events (matches §22.3a canonical code) or excluded
   (matches the test)? Applies to 8 listeners.
4. **CLUSTER-3 duplicate-scan-after-completion** — idempotency replay before the
   status guard (blueprint canonical code changes), or narrow the acceptance
   criterion (test is drift)?

## Consolidated council-fix list (non-boundary only)

1. **CLUSTER-1b `transfer_requisition_items.requested_base_qty`** — test/fixture
   only: stop passing `requested_base_qty => null`; use `approved_base_qty =>
   null` as the un-materializable sentinel. Schema and blueprint agree; no owner
   decision. Fix side: `NegotiationServiceTest`, `TransferRequisitionServiceTest`.

## Unresolved / missing evidence

- **CLUSTER-1a single verdict.** Cannot collapse to test-drift vs app-bug
  without the approved dispatch contract for `approved_unit_name`. Evidence that
  would resolve it: the PRD/spec statement on whether a requisition item is
  *required* to carry an approved unit name before dispatch (i.e., must
  negotiation always run first), or an example production item created without
  negotiation. Absent that, all three fix sides remain live.
- **CLUSTER-1b** — resolved (non-boundary). Listed here only for completeness:
  the fix direction depends on the schema/blueprint agreement already observed.
- **CLUSTER-2 single verdict.** Cannot collapse to app-bug vs test-drift without
  the approved recipient policy. The §22.3a prose ("admins plus staff") and its
  code (role-agnostic) disagree. Evidence that would resolve it: the owner's
  recipient-matrix spec, or a recorded decision that "staff" excludes auditors.
- **CLUSTER-3 single verdict.** The contradiction is proven, but which side is
  canonical is an owner call — no code evidence can break the tie.
- **TRIAGE count accuracy.** TRIAGE's per-column counts
  (`unit_name_used` ~60, `requested_base_qty` ~8, `transferred_by` 2) were **not**
  all independently reproduced; this session observed 1 NOT NULL failure for
  `transferred_by` (the TRIAGE "2" appears to include the `CarbonImmutable` cast
  failure from the cast family). Full-suite re-count not run this session.

## Read-only attestation

- No file modified except this diagnosis report.
- No application code, test, migration, factory, seeder, or blueprint file
  modified.
- No `git add`, no `git rm`, no commit.
