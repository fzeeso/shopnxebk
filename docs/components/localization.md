# Admin component localization contract

The supported-language catalog and the admin interface dictionaries are related
but are not the same thing.

- `Modules/Settings/app/Actions/EnsureLanguageCatalog.php` is the backend source
  for language names, native names, locale codes, `lang_icon`/`lang_image` asset
  references, direction, and availability.
- The separate frontend owns translated component labels and messages.
- This API repository currently has no runtime `lang/`, locale JSON, or
  frontend dictionary files. Do not add unused backend dictionaries merely to
  mirror the catalog.

## Merchant content translation locks

Every backend `*_translations` row exposes or persists a non-null `lock_it`
boolean with default `false`. Translation editors for implemented APIs should
offer an explicit “protect from automatic translation” control and preserve the
current value when the field is not changed. Background imports, AI translation,
and machine-generated refreshes must use `AutomatedTranslationWriter`; locked
merchant content is skipped until a user clears the flag.

## Platform Settings keys

Every frontend locale that supports the Platform admin interface should define
equivalents of these stable keys:

```text
admin.navigation.settings
admin.settings.title
admin.settings.languages.title
admin.settings.languages.add
admin.settings.languages.edit
admin.settings.languages.icon
admin.settings.currencies.title
admin.settings.currencies.add
admin.settings.currencies.edit
admin.settings.actions.save
admin.settings.actions.cancel
admin.settings.status.active
admin.settings.status.inactive
admin.settings.errors.load
admin.settings.errors.forbidden
```

Field labels should follow the API names documented in the
[Platform Settings component guide](platform-settings-admin.md): language
name/native name/locale/language icon/language image/direction/active state and currency
name/code/symbol/symbol position/decimal places/USD rate/base/active state.

## Platform Stores keys

Every frontend locale that exposes Store administration should define
equivalents of these stable keys:

```text
admin.navigation.merchants
admin.stores.title
admin.stores.add
admin.stores.edit
admin.stores.search
admin.stores.filters.status
admin.stores.filters.business_type
admin.stores.filters.currency
admin.stores.filters.language
admin.stores.filters.country
admin.stores.filters.verified
admin.stores.pagination.rows_per_page
admin.stores.empty
admin.stores.no_results
admin.stores.errors.load
admin.stores.errors.forbidden
admin.stores.creation.unassigned_notice
admin.merchants.creation.with_owner
```

The separate frontend owns these dictionaries; this API repository still has
no runtime language files to update. Treat backend English labels as fallback
text and keep every frontend dictionary synchronized with the
[Platform Stores component guide](platform-stores-admin.md).

## Direction

The catalog direction is authoritative for Store-language presentation.
Frontend locale registration must independently mark RTL admin dictionaries.
Arabic (`ar`), Persian (`fa`), and Urdu (`ur`) are currently RTL. Do not infer
direction from translated text or the browser.

## Synchronization checklist

When adding or changing a supported language:

1. Update `EnsureLanguageCatalog` with the administrative name, native name,
   normalized locale, bundled `lang_icon`/`lang_image` assets, and direction.
2. Update Settings PostgreSQL coverage for the catalog row, count, icon file,
   direction, and idempotency.
3. Update the frontend locale registry and every relevant component dictionary.
4. Update RTL layout configuration when direction is `rtl`.
5. Verify fallback behavior for admin UI locales that are not yet translated.
6. Update context, developer, API, component, module, and development-log
   documentation when behavior or public fields change.
7. Run the catalog seeder, PostgreSQL tests, frontend dictionary checks, and
   missing-key detection.

When only a component label changes, keep the catalog unchanged and update the
key in every frontend dictionary. A language being available to Stores does
not automatically mean the Platform admin UI has been translated into it.
