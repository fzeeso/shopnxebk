# Stores

Phase 1 uses one shared PostgreSQL database and schema. `Store` and `StoreMembership` use bigint internal primary/foreign keys, public ULIDs, and timezone-aware timestamps. Users are global and can have many active or suspended memberships.

Authenticated Store REST and GraphQL operations send `X-Store-ID: <store-ulid>`. `ResolveStore` validates ULID syntax and resolves `stores.public_id`; `EnsureStoreMembership` validates an active membership, selects the internal bigint ID as Spatie's permission team, and rejects a store-scoped token used for a different store. Registration, login, password reset, store listing, and account token management do not require the header. Missing required context is 400, unknown store is 404, and non-member/suspended/token mismatch is 403.

`RequestStoreContext` is container-scoped. `ClearRequestContext` and queue lifecycle hooks clear it, `Store::forgetCurrent()`, the active permission team, auth guards, locale, and log context after each request/job. Store-aware jobs use Spatie's queue hooks; global jobs implement `GlobalJob`/vendor `NotTenantAware` explicitly. Store-owned future entity tables require bigint `id`, ULID `public_id`, a non-null bigint `store_id`, a foreign key/index, `StoreScoped`, and store-local composite unique constraints.

Store cache keys and search documents are filtered by internal `store_id`. Public events, media paths, and URLs use Store/entity ULIDs. Public storefront domain resolution and PostgreSQL RLS are future hardening work.

## Languages

`languages` is the platform master catalog. Each language has a public ULID,
administrative name, native name, unique locale, `ltr` or `rtl` direction, and
active state. The idempotent catalog action seeds English, Arabic, Simplified
and Traditional Chinese, Czech, Danish, Dutch, Finnish, French, German,
Italian, Japanese, Korean, Norwegian Bokmål, Polish, both Portuguese variants,
Spanish, Swedish, Thai, and Turkish.

`store_languages` records which catalog entries a Store operates in. Its
Store/language pair is unique, and PostgreSQL permits only one default row per
Store. `PUT /api/v1/store/languages` requires `manage store`, updates the set in
one transaction, and synchronizes `stores.language_code` to the selected
default. `GET /api/v1/store/languages` is available to any active Store member.

The Platform catalog is read at `GET /api/v1/platform/languages`. Adding a
catalog entry at `POST /api/v1/platform/languages` requires a Platform account
with `manage platform settings`. Numeric language and Store keys never cross
the API; both contracts use public ULIDs.
