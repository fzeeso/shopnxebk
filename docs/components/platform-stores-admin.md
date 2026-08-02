# Platform Stores admin component

This contract covers the Store catalog inside the Platform admin interface.
The backend is implemented here; the visual component remains in the separate
frontend. The existing `merchants` navigation entry at `/admin/merchants`
requires `manage stores` and may present Store catalog and merchant-owner
workflows as separate tabs.

## API boundary

Platform Store requests never send `X-Store-ID`. Every route requires an
authenticated Platform account with `manage stores`.

| Method | Route | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/platform/stores` | Search, filter, sort, and page Store rows. |
| `POST` | `/api/v1/platform/stores` | Create an unassigned Store row. |
| `GET` | `/api/v1/platform/stores/{store}` | Load one Store by public ULID. |
| `PATCH` | `/api/v1/platform/stores/{store}` | Edit validated Platform-controlled Store fields. |

The existing `/api/v1/platform/merchants*` workflow remains the correct choice
when the administrator must create or edit an owner identity, membership, and
Store roles together. Direct Store creation intentionally creates no user,
membership, role assignment, subscription, or plan assignment.

## List controls

The listing accepts `search` across Store name, legal name, slug, contact
email, primary domain, and every related member's name/email through
`store_users.user_id`. Exact filters are `status`, `business_type`, `currency_code`,
`language_code`, `country_code`, `is_verified`, `is_ai_enabled`,
`is_pos_enabled`, `is_b2b_enabled`, and `is_marketplace_enabled`.
`created_from`/`created_to` use `YYYY-MM-DD`.

Sort fields are `name`, `slug`, `status`, `created_at`, and `updated_at`, with
`direction=asc|desc`. `page` starts at 1; `per_page` defaults to 10 and is
capped at 100. Render pagination from response `meta` and `links`, rather than
inferring totals from the current page.

The admin UI offers only 10, 20, 50, and 100 as page-size choices. Search and
page-size changes reset the page to 1.

## Execution path

| Layer | Repository path | Responsibility |
| --- | --- | --- |
| Route | `Modules/Stores/routes/api.php` | Registers `GET /api/v1/platform/stores` under `api`, `auth:sanctum`, and `user.scope:platform`. |
| Authorization/validation | `Modules/Stores/app/Http/Requests/ListPlatformStoresRequest.php` | Requires `manage stores`; normalizes and validates query inputs. |
| Controller | `Modules/Stores/app/Http/Controllers/Api/V1/PlatformStoreController.php` | Passes validated filters and the authenticated actor to the service. |
| Query service | `Modules/Stores/app/Services/PlatformStoreAdminService.php` | Applies Store/member search, filters, deterministic sorting, eager loading, and length-aware pagination. |
| Store relationship | `Modules/Stores/app/Models/Store.php` | `primaryMembership()` selects the earliest `store_users` row; `memberships()` supports member search. |
| Membership relationship | `Modules/Stores/app/Models/StoreMembership.php` | Resolves `user_id` to `Modules/Authentication/Models/User`. |
| Collection resource | `Modules/Stores/app/Http/Resources/PlatformStoreListResource.php` | Adds a public `owner` projection to the normal Store resource without exposing bigint keys. |

The request runs in the Laravel API process at `http://127.0.0.1:8000` during
local development. Start it from `C:\xampp\htdocs\shopnxebk` with
`php artisan serve --host=127.0.0.1 --port=8000`. The Next.js browser client
uses its `/laravel` same-origin proxy.

## List input

| Query input | Type/default | Meaning |
| --- | --- | --- |
| `search` | string, optional, max 120 | Case-insensitive Store identity/domain and related member name/email search. `%` and `_` are treated literally. |
| `page` | integer, default 1 | Requested one-based page. |
| `per_page` | integer, default 10, max 100 | Page size; the admin offers 10/20/50/100. |
| `sort` | `name`, `slug`, `status`, `created_at`, or `updated_at`; default `created_at` | Whitelisted sort column. |
| `direction` | `asc` or `desc`; default `desc` | Sort direction. |
| profile/capability filters | optional | Existing exact filters and date-range inputs listed above. |

Example:

```http
GET /api/v1/platform/stores?search=ahmed&page=1&per_page=10&sort=created_at&direction=desc
Accept: application/json
Cookie: <Sanctum session>
```

## List output

The response is a Laravel resource collection. `data` contains public Store
fields plus:

```json
{
  "owner": {
    "id": "01J...USER_ULID",
    "name": "Ahmed Khan",
    "email": "ahmed@example.com"
  }
}
```

`owner` is `null` when no membership exists. It is derived from the earliest
membership row because `store_users` currently has no dedicated owner
flag. Internal Store, membership, and user bigint IDs are never serialized.
`links` contains `first`, `last`, `prev`, and `next`; `meta` contains
`current_page`, `from`, `last_page`, `path`, `per_page`, `to`, and `total`.

Errors are JSON: `401` unauthenticated, `403` missing Platform scope or
`manage stores`, and `422` invalid query input. Every response has
`X-Request-ID`.

## Add and edit fields

The form groups fields as follows:

- identity/contact: name, slug, legal name, description, email, phone, and
  primary domain;
- branding/classification: logo, favicon, cover image, industry, and business
  type;
- locale: active currency code, active language locale, timezone, and country;
- lifecycle/capabilities: status, verification, AI, POS, B2B, marketplace,
  launch time, and trial end time.

Load currency and language choices from the Platform Settings read APIs. Codes
are normalized by the backend. New direct Stores default to `draft`, and
legal name defaults to the submitted display name when omitted.

The Store status selector and filters use exactly `draft`, `trial`, `active`,
`suspended`, `frozen`, and `closed`. Do not send the retired `pending` or
`cancelled` values.

Never send bigint IDs, `plan_id`, `subscription_id`, raw `settings`, raw
`metadata`, Store preferences, owner data, or roles. Billing assignment and
merchant-owner provisioning are separate workflows.

## Component states

- Keep list loading, empty, no-search-results, error, and ready states distinct.
- Keep filters in the URL so paging and refresh are reproducible.
- Reset to page 1 when search, filters, sorting, or page size changes.
- On `422`, bind field errors and retain the form; on `404`, close stale detail
  state after notifying the user.
- On `401`, return to authentication. On `403`, render a permission state and
  suppress mutations.
- Use the returned public ULID for detail and edit URLs; never expose bigint
  keys.

## Acceptance criteria

1. A user without `manage stores` cannot load or mutate the Store catalog.
2. Search is case-insensitive and composes with filters, sorting, and paging.
3. The UI never requests more than 100 rows or derives a total from page size.
4. Add/edit forms offer only validated public fields and active locale catalog
   choices.
5. Direct Store creation is visibly distinguished from complete merchant
   provisioning.
6. Internal Billing links and raw JSON never cross the component boundary.
