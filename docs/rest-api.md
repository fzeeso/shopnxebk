# REST boundary

Implemented REST routes are documented in `docs/openapi.yaml`. All application routes are JSON or streamed responses and are versioned under `/api/v1`, except `/api/health/*` and `/graphql`.

Reserved route families are intentionally not implemented yet: uploads and presigns, export creation/status/download, authorized file downloads, provider webhooks, and `/api/broadcasting/auth`. Uploads must validate type/size and authorize before attaching media; downloads issue temporary private URLs; exports queue large work; webhooks verify the raw body and provider signature before parsing, enforce idempotency, and resolve a trusted installation rather than trusting `X-Store-ID`.

Every response receives `X-Request-ID`. 401, 403, 404, and 422 responses are structured JSON and never redirect to a login page.

Store management REST contracts:

| Method | Route | Scope and purpose |
| --- | --- | --- |
| `POST` | `/api/v1/stores` | Create another Store for the authenticated Store account and assign Owner. |
| `GET` | `/api/v1/store` | View the active member's selected `X-Store-ID`. |
| `PATCH` | `/api/v1/store/profile` | Update merchant-owned profile fields; requires `manage store`. |
| `GET` | `/api/v1/store/settings` | View selected Store locale, preferences, and read-only capabilities. |
| `PATCH` | `/api/v1/store/settings` | Merge validated locale/preferences; currency and language must be active Platform catalog entries; requires `manage store`. |

Platform lifecycle, Billing links, verification, capabilities, trial dates, and raw JSON are prohibited in Store profile/settings requests. See [Store management](store-management.md).

Bearer-authenticated Store routes require a Store-bound token with the
`store:access` ability. Account-only and unbound tokens are rejected even when
their user has an active membership. New tokens expire after 30 days by
default, and password reset revokes all bearer tokens for the account.

Plans & Pricing REST contracts:

| Method | Route | Purpose |
| --- | --- | --- |
| `GET/POST` | `/api/v1/platform/plans` | List or add plans. |
| `GET/PATCH/DELETE` | `/api/v1/platform/plans/{plan}` | View, edit, or safely remove a plan by public ULID. |
| `GET/POST` | `/api/v1/platform/features` | List or add reusable feature definitions. |
| `PATCH/DELETE` | `/api/v1/platform/features/{feature}` | Edit or remove an unassigned feature. |
| `PUT/DELETE` | `/api/v1/platform/plans/{plan}/features/{feature}` | Add/update or detach a plan feature/add-on. |

All plan routes require Platform scope and `manage plans`. Fixed and add-on prices are integer minor units. Assigned plans must be archived instead of deleted. See [Plans & Pricing](plans-and-pricing.md).

Platform Settings REST contracts:

| Method | Route | Scope and purpose |
| --- | --- | --- |
| `GET` | `/api/v1/platform/settings/currencies` | List the complete currency catalog for a Platform-scoped user. |
| `POST` | `/api/v1/platform/settings/currencies` | Add a non-USD currency; requires `manage platform settings`. |
| `PATCH` | `/api/v1/platform/settings/currencies/{currency}` | Update display options, active state, or USD-relative rate by public ULID; requires `manage platform settings`. |
| `GET` | `/api/v1/platform/settings/languages` | List the complete language catalog for a Platform-scoped user. |
| `POST` | `/api/v1/platform/settings/languages` | Add a supported language; requires `manage platform settings`. |
| `PATCH` | `/api/v1/platform/settings/languages/{language}` | Update names, direction, or active state by public ULID; requires `manage platform settings`. |

Admin component request flow:

1. Load `GET /api/v1/auth/interfaces`.
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
