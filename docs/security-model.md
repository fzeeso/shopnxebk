# Security model

Security is layered: Sanctum token abilities, exclusive `users.scope`, authenticated-user membership, Spatie permissions, and model policies. Platform and Store accounts cannot share memberships, roles, direct permissions, or Store-bound tokens. A global Platform super-admin is never inferred from a Store header. Platform staff, merchant, and Store-user creation validate role scope in Form Requests/services and are independently protected by PostgreSQL scope triggers.

Bearer tokens receive a 30-day default expiry unless the caller supplies an
explicit expiry. Store routes require the `store:access` ability and an exact
token `store_id` match; an account-only or unbound bearer token cannot enter
Store context. Password reset revokes every bearer token owned by the user.
Expired token rows are pruned daily.

Store/entity public IDs are ULIDs; bigint IDs and bigint foreign keys remain internal. Store context is request scoped and cleared for Octane and queue workers. Store-owned data must use foreign keys, scopes, policies, and composite uniqueness. Search documents and cache keys are filtered with internal `store_id`. Private media paths use Store and media ULIDs. Incoming webhooks must verify raw-body signatures and idempotency keys before processing.

Store Admin mutations have three application-level isolation checks. Store
context and active membership are established before route-model binding;
bound models carrying a different `store_id` resolve as 404; and `StoreScoped`
model save/delete events reject a Store mismatch even when application code
already holds the model instance. Store/GraphQL HTTP execution also blocks
schema SQL (`CREATE`, `ALTER`, `DROP`, `TRUNCATE`, grants, and equivalent
commands). AI media and translation providers receive fixed operations and
structured content only: they are not given SQL, shell, database, route, or
deletion tools. These controls complement, rather than replace, a
least-privileged production database role.

Platform Plans & Pricing routes do not accept Store context. They require `users.scope = platform` and `manage plans` at the service boundary; Store users are rejected before plan data is returned. Store profile/settings requests separately prohibit Billing links, lifecycle, verification, entitlement, trial, and raw JSON fields, preventing merchant self-upgrades or cross-boundary configuration changes.

Internal dashboards fail closed: enabling them is insufficient unless the
requesting Platform Super Admin's IP also appears in the configured allow-list.
Registration and token-management endpoints have dedicated rate limits.

The development XAMPP layout includes a repository-root `.htaccess` safeguard
that permits HTTP access only below `public/`. Production web servers must
still use `public/` as their actual document root. Development Compose ports
bind to `127.0.0.1` and are not intended for remote access.

PostgreSQL RLS is a future hardening option; this release intentionally uses an explicit, tested application tenancy layer instead of a half-configured session-level policy.
