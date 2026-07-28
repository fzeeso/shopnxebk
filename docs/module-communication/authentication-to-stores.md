# Authentication → Stores communication

Authentication communicates with Stores in two intentional ways.

## Registration

`RegisterUser` depends on `Modules\Stores\Contracts\StoreProvisioner`. It supplies the newly persisted `User`, Store name, and Store slug. The implementation creates the Store, active owner membership, role assignment, and after-commit `StoreCreated` event. Authentication receives the Store only to serialize the registration response.

Authentication must not insert into `stores` or `store_memberships` directly. If provisioning needs new behavior, extend the Stores contract and update both module documents and this communication contract.

## Token issuance and store listing

`IssueStoreToken` resolves a submitted Store ULID using the Stores model, then checks Stores-owned memberships with internal bigint IDs. The token stores the internal Store key and API resources convert it back to the Store ULID. `viewerStores` and `GET /api/v1/auth/stores` return only active memberships.

This read dependency is security-sensitive. Changes require tests for malformed ULIDs, missing stores, suspended/non-members, cross-store tokens, and public-ID serialization.
