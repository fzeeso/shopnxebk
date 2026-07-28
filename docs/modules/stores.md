# Stores module

## Ownership

`Modules/Stores` owns:

- `stores` and `store_memberships`;
- Store and membership status enums;
- `StoreProvisioner`;
- Store ULID resolution from `X-Store-ID`;
- request-scoped `StoreContext`;
- active-membership and token/store enforcement;
- Store-scoped model, cache, queue, media, and search helpers;
- the `activeStore` GraphQL field.

## Identifier behavior

`stores.id` and `store_memberships.id` are bigint internal keys. Their `public_id` values are ULIDs. `store_memberships.store_id` and `user_id` are bigint foreign keys. Middleware resolves a public Store ULID once; downstream database work uses the internal key.

## Store selection flow

1. `ResolveStore` requires `X-Store-ID` for store-required routes; GraphQL uses `ResolveOptionalStore`.
2. `HeaderStoreFinder` rejects malformed ULIDs with 400 and missing records with 404.
3. `Store::makeCurrent()` and `RequestStoreContext::set()` establish current context.
4. `EnsureStoreMembership` requires an active membership and validates a bound token’s internal `store_id`.
5. The internal Store ID becomes Spatie Permission’s team key.
6. The action/policy runs.
7. `ClearRequestContext` removes all current state in a `finally` block.

## Provisioning flow

`ProvisionStore` creates the Store and active owner membership, ensures the authorization catalog exists, assigns global Store role `Owner` under the new internal Store team, and dispatches `StoreCreated` after commit. The provisioning caller owns the surrounding transaction.

## Outbound communication

See [Stores → Authentication](../module-communication/stores-to-authentication.md). Business modules should depend on `StoreContext`, Store ULIDs in public contracts, and bigint `store_id` in their own persistence.
