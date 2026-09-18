# AI Operating Directory

This directory contains AI-facing working material for the project.

It is separate from the Tolaria Vault documentation library.

## Structure

```text
docs/00-project/ai/
├── README.md
├── references/
│   └── README.md
└── plans/
    ├── pending/
    └── approved/
```

### `references/`

Supporting project-specific reference material that helps the AI understand conventions and existing decisions.

Examples:

- `domain-rules.md`
- `naming-conventions.md`
- `ui-conventions.md`
- `testing-conventions.md`
- `integration-notes.md`

References are supporting context, not authority. They must not override current user requirements, the PRD, approved specifications, `CLAUDE.md`, or the Vibe Coding Standard.

### `plans/pending/`

AI-generated implementation plans awaiting explicit user approval.

A non-trivial task must not enter implementation while its plan is `PENDING` or `CHANGES_REQUESTED`.

### `plans/approved/`

The approved implementation-plan record used during execution.

Approval happens in the user/agent conversation. Writing `APPROVED` into a file by itself is not approval.

## Relationship to `docs/00-project/plans/`

These directories have different purposes:

- `docs/00-project/ai/plans/` — AI working plans and approval workflow.
- `docs/00-project/plans/` — durable project documentation for owner/reference.

After a plan is approved, the AI plan may remain in `ai/plans/approved/` as execution history. If a durable project plan is also needed, create the appropriate owner-facing record under `docs/00-project/plans/`.

Do not treat these directories as interchangeable.

## Safety

Do not store secrets, credentials, tokens, private keys, or sensitive production data here.


## Standard AI tooling

Context7 is the standard reference tool for version-sensitive framework, package, SDK, API, setup, and configuration research. EnvKit is the standard local PHP environment for supported platforms, with bounded MCP autonomy for local environment operations.
