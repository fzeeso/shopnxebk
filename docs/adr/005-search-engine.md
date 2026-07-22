# ADR 005: Meilisearch via Scout

Status: accepted

Scout targets Meilisearch in production and its database driver in reduced-infrastructure development/tests. Search is asynchronous, shared-index, tenant-filtered, and never the system of record. A tenant-aware search abstraction prevents unscoped model searches.
