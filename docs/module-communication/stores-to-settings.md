# Stores to Settings

Stores depends on the Settings language catalog when it lists or updates the
languages enabled for one Store.

- `StoreLanguageService` queries active `Modules\Settings\Models\Language`
  records by public ULID.
- `store_languages` remains Stores-owned and uses internal bigint foreign keys.
- `EnsureStoreLanguageDefaults` runs after `EnsureLanguageCatalog` and
  backfills a Store from `stores.language_code`, falling back to English.
- Stores never creates or edits master catalog rows.
- Settings never loads Store context, memberships, preferences, or selection
  rows.

The global catalog may therefore grow with future Platform settings without
turning Store Management into the owner of SaaS-wide configuration.
