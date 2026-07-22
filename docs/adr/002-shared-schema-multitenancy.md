# ADR 002: Shared-schema tenancy

Status: accepted

Phase 1 stores all tenants in one PostgreSQL schema with UUID foreign keys and a request-scoped tenant context. It minimizes operational overhead while policies, scopes, token checks, and tests enforce isolation. RLS/database-per-tenant can be evaluated later with Octane/connection-pooling integration evidence.
