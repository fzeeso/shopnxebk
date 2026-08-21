# GraphQL

Lighthouse serves `POST /graphql`. `viewer.scope` exposes the exclusive `platform` or `store` account class. `viewerStores` is meaningful only for Store users, and `activeStore` additionally requires Store scope plus valid active Store context. Catalog owns authenticated `categories`, `category`, `productTypes`, `productType`, `products`, and `product` queries plus explicit create/update/delete mutations in `Modules/Catalog/graphql/schema.graphql`.

Security is configured with Sanctum guards, explicit field directives/resolvers, a depth limit, complexity limit, pagination maximum of 100, production introspection control, request rate limiting, and no production traces. Store-sensitive fields must call typed actions/resolvers that enforce membership, token store, permission, policy, validation, transaction, and event rules. Do not use unrestricted `@whereConditions`, automatic store-sensitive CRUD directives, or arbitrary `Model::search` calls.

GraphQL errors use Lighthouse's validation/authentication handlers and are logged with the request ID while production responses omit traces and internal messages. Future modules should prefer cursor pagination and explicit allow-lists for filtering and ordering.

The [API manual](api-manual.md) documents request headers, Catalog inputs,
copy/paste queries and mutations, manual/automatic translation behavior,
polling, errors, and the client integration cycle. The generated
[GraphQL operation reference](generated/graphql-operations.md) is rebuilt by
`composer docs:update` and verified by `composer docs:check`.
