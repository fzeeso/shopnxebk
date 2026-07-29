# Security model

Security is layered: Sanctum token abilities, exclusive `users.scope`, authenticated-user membership, Spatie permissions, and model policies. Platform and Store accounts cannot share memberships, roles, direct permissions, or Store-bound tokens. A global Platform super-admin is never inferred from a Store header.

Store/entity public IDs are ULIDs; bigint IDs and bigint foreign keys remain internal. Store context is request scoped and cleared for Octane and queue workers. Store-owned data must use foreign keys, scopes, policies, and composite uniqueness. Search documents and cache keys are filtered with internal `store_id`. Private media paths use Store and media ULIDs. Incoming webhooks must verify raw-body signatures and idempotency keys before processing.

PostgreSQL RLS is a future hardening option; this release intentionally uses an explicit, tested application tenancy layer instead of a half-configured session-level policy.
