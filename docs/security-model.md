# Security model

Security is layered: Sanctum token abilities, authenticated-user membership and Spatie store permissions, and model policies. A global platform super-admin is an explicit platform-scoped role and is never inferred from a store header. Passwords are hashed, tokens are hashed by Sanctum, sensitive request fields/headers are filtered from Telescope, and production logging must not contain credentials, cookies, signatures, payment payloads, or file contents.

Store/entity public IDs are ULIDs; bigint IDs and bigint foreign keys remain internal. Store context is request scoped and cleared for Octane and queue workers. Store-owned data must use foreign keys, scopes, policies, and composite uniqueness. Search documents and cache keys are filtered with internal `store_id`. Private media paths use Store and media ULIDs. Incoming webhooks must verify raw-body signatures and idempotency keys before processing.

PostgreSQL RLS is a future hardening option; this release intentionally uses an explicit, tested application tenancy layer instead of a half-configured session-level policy.
