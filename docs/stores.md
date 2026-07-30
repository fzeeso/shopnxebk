# Stores

Phase 1 uses one shared PostgreSQL database and schema. `Store` and
`StoreMembership` use bigint internal primary/foreign keys, public ULIDs, and
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
