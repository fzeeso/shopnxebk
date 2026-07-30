# Platform settings

Platform settings are global SaaS configuration controlled by Platform
administrators. They are not Store settings and never require `X-Store-ID`.
`Modules/Settings` is the extension point for future global settings.

Store settings remain merchant-owned preferences and locale selections exposed
under `/api/v1/store/settings` and `/api/v1/store/languages`.

## Access

All Settings routes require an authenticated Platform-scoped account. Any
Platform role may read the catalogs. Mutations additionally require
`manage platform settings`, initially assigned only to `Super Admin`.

The canonical route prefix is `/api/v1/platform/settings`. The former
`/api/v1/platform/currencies` and `/api/v1/platform/languages` routes remain
backward-compatible aliases.

`GET /api/v1/auth/interfaces` returns the `Settings` navigation item at
`/admin/settings` only when the Platform account has
`manage platform settings`. The API remains authoritative if a client
constructs the path manually.

The frontend renders Languages and Currencies as sections of one extensible
Settings shell. See the
[Platform Settings admin component guide](components/platform-settings-admin.md)
for composition, form behavior, error states, and acceptance criteria.

## Currency catalog

`currencies` is the master catalog for currency codes, display formatting, and
manual USD-relative exchange rates. USD is the only base currency and must
remain active at rate `1.00000000`. Non-USD rates may be null; the application
does not invent or fetch market rates.

| Method | Route | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/platform/settings/currencies` | List all currencies. |
| `POST` | `/api/v1/platform/settings/currencies` | Add a non-USD currency. |
| `PATCH` | `/api/v1/platform/settings/currencies/{currency}` | Edit display, active state, or USD rate by public ULID. |

## Language catalog

`languages` is the master catalog of supported languages. Each row has a public
ULID, immutable locale code, administrative and native names, text direction,
and active state. Deactivating a language removes it from new Store selection
responses without rewriting existing Store history.

| Method | Route | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/platform/settings/languages` | List all languages. |
| `POST` | `/api/v1/platform/settings/languages` | Add a supported language. |
| `PATCH` | `/api/v1/platform/settings/languages/{language}` | Edit names, direction, or active state by public ULID. |

Locale codes are immutable after creation because `stores.language_code` and
Store language selections may already refer to them.

## Language-resource synchronization

The backend catalog source is
`Modules/Settings/app/Actions/EnsureLanguageCatalog.php`, with PostgreSQL
coverage in `Modules/Settings/tests/Feature/PlatformCatalogApiFeatureTest.php`.
Admin UI dictionaries live in the separate frontend and must be updated when a
Settings label or supported admin locale changes. This API repository currently
has no runtime translation dictionary directory.

Catalog availability and admin UI translation coverage are separate: a Store
may use a language even when the Platform admin interface falls back to its
default UI locale. See the
[admin localization contract](components/localization.md).

## Module boundary

`Settings` owns the master `languages` and `currencies` tables, catalog models,
seed actions, platform access checks, requests, resources, controllers, and
routes. `Stores` owns `store_languages`, Store preferences, and the services
that select catalog languages for one authorized Store.

See [Settings module](modules/settings.md),
[Platform Settings admin component](components/platform-settings-admin.md),
[Stores to Settings](module-communication/stores-to-settings.md), and
[REST boundary](rest-api.md).
