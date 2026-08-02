# REST boundary

Implemented REST routes are documented in `docs/openapi.yaml`. All application routes are JSON or streamed responses and are versioned under `/api/v1`, except `/api/health/*` and `/graphql`.

Reserved route families are intentionally not implemented yet: uploads and presigns, export creation/status/download, authorized file downloads, provider webhooks, and `/api/broadcasting/auth`. Uploads must validate type/size and authorize before attaching media; downloads issue temporary private URLs; exports queue large work; webhooks verify the raw body and provider signature before parsing, enforce idempotency, and resolve a trusted installation rather than trusting `X-Store-ID`.

Every response receives `X-Request-ID`. 401, 403, 404, and 422 responses are structured JSON and never redirect to a login page.

Table-style list endpoints accept `page` and `per_page`. The usual default page
size is 25; the Platform Store catalog defaults to 10 for its admin directory.
The maximum is 100. Responses keep records in `data` and add Laravel
`links` and `meta` pagination objects. This applies to personal access tokens,
Platform users, Stores, merchants, plans, features, currencies, languages, and
selected-Store users. Selector/option endpoints such as roles, active Store
memberships, and Store language choices remain complete unpaginated lists.

User and merchant administration contracts:

| Method | Route | Scope and purpose |
| --- | --- | --- |
| `GET/POST` | `/api/v1/platform/users` | Page Platform staff or create one; requires `manage platform users`. |
| `GET/PATCH` | `/api/v1/platform/users/{user}` | View or edit a Platform user by public ULID. |
| `GET` | `/api/v1/platform/roles` | List assignable Platform roles. |
| `GET/POST` | `/api/v1/platform/stores` | Search/filter/page or directly create Store rows; requires `manage stores`. |
| `GET/PATCH` | `/api/v1/platform/stores/{store}` | View or edit a Store by public ULID; requires `manage stores`. |
| `GET/POST` | `/api/v1/platform/merchants` | Page merchants or atomically create a draft Store, Store owner, settings, platform domain, selected active theme, membership, and Store roles; requires `manage stores`. |
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
status is supplied. It never creates an owner, membership, Store role,
subscription, or plan assignment. Use `/api/v1/platform/merchants` for the
complete owner-aware workflow. Platform Store writes accept validated public
profile, locale, lifecycle, and capability fields; internal Billing links and
raw JSON are prohibited. See the
[Platform Stores admin component guide](components/platform-stores-admin.md).

Accepted Store lifecycle values are `draft`, `trial`, `active`, `suspended`,
`frozen`, and `closed`. `pending` and `cancelled` are retired legacy values.

Store management REST contracts:

| Method | Route | Scope and purpose |
| --- | --- | --- |
| `POST` | `/api/v1/stores` | Create another Store for the authenticated Store account and assign Owner. |
| `GET` | `/api/v1/store` | View the active member's selected `X-Store-ID`. |
| `PATCH` | `/api/v1/store/profile` | Update merchant-owned profile fields; requires `manage store`. |
| `GET` | `/api/v1/store/settings` | View selected Store locale, normalized contact/address values, preferences, and read-only capabilities. |
| `PATCH` | `/api/v1/store/settings` | Update normalized contact/address values and merge validated locale/preferences; currency and language must be active Platform catalog entries; requires `manage store`. |

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

Platform Settings REST contracts:

| Method | Route | Scope and purpose |
| --- | --- | --- |
| `GET` | `/api/v1/platform/settings/currencies` | Page the currency catalog for a Platform-scoped user. |
| `POST` | `/api/v1/platform/settings/currencies` | Add a non-USD currency; requires `manage platform settings`. |
| `PATCH` | `/api/v1/platform/settings/currencies/{currency}` | Update display options, active state, or USD-relative rate by public ULID; requires `manage platform settings`. |
| `GET` | `/api/v1/platform/settings/languages` | Page the language catalog for a Platform-scoped user. |
| `POST` | `/api/v1/platform/settings/languages` | Add a supported language; requires `manage platform settings`. |
| `PATCH` | `/api/v1/platform/settings/languages/{language}` | Update names, direction, or active state by public ULID; requires `manage platform settings`. |

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
in the selected set.
