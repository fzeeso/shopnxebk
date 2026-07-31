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

## 2026-07-31 — Consistent management-list pagination

- Changed: Added shared `page`/`per_page` validation and Laravel `data`/`links`/`meta` responses to token, Platform user, Store, merchant, plan, feature, currency, language, and selected-Store-user lists.
- Reason: Every table-style administration view needs bounded paging and stable navigation/count metadata.
- Data/configuration impact: No migration or environment change. Page size defaults to 25 and is capped at 100; selector/option lists remain unpaginated.
- Compatibility or rollout notes: Record arrays remain under `data`; clients should now read `links` and `meta` and request additional pages instead of assuming one complete management collection.
- Verification: Passed Pint, PHPStan, documentation checks, and the full PostgreSQL suite with 29 tests and 395 assertions, including page boundaries, Store isolation, metadata shape, and maximum page size.

## 2026-07-31 — Direct Platform Store administration

- Changed: Completed the scope-safe `/api/v1/platform/stores*` list, create, detail, and edit contracts with shared request authorization/validation, normalized filters, pagination, public resources, and focused PostgreSQL coverage.
- Reason: Let the separate Platform Admin frontend manage Store profiles directly while keeping merchant-owner provisioning and future Billing/subscription workflows independent.
- Data/configuration impact: No migration was required. Direct Store creation produces an unassigned `pending` Store and accepts only validated public profile, locale, lifecycle, verification, branding-reference, and capability fields.
- Compatibility or rollout notes: Use `/api/v1/platform/merchants*` when an owner, membership, and Store role must be created together. Plans, subscriptions, raw settings/metadata, owners, and roles remain prohibited in direct Store writes.
- Verification: Passed targeted Pint, full Pint checks, PHPStan, strict Composer validation, documentation inventory/checks, the focused Stores feature suite (7 tests, 92 assertions), and the settled full PostgreSQL suite (29 tests, 395 assertions), including direct create, search/filter/page, detail, edit, permission, unsafe-input, and missing-Store scenarios.

## 2026-07-31 — Scope-safe user and merchant provisioning

- Changed: Added Platform user/role create, view, list, and edit APIs; Platform merchant/merchant-role create, view, list, and edit APIs; selected-Store user/role APIs; transactional services; `manage platform users`; permission-filtered Admin Users/Merchants navigation metadata; and opt-in local test-account seed actions.
- Reason: Let SaaS administrators create Platform staff and complete merchant accounts while letting Store owners create their own staff, without mixing Platform and Store identities or roles.
- Data/configuration impact: No migration was required. The authorization catalog gains `manage platform users`; `.env.example` gains empty local fixture variables. The ignored local `.env` can seed one verified all-Platform-role test admin and one verified all-Store-role test merchant.
- Compatibility or rollout notes: “All roles” is scope-specific. Platform accounts never receive memberships; Store accounts never receive Platform roles. The backend remains API-only, so `/admin/users` and `/admin/merchants` are navigation hints for a separate frontend.
- Verification: Registered the routes, passed Pint, PHPStan, strict Composer validation, documentation inventory checks, credential/hash checks for both local fixtures, and the full PostgreSQL suite with 28 tests and 265 assertions.

## 2026-07-30 — Low-disruption security hardening

- Changed: Blocked XAMPP HTTP access outside `public/`, bound development Compose ports to localhost, added a token prefix and 30-day default expiry, scheduled expired-token pruning, enforced Store token ability/binding, revoked bearer tokens on password reset, added registration/token-management rate limits, made internal-dashboard IP checks fail closed, restricted new Store currency/language choices to active Platform catalog entries, and repaired protected missing paths through conventional internal service/seeder/test renames.
- Reason: Close the confirmed local source/secret exposure and strengthen account and Store boundaries without rotating credentials, changing encryption keys, or introducing disruptive schema changes.
- Data/configuration impact: Adds `AUTH_TOKEN_TTL_MINUTES` and `SANCTUM_TOKEN_PREFIX`; no migration or existing catalog row changes. Existing expired/unbound tokens may be rejected as intended, while stateful browser sessions continue to use the same APIs.
- Compatibility or rollout notes: Recreate Compose services when convenient to apply localhost port bindings. Configure the web server with `public/` as its document root. Credential rotation, mandatory Platform MFA, database least privilege/TLS, persistent infrastructure volumes, and backup automation remain separate controlled work.
- Verification: Confirmed sensitive XAMPP paths return `403` while the public health endpoint returns `200`; validated Compose and Composer configuration; passed Pint, PHPStan, documentation checks, and the full PostgreSQL suite with 23 tests and 219 assertions.

## 2026-07-30 — Platform Settings module boundary

- Changed: Added `Modules/Settings` as the owner of the global language and currency catalogs, moved Platform controllers/services/models/routes out of Stores, added canonical `/api/v1/platform/settings/*` routes, a permission-filtered `/admin/settings` navigation hint, language editing, admin-shell/Platform Settings/localization component contracts, and retained the former Platform routes as aliases.
- Reason: Platform administrators manage global SaaS configuration through one extensible Settings boundary; Store Management should own only merchant data and Store-specific selections.
- Data/configuration impact: Existing `languages` and `currencies` tables and migration names are preserved. The Store-owned `store_languages` migration is idempotent for upgraded databases. Seeding now separates master-catalog maintenance from Store-default backfilling.
- Compatibility or rollout notes: Existing `/api/v1/platform/languages*` and `/api/v1/platform/currencies*` clients continue to work. New admin clients should use `/api/v1/platform/settings/*`. Language locale and currency code remain immutable after creation.
- Verification: Registered canonical and legacy routes, passed PHP syntax and Settings PHPStan checks, and passed the full PostgreSQL suite with 22 tests and 205 assertions.

## 2026-07-30 — Hindi, Urdu, and Persian language support

- Changed: Expanded the idempotent master language catalog from 21 to 24 entries with Hindi (`hi`), Urdu (`ur`), and Persian (`fa`), including native names and script directions.
- Reason: Let merchants select these languages while allowing the Next.js admin to resolve matching interface dictionaries.
- Data/configuration impact: Seeding adds or refreshes the three active catalog rows. Hindi is LTR; Urdu and Persian are RTL.
- Compatibility or rollout notes: Existing Store language selections are unchanged. Run the catalog action or application seeder to add the rows to an existing database.
- Verification: Added PostgreSQL-backed coverage for all three rows, direction metadata, the 24-row total, and idempotent updates.

## 2026-07-29 — Platform Plans & Pricing administration

- Changed: Added the Billing module with `plans`, reusable `features`, `plan_features`, fixed/custom minor-unit prices, optional add-ons, Platform CRUD services/routes/resources, safe deletion rules, a permission-filtered `Plans & Pricing` menu item, and an idempotent seven-plan sample catalog.
- Reason: Let Platform administrators change plan prices and feature composition without code changes while keeping Billing administration completely separate from Store accounts.
- Data/configuration impact: Added three bigint/ULID tables and enabled the Billing module. Seeded Launch 1, Launch 5, Starter, Growth, Professional, Business, Enterprise, and the supplied Launch feature assignments without overwriting later edits.
- Compatibility or rollout notes: All writes require Platform scope plus `manage plans`. Prices are integer minor units. Plans referenced by Stores must be archived; subscription/provider/invoice workflows remain future work.
- Verification: Applied the PostgreSQL migration, seeded and queried the local catalog, registered all Platform routes, and passed four focused tests with 36 assertions.

## 2026-07-29 — Store profile and settings services

- Changed: Added Store creation, view, profile update, and settings services plus Store-scoped REST controllers, Form Requests, resources, and route registration.
- Reason: Give Store owners/managers a clear application-service flow for maintaining only their own merchant information and validated settings.
- Data/configuration impact: No additional Store columns. Settings updates merge approved preference keys; creating another Store provisions an active Owner membership and role.
- Compatibility or rollout notes: Existing Store operations require `X-Store-ID` and active membership; writes require `manage store`. Merchant requests cannot modify Platform-controlled Billing, lifecycle, verification, entitlement, trial, or raw JSON fields.
- Verification: Registered the five Store routes and added membership, role, cross-Store, Platform-account, and protected-field coverage.

## 2026-07-29 — Platform currency catalog and USD exchange rates

- Changed: Added a 25-entry currency catalog, an immutable USD base rate, display-format metadata, public currency ULIDs, and Platform list/create/update REST endpoints protected by `manage platform settings`.
- Reason: Give the ShopNXE Platform Admin one authoritative place to maintain currency presentation and manual USD-relative exchange rates for future Store and Billing workflows.
- Data/configuration impact: Adds `currencies`. USD is constrained to active base rate `1`; non-USD rates start null and use the convention `1 USD = X target currency units`. Seeding is idempotent and preserves administrator-entered rates.
- Compatibility or rollout notes: Run the migration and seeder before opening the admin currency screen. `stores.currency_code` remains unchanged. This release does not fetch rates from an external financial provider.
- Verification: Added PostgreSQL feature coverage for all 25 seed rows, USD invariants, idempotent rate preservation, Platform role permissions, public REST contracts, create/update behavior, and Support write denial.

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
