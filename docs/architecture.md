# Architecture

The application is a modular monolith: one Laravel process, one PostgreSQL shared schema, and explicit module boundaries. `Modules/Authentication` and `Modules/Tenancy` own their models, migrations, routes, GraphQL extensions, policies, actions, and tests. `app/Support` contains only small cross-cutting adapters (request IDs, tenant-aware search, media path generation, and dashboard access).

HTTP is API-only. REST is versioned under `/api/v1` and is reserved for authentication, files/uploads/downloads, exports, webhooks, broadcasting authentication, and health. Lighthouse serves business reads and writes at `/graphql`. Queue work uses Redis/Horizon; Reverb handles WebSockets; Scout targets Meilisearch or the database driver; private media uses a tenant-prefixed path generator.

The shared-schema tenancy model keeps global identities in `users` and tenant ownership in `tenant_memberships`. A request-scoped `TenantContext` is set only after authentication and active-membership validation. Middleware and `TenantScoped` are defense-in-depth; policies and typed actions remain authoritative. PostgreSQL row-level security is deliberately deferred until it can be proven safe with Octane and pooled connections.

All external side effects are dispatched after commit, queued jobs restore and clear tenant state, and `ClearRequestContext` resets tenant, permission-team, authentication, locale, and logging context for long-lived workers.
