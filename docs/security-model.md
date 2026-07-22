# Security model

Security is layered: Sanctum token abilities, authenticated-user membership and Spatie tenant permissions, and model policies. A global platform super-admin is an explicit protected account attribute and is never inferred from a tenant header. Passwords are hashed, tokens are hashed by Sanctum, sensitive request fields/headers are filtered from Telescope, and production logging must not contain credentials, cookies, signatures, payment payloads, or file contents.

Tenant IDs are UUIDs. Tenant context is request scoped and cleared for Octane and queue workers. Tenant-owned data must use foreign keys, scopes, policies, and composite uniqueness. Search documents and cache keys are tenant-prefixed/filtered. Private media paths are tenant-prefixed and non-guessable. Incoming webhooks must verify raw-body signatures and idempotency keys before processing.

PostgreSQL RLS is a future hardening option; this release intentionally uses an explicit, tested application tenancy layer instead of a half-configured session-level policy.
