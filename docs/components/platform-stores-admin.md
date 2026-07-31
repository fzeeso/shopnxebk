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

The listing accepts `search` across name, legal name, slug, contact email, and
primary domain. Exact filters are `status`, `business_type`, `currency_code`,
`language_code`, `country_code`, `is_verified`, `is_ai_enabled`,
`is_pos_enabled`, `is_b2b_enabled`, and `is_marketplace_enabled`.
`created_from`/`created_to` use `YYYY-MM-DD`.

Sort fields are `name`, `slug`, `status`, `created_at`, and `updated_at`, with
`direction=asc|desc`. `page` starts at 1; `per_page` defaults to 25 and is
capped at 100. Render pagination from response `meta` and `links`, rather than
inferring totals from the current page.

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
are normalized by the backend. New direct Stores default to `pending`, and
legal name defaults to the submitted display name when omitted.

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
