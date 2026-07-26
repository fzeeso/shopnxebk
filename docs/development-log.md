# Development log

Record meaningful changes to behavior, architecture, dependencies, schemas, operations, or developer workflow. Keep entries concise; this is not a copy of Git history.

Generated facts live in the [system inventory](generated/system-inventory.md). Run `composer docs:update` before completing an entry.

## Entry template

```markdown
## YYYY-MM-DD — Short change title

- Changed:
- Reason:
- Data/configuration impact:
- Compatibility or rollout notes:
- Verification:
```

## 2026-07-26 — Infrastructure-only development Compose

- Changed: Replaced the full application Compose stack with Redis, Meilisearch, Mailpit, and MinIO services.
- Reason: Run Laravel and the existing PostgreSQL installation directly on the Windows/XAMPP host while Docker supplies supporting infrastructure.
- Data/configuration impact: Compose no longer starts the Laravel application or PostgreSQL. Host application configuration uses `127.0.0.1` for all exposed services.
- Compatibility or rollout notes: Local Meilisearch uses the configured development master key, and MinIO uses the explicitly provided local-only credentials.
- Verification: Validated the Compose configuration, refreshed the generated developer inventory, ran formatting, and ran PostgreSQL-backed tests.

## 2026-07-26 — Connect Laravel to local infrastructure

- Changed: Configured the untracked local Laravel environment to use Docker Redis, Meilisearch, Mailpit SMTP, and MinIO S3-compatible storage.
- Reason: Exercise the configured production-style integrations during local development instead of leaving the supporting containers unused.
- Data/configuration impact: Search uses Meilisearch, cache/session/queues use Redis, outgoing development mail uses Mailpit, and default/media storage uses the local MinIO bucket.
- Compatibility or rollout notes: Local credentials remain in the untracked `.env` only. The configured MinIO bucket must exist before storage writes.
- Verification: Cleared Laravel configuration, verified effective configuration without exposing secrets, refreshed documentation, and ran PostgreSQL-backed tests.

## 2026-07-24 — Living developer guide

- Changed: Added the developer guide, generated system inventory, documentation commands, and CI stale-document check.
- Reason: Give developers one end-to-end explanation of the installed foundation, information flows, execution processes, and safe change workflow.
- Data/configuration impact: No runtime data or environment changes.
- Compatibility or rollout notes: Narrative decisions remain human-maintained; repository facts are generated deterministically.
- Verification: Generated and checked the inventory, formatted code, and ran the PostgreSQL-backed test suite.

## 2026-07-22 — Backend foundation

- Changed: Bootstrapped the Laravel 13 API-only backend with Authentication and Tenancy modules, PostgreSQL UUID persistence, REST authentication, GraphQL, Redis/Horizon, search, private media, Reverb, Octane, observability, health endpoints, tests, CI, and architecture documentation.
- Reason: Establish the production-oriented ShopNXE SaaS foundation before commerce modules.
- Data/configuration impact: Added foundational users, tenants, memberships, tokens, permissions, queue, notification, media, Pulse, and Telescope migrations plus the environment contract.
- Compatibility or rollout notes: PostgreSQL is mandatory for tests. Commerce modules remain intentionally deferred.
- Verification: Applied migrations, verified routes, passed Pint, and passed the PostgreSQL-backed test suite.
