# Authentication → Stores communication

Authentication communicates with Stores in two intentional ways.

## Registration

`RegisterUser` depends on `Modules\Stores\Contracts\StoreProvisioner`. It supplies a newly persisted `scope = store` User, Store name, and Store slug. The implementation creates the Store, active owner membership, validated Owner assignment, and after-commit `StoreCreated` event.

Authentication must not insert into `stores` or `store_memberships` directly. If provisioning needs new behavior, extend the Stores contract and update both module documents and this communication contract.

## Administrative account creation

Authentication owns `/api/v1/platform/users` and creates only `scope = platform` identities. Stores owns `/api/v1/platform/merchants` and `/api/v1/store/users`; those services create `scope = store` identities but use Authentication's `User`, `ScopedRoleAssignmentService`, registration event, and verification notification contract. Merchant creation delegates Store/membership creation to `StoreProvisioner` and shares one database transaction.

Authentication never creates a Store membership for a Platform account. Stores never assigns a Platform role. Role names are resolved from the extendable database catalog and constrained by `roles.scope` before assignment.

## Token issuance and store listing

`IssueStoreToken` first requires a Store-scoped account, resolves a submitted Store ULID, then checks Stores-owned memberships with internal bigint IDs. Platform accounts cannot receive Store-bound tokens. `viewerStores` and `GET /api/v1/auth/stores` are Store-only.

This read dependency is security-sensitive. Changes require tests for malformed ULIDs, missing stores, suspended/non-members, cross-store tokens, and public-ID serialization.

## Interface access profile

`AccountInterfaceAccessService` reads `users.scope` first and never queries Store memberships for Platform users. For Store users, each Store is resolved independently using its internal bigint ID and serialized with its public ULID. Exactly one interface can be available.
