# Legacy customer data conversion runbook

## Scope and result

The reviewed source is the schema-only MariaDB/phpMyAdmin dump `choc (2).sql`.
It defines `customers`, `customer_credits`, `customer_groups`,
`customer_group_categories`, and `customer_group_discounts`; it contains no
`INSERT` statements. The conversion therefore prepares a normalized,
Store-scoped PostgreSQL schema and supported application services. The named
additive migration was executed against the configured local PostgreSQL
database on 2026-08-31. It created empty tables only; no legacy customer row was
imported or changed.

The new migration creates six tables. The extra table,
`customer_group_translations`, is the only multilingual split. Customer
identity, credentials, contact values, notes, balances, ledger reasons, and
discount rules are deliberately not translated.

## Conversion steps completed

1. Parsed all five source definitions, indexes, defaults, enum values, and
   auto-increment keys.
2. Assigned the domain to a dedicated `Customers` module and documented its
   dependencies on Stores, Settings Languages, Authentication Users, and
   Catalog Categories/Products.
3. Replaced MariaDB/MyISAM conventions with PostgreSQL bigint keys, public
   ULIDs, timezone timestamps, decimal checks, partial unique indexes, and real
   foreign keys.
4. Added non-null `store_id` to every owned/relationship table and composite
   foreign keys that reject cross-Store links.
5. Separated the one presentation field (`customer_groups.groupname`) into
   language rows; retained logic codes/methods/types in base tables.
6. Replaced the ambiguous polymorphic `catorprodid` with mutually exclusive
   `category_id` and `product_id` columns plus an exactly-one-target check.
7. Converted Store credit into an append-only signed ledger contract. Balance
   is derived rather than accepting a mutable client balance.
8. Added public Store-admin routes/resources/validation and required
   `manage customers` for writes.
9. Added a cross-module customer-group resolver and a Catalog-target port so
   future modules do not depend on customer table details.
10. Added automatic group-name translation with active-language/default-source
    enforcement and locked-row protection.
11. Added non-database contract/request tests and complete rollout,
    reconciliation, rollback, and security guidance.
12. After explicit approval, confirmed the `local` environment, PostgreSQL
    driver, dependency-table presence, pending migration status, and absence of
    all target tables; then executed only
    `2026_08_31_001000_create_customer_domain_tables` and verified all six
    tables plus the 18 registered Store-admin routes.

## Field mapping: `customers`

| Legacy field | PostgreSQL destination | Conversion rule |
| --- | --- | --- |
| `customerid` | `customers.legacy_id` | Preserve for traceability; PostgreSQL generates new bigint `id` and public ULID. |
| — | `customers.store_id` | Supply the trusted destination Store bigint during import; never derive it from source content. |
| `salt` | `legacy_password_salt` | Empty string becomes null; never expose through API. |
| `custpassword` | `legacy_password_hash` | Preserve only for a controlled rehash bridge; do not copy into `password`. |
| `custimportpassword` | `legacy_import_password_hash` | Preserve only when non-empty; never expose. |
| — | `password` | Null during import unless a separate Authentication-owned flow creates a modern adaptive hash. |
| `custconcompany` | `company` | Trim; empty string becomes null. |
| `custconfirstname` | `first_name` | Trim; empty string becomes null. |
| `custconlastname` | `last_name` | Trim; empty string becomes null. |
| `custconemail` | `email` | Trim/lower-case; quarantine blank/invalid/duplicate Store emails before load. |
| `custconphone` | `phone` | Trim; empty string becomes null. |
| `customertoken` | none | Expire/discard. Do not migrate bearer-token plaintext. |
| `customerpasswordresettoken` | none | Expire/discard and require a new reset flow. |
| `customerpasswordresetemail` | none | Redundant transport state; canonical email remains `email`. |
| `custdatejoined` | `joined_at`, `created_at` | Convert non-zero Unix seconds to UTC `timestamptz`; use a documented cutover timestamp for zero. |
| `custlastmodified` | `updated_at` | Convert non-zero Unix seconds to UTC; otherwise use `created_at`. |
| `custstorecredit` | reconciliation entry | Do not write a profile balance. Reconcile against imported ledger sum as described below. |
| `custregipaddress` | `registered_ip` | Validate IPv4/IPv6; invalid/empty values become null and are reported. |
| `custgroupid` | `customer_group_id` | Resolve through the destination Store plus `customer_groups.legacy_id`; legacy zero becomes null/default by explicit policy. |
| `custnotes` | `admin_notes` | Preserve as merchant audit notes; do not translate. |
| `custformsessionid` | none | Discard ephemeral form-session state. |
| `new_points` | `points_balance` | Null becomes zero; quarantine negative values. |
| `redeem` | `redeemed_points` | Null becomes zero; quarantine negative values. |
| — | `status` | Import as `active` unless source-specific disable evidence exists. |
| — | `deleted_at` | Null for imported active rows. |

## Field mapping: credits and groups

### `customer_credits`

| Legacy field | Destination | Rule |
| --- | --- | --- |
| `custcreditid` | `legacy_id` | Preserve per Store; generate new internal/public IDs. |
| `customerid` | `customer_id` | Resolve by Store plus customer `legacy_id`; reject missing parents. |
| `creditamount` | `amount` | Preserve decimal(20,4); zero rows fail the new invariant and must be reviewed. |
| `credittype` | `type` | Preserve `return`, `gift`, or `adjustment`. |
| `creditdate` | `occurred_at` | Convert Unix seconds to UTC; report zero dates and use the documented cutover only if approved. |
| `creditrefid` | `legacy_reference_id` | Zero becomes null; do not assume it is a new Order ID. |
| `credituserid` | `legacy_user_id` | Preserve for audit; resolve `created_by` only through an explicit trusted user map. |
| `creditreason` | `reason` | Trim; legacy empty/`0` becomes `Legacy credit import`. Not translated. |

### `customer_groups`

| Legacy field | Destination | Rule |
| --- | --- | --- |
| `customergroupid` | `legacy_id` | Preserve per Store; generate new internal/public IDs. |
| `groupname` | translation `name` | Create a row for the Store default language. Generate stable `code` separately, for example `LEGACY_GROUP_<id>`, to avoid treating display text as logic. |
| `discount` | `default_discount_percentage` | Preserve scale; quarantine values outside 0–100. |
| `discountmethod` | `discount_method` | Trim and preserve until a later controlled vocabulary migration. |
| `isdefault` | `is_default` | Convert to boolean; if multiple rows are true, select one explicitly before load. |
| `categoryaccesstype` | `category_access_type` | Map `none`, `all`, `specific` unchanged. |

### Relationships and targeted discounts

`customer_group_categories.customergroupid` resolves to the Store group and
`categoryid` resolves to the same-Store Catalog Category. Duplicate pairs
collapse only after counts are recorded.

For `customer_group_discounts`, preserve `groupdiscountid` as `legacy_id`,
resolve `customergroupid`, lower-case `discounttype`, and route `catorprodid` to
`category_id` or `product_id`. Map `CATEGORY_ONLY` to `category_only`,
`CATEGORY_AND_SUBCATS` to `category_and_descendants`, and `NOT_APPLICABLE` to
`not_applicable`. Preserve `discountpercent` and `discountmethod`; quarantine
missing targets, duplicate group/target pairs, or invalid target/application
combinations.

## Recommended data-load order

1. Take an immutable source snapshot and record source row counts/checksums.
2. Choose exactly one destination Store and its default active Language.
3. Build trusted maps for legacy Category/Product IDs and, optionally, legacy
   admin user IDs. Do not accept mapping instructions from Store content.
4. Import groups and their default-language names; resolve the one default.
5. Import customers with normalized emails and resolved group IDs. Keep modern
   `password` null and expire all legacy tokens.
6. Import category access pairs.
7. Import targeted discounts after every Category/Product target resolves.
8. Import credit rows and preserve legacy references separately.
9. For each customer, compare the signed imported credit sum with legacy
   `custstorecredit`. When different, append one `adjustment` entry for the
   exact delta with reason `Legacy opening-balance reconciliation` and a stable
   external reference. Never overwrite ledger history to force the total.
10. Validate counts, parent resolution, email uniqueness, default-group count,
    credit totals, points, and translated group-name coverage before cutover.
11. Keep the legacy source read-only until the new application has passed an
    agreed reconciliation/acceptance window.

## Acceptance checks

- Every imported row has the intended `store_id`; there are no cross-Store
  group, customer, category, Product, or discount links.
- Source/destination counts match after documented quarantine and deduplication.
- Every source primary key appears once as destination `legacy_id` within the
  Store.
- Every customer has a normalized unique email or a documented quarantine
  record.
- Exactly zero or one group is default, according to the rollout decision.
- Every group has its default-language translation; only display names were
  translated.
- Every credit is non-zero and its type is valid; per-customer ledger sums equal
  the accepted legacy balances after explicit reconciliation entries.
- No legacy API/reset tokens appear in PostgreSQL, logs, documentation, or API
  responses.
- No migration/API response exposes internal bigint IDs, hashes, salts, or
  legacy audit IDs.

## Deployment and rollback

The additive Customers migration was executed only against the configured local
PostgreSQL database after its dependency tables and pending state were checked.
It created the six empty tables and was recorded in migration batch 42. No
legacy rows were loaded. Other local, staging, or production environments must
still run only the named migration after the same safeguards. Production data
loading requires a separately reviewed, idempotent importer with a
dry-run/quarantine report and Store lock.

Before traffic cutover, rollback means removing only the newly loaded Customers
tables in an authorized disposable/local environment. After traffic or ledger
writes begin, do not roll back by dropping data; stop writes, retain both data
sets, diagnose, and use a forward repair/re-import plan. Never use a generic
rollback, refresh, seed, or `migrate:fresh` command on Store data.
