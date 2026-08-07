# Store policies

Store policies are Store-owned legal and customer-information pages backed by
a Platform-wide type catalog. The Stores module owns the schema, services,
authorization, REST routes, version history, and published storefront reads.
The Settings module remains the source of language identities.

## Persistence model

`policy_types` is the global master catalog. Every row has a bigint internal
key, public ULID, unique machine `code`, display name/description, system flag,
sort order, and timezone-aware timestamps. The idempotent
`EnsurePolicyTypeCatalog` action maintains these system types: `privacy`,
`refund`, `shipping`, `terms`, `contact`, `cookie`, `billing`, and
`cancellation`. Platform administrators may add custom types; system types and
their codes are protected.

`store_policies` contains at most one row for a `(store_id, policy_type_id)`
pair. Slugs are also unique per Store. It keeps the default administrative
title, `draft`/`published` lifecycle, publication time, and nullable creator
and updater audit references. PostgreSQL requires published rows to have
`published_at` and draft rows not to have it.

`store_policy_translations` contains one localized title/content/SEO record
per `(store_policy_id, language_id)`. Content is stored as PostgreSQL text and
may contain Markdown or HTML that was sanitized before persistence. This
backend does not render or sanitize markup; consumers must use the platform's
approved content pipeline before accepting or displaying HTML.
Translation and version resources include the Settings-owned language
`lang_icon` URL so admin clients can label language tabs consistently.

`policy_versions` is an immutable content history. A version belongs to one
policy and one language, has a monotonically increasing positive integer, and
records the author and creation time. Language identity is deliberately added
to the proposed base structure so rollback cannot copy one locale into
another. Translation content changes create versions automatically. A restore
updates the live translation and appends a new version instead of changing
history.

Store deletion cascades policies, translations, and versions. Language and
policy-type deletion is restricted while referenced. User deletion nulls
audit references without deleting legal content.

## Authorization and lifecycle

All merchant management routes require Store scope, `X-Store-ID`, an active
membership, and `manage policies`. Owners and Managers receive this permission
from the authorization catalog. Any active member may list policy types,
policies, and version history; mutation services always re-check Store
ownership and `manage policies`.

A policy starts as `draft`. It cannot be published until at least one non-empty
translation exists. A published policy may not lose its last translation.
Unpublishing clears `published_at`; republishing assigns a new publication time.
The Storefront endpoints require Store context but no authenticated user and
return only published policies. Locale selection uses the requested `locale`,
then `stores.language_code`, then the first available translation.

## REST contracts

Platform policy-type administration uses `/api/v1/platform/policy-types`.
Listing is available to Platform accounts; create/update/delete requires
`manage platform settings`.

Selected-Store management uses:

| Method | Route | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/store/policy-types` | List the complete ordered master catalog. |
| `GET/POST` | `/api/v1/store/policies` | List Store policies or create a draft. |
| `GET/PATCH/DELETE` | `/api/v1/store/policies/{policy}` | Read, edit, or delete by public ULID. |
| `POST` | `/api/v1/store/policies/{policy}/publish` | Publish after content validation. |
| `POST` | `/api/v1/store/policies/{policy}/unpublish` | Return a policy to draft. |
| `PUT/DELETE` | `/api/v1/store/policies/{policy}/translations/{language}` | Upsert or remove localized content using public ULIDs. |
| `GET` | `/api/v1/store/policies/{policy}/versions` | List immutable language-scoped versions. |
| `POST` | `/api/v1/store/policies/{policy}/versions/{version}/restore` | Restore content and append a new version. |

Public reads use `GET /api/v1/storefront/policies` and
`GET /api/v1/storefront/policies/{slug}` with `X-Store-ID`; an optional
`locale` query parameter chooses localized content.
