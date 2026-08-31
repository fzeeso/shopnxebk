# Custom Objects (metaobjects)

This document is the implementation and API reference for ShopNXE's
multi-language, Store-owned Custom Object system. Custom Objects are reusable,
merchant-defined records such as Designer, Size Guide, Care Guide,
Manufacturer, Author, Certification, or Ingredient. They complement Custom
Fields; they do not replace Products, Variants, Categories, Collections,
Brands, Pages, or other core commerce entities.

## ShopNXE structure

The supplied generic schema was converted to the repository's established
Catalog conventions:

| Generic proposal | ShopNXE implementation |
| --- | --- |
| UUID/public ID | Internal bigint `id` plus public ULID `public_id` |
| `store_language_id` on translations | `store_id` plus normalized `locale`, validated against active `store_languages` through `LocalizedTranslationWriter` |
| Generic tenant lookup | `StoreScoped`, selected `StoreContext`, Store middleware, and composite Store foreign keys |
| `custom_field_id` | Existing `custom_field_definitions.id`, named `custom_field_definition_id` in reference rows |
| Translated names in core rows | Separate translation rows; handles and structural settings remain language-neutral |
| Raw JSON object IDs | Relational `custom_object_references` and `custom_object_value_references` rows |
| UUID API parameters | Public ULIDs only; internal bigint IDs are never serialized |
| Generic authorization | Active Store membership for reads and existing `manage products` permission for writes |

`store_languages` remains the Store-to-Settings-language selection pivot. It
is not duplicated and is not treated as the permanent identity of translated
content. Locale rows follow the same convention as the existing Catalog
translations and keep `lock_it` for safe future automatic translation.

## Owned tables

| Table | Purpose |
| --- | --- |
| `custom_object_types` | Store-owned type definition, stable handle, lifecycle, system/audit state |
| `custom_object_type_translations` | Localized type name and description |
| `custom_object_fields` | Dynamic field schema, validation/settings, localization and reference configuration |
| `custom_object_field_translations` | Localized label, description, help text, and placeholder |
| `custom_object_entries` | Reusable records for one type |
| `custom_object_entry_translations` | Localized entry name and description |
| `custom_object_values` | One typed, non-localized base value per entry/field or the owner row for localized/reference values |
| `custom_object_value_translations` | Localized text or JSON selection value |
| `custom_object_value_references` | Ordered relational references from one Custom Object value to other entries |
| `custom_object_references` | Ordered polymorphic references from Products, Collections, Categories, Brands, or Pages to entries through an existing Custom Field |

The migration also adds nullable `reference_object_type_id` to
`custom_field_definitions` and extends its allowed types with
`object_reference` and `multi_object_reference`. PostgreSQL checks require a
reference type exactly when either reference field type is selected.

All addressable type, field, entry, value, and reference rows have public
ULIDs. Translation and value-reference junction rows use composite natural
keys. Store-matching composite foreign keys protect all relationships that can
be represented relationally; the polymorphic source is resolved and checked
inside the selected Store by `CustomObjectReferenceService`.

## Request boundary

Every endpoint uses the standard Store API envelope:

```http
Authorization: Bearer <store-bound-token>
X-Store-ID: <store-public-ulid>
Accept: application/json
Content-Type: application/json
```

Reads require active Store membership. Mutations require `manage products`.
The API accepts and returns public ULIDs. Cross-Store type, field, entry,
media, source, and reference IDs resolve as not found or validation errors.

Localized reads resolve content in this order:

1. `?locale=<locale>` when present;
2. the first locale from `Accept-Language`;
3. the Store's active default language;
4. the first available translation.

Reads never create a missing translation. Admin resources include all saved
`translations` plus flattened `name`, `description`, `label`, or value fields
for the resolved locale and a `resolved_locale` marker.

## API index

### Type definitions

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/store/custom-object-types` | Paginated/searchable type list |
| `POST` | `/api/v1/store/custom-object-types` | Create a type, translations, and optional initial fields |
| `GET` | `/api/v1/store/custom-object-types/{type}` | Read one complete type schema |
| `PATCH` | `/api/v1/store/custom-object-types/{type}` | Update handle, lifecycle, or submitted translations |
| `DELETE` | `/api/v1/store/custom-object-types/{type}` | Soft-delete an unreferenced, non-system type |

List query parameters are `page`, `per_page` (maximum 100), `search`,
`status`, and `locale`. Type and entry statuses are `draft`, `active`, or
`archived`. Handles use lowercase ASCII letters, numbers, and hyphens and are
not translated.

```json
{
  "handle": "designer",
  "status": "active",
  "translations": [
    {
      "locale": "en",
      "name": "Designer",
      "description": "Reusable designer information."
    },
    {
      "locale": "ar",
      "name": "المصمم",
      "description": "معلومات المصمم"
    }
  ]
}
```

Only a Platform Super Admin may set `is_system`. System types cannot be
deleted through the Store service.

### Field schema

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET`, `POST` | `/api/v1/store/custom-object-types/{type}/fields` | List or add fields |
| `GET`, `PATCH`, `DELETE` | `/api/v1/store/custom-object-fields/{field}` | Read, update, or soft-delete a field |

Supported field types are:

`text`, `textarea`, `rich_text`, `number`, `decimal`, `boolean`, `date`,
`datetime`, `url`, `email`, `media`, `image`, `select`, `multi_select`,
`object_reference`, and `multi_object_reference`.

Only text, textarea, rich text, URL, email, select, and multi-select fields may
set `is_localized`. Multi-value fields cannot set `is_unique`. Reference fields
must send the public `reference_object_type_id`; other field types must not.
Required fields cannot be added after entries exist unless a separate
backfill/default workflow has populated them.

```json
{
  "handle": "biography",
  "field_type": "rich_text",
  "is_required": false,
  "is_unique": false,
  "is_localized": true,
  "is_searchable": true,
  "is_filterable": false,
  "sort_order": 20,
  "validation_rules": {"max_length": 20000},
  "translations": [
    {"locale": "en", "label": "Biography", "help_text": "Public biography"},
    {"locale": "ar", "label": "السيرة الذاتية"}
  ]
}
```

Select choices are stable strings in `settings.options`; localized selection
values are saved per locale. Media/image values resolve an existing Store
Media public ULID and store the internal composite foreign key.

### Entries and selector options

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET`, `POST` | `/api/v1/store/custom-object-types/{type}/entries` | Search/list or create entries |
| `GET` | `/api/v1/store/custom-object-types/{type}/entries/options` | Active, paginated, locale-resolved selector payload |
| `GET`, `PATCH`, `DELETE` | `/api/v1/store/custom-object-entries/{entry}` | Read, update, or soft-delete an entry |

The entry list accepts the same pagination, search, status, and locale
parameters. Search covers handle and translated name. The options endpoint
forces `status=active` and returns compact `id`, `handle`, `name`,
`description`, `resolved_locale`, and `status` records.

An entry create or patch may include `values`. Each value names a field with
its public `field_id` and supplies exactly one property determined by the
field schema:

| Field type | Value property |
| --- | --- |
| non-localized text/textarea/rich text/URL/email | `value_text` |
| number/decimal | `value_number` |
| boolean | `value_boolean` |
| date | `value_date` (`YYYY-MM-DD`) |
| datetime | `value_datetime` |
| media/image | `media_id` public ULID |
| select/multi-select | `value_json` string list (select has exactly one item) |
| object/multi-object reference | ordered `entry_ids` public ULID list |
| localized text or select types | `translations` with `locale` and `value_text` or `value_json` |

```json
{
  "handle": "nike",
  "status": "active",
  "translations": [
    {"locale": "en", "name": "Nike", "description": "Global sportswear brand."},
    {"locale": "ar", "name": "نايكي"}
  ],
  "values": [
    {
      "field_id": "01K...BIOGRAPHY",
      "translations": [
        {"locale": "en", "value_text": "Global sportswear brand."},
        {"locale": "ar", "value_text": "شركة ملابس رياضية عالمية."}
      ]
    },
    {"field_id": "01K...WEBSITE", "value_text": "https://www.nike.com"},
    {"field_id": "01K...PHOTO", "media_id": "01K...MEDIA"}
  ]
}
```

On PATCH, omitted values remain unchanged. Send
`{"field_id":"01K...","delete":true}` to clear an optional value. Required
value clearing is rejected. Field settings and validation rules are applied by
the service before persistence. Unique values are checked inside the entry
transaction.

### Existing Custom Field integration

Create a normal Custom Field using one of the new relational types:

```json
{
  "field_key": "designer",
  "field_type": "object_reference",
  "reference_object_type_id": "01K...DESIGNER_TYPE",
  "translations": [{"locale": "en", "label": "Designer"}]
}
```

Do not call the primitive Product/Variant custom-field value endpoint for this
definition. It deliberately returns validation guidance. Object assignments
use the reference endpoints below so referential integrity, ordering,
filtering, and deletion guards remain available.

### Polymorphic references

Supported `source_type` values are `product`, `collection`, `category`,
`brand`, and `page`. A Custom Field with null `product_type` may be used by any
supported source. A Product-type-scoped field is accepted only for a matching
Product.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/store/custom-object-references?source_type=product&source_id={source}` | Read a source's assignments; optional `definition_id` filter |
| `PUT` | `/api/v1/store/custom-object-references/{definition}` | Replace one field's ordered entries; body includes source and `entry_ids` |
| `DELETE` | `/api/v1/store/custom-object-references/{definition}` | Clear one field; query/body identifies source |
| `GET` | `/api/v1/store/products/{product}/custom-object-references` | Product convenience read |
| `PUT` | `/api/v1/store/products/{product}/custom-object-references/{definition}` | Product convenience replace |
| `DELETE` | `/api/v1/store/products/{product}/custom-object-references/{definition}` | Product convenience clear |

Generic replace example:

```json
{
  "source_type": "product",
  "source_id": "01K...PRODUCT",
  "entry_ids": ["01K...NIKE", "01K...ADIDAS"]
}
```

`object_reference` accepts zero or one entry; `multi_object_reference` accepts
up to 100 distinct entries. Replace preserves submitted order. New selections
must be active entries of the configured type. Existing references continue
to resolve archived entries, allowing an entry to be archived without
silently changing Products.

## Product Detail façade

`custom_objects` is a built-in Product Detail section. Product reads return
the resolved references and bootstrap reference data includes active Custom
Object Types. Selectors should use the paginated options endpoint rather than
assuming the bounded bootstrap set is complete.

```json
{
  "expected_updated_at": "2026-08-31T12:00:00Z",
  "sections": {
    "custom_objects": {
      "replace": [
        {
          "definition_id": "01K...DESIGNER_FIELD",
          "entry_ids": ["01K...NIKE"]
        }
      ],
      "clear": ["01K...CARE_GUIDE_FIELD"]
    }
  }
}
```

The Product Detail writer calls `CustomObjectReferenceService` inside its
existing outer transaction. It does not make an HTTP loopback request and does
not trigger synchronous search, AI, or other remote work.

## Deletion and lifecycle behavior

- Type, field, and entry DELETE operations are soft deletes.
- Referenced types and entries cannot be deleted; archive them instead.
- A field with saved values cannot be deleted; archive it instead.
- A Custom Field with active object assignments cannot be deleted until the
  assignments are cleared.
- Removing a translation does not remove the underlying object or reference.
- Handles, statuses, IDs, ordering, structural settings, and reference IDs are
  never translated.
- No read path silently creates translations.

## Implementation references

- Migrations:
  `Modules/Catalog/database/migrations/2026_08_31_020000_create_custom_object_tables.php`
  and
  `2026_08_31_020100_add_custom_object_references_to_custom_fields.php`.
- Public type lifecycle service: `CustomObjectTypeService`.
- Public field schema service: `CustomObjectFieldService`, including atomic
  complete-order replacement through `reorderFields()`.
- Public entry lifecycle/value service: `CustomObjectEntryService`.
- Shared aggregate implementation: `CustomObjectManagementService`; retained
  behind the three narrower services for one validation/transaction boundary.
- Typed value service: `CustomObjectValueService`.
- Polymorphic assignment service: `CustomObjectReferenceService`.
- Locale fallback: `CustomObjectTranslationResolver` plus the existing
  `LocalizedTranslationWriter` and `StoreTranslationLanguages`.
- Routes: `routes/custom-object-api.php`.
- Product composition: `ProductDetailReadService`,
  `ProductDetailWriteService`, and `ProductDetailResource`.

## Migration rollout

The two migrations are ordered and reversible. The first creates only new
tables and was applied to the local database in batch 43 after its ten targets
were confirmed absent. The second intentionally alters the existing
`custom_field_definitions` check constraint and adds a nullable reference
column/foreign key. It remains pending because the repository's Codex database
safety policy permits additive table creation but forbids changes to existing
tables. Reference-backed Custom Field writes require that companion migration
to be applied through the normal reviewed database rollout outside Codex.
