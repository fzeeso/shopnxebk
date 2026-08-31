# Customers module

`Modules/Customers` owns Store-scoped customer profiles, customer groups,
group display-name translations, the customer-credit ledger, category-access
assignments, and category/Product-specific group discounts. It does not own
merchant users, storefront customer authentication, Orders, Product prices, or
Catalog records.

The source conversion and field-level mapping from the MariaDB dump are in the
[customer data conversion runbook](../customer-data-conversion.md). The
Store-admin workflow and response contracts are in the
[Customers guide](../customers.md).

## Boundary and invariants

- Every addressable entity has an internal bigint key, public ULID, Store key,
  and timezone-aware timestamps. API clients receive only public ULIDs.
- Store ownership is repeated on relationship tables and protected with
  composite foreign keys. Cross-Store customer/group/category/Product links are
  rejected by PostgreSQL even if an application check is bypassed.
- A lower-cased email is unique among non-deleted customers within one Store.
  The same email may belong to a customer in another Store.
- Customer deletion is a soft delete and changes status to `disabled`; credit
  history and legacy traceability remain available internally.
- Credits are append-only through the public service. The customer balance is
  derived with `SUM(customer_credits.amount)` and returned as a fixed-scale
  decimal string. There are no credit update or delete routes.
- At most one group is default per Store. Default groups cannot be deleted, and
  a group referenced by any current or soft-deleted customer cannot be deleted.
- Group codes, discount methods, target types, category access, and application
  rules are language-neutral logic values. Only the customer-group display name
  has a translation table.
- A group creation request must include its default Store-language name.
  Additional names use active Store languages and support `lock_it` so automatic
  translation cannot overwrite merchant-edited rows.
- `specific` category access accepts a replacement list of Catalog Category
  public ULIDs. `all` and `none` require an empty list.
- Targeted discounts reference exactly one same-Store Category or Product.
  Category rules use `category_only` or `category_and_descendants`; Product
  rules use `not_applicable`.

## Public application services

`CustomerManagementService` owns profile search, read, create, edit, group
assignment, default-group assignment, and soft deletion.
`CustomerCreditService` owns paginated ledger reads and append-only entries.
`CustomerGroupManagementService` owns groups, translations, category access,
and target discounts. Every read requires active Store membership; every write
also requires `manage customers`.

The module exports `CustomerGroupResolver`, which accepts a trusted `Store` plus
a group public ULID and returns the internal ID/public ID/code reference. Future
Orders, Discounts, or Catalog audience integrations consume this contract
instead of importing the CustomerGroup Eloquent model or trusting a client
bigint.

The module consumes Catalog through its own `CatalogTargetResolver` port. The
Eloquent adapter resolves Category/Product public ULIDs inside the same Store;
the service layer does not query Catalog tables directly.

## Persistence

| Table | Purpose | Important constraints |
| --- | --- | --- |
| `customer_groups` | Stable group logic and defaults | Store/code case-insensitive uniqueness; one partial default index |
| `customer_group_translations` | Localized display names only | one row per group/language; composite group/Store FK; `lock_it` |
| `customers` | Profile, account migration state, and points | partial Store/email uniqueness; soft delete; composite group/Store FK |
| `customer_credits` | Signed credit ledger entries | non-zero amount; Store/customer composite FK; append-only service |
| `customer_group_categories` | Explicit access allow-list | composite Store/group and Store/Category FKs |
| `customer_group_discounts` | Category/Product percentage rules | exactly-one-target check; target-specific partial unique indexes |

The migration is additive and creates only these six tables. It depends on
Stores, Settings Languages, Authentication Users, and Catalog Category/Product
tables already existing. After explicit authorization and an additive-only
preflight, the named migration was executed against the configured local
PostgreSQL database on 2026-08-31 and recorded in batch 42. All six tables were
verified present and remain empty until a separately authorized import or API
write. Every other environment requires its own reviewed migration rollout.

## Translation behavior

`CustomerGroupTranslationHandler` registers content type `customer_group` with
the shared translation registry. The default Store-language row is the source.
Only `name` is sent for translation. Active target languages are considered;
the source language, inactive languages, existing locked rows, and (for
missing-only requests) existing rows are skipped. Writes run after commit and
use `AutomatedTranslationWriter`, which rechecks `lock_it` under a row lock.

Customer names, company, email, phone, notes, credit reason, credentials,
points, and discount configuration never enter this translation flow.

## Deletion and security

Customer creation accepts an optional confirmed password under the shared
12-character mixed-case/number/symbol policy. The model's `hashed` cast hashes
it before persistence. Customer update prohibits password fields, and resources
never serialize passwords, hashes/salts, internal IDs, or legacy audit
identifiers. Legacy bearer/reset tokens are not represented in the new schema
and must be expired at cutover. The nullable credential-migration columns exist
only for a controlled future rehash-on-login bridge; storefront customer login,
reset, session, and token endpoints are not exposed by this module.

Store deletion cascades Store-owned customer data. Category or Product deletion
cascades only the corresponding access/discount rule. A physical customer
deletion is not available through the API; ordinary deletion is recoverable at
the row level and preserves the ledger.

## Verification

Safe non-database contract tests cover additive schema shape, Store-safe keys,
the single translated concept, creation-only password validation/hashing and
credential response exclusion, append-only credits, route coverage, request
validation, and the exported group resolver. PostgreSQL
integration tests should be added/run only in an authorized disposable database
because repository policy prohibits database-mutating verification by default.

See [Customers to Stores](../module-communication/customers-to-stores.md),
[Customers to Catalog](../module-communication/customers-to-catalog.md),
[Catalog to Customers](../module-communication/catalog-to-customers.md), and
[Customers to Authentication](../module-communication/customers-to-authentication.md).
