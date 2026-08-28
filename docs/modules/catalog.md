# Catalog module

`Modules/Catalog` owns the global Platform classification taxonomy plus the
Store-local merchandising and product persistence foundation, the reusable
multi-language Product Modifier library, the global
localized fulfillment catalog, Brand/Fulfillment Type/Product/Custom Field REST
routes, Category/Product Type/Product/Custom Field GraphQL schema, Catalog models,
transactional services, resolvers, translation handlers, and authorization
boundary. Product files/fulfillment, search
projections, and admin screens remain follow-up work.

The complete column-by-column contract, diagrams, indexes, deletion behavior,
and query patterns are in the [Catalog schema reference](../catalog.md).

## API exposure

| Resource | Supported API surface |
| --- | --- |
| Brand | Store REST CRUD and signed media delivery; no GraphQL fields |
| Category | GraphQL list/detail/create/update/delete only; no Store REST route |
| Product Type | GraphQL list/detail/create/update/delete only; no Store REST route |
| Product | Store REST and GraphQL lifecycle APIs |
| Product Detail façade | Store REST composed bootstrap/read and transactional partial-section create/update |
| Product Option and Variant | Nested Store REST multilingual CRUD; no GraphQL fields |
| Product Image | Nested Store REST metadata CRUD only |
| Custom Field | Store REST and GraphQL definition/option lifecycle plus Product/Variant typed values |
| Modifier Library | Store REST category/definition lifecycle; nested translations, values, rules, and prices |
| Product Modifier | Nested Store REST groups/assignments/reorder plus resolved storefront DTO |
| Fulfillment Type | Platform/Store REST only |

Models and tables not listed here remain persistence contracts until an
explicit route or GraphQL field is implemented and documented.

## Owned persistence

| Area | Tables |
| --- | --- |
| Brands | `brands`, `brand_translations` |
| Collections | `collections`, `collection_translations`, `collection_rules`, `collection_ai_jobs`, `product_collections` |
| Categories and tags | `categories`, `category_translations`, `tags`, `product_tags`, `product_categories` |
| Platform taxonomy | `platform_taxonomies`, `platform_taxonomy_nodes`, `platform_taxonomy_custom_fields` |
| Product types | `product_types`, `product_type_translations` |
| Fulfillment types | `fulfillment_types`, `fulfillment_type_translations` |
| Products | `products`, `product_translations` |
| Modifier library | `modifier_library_categories`, `modifier_library_category_translations`, `modifier_definitions`, `modifier_translations`, `modifier_values`, `modifier_value_translations`, `modifier_validation_rules`, `modifier_validation_rule_translations`, `modifier_price_adjustments`, `modifier_value_price_adjustments` |
| Product modifier assignments | `product_modifier_groups`, `product_modifier_group_translations`, `product_modifier_assignments`, `product_modifier_assignment_translations`, `product_modifier_value_assignments`, `product_modifier_price_overrides`, `product_modifier_value_price_overrides` |
| Cart/order modifier integration | `cart_item_modifier_selections`, `order_item_modifier_snapshots` |
| Options and variants | `product_options`, `product_option_translations`, `product_option_values`, `product_option_value_translations`, `product_variants`, `product_variant_translations`, `variant_option_values` |
| Shared Product options | `shared_product_options`, `shared_product_option_translations`, `shared_product_option_values`, `shared_product_option_value_translations`, `product_shared_option_assignments` |
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
- Fulfillment types are a global read-only reference catalog with unique stable
  codes, active state, and sort order. Each Language-catalog locale has one
  translation row keyed by `(fulfillment_type_id, locale)`; deleting a parent
  or Language cascades its localized row.
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
- Products retain product-level commerce snapshots for SKU and external trade
  identifiers, downloadable-file/availability metadata, six decimal price
  values, inventory thresholds, warranty, shipping dimensions/cost, rating and
  activity counters, purchase/price/search switches, a related-product display
  count, condition/preorder/release controls, review enablement, quantity
  bounds, product points, and a legacy tax-class identifier. Defaults make the
  migration safe for existing rows; `releasedate` and `warranty` are nullable.
  Store-scoped Product REST CRUD now validates and serializes these columns;
  Product GraphQL remains unchanged.
- Product image metadata has nested Store REST CRUD under each Product. It
  exposes public image/variant ULIDs, locator, pixel dimensions, gallery
  position, and active-Store-locale alt text. Reads require membership and
  writes require `manage products`; the API does not own binary upload or
  storage deletion.
- The Product Detail façade composes Product core, selector references, images,
  attached media, Custom Field values, options, variants, shared options, and
  Modifier configuration into one bounded Store response. Its intelligent save
  command touches only named sections, supports request-local references for
  dependent creates, detects optional stale revisions, and wraps Catalog-owned
  writes in one transaction while delegating validation to the owning services.
  Binary uploads remain a separate contract. Future owning modules register an
  explicit `ProductDetailSectionProvider`; its unique key, relative validation,
  bounded bootstrap/read payload, transactional save, metadata, capabilities,
  and shared request-local references are then composed automatically. No table
  is exposed by discovery or naming convention. Providers retain permission,
  Store/Product-isolation, serialization, and domain-service ownership, and
  defer remote effects until after commit.
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
- Product option/value REST writes accept only active Store locales and preserve
  omitted language rows and translation locks. Variant writes require exactly
  one same-product value per current option and reject duplicate combinations.
  Option dimensions cannot be added or deleted while variants exist, selected
  values cannot be deleted, and the first/last variant synchronizes
  `products.has_variants`. Variant reads carry all option/value and optional
  title translations for a multi-language editor without bigint leakage.
- Shared Product options expose Store-wide REST CRUD for a unique internal
  name, constrained presentation type, translated display name, ordered
  translated Values, and one optional default. Product assignments reuse the
  same definition across Products and must be removed before definition
  deletion. Composite foreign keys and Store-scoped ULID lookups reject
  cross-Store Products, Options, Values, and assignments. A Value rejects a
  duplicate locale inside its own translation list, while separate Values may
  each carry a translation for the same Store locale.
- Product/option/variant composite foreign keys ensure a variant cannot select
  an option value from another product. Equivalent constraints keep product
  images, digital assets, license keys, and variant-level custom fields attached
  to the same Store and product.
- Custom-field definitions support typed scalar, translated text/URL, select,
  and multi-select values. PostgreSQL enforces one value per
  definition/product/optional-variant scope and prevents mixed-definition
  option assignments.
- `CustomFieldManagementService` exposes definition/option CRUD and Product- or
  Variant-scope value list/read/idempotent-set/delete through both REST and
  GraphQL. Reads require membership; writes require `manage products`. The
  service enforces type-specific request shapes, active-locale translations,
  Product Type-code applicability, nested Variant ownership, and
  same-definition option selection before composite foreign keys provide the
  final boundary.
- Modifier definitions are Store-owned reusable catalog records, never copied
  when attached to Products. Product assignments may override required/min/max
  state, translated presentation, enabled/default values, settings, grouping,
  ordering, and prices. Composite foreign keys carry the modifier identity
  through Product/value junctions so a value from another modifier or Store is
  rejected by PostgreSQL.
- Modifier REST writes support both aggregate parent updates and explicit
  full-collection `PUT` operations for translations, values, rules, and library
  or Product pricing. The latter accept only their named collection, run through
  the same Store-scoped service transaction, and never expose internal row IDs.
  Public-ULID modifier values also have parent-scoped CRUD for concurrency-safe
  single-value changes, including nested translation and value-price updates.
- Translation resolution is field-by-field: requested-locale Product override,
  requested-locale library translation, Store-default library translation,
  then a code-derived safe label. Value names use requested locale, Store
  default, then value code. Resolved responses expose the active language flag,
  native name, direction, and all active Store language options. Localized
  required/rule messages and the generic validation message drive server-side
  modifier selection errors.
- Price resolution independently chooses modifier and value components. A
  matching Product override replaces its corresponding library component;
  exact channel/customer-group rows outrank broader rows; active currency and
  date windows are mandatory. Fixed amounts and Product-base-price percentages
  are summed server-side. The cart writer never accepts a client price.
- Multi-select carts persist one row per selected value. Free-form modifiers
  persist typed JSON. Checkout copies names, codes, public IDs, locale,
  currency, inputs, and calculated amount into append-only order snapshots.
  Catalog edits never update those snapshots.

## Fulfillment and security boundaries

The global fulfillment catalog exposes `merchant`, `dropship`,
`third_party_logistics`, `store_pickup`, `local_delivery`, and `digital` to
authenticated Platform users through
`/api/v1/platform/settings/fulfillment-types`. Listing/detail are available to
all Platform accounts; create/update require `manage platform settings`.
Store members can list active types through
`GET /api/v1/store/fulfillment-types`.
The catalog is seeded for every Language row and is not yet a foreign key from
Products. The existing Product fulfillment field still accepts `physical`,
`digital`, `software`, and `service`. Digital assets may apply to a product or
one variant. Software license
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
- Cart, Orders, Sales Channels, and Customer Groups are not installed modules.
  Catalog therefore provides `CartModifierSelectionService` and
  `OrderModifierSnapshotService` integration seams and nullable internal
  audience columns, but no cart/checkout HTTP route. The migration adds
  `cart_items`/`order_items` foreign keys only when those owning tables already
  exist. Audience IDs are not accepted by the public modifier API until those
  modules provide public ULIDs and Store-scoped resolvers.

See [Catalog to Stores](../module-communication/catalog-to-stores.md),
[Catalog to Settings](../module-communication/catalog-to-settings.md), and
[Catalog to Files](../module-communication/catalog-to-files.md). See also the
complete [Catalog schema reference](../catalog.md) and the end-to-end
[API manual](../api-manual.md).
