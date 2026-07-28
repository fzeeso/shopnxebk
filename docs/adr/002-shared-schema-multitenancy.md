# ADR 002: Shared-schema tenancy

Status: accepted

Phase 1 stores all stores in one PostgreSQL schema with bigint primary/foreign keys, public ULIDs, and a request-scoped Store context. It minimizes operational overhead while policies, scopes, token checks, and tests enforce isolation. RLS/database-per-store can be evaluated later with Octane/connection-pooling integration evidence.
