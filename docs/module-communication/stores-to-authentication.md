# Stores → Authentication communication

Stores communicates with Authentication while provisioning and authorizing access.

## Provisioning

`ProvisionStore` receives the Authentication-owned `User` through the `StoreProvisioner` contract and rejects any account not scoped to Store. After creating active membership, it calls Authentication’s `ScopedRoleAssignmentService`, which validates scope/membership, selects the Store team, and assigns `Owner`.

The Stores module may reference the stable User identity model for membership foreign keys, but it must not read or change passwords, MFA secrets, sessions, reset tokens, or Sanctum hashes.

## Merchant and Store-user provisioning

`PlatformMerchantService` and `StoreUserAdminService` create Authentication-owned User records only as part of their explicit onboarding workflows. Input passwords are passed to the User's hashed cast and are never serialized or logged. Both flows call `ScopedRoleAssignmentService` after an active membership exists, then dispatch Authentication's registration event and queued verification notification after commit.

Platform merchant provisioning requires `manage stores` with no active Store team. Store-user provisioning requires the selected Store, active actor membership, `manage store members`, and `manage store roles`. The role service accepts only Store-scoped roles for that Store.

## Request authorization

`EnsureStoreMembership` first requires `users.scope = store`, then reads the authenticated User identifier and the Authentication-owned token. Membership checks use internal bigint IDs. The token’s optional internal `store_id` must match current context.

Changes to token binding, User identity, role scope, or team-key configuration must update both module documents, both directional communication documents, and cross-store security tests.
