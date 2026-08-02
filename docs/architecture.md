# Architecture

The application is a modular monolith: one Laravel process, one PostgreSQL shared schema, and explicit module boundaries. `Modules/Authentication`, `Modules/Settings`, `Modules/Stores`, and `Modules/Billing` own their models, migrations, routes, policies/services, resources, and tests. Settings owns global SaaS configuration and never enters Store context; Stores owns merchant data and Store-specific selections. Billing owns Platform plan/feature administration; it never enters Store context. `app/Support` contains only small cross-cutting adapters (request IDs, store-aware search, media path generation, and dashboard access).

HTTP is API-only. REST is versioned under `/api/v1` and is reserved for authentication, files/uploads/downloads, exports, webhooks, broadcasting authentication, and health. Lighthouse serves business reads and writes at `/graphql`. Queue work uses Redis/Horizon; Reverb handles WebSockets; Scout targets Meilisearch or the database driver; private media uses a store-prefixed path generator.

The shared-schema Store isolation model keeps identities in `users` with an exclusive `platform` or `store` scope. Only Store users can appear in `store_users`. That relationship grants Store membership; Store-scoped roles and permissions for the same internal `store_id` determine allowed actions. Domain entities have bigint internal keys and public ULIDs. A request-scoped `StoreContext` is set only after account-scope and active-membership validation.

All external side effects are dispatched after commit, queued jobs restore and clear store state, and `ClearRequestContext` resets store, permission-team, authentication, locale, and logging context for long-lived workers.
