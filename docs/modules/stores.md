# Stores module

## Ownership

`Modules/Stores` owns:

- `stores`, `store_users`, `store_languages`, `store_domains`,
  `store_settings`, and `store_locale_settings`;
- Store identity, contact, branding, classification, locale, lifecycle, billing-link, and capability fields;
- Store language selection/default workflows over the Settings-owned language catalog;
- Store and membership status enums;
- `StoreProvisioner` and its call to the Themes-owned `ThemeInstaller`;
- Store ULID resolution from `X-Store-ID`;
- request-scoped `StoreContext`;
- active-membership and token/store enforcement;
- Store-scoped model, cache, queue, media, and search helpers;
- automatic disabled Store-policy catalogs, localized policy content whose
  `lock_it` flag protects merchant-authored translations from automated
  overwrite, immutable policy versions, and the Store-policy adapter for the
  shared after-commit translation queue;
- the `activeStore` GraphQL field.
- Platform Store catalog/merchant provisioning and selected-Store user management APIs.

Platform Store, merchant, and selected-Store user table lists are paginated.
They accept `page`/`per_page`, default to 25, cap at 100, and return `data`,
`links`, and `meta`; Store role and language-option catalogs stay complete.

## Identifier behavior

`stores.id`, `store_users.id`, and `store_domains.id` are bigint internal
keys. Their `public_id` values are ULIDs. Related `store_id`, user, and media
keys are bigint foreign keys. `store_settings` is a one-to-one dependent
record, so its bigint `store_id` is also its primary key and it has no
independently routable public ID. `store_locale_settings` follows the same
one-to-one key rule. Themes owns the bigint/public-ULID
`store_themes` contract. Middleware resolves a public Store ULID once;
downstream database work uses the internal key.

Only `users.scope = store` accounts may appear in `store_users`, receive Store roles, request Store-bound tokens, or enter `StoreContext`. A `store_users` row grants access to the Store boundary; the Store-scoped roles and permissions assigned for the same `store_id` decide which operations the user may perform. Platform accounts are rejected before Store data is loaded.

## Store data groups

| Group | Fields | Rules |
| --- | --- | --- |
| Identity and contact | `name`, `legal_name`, `description`, `email`, `phone`, `slug`, `primary_domain` | `name` and `slug` are required; optional onboarding details may be null. |
| Branding | `logo`, `favicon`, `cover_image` | Nullable storage references, not binary data. |
| Classification | `industry`, `business_type` | Business type is nullable or one of `ecommerce`, `b2b`, `services`, `digital`, `restaurant`, `marketplace`. |
| Billing links | `plan_id`, `subscription_id` | Nullable indexed internal bigints; not public API fields and not constrained until Billing exists. |
| Locale | `currency_code`, `language_code`, `timezone`, `country_code` | Defaults are `USD`, `en`, and `UTC`; country remains nullable. |
| Lifecycle | `status`, `launched_at`, `trial_ends_at` | Status is `draft`, `trial`, `active`, `suspended`, `frozen`, or `closed`; timestamps are timezone-aware. |
| Capabilities | `is_verified`, `is_ai_enabled`, `is_pos_enabled`, `is_b2b_enabled`, `is_marketplace_enabled` | Boolean switches default to `false`. Authorization is still permission/policy based. |
| Extensibility | `settings`, `metadata` | JSON objects for non-core configuration; do not move stable first-class fields back into JSON. |

`BusinessType` and `StoreStatus` provide typed Eloquent casts. Store resources and GraphQL expose public-safe profile, locale, lifecycle, and capability values. Internal `id`, `plan_id`, and `subscription_id` are deliberately omitted.

## Domains, settings, and Theme integration

| Table | Purpose and invariants |
| --- | --- |
| `store_domains` | Stores a globally unique domain, extensible `domain_type`, verification/SSL states, and verification time. A PostgreSQL partial unique index permits at most one `is_primary = true` row per Store. |
| `store_settings` | Stores one contact, postal address (`store_country_code`, state, city, ZIP, and two address lines), storefront/password/order/branding/settings record per Store, plus the opt-in `auto_store_translation_flag` and `is_searchable_on_platform_flag` switches. Both flags default to `false`. `logo_media_id` and `favicon_media_id` are nullable bigint foreign keys to `media.id` and become null if that media row is deleted. `password_hash` is automatically hashed on model assignment and hidden from serialization. |
| `store_locale_settings` | Stores one date/time/week, number-format, weight, and dimension preference row per Store. Currency, language, country, and IANA timezone stay on `stores`; UTF-8 and automatic timezone daylight-saving behavior are managed standards rather than columns. |

Deleting a Store cascades its domains/settings and the Themes-owned licenses and
installed copies. Domain/SSL states and JSON settings remain extensible.
Provisioning writes Store records then calls `ThemeInstaller`; post-creation
Theme marketplace/install/customize/publish routes are implemented by Themes.

## Store management flow

1. Registration, `POST /api/v1/stores`, and Platform merchant creation call `StoreProvisioner` inside their owning transaction.
2. `ProvisionStore` creates a `draft` Store, its one-to-one settings, the
   default Store-language selection when the Settings catalog exists, the
   configured platform domain, then calls `ThemeInstaller` to issue the
   selected Theme license and create one published installation before adding
   the active Owner membership/role and one disabled policy per master type.
3. A submitted custom domain is recorded as the pending primary domain while the verified platform domain remains available as a non-primary domain.
4. Creation returns `dashboard_url=<STORE_ADMIN_DASHBOARD_URL>?store=<store-public-ulid>`; the frontend accepts only an ID present in the authenticated Store-access profile.
5. Existing Store routes resolve `X-Store-ID` and require an active membership.
6. `GET /api/v1/store` and `GET /api/v1/store/settings` allow active members to view their own Store.
7. `PATCH /api/v1/store/profile` calls `UpdateStoreProfileService`; the transactional `PATCH /api/v1/store/settings` controller flow persists contact/address and opt-in flag values in `store_settings`.
8. Both write paths require `manage store`, use a transaction, and return public-safe resources.
9. Merchant validation prohibits Platform-owned lifecycle, Billing, verification, capability, and raw JSON fields.
10. `/api/v1/platform/stores*` separately gives Platform staff with `manage
   stores` a searchable, filtered, paginated Store catalog plus direct
   create/view/edit operations without Store context.
11. `GET/PATCH /api/v1/platform/stores/{store}/locale-settings` composes Store
    locale columns and `store_locale_settings` through one Platform service.
12. `GET/POST /api/v1/platform/stores/{store}/domains` and `PATCH
    /api/v1/platform/stores/{store}/domains/{domain}` map Platform domain forms
    to `store_domains`, while `StoreDomainManager` keeps primary selection and
    `stores.primary_domain` synchronized.

## Store selection flow

1. `ResolveStore` requires `X-Store-ID` for store-required routes; GraphQL uses `ResolveOptionalStore`.
2. `HeaderStoreFinder` rejects malformed ULIDs with 400 and missing records with 404.
3. `Store::makeCurrent()` and `RequestStoreContext::set()` establish current context.
4. `EnsureStoreMembership` requires an active membership and validates a bound token’s internal `store_id`.
5. The internal Store ID becomes Spatie Permission’s team key.
6. The action/policy runs.
7. `ClearRequestContext` removes all current state in a `finally` block.

## Provisioning flow

`ProvisionStore` requires a Store-scoped user and enforces its own database
transaction, which safely nests inside registration, additional-Store,
Platform merchant, and local-fixture transactions. It validates the configured
root domain and Theme key, creates every required Store record, calls the
Themes-owned `ThemeInstaller`, delegates validated `Owner` assignment to
`ScopedRoleAssignmentService`, idempotently provisions the complete disabled
Store-policy catalog, and dispatches `StoreCreated` only after the outermost
transaction commits. Direct Platform Store creation uses the same policy
catalog action even though it intentionally creates no membership.

`PlatformMerchantService` is the Platform-admin composition root for a merchant: it requires `manage stores`, creates the Authentication-owned Store identity, calls `StoreProvisioner`, applies merchant profile fields, synchronizes Store roles, and queues registration/verification behavior after the transaction commits. `StoreUserAdminService` separately creates Store staff under an already selected Store and requires both member and role management permissions. Neither flow can assign Platform roles.

`PlatformStoreAdminService` is the direct Store-row administration boundary.
It uses the same Platform permission but creates no identity or membership. Its
query contract searches stable identity fields, applies validated exact/date/
boolean filters, restricts sorting to known columns, caps pages at 100, and
uses the Store public ULID for detail/update. It rejects Billing relationship
IDs and raw JSON.

## Language flow

1. Settings idempotently seeds the supported master catalog.
2. `EnsureStoreLanguageDefaults` backfills an existing Store default from
   `stores.language_code` after the catalog exists.
3. An active Store member may list active catalog entries through a request
   carrying `X-Store-ID`.
4. A Store user with `manage store` submits public language ULIDs and one
   default ULID.
5. `StoreLanguageService` resolves Settings-owned public IDs, updates the Store-local join
   rows in one transaction, preserves the one-default database constraint, and
   synchronizes `stores.language_code`.

Default-language policy edits persist immediately and call the shared
`TranslationCoordinator` inside the same transaction. The coordinator records
the durable request and dispatches only after commit. The Store-policy handler
resolves Settings-owned active languages, excludes `lock_it = true` rows,
applies generated text in a short worker transaction, and appends a version for
each generated content change. Provider latency and failures never hold or
undo the merchant's source edit.

## Outbound communication

Store profile integrations are defined separately in [Stores to Billing](../module-communication/stores-to-billing.md), [Stores to Settings](../module-communication/stores-to-settings.md), [Stores to Files](../module-communication/stores-to-files.md), and [Stores to Themes](../module-communication/stores-to-themes.md). See the dedicated [Store management contract](../store-management.md).

See [Stores → Authentication](../module-communication/stores-to-authentication.md). Business modules should depend on `StoreContext`, Store ULIDs in public contracts, and bigint `store_id` in their own persistence.
