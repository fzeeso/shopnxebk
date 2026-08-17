# ShopNXE application context

This is the canonical context for architecture and future implementation work. Read it before changing a module, migration, route, API contract, authorization rule, queue job, search document, or media path.

## Domain language

ShopNXE is a multi-store SaaS commerce platform. A **store** is the business/account boundary that some libraries call a tenant. Application code, database columns, routes, headers, events, documentation, and user-facing messages use **Store**. The word “tenant” is retained only when referring to a third-party class, interface, configuration key, or command that cannot be renamed.

A **user** is an authenticated identity stored in the shared `users` table, but every row has one exclusive `scope`: `platform` or `store`. Platform administrators/support/billing staff are Platform users. Store owners/managers/sales/inventory staff are Store users. A user cannot change scope while memberships, roles, or direct permissions exist.

## Access interfaces

The two account classes and interfaces are mutually exclusive:

| Interface | Administrative user | Scoped users | Context |
| --- | --- | --- | --- |
| `platform_admin` | `Super Admin` is the SaaS Owner | `Support` and `Billing` are platform staff | Platform scope; no Store header |
| `store_admin` | `Owner` and `Manager` administer the merchant | `Sales`, `Inventory`, and future Store roles are Store staff | One selected Store ULID |

After authentication, `GET /api/v1/auth/session` returns the current User plus both stable interface keys, with exactly one side available. `GET /api/v1/auth/interfaces` remains the profile-only variant. A Platform user receives only `platform_admin` roles/permissions/navigation and can never have Store membership, Store roles, or Store-bound tokens. `Plans & Pricing` is returned only with `manage plans`; `Settings` only with `manage platform settings`; `Admin Users` only with `manage platform users`; and `Merchants` only with `manage stores`. A Store user receives only `store_admin` Stores/roles/permissions and can never receive a Platform role or permission. Backend scope middleware, Store membership/context, permissions, and policies remain authoritative.

Platform staff creation and merchant provisioning are separate APIs. `/api/v1/platform/users` creates only Platform identities. `/api/v1/platform/merchants` atomically creates a Store identity, Store, active membership, and Store roles. `/api/v1/store/users` lets an authorized merchant create staff only inside the selected Store. “All roles” is always restricted to the identity's own scope. See [User and merchant management](user-merchant-management.md).

Platform Store catalog administration is exposed separately at
`/api/v1/platform/stores*`. It requires `manage stores`, never enters Store
context, and supports public-ULID detail/edit plus case-insensitive search,
exact filters, whitelisted sorting, and bounded pagination. Direct creation
produces an unassigned Store; administrators use `/platform/merchants` when an
owner identity, membership, and roles must be provisioned atomically.

## Administration component contract

The backend is API-only, but its navigation metadata defines stable component
entry points for the separate admin frontend. `/admin/settings` is the single
extensible Platform Settings shell. Languages and Currencies are sections of
that shell; they are not Store Management components. The shell uses only
`/api/v1/platform/settings/*`, never sends `X-Store-ID`, and requires
`manage platform settings`.

The `merchants` navigation entry may compose the searchable Platform Store
catalog and the owner-aware merchant workflow. Its Store catalog uses only
`/api/v1/platform/stores*`, never sends `X-Store-ID`, and requires
`manage stores`. See the
[Platform Stores component guide](components/platform-stores-admin.md).

The `themes` navigation entry mounts at `/admin/themes` only for a Platform
user with `manage marketplace`. Its catalog, publisher, category, immutable
version, submission, review, publication, and license calls use
`/api/v1/platform/theme*` routes without Store context. Merchant installations
are a separate Store interface under `/api/v1/store/theme*`, require
`X-Store-ID` plus `manage themes`, and never store merchant customization on
the global Theme listing.

Store Settings is a separate future Store-admin component. It will operate on
one selected Store through Store-scoped routes and must not create or edit
Platform master catalog rows. See the [admin component guides](components.md).

Supported Store languages and translated admin-interface locales are separate
capabilities. Catalog changes update `EnsureLanguageCatalog`, bundled
country-flag assets and `lang_icon`/`lang_image` references, direction data, and PostgreSQL
coverage; visible admin-label changes update every relevant frontend dictionary.
Storefront/admin selectors and localized-content tabs consume the render-ready
image URL from the language API instead of maintaining their own locale-to-flag
map. See the
[admin localization contract](components/localization.md).

## Local admin integration contract

The separate Next.js admin owns a same-origin `/laravel/*` browser proxy. It
forwards requests to a server-only Laravel upstream; under XAMPP the upstream
is `http://localhost/shopnxebk/public`. Browser code therefore never depends on
a second hostname or port for Sanctum cookies. Local Laravel sessions use an
empty `SESSION_DOMAIN` so cookies are host-only, while CORS and Sanctum keep
both `localhost:3000` and `127.0.0.1:3000` as explicit development origins.
Production still requires exact HTTPS origins and secure cookies.
Dashboard bootstrap uses one `/auth/session` call rather than parallel
`/auth/me` and `/auth/interfaces` calls, preventing timeout-driven partial
scope resolution on slower local PHP runtimes.

## Identifier contract

Domain entities have two identifiers:

| Purpose | Column | Type | Visibility |
| --- | --- | --- | --- |
| Database primary key | `id` | PostgreSQL `bigint` | Internal only |
| Public identifier | `public_id` | 26-character ULID | REST, GraphQL, URLs, events exposed outside the process |
| Relationships | `*_id` | PostgreSQL `bigint` | Internal only |

Laravel route binding uses `public_id`. Requests resolve a ULID once and then use the bigint key for joins, scopes, permission teams, token binding, cache/search filters, and internal events. API resources and GraphQL types must never expose an internal bigint key as `id`.

Entity tables such as `users`, `stores`, `store_users`, `roles`, `permissions`, `personal_access_tokens`, `media`, and future commerce records require both columns. Pure relationship tables managed through direct package inserts may use only an internal bigint `id`, because they are not addressable public resources. Protocol/infrastructure identifiers required by Laravel packages remain exceptions: notification IDs and Media Library UUIDs are UUIDs, failed-job UUIDs remain diagnostic identifiers, and cache/queue/monitoring tables follow their package contracts.

## Store profile contract

The `stores` table is the source of truth for merchant identity, contact details, branding references, classification, locale, lifecycle, and Store-level capability switches. `business_type` is nullable during onboarding and, when present, is one of `ecommerce`, `b2b`, `services`, `digital`, `restaurant`, or `marketplace`. `status` is one of `draft`, `trial`, `active`, `suspended`, `frozen`, or `closed`. The lifecycle migration maps historical `pending` rows to `draft` and `cancelled` rows to `closed`.

Store-owned configuration includes `store_domains`, one-to-one
`store_settings`, and Theme installations. Domains are globally unique and
permit only one primary domain per Store. Settings use `store_id` as both
bigint primary key and Store foreign key because the row is never independently
addressed. `store_themes` now belongs to the Themes module: each row references
a global/custom Theme, immutable version, and Store license, owns mutable
settings/template/CSS with an optimistic revision, and permits only one
non-deleted `published` copy per Store. Domain/SSL states and Theme
customization JSON remain extensible.

`store_users` is the Store-to-user relationship table. Each row has its own internal bigint `id`, public ULID, bigint `store_id` and `user_id`, membership `status`, invitation/join timestamps, and audit timestamps. The Store/user pair is unique. The table was renamed from `store_memberships` without rewriting IDs or losing rows; its PostgreSQL constraints, indexes, and sequence now also use `store_users_*` names. Historical migrations retain the old name only as an upgrade step before the rename migration runs.

Membership and authorization are separate layers. An active `store_users` row answers “may this Store-scoped identity enter this Store?” Store-scoped `model_has_roles` assignments and their permissions, evaluated with the same internal `store_id`, answer “what may this identity do?” A Platform identity cannot have a `store_users` row. PostgreSQL triggers reject mixed account scopes, Store role/direct-permission assignments without an active relationship, and scope changes while access records exist.

The `store_settings` row contains normalized operational settings rather than routing them through arbitrary JSON. Its address fields are `store_country_code`, `store_state`, `store_city`, `store_zip`, `store_address_1`, and `store_address_2`; all are nullable during onboarding. Contact email/phone, weight unit, storefront/password switches, order prefix, branding media keys, social links, extra settings, and the boolean `auto_store_translation_flag` and `is_searchable_on_platform_flag` opt-ins remain alongside them. Both opt-ins default to `false` and persist intent only; separate translation/search workflows must consume them. `store_country_code` is normalized to an uppercase two-character country code at API boundaries.

`plan_id` and `subscription_id` are nullable internal bigint Billing integration keys. The Billing plan catalog now exists, but the historical Store columns remain unconstrained until existing values are audited and a subscription assignment workflow is implemented. They must never be returned as public identifiers. `logo`, `favicon`, and `cover_image` hold nullable storage references; upload authorization and file delivery remain Files/media responsibilities.

Currency uses a three-character ISO 4217 code, country uses a two-character ISO 3166-1 alpha-2 code, language accepts a BCP 47-style code, and timezone stores an IANA timezone name. The database defaults new Stores to `USD`, `en`, `UTC`, and all capability/verification flags to `false`. Provisioning copies the display name into `legal_name`; imported historical Stores may keep optional profile values null until onboarding completes.

Store management is service-based. `CreateStoreService` creates an additional Store and Owner relationship for a Store-scoped identity and passes contact/address data into `StoreProvisioner`. `ViewStoreService`, `UpdateStoreProfileService`, and the transactional `StoreController` settings flow require the selected Store ULID and active `store_users` row; profile/settings writes additionally require `manage store`. `GET /api/v1/store/settings` returns normalized contact/address values with locale, preferences, opt-in flags, and read-only capabilities. `PATCH /api/v1/store/settings` updates those columns and synchronizes support email, weight unit, and order prefix from validated preferences. Profile email/phone changes synchronize the normalized contact columns. Merchant requests cannot change lifecycle, Billing links, verification, entitlements, launch/trial timestamps, or raw metadata/settings.

Merchant Store creation is one atomic provisioning workflow. `ProvisionStore`
creates the Store as `draft`, creates its one-to-one settings, reserves the
`<slug>.<STOREFRONT_ROOT_DOMAIN>` platform domain, optionally records a
submitted custom primary domain as pending, calls the Themes-owned
`ThemeInstaller` contract to resolve the selected published version, issue a
license, and create the first published Store copy, creates the active Owner
membership/role, and returns the Store. The
creation responses add a Store-specific `dashboard_url` built from
`STORE_ADMIN_DASHBOARD_URL` and the Store public ULID. Any failed database step
rolls back the entire Store setup.

The same address input is supported by account registration, authenticated
`POST /api/v1/stores`, and Platform `POST /api/v1/platform/merchants`.
Platform merchant detail/create/update resources include `store_settings`, and
`PATCH /api/v1/platform/merchants/{merchant}` updates normalized address and
contact data in the same transaction as owner and Store-profile changes. The
public Store-user payload retains the key `membership` for API compatibility;
that label does not expose or restore the former database table name.

`PlatformStoreAdminService` is the Platform-only Store catalog boundary. It
requires `manage stores`, returns public-safe Store resources, creates or edits
validated profile/locale/lifecycle/capability fields, and deliberately rejects
internal plan/subscription IDs and raw JSON. Page size is capped at 100 and
sorting is limited to known Store columns.

## Plans and pricing contract

Billing owns `plans`, reusable `features`, and `plan_features`. A plan-feature assignment carries a typed JSON value and may be included or an optional add-on with its own nullable minor-unit price. Fixed plan prices and add-on prices use integer minor units; billing intervals are `month` or `year`. Plans are `draft`, `active`, or `archived`; custom-priced plans store no fixed amount/interval.

Plan administration is Platform-only and requires `manage plans`. `Super Admin` and `Billing` can initially manage the catalog; `Support` and all Store users cannot. Assigned plans are archived rather than deleted. Public APIs use plan/feature ULIDs and never expose bigint relationships.

## Catalog persistence contract

Catalog owns Store-local brands, collections, categories, tags, products,
options, variants, media/fulfillment metadata, software license-key pools, and
typed custom fields. Addressable rows use bigint primary keys, public ULIDs,
non-null indexed Store IDs, and timezone timestamps. Translation and
relationship rows retain Store IDs so composite foreign keys reject cross-Store
associations at the database boundary.

Translated slugs are unique per Store and locale. Every `*_translations` table
has non-null `lock_it = false`; manual editors control it, while automated,
import, and AI writers must skip locked rows through
`AutomatedTranslationWriter`. The dynamic PostgreSQL contract test enforces the
flag on translation tables added later.

Automatic translation is always asynchronous in Redis-backed environments.
Brand, Category, Product, and Store-policy writes save the source and a Store-scoped
`translation_requests` ledger row in one transaction. Only after commit may
`TranslateContentJob` enter the dedicated `translations` queue. The external
provider call runs without database locks or an open transaction; a short
transaction revalidates the source/target snapshot and writes only unlocked
rows. Stale work becomes `superseded`, deleted content becomes `cancelled`, and
provider failure becomes retryable/`failed` without rolling back source data.
The authenticated Store status URL is
`GET /api/v1/store/translation-requests/{translationRequest}`.

Every future automatically translated page or entity must implement
`TranslationContentHandler`, register it under a stable content-type key, and
request work through `TranslationCoordinator`. It must not call
`TranslationProvider` from an HTTP database transaction. The ledger,
after-commit dispatcher, snapshot hashes, `lock_it` recheck, Store-aware job,
retry policy, and scheduled recovery are shared infrastructure rather than
feature-specific implementations.

Categories are the strict navigation taxonomy; collections are manual,
rule-based, or AI-generated merchandising groups. Category and Product GraphQL
queries require authenticated active Store membership; create/update/delete
mutations additionally require `manage products`. Explicit resolvers delegate
to transactional Catalog services, accept only public ULIDs and allow-listed
filters/sorts, enforce hierarchy/primary-category invariants, and expose all
translations plus exact normalized-locale selection. Category translations
carry independent nullable `image_url` and `banner_url` locators; these are
validated manual metadata and are excluded from automatic language translation.
Variant prices use
non-negative integer minor units plus an uppercase three-letter currency code.
Options, variants, files, fulfillment, and custom fields remain persistence-only
until their owning APIs are implemented. See the [API manual](api-manual.md) and
[Catalog schema reference](catalog.md).

## Store context and request flow

```mermaid
flowchart LR
    Request["Request with Store ULID"]
    Resolve["Resolve public_id"]
    Store["Store bigint id"]
    Membership["Check store_users"]
    Token["Compare token.store_id"]
    Team["Set permission team to bigint store_id"]
    Domain["Run policy/action/query"]
    Public["Serialize public_id"]

    Request --> Resolve --> Store --> Membership --> Token --> Team --> Domain --> Public
```

Store-scoped operations receive `X-Store-ID: <store-ulid>`. `ResolveStore` validates the ULID and loads `stores.public_id`. `EnsureStoreMembership` verifies an active membership and, for bearer authentication, requires `store:access` plus an exact non-null token `store_id` match before setting Spatie Permission’s team to the internal `stores.id`. Account-only and unbound bearer tokens cannot enter Store context. `ClearRequestContext` always removes store, permission-team, guard, locale, and log context after a request.

## Roles and permissions

Roles and permissions are data, not hard-coded user types. Both have a `scope` of `platform` or `store`, and the catalog is extendable.

Platform roles may be assigned only to `users.scope = platform` and are evaluated with no Store team. Store roles may be assigned only to `users.scope = store`, require active membership, and carry the matching internal `store_id`. PostgreSQL triggers enforce these boundaries for memberships, assignments, scope transitions, and Store-bound tokens. `ScopedRoleAssignmentService` is the application write path.

Platform roles:

| Role | Initial permissions |
| --- | --- |
| Super Admin | manage stores, manage plans, manage subscriptions, impersonate store, manage marketplace, manage platform settings |
| Support | manage stores, impersonate store |
| Billing | manage plans, manage subscriptions |

Store roles:

| Role | Initial permissions |
| --- | --- |
| Owner | access/manage store, members, roles, themes, products, orders, customers, discounts |
| Manager | access/manage store, members, themes, products, orders, customers, discounts |
| Sales | access store, manage orders, customers, discounts |
| Inventory | access store, manage products |

Platform roles are evaluated without an active store team. Store-role assignments carry an internal `store_id`; the active store can never grant a platform role. `Super Admin` is the only global Gate bypass. New roles or permissions are added through the authorization catalog, migrations/seeders, policies, tests, and documentation—not through boolean columns on `users`.

## Module boundaries

- Authentication owns global identities, credentials, sessions, MFA, tokens, email verification, password reset, and the role/permission catalog.
- Settings owns Platform-wide language/currency catalogs, their seed actions, and global Settings administration.
- Stores owns stores, Platform Store catalog administration, memberships, merchant profile/settings management, Store language selections, store resolution, context lifecycle, provisioning, and store isolation helpers.
- Themes owns marketplace publishers/categories/listings, immutable versions,
  review submissions, Store licenses, installed/customized Store copies, and
  the Store-provisioning installer contract.
- Billing owns plan prices, reusable features, plan-feature/add-on assignments, and Platform plan administration. Subscription/provider/invoice workflows remain future work.
- Catalog owns Store-local merchandising, products, variants, fulfillment
  metadata, and custom fields. Inventory, Files, Search, and Orders consume its
  stable identifiers through future contracts/events rather than writing its
  tables.
- Business modules own their records and actions. Store-owned records use bigint `store_id` and `StoreScoped`, then return ULIDs publicly.
- Cross-module calls use contracts, typed actions, immutable data objects, or after-commit domain events. A module must not update another module’s tables directly.

See the [API manual](api-manual.md), [Authentication module](modules/authentication.md), [Settings module](modules/settings.md), [Stores module](modules/stores.md), [Themes module](modules/themes.md), [Billing module](modules/billing.md), [Catalog module](modules/catalog.md), [Catalog schema](catalog.md), [Theme marketplace](themes.md), [Platform settings](settings.md), [admin component guides](components.md), [Store management](store-management.md), [Plans & Pricing](plans-and-pricing.md), and the directional communication contracts in [module communication](module-communication/).

## Change rule

Every meaningful change must update the affected module document and directional communication document. API contract or execution-flow changes also update the [API manual](api-manual.md) and [developer guide](developer-guide.md); admin navigation/page behavior updates the affected [component guide](components.md); label or locale changes update relevant language dictionaries and the [localization contract](components/localization.md); and every meaningful change receives a [development log](development-log.md) entry. `composer docs:update` regenerates both factual inventory and the GraphQL operation reference; CI's `composer docs:check` rejects either generated artifact when stale. Finish with those commands, Pint, and PostgreSQL-backed tests.
