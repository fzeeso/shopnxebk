# Theme marketplace and Store themes

ShopNXE separates the global Theme marketplace from the mutable copy installed
for one Store. Marketplace identity, pricing, releases, review, and licensing
belong to `Modules/Themes`; merchant layout/settings/CSS belong only to an
installed `store_themes` row.

## Responsibility model

| Record | Responsibility |
| --- | --- |
| `theme_publishers` | Platform or third-party publisher identity, support ownership, lifecycle, verification, commission default, and opaque payout-provider reference. |
| `theme_categories` | Hierarchical industry taxonomy plus style, feature, and catalog-size facets. |
| `theme_category_assignments` | Theme-to-category relationship with one optional primary category per Theme. |
| `themes` | Marketplace product/listing identity, source, visibility, commercial model, price, support links, and current published version. |
| `theme_versions` | Immutable version metadata, quarantine/compiled object keys, package hash/size limits, manifest, settings schema, validation report, and approval/publication state. |
| `theme_submissions` | Numbered review attempts and automated/manual decision evidence for one version. |
| `theme_licenses` | A Store's current right to use one Theme, including trial/free/paid/custom/complimentary lifecycle. |
| `store_themes` | A licensed Store installation with mutable settings, template data, CSS, revision, ancestry, and draft/published state. |

`theme_sales` is deliberately deferred until provider-backed orders,
commissions, refunds, and publisher payouts are implemented. The current
`billing_order_item_id` is a nullable integration reference, not a sales
ledger.

Every addressable record has a bigint internal primary key and a public ULID.
The relationship-only `theme_category_assignments` table uses its composite
internal key. REST requests and responses use only public ULIDs.

## Database invariants

- Platform/third-party Themes require a publisher and cannot have an owner
  Store. Custom Themes require an owner Store, no publisher, private visibility,
  and the private commercial type.
- Paid Themes require a non-null minor-unit amount and currency; free/private
  Themes prohibit both price fields.
- A Theme has no more than one primary category.
- A Theme/version semantic-version pair is unique, and version records are
  never edited in place.
- A Store has no more than one current trial/active license for a Theme.
- A Store has no more than one non-deleted `published` installed Theme.
- Publishing another installed Theme archives the former published copy inside
  the same transaction.
- Store customization writes require the caller's current
  `customization_revision`; a stale revision is rejected instead of silently
  overwriting another editor.

## Lifecycle

The marketplace listing lifecycle is `draft`, `pending_review`,
`approved`, `published`, `suspended`, `rejected`, or `retired`.
Versions move through `uploaded`, scanning/validation states,
`ready_for_review`, `approved`, `published`, `deprecated`, or
`blocked`. A submission records each review attempt as submitted, automated
or manual review, changes requested, approved, rejected, or withdrawn.

Release execution is:

1. Register a new immutable version with quarantine object key, SHA-256, size
   limits, manifest, settings schema, and validation report.
2. Submit the version, creating the next numbered submission.
3. Record an approve, changes-requested, or reject decision.
4. Publish only an approved version; this updates the Theme's current-version
   pointer and publication timestamps without mutating an older release.

## Platform API

All Platform routes require Sanctum authentication, `platform` scope, and
`manage marketplace`. They never accept `X-Store-ID`.

| Method | Route | Purpose |
| --- | --- | --- |
| `GET/POST` | `/api/v1/platform/themes` | Paginate/filter or create listings. |
| `GET/PATCH` | `/api/v1/platform/themes/{theme}` | View/update a listing by public ULID. |
| `GET/POST` | `/api/v1/platform/theme-publishers` | Paginate or create publishers. |
| `PATCH` | `/api/v1/platform/theme-publishers/{publisher}` | Update publisher metadata/lifecycle. |
| `GET/POST` | `/api/v1/platform/theme-categories` | Paginate or create taxonomy/facet rows. |
| `PATCH` | `/api/v1/platform/theme-categories/{category}` | Update a category. |
| `POST` | `/api/v1/platform/themes/{theme}/versions` | Register an immutable version. |
| `POST` | `/api/v1/platform/theme-versions/{version}/submit` | Create the next review submission. |
| `PATCH` | `/api/v1/platform/theme-submissions/{submission}/review` | Decide a review. |
| `POST` | `/api/v1/platform/theme-versions/{version}/publish` | Publish an approved version. |
| `POST` | `/api/v1/platform/themes/{theme}/licenses` | Issue a Store license. |
| `PATCH` | `/api/v1/platform/theme-licenses/{license}` | Revoke/update a license lifecycle. |

Theme, publisher, and category list routes use Laravel `data`, `links`, and
`meta` pagination with a maximum `per_page` of 100.

## Store API

Store routes require Sanctum authentication, Store scope, a resolved
`X-Store-ID`, active `store_users` membership, and `manage themes`.

| Method | Route | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/store/theme-marketplace` | List installable published public/unlisted Themes for the selected Store. |
| `GET` | `/api/v1/store/themes` | List the selected Store's installed/customized copies. |
| `POST` | `/api/v1/store/themes` | Validate license/current version and create a draft installation. |
| `PATCH` | `/api/v1/store/themes/{storeTheme}` | Update name/settings/template/CSS with optimistic revision control. |
| `POST` | `/api/v1/store/themes/{storeTheme}/duplicate` | Create a draft derived from an installed copy. |
| `POST` | `/api/v1/store/themes/{storeTheme}/publish` | Publish the selected copy and archive the previous published copy. |
| `DELETE` | `/api/v1/store/themes/{storeTheme}` | Soft-delete a non-published draft/archive. |

Free Themes receive a free license during installation when needed. Paid Themes
must already have an active/trial license issued by the Platform workflow.
Custom owner Themes use a custom-owner license.

## Store provisioning and upgrades

`ProvisionStore` calls the `ThemeInstaller` contract during the same
transaction that creates the draft Store, settings, domain, Owner relationship,
and role. `DefaultThemeInstaller` ensures the selected platform catalog
Theme/version exists, issues the correct license, and creates the initial
`published` Store copy. Failure rolls back the entire Store setup.

The replacement migration intentionally drops the former simple
`store_themes(name, template_key, is_active, settings)` table. A follow-up
backfill creates the bundled default marketplace Theme/version and gives each
existing Store a free license plus published installed copy.

## Artifact and media safety

API metadata registration is not an upload or execution boundary. Source
archives remain in quarantine and are identified by object key plus SHA-256.
Artifact workers must enforce compressed/uncompressed size, file-count,
extension/path, manifest/schema, malware, and executable-code policies before
writing a compiled artifact key or validation result. A theme package must
never execute arbitrary PHP, Node.js, React, Next.js, build scripts, or server
code inside the Laravel or admin process.

Theme listing media is Platform-owned and therefore may have `media.store_id =
null`; Store-owned media keeps Store context and Store-scoped paths. Global
media paths use `platform/media/{media-public-ulid}`.

See [Themes module](modules/themes.md), [Stores to Themes](module-communication/stores-to-themes.md),
[Themes to Stores](module-communication/themes-to-stores.md), [Themes to
Authentication](module-communication/themes-to-authentication.md), [Themes to
Settings](module-communication/themes-to-settings.md), and [Themes to
Files](module-communication/themes-to-files.md).
