# ShopNXE API manual

This is the implementation handoff for clients and future ShopNXE applications. It explains how a request moves through authentication, Store selection, authorization, validation, persistence, translation, and response serialization. The executable sources remain the Lighthouse schemas under `graphql/` and `Modules/*/graphql/`, plus `docs/openapi.yaml` for REST.

The generated [GraphQL operation reference](generated/graphql-operations.md) is refreshed by `composer docs:update` and checked by CI. Start with that index when an operation is added or renamed.

## 1. Endpoint and request envelope

GraphQL uses one endpoint:

```http
POST /graphql
Accept: application/json
Content-Type: application/json
Authorization: Bearer <store-bound-token>
X-Store-ID: <store-public-ulid>
```

Cookie-authenticated Sanctum sessions are also supported. A bearer token used with a Store operation must have the `store:access` ability and be bound to the same Store as `X-Store-ID`. Category, Product Type, and Product reads require an active Store membership. Mutations additionally require the Store-team permission `manage products`.

The JSON envelope is standard GraphQL:

```json
{
  "query": "query Product($id: ID!) { product(id: $id) { id status } }",
  "variables": { "id": "01K..." },
  "operationName": "Product"
}
```

All public entity IDs are ULIDs. Never send or persist ShopNXE's internal bigint primary keys in another application.

Store Product REST requests use the same authentication and Store envelope:

```http
GET /api/v1/store/products?status=active&per_page=25
Authorization: Bearer <store-bound-token>
X-Store-ID: <store-public-ulid>
Accept: application/json
```

### 1.1 Catalog API exposure matrix

| Resource | REST exposure | GraphQL exposure |
| --- | --- | --- |
| Brands | Full Store CRUD plus signed Brand media delivery under `/api/v1/store/brands` | Not exposed |
| Categories | Not exposed through REST | Full list/detail/create/update/delete through `/graphql` |
| Product Types | Not exposed through REST | Full list/detail/create/update/delete through `/graphql` |
| Products | Full Store CRUD under `/api/v1/store/products` | Full list/detail/create/update/delete through `/graphql` |
| Product Images | Nested metadata CRUD under `/api/v1/store/products/{product}/images` | Not exposed |
| Reusable Media | Upload/complete/list/detail/content/delete plus Product and Variant attachment under `/api/v1/store` | Not exposed |
| Modifier Library | Store category/definition lifecycle with nested translations, values, validation, and pricing | Not exposed |
| Product Modifiers | Nested group/assignment/reorder APIs and resolved storefront DTO | Not exposed |
| Fulfillment Types | Platform management plus active Store discovery through REST | Not exposed |

There are currently no `/api/v1/store/categories` or
`/api/v1/store/product-types` routes. REST clients that need those resources
must use the Catalog GraphQL operations or wait for a separately versioned REST
contract; database persistence alone does not imply REST exposure.

## 2. Request cycle

```mermaid
sequenceDiagram
    participant Client
    participant HTTP as Laravel + Lighthouse
    participant Store as Store middleware
    participant Resolver
    participant Service as Catalog service
    participant DB as PostgreSQL
    participant Queue as Translation queue

    Client->>HTTP: POST /graphql + auth + X-Store-ID
    HTTP->>Store: Resolve Store ULID and membership
    Store->>Store: Verify token binding and permission team
    HTTP->>Resolver: Validate GraphQL document and guard
    Resolver->>Service: Authenticated User + typed arguments
    Service->>Service: Allow-list validation and authorization
    Service->>DB: One Store-scoped transaction
    DB-->>Service: Catalog entity + translations
    Service->>DB: Record translation request when targets exist
    DB-->>Client: Commit before response
    Service-->>Queue: Dispatch after commit
    HTTP-->>Client: data and optional translationRequest
```

Resolvers are adapters. Business rules, Store ownership, permissions, translation synchronization, hierarchy checks, and product-category synchronization live in Catalog services. Database composite foreign keys are the final cross-Store boundary.

## 3. Catalog GraphQL operations

| Operation | Purpose | Permission |
| --- | --- | --- |
| `categories` | Filtered, sorted, paginated category list | Active Store membership |
| `category` | One category by public ULID | Active Store membership |
| `productTypes` | Filtered, sorted, paginated Product Type list | Active Store membership |
| `productType` | One Product Type by public ULID | Active Store membership |
| `products` | Filtered, sorted, paginated product list | Active Store membership |
| `product` | One product by public ULID | Active Store membership |
| `createCategory`, `updateCategory`, `deleteCategory` | Category lifecycle | `manage products` |
| `createProductType`, `updateProductType`, `deleteProductType` | Product Type lifecycle | `manage products` |
| `createProduct`, `updateProduct`, `deleteProduct` | Product lifecycle | `manage products` |

Lists default to 20 records and permit at most 100. Category sorting is allow-listed to `SORT_ORDER`, `CREATED_AT`, or `UPDATED_AT`. Product Type sorting is allow-listed to `SORT_ORDER`, `CODE`, `CREATED_AT`, or `UPDATED_AT`. Product sorting is allow-listed to `CREATED_AT`, `UPDATED_AT`, `STATUS`, or `PUBLISHED_AT`.

Category filters are `search`, `locale`, `parentId`, `rootOnly`, and `isActive`. Product Type filters are `search`, `locale`, `code`, `platformTaxonomyNodeId`, and `isActive`. Product filters are `search`, `locale`, `status`, `fulfillmentType`, `brandId`, and `categoryId`. Search matches the entity's localized title/name or slug case-insensitively; `locale` restricts which translation is searched.

### 3.1 Fulfillment Type REST catalog

`GET /api/v1/platform/settings/fulfillment-types` returns the complete global
fulfillment catalog for Platform settings. Any authenticated Platform account
may list or read a type by stable code. `POST` creates a type and `PATCH
/api/v1/platform/settings/fulfillment-types/{code}` updates active/sort metadata
or upserts supplied translations; both writes require `manage platform
settings`. Stable codes are immutable after creation. Deactivation is the
supported lifecycle operation, so no delete endpoint is exposed.

`GET /api/v1/store/fulfillment-types` returns only active types to an
authenticated member of the Store selected by `X-Store-ID`. It is read-only
and intended for Store-admin selectors.

The response is sorted by `sort_order`, then internal `id`. Each item contains
`id`, `code`, `is_active`, `sort_order`, audit timestamps, and every seeded
`{ id, locale, name, description }` translation. The numeric IDs are catalog
reference values for this REST response; clients should use stable `code`
values for integrations. The six defaults are `merchant`, `dropship`,
`third_party_logistics`, `store_pickup`, `local_delivery`, and `digital` with
sort orders 1 through 6. `DatabaseSeeder` creates one non-empty localized row
for each fulfillment type and every row in `languages`.

This catalog is separate from the existing `products.fulfillment_type`
physical/digital/software/service field. The former describes the operational
fulfillment method; the Product field describes the product delivery mode.

## 4. Product Type lifecycle

A Product Type is a reusable, Store-local classification such as `running-shoe`
or `ebook`. `code` is required, accepts ASCII letters, numbers, dots,
underscores, and hyphens, and is indexed but not database-unique. `sortOrder`
controls presentation order, while `isActive` controls availability. An
optional `platformTaxonomyNodeId` maps the type to a global Platform taxonomy
node; all API identifiers are public ULIDs.

Create a Product Type:

```graphql
mutation CreateProductType($input: CreateProductTypeInput!) {
  createProductType(input: $input) {
    productType {
      id
      code
      platformTaxonomyNodeId
      isActive
      sortOrder
      productsCount
      translations { locale name slug description lockIt }
      translation(locale: "de") { name slug }
    }
    translationRequest { id status sourceLocale targetLocales }
  }
}
```

```json
{
  "input": {
    "code": "running-shoe",
    "platformTaxonomyNodeId": "01K_TAXONOMY_NODE",
    "isActive": true,
    "sortOrder": 10,
    "translations": [
      {
        "locale": "en",
        "name": "Running shoe",
        "slug": "running-shoe",
        "description": "Footwear designed for running.",
        "lockIt": false
      }
    ]
  }
}
```

Create commits the Product Type and supplied translations together. If other
active Store languages are eligible, `translationRequest` identifies the
after-commit job; generated translations may not be visible in the original
mutation payload, so poll the request and read the entity again.

List and search Product Types:

```graphql
query ProductTypes($filter: ProductTypeFilterInput!) {
  productTypes(
    filter: $filter
    page: 1
    perPage: 25
    sortBy: SORT_ORDER
    sortDirection: ASC
  ) {
    data {
      id
      code
      isActive
      productsCount
      translation(locale: "de") { name slug description }
    }
    paginatorInfo { count currentPage lastPage perPage total }
  }
}
```

```json
{
  "filter": {
    "search": "Laufschuh",
    "locale": "de",
    "isActive": true,
    "platformTaxonomyNodeId": "01K_TAXONOMY_NODE"
  }
}
```

Use `productType(id: ID!)` for detail reads. Update inputs are partial: omitted
metadata remains unchanged, and each supplied translation is a complete value
for that locale. Send `platformTaxonomyNodeId: null` to remove the global
mapping. Deleting a Product Type cascades its translations and sets
`products.product_type_id` to null; Products are not deleted.

## 5. Category lifecycle

A category is Store-owned navigation taxonomy. `parentId` creates a tree, `sortOrder` controls sibling ordering, and translated slugs are unique inside one Store and locale. A category cannot become its own parent or a child of one of its descendants.

Create a root category:

```graphql
mutation CreateCategory($input: CreateCategoryInput!) {
  createCategory(input: $input) {
    category {
      id
      parentId
      isActive
      sortOrder
      translation(locale: "en") {
        title
        slug
        description
        seoTitle
        pageTitle
        lockIt
      }
    }
    translationRequest {
      id
      status
      sourceLocale
      targetLocales
    }
  }
}
```

```json
{
  "input": {
    "isActive": true,
    "sortOrder": 10,
    "imageUrl": "/catalog/shoes.webp",
    "translations": [
      {
        "locale": "en",
        "title": "Shoes",
        "slug": "shoes",
        "description": "Browse every shoe",
        "imageUrl": "/catalog/localized/shoes.webp",
        "bannerUrl": "/catalog/localized/shoes-banner.webp",
        "seoTitle": "Shop shoes",
        "seoDescription": "Footwear for every occasion",
        "pageTitle": "All shoes",
        "searchKeywords": "shoe, footwear",
        "categoryTemplate": "category-grid",
        "lockIt": false
      }
    ]
  }
}
```

Create a child by sending the parent's public ULID as `parentId`. Send `parentId: null` in `UpdateCategoryInput` to move a category back to the root. Omitted update fields remain unchanged; a supplied translation is a complete value for that locale.

The Category-level `imageUrl` is the shared/default taxonomy image. Each
translation may additionally provide its own localized `imageUrl` and
`bannerUrl`; both accept a root-relative path or an HTTP(S) URL and remain
nullable. Localized image locators are saved exactly as manual metadata and are
not sent to the language-translation provider.

Delete behavior is deliberate: child categories become roots, category translations are deleted, and product assignments to that category are removed. Products themselves are not deleted.

## 6. Product lifecycle

A product can reference an existing Store-local Brand, zero or more categories, and at most one primary category. When `primaryCategoryId` is present it must also appear in `categoryIds`. Category order in `categoryIds` becomes assignment `sort_order`.

Allowed product values:

| Field | Values or behavior |
| --- | --- |
| `status` | `draft`, `active`, `archived` |
| `fulfillmentType` | `physical`, `digital`, `software`, `service` |
| `trackInventory` | Boolean; defaults to `true` |
| `hasVariants` | Read-only in this API; variant APIs will own it |
| `productTypeId` | Nullable same-Store `product_types.public_id` ULID |
| `platformTaxonomyNodeId` | Nullable global `platform_taxonomy_nodes.public_id` ULID |
| `publishedAt` | Set on first activation, cleared when returned to draft, retained when archived |

Create a product:

```graphql
mutation CreateProduct($input: CreateProductInput!) {
  createProduct(input: $input) {
    product {
      id
      status
      fulfillmentType
      publishedAt
      productTypeId
      platformTaxonomyNodeId
      primaryCategoryId
      categories { id translation(locale: "en") { title } }
      translations { locale title slug lockIt }
    }
    translationRequest { id status targetLocales }
  }
}
```

```json
{
  "input": {
    "brandId": "01K...",
    "vendor": "ShopNXE Demo",
    "productTypeId": "01K_PRODUCT_TYPE",
    "platformTaxonomyNodeId": "01K_TAXONOMY_NODE",
    "fulfillmentType": "physical",
    "trackInventory": true,
    "status": "active",
    "categoryIds": ["01K_CATEGORY"],
    "primaryCategoryId": "01K_CATEGORY",
    "translations": [
      {
        "locale": "en",
        "title": "Trail Runner",
        "slug": "trail-runner",
        "description": "A durable trail shoe",
        "seoTitle": "Trail Runner shoe",
        "lockIt": false
      }
    ]
  }
}
```

Updating `categoryIds` replaces the product's category assignments atomically.
Sending an empty list removes every assignment. Sending `brandId: null`,
`productTypeId: null`, or `platformTaxonomyNodeId: null` removes that
relationship. A Product Type from another Store is rejected. Deleting a
product cascades its translations and category/tag/collection/options/variant-
owned relationships according to the Catalog schema; it does not delete shared
categories, Brands, Product Types, or Platform taxonomy nodes.

### 6.1 Product REST lifecycle

The same Product service is available through Store-scoped REST:

| Method | Endpoint | Permission |
| --- | --- | --- |
| `GET` | `/api/v1/store/products` | Active Store membership |
| `POST` | `/api/v1/store/products` | `manage products` |
| `GET` | `/api/v1/store/products/{product}` | Active Store membership |
| `PATCH` | `/api/v1/store/products/{product}` | `manage products` |
| `DELETE` | `/api/v1/store/products/{product}` | `manage products` |

The collection accepts `page`, `per_page`, `search`, `locale`, `sku`,
`status`, `fulfillment_type`, `condition`, `is_featured`, `brand_id`, and
`category_id`. Sort fields are `created_at`, `updated_at`, `status`,
`published_at`, `price`, and `sort_order`; `sort_direction` is `asc` or `desc`.
General search matches SKU, UPC, GTIN, MPN, and localized title/slug.

REST write bodies use snake_case. Creation requires at least one complete
active Store-locale translation. Updates are partial, but each supplied
translation must include `locale`, `title`, and `slug`. Category assignment,
same-Store Brand/Product Type resolution, global taxonomy-node resolution,
publication timestamps, translation locks, and after-commit translation work
behave exactly like GraphQL.

The REST resource exposes the Product's commerce fields, including SKU and
external identifiers, prices, inventory thresholds, warranty, dimensions and
shipping cost, ratings/activity counters, purchase/visibility/search switches,
condition/preorder/release settings, quantities, tax class, related-product
count, points, and review enablement. Four-decimal values serialize as strings
to avoid floating-point loss. Flag columns serialize as `0` or `1` because the
database contract requested integer flags. Product and relationship IDs remain
public ULIDs.

### 6.2 Product image REST lifecycle

Product gallery metadata is exposed as a nested Store-scoped resource:

| Method | Endpoint | Permission |
| --- | --- | --- |
| `GET` | `/api/v1/store/products/{product}/images` | Active Store membership |
| `POST` | `/api/v1/store/products/{product}/images` | `manage products` |
| `GET` | `/api/v1/store/products/{product}/images/{image}` | Active Store membership |
| `PATCH` | `/api/v1/store/products/{product}/images/{image}` | `manage products` |
| `DELETE` | `/api/v1/store/products/{product}/images/{image}` | `manage products` |

The collection accepts the standard bounded `page` and `per_page` query
parameters and orders images by `position`, then creation identity. Create and
update bodies accept a root-relative or HTTP(S) `url`, nullable positive pixel
`width`/`height`, unsigned `position`, an optional same-product `variant_id`
public ULID, and optional Store-language `translations` containing `locale`,
nullable `alt_text`, and `lock_it`. Omitted update fields remain unchanged;
submitting `variant_id: null` removes the variant association. An image or
variant outside the selected Product and Store returns `404`.

These endpoints manage image metadata and locators only. They do not upload,
scan, transform, sign, or delete an underlying media object, and image alt text
does not currently trigger automatic translation. Translation rows are
upserted by locale, must use active Store languages, and preserve an existing
`lock_it` value when the field is omitted.

### 6.3 Reusable media REST lifecycle

The reusable media API is the binary/object workflow that complements the
legacy Product Image locator API:

| Method | Endpoint | Permission |
| --- | --- | --- |
| `POST` | `/api/v1/store/media/uploads` | `manage products` |
| `POST` | `/api/v1/store/media/{media}/complete` | `manage products` |
| `GET` | `/api/v1/store/media` | Active Store membership |
| `GET` | `/api/v1/store/media/{media}` | Active Store membership |
| `GET` | `/api/v1/store/media/{media}/content?variant=thumbnail` | Active Store membership |
| `DELETE` | `/api/v1/store/media/{media}` | `manage products` |
| `POST` | `/api/v1/store/media/ai/generate` | `manage products`; throttled 6/minute |
| `POST` | `/api/v1/store/media/{media}/ai` | `manage products`; throttled 10/minute |
| `GET` | `/api/v1/store/media/{media}/ai-results` | Active Store membership |
| `POST` | `/api/v1/store/products/{product}/media` | `manage products` |
| `DELETE` | `/api/v1/store/products/{product}/media/{media}` | `manage products` |
| `PUT` | `/api/v1/store/products/{product}/media/{media}/primary` | `manage products` |
| `POST` | `/api/v1/store/product-variants/{variant}/media` | `manage products` |
| `DELETE` | `/api/v1/store/product-variants/{variant}/media/{media}` | `manage products` |

Upload uses `multipart/form-data` with required `file`; optional fields are
`disk`, `visibility`, `alt_text`, `title`, `caption`, and JSON `metadata`.
Maximum size, allowed server-detected MIME types/extensions, and disks come from
`config/media-management.php`. A successful upload returns `201` and a
`pending` media resource. Completion returns `202`, changes the row to
`processing`, and idempotently queues metadata extraction, optimization,
derivative generation, and finalization. Clients poll detail until `ready` or
`failed`.

The list accepts `page`, `per_page`, `status`, `mime_type`, `source`
(`uploaded` or `ai_generated`), and `search` over original filename/title/alt
text. It excludes logically deleted rows. The
content route streams the original when `variant` is absent or one of
`original`, `thumbnail`, `small`, `medium`, or `large`; Store authentication is
always required.

AI generation accepts JSON with required `prompt` and optional `image_type`,
`style`, `aspect_ratio` (`1:1`, `4:5`, or `16:9`), `image_count` (1-4), and
`quality` (`low`, `medium`, or `high`). It returns `201` with generated private
Media resources in `data`; each has `metadata.source=ai_generated` and starts
normal asynchronous Media processing.

The per-media AI route accepts `{ "operation": "generate_alt_text" }`; other
allowed values are `generate_attributes`, `generate_tags`,
`generate_seo_filename`, `remove_background`, and `enhance_image`. Metadata
operations return `200` with `data` (the completed AI result), `media` (the
updated source), and `generated_media: null`. Image edits return `201` with the
new derived Media resource in `generated_media`. Only a `ready` image in the
selected Store is accepted. History returns the latest 50 AI results. Provider
failure messages are safe and never contain the API key, prompt, image bytes,
or provider response body.

Product attachment JSON is `{ "media_id": "<media-ulid>", "sort_order": 0,
"is_primary": false }`. Variant attachment accepts the same body but ignores
`is_primary`. Only `ready` media from the selected Store can attach. One media
asset may attach to several resources in that Store. Setting primary clears the
former primary atomically. Deleting media is logical and recoverable: Product
and Variant pivots are detached, the usage audit and physical objects remain,
and the next ordered Product asset is promoted when necessary.

Every lookup combines the selected Store's internal ID with the supplied public
ULID. Cross-Store identifiers therefore return `404`; missing membership or
permissions return `401`/`403`. Database composite foreign keys enforce the
same rule. The full architecture and operational safety notes are in
[Media management](media-management.md).

### 6.4 Reusable Product Modifier REST lifecycle

All modifier routes use the normal Store authentication envelope. Reads
require active membership; every write also requires `manage products`.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET`, `POST` | `/api/v1/store/modifier-library/categories` | List/create library categories |
| `GET`, `PATCH`, `DELETE` | `/api/v1/store/modifier-library/categories/{category}` | Read/edit/soft-delete a category |
| `GET`, `POST` | `/api/v1/store/modifier-library` | Filter/list or transactionally create definitions |
| `GET`, `PATCH`, `DELETE` | `/api/v1/store/modifier-library/{modifier}` | Read/edit/soft-delete one definition |
| `PATCH` | `/api/v1/store/modifier-library/{modifier}/active` | Activate/deactivate with `{ "is_active": true }` |
| `PUT` | `/api/v1/store/modifier-library/{modifier}/translations` | Replace library translations |
| `GET`, `POST`, `PUT` | `/api/v1/store/modifier-library/{modifier}/values` | List/create values or replace the complete value collection |
| `GET`, `PATCH`, `DELETE` | `/api/v1/store/modifier-library/{modifier}/values/{value}` | Read/edit/soft-delete one public-ULID value; PATCH may replace its translations/prices |
| `PUT` | `/api/v1/store/modifier-library/{modifier}/validation-rules` | Replace validation rules and messages |
| `PUT` | `/api/v1/store/modifier-library/{modifier}/price-adjustments` | Replace whole-modifier library prices |
| `GET`, `POST` | `/api/v1/store/products/{product}/modifier-groups` | List/create Product groups |
| `GET`, `PATCH`, `DELETE` | `/api/v1/store/products/{product}/modifier-groups/{group}` | Read/edit/soft-delete a group |
| `GET`, `POST` | `/api/v1/store/products/{product}/modifiers` | List/attach reusable definitions |
| `GET`, `PATCH`, `DELETE` | `/api/v1/store/products/{product}/modifiers/{assignment}` | Read/override/remove an assignment |
| `PUT` | `/api/v1/store/products/{product}/modifiers/{assignment}/translations` | Replace presentation overrides |
| `PUT` | `/api/v1/store/products/{product}/modifiers/{assignment}/value-assignments` | Replace enabled/default/value settings |
| `PUT` | `/api/v1/store/products/{product}/modifiers/{assignment}/price-overrides` | Replace Product modifier prices |
| `PUT` | `/api/v1/store/products/{product}/modifiers/{assignment}/value-price-overrides` | Replace Product value prices |
| `PATCH` | `/api/v1/store/products/{product}/modifiers/reorder` | Apply `{ "items": [{ "id": "<assignment-ulid>", "sort_order": 1 }] }` |
| `GET` | `/api/v1/store/products/{product}/modifiers/resolved?locale=en&currency=GBP` | Frontend-safe resolved configuration |

Library create/update bodies use snake_case and may atomically include
`translations`, `values` (each with translations and price adjustments),
`validation_rules`, and modifier `price_adjustments`. A supplied value `id` is
its public ULID; a value omitted from an explicitly supplied `values` array is
soft-deleted. Supported types are `select`, `radio`, `buttons`, `swatch`,
`checkbox`, `checkbox_group`, `text`, `textarea`, `number`, `date`, `datetime`,
`file`, `image_upload`, and `toggle`. Price rows use `fixed` or `percentage`, a
three-letter `currency_code`, four-decimal `amount`, optional date bounds, and
active state.

Assignment bodies use a library `modifier_id` ULID and may include `group_id`,
required/min/max/settings overrides, presentation `translations`,
`value_assignments`, `price_overrides`, and `value_price_overrides`. Updating a
definition or assignment with nested arrays replaces that nested collection in
one transaction. An absent nested key leaves it unchanged. Cross-Store
Products/modifiers/groups/media and values from the wrong definition return
`404` or validation errors; no internal bigint is accepted or returned.

The dedicated `PUT` routes accept an object whose single top-level property
matches the collection name (`translations`, `values`, `validation_rules`,
`price_adjustments`, `value_assignments`, `price_overrides`, or
`value_price_overrides`). They use full-replacement semantics. In particular,
omitting an existing modifier value from `values` soft-deletes it, while an
empty assignment translation/price/value collection removes all rows in that
collection. Each replacement is Store-scoped, validated, and committed in one
transaction; unrelated parent fields in the request are ignored.

Individual modifier-value `POST`/`PATCH` bodies use the same value shape as the
aggregate `values` collection: `code`, ordering/default/active/presentation
fields, Store media `image_id`, settings, `translations`, and nested
`price_adjustments`. Create requires `code` and at least one active-Store
translation. PATCH changes only supplied fields; supplying translations or
prices replaces that value's corresponding collection. The `{value}` ULID is
always resolved beneath both the active Store and parent `{modifier}`.

The resolved response uses assignment/value ULIDs and camelCase frontend
fields. It applies Product requested-locale translations before library
requested/default translations, filters disabled values, applies default and
required overrides, and returns server-calculated price objects. Clients must
never submit those displayed amounts as authoritative cart prices.

The requested `locale` must be one of the active Store languages. The resolved
response includes `meta.language` and `meta.available_languages`; each language
descriptor contains its public ULID, locale, administrative/native names,
render-ready `lang_image` and `lang_icon` flag URLs, `ltr`/`rtl` direction, and
default flag. This is the same language presentation contract exposed by
`GET /api/v1/store/languages`, so admin translation tabs should key labels,
help text, required errors, validation errors, and validation-rule messages by
`locale` and render the matching language flag.

Server-side modifier selection errors resolve from the requested locale. A
localized `required_message` is used for missing required selections;
rule-specific localized messages take precedence for their rules; the
localized `validation_message` is the fallback for selection shape, type,
availability, file/media, and otherwise generic validation failures. Store
default-language copy and safe English internal messages remain the final
fallbacks when translated copy is absent.

There are no installed Cart, Orders, Sales Channel, or Customer Group APIs.
Consequently modifier cart validation and immutable checkout snapshots are
available as Catalog integration services but are not exposed as HTTP routes.
The nullable audience columns are also not writable through these APIs until
those modules expose Store-scoped public ULIDs.

## 7. Reading localized data

Every Category, Product Type, and Product returns:

- `translations`: all saved manual or generated locale rows, ordered by locale.
- `translation(locale: "...")`: one exact locale using hyphen/underscore and case-insensitive normalization.

Example list query:

```graphql
query Products($filter: ProductFilterInput!) {
  products(filter: $filter, page: 1, perPage: 25, sortBy: CREATED_AT, sortDirection: DESC) {
    data {
      id
      status
      translation(locale: "de") { title slug description }
      categories { id translation(locale: "de") { title } }
    }
    paginatorInfo { count currentPage lastPage perPage total }
  }
}
```

```json
{
  "filter": {
    "search": "Laufschuh",
    "locale": "de",
    "status": "active",
    "categoryId": "01K_CATEGORY"
  }
}
```

Only Store languages that are both selected and active may be written. Locale tags normalize `en-US` and `en_US` for matching while the saved Store language spelling is retained.

## 8. Manual and automatic translation

The first/default active Store language is the preferred source. If it is not yet saved, the first supplied translation becomes the source. Category automation translates title, description, SEO title/description, page title, and search keywords. Product Type automation translates name and description. Product automation translates title, description, and SEO title/description. Slugs are generated from translated titles or Product Type names and made unique per Store/locale. Category image/banner URLs and category template keys are manual metadata and are not sent for language translation.

The write cycle is:

1. Validate that every supplied locale is active for the selected Store.
2. Save source/manual rows in the same transaction as the entity.
3. Preserve an existing `lockIt` value when the input omits it.
4. Create a deduplicated `translation_requests` ledger row if unlocked target locales exist.
5. Commit the entity and request.
6. Dispatch translation work after commit.
7. Recheck source hashes and target locks before applying provider output.

`lockIt: true` means a merchant owns that locale and automation must never overwrite it. Send the same complete locale with `lockIt: false` to opt it back into later automation.

`translationRequest` is nullable because no job is needed when there are no other target languages, all targets are locked, or all missing-only targets already exist. In Redis-backed environments it is eventually consistent. Poll:

```http
GET /api/v1/store/translation-requests/<translation-request-ulid>
Authorization: Bearer <store-bound-token>
X-Store-ID: <store-public-ulid>
```

Statuses are `pending`, `processing`, `completed`, `failed`, `superseded`, or `cancelled`. A failed provider call never rolls back valid source content. A changed source supersedes stale work; deleted content cancels it.

## 9. Errors and client behavior

GraphQL execution/validation failures normally return HTTP 200 with an `errors` array and may include partial `data`. HTTP middleware failures, such as missing authentication during Store resolution, may return 401/403 JSON before GraphQL execution.

Clients should:

1. Treat the presence of `errors` as failure for the affected operation.
2. Log the response `X-Request-ID` for support correlation.
3. Show field validation messages without exposing traces.
4. Treat missing/cross-Store ULIDs as not found rather than trying another internal ID.
5. Retry only transport failures and explicitly retryable jobs; do not blindly replay mutations.

Common failures include missing `X-Store-ID`, inactive membership, token/Store mismatch, missing `manage products`, inactive translation locale, duplicate localized slug, invalid Product Type code, unknown taxonomy-node ULID, invalid category tree, and a primary category absent from `categoryIds`.

Store Admin route models are bound only after Store resolution and membership.
A ULID owned by another Store returns 404 for reads, updates, and deletes, and
model-level guards independently reject cross-Store save/delete attempts.
Store and GraphQL HTTP requests cannot execute schema SQL; an attempted
`CREATE`, `ALTER`, `DROP`, `TRUNCATE`, grant, or equivalent command is rejected
with 403. AI media and translation endpoints expose fixed operations only and
never pass database or deletion tools to the provider.

## 10. Adding another application or Catalog entity

An external application should bootstrap in this order:

1. Authenticate and obtain the user's allowed Store/interface data.
2. Select one Store public ULID.
3. Send that ULID on every Store-scoped call.
4. Load active Store languages before rendering translation editors.
5. Read the generated operation reference and executable SDL.
6. Store ShopNXE public ULIDs, never internal joins.
7. Poll returned translation requests when localized content must be immediately visible.

When developers add another translatable entity, they must add the Store-scoped model/service, explicit GraphQL resolver and SDL, translation handler and registry tag, `lock_it` migration contract, PostgreSQL feature tests, this manual's behavioral explanation, the affected module/context guide, and a development-log entry.

## 11. Keeping this manual current

After application, dependency, route, GraphQL, migration, module, configuration, or workflow changes:

```powershell
composer docs:update
composer docs:check
composer format:check
composer analyse
php artisan test
```

`docs:update` regenerates the system inventory and GraphQL operation reference. Composer also invokes it after autoload dumps. CI runs `docs:check`, so changed executable operations cannot be merged with a stale generated reference. Business intent and examples cannot be inferred safely; developers must update this manual, the project context/developer guide, and `docs/development-log.md` in the same change.
