# REST boundary

Implemented REST routes are documented in `docs/openapi.yaml`. All application routes are JSON or streamed responses and are versioned under `/api/v1`, except `/api/health/*` and `/graphql`.

Reserved route families are intentionally not implemented yet: uploads and presigns, export creation/status/download, authorized file downloads, provider webhooks, and `/api/broadcasting/auth`. Uploads must validate type/size and authorize before attaching media; downloads issue temporary private URLs; exports queue large work; webhooks verify the raw body and provider signature before parsing, enforce idempotency, and resolve a trusted installation rather than trusting `X-Store-ID`.

Every response receives `X-Request-ID`. 401, 403, 404, and 422 responses are structured JSON and never redirect to a login page.
