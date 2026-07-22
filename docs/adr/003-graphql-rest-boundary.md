# ADR 003: GraphQL and REST boundary

Status: accepted

GraphQL owns business reads/writes with explicit resolvers/actions. REST owns authentication, files, exports, webhooks, broadcasting authentication, and health because those workflows need HTTP semantics, streaming, signatures, or operational probes.
