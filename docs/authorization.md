# Authorization model

Authorization combines five checks:

1. Sanctum authenticates the session or bearer token.
2. `users.scope` must match the owning interface or route.
3. A Store-bound token must belong to a Store user and match the selected internal `store_id`.
4. Store membership must be active.
5. Spatie Permission and Laravel policies authorize the requested operation.

`users.scope`, `roles.scope`, and `permissions.scope` are `platform` or `store`. A user, role, and permission must have matching scopes. Platform assignments have a null Store; Store assignments require active membership and the matching internal `store_id`.

`manage platform users` is initially granted only to `Super Admin` and protects the Platform user/role catalog APIs. `manage stores` protects the Platform Store catalog and merchant provisioning APIs. Inside a Store, `manage store members` permits member listing while both `manage store members` and `manage store roles` are required to create a Store user with roles.

`EnsureAuthorizationCatalog` maintains the initial catalog described in [application context](context.md). It is idempotent: provisioning or seeding may call it repeatedly. Adding a role or permission requires updating that catalog, the relevant policies/actions, tests, this document, and the affected module communication contracts.

Platform authorization is resolved with no active permission team. `User::isPlatformSuperAdmin()` checks the global `Super Admin` role. Store authorization sets the permission team to `stores.id`; a header contains only `stores.public_id` and is never accepted as proof of membership or privilege.

The Platform `manage platform settings` permission is assigned to `Super
Admin` and protects global Settings mutations such as creating/editing
languages and currencies. Support and Billing may read the catalogs but cannot
mutate them. Store language selection is separate and requires the Store-scoped
`manage store` permission after Store resolution and active-membership checks.

`ScopedRoleAssignmentService` is the application write path for role assignments. PostgreSQL triggers independently reject cross-scope memberships, assignments, scope changes with existing access, and Platform Store-bound tokens.

`AccountInterfaceAccessService` groups authorization data for frontend selection. `platform_admin` represents the SaaS Owner and platform staff; `store_admin` represents merchant administrators and Store staff. These interfaces are mutually exclusive. Platform navigation is permission-filtered: `Plans & Pricing` requires `manage plans`, `Settings` requires `manage platform settings`, `Admin Users` requires `manage platform users`, and `Merchants` requires `manage stores`; the corresponding APIs independently enforce Platform scope and their permissions.

Role/permission examples are a starting catalog, not a closed enum. New business modules should introduce permissions using verb-object language such as `manage products`, then add them to intended roles explicitly.
