# Stores

Phase 1 uses one shared PostgreSQL database and schema. `Store` and
`StoreMembership` (backed by `store_users`) uses bigint internal primary/foreign keys, public ULIDs, and
timezone-aware timestamps. Users are global and can have many active or
suspended memberships.

Authenticated Store REST and GraphQL operations send
`X-Store-ID: <store-ulid>`. `ResolveStore` validates ULID syntax and resolves
`stores.public_id`; `EnsureStoreMembership` validates an active membership,
selects the internal bigint ID as Spatie's permission team, and rejects a
Store-scoped token used for a different Store. Registration, login, password
reset, Store listing, and account token management do not require the header.
Missing required context is 400, unknown Store is 404, and
non-member/suspended/token mismatch is 403.

`RequestStoreContext` is container-scoped. `ClearRequestContext` and queue
lifecycle hooks clear it, `Store::forgetCurrent()`, the active permission team,
auth guards, locale, and log context after each request/job. Store-aware jobs
use Spatie's queue hooks; global jobs implement
`GlobalJob`/vendor `NotTenantAware` explicitly. Store-owned future entity
tables require bigint `id`, ULID `public_id`, a non-null bigint `store_id`, a
foreign key/index, `StoreScoped`, and Store-local composite unique constraints.

Store cache keys and search documents are filtered by internal `store_id`.
Public events, media paths, and URLs use Store/entity ULIDs. Public storefront
domain resolution and PostgreSQL RLS are future hardening work.

## Domain, settings, and theme persistence

`store_domains` normalizes Store-owned domains with bigint internal
relationships and public ULIDs. Domains are globally unique, and PostgreSQL
allows no more than one primary domain for a Store. `domain_type`, `status`,
and `ssl_status` are extensible strings so later domain-verification services
can evolve without replacing a database enum.

`store_settings` is a one-to-one dependent record keyed by `store_id`; it is
not independently addressable and therefore intentionally has no `id` or
`public_id`. It stores contact details, weight unit, storefront/password
switches, an automatically hashed and serialization-hidden password value,
order prefix, nullable Media Library bigint
references, and JSON social/extra settings.
The normalized postal address is stored as `store_country_code`, `store_state`,
`store_city`, `store_zip`, `store_address_1`, and `store_address_2`.

`store_themes` uses bigint IDs and public ULIDs for named template instances,
with JSON settings and at most one active theme per Store. These persistence
records are available through `Store::domains()`, `Store::storeSettings()`,
`Store::themes()`, and `Store::activeTheme()`. Provisioning creates the initial
records. `GET/PATCH /api/v1/store/settings` reads and updates the normalized
contact/address values; dedicated post-creation domain and theme APIs remain
outside the current REST contract.

## Store provisioning

Merchant onboarding accepts an optional `theme_template_key`, defaulting to
`STORE_DEFAULT_THEME_KEY`. In one transaction it:

1. creates the Store with `status = draft`;
2. creates `store_settings` with the merchant contact/preferences and a
   disabled public storefront;
3. reserves `<slug>.<STOREFRONT_ROOT_DOMAIN>` as an immediately verified
   platform domain with SSL pending;
4. records an optional custom domain as pending and primary;
5. installs the selected template as the single active Store theme;
6. creates the active Owner membership and role; and
7. returns `dashboard_url`, containing the Store public ULID as the `store`
   query parameter.

A constraint or authorization failure at any stage rolls back every inserted
Store-owned row. `StoreCreated` and authentication notifications run only
after the outermost transaction commits.

## Profile and settings services

The Stores module exposes `POST /api/v1/stores`, `GET /api/v1/store`,
`PATCH /api/v1/store/profile`, and Store settings read/update routes. Creation
gives the Store-scoped caller an active Owner membership. Existing Store reads
require active membership; profile/settings writes require `manage store`.

Merchant-editable profile, locale, branding, and validated preference fields
are separated from Platform-controlled status, Billing links, verification,
capability entitlements, trial dates, and raw JSON. See
[Store management](store-management.md) for the complete service, endpoint,
field-ownership, and error contract.

## Store languages

`store_languages` records which active master-catalog entries a Store operates
in. Its Store/language pair is unique, and PostgreSQL permits only one default
row per Store. `PUT /api/v1/store/languages` requires `manage store`, updates
the set in one transaction, and synchronizes `stores.language_code` to the
selected default. `GET /api/v1/store/languages` is available to any active
Store member.

The master language and currency catalogs are Platform-wide configuration
owned by `Modules/Settings`, not Store Management. Stores consumes active
languages and owns only Store-specific selection rows. Numeric language and
Store keys never cross the API; both contracts use public ULIDs. See
[Platform settings](settings.md).
