# Authentication

Sanctum provides stateful first-party cookie authentication and tenant-scoped bearer tokens. Authentication REST routes live in `Modules/Authentication/routes/api.php`; GraphQL does not expose login or token mutations.

Registration creates a normalized lower-case email, UUID user, tenant, active membership, and owner role in one transaction. `Registered`, tenant-created, and verification notification work run after commit. Login uses the `web` session guard and regenerates the session. Token login requires a tenant UUID and an active membership, stores only the Sanctum hash, and returns the plain token once. A token with a tenant cannot be used with another `X-Tenant-ID`.

Password reset uses Laravel's broker and queued JSON notifications with a configured frontend reset URL. Email verification uses a temporary signed URL and returns JSON. Logout invalidates the session for cookies or revokes only the current bearer token. Super-admin is global and controlled only by the protected `is_platform_admin` attribute; the tenant header can never grant it.

Required environment values include `FRONTEND_RESET_PASSWORD_URL`, `FRONTEND_EMAIL_VERIFIED_URL`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, CORS origins, and mail settings. Never put credentials or real reset links in source control.
