# Authorization model

Authorization combines four checks:

1. Sanctum authenticates the session or bearer token.
2. A store-bound token must match the selected internal `store_id`.
3. Store membership must be active.
4. Spatie Permission and Laravel policies authorize the requested operation.

The `roles` and `permissions` tables use internal bigint keys and public ULIDs. `scope` is `platform` or `store`. Role names are unique within their guard, scope, and optional store boundary. Assignments in `model_has_roles` and `model_has_permissions` carry the internal `store_id` when they apply to one store.

`EnsureAuthorizationCatalog` maintains the initial catalog described in [application context](context.md). It is idempotent: provisioning or seeding may call it repeatedly. Adding a role or permission requires updating that catalog, the relevant policies/actions, tests, this document, and the affected module communication contracts.

Platform authorization is resolved with no active permission team. `User::isPlatformSuperAdmin()` checks the global `Super Admin` role. Store authorization sets the permission team to `stores.id`; a header contains only `stores.public_id` and is never accepted as proof of membership or privilege.

Role/permission examples are a starting catalog, not a closed enum. New business modules should introduce permissions using verb-object language such as `manage products`, then add them to intended roles explicitly.
