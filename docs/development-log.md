# Development log

## 2026-08-17 - Large-policy automatic translation capacity

- Changed: Raised the OpenAI translation timeout default to 75 seconds and
  made the structured-response output allowance configurable, defaulting to
  16,000 tokens with the provider maximum enforced.
- Reason: Translating an approximately 8,000-character policy into three Store
  locales exceeded the former 30-second and 4,500-token limits, producing
  connection timeouts followed by truncated invalid JSON.
- Data/configuration impact: Added
  `OPENAI_TRANSLATION_MAX_OUTPUT_TOKENS=16000` and changed the tracked
  `OPENAI_TRANSLATION_TIMEOUT` default to `75`; no schema changes are required.
- Compatibility or rollout notes: Queue jobs retain their 90-second worker
  limit. Deployments with explicit environment overrides keep those values,
  subject to the provider's 16,000-token safety cap.
- Verification: OpenAI provider unit tests, formatting, generated-document
  update/check, and live Redis translation-worker processing.

## 2026-08-13 - Durable after-commit automatic translations

- Changed: Brand and default-language Store-policy writes now commit source
  content plus a deduplicated `translation_requests` ledger row before
  dispatching `TranslateContentJob`. Registered Brand and policy content
  handlers snapshot active/unlocked targets, invoke OpenAI outside database
  transactions, revalidate hashes, and apply results in short transactions.
  Added a tenant-scoped status endpoint and nullable `translation_request`
  response objects. The policy controller's co-located translation workflow
  now owns source/version writes before handing eligible work to the shared
  coordinator. Restored the Stores-owned `StoreSetting` model, including the
  translation/search opt-in casts, because provisioning referenced the missing
  class and could not create Stores. New Store provisioning now immediately
  selects its configured/default language when the language catalog exists, so
  source content is valid for Brand and policy translation from the first edit.
- Reason: External AI latency must not hold PostgreSQL locks/connections or
  slow unrelated Store actions, and provider failure must not discard a valid
  merchant source edit.
- Data/configuration impact: Added `translation_requests`, the dedicated
  `translations` queue, translation retry/recovery settings, and isolated
  Horizon supervisors for critical, default, translation, and heavy jobs. A
  minute scheduler recovers stranded pending and stale processing requests.
- Compatibility or rollout notes: In Redis-backed environments translations
  are eventually consistent; clients may poll
  `/api/v1/store/translation-requests/{public-ulid}`. The `sync` test queue
  preserves immediate test behavior. Locked rows remain protected. Changed
  source/target snapshots are superseded rather than overwritten. Native
  Windows uses `queue:work` because Horizon requires `pcntl`; Linux deployment
  keeps the isolated Horizon supervisors.
- Verification: Development migrations, route/scheduler discovery, Pint,
  generated-doc update/check, OpenAI provider unit tests, and the complete
  PostgreSQL-backed suite pass after synchronizing the local test database
  password: 18 tests and 268 assertions. Full PHPStan reaches only two pre-existing
  missing-model errors in Catalog and Stores; native-Windows Larastan also
  deletes explicitly targeted source files during Testbench cleanup, so final
  restored endpoint verification uses PHP syntax, container, and route checks.

## 2026-08-10 - Shared OpenAI translation provider for Brand and policies

- Changed: Added one field-agnostic `TranslationProvider`, backed by the OpenAI
  Responses API with strict structured output, for every automatic translation
  workflow. Brand create and translation-bearing edits translate the source
  name, description, and SEO fields; default-language Store-policy saves
  translate title, content, and SEO fields. Metadata-only Brand edits translate
  only newly missing locale rows. Generated Brand slugs remain Store/locale
  unique, and generated policy content appends language-scoped versions. The
  active Brand transaction service now lives at
  `Modules\Catalog\Services\BrandManagementService` with its controller after
  the former application-support service path became unavailable.
- Reason: The earlier locale fan-out created rows but copied the source text, so
  merchants saw English content under Arabic, German, and other language flags.
- Data/configuration impact: Added server-only `OPENAI_API_KEY`, optional
  `OPENAI_TRANSLATION_MODEL` (default `gpt-5-mini`), and optional
  `OPENAI_TRANSLATION_TIMEOUT` (default 30 seconds). No database migration is
  required.
- Compatibility or rollout notes: `lock_it = true` saves merchant-authored
  content directly and excludes that locale from OpenAI refreshes. Sending
  `lock_it = false` opts it back into a later automatic refresh. Non-default
  policy-language saves do not cascade. API failures roll back the source write
  with a validation error; API keys and merchant content are not logged.
- Verification: PHP syntax and focused Pint checks pass. A live generic OpenAI
  smoke request succeeded with strict JSON output. The root `routes/api.php`
  was restored, Laravel caches clear successfully, health and authentication
  routes boot, direct plus frontend-proxied health checks return HTTP 200, and
  the OpenAI translation unit tests pass. PostgreSQL feature tests remain
  blocked because the configured `shopnxe_test` password is rejected. Generated
  documentation checks remain blocked because
  `scripts/update-developer-guide.php` is absent from the current checkout.

## 2026-08-09 — Brand translation generation on create and edit

- Changed: Brand create explicitly persists every active Store locale, and all
  Brand edits backfill any missing locale rows from the saved default-language
  source. Existing locale rows remain untouched by metadata-only backfills;
  translation-bearing updates continue through `AutomatedTranslationWriter`.
- Reason: A Brand could be created or later opened for editing without visible
  values for languages that did not have an explicit custom form entry.
- Data/configuration impact: Existing Brands gain missing translation rows the
  next time they are edited. No migration or configuration change is required.
- Compatibility or rollout notes: Custom rows with `lock_it = true` remain
  protected. Unlocked translations explicitly submitted by the merchant keep
  the established update behavior.
- Verification: PHP syntax, focused static analysis, frontend documentation,
  typecheck, lint, production build, and a PostgreSQL-backed missing-locale
  edit scenario are required before handoff.

## 2026-08-09 — Signed private Brand media previews

- Changed: Brand response media slots now contain 15-minute relative signed
  URLs, and a signature-protected Brand endpoint streams only the existing
  `image` or `banner` object from its configured Media Library disk. Brand
  routes now register from `routes/brand-api.php`, loaded by
  `AppServiceProvider`, after removal of the module's separate provider and
  route include. The provider retains Catalog config/migration discovery, and
  the active module controller co-locates its response resource after removal
  of the standalone response file.
- Reason: Brand edit already consumed the saved logo/banner URLs, but private
  disk objects were represented by `/storage/...` URLs that target Laravel's
  public-storage symlink and therefore could not render.
- Data/configuration impact: None. Existing media rows and files remain on
  their current disks; no global private-storage serving surface is enabled.
- Compatibility or rollout notes: Clients must treat `image.url` and
  `banner.url` as ephemeral and use them before expiry. Brand CRUD remains
  authenticated and Store-scoped; the media read itself is authorized by its
  unmodified signature and is limited to the two Brand media collections.
- Verification: PHP syntax, route registration, Pint, generated documentation
  freshness, a `200 image/png` response for an existing private logo, a `403`
  response without the signature, and a `404` response for a correctly signed
  but absent banner all passed. No dedicated Brand feature test currently
  exists in the backend suite.

## 2026-08-09 — Restore Brand API response resource

- Changed: Restored the shared `App\Support\Media\BrandResource` required by
  both Brand controllers, including image/banner metadata and translation
  `lock_it` serialization.
- Reason: Brand list, detail, create, and update requests failed during
  controller resolution with `Class "App\Support\Media\BrandResource" not
  found` after the response resource was removed while its imports remained.
- Data/configuration impact: None. The restored class serializes the existing
  Brand, translation, and Media Library models without changing persistence.
- Compatibility or rollout notes: The established Store Brand response shape
  is restored; no client payload or endpoint changes are required.
- Verification: Confirmed PHP syntax and Composer autoload resolution, then ran
  Brand route inspection, Pint, and generated documentation update/freshness.
  The focused PostgreSQL-backed regression could not start because the local
  `shopnxe_test` credentials were rejected by PostgreSQL.

## 2026-08-09 — Automatic disabled Store policy catalog

- Changed: Store provisioning and direct Platform Store creation now create one
  editable `disabled` Store policy for every master policy type. New custom
  types propagate to existing Stores, existing Stores are backfilled, and the
  Store policy API adds explicit enable/disable actions. DELETE now disables
  non-destructively instead of removing translations and version history.
- Reason: Every Store needs a complete, predictable policy catalog at creation
  while allowing merchants to complete and publish only the policies they use.
- Data/configuration impact: The Stores migration expands the PostgreSQL status
  constraint to `disabled`, `draft`, and `published`, enforces null publication
  time outside `published`, and backfills missing Store/type pairs as disabled.
- Compatibility or rollout notes: Existing list, update, translation, publish,
  unpublish, version, and storefront routes remain available. Clients should
  use the new enable/disable routes; DELETE returns 204 but preserves the row.
- Verification: Applied the migration and ran focused PostgreSQL lifecycle/API
  coverage, Pint, PHPStan, route inspection, generated documentation update and
  freshness checks.

## 2026-08-09 - Store translation and Platform-search flags

- Changed: Added `auto_store_translation_flag` and
  `is_searchable_on_platform_flag` to `store_settings`, with model casts,
  provisioning defaults, Store settings API reads/writes, OpenAPI coverage, and
  PostgreSQL-backed feature coverage.
- Reason: Stores need explicit opt-ins for future automatic content translation
  and Platform discovery without placing stable switches in JSON settings.
- Data/configuration impact: An additive migration creates two non-null boolean
  columns defaulting to `false`, so existing and new Stores remain opted out.
- Compatibility or rollout notes: The flags persist merchant intent only; this
  change does not start translation jobs or change Platform search indexing.
- Verification: Run the focused Store settings feature test, Pint, generated
  documentation checks, and the relevant PostgreSQL-backed suite.

## 2026-08-09 - Brand media and Store-language synchronization

- Changed: Extended Store Brand create, update, list, detail, and delete with
  managed single-file image/banner uploads plus automatic translation rows for
  every active Store language. Translation refreshes use the shared automated
  writer and preserve locked locales.
- Reason: Merchants need complete localized Brand records without manually
  repeating every locale, and Brand imagery must be owned and cleaned up by the
  media layer instead of relying only on external URL strings.
- Data/configuration impact: No new Brand columns are required. Uploads use the
  existing Store-scoped `media` table and configured Media Library disk in the
  `image` and `banner` collections. The existing `logo_url` remains compatible.
- Compatibility or rollout notes: Send JPEG, PNG, WebP, or AVIF files as
  multipart `image`/`banner` fields. Missing active locales inherit the submitted
  default-locale (or first) content. Set `lock_it` to protect a locale; submit
  `lock_it = false` to unlock it before a refresh. Brand deletion now also
  removes its managed media objects.
- Verification: Ran focused PostgreSQL-backed API coverage for create,
  replacement, listing, automatic locale fan-out, lock preservation, cascade
  deletion, and physical media cleanup.

## 2026-08-09 — Translation overwrite locks

- Changed: Added non-null `lock_it = false` to all 13 existing translation
  tables, exposed the flag in Brand and Store-policy translation APIs, and added
  a shared automated translation writer that skips locked rows.
- Reason: Merchant-authored translations must survive imports, AI translation,
  catalog refreshes, and other system-generated updates until the merchant
  explicitly unlocks them.
- Data/configuration impact: Two additive migrations cover the 12 Catalog
  translation tables and `store_policy_translations`; existing records remain
  unlocked. New translation migrations use `TranslationSchema::addLock()`.
- Compatibility or rollout notes: Manual API writes may set or clear `lock_it`.
  Automated writers must use `AutomatedTranslationWriter`; locked content is
  skipped, while unlocked and new rows retain the normal update behavior.
- Verification: Added dynamic PostgreSQL coverage for every current and future
  `*_translations` table plus locked/unlocked automated-update behavior, and ran
  formatting, static analysis, documentation checks, and focused tests.

## 2026-08-08 — Category translation presentation metadata

- Changed: Added nullable `banner_url`, `page_title`, `search_keywords`, and
  `category_template` fields to `category_translations`.
- Reason: Localized category pages need a dedicated page title and merchant
  search-keyword metadata in addition to the existing SEO title and description,
  and each translation may select its own banner image and rendering template.
- Data/configuration impact: Additive Catalog migrations provide
  nullable `banner_url varchar(500)`, `page_title varchar(255)`,
  `search_keywords text`, and `category_template varchar(120)`. Existing
  translations remain valid without backfill.
- Compatibility or rollout notes: Categories remain persistence-only; no
  Category REST or GraphQL contract exists yet.
- Verification: Added PostgreSQL-backed schema and persistence coverage, then
  ran Catalog formatting, documentation inventory checks, and the focused test.

## 2026-08-07 — Brand website URL and origin

- Changed: Added nullable `brands.website_url` and `brands.origin` persistence
  with PostgreSQL schema/value coverage. Added Store-scoped Brand models,
  validation, translations, resources, CRUD services, and five REST routes.
- Reason: Store catalogs need to retain an official external website for each
  brand plus its country, region, or free-form origin independently from
  localized name, description, and SEO content.
- Data/configuration impact: Two additive Catalog migrations add nullable
  `website_url varchar(2048)` and `origin varchar(120)` columns. Existing brand
  rows remain valid without backfill.
- Compatibility or rollout notes: Brand reads require active Store membership;
  writes require `manage products`. Website values accept HTTP(S), while logo
  locators may be root-relative or HTTP(S). Other Catalog areas remain
  persistence-only.
- Verification: Catalog PostgreSQL tests cover column registration, null
  compatibility, URL/origin persistence, Brand CRUD, translations, validation,
  pagination, authorization, and cross-Store isolation.

## 2026-08-07 — Language country-flag icons and selector images

- Changed: Added `languages.lang_icon` and `languages.lang_image` asset references,
  bundled SVG country flags, 25 circular 256×256 WebP selector images derived
  from the supplied flag sprite, platform create/edit validation, and render-ready
  asset URLs in platform, Store-language, policy-translation, and policy-version
  resources.
- Reason: Let storefront language switchers and admin translation workflows for
  brands, collections, categories, products, policies, and future localized
  entities identify each language consistently with an icon and native label.
- Data/configuration impact: Two additive Settings migrations backfill existing
  icon/image references and give future rows the generic asset by default. Root-relative
  asset references and HTTP(S) icon URLs are accepted; locale immutability and
  Store language selections are unchanged.
- Compatibility or rollout notes: Apply migrations and run the idempotent
  application seeder. Separate frontend/admin clients should render the returned
  `lang_image` URL, retain `lang_icon` as fallback, and keep visible text/alt text
  for accessibility. Resource rendering falls back through image, icon, and the
  generic asset when a partial query omits attributes, but deployments must still
  apply the migrations before asset writes.
- Verification: Added PostgreSQL-backed catalog/API assertions for seeded icon
  and image paths, public assets, fallback creation, custom updates, and unsafe-scheme
  rejection; generated documentation, formatting, and relevant tests were run.

## 2026-08-07 — Localized Store policies and version history

- Changed: Added the eight-type system policy catalog, Platform custom-type
  administration, Store-local draft/published policies, localized content and
  SEO fields, automatic immutable language-scoped versions, rollback,
  merchant management APIs, and public published storefront reads.
- Reason: Give every Store a normalized, translatable policy structure with
  strong Store isolation and auditable legal-content changes instead of
  storing unrelated pages in settings or unversioned blobs.
- Data/configuration impact: One additive Stores migration creates
  `policy_types`, `store_policies`, `store_policy_translations`, and
  `policy_versions`. Database constraints enforce one policy per Store/type,
  Store-local slug uniqueness, valid publication state, unique translations,
  and positive per-language versions. The authorization catalog adds
  `manage policies` to Owner and Manager.
- Compatibility or rollout notes: Run the database seeder after migration to
  idempotently install the system types. Storefront policy routes require
  `X-Store-ID` but intentionally do not require authentication. Content may be
  Markdown or previously sanitized HTML; this backend does not sanitize or
  render arbitrary HTML.
- Verification: Added PostgreSQL feature coverage for catalog protection,
  Store isolation and uniqueness, translation version creation, publication,
  public locale reads, rollback, and last-translation protection.

## 2026-08-05 — Store-local Catalog schema

- Changed: Added and enabled the Catalog module with 33 normalized tables for
  brands, translated collections/categories/products, collection rules and AI
  jobs, tags and assignments, options/values/variants, product images, digital
  assets, software license-key pools, and typed custom fields. Added a complete
  schema reference covering every column, relationship, constraint, index,
  deletion rule, application responsibility, and core PostgreSQL query pattern.
- Reason: Establish stable Store-local product identifiers and database
  invariants before adding Catalog APIs, search projections, Inventory, Files,
  or Orders integrations.
- Data/configuration impact: Four additive migrations use bigint internal IDs,
  ULID public IDs, timezone timestamps, Store-scoped localized slugs, composite
  foreign keys, partial/expression uniqueness, checked lifecycle/type values,
  and integer-minor-unit variant prices. Store deletion cascades Catalog rows.
- Compatibility or rollout notes: This delivery is persistence-only and adds
  no public route or GraphQL field. Apply all four migrations in order. Future
  write paths must encrypt license material and protect digital asset locators.
- Verification: Applied all four migrations to the configured development
  PostgreSQL database. Module discovery, generated-documentation update/check,
  Pint, PHPStan, and the full PostgreSQL suite pass with 22 tests and 323
  assertions, including all 33 tables, Store-local slug reuse/uniqueness, and
  cross-Store assignment rejection. The complete reference was mechanically
  checked against all 33 migration table names, and its local links resolve.

## 2026-08-05 — Self-service password changes

- Changed: Added the authenticated `PATCH /api/v1/auth/password` contract for
  both Platform and Store users, current-password verification, the shared
  strong-password policy, per-user rate limiting, active session/CSRF rotation,
  remember-token rotation, personal access-token revocation, and feature tests.
- Reason: Allow every signed-in admin account to replace its own password from
  the Next.js Security page without exposing a scope-specific or privileged
  password route.
- Data/configuration impact: No schema change. Successful changes invalidate
  outstanding MFA challenges through their existing password-hash binding and
  revoke all personal API tokens while preserving the current browser session.
- Compatibility or rollout notes: Clients must send `current_password`,
  `password`, and `password_confirmation` with a Sanctum-authenticated,
  CSRF-protected PATCH request. Laravel Fortify remains the MFA engine; the
  password endpoint is a ShopNXE Authentication module contract.
- Verification: Focused PostgreSQL feature tests passed for Platform and Store
  scopes, weak/incorrect passwords, unauthenticated access, token revocation,
  and password/session credential rotation.

## 2026-08-03 — Theme marketplace architecture, APIs, and admin interface

- Changed: Replaced the simple Store template table with the full Themes module:
  publishers, categories/assignments, marketplace listings, immutable versions,
  numbered review submissions, Store licenses, and licensed installed Store
  copies. Added Platform catalog/release/review/license APIs, Store
  marketplace/install/customize/duplicate/publish/delete APIs, Store
  provisioning through `ThemeInstaller`, global Theme media support, and the
  permission-gated Next.js Theme list/taxonomy/publisher interface.
- Reason: Keep global marketplace identity and immutable release evidence
  separate from Store-specific mutable settings/layout/CSS while supporting
  safe review, licensing, installation, and future marketplace growth.
- Data/configuration impact: The migration intentionally drops the former
  `store_themes(name, template_key, is_active, settings)` table, creates the
  eight-table Theme architecture, allows Platform media rows with null
  `store_id`, and backfills the bundled default Theme/version/free license plus
  one published installation for existing Stores. PostgreSQL enforces one
  primary category, one current Store/Theme license, and one published
  installation per Store.
- Compatibility or rollout notes: Apply the Theme migrations before deploying
  the new services. Store provisioning still accepts `theme_template_key` but
  resolves it to a marketplace Theme/version/license. `theme_sales` is
  intentionally deferred until provider-backed orders, commissions, refunds,
  and payouts exist. Theme artifact metadata registration never executes
  package code.
- Documentation: Added the Theme architecture/module guide, all directional
  module communication contracts, all 23 REST routes and OpenAPI request
  shapes, and updated Store/context/developer/admin guides to the new ownership
  model.
- Verification: Applied a clean PostgreSQL migration set; validated OpenAPI
  YAML; passed PHP syntax checks, Pint, PHPStan, and the full backend suite with
  29 tests and 433 assertions. The admin passed generated-documentation checks,
  TypeScript, ESLint, and its 21-page Next.js production build. The restarted
  local admin returned `200` for `/login`, its Laravel proxy returned the
  expected validation response, and the unauthenticated login screen passed
  desktop and 390-pixel layout/overflow/console checks. Authenticated Theme
  workflows were not exercised because no signed-in Platform session was
  available in the verification browser.

## 2026-08-03 — Platform Store domain settings and complete add workflow

- Changed: Added Platform Store domain list/create/update APIs over `store_domains`, primary-domain synchronization, domain verification/routing/SSL controls, and complete direct Store creation of normalized locale, settings, generated platform-domain, and optional custom-domain records.
- Reason: The Store editor needs one safe domain-settings form object and a usable Add Store workflow without duplicating domain data in another table.
- Data/configuration impact: No migration. Existing `store_domains` remains authoritative. New direct Platform Stores receive `<slug>.<STOREFRONT_ROOT_DOMAIN>` and optionally a pending custom primary domain; `stores.primary_domain` changes transactionally when primary selection changes. Create requests may include normalized `locale_settings` preferences.
- Compatibility or rollout notes: All routes require Platform scope plus `manage stores` and never use `X-Store-ID`. No delete route is exposed. Hostnames are globally unique, the current primary cannot be unset directly, and owner/membership/role/plan/subscription creation remains outside the direct Store API.
- Verification: Pint, PHPStan, generated-documentation checks, and the full PostgreSQL suite pass with 32 tests and 458 assertions, including initial domain creation, locale persistence, domain listing/add/update, primary switching, verification state, and global hostname uniqueness. The separate admin passed generated-documentation checks, TypeScript, ESLint, a Next.js production build, and authenticated desktop/mobile browser checks with a clean console.

## 2026-08-03 — Store locale settings

- Changed: Added the one-to-one `store_locale_settings` table and Platform Store locale-settings read/update API. The API combines Store-owned currency/language/country/timezone fields with normalized date, time, week, number, weight, and dimension preferences for one editor workflow.
- Reason: Keep regional presentation settings in a focused Store-edit section without mixing them into profile, lifecycle, billing, or raw JSON controls.
- Data/configuration impact: The migration backfills every existing Store from validated legacy preference values and defaults, and new Store provisioning creates the row transactionally. Store-language selection remains in `store_languages`; currency, language, country, and IANA timezone remain first-class `stores` columns instead of being duplicated. UTF-8 and daylight-saving behavior are platform-managed.
- Compatibility or rollout notes: `GET/PATCH /api/v1/platform/stores/{store}/locale-settings` requires Platform scope plus `manage stores`. Merchant `PATCH /api/v1/store/settings` continues to work and synchronizes its date/time/weight/dimension preference subset into the normalized row.
- Verification: Applied the migration locally. Focused PostgreSQL coverage passes with 2 tests and 21 assertions.

## 2026-08-02 — Store users table and normalized Store address

- Changed: Renamed the Store membership table to `store_users`; Eloquent pivots, membership enforcement, PostgreSQL authorization functions, broadcasting, Store-user administration, and API resources now use the renamed table. Store membership continues to establish Store access only, while Store-scoped roles and permissions decide permitted actions.
- Reason: Make the Store-to-user relationship explicit in the database without weakening the existing scope, membership-status, role, permission, and policy layers.
- Data/configuration impact: Added nullable `store_country_code`, `store_state`, `store_city`, `store_zip`, `store_address_1`, and `store_address_2` columns to `store_settings`. Registration, Store creation, Platform merchant create/edit, Store profile contact updates, and Store settings read/update now keep normalized settings synchronized. The migrations preserve all existing relationship rows, rewrite PostgreSQL authorization functions to query `store_users`, and normalize legacy constraint/index/sequence names to `store_users_*`.
- Compatibility or rollout notes: Public Store-user response keys remain `membership` for API compatibility. Apply migrations before deploying application code. Existing Store roles and permissions are unchanged.
- Documentation: Expanded the project context and developer guide with the complete `store_users` schema/permission flow, normalized Store-address schema, creation/update service paths, response compatibility, and migration behavior.
- Verification: Applied the migration locally; the full PostgreSQL suite passes with 30 tests and 409 assertions. Pint, PHPStan, backend/frontend documentation checks, frontend TypeScript, ESLint, and the 17-page Next.js production build pass; the restarted admin server returns `200` for `/login` and the expected unauthenticated redirect for `/dashboard`.

## 2026-08-02 — Atomic merchant Store setup

- Changed: Made Store provisioning create a draft Store, normalized settings, platform/custom domains, and one selected active theme before creating the Owner membership/role; registration, additional-Store creation, and Platform merchant creation now return a Store-specific `dashboard_url`.
- Reason: Merchant setup must produce one complete, immediately manageable Store configuration instead of requiring disconnected follow-up inserts.
- Data/configuration impact: No migration. Added `STOREFRONT_ROOT_DOMAIN`, `STORE_DEFAULT_THEME_KEY`, and `STORE_ADMIN_DASHBOARD_URL` configuration. Newly provisioned storefronts are disabled while the Store is draft; platform-domain SSL begins pending. Store slugs submitted through merchant creation must be DNS-safe lowercase labels separated by hyphens.
- Compatibility or rollout notes: `theme_template_key` is optional and defaults to `default`. Existing custom-domain input becomes a pending primary custom domain while the generated platform domain remains active and verified. Direct unassigned Platform Store creation keeps its separate row-only contract.
- Verification: Passed Pint, PHPStan, generated-documentation checks, and the full PostgreSQL suite with 29 tests and 386 assertions. The admin passed generated-documentation checks, TypeScript, ESLint, and a Next.js production build; its restarted local server returned HTTP 200 for login and correctly redirected unauthenticated Store-specific dashboard links on desktop and 390-pixel mobile with no console warnings/errors. Authenticated visual Store switching was not exercised because the verification browser had no signed-in merchant session.

## 2026-08-02 — Store lifecycle and storefront configuration persistence

- Changed: Replaced Store lifecycle values with `draft`, `trial`, `active`, `suspended`, `frozen`, and `closed`; added Store-owned domain, one-to-one settings, and theme persistence with Eloquent relationships and database invariants.
- Reason: Give Store lifecycle, domains, storefront controls, media-backed branding, and theme configuration explicit relational ownership instead of relying only on legacy Store columns/JSON.
- Data/configuration impact: Migrates `pending` Stores to `draft` and `cancelled` Stores to `closed`; creates `store_domains`, `store_settings`, and `store_themes`. Store deletion cascades dependent rows, media deletion nulls settings references, and PostgreSQL limits each Store to one primary domain and one active theme.
- Compatibility or rollout notes: New direct Platform Stores default to `draft`. Domain/SSL state strings and JSON settings remain extensible. Existing profile/settings REST contracts are unchanged; dedicated APIs for the normalized records require a later service change.
- Verification: Applied the migrations to the local development database; passed Pint, PHPStan, generated-documentation checks, and the full PostgreSQL suite with 27 tests and 364 assertions. The admin passed generated-documentation checks, TypeScript, ESLint, and a Next.js production build; its restarted local server returned HTTP 200. In-app visual reloading was blocked by browser URL policy after the restart, so no visual-pass claim is recorded.

## 2026-08-02 — Owner-aware Platform Store directory

- Changed: Extended `GET /api/v1/platform/stores` with a public owner projection from the earliest Store membership, related-member name/email search, and a 10-row default while retaining the 100-row maximum.
- Data/configuration impact: No migration and no data rewrite. Existing `stores`, `store_memberships`, and `users` rows are read only; internal bigint keys remain private.
- Compatibility or rollout notes: Existing Store fields, filters, sorting, `links`, and `meta` remain compatible. Collection items add nullable `owner`; direct Store create/detail/edit contracts are unchanged.
- Verification: Added focused PostgreSQL feature coverage for the default page size, owner shape, and Store/domain/member search; targeted Pint passed.

Record meaningful changes to behavior, architecture, dependencies, schemas, operations, or developer workflow. Keep entries concise; this is not a copy of Git history.

Generated facts live in the [system inventory](generated/system-inventory.md). Run `composer docs:update` before completing an entry.

## 2026-07-31 — Hostname-safe admin authentication proxy

- Changed: Standardized the local XAMPP API URL, made development session cookies host-only, allowed both local admin origins, documented the Next.js admin's same-origin `/laravel` proxy, and added atomic `GET /api/v1/auth/session` dashboard bootstrap.
- Reason: The admin was opened on `127.0.0.1:3000` while its browser bundle called an unavailable `localhost:8000` API and Laravel scoped cookies to `localhost`, producing connection and session failures. Parallel post-login User/interface requests also competed with a five-second server timeout on the slower local PHP runtime.
- Data/configuration impact: No migration or stored-data change. Local `APP_URL`, CORS, and session-domain values change; production must continue using exact HTTPS origins and secure cookies.
- Compatibility or rollout notes: Browser code now uses the admin origin and Next.js forwards to the server-only Laravel upstream. Dashboard clients should use `/auth/session`; direct API clients may continue using `/auth/me` and `/auth/interfaces` normally.
- Verification: Passed backend documentation generation/checks, Pint, PHPStan, and the full PostgreSQL suite with 23 tests and 305 assertions. Passed admin documentation checks, TypeScript, ESLint, and the Next.js production build. Browser sign-in reached the Platform Admin dashboard through `127.0.0.1:3000`, retained the correct scope across repeated desktop and 390-pixel mobile reloads, exposed Plans & Pricing, and produced no console warnings or errors.

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
