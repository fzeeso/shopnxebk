# Themes module

`Modules/Themes` owns the global marketplace catalog, immutable releases,
review submissions, Store licenses, and mutable installed Store copies. It is
the only module that writes the eight Theme tables.

## Owned persistence

- `theme_publishers`
- `theme_categories`
- `theme_category_assignments`
- `themes`
- `theme_versions`
- `theme_submissions`
- `theme_licenses`
- `store_themes`

The catalog uses `ThemePublisher`, `ThemeCategory`, and `Theme`.
`ThemeVersion` and `ThemeSubmission` own immutable release/review history.
`ThemeLicense` owns Store usage rights. `StoreTheme` owns merchant-specific
settings/layout/CSS and never becomes the global marketplace product.

## Services and contracts

| Class | Responsibility |
| --- | --- |
| `EnsureThemeCatalog` | Idempotently maintain the bundled Platform publisher, default Theme, and initial published version. |
| `ThemeInstaller` | Cross-module Store-provisioning contract. |
| `DefaultThemeInstaller` | Resolve the selected current version, issue the correct license, and create the initial published Store installation. |
| `ThemeAccessService` | Enforce Platform `manage marketplace` or selected-Store `manage themes` access. |
| `ThemeCatalogAdminService` | Paginated publisher/category/listing reads and listing/taxonomy writes. |
| `ThemeReleaseAdminService` | Immutable versions, numbered submissions, review decisions, publication, and license lifecycle. |
| `StoreThemeService` | Store marketplace, installation, optimistic customization, duplication, publication, and draft deletion. |

Controllers stay thin: validate with Form Requests, call the service, and
serialize resources that expose public ULIDs. PostgreSQL partial indexes/check
constraints remain the final invariant layer.

## Authorization

Platform routes require `user.scope:platform` plus `manage marketplace`.
Store routes require `user.scope:store`, resolved Store context, active
membership, and `manage themes`. Owner and Manager receive `manage themes`;
Sales and Inventory do not. The services re-check authorization so direct
service use cannot bypass route middleware.

## Integration boundaries

- Stores calls `ThemeInstaller` during atomic provisioning; Themes does not
  create Store identity/settings/domain/membership records.
- Themes consumes authenticated public User identities and scoped permissions;
  it does not manage credentials or roles.
- Theme paid-price currency codes reference the Settings-owned currency catalog.
- Listing media may be global Platform media; archives/artifacts are opaque
  private-storage object keys awaiting a dedicated validated artifact worker.
- Billing/payment/order services may later write the license's opaque
  `billing_order_item_id`; Themes does not infer a sale from a license.

See the complete [Theme marketplace and Store themes](../themes.md) contract.
