# Authentication module

## Ownership

`Modules/Authentication` owns:

- the shared `users` table, exclusive Platform/Store account scope, and `User` model;
- session login, logout, password reset, and signed email verification;
- Fortify-backed TOTP enrollment, MFA challenges, and recovery codes;
- Sanctum personal access tokens;
- roles, permissions, and the initial authorization catalog;
- validated assignments through `ScopedRoleAssignmentService`;
- Platform Admin and Store Admin interface-access resolution;
- public User and token resources;
- authentication REST routes and the `viewer`/`viewerStores` GraphQL fields.

It does not own stores or memberships. It requests first-store creation through `StoreProvisioner` and reads a user’s stores through the relationship defined against Stores-owned tables.

## Identifier behavior

`users.id` and `personal_access_tokens.id` are bigint internal keys. `public_id` is returned as the API `id` and used for signed verification routes or token revocation. `personal_access_tokens.store_id` is the resolved internal store key; token requests accept a Store ULID. Migrated token rows may contain a private `legacy_id` UUID to recognize bearer credentials issued before the bigint migration; new tokens leave it null.

## Execution flows

Registration:

1. `RegisterRequest` normalizes and validates user and store input.
2. `RegisterUser` creates a Store-scoped user in a database transaction.
3. It calls the Stores-owned `StoreProvisioner` contract.
4. Stores returns the created Store model after membership and Owner assignment.
5. Registration/verification events dispatch after commit.
6. The response exposes User and Store ULIDs.

Store token login:

1. `TokenLoginRequest` validates credentials and `store_id` as a ULID.
2. `IssueStoreToken` verifies the password, resolves `stores.public_id`, and checks active membership with bigint IDs.
3. If MFA is enabled, a short-lived cache challenge stores the Store ULID until completion.
4. Sanctum creates the token; the custom token record stores internal `store_id`.
5. The plaintext token is returned once. List/revoke operations expose only token and Store ULIDs.

Interface selection:

1. An authenticated client calls `GET /api/v1/auth/interfaces`.
2. It checks exclusive `users.scope`.
3. Platform users receive only Platform assignments and permission-filtered navigation; Store users load only active Store memberships and Store-scoped roles/permissions.
4. `Plans & Pricing` is returned only when the Platform user has `manage plans`;
   `Settings` is returned only with `manage platform settings`.
5. The response keeps both stable keys, but only one interface can be available.
6. The frontend chooses the matching shell, while scope middleware and policies continue to authorize every request.

Frontend consumption is documented in the
[Platform admin shell component guide](../components/admin-shell.md).

## Outbound communication

Billing's dependency on Platform identity and `manage plans` is documented in [Billing to Authentication](../module-communication/billing-to-authentication.md).

See [Authentication → Stores](../module-communication/authentication-to-stores.md). When another module needs identity information, prefer a small identity contract or event rather than importing credentials or token internals.
