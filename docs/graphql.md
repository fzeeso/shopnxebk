# GraphQL

Lighthouse serves `POST /graphql`. The root schema imports module schemas. The current public field is `apiVersion`; `viewer` and `viewerTenants` require Sanctum, and `activeTenant` additionally requires a valid active tenant context. Authentication, uploads, exports, webhooks, and file streaming remain REST concerns.

Security is configured with Sanctum guards, explicit field directives/resolvers, a depth limit, complexity limit, pagination maximum of 100, production introspection control, request rate limiting, and no production traces. Tenant-sensitive fields must call typed actions/resolvers that enforce membership, token tenant, permission, policy, validation, transaction, and event rules. Do not use unrestricted `@whereConditions`, automatic tenant-sensitive CRUD directives, or arbitrary `Model::search` calls.

GraphQL errors use Lighthouse's validation/authentication handlers and are logged with the request ID while production responses omit traces and internal messages. Future modules should prefer cursor pagination and explicit allow-lists for filtering and ordering.
