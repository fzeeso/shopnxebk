# Stores

Phase 1 uses one shared PostgreSQL database and schema. `Store` and `StoreMembership` use bigint internal primary/foreign keys, public ULIDs, and timezone-aware timestamps. Users are global and can have many active or suspended memberships.

Authenticated Store REST and GraphQL operations send `X-Store-ID: <store-ulid>`. `ResolveStore` validates ULID syntax and resolves `stores.public_id`; `EnsureStoreMembership` validates an active membership, selects the internal bigint ID as Spatie's permission team, and rejects a store-scoped token used for a different store. Registration, login, password reset, store listing, and account token management do not require the header. Missing required context is 400, unknown store is 404, and non-member/suspended/token mismatch is 403.

`RequestStoreContext` is container-scoped. `ClearRequestContext` and queue lifecycle hooks clear it, `Store::forgetCurrent()`, the active permission team, auth guards, locale, and log context after each request/job. Store-aware jobs use Spatie's queue hooks; global jobs implement `GlobalJob`/vendor `NotTenantAware` explicitly. Store-owned future entity tables require bigint `id`, ULID `public_id`, a non-null bigint `store_id`, a foreign key/index, `StoreScoped`, and store-local composite unique constraints.

Store cache keys and search documents are filtered by internal `store_id`. Public events, media paths, and URLs use Store/entity ULIDs. Public storefront domain resolution and PostgreSQL RLS are future hardening work.

## Profile and settings services

The Stores module now exposes `POST /api/v1/stores`, `GET /api/v1/store`, `PATCH /api/v1/store/profile`, and Store settings read/update routes. Creation gives the Store-scoped caller an active Owner membership. Existing Store reads require active membership; profile/settings writes require `manage store`.

Merchant-editable profile, locale, branding, and validated preference fields are separated from Platform-controlled status, Billing links, verification, capability entitlements, trial dates, and raw JSON. See [Store management](store-management.md) for the complete service, endpoint, field-ownership, and error contract.

## Currencies

`currencies` is the Platform master catalog for currency codes and display
formatting. Each currency has a public ULID, unique three-letter code, name,
symbol, symbol placement, decimal places, active state, nullable
`usd_exchange_rate`, and rate-update timestamp. USD is the only base currency
and is constrained to active rate `1.00000000`.

All rates mean `1 USD = X target currency units`. The idempotent seed action
creates 25 common currencies and leaves non-USD rates null so ShopNXE does not
silently publish stale financial data. A Platform user can list the catalog at
`GET /api/v1/platform/currencies`; `POST /api/v1/platform/currencies` and
`PATCH /api/v1/platform/currencies/{currency}` require
`manage platform settings`. The route parameter and response IDs are public
ULIDs. The existing Store `currency_code` field remains compatible and is not
changed by catalog administration.

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
