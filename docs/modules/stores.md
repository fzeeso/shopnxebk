# Stores module

## Ownership

`Modules/Stores` owns:

- `stores`, `store_memberships`, and `store_languages`;
- Store identity, contact, branding, classification, locale, lifecycle, billing-link, and capability fields;
- Store language selection/default workflows over the Settings-owned language catalog;
- Store and membership status enums;
- `StoreProvisioner`;
- Store ULID resolution from `X-Store-ID`;
- request-scoped `StoreContext`;
- active-membership and token/store enforcement;
- Store-scoped model, cache, queue, media, and search helpers;
- the `activeStore` GraphQL field.

## Identifier behavior

`stores.id` and `store_memberships.id` are bigint internal keys. Their `public_id` values are ULIDs. `store_memberships.store_id` and `user_id` are bigint foreign keys. Middleware resolves a public Store ULID once; downstream database work uses the internal key.

Only `users.scope = store` accounts may appear in `store_memberships`, receive Store roles, request Store-bound tokens, or enter `StoreContext`. Platform accounts are rejected before Store data is loaded.

## Store data groups

| Group | Fields | Rules |
| --- | --- | --- |
| Identity and contact | `name`, `legal_name`, `description`, `email`, `phone`, `slug`, `primary_domain` | `name` and `slug` are required; optional onboarding details may be null. |
| Branding | `logo`, `favicon`, `cover_image` | Nullable storage references, not binary data. |
| Classification | `industry`, `business_type` | Business type is nullable or one of `ecommerce`, `b2b`, `services`, `digital`, `restaurant`, `marketplace`. |
| Billing links | `plan_id`, `subscription_id` | Nullable indexed internal bigints; not public API fields and not constrained until Billing exists. |
| Locale | `currency_code`, `language_code`, `timezone`, `country_code` | Defaults are `USD`, `en`, and `UTC`; country remains nullable. |
| Lifecycle | `status`, `launched_at`, `trial_ends_at` | Status is `pending`, `active`, `suspended`, or `cancelled`; timestamps are timezone-aware. |
| Capabilities | `is_verified`, `is_ai_enabled`, `is_pos_enabled`, `is_b2b_enabled`, `is_marketplace_enabled` | Boolean switches default to `false`. Authorization is still permission/policy based. |
| Extensibility | `settings`, `metadata` | JSON objects for non-core configuration; do not move stable first-class fields back into JSON. |

`BusinessType` and `StoreStatus` provide typed Eloquent casts. Store resources and GraphQL expose public-safe profile, locale, lifecycle, and capability values. Internal `id`, `plan_id`, and `subscription_id` are deliberately omitted.

## Store management flow

1. `POST /api/v1/stores` calls `CreateStoreService`, provisions an active Owner membership/role, and applies validated initial profile/settings.
2. Existing Store routes resolve `X-Store-ID` and require an active membership.
3. `GET /api/v1/store` and `GET /api/v1/store/settings` allow active members to view their own Store.
4. `PATCH /api/v1/store/profile` calls `UpdateStoreProfileService`; `PATCH /api/v1/store/settings` calls `StoreSettingsService`.
5. Both write paths require `manage store`, use a transaction, and return public-safe resources.
6. Merchant validation prohibits Platform-owned lifecycle, Billing, verification, capability, and raw JSON fields.

## Store selection flow

1. `ResolveStore` requires `X-Store-ID` for store-required routes; GraphQL uses `ResolveOptionalStore`.
2. `HeaderStoreFinder` rejects malformed ULIDs with 400 and missing records with 404.
3. `Store::makeCurrent()` and `RequestStoreContext::set()` establish current context.
4. `EnsureStoreMembership` requires an active membership and validates a bound token’s internal `store_id`.
5. The internal Store ID becomes Spatie Permission’s team key.
6. The action/policy runs.
7. `ClearRequestContext` removes all current state in a `finally` block.

## Provisioning flow

`ProvisionStore` requires a Store-scoped user, creates the Store and active owner membership, delegates validated `Owner` assignment to `ScopedRoleAssignmentService`, and dispatches `StoreCreated` after commit. The provisioning caller owns the surrounding transaction.

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

## Outbound communication

Store profile integrations are defined separately in [Stores to Billing](../module-communication/stores-to-billing.md), [Stores to Settings](../module-communication/stores-to-settings.md), and [Stores to Files](../module-communication/stores-to-files.md). See the dedicated [Store management contract](../store-management.md).

See [Stores → Authentication](../module-communication/stores-to-authentication.md). Business modules should depend on `StoreContext`, Store ULIDs in public contracts, and bigint `store_id` in their own persistence.
