# Catalog module

`Modules/Catalog` owns the global Platform classification taxonomy plus the
Store-local merchandising and product persistence foundation, Brand REST
routes, Category/Product Type/Product GraphQL schema, Catalog models,
transactional services, resolvers, translation handlers, and authorization
boundary. Options, variants, product files/fulfillment, custom fields, search
projections, and admin screens remain follow-up work.

The complete column-by-column contract, diagrams, indexes, deletion behavior,
and query patterns are in the [Catalog schema reference](../catalog.md).

## Owned persistence

| Area | Tables |
| --- | --- |
| Brands | `brands`, `brand_translations` |
| Collections | `collections`, `collection_translations`, `collection_rules`, `collection_ai_jobs`, `product_collections` |
| Categories and tags | `categories`, `category_translations`, `tags`, `product_tags`, `product_categories` |
| Platform taxonomy | `platform_taxonomies`, `platform_taxonomy_nodes`, `platform_taxonomy_custom_fields` |
| Product types | `product_types`, `product_type_translations` |
| Products | `products`, `product_translations` |
| Options and variants | `product_options`, `product_option_translations`, `product_option_values`, `product_option_value_translations`, `product_variants`, `product_variant_translations`, `variant_option_values` |
| Media and fulfillment | `product_images`, `product_image_translations`, `product_digital_assets`, `product_digital_asset_translations`, `product_license_keys` |
| Custom fields | `custom_field_definitions`, `custom_field_definition_translations`, `custom_field_options`, `custom_field_option_translations`, `product_custom_field_values`, `product_custom_field_value_translations`, `product_custom_field_value_options` |

## Persistence rules

- Addressable entities use bigint `id`, unique ULID `public_id`, and
  timezone-aware audit timestamps. Store-owned entities additionally carry a
  non-null indexed bigint `store_id`; Platform taxonomies/nodes are global.
- Translation and relationship tables are not public resources. They use
  composite keys and retain `store_id` so composite foreign keys reject
  cross-Store parents, assignments, variants, options, and custom values.
- Every translation table has non-null `lock_it = false`. Merchant editors may
  lock their content; system-generated translation jobs must use
  `AutomatedTranslationWriter` and skip locked rows.
- Brand, collection, category, and product slugs are unique by
  `(store_id, locale, slug)`. Locale fields accept up to 35 characters for
  BCP 47-style values.
- Platform taxonomies are versioned global trees. A same-taxonomy composite
  self-reference protects node ancestry; code/path uniqueness provides stable
  classification, and only one taxonomy may be the Platform default.
- Product types have Store-local codes, nullable foreign-key Platform
  taxonomy-node mappings, active/sort metadata, and localized names/slugs/descriptions. Their
  translations use bigint `id`, unique `(product_type_id, locale)`, unique
  `(store_id, locale, slug)`, and a composite foreign key that rejects a parent
  from another Store.
- Brand reads require active Store membership. Brand writes require
  `manage products`; they accept localized identity/SEO data plus optional
  translation locks, managed image/banner uploads, a legacy logo locator,
  official website, origin, active state, and sort order. Brand translation
  writes commit the submitted source first, record durable translation work,
  and queue all eligible Store locales after commit. The Brand handler skips
  locked rows and rejects stale snapshots before applying generated fields.
- Categories form the strict merchant-curated taxonomy. Collections are
  merchandising groups and may be manual, rule-based, or AI-generated.
  PostgreSQL permits only one primary category assignment per Store/product.
- Category, Product Type, and Product GraphQL reads require active Store membership. Their
  explicit mutations require `manage products`, use public ULIDs, reject
  cross-Store references, resolve Product Types within the selected Store,
  accept global Platform taxonomy-node ULIDs, validate Category cycles, and replace product
  category/primary assignments atomically. Lists use bounded pagination plus
  explicit filter and sort allow-lists.
- Category, Product Type, and Product translation inputs accept only active Store locales.
  Manual `lock_it` values are preserved, source writes create durable
  translation requests, generated localized slugs remain Store/locale unique,
  and entity-specific handlers recheck stale snapshots and locked targets.
  Category translations also own nullable `image_url` and `banner_url` manual
  locators; services validate and persist them without sending URLs to the
  automatic language translator.
- Variant prices use non-negative integer minor units plus a three-letter
  uppercase currency code. A Store-local partial unique index rejects duplicate
  non-null SKUs.
- Product/option/variant composite foreign keys ensure a variant cannot select
  an option value from another product. Equivalent constraints keep product
  images, digital assets, license keys, and variant-level custom fields attached
  to the same Store and product.
- Custom-field definitions support typed scalar, translated text/URL, select,
  and multi-select values. PostgreSQL enforces one value per
  definition/product/optional-variant scope and prevents mixed-definition
  option assignments.

## Fulfillment and security boundaries

`physical`, `digital`, `software`, and `service` are the supported fulfillment
types. Digital assets may apply to a product or one variant. Software license
rows hold a Store-local key pool and retain an opaque nullable future order ID;
there is no Orders foreign key until that module owns a stable contract.

Application write paths must encrypt sensitive license material before storing
`key_code`. They must treat digital `file_url` values as protected storage
locators and issue authorized temporary downloads instead of returning the
stored value directly. Brand image/banner writes are the current exception:
Catalog delegates those uploads to the shared image service and Media Library.
Product files still have no upload, scan, signing, or delivery workflow.

## Integration boundaries

- Catalog consumes Stores identity through internal bigint foreign keys and
  must use Stores middleware/context before future Store APIs access rows.
- Product Type list/detail queries support bounded pagination, explicit
  filters/sorts, exact normalized-locale selection, Product counts, and public
  ULIDs. Create/update/delete require `manage products`; deletion cascades its
  translations and nulls Product references without deleting Products.
- Catalog's Brand, Category, Product Type, and Product services request automatic translation
  through the shared `TranslationCoordinator`. Their handlers own only each
  entity's field, slug, locale, and locked-row behavior. They never perform an
  external AI call inside a Catalog write transaction.
- Locale and currency strings follow Settings-owned catalog semantics without
  importing Settings bigint IDs into translated or historical product data.
- Future Files and Search modules consume Catalog identifiers through contracts
  or events; they do not become the product system of record.
- Inventory owns stock ledgers/reservations when implemented. The current
  variant quantity and policy are the requested sellability snapshot, not an
  Inventory module substitute.

See [Catalog to Stores](../module-communication/catalog-to-stores.md),
[Catalog to Settings](../module-communication/catalog-to-settings.md), and
[Catalog to Files](../module-communication/catalog-to-files.md). See also the
complete [Catalog schema reference](../catalog.md) and the end-to-end
[API manual](../api-manual.md).
