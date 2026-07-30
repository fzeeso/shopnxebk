# Settings module

## Ownership

`Modules/Settings` owns:

- the global `languages` and `currencies` master tables;
- language and currency models, value enums, resources, and seed actions;
- canonical `/api/v1/platform/settings/*` routes and legacy platform aliases;
- Platform read access and `manage platform settings` mutation enforcement;
- the permission-filtered `Settings` navigation hint at `/admin/settings`;
- language creation and editing, including active-state management;
- currency creation, formatting, active-state, and USD-relative rate management.

The module is designed to receive future global SaaS settings. It does not own
merchant preferences, Store context, Store memberships, or Store-specific
language selections.

## Access flow

1. `user.scope:platform` rejects Store identities before settings data is read.
2. `PlatformSettingsAccessService::ensureCanView()` permits Platform roles to
   read catalog data.
3. Form Requests and `ensureCanManage()` independently require
   `manage platform settings` for mutations.
4. Controllers resolve public ULIDs; internal bigint IDs do not cross REST.

## Catalog initialization

`EnsureLanguageCatalog` idempotently maintains the initial supported-language
catalog. `EnsureCurrencyCatalog` maintains the common currency catalog while
preserving administrator-entered non-USD rates. Store default backfilling is a
separate Stores action and runs after the language catalog exists.

Language catalog rows do not act as admin UI dictionaries. Component labels
are synchronized through the separate frontend using the
[admin localization contract](../components/localization.md).

## Dependencies

Settings reads the authenticated `User` and Platform permissions from
Authentication. It does not import Store models. Stores consumes the public
language catalog through a one-way dependency documented in
[Stores to Settings](../module-communication/stores-to-settings.md).

See [Platform settings](../settings.md),
[Platform Settings admin component](../components/platform-settings-admin.md),
and [Settings to Authentication](../module-communication/settings-to-authentication.md).
