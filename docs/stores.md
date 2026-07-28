# Stores

Phase 1 uses one shared PostgreSQL database and schema. `Store` and `StoreMembership` use bigint internal primary/foreign keys, public ULIDs, and timezone-aware timestamps. Users are global and can have many active or suspended memberships.

Authenticated Store REST and GraphQL operations send `X-Store-ID: <store-ulid>`. `ResolveStore` validates ULID syntax and resolves `stores.public_id`; `EnsureStoreMembership` validates an active membership, selects the internal bigint ID as Spatie's permission team, and rejects a store-scoped token used for a different store. Registration, login, password reset, store listing, and account token management do not require the header. Missing required context is 400, unknown store is 404, and non-member/suspended/token mismatch is 403.

`RequestStoreContext` is container-scoped. `ClearRequestContext` and queue lifecycle hooks clear it, `Store::forgetCurrent()`, the active permission team, auth guards, locale, and log context after each request/job. Store-aware jobs use Spatie's queue hooks; global jobs implement `GlobalJob`/vendor `NotTenantAware` explicitly. Store-owned future entity tables require bigint `id`, ULID `public_id`, a non-null bigint `store_id`, a foreign key/index, `StoreScoped`, and store-local composite unique constraints.

Store cache keys and search documents are filtered by internal `store_id`. Public events, media paths, and URLs use Store/entity ULIDs. Public storefront domain resolution and PostgreSQL RLS are future hardening work.
