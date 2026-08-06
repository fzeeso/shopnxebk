# ADR 006: Store-local catalog persistence

Status: accepted

The Catalog domain uses normalized Store-owned PostgreSQL tables with separate
translation rows, a strict category taxonomy, flexible merchandising
collections, products/options/variants, fulfillment metadata, and typed custom
fields. Categories and collections remain distinct because navigation
hierarchy and dynamic merchandising have different lifecycle and integrity
rules.

Every addressable entity has an internal bigint key and public ULID. Store IDs
are denormalized into translation and relationship tables so composite foreign
keys enforce tenant consistency without relying only on application scopes.
Localized slugs are unique per Store and locale. Prices use integer minor units
and ISO-style currency codes in line with the platform money contract.

This creates more columns and constraints than a minimal pivot design, but it
prevents cross-Store relationships, supports localized URLs without global
slug collisions, and gives future Catalog APIs, Search, Inventory, Files, and
Orders stable identifiers. The initial delivery is persistence-only; public
application contracts require separate authorization, service, and API work.
