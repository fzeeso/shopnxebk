# Tenancy

Phase 1 uses a shared PostgreSQL database and schema. `Tenant` and `TenantMembership` use UUIDv7 identifiers and timezone-aware timestamps. Users are global and can have many active or suspended memberships.

Authenticated admin REST and GraphQL operations send `X-Tenant-ID`. `ResolveTenant` validates UUID syntax and existence; `EnsureTenantMembership` validates an active membership, selects Spatie's permission team, and rejects a tenant-scoped token used for a different tenant. Registration, login, password reset, tenant listing, and account token management do not require the header. Missing required context is 400, unknown tenant is 404, and non-member/suspended/token mismatch is 403.

`RequestTenantContext` is container-scoped. `ClearRequestContext` and queue lifecycle hooks clear it, `Tenant::forgetCurrent()`, the active permission team, auth guards, locale, and log context after each request/job. Tenant-aware jobs use Spatie's queue hooks; global jobs implement `GlobalJob`/`NotTenantAware` explicitly. Tenant-owned future tables must have a non-null UUID `tenant_id`, foreign key, index, tenant-aware scope, and composite unique constraints.

Tenant-prefixed cache keys and search documents are mandatory. Public storefront domain resolution and PostgreSQL RLS are future hardening work.
