# REST boundary

Implemented REST routes are documented in `docs/openapi.yaml`. All application routes are JSON or streamed responses and are versioned under `/api/v1`, except `/api/health/*` and `/graphql`.

Reserved route families are intentionally not implemented yet: uploads and presigns, export creation/status/download, authorized file downloads, provider webhooks, and `/api/broadcasting/auth`. Uploads must validate type/size and authorize before attaching media; downloads issue temporary private URLs; exports queue large work; webhooks verify the raw body and provider signature before parsing, enforce idempotency, and resolve a trusted installation rather than trusting `X-Store-ID`.

Every response receives `X-Request-ID`. 401, 403, 404, and 422 responses are structured JSON and never redirect to a login page.

Language REST contracts:

| Method | Route | Scope and purpose |
| --- | --- | --- |
| `GET` | `/api/v1/platform/languages` | List the complete master catalog for a Platform-scoped user. |
| `POST` | `/api/v1/platform/languages` | Add a supported language; requires `manage platform settings`. |
| `GET` | `/api/v1/store/languages` | List active languages plus the selected/default state for the authorized `X-Store-ID`. |
| `PUT` | `/api/v1/store/languages` | Replace the Store language set and default; requires `manage store`. |

Language and Store identifiers in request/response bodies are public ULIDs.
`locale` accepts a language plus optional region and normalizes hyphens to an
underscore. Store updates require at least one language and a default included
in the selected set.
