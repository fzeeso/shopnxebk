# Stores → Authentication communication

Stores communicates with Authentication while provisioning and authorizing access.

## Provisioning

`ProvisionStore` receives the Authentication-owned `User` through the `StoreProvisioner` contract. It calls `EnsureAuthorizationCatalog`, switches Spatie Permission’s team to the internal Store bigint ID, and assigns the global Store role `Owner`. It restores the previous team in a `finally` block.

The Stores module may reference the stable User identity model for membership foreign keys, but it must not read or change passwords, MFA secrets, sessions, reset tokens, or Sanctum hashes.

## Request authorization

`EnsureStoreMembership` reads the authenticated User identifier and the custom Authentication-owned `PersonalAccessToken`. Membership checks use internal bigint IDs. The token’s optional internal `store_id` must match current context. Store permissions are then evaluated with `stores.id` as the Spatie team key.

Changes to token binding, User identity, role scope, or team-key configuration must update both module documents, both directional communication documents, and cross-store security tests.
