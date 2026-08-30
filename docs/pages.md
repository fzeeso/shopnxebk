# Store pages

Store pages are Store-owned, hierarchical, multilingual content records managed
through authenticated Store Admin REST APIs. The Stores module owns their
schema, lifecycle, authorization, manual translation writes, and shared queued
automatic-translation adapter. This first release does not expose storefront
page reads; publishing records lifecycle state for the future storefront
resolver.

## Persistence model

`pages` contains language-neutral data: bigint internal ID, public ULID,
`store_id`, same-Store parent relationship, page type, lifecycle status, sort
order, layout key, homepage/customer/SEO flags, type-specific external/feed or
contact configuration, audit users, and timezone-aware publication/timestamps.
Page type is one of `content`, `contact`, `external_link`, or `rss`; lifecycle
is `disabled`, `draft`, or `published`. PostgreSQL requires a publication time
only for published rows and permits at most one homepage per Store.

`page_translations` contains one row for each `(page_id, language_id)`. It owns
the localized title, Unicode-capable slug, content, summary, SEO title,
description/keywords, search keywords, and non-null `lock_it` flag. A composite
foreign key requires the translation and page to have the same Store, while a
unique expression index prevents duplicate case-insensitive slugs inside one
Store/language. Application validation additionally requires the language to
be active for the selected Store.

The hierarchy uses `parent_id`, not stored ancestor lists or nested-set left/
right values. The composite parent foreign key prevents cross-Store parents,
and the management service rejects self/descendant cycles. Parent deletion is
restricted; clients must reparent children before physical maintenance removes
a page. The public DELETE API is deliberately non-destructive and disables the
page instead.

## Authorization and lifecycle

Every route requires Sanctum authentication, a Store-scoped identity,
`X-Store-ID`, active membership, Store-first bindings, and the existing Store
context guards. Active members may list/read pages. Mutations currently reuse
`manage policies`, already assigned to Owner and Manager, so rollout does not
change existing permission rows.

A page is created as `draft`. `disable` and DELETE set `disabled` and clear
publication time without deleting hierarchy or translations. `enable` moves a
disabled page to draft. Publishing requires the Store's default-language
translation; a `content` page additionally requires non-empty default-language
content, `external_link` requires `external_url`, and `rss` requires
`feed_url`. Unpublish returns a published page to draft. Replacing the homepage
flag clears it from the previous Store homepage in the same transaction.

## Translation behavior

Create accepts one or more translation objects identified by public language
ULID. The dedicated translation endpoint upserts one complete language row.
Slugs accept Unicode letters/numbers with single hyphens between segments and
are unique per Store/language.

When a default-language translation is saved, the `page` translation handler
snapshots title, nullable content/summary, and SEO/search fields, records a
durable `translation_requests` row, and dispatches only after commit. The
worker regenerates unique target slugs from translated titles and writes
through `AutomatedTranslationWriter`. A target with `lock_it = true` is never
overwritten. Non-default manual edits do not cascade. Generated translation
status uses the shared authenticated
`GET /api/v1/store/translation-requests/{translationRequest}` endpoint.

## Store Admin REST contract

All routes below are relative to the configured API base and require
`Authorization` plus `X-Store-ID`.

| Method | Route | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/store/pages` | Paginate/filter selected-Store pages. |
| `POST` | `/api/v1/store/pages` | Create a draft page and initial translations. |
| `GET` | `/api/v1/store/pages/{page}` | Read one page by public ULID. |
| `PATCH` | `/api/v1/store/pages/{page}` | Update language-neutral configuration/hierarchy. |
| `DELETE` | `/api/v1/store/pages/{page}` | Non-destructively disable the page. |
| `POST` | `/api/v1/store/pages/{page}/enable` | Move disabled to draft. |
| `POST` | `/api/v1/store/pages/{page}/disable` | Disable and clear publication time. |
| `POST` | `/api/v1/store/pages/{page}/publish` | Validate and publish. |
| `POST` | `/api/v1/store/pages/{page}/unpublish` | Return published to draft. |
| `PUT` | `/api/v1/store/pages/{page}/translations/{language}` | Upsert one complete localized record. |
| `DELETE` | `/api/v1/store/pages/{page}/translations/{language}` | Delete an eligible localized record. |

List filters are `page`, `per_page` (maximum 100), `status`, `page_type`,
`parent_id`, `root_only`, `language_id`, and localized `search`. All page,
parent, language, user, and translation identifiers returned publicly are
ULIDs; internal bigint keys never cross the API.
