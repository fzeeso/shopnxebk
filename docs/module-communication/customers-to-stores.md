# Customers to Stores

Customers consumes `StoreContext` and `StoreAccessService`. Every API requires a
trusted active Store resolved from the Store-scoped token and `X-Store-ID`.
Reads require active membership; writes add `manage customers`. Every owned and
relationship table carries `store_id`, and every service query repeats the
active Store predicate even when an Eloquent scope is present.

Customers never accepts a Store bigint or Store public ID in request bodies.
Route bindings pass through `store.bindings`; composite database foreign keys
are the final cross-Store guard. Store deletion cascades the Store-owned
customer domain. Long-lived workers rely on Stores to clear request context and
permission team state.
