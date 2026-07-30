# Authentication → Stores communication

Authentication communicates with Stores in two intentional ways.

## Registration

`RegisterUser` depends on `Modules\Stores\Contracts\StoreProvisioner`. It supplies a newly persisted `scope = store` User, Store name, and Store slug. The implementation creates the Store, active owner membership, validated Owner assignment, and after-commit `StoreCreated` event.

Authentication must not insert into `stores` or `store_memberships` directly. If provisioning needs new behavior, extend the Stores contract and update both module documents and this communication contract.

## Token issuance and store listing

`IssueStoreToken` first requires a Store-scoped account, resolves a submitted Store ULID, then checks Stores-owned memberships with internal bigint IDs. Platform accounts cannot receive Store-bound tokens. `viewerStores` and `GET /api/v1/auth/stores` are Store-only.

This read dependency is security-sensitive. Changes require tests for malformed ULIDs, missing stores, suspended/non-members, cross-store tokens, and public-ID serialization.

## Interface access profile

`AccountInterfaceAccessService` reads `users.scope` first and never queries Store memberships for Platform users. For Store users, each Store is resolved independently using its internal bigint ID and serialized with its public ULID. Exactly one interface can be available.
