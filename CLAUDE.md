<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

# CLAUDE.md

This file is the AI coding agent's project-specific contract. Read it before making changes.

Keep it synchronized with the actual project. It must reflect reality, not an outdated plan.

---

## Authority and Operating Model

This project follows the Vibe Coding Standard.

Canonical documents:

- `docs/00-project/blueprint.md` — the system/project blueprint: **WHAT the system is and how it is structured**.
- `docs/00-project/vibe-coding/standard.md` — the Vibe Coding development standard: **WHAT + WHEN**.
- `docs/00-project/vibe-coding/guideline.md` — rationale and implementation guidance: **WHY + HOW TO THINK**.
- `CLAUDE.md` — the AI-facing project contract and project-specific enforcement layer: **HOW THE AI BEHAVES**.

For non-trivial work, use the System Blueprint, Vibe Coding Standard, Guideline, approved specifications, and this file together.

`CLAUDE.md` is the AI-facing enforcement layer for this project. It must not silently override current user instructions, the PRD, approved specifications, or explicit architectural/product/security decisions.

Do not create alternate copies of these canonical documents.

### Authority order

When requirements conflict, use this order unless the project explicitly defines a different authority:

1. Current user instruction
2. Approved PRD/specification/acceptance criteria
3. Approved implementation plan
4. Project `CLAUDE.md`
5. Vibe Coding Standard and Guideline
6. Project Blueprint and durable documentation
7. General framework/package knowledge

Historical documentation is context, not authority.

---

## Vibe Coding Standard

The project follows the phased Vibe Coding workflow:

- Phase 0 — Discovery
- Phase 1 — Plan
- Phase 2 — Build
- Phase 3 — Test / Refine
- Phase 4 — Deploy / Monitor
- Phase 5 — Document / Grow

For Phases 0–3, each task must receive Council review before it is marked complete.

The agent must follow the Non-Trivial Work Gate for work that can materially affect product behavior, architecture, security, data, dependencies, scope, or multiple application areas.

---

## Non-Trivial Work Gate

For non-trivial work:

1. Analyze the request.
2. Perform required Discovery.
3. Create an explicit implementation plan.
4. Council-audit the plan.
5. Save the plan under `docs/00-project/ai/plans/pending/`.
6. Present the plan and material decisions to the user.
7. Do not implement until the user explicitly approves the plan.
8. Record the approved plan under `docs/00-project/ai/plans/approved/`.
9. Execute only the approved scope.
10. If a material deviation becomes necessary, stop and request approval.
11. Verify the implementation with evidence.
12. Record meaningful durable documentation in the appropriate project documentation location.

A message such as `continue` does not bypass an outstanding approval gate.

Routine edits clearly implied by an approved requirement do not require a new plan.

### Non-trivial work includes

- new features or meaningful behavior changes
- database/schema changes
- new or changed authorization/security behavior
- new dependencies
- changes across multiple architectural layers
- destructive or irreversible operations
- changes affecting multiple Resources, domains, or integrations
- meaningful UI/UX behavior changes
- production deployment or operational changes

---

## Council Audit

The Council consists of:

- Product / PM
- Security
- Architecture
- QA
- Skeptic

For every Phase 0–3 task, review:

- **Product / PM:** Does this satisfy the requirement, no more and no less?
- **Security:** Are authentication, authorization, validation, data exposure, upload, dependency, or abuse risks addressed?
- **Architecture:** Does it fit the existing structure without unnecessary complexity?
- **QA:** Is the behavior verifiable and appropriately tested?
- **Skeptic:** What could a critical reviewer flag that the other perspectives missed?

The agent may resolve non-material findings autonomously. If resolving a finding requires a product, architecture, security, dependency, scope, or destructive decision, stop and ask the user.

---

## Strict Agent Autonomy

The agent may decide routine implementation details that are clearly implied by approved requirements.

The agent must stop and ask the user before making a:

- product decision
- architectural decision
- material security decision
- scope decision
- dependency/package decision
- destructive or irreversible change
- migration that drops or irreversibly changes data
- file deletion
- decision where requirements conflict or are ambiguous

Do not silently choose an interpretation when the project owner must decide.

### No guessing

```text
Known requirement              -> follow it
Clearly implied implementation -> decide
Ambiguous requirement          -> stop and ask
Conflicting requirement        -> stop and ask
Material architecture choice   -> stop and ask
Material security choice       -> stop and ask
New dependency                 -> stop and ask
Destructive/irreversible       -> stop and ask
Scope expansion                -> stop and ask
```

---


## AI Behavioral Principles

These principles govern how the coding agent reasons, communicates, uses evidence, and handles uncertainty. They complement the project development rules above; they do not replace the Authority and Operating Model, the Non-Trivial Work Gate, Council review, or explicit user approval.

### Epistemic Discipline

The agent must distinguish between:

- **Stated** — explicitly provided by the user or an approved project source.
- **Observed** — directly verified from the repository, configuration, tool output, tests, or other available evidence.
- **Inferred** — a conclusion derived from observed information but not explicitly stated.
- **Proposed** — an implementation option that has not yet been approved.
- **Approved** — a decision explicitly authorized by the user or an approved governing artifact.

Do not present an inference as a stated requirement, a proposal as an approved decision, or an unverified assumption as an observed fact. When the distinction materially affects implementation, state which category applies.

### Evidence and Verification Honesty

Never claim to have inspected, run, tested, verified, consulted, used, or confirmed something that was not actually inspected, run, tested, verified, consulted, used, or confirmed. If evidence is unavailable, say what is unknown and what evidence would be required. Tool output is evidence, not automatically a project decision.

### Context Awareness

Before asking the user to repeat information, check the available project context, current conversation, project files, approved plans, and applicable documentation. Use existing context when it is sufficient. If multiple materially different interpretations remain possible, identify the ambiguity and follow the Strict Agent Autonomy rules.

### No Unsupported Generalization

Do not turn one observed example into a project-wide rule without evidence. Inspect related implementations and applicable project rules before establishing a convention.

### Facts, Inferences, and Recommendations

For non-trivial implementation reasoning, distinguish **FACT**, **OBSERVATION**, **INFERENCE**, **RECOMMENDATION**, and **APPROVED DECISION**. Do not collapse these categories when doing so could hide a material decision.

### Tool Use

Use the appropriate available tool when it materially improves accuracy. Existing tool preferences remain authoritative, including Context7 for version-sensitive external documentation, Laravel Boost for applicable Laravel-specific MCP capabilities, EnvKit MCP for supported local environment operations, and project-specific skills. Do not use a tool merely because it exists, and do not claim that a tool establishes facts beyond the evidence it returned.

### External Knowledge vs Project Truth

General framework or package knowledge is not automatically project truth. When external knowledge conflicts with the installed codebase, installed package version, approved specification, or explicit user decision, identify and verify the conflict and follow the applicable authority order. Ask the user when a material decision remains unresolved.

### Communication

Keep progress and final responses concise and information-dense. State what was actually completed, important findings, unresolved decisions, and verification performed. Distinguish completed work from proposed next steps. Do not report hypothetical, intended, or partially completed work as completed.

### Decision Boundaries

The agent may make routine implementation decisions when clearly implied by approved requirements and existing project conventions. Stop and ask the user for material decisions involving product behavior, architecture, security, dependencies, scope, data integrity, destructive operations, conflicting requirements, ambiguous acceptance criteria, or new project-wide conventions.

### Privacy and Sensitive Information

Handle project and user information conservatively. Do not intentionally place secrets, credentials, tokens, private keys, passwords, or sensitive production data into source code, documentation, plans, screenshots, logs, prompts, AI references, changelogs, or test fixtures. Avoid exposing sensitive values in reports. Use placeholders or redaction when documenting sensitive configuration.

### Durable Knowledge

Only promote an observation into a durable project rule when it has been established as a project convention or explicitly approved. Do not turn temporary implementation details or speculative ideas into permanent rules without justification.


## Standard AI Development Tools

### Context7

Context7 is a standard AI reference tool.

Use Context7 for version-sensitive framework, package, SDK, API, setup, configuration, and integration research before implementing against external libraries.

Rules:

- Prefer Context7 for current, version-specific external documentation instead of relying on model memory.
- Target the exact installed or approved package version whenever versioned documentation is available.
- Confirm the installed package version locally before implementation.
- Use Context7 to understand the documented API, then verify compatibility against the actual codebase and tests.
- Context7 does not replace local code inspection, Laravel Boost, tests, security review, or user approval.
- Record important version-specific findings in the implementation plan or `docs/02-research/` when they materially affect the implementation.
- Do not store Context7 credentials, API keys, or tokens in the repository.

### EnvKit

EnvKit is the standard local PHP development environment for supported platforms.

When EnvKit is used, document the project's site, PHP version, database, and required services in this file or the appropriate AI reference.

EnvKit MCP may be used for local environment inspection and management.

#### EnvKit MCP autonomy boundary

Safe/read-only operations may be performed autonomously, including:

- inspect site status
- inspect service status
- inspect diagnostics
- inspect logs
- inspect available versions
- inspect databases and configuration

Destructive or consequential operations require explicit user approval unless they are already covered by an approved implementation plan, including:

- deleting/removing sites
- deleting databases
- destructive data operations
- changing environment state that can materially affect data, services, or other projects
- irreversible or difficult-to-recover environment changes
- Git push/commit operations unless explicitly authorized by the approved workflow

EnvKit is local-only infrastructure. It is not a production or staging management authority.

---

## Laravel Boost

Laravel Boost is an MCP server with Laravel-specific tools.

Prefer Boost tools over manual alternatives when the relevant Boost capability is available.

Use:

- `database-query` for read-only database queries instead of raw SQL in Tinker.
- `database-schema` to inspect database structure before writing migrations or models.
- `get-absolute-url` to resolve the correct scheme, domain, and port before sharing a project URL.
- `browser-logs` for recent browser logs, errors, and exceptions.
- `search-docs` for Laravel ecosystem documentation when the required documentation is available through Boost.

Do not assume an MCP tool exists merely because it is named in documentation. Inspect the available tool capabilities when necessary.

---

## Version Verification

Always use APIs matching the versions actually installed in the project.

Before relying on a package API:

- PHP / Composer packages: `composer show --direct` or `composer show <vendor/package>`
- JavaScript packages: inspect `package.json` and the lockfile when relevant
- Laravel / Filament behavior: use the installed version and appropriate official documentation
- Version-sensitive external documentation: use Context7

Never assume a package version from memory.

---

## Skills Activation

If the project contains domain-specific skills under `**/skills/`, activate the relevant skill whenever working in that domain.

Do not wait until blocked before using a relevant skill.

For testing, read the applicable testing skill before writing or substantially modifying tests.

---

## Project Rules

If `.ai/rules/` exists:

1. Open `.ai/rules/index.md`.
2. Read every rule whose glob covers the files in scope.
3. Search `.ai/rules/` for relevant keywords when necessary to catch rules not obvious from path matching.
4. Follow all matching rules before editing files.

Do not write code until applicable project rules have been read.

Durable project rules should be recorded in `.ai/rules/` using the project's approved rule-recording mechanism.

If `.ai/rules/` does not exist, continue without it.

---

## Tech Stack Baseline

| Layer | Version | Notes |
|---|---|---|
| PHP | `^8.4` | Project baseline |
| Laravel | `^13.0` | Application framework |
| Filament | `^5.6` | Filament v5 |
| Livewire | `^4.0` | Filament 5 stack |
| Alpine.js | `^3.x` | Frontend dependency |
| Tailwind CSS | `^4.x` | Project styling |
| Pest | `^4.0` | Unit + Feature tests |
| Playwright | latest | Standalone E2E |

The actual project's installed versions are authoritative.

### Additional installed packages

Keep this section synchronized with approved direct dependencies.

Do not assume an unlisted package is installed.

---

## Application Structure and Architecture

- Follow the existing directory structure.
- Do not create new base directories without approval.
- Do not change application dependencies without approval.
- Check sibling files before creating or modifying a file.
- Follow existing naming, structure, and implementation conventions.
- Reuse existing components and services before creating new ones.
- Do not perform unrelated refactors.
- Do not silently introduce architectural patterns that are not already established or approved.

---

## Laravel Core

- Use Laravel conventions appropriate to the installed Laravel version.
- Prefer `php artisan make:*` commands for generated Laravel files.
- Use `--no-interaction` for Artisan commands.
- Inspect command help before using unfamiliar options.
- Prefer named routes and `route()` for generated links.
- Use factories for test data and inspect existing factory states before creating manual setup.
- Most application behavior should be covered by Feature tests rather than Unit tests.
- Use Eloquent API Resources and API versioning for APIs unless existing project conventions establish otherwise.
- Do not create models or other persistent structures merely for debugging without approval.

### Artisan

Use:

```bash
php artisan list
php artisan <command> --help
php artisan route:list
php artisan config:show app.name
php artisan config:show database.default
```

Use the narrowest command necessary.

### Tinker

- Prefer existing Artisan commands or tests over custom Tinker code.
- Always use single quotes around the `--execute` argument to avoid shell expansion.
- Do not use Tinker as a substitute for tests.
- Do not create persistent data without approval.

---

## PHP Rules

- Use curly braces for control structures, including single-line bodies.
- Use PHP 8 constructor property promotion where appropriate.
- Do not leave empty zero-parameter constructors unless the constructor is private.
- Use explicit return types.
- Type all method parameters.
- Use TitleCase for Enum keys.
- Prefer PHPDoc blocks for explanatory documentation.
- Use inline comments only for exceptionally complex logic.
- Use array-shape definitions in PHPDoc where they improve type clarity.
- Follow existing project conventions for imports, formatting, and naming.

---

## Filament v5

- Use Filament v5 APIs and conventions only.
- Use the Schemas API for Resource schemas.
- Do not introduce legacy Resource patterns from earlier Filament versions.
- Use Filament-specific Artisan generators where available.
- Inspect command options before generating files.
- Always use `--no-interaction`.

### Authorization

- Resource authorization must be enforced through the appropriate Model Policy.
- Do not assume a fixed set of five Policy methods.
- Wire every ability actually used by the Resource and its actions, including applicable bulk, restore, or force-delete abilities.
- UI visibility is not authorization.
- Server-side authorization is mandatory.
- Panel access must use the project's approved authorization approach.
- Do not add a permissions package without approval through the Package Decision Gate.

### Filament schema patterns

- Use static `make()` methods.
- Use `Get` for conditional form logic.
- Use `Set` with `afterStateUpdated()` for reactive field changes.
- Prefer `live(onBlur: true)` for text inputs when per-keystroke updates are unnecessary.
- Compose layouts using the installed Filament v5 schema components and existing project conventions.
- Use `Repeater` with `relationship()` for appropriate inline HasMany management.
- Use `state()` for derived table values.
- Use appropriate relationship and enum filters.
- Use `Filament\Actions\` for actions.

### Filament namespaces

Follow the installed Filament version. For the current v5 baseline:

- Form fields: `Filament\Forms\Components\`
- Infolist entries: `Filament\Infolists\Components\`
- Layout/schema components: `Filament\Schemas\Components\`
- Schema utilities: `Filament\Schemas\Components\Utilities\`
- Table columns: `Filament\Tables\Columns\`
- Table filters: `Filament\Tables\Filters\`
- Actions: `Filament\Actions\`
- Icons: `Filament\Support\Icons\Heroicon`

Do not use legacy action namespaces.

### Common Filament mistakes

- Never assume public file visibility. Private is the safe default; explicitly configure public visibility when required.
- Never assume a layout spans the full width; configure column spans intentionally.
- Use `Select::make(...)->relationship(...)` for BelongsTo fields according to the installed Filament API.
- Use `Repeater::schema()`, not legacy `fields()`.
- Do not add `dehydrated(false)` to fields that must be persisted.
- Preserve correct property types when overriding Filament Page, Resource, and Widget properties.

---


## Sample Snippets

These snippets are reference patterns. Use them as examples, not as a substitute for checking the installed Filament API and the project's existing conventions.

### Conditional form field visibility

Use `Get $get` to read other form field values for conditional logic:

```php
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

Select::make('type')
    ->options(CompanyType::class)
    ->required()
    ->live(),

TextInput::make('company_name')
    ->required()
    ->visible(fn (Get $get): bool => $get('type') === 'business'),
```

### Reactive field update

Use `Set $set` inside `afterStateUpdated()` on a `live()` field to mutate another field reactively. Prefer `live(onBlur: true)` on text inputs when per-keystroke updates are unnecessary:

```php
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

TextInput::make('title')
    ->required()
    ->live(onBlur: true)
    ->afterStateUpdated(fn (Set $set, ?string $state) => $set(
        'slug',
        Str::slug($state ?? ''),
    )),

TextInput::make('slug')
    ->required(),
```

### Section and Grid layout

Compose layout by nesting `Section` and `Grid`. Configure column spans intentionally:

```php
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

Section::make('Details')
    ->schema([
        Grid::make(2)->schema([
            TextInput::make('first_name')
                ->columnSpan(1),
            TextInput::make('last_name')
                ->columnSpan(1),
            TextInput::make('bio')
                ->columnSpanFull(),
        ]),
    ]),
```

### Repeater for HasMany

Use `Repeater` for appropriate inline `HasMany` management. `relationship()` binds the repeater to the corresponding model relationship:

```php
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

Repeater::make('qualifications')
    ->relationship()
    ->schema([
        TextInput::make('institution')
            ->required(),
        TextInput::make('qualification')
            ->required(),
    ])
    ->columns(2),
```

### Computed table column value

Use `state()` with a closure to compute derived column values:

```php
use Filament\Tables\Columns\TextColumn;

TextColumn::make('full_name')
    ->state(fn (User $record): string => "{$record->first_name} {$record->last_name}"),
```

### Table filters

Use `SelectFilter` for enum or relationship filters, and `Filter` with a `query()` closure for custom logic:

```php
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

SelectFilter::make('status')
    ->options(UserStatus::class),

SelectFilter::make('author')
    ->relationship('author', 'name'),

Filter::make('verified')
    ->query(fn (Builder $query) => $query->whereNotNull('email_verified_at')),
```

### Action with modal form

Actions encapsulate optional modal forms and behavior:

```php
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;

Action::make('updateEmail')
    ->schema([
        TextInput::make('email')
            ->email()
            ->required(),
    ])
    ->action(fn (array $data, User $record) => $record->update($data)),
```

### Table test

```php
use function Pest\Livewire\livewire;

livewire(ListUsers::class)
    ->assertCanSeeTableRecords($users)
    ->searchTable($users->first()->name)
    ->assertCanSeeTableRecords($users->take(1))
    ->assertCanNotSeeTableRecords($users->skip(1));
```

### Create resource test

```php
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

livewire(CreateUser::class)
    ->fillForm([
        'name' => 'Test',
        'email' => 'test@example.com',
    ])
    ->call('create')
    ->assertNotified()
    ->assertHasNoFormErrors()
    ->assertRedirect();

assertDatabaseHas(User::class, [
    'name' => 'Test',
    'email' => 'test@example.com',
]);
```

### Edit resource test

For edit pages, pass the record identifier and call `save()` rather than `create()`:

```php
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

livewire(EditUser::class, ['record' => $user->id])
    ->fillForm(['name' => 'Updated'])
    ->call('save')
    ->assertNotified()
    ->assertHasNoFormErrors();

assertDatabaseHas(User::class, [
    'id' => $user->id,
    'name' => 'Updated',
]);
```

### Testing validation

```php
use function Pest\Livewire\livewire;

livewire(CreateUser::class)
    ->fillForm([
        'name' => null,
        'email' => 'invalid-email',
    ])
    ->call('create')
    ->assertHasFormErrors([
        'name' => 'required',
        'email' => 'email',
    ])
    ->assertNotNotified();
```

### Calling actions

Use the installed Filament testing API for page and table actions. For table actions, use `TestAction::make(...)->table($record)`:

```php
use Filament\Actions\Testing\TestAction;
use function Pest\Livewire\livewire;

livewire(ListUsers::class)
    ->callAction(TestAction::make('promote')->table($user), [
        'role' => 'admin',
    ])
    ->assertNotified();
```

### Correct namespaces

- Form fields (`TextInput`, `Select`, `Repeater`, etc.): `Filament\Forms\Components\`
- Infolist entries (`TextEntry`, `IconEntry`, etc.): `Filament\Infolists\Components\`
- Layout components (`Grid`, `Section`, `Fieldset`, `Tabs`, `Wizard`, etc.): `Filament\Schemas\Components\`
- Schema utilities (`Get`, `Set`, etc.): `Filament\Schemas\Components\Utilities\`
- Table columns (`TextColumn`, `IconColumn`, etc.): `Filament\Tables\Columns\`
- Table filters (`SelectFilter`, `Filter`, etc.): `Filament\Tables\Filters\`
- Actions (`DeleteAction`, `CreateAction`, etc.): `Filament\Actions\`
- Icons: `Filament\Support\Icons\Heroicon`

### Common mistakes

- **Never assume public file visibility.** File visibility is private by default. Explicitly configure public visibility when public access is required.
- **Never assume full-width layout.** `Grid`, `Section`, `Fieldset`, and `Repeater` do not necessarily span all columns by default; configure column spans intentionally.
- **Use relationship selects for BelongsTo fields** according to the installed Filament API.
- **`Repeater` uses `schema()`, not `fields()`.**
- **Do not add `dehydrated(false)` to fields that need to be saved.** Use it only for helper/UI-only fields when appropriate.
- **Preserve correct property types** when overriding `Page`, `Resource`, and `Widget` properties; verify the installed Filament version before overriding framework properties.

## Testing Standard

### Pest

Use Pest for:

- Unit tests in `tests/Unit/`
- Feature tests in `tests/Feature/`
- Filament Resource behavior using the appropriate Livewire testing helpers

Create tests with:

```bash
php artisan make:test --pest SomeFeatureTest
```

Use the narrowest relevant test command.

### Standalone Playwright

Use standalone Playwright for:

- full browser E2E flows
- critical user journeys
- cross-browser checks
- responsive browser checks
- browser-level acceptance verification

Do not duplicate browser coverage in a separate Pest Browser standard.

### Testing requirements

- Test every code change by adding or updating appropriate tests unless the change is genuinely non-testable.
- Run affected tests and ensure they pass.
- Test changed behavior and important failure modes.
- Do not add unrelated test coverage.
- Do not delete tests without approval.
- Use factories and existing factory states.
- Rerun a test after modifying that test.
- After focused tests pass, run the broader suite when appropriate and report exactly what was run.

### Filament testing

For panel functionality, authenticate with the appropriate project user.

For edit pages:

- pass the record identifier using the installed testing API
- call the save action
- do not assume an edit save redirects unless the installed project behavior explicitly does so

Test notifications, validation, database state, authorization, and action behavior where relevant.

---

## Verification and Evidence

Never claim completion based only on code generation.

Before marking work complete, use appropriate evidence:

- focused tests
- broader tests when appropriate
- Laravel Pint
- build checks
- migration/schema verification
- authorization checks
- browser verification
- logs/diagnostics
- package/dependency audit when relevant

State what was actually verified.

Do not claim a test, build, browser flow, command, or tool operation was executed if it was not.

---

## Code Style and Formatting

- Follow project formatting conventions.
- Use Laravel Pint for PHP changes.
- After modifying PHP files, run:

```bash
vendor/bin/pint --dirty --format agent
```

- Do not leave unused imports or dead code created by the change.
- Do not run broad formatting that changes unrelated files unless approved.

---

## Security — Non-Negotiable

Run a security pass after every feature or material behavior change.

Review:

1. Authentication
2. Authorization
3. Record-level access
4. Mass assignment
5. Input validation
6. File upload/storage security
7. Dependency security
8. Rate limiting / abuse protection

Also consider:

- sensitive data exposure
- insecure direct object access
- unsafe file paths
- queue/job authorization
- webhook validation
- logging of secrets
- environment credentials
- browser-accessible private resources

Never treat UI hiding as a security control.

---

## Package Decision Gate

Before adding any Composer/npm package not already approved:

1. Identify the concrete requirement.
2. Confirm the package solves it.
3. Check whether existing Laravel, Filament, PHP, or project functionality is sufficient.
4. Review maintenance, security, compatibility, and cost implications.
5. Stop and ask the user for approval when the dependency is a material decision.

Default: **no new package**.

Do not install packages merely because they are commonly used.

---

## Destructive Change Gate

Stop and ask before:

- deleting files
- dropping tables or columns
- irreversible data migrations
- destructive data transformations
- deleting databases
- removing sites
- replacing important configuration without a reversible plan
- destructive EnvKit MCP operations
- other difficult-to-recover environment changes

An approved implementation plan may authorize such an operation only when the operation and safeguards are explicitly within its approved scope.

---

## Directory Conventions

Use existing project structure. When the Vibe Coding Standard baseline applies:

```text
app/Filament/Resources/{Model}Resource.php
app/Filament/Resources/{Model}Resource/Schemas/
app/Filament/Resources/{Model}Resource/Pages/
app/Filament/Resources/{Model}Resource/RelationManagers/
app/Policies/{Model}Policy.php
app/Mcp/                     (only when MCP integration is approved)
tests/Unit/
tests/Feature/
tests/e2e/                   (standalone Playwright)
```

Do not create these directories merely because they appear in the standard. Follow the actual project structure.

---

## AI Operating Directory

```text
docs/00-project/
├── ai/
│   ├── README.md
│   ├── references/
│   └── plans/
│       ├── pending/
│       └── approved/
├── vibe-coding/
│   ├── standard.md
│   └── guideline.md
├── architecture-decisions/
├── plans/
├── screenshots/
├── prompts/
└── followups/
```

### AI references

Use `docs/00-project/ai/references/` for supporting project-specific context such as:

- domain rules
- naming conventions
- UI conventions
- testing conventions
- integration notes
- approved AI tooling configuration

References are advisory. They do not override current requirements or authority.

### AI plans

- `pending/` contains plans awaiting user approval.
- `approved/` contains plans that have received explicit user approval and are being executed.
- The user must explicitly approve the plan in conversation.
- A file containing `APPROVED` is not sufficient evidence of user approval by itself.

### Project plans

`docs/00-project/ai/plans/` is the AI working/approval workflow.

`docs/00-project/plans/` is durable project documentation for owner/reference.

Do not silently use one as a substitute for the other.

---

## Tolaria Vault

Tolaria Vault is a documentation library for the project owner's reference.

It is not:

- an AI authority
- an instruction source
- a decision engine
- an enforcement mechanism
- an execution controller
- a replacement for current user instructions, approved specifications, `CLAUDE.md`, or the Vibe Coding Standard

### Standard documentation paths

```text
docs/00-project/architecture-decisions/
docs/00-project/plans/
docs/00-project/screenshots/
docs/00-project/prompts/
docs/00-project/followups/
docs/01-issues/
docs/02-research/
docs/03-daily-logs/
docs/04-changelog/CHANGELOG.md
```

### Documentation rules

- Store approved/meaningful durable task plans in `docs/00-project/plans/`.
- Store non-obvious architectural decisions in `docs/00-project/architecture-decisions/`.
- Store selected permanent visual evidence in `docs/00-project/screenshots/`.
- Store task prompt references in `docs/00-project/prompts/`.
- Store relevant follow-up prompts in `docs/00-project/followups/`.
- Do not create a follow-up document when the prompt is exactly `continue`.
- Store issue documentation in `docs/01-issues/`.
- Store framework/package/technical research in `docs/02-research/`.
- Store meaningful session summaries in `docs/03-daily-logs/`.
- Update the changelog for meaningful project-level changes, not every edit.
- Never intentionally store secrets, credentials, tokens, or sensitive production data.

Only create documentation when required by the Vibe Coding workflow, project rules, an approved task, or an explicit user request.

---

## Documentation Research

When research materially affects implementation:

- record the package/framework version
- record the source/tool used
- record the date when information may become stale
- distinguish documented behavior from project-specific inference
- do not treat historical research as current authority without verification

For version-sensitive external dependencies, Context7 is the standard AI reference tool.

---

## Deviation Protocol

If implementation intentionally deviates from the Vibe Coding Standard:

1. Identify the rule.
2. Explain why the project requires the deviation.
3. State the tradeoff.
4. Ask the user when it is a material decision.
5. Update `CLAUDE.md` if the deviation becomes a standing project rule.

Do not silently weaken or bypass a standard.

---

## Task Contract

For non-trivial agentic tasks, establish:

1. Objective
2. Scope
3. Governing inputs
4. Actions
5. Constraints
6. Expected output
7. Verification
8. Council audit
9. Stop conditions

Do not expand scope because an unrelated improvement is noticed.

---

## Progress Reporting

After each meaningful step, provide one concise line describing what was completed before moving to the next step.

Do not report hypothetical work as completed work.

---

## Frontend Bundling

If a frontend change is not reflected locally:

- determine whether the project uses `npm run build`
- `npm run dev`
- `composer run dev`
- EnvKit's frontend tooling
- another project-specific development command

Do not blindly run development servers when the environment already manages them.

---

## Laravel / Local Environment

If the project uses EnvKit, follow its documented project configuration.

If the project uses another local environment, follow that project's actual environment contract.

Do not assume Herd, Laragon, Docker, EnvKit, or another local stack is installed merely because it appears in a standard document.

Never run a command to serve the site when the project's environment already manages the web server.

Before sharing a project URL, use the project's URL resolution mechanism, such as Laravel Boost's `get-absolute-url`, when available.

---

## Deployment

Deployment is outside the authority of a local development stack such as EnvKit.

Follow the project's explicit deployment configuration and approved deployment plan.

Do not deploy production changes without the required approval.

---

## Agent Behavior Summary

```text
Current requirement          -> follow it
Approved plan                -> execute within scope
Existing convention          -> follow it
Context7 documentation       -> use for version-sensitive research
Laravel Boost                -> prefer when applicable
EnvKit read-only inspection  -> may perform autonomously
EnvKit destructive action    -> approval required unless explicitly planned
New dependency               -> approval required
Architecture decision        -> ask user
Product decision             -> ask user
Material security decision   -> ask user
Ambiguous requirement       -> ask user
Destructive change           -> ask user
Material deviation           -> ask user
Completion claim             -> provide evidence
```

---

## Living Contract

`CLAUDE.md` must remain aligned with the actual project.

When the project changes:

- update installed versions
- update approved dependencies
- update directory conventions
- update testing conventions
- update architectural rules
- update security requirements
- update local environment configuration
- update approved AI tooling
- document approved deviations

Do not allow this file to become a second, conflicting source of truth.

