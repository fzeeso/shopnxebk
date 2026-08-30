# Customer management

The Customers module exposes authenticated Store-admin services under
`/api/v1/store`. Every request requires a Store-scoped Sanctum identity,
`X-Store-ID`, active membership, and Store-owned route bindings. Reads require
membership; writes require `manage customers`.

## Profiles

`GET /api/v1/store/customers` accepts `page`, `per_page`, case-insensitive
`search`, `status`, and `customer_group_id`. Search covers email, first/last
name, company, and phone. `customer_group_id` is the group public ULID.

`POST /api/v1/store/customers` requires `email` and accepts:

```json
{
  "email": "buyer@example.com",
  "customer_group_id": "01K4Z1BMX5A1F8QMCV1A8Z5E21",
  "company": "Example Buyer Ltd",
  "first_name": "Amina",
  "last_name": "Khan",
  "phone": "+92-300-0000000",
  "status": "active",
  "registered_ip": "203.0.113.10",
  "admin_notes": "B2B account",
  "points_balance": 120,
  "redeemed_points": 40,
  "joined_at": "2026-08-31T00:00:00Z"
}
```

If `customer_group_id` is omitted, the current default group is assigned when
one exists. Supplying `null` explicitly leaves the customer ungrouped. Email is
trimmed/lower-cased and unique among non-deleted customers in the selected
Store. `PATCH /customers/{customer}` accepts the same editable properties;
`DELETE` disables and soft-deletes the customer.

Passwords, legacy IDs, hashes, salts, reset tokens, and API tokens are
prohibited. This is a merchant-management API, not a storefront customer-login
API.

Customer responses include the selected group public ULID and
`credit_balance`, derived from the ledger. Monetary decimals are strings:

```json
{
  "data": {
    "id": "01K4Z1BMX5A1F8QMCV1A8Z5E30",
    "customer_group_id": "01K4Z1BMX5A1F8QMCV1A8Z5E21",
    "email": "buyer@example.com",
    "company": "Example Buyer Ltd",
    "first_name": "Amina",
    "last_name": "Khan",
    "phone": "+92-300-0000000",
    "status": "active",
    "points_balance": 120,
    "redeemed_points": 40,
    "credit_balance": "25.0000",
    "joined_at": "2026-08-31T00:00:00+00:00"
  }
}
```

## Credit ledger

`GET /customers/{customer}/credits` is paginated. `POST` appends a signed,
non-zero entry:

```json
{
  "amount": "25.0000",
  "type": "gift",
  "external_reference": "campaign-2026-08",
  "reason": "Welcome credit",
  "occurred_at": "2026-08-31T00:00:00Z"
}
```

Types are `return`, `gift`, and `adjustment`. Negative adjustment values are
allowed. `external_reference` is an opaque external/public reference; clients
never send an internal Order or user bigint. Entries record the authenticated
merchant user as `created_by`. There are intentionally no update/delete routes:
corrections use a compensating adjustment.

Credit reasons are audit text and are not translated. If a localized label is
needed in a storefront, the client maps the stable `type` or an application
reason code; it must not rewrite ledger history.

## Groups and multilingual names

`GET /customer-groups` is paginated and supports `search` over stable code and
translated name. Create a group with a logic code plus at least the default
Store-language name:

```json
{
  "code": "WHOLESALE",
  "default_discount_percentage": "5.0000",
  "discount_method": "price",
  "is_default": false,
  "category_access_type": "specific",
  "category_ids": ["01K4Z1BMX5A1F8QMCV1A8Z5E40"],
  "translations": [
    {
      "language_id": "01K4Z1BMX5A1F8QMCV1A8Z5E50",
      "name": "Wholesale customers",
      "lock_it": false
    }
  ]
}
```

`code`, percentages, `discount_method`, default state, and category access are
logic fields and stay language-neutral. Only `name` is translated. Upsert or
delete a non-default language name with:

- `PUT /customer-groups/{group}/translations/{language}`
- `DELETE /customer-groups/{group}/translations/{language}`

The `language` segment is a Platform Language public ULID that must be active
for the Store. The default language name cannot be deleted. Creating a group or
editing its default-language name may return `translation_request`; poll its
existing `status_url`. A locked translation is never overwritten by automatic
translation.

## Category access and targeted discounts

Replace the complete explicit category allow-list with
`PUT /customer-groups/{group}/categories`:

```json
{
  "category_ids": [
    "01K4Z1BMX5A1F8QMCV1A8Z5E40",
    "01K4Z1BMX5A1F8QMCV1A8Z5E41"
  ]
}
```

The group must use `category_access_type = specific`. Changing the group to
`all` or `none` clears its explicit assignments.

Create a target discount with
`POST /customer-groups/{group}/discounts`; replace it with `PUT` and remove it
with `DELETE`:

```json
{
  "target_type": "category",
  "target_id": "01K4Z1BMX5A1F8QMCV1A8Z5E40",
  "discount_percentage": "12.5000",
  "applies_to": "category_and_descendants",
  "discount_method": "price"
}
```

`target_type` is `category` or `product`. Category `applies_to` is
`category_only` or `category_and_descendants`; Product rules require
`not_applicable`. The target public ULID must resolve in the same Store, and a
group may have only one discount for each target.

## Error behavior

- `401`: no valid Store-scoped Sanctum identity.
- `403`: inactive membership or missing `manage customers` for a write.
- `404`: customer/group/translation/discount/Catalog target does not belong to
  the selected Store.
- `422`: invalid state, duplicate email/code/target, missing default-language
  name, invalid category access, or unsafe deletion.

See the [Customers module](modules/customers.md),
[conversion runbook](customer-data-conversion.md), and [OpenAPI](openapi.yaml).
