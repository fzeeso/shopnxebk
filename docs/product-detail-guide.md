# Product Detail Store Admin guide

This guide explains how a Store Admin client should create, edit, and save a
Product through the Product Detail façade. ShopNXE is API-only, so "screen",
"tab", and "form" below describe behavior expected from a separate Store Admin
frontend.

## What the Product Detail façade solves

A Product editor needs Product fields plus Brands, Categories, Product Types,
images, media, Custom Fields, Options, Variants, shared Options, and Modifiers.
Calling every granular endpoint from the browser creates unnecessary network
round trips and makes partial failure difficult to manage. Product Detail gives
the client one composition contract while the backend continues to delegate to
the service that owns each domain rule.

The façade provides:

- one bootstrap read for a new-Product form;
- one composed read for an existing Product;
- one transactional create command;
- one dirty-section update command;
- optional selective reads for lightweight tabs and summaries;
- optimistic conflict detection; and
- request-local references for dependent records created together.

It does not turn all Product work into one SQL query. It removes client-to-
server request fan-out and skips unrequested backend sections when a selective
read is used.

## Requirements

Every request requires:

- an authenticated Store-scoped user;
- `Accept: application/json`;
- the public Store ULID in `X-Store-ID`; and
- active membership in that Store.

Reads require membership. Creates and updates additionally require
`manage products`. Every Product and relationship identifier sent to or
returned by the client is a public ULID; internal bigint identifiers are never
part of the contract.

## Endpoints

| Method | Endpoint | Use |
| --- | --- | --- |
| `GET` | `/api/v1/store/product-detail` | Bootstrap a new-Product editor |
| `GET` | `/api/v1/store/product-detail/{product}` | Load an existing Product editor |
| `POST` | `/api/v1/store/product-detail` | Create Product core and supplied sections |
| `PATCH` | `/api/v1/store/product-detail/{product}` | Save supplied dirty fields and sections |

Existing granular Product, image, media, option, variant, Custom Field, shared-
option, and Modifier endpoints remain supported. Use those for paginated
continuation, single-record tools, integrations, or workflows that do not need
the editor aggregate.

## Recommended new-Product workflow

### 1. Bootstrap the form

```http
GET /api/v1/store/product-detail
Authorization: Bearer <token>
X-Store-ID: <store-ulid>
Accept: application/json
```

The response has `product: null`, empty built-in sections, registered extension
sections, selector `reference_data`, section metadata, and capabilities.
Reference data contains bounded active Brands, Categories, Product Types,
Platform taxonomy nodes, fulfillment types, Custom Fields, shared Options,
Modifiers, Store languages, currencies, and Store defaults.

Cache bootstrap reference data for the current Store. Invalidate it when the
active Store changes or after the client changes one of those selector
collections.

### 2. Upload binary files separately

Binary upload is deliberately outside the Product transaction. Upload or
complete media first through the media API, then put the resulting media public
ULID in the Product Detail `media` section. Image metadata with an existing URL
can be included directly, but Product Detail does not transfer binary content.

### 3. Submit one create command

```json
{
  "product": {
    "status": "draft",
    "translations": [
      {"locale": "en", "title": "Runner", "slug": "runner"}
    ]
  },
  "sections": {
    "options": {
      "upsert": [
        {
          "ref": "size",
          "translations": [{"locale": "en", "name": "Size"}],
          "values": [
            {
              "ref": "size-small",
              "translations": [{"locale": "en", "value": "Small"}]
            }
          ]
        }
      ]
    },
    "variants": {
      "upsert": [
        {
          "ref": "small-variant",
          "price_amount_minor": 2499,
          "currency_code": "USD",
          "option_value_ids": ["@size-small"]
        }
      ]
    }
  }
}
```

The backend creates Product core first, processes dependent Catalog sections in
a stable order, runs registered extension providers, and commits or rolls back
the complete command. The `references` response maps `size`, `size-small`, and
`small-variant` to their generated public ULIDs.

## Recommended existing-Product workflow

### Full editor load

Omit `sections` when the screen needs the complete Product editor:

```http
GET /api/v1/store/product-detail/<product-ulid>
```

The response includes Product core and revision, every built-in section, every
registered extension section, per-section metadata, selector reference data,
and capabilities.

### Selective tab or summary load

Use a comma-separated manifest when the screen needs only some sections:

```http
GET /api/v1/store/product-detail/<product-ulid>?sections=product,images,options&with_reference_data=false
```

Rules for `sections`:

- keys use lowercase snake_case;
- keys must be distinct;
- accepted keys are `product`, Catalog built-ins, and currently registered
  extension-provider keys;
- an unknown or duplicate key returns `422`; and
- omission means all sections.

Product core and `revision` remain present for existing Products even when the
manifest contains only section keys. `product` is accepted as an explicit
marker so a frontend can keep one complete screen manifest. Only named Catalog
section queries and extension providers execute. `section_meta` covers only
loaded sections, while `capabilities.writable_sections` still advertises the
complete write contract.

Suggested manifests:

| Screen | Suggested query |
| --- | --- |
| Product summary | `sections=product&with_reference_data=false` |
| Media tab | `sections=product,images,media&with_reference_data=false` |
| Variant tab | `sections=product,options,variants&with_reference_data=false` |
| Customization tab | `sections=product,custom_fields,custom_objects,modifier_groups,modifiers&with_reference_data=false` |
| Full editor | Omit `sections`; include reference data as needed |

### Save only dirty data

Track dirty Product fields and section names in the frontend. Send only those
values in `PATCH`; omitted sections are not read as empty and are never changed.

```json
{
  "expected_updated_at": "2026-08-28T10:30:00Z",
  "product": {
    "status": "active"
  },
  "sections": {
    "images": {
      "delete": ["<image-ulid>"]
    }
  }
}
```

On success, replace local data with the returned aggregate, store the new
`revision`, clear only the acknowledged `saved_sections`, and retain any
unsubmitted dirty state.

## Built-in sections

| Section | Information | Main command shape |
| --- | --- | --- |
| `images` | Gallery metadata and localized alt text | `upsert`, `delete` |
| `media` | Reusable Product/Variant media attachments | `attach`, `detach`, `variant_attach`, `variant_detach`, `primary_media_id` |
| `custom_fields` | Product- and Variant-scoped typed values | `upsert`, `delete` |
| `custom_objects` | Ordered references to reusable multilingual Custom Object entries | `replace`, `clear` |
| `options` | Option dimensions and Values | `upsert`, `delete`, `value_upsert`, `value_delete` |
| `variants` | Sellable combinations and prices | `upsert`, `delete` |
| `shared_options` | Store library Option assignments | `upsert`, `delete` |
| `modifier_groups` | Product grouping for Modifiers | `upsert`, `delete` |
| `modifiers` | Reusable Modifier assignments/overrides | `upsert`, `delete` |

New modules may add more keys. Do not hard-code this table as the complete
future list; use `capabilities.writable_sections` to discover the active
contract and release UI support intentionally.

Each `custom_objects.replace` item supplies a Custom Field `definition_id` and
the complete ordered `entry_ids` list for that definition. `clear` is a list of
definition ULIDs. Selector searches use the paginated Custom Object options API
because bounded Product Detail reference data is not guaranteed to contain
every entry. See [Custom Objects](custom-objects.md).

## Request-local references

A create item may include `ref`, using 1–100 letters, numbers, dots, underscores,
or hyphens. Later work in the same command addresses it with `@ref`.

Use references when a Variant depends on a new Option Value, or when an image,
Custom Field, media attachment, or extension section depends on a new Variant.
References exist only during that request. Persist the public ULIDs returned in
`references`; never send an old `@ref` in a later request.

## Conflict handling

The existing-Product response contains `revision`, sourced from Product
`updated_at`. Send it as `expected_updated_at` on `PATCH`.

If another writer changed the Product after the editor loaded it, the API
returns `409 Conflict`. The frontend should:

1. retain the user's unsaved changes;
2. reload the required Product Detail sections;
3. show that server data changed;
4. let the user reapply or merge their changes; and
5. retry with the new revision.

Omitting `expected_updated_at` opts into last-write-wins behavior and should be
an intentional integration choice, not the default Store Admin behavior.

## Limits and continuation

`section_limit` defaults to 100 and is capped at 250. `reference_limit`
defaults to 250 and is capped at 500. Each loaded section reports:

```json
{
  "total": 420,
  "returned": 100,
  "limit": 100,
  "truncated": true
}
```

When `truncated` is true, continue that collection through its existing
granular paginated endpoint. Do not interpret a truncated aggregate section as
the complete saved collection, and never submit it as a replacement unless the
section contract explicitly requests that behavior.

## Common responses

| Status | Meaning | Client action |
| --- | --- | --- |
| `200` | Detail read or update succeeded | Replace acknowledged state and revision |
| `201` | Product aggregate created | Navigate using returned Product public ULID |
| `401` | Authentication missing or invalid | Re-authenticate |
| `403` | Membership or `manage products` missing | Hide write controls or request access |
| `404` | Product/relationship is outside the selected Store or absent | Do not reveal cross-Store existence |
| `409` | `expected_updated_at` is stale | Reload and merge |
| `422` | Invalid fields, section manifest, references, or domain rule | Map validation messages to the form |

## Performance checklist

- Bootstrap once per active Store and cache selector data.
- Use `with_reference_data=false` after reference data is cached.
- Use `sections` for tab, summary, and background-refresh requests.
- Omit `sections` only when the screen really needs the complete aggregate.
- Save only dirty Product fields and dirty sections.
- Upload binary media before the Product Detail save.
- Continue truncated collections through granular pagination.
- Do not replace the façade with browser fan-out to every granular endpoint.
- In AWS staging, establish an uncached baseline with the read-only k6 harness,
  then enable Store lookup and reference caching one flag at a time. Reference
  caching does not cache Product sections or authorization.
- Product API rate limits are opt-in and keyed by selected Store plus user/IP;
  clients must handle `429` with bounded retry/backoff when they are enabled.
- Deployment sizing, rollback flags, and load-test gates are recorded in the
  [AWS scaling and deployment decision guide](aws-scaling-deployment-guide.md).

## Security and ownership

Product Detail is a composition boundary, not a table exposure mechanism.
Every lookup remains bound to the active Store, every provider must delegate to
its owning service, and resources expose only public data. Adding a table does
not add an API section. A future section appears only after its owning module
explicitly registers a provider and its public contract has been reviewed.

See the [API manual](api-manual.md) for exact integration contracts, the
[developer guide](developer-guide.md) for implementation details, and the
[section-provider contract](module-communication/product-detail-section-providers.md)
for adding a future module.
