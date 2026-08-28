# REST boundary

Implemented REST routes are documented in `docs/openapi.yaml`. All application routes are JSON or streamed responses and are versioned under `/api/v1`, except `/api/health/*` and `/graphql`.

Reserved route families are intentionally not implemented yet: uploads and presigns, export creation/status/download, authorized file downloads, provider webhooks, and `/api/broadcasting/auth`. Uploads must validate type/size and authorize before attaching media; downloads issue temporary private URLs; exports queue large work; webhooks verify the raw body and provider signature before parsing, enforce idempotency, and resolve a trusted installation rather than trusting `X-Store-ID`.

Every response receives `X-Request-ID`. 401, 403, 404, and 422 responses are structured JSON and never redirect to a login page.

Table-style list endpoints accept `page` and `per_page`. The usual default page
size is 25; the Platform Store catalog defaults to 10 for its admin directory.
The maximum is 100. Responses keep records in `data` and add Laravel
`links` and `meta` pagination objects. This applies to personal access tokens,
Platform users, Stores, merchants, plans, features, currencies, languages,
Themes, Theme publishers/categories, installed Store Themes, and selected-Store
users. Selector/option endpoints such as roles, active Store memberships, and
Store language choices remain complete unpaginated lists.

User and merchant administration contracts:

| Method | Route | Scope and purpose |
| --- | --- | --- |
| `GET/POST` | `/api/v1/platform/users` | Page Platform staff or create one; requires `manage platform users`. |
| `GET/PATCH` | `/api/v1/platform/users/{user}` | View or edit a Platform user by public ULID. |
| `GET` | `/api/v1/platform/roles` | List assignable Platform roles. |
| `GET/POST` | `/api/v1/platform/stores` | Search/filter/page or directly create Store rows; requires `manage stores`. |
| `GET/PATCH` | `/api/v1/platform/stores/{store}` | View or edit a Store by public ULID; requires `manage stores`. |
| `GET/POST` | `/api/v1/platform/stores/{store}/domains` | List or add normalized Store domains; requires `manage stores`. |
| `PATCH` | `/api/v1/platform/stores/{store}/domains/{domain}` | Update domain routing/SSL/verification/primary state by public ULID. |
| `GET/POST` | `/api/v1/platform/merchants` | Page merchants or atomically create a draft Store, Store owner, settings, platform domain, licensed published Theme copy, membership, and Store roles; requires `manage stores`. |
| `GET/PATCH` | `/api/v1/platform/merchants/{merchant}` | View or edit a merchant owner/Store by Store ULID. |
| `GET` | `/api/v1/platform/merchant-roles` | List assignable Store roles for merchant creation. |
| `GET/POST` | `/api/v1/store/users` | Page members or create a new Store user under `X-Store-ID`; creation requires member and role management permissions. |
| `GET` | `/api/v1/store/roles` | List roles for the selected Store; requires `manage store roles`. |

Role input is scope-filtered and passwords are write-only. See [User and merchant management](user-merchant-management.md).

The Platform Store list accepts case-insensitive `search` over name, legal
name, slug, Store email, primary domain, and related member name/email through
`store_users`. It composes exact status, business type, locale/country,
verification, and capability filters with creation-date range, whitelisted
sorting, and `page`/`per_page` pagination. Page size defaults to 10 and is
capped at 100. The response uses Laravel `data`, `meta`, and `links` pagination
fields. Each list item adds `owner` with the public ID, name, and email from the
earliest membership row, or `null` when the Store has no membership.

Direct Platform Store creation creates an unassigned `draft` Store unless a
status is supplied. It also creates normalized locale/settings rows, a
generated platform domain, and an optional pending custom primary domain. It
never creates an owner, membership, Store role, subscription, or plan
assignment. Use `/api/v1/platform/merchants` for the complete owner-aware
workflow. Platform Store writes accept validated public profile, locale,
lifecycle, and capability fields; internal Billing links and raw JSON are
prohibited. Domain list/create/update routes own hostname type, routing state,
SSL state, verification state, and primary selection. See the
[Platform Stores admin component guide](components/platform-stores-admin.md).

Accepted Store lifecycle values are `draft`, `trial`, `active`, `suspended`,
`frozen`, and `closed`. `pending` and `cancelled` are retired legacy values.

Store management REST contracts:

| Method | Route | Scope and purpose |
| --- | --- | --- |
| `POST` | `/api/v1/stores` | Create another Store for the authenticated Store account and assign Owner. |
| `GET` | `/api/v1/store` | View the active member's selected `X-Store-ID`. |
| `PATCH` | `/api/v1/store/profile` | Update merchant-owned profile fields; requires `manage store`. |
| `GET` | `/api/v1/store/settings` | View selected Store locale, normalized contact/address values, translation/Platform-search opt-ins, preferences, and read-only capabilities. |
| `PATCH` | `/api/v1/store/settings` | Update normalized contact/address values and boolean translation/Platform-search opt-ins, and merge validated locale/preferences; currency and language must be active Platform catalog entries; requires `manage store`. |

Merchant-facing Store creation accepts optional `theme_template_key` and
defaults it from `STORE_DEFAULT_THEME_KEY`. The generated platform domain is
`<slug>.<STOREFRONT_ROOT_DOMAIN>`. Successful registration,
`POST /api/v1/stores`, and `POST /api/v1/platform/merchants` responses include
`dashboard_url`, which selects the Store using its public ULID. Provisioning is
atomic and a failed settings/domain/theme/membership/role step creates no
partial Store.

Platform lifecycle, Billing links, verification, capabilities, trial dates, and raw JSON are prohibited in Store profile/settings requests. See [Store management](store-management.md).

Bearer-authenticated Store routes require a Store-bound token with the
`store:access` ability. Account-only and unbound tokens are rejected even when
their user has an active membership. New tokens expire after 30 days by
default, and password reset revokes all bearer tokens for the account.

Plans & Pricing REST contracts:

| Method | Route | Purpose |
| --- | --- | --- |
| `GET/POST` | `/api/v1/platform/plans` | Page or add plans. |
| `GET/PATCH/DELETE` | `/api/v1/platform/plans/{plan}` | View, edit, or safely remove a plan by public ULID. |
| `GET/POST` | `/api/v1/platform/features` | Page or add reusable feature definitions. |
| `PATCH/DELETE` | `/api/v1/platform/features/{feature}` | Edit or remove an unassigned feature. |
| `PUT/DELETE` | `/api/v1/platform/plans/{plan}/features/{feature}` | Add/update or detach a plan feature/add-on. |

All plan routes require Platform scope and `manage plans`. Fixed and add-on prices are integer minor units. Assigned plans must be archived instead of deleted. See [Plans & Pricing](plans-and-pricing.md).

Theme marketplace Platform REST contracts:

| Method | Route | Scope and purpose |
| --- | --- | --- |
| `GET/POST` | `/api/v1/platform/themes` | Page/filter or create marketplace listings; requires `manage marketplace`. |
| `GET/PATCH` | `/api/v1/platform/themes/{theme}` | View or update a listing by Theme public ULID. |
| `GET/POST` | `/api/v1/platform/theme-publishers` | Page or create Platform/third-party publishers. |
| `PATCH` | `/api/v1/platform/theme-publishers/{publisher}` | Update publisher identity, support, verification, lifecycle, and commission default. |
| `GET/POST` | `/api/v1/platform/theme-categories` | Page or create industry/style/feature/catalog-size taxonomy. |
| `PATCH` | `/api/v1/platform/theme-categories/{category}` | Update taxonomy/facet metadata. |
| `POST` | `/api/v1/platform/themes/{theme}/versions` | Register immutable version/artifact metadata. |
| `POST` | `/api/v1/platform/theme-versions/{version}/submit` | Create the next numbered review submission. |
| `PATCH` | `/api/v1/platform/theme-submissions/{submission}/review` | Approve, request changes, or reject a submission. |
| `POST` | `/api/v1/platform/theme-versions/{version}/publish` | Publish an approved version and update the Theme current-version pointer. |
| `POST` | `/api/v1/platform/themes/{theme}/licenses` | Issue a Store license. |
| `PATCH` | `/api/v1/platform/theme-licenses/{license}` | Update/revoke a license lifecycle. |

Platform Theme routes never send `X-Store-ID`. The global listing/version
does not contain merchant settings, layout, or CSS.

Selected-Store Theme REST contracts:

| Method | Route | Scope and purpose |
| --- | --- | --- |
| `GET` | `/api/v1/store/theme-marketplace` | Page installable published Themes for the selected Store. |
| `GET` | `/api/v1/store/themes` | Page licensed installed/customized copies for the selected Store. |
| `POST` | `/api/v1/store/themes` | Install a current version as a draft after validating/issuing the license. |
| `PATCH` | `/api/v1/store/themes/{storeTheme}` | Update name/settings/template/CSS using required `customization_revision`. |
| `POST` | `/api/v1/store/themes/{storeTheme}/duplicate` | Create a draft child copy. |
| `POST` | `/api/v1/store/themes/{storeTheme}/publish` | Publish this copy and archive the former published copy. |
| `DELETE` | `/api/v1/store/themes/{storeTheme}` | Soft-delete a non-published copy. |

These routes require Store scope, `X-Store-ID`, active membership, and
`manage themes`. See [Theme marketplace and Store themes](themes.md).

Platform Settings REST contracts:

| Method | Route | Scope and purpose |
| --- | --- | --- |
| `GET` | `/api/v1/platform/settings/currencies` | Page the currency catalog for a Platform-scoped user. |
| `POST` | `/api/v1/platform/settings/currencies` | Add a non-USD currency; requires `manage platform settings`. |
| `PATCH` | `/api/v1/platform/settings/currencies/{currency}` | Update display options, active state, or USD-relative rate by public ULID; requires `manage platform settings`. |
| `GET` | `/api/v1/platform/settings/languages` | Page the language catalog for a Platform-scoped user. |
| `POST` | `/api/v1/platform/settings/languages` | Add a supported language; requires `manage platform settings`. |
| `PATCH` | `/api/v1/platform/settings/languages/{language}` | Update names, country-flag icon/image, direction, or active state by public ULID; requires `manage platform settings`. |

Admin component request flow:

1. Load `GET /api/v1/auth/session` for the User and interface profile in one request. Use `GET /api/v1/auth/interfaces` only when the User is already known.
2. Mount `/admin/settings` only when the returned Platform navigation contains
   `platform_settings`.
3. Load Languages and Currencies independently so one section may retry without
   blocking the other.
4. Submit create/edit requests to the canonical Settings routes.
5. Render `422` errors beside fields; treat `401` as session expiry and `403`
   as permission loss.
6. Never attach `X-Store-ID` to Platform Settings requests.

Currency rates are returned as fixed-scale decimal strings and mean
`1 USD = X target currency units`. A null rate is deliberately unconfigured.
USD is the active base and cannot be deactivated or changed from rate `1`.
Codes are normalized to uppercase; symbols may contain Unicode; decimal places
must be between zero and four.

The shorter `/api/v1/platform/currencies*` and
`/api/v1/platform/languages*` routes remain backward-compatible aliases.
Locale and currency codes are immutable after creation.

See the [Platform Settings admin component guide](components/platform-settings-admin.md)
for field behavior, states, and acceptance criteria.

Store language REST contracts:

| Method | Route | Scope and purpose |
| --- | --- | --- |
| `GET` | `/api/v1/store/languages` | List active languages plus the selected/default state for the authorized `X-Store-ID`. |
| `PUT` | `/api/v1/store/languages` | Replace the Store language set and default; requires `manage store`. |

Language and Store identifiers in request/response bodies are public ULIDs.
`locale` accepts a language plus optional region and normalizes hyphens to an
underscore. Store updates require at least one language and a default included
in the selected set. Language catalog and Store option responses include a
render-ready `lang_image` and `lang_icon` URLs for storefront/admin switchers
and translation editors.

Store policy REST contracts:

| Method | Route | Scope and purpose |
| --- | --- | --- |
| `GET/POST` | `/api/v1/platform/policy-types` | Page or create master policy types; creation provisions a disabled policy for every existing Store and writes require `manage platform settings`. |
| `PATCH/DELETE` | `/api/v1/platform/policy-types/{policyType}` | Edit or remove a custom unreferenced type; system types are protected. |
| `GET` | `/api/v1/store/policy-types` | List the ordered policy-type catalog for an active Store member. |
| `GET/POST` | `/api/v1/store/policies` | List Store policies or create one disabled policy for a missing type; writes require `manage policies`. |
| `GET/PATCH/DELETE` | `/api/v1/store/policies/{storePolicy}` | Read, edit, or non-destructively disable one Store-owned policy. |
| `POST` | `/api/v1/store/policies/{storePolicy}/enable` | Move a disabled policy to draft. |
| `POST` | `/api/v1/store/policies/{storePolicy}/disable` | Hide a policy without deleting its translations or versions. |
| `POST` | `/api/v1/store/policies/{storePolicy}/publish` | Publish an enabled draft policy containing at least one translation. |
| `POST` | `/api/v1/store/policies/{storePolicy}/unpublish` | Return a published policy to draft. |
| `PUT/DELETE` | `/api/v1/store/policies/{storePolicy}/translations/{language}` | Upsert or delete localized title/content/SEO fields and the automated-overwrite lock. |
| `GET` | `/api/v1/store/translation-requests/{translationRequest}` | Read the selected Store's asynchronous translation status by public ULID. |
| `GET` | `/api/v1/store/policies/{storePolicy}/versions` | List immutable per-language content versions. |
| `POST` | `/api/v1/store/policies/{storePolicy}/versions/{policyVersion}/restore` | Restore content and append a new version. |
| `GET` | `/api/v1/storefront/policies[/{slug}]` | Publicly read published policies for `X-Store-ID`, optionally selecting `locale`. |

All entity and language parameters use public ULIDs except the public policy
slug. Every Store creation path automatically creates one disabled policy for
each master policy type. Saving a policy translation in the Store's default
language automatically generates every unlocked active Store language through
the server-side translation provider and appends versions for generated
content. The source and durable request commit first; provider work starts only
after commit. Saving a non-default language remains a manual, non-cascading
write. Provider failures do not roll back source content. The write response's
nullable `translation_request` can be polled at the generic status URL. See
[Store policies](store-policies.md).

Catalog API exposure is intentionally mixed. Brands, Products, nested Product
Images, and Fulfillment Types have REST contracts. Categories and Product Types
do not: their supported list/detail/create/update/delete contracts are GraphQL
operations at `POST /graphql`. No `/api/v1/store/categories` or
`/api/v1/store/product-types` route is registered. Products remain available
through both REST and GraphQL.

Store Brand REST contracts:

Base API URL: `/api/v1/store/brands`

| Method | URL | Scope and purpose |
| --- | --- | --- |
| `GET` | `/api/v1/store/brands` | List paginated Brands for the selected Store. |
| `POST` | `/api/v1/store/brands` | Create a Brand with at least one translation and optional multipart `image`/`banner` uploads. |
| `GET` | `/api/v1/store/brands/{brand}` | Read one Brand by public ULID. |
| `PATCH` | `/api/v1/store/brands/{brand}` | Update Brand fields, translations, or replace/clear managed media. |
| `DELETE` | `/api/v1/store/brands/{brand}` | Delete the Brand, all translations, and its managed image/banner objects. |
| `GET` | `/api/v1/store/brands/{brand}/media/{collection}?expires=...&signature=...` | Stream the `image` or `banner` from private storage using the short-lived signed URL returned in Brand media metadata. |

All Brand routes require Store scope, `X-Store-ID`, and active membership.
Writes additionally require `manage products`. Image writes use multipart
`image` and `banner` fields; responses include their media metadata alongside
the legacy `logo_url`, HTTP(S) `website_url`, free-form `origin`, active/sort
state, and localized name/slug/description/SEO records with `lock_it`. A create
or translation-bearing update generates every unlocked active Store language
from the default-language source through the shared server-side OpenAI provider
after the source transaction commits.
Locked rows remain merchant-controlled; clearing `lock_it` opts that locale
back into automatic refresh. Translation failures are retried independently
and never roll back the Brand write. Create/update responses contain a nullable
`translation_request` object with its public status URL identifier. See
[Catalog](catalog.md).

Brand CRUD URLs require `Authorization: Bearer <token>` and the public Store
ULID in `X-Store-ID`. The media URL instead requires its unexpired, unmodified
query signature and does not accept a client-selected storage path. Use
`multipart/form-data` when sending `image` or `banner`; JSON is accepted for
metadata-only requests. The complete request/response schemas are published in
[OpenAPI](openapi.yaml).

Fulfillment Type REST contracts:

| Method | URL | Scope and purpose |
| --- | --- | --- |
| `GET/POST` | `/api/v1/platform/settings/fulfillment-types` | List the global catalog or create a type; writes require `manage platform settings`. |
| `GET/PATCH` | `/api/v1/platform/settings/fulfillment-types/{code}` | Read or update one type by immutable stable code; writes require `manage platform settings`. |
| `GET` | `/api/v1/store/fulfillment-types` | List active types for an authenticated member of the selected Store. |

Product REST contracts:

| Method | URL | Scope and purpose |
| --- | --- | --- |
| `GET/POST` | `/api/v1/store/products` | Page/filter Products or create one with translations and commerce metadata. |
| `GET/PATCH/DELETE` | `/api/v1/store/products/{product}` | Read, partially update, or delete a Product by public ULID. |
| `GET/POST` | `/api/v1/store/products/{product}/images` | Page Product image metadata or add a gallery image locator. |
| `GET/PATCH/DELETE` | `/api/v1/store/products/{product}/images/{image}` | Read, partially update, or delete nested image metadata by public ULID. |

Product reads require active Store membership; writes require `manage
products`. Create requires at least one active Store-locale translation. REST
uses snake_case and exposes the Product's pricing, stock, shipping, identifier,
condition/release, merchandising, points, and review fields. Category and
classification IDs are public ULIDs. The Product service preserves the same
Store isolation, primary-category invariant, publication lifecycle, manual
translation locks, and after-commit translation behavior as GraphQL.

Product image reads use the same membership boundary and image writes use the
same `manage products` permission. The image contract accepts root-relative or
HTTP(S) locators, dimensions, gallery position, an optional same-product
variant public ULID, and active-Store-locale alt text with a manual lock. It is
a metadata API, not a binary upload or storage-deletion API.

Product Detail Store Admin contracts:

| Method | URL | Scope and purpose |
| --- | --- | --- |
| `GET` | `/api/v1/store/product-detail` | Bootstrap a new-Product editor with bounded selectors and registered sections. |
| `GET` | `/api/v1/store/product-detail/{product}` | Compose Product core, requested sections, metadata, capabilities, and optional selectors. |
| `POST` | `/api/v1/store/product-detail` | Create Product core and supplied sections in one transaction. |
| `PATCH` | `/api/v1/store/product-detail/{product}` | Save only supplied dirty fields/sections, optionally checking the read revision. |

Both reads accept an optional comma-separated `sections` query. Omission loads
the full aggregate; a manifest such as
`product,images,options,variants` skips every unrequested Catalog query and
registered provider. Existing-Product core and revision remain present.
Unknown or duplicate names return `422`; capabilities continue to list the full
writable contract. `section_limit`, `reference_limit`, and
`with_reference_data` control bounded payload size. Binary media is uploaded
separately and attached by public media ULID. See the
[Product Detail Store Admin guide](product-detail-guide.md) for client flow and
the [API manual](api-manual.md#611-product-detail-composition-and-intelligent-save)
for the complete request contract.
