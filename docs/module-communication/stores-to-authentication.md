# Stores → Authentication communication

Stores communicates with Authentication while provisioning and authorizing access.

## Provisioning

`ProvisionStore` receives the Authentication-owned `User` through the `StoreProvisioner` contract and rejects any account not scoped to Store. After creating active membership, it calls Authentication’s `ScopedRoleAssignmentService`, which validates scope/membership, selects the Store team, and assigns `Owner`.

The Stores module may reference the stable User identity model for membership foreign keys, but it must not read or change passwords, MFA secrets, sessions, reset tokens, or Sanctum hashes.

## Request authorization

`EnsureStoreMembership` first requires `users.scope = store`, then reads the authenticated User identifier and the Authentication-owned token. Membership checks use internal bigint IDs. The token’s optional internal `store_id` must match current context.

Changes to token binding, User identity, role scope, or team-key configuration must update both module documents, both directional communication documents, and cross-store security tests.
