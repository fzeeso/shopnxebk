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
their codes are protected. Creating a custom type also creates its disabled
policy row for every existing Store.

`store_policies` contains at most one row for a `(store_id, policy_type_id)`
pair. Slugs are also unique per Store. It keeps the default administrative
title, `disabled`/`draft`/`published` lifecycle, publication time, and nullable
creator and updater audit references. PostgreSQL requires published rows to
have `published_at` and disabled/draft rows not to have it.

`EnsureStorePolicyCatalog` creates one disabled policy for every master type
during Store provisioning. It is idempotent, so migrations can safely backfill
existing Stores and direct Platform Store creation uses the same invariant.
The initial title and slug come from the policy type; merchants may edit them
later without enabling or publishing the policy.

`store_policy_translations` contains one localized title/content/SEO record
per `(store_policy_id, language_id)`. Content is stored as PostgreSQL text and
may contain Markdown or HTML that was sanitized before persistence. This
backend does not render or sanitize markup; consumers must use the platform's
approved content pipeline before accepting or displaying HTML. Its non-null
`lock_it` flag defaults to false; merchant editors may enable it to prevent
future automated translation jobs from replacing their content.
Translation and version resources include the Settings-owned language
`lang_image` and `lang_icon` URLs so admin clients can label language tabs consistently.

Saving the active Store's default-language translation uses the shared
server-side OpenAI translation provider to generate the title, content, and SEO
fields for every other active Store language. Generated writes flow through
`AutomatedTranslationWriter`, so a target with `lock_it = true` is never
overwritten. Saving a non-default language is a manual edit and does not
cascade. Provider or structured-output failure returns a validation error and
rolls back the source write instead of leaving a partially translated policy.

`policy_versions` is an immutable content history. A version belongs to one
policy and one language, has a monotonically increasing positive integer, and
records the author and creation time. Language identity is deliberately added
to the proposed base structure so rollback cannot copy one locale into
another. Translation content changes create versions automatically. A restore
updates the live translation and appends a new version instead of changing
history.
Automatically generated content also appends a version whenever its content
differs from the previous unlocked translation.

Store deletion cascades policies, translations, and versions. Language and
policy-type deletion is restricted while referenced. User deletion nulls
audit references without deleting legal content.

## Authorization and lifecycle

All merchant management routes require Store scope, `X-Store-ID`, an active
membership, and `manage policies`. Owners and Managers receive this permission
from the authorization catalog. Any active member may list policy types,
policies, and version history; mutation services always re-check Store
ownership and `manage policies`.

A policy starts as `disabled` and remains editable. Enabling moves it to
`draft`; publishing requires at least one non-empty translation. A published
policy may not lose its last translation, and a disabled policy cannot bypass
the explicit enable step. Unpublishing returns a published policy to draft,
while disabling clears `published_at` and hides it without deleting its
translations or version history. The DELETE compatibility endpoint performs
this same non-destructive disable operation. Republishing assigns a new
publication time.
The Storefront endpoints require Store context but no authenticated user and
return only published policies. Locale selection uses the requested `locale`,
then `stores.language_code`, then the first available translation.

## REST contracts

All URLs below are relative to the configured application base URL. Platform
and selected-Store management endpoints require Sanctum authentication.
Selected-Store and storefront endpoints also require
`X-Store-ID: <store-public-ulid>`; storefront reads do not require a user.

Platform policy-type administration uses:

| Method | URL | Purpose |
| --- | --- | --- |
| `GET/POST` | `/api/v1/platform/policy-types` | Page the master catalog or create a custom type. |
| `PATCH/DELETE` | `/api/v1/platform/policy-types/{policyType}` | Update or delete an eligible custom type by public ULID. |

Listing is available to Platform accounts; create/update/delete requires
`manage platform settings`. A newly created custom type is provisioned as a
disabled policy for all existing Stores.

Selected-Store management uses:

| Method | Route | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/store/policy-types` | List the complete ordered master catalog. |
| `GET/POST` | `/api/v1/store/policies` | List Store policies or create a disabled policy for a missing type. |
| `GET/PATCH/DELETE` | `/api/v1/store/policies/{policy}` | Read, edit, or non-destructively disable by public ULID. |
| `POST` | `/api/v1/store/policies/{policy}/enable` | Move a disabled policy to draft. |
| `POST` | `/api/v1/store/policies/{policy}/disable` | Hide a policy and clear its publication time while preserving content/history. |
| `POST` | `/api/v1/store/policies/{policy}/publish` | Publish after content validation. |
| `POST` | `/api/v1/store/policies/{policy}/unpublish` | Return a policy to draft. |
| `PUT/DELETE` | `/api/v1/store/policies/{policy}/translations/{language}` | Upsert or remove localized content using public ULIDs. |
| `GET` | `/api/v1/store/policies/{policy}/versions` | List immutable language-scoped versions. |
| `POST` | `/api/v1/store/policies/{policy}/versions/{version}/restore` | Restore content and append a new version. |

Public Storefront URLs are:

| Method | URL | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/storefront/policies` | List published localized policies. |
| `GET` | `/api/v1/storefront/policies/{slug}` | Read one published localized policy. |

Both use `X-Store-ID`; an optional `locale` query parameter chooses localized
content.
