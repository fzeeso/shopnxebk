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

## 2026-07-29 — Platform and Store language settings

- Changed: Added the 21-entry language catalog, Store language selections with one PostgreSQL-enforced default, public language ULIDs, platform catalog list/create endpoints, Store list/update endpoints, and the `manage platform settings` permission for Super Admin.
- Reason: Let ShopNXE control supported languages centrally while each merchant chooses the languages and default locale their Store operates in.
- Data/configuration impact: Adds `languages` and `store_languages`; seeding is idempotent and backfills existing Stores from `stores.language_code`, falling back to English.
- Compatibility or rollout notes: Run the migration and seeder before using the admin language screen. Store updates keep `stores.language_code` synchronized for existing consumers. Numeric identifiers remain internal.
- Verification: Added PostgreSQL feature coverage for all 21 seeds, RTL data, Super Admin creation, Support denial, Store selection/default persistence, compatibility-field synchronization, permissions, and cross-scope rejection.

## 2026-07-29 — Exclusive Platform and Store user scopes

- Changed: Added mandatory `users.scope` (`platform` or `store`), scope-aware API/GraphQL resources, scoped role-assignment service, route/middleware checks, Store-token restrictions, and PostgreSQL enforcement triggers.
- Reason: Make Platform Admin/staff and Store Admin/staff separate account classes so neither can receive the other class’s memberships, roles, permissions, tokens, interfaces, or data.
- Data/configuration impact: Existing users with global Platform assignments were classified as Platform; all others remain Store. The migration refuses ambiguous mixed accounts. `admin@shopnxe.com` is Platform-scoped with `Super Admin`; four existing merchant accounts are Store-scoped.
- Compatibility or rollout notes: Accounts can no longer access both interfaces. Remove all memberships/assignments before deliberately changing a user’s scope. Use `ScopedRoleAssignmentService`; direct incompatible pivot writes are rejected by PostgreSQL.
- Verification: Proved fresh and existing migrations, confirmed zero cross-scope conflicts, and added Platform/Store interface, role, Store route, token, and context isolation coverage.

## 2026-07-29 — Store profile, lifecycle, and capabilities

- Changed: Expanded `stores` with legal/contact details, branding references, industry/business type, internal Billing links, locale, lifecycle dates/statuses, verification, and AI/POS/B2B/marketplace switches; added typed casts, public-safe REST/GraphQL fields, factory defaults, and database value checks.
- Reason: Make Store onboarding and merchant configuration explicit first-class data while preserving the internal-bigint/public-ULID boundary.
- Data/configuration impact: Added nullable profile/media/Billing columns, `USD`/`en`/`UTC` defaults, false capability defaults, and the `pending`, `active`, `suspended`, `cancelled` plus six-business-type constraints. Existing Store records retain their data and receive defaults where applicable.
- Compatibility or rollout notes: Run migrations before deploying the updated model. `plan_id` and `subscription_id` remain internal nullable keys without foreign constraints until Billing exists; media values are storage references. No Store profile write endpoint is included.
- Verification: Added PostgreSQL-backed Store cast, persistence, REST, GraphQL, registration-default, and internal-ID non-disclosure coverage.

## 2026-07-29 — Scoped administration interfaces

- Changed: Added `UserInterfaceAccessService` and authenticated `GET /api/v1/auth/interfaces` to distinguish the Platform Admin (SaaS Owner/platform staff) interface from the Store Admin (Merchant/Store staff) interface.
- Reason: Let clients select the correct application shell and navigation from roles/permissions without introducing separate user tables or a fixed user-type column.
- Data/configuration impact: No schema or environment changes. The response contains Platform roles/permissions and active Stores with isolated Store roles/permissions, exposing only public ULIDs.
- Compatibility or rollout notes: This entry records the endpoint’s initial dual-interface behavior, which was superseded by the later exclusive Platform/Store user-scope change.
- Verification: Initial dual-interface coverage was replaced by exclusive account-scope and cross-boundary rejection tests.

## 2026-07-28 — Store terminology, bigint keys, public ULIDs, and scoped authorization

- Changed: Replaced the application’s Tenancy module/domain vocabulary with Stores; changed domain primary and foreign keys to bigint; added public ULIDs; unified every staff/merchant identity in `users`; added extendable platform/Store roles and permissions; updated REST, GraphQL, tokens, channels, media paths, tests, and module contracts.
- Reason: Keep joins and internal queries efficient while exposing non-sequential identifiers, make Store language clear throughout the product, and replace fixed user-type flags with extendable scoped authorization.
- Data/configuration impact: Added a transactional PostgreSQL migration that preserves legacy users, stores, memberships, tokens, MFA data, and authorization assignments while mapping UUIDs to bigint IDs/ULIDs. `X-Store-ID`, `store_id`, `activeStore`, `viewerStores`, and `/api/v1/auth/stores` are the public contracts.
- Compatibility or rollout notes: Tenant-named application APIs are intentionally replaced, so clients must move to Store names and ULIDs. Existing bearer credentials remain valid through a private legacy token-key lookup. Third-party Spatie Multitenancy class/config names remain vendor terminology. A database backup is required before production rollout; the migration is intentionally irreversible.
- Verification: Proved fresh migrations, rehearsed the upgrade against a clone of the existing PostgreSQL database, verified record/role preservation and ULID shape, ran PostgreSQL-backed feature tests, Pint, documentation checks, and PHPStan over `app` and `Modules`.

## 2026-07-28 — TOTP multi-factor authentication

- Changed: Added Fortify-backed TOTP enrollment, QR provisioning, confirmation, encrypted recovery codes, MFA management, and two-stage session and store-token login.
- Reason: Require a standards-compatible second factor before issuing authenticated sessions or Sanctum bearer tokens for MFA-enabled users.
- Data/configuration impact: Added nullable MFA columns to `users`, Fortify and its locked dependencies, Redis/cache-backed short-lived challenges, TOTP replay markers, MFA rate limits, and optional challenge tuning environment variables.
- Compatibility or rollout notes: Existing users remain password-only until they confirm MFA. Google Authenticator, Microsoft Authenticator, Authy mobile, 1Password, and standard TOTP apps are supported. Preserve `APP_KEY`; rotating it requires an explicit MFA re-enrollment plan.
- Verification: Added PostgreSQL-backed feature coverage for enrollment, encrypted recovery material, session and token challenges, recovery-code consumption, and replay rejection; refreshed generated documentation and ran formatting and documentation checks.

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

- Changed: Bootstrapped the Laravel 13 API-only backend with Authentication and Stores modules, initial PostgreSQL UUID persistence, REST authentication, GraphQL, Redis/Horizon, search, private media, Reverb, Octane, observability, health endpoints, tests, CI, and architecture documentation.
- Reason: Establish the production-oriented ShopNXE SaaS foundation before commerce modules.
- Data/configuration impact: Added foundational users, stores, memberships, tokens, permissions, queue, notification, media, Pulse, and Telescope migrations plus the environment contract.
- Compatibility or rollout notes: PostgreSQL is mandatory for tests. Commerce modules remain intentionally deferred.
- Verification: Applied migrations, verified routes, passed Pint, and passed the PostgreSQL-backed test suite.
