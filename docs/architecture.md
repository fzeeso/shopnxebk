# Architecture

The application is a modular monolith: one Laravel process, one PostgreSQL shared schema, and explicit module boundaries. `Modules/Authentication` and `Modules/Stores` own their models, migrations, routes, GraphQL extensions, policies, actions, and tests. `app/Support` contains only small cross-cutting adapters (request IDs, store-aware search, media path generation, and dashboard access).

HTTP is API-only. REST is versioned under `/api/v1` and is reserved for authentication, files/uploads/downloads, exports, webhooks, broadcasting authentication, and health. Lighthouse serves business reads and writes at `/graphql`. Queue work uses Redis/Horizon; Reverb handles WebSockets; Scout targets Meilisearch or the database driver; private media uses a store-prefixed path generator.

The shared-schema Store isolation model keeps global identities in `users` and Store access in `store_memberships`. Domain entities have bigint internal keys and public ULIDs. A request-scoped `StoreContext` is set only after authentication and active-membership validation. Middleware and `StoreScoped` are defense-in-depth; policies and typed actions remain authoritative. PostgreSQL row-level security is deliberately deferred until it can be proven safe with Octane and pooled connections.

All external side effects are dispatched after commit, queued jobs restore and clear store state, and `ClearRequestContext` resets store, permission-team, authentication, locale, and logging context for long-lived workers.
