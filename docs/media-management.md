# Media management

ShopNXe media is a reusable, Store-owned master asset. The implementation
extends the existing Spatie Media Library integration; it does not introduce a
second package, replace the existing `media` table, or store binary data in
PostgreSQL.

## Ownership and compatibility

- `media.store_id` is the tenancy boundary. Application queries use the active
  Store explicitly and the `Media` model retains its Store global scope.
- Public IDs remain ShopNXe ULIDs. The existing unique UUID column required by
  Spatie Media Library remains unchanged. Existing bigint primary and foreign
  keys remain unchanged.
- Existing `product_images` and `product_image_translations` are a legacy
  locator/metadata API and remain fully compatible. New uploaded assets use
  `product_media` and `product_variant_media`; `product_id` is never stored on
  the master `media` row.
- Existing Spatie columns and pre-existing file paths are retained. The
  migration backfills new path fields to the exact old path. New files use
  `stores/{store_public_id}/media/{year}/{month}/{media_public_id}/`.
- Every newly created Spatie or ShopNXe media row records its directory and path
  in `custom_properties.shopnxe_storage`. Those values survive a schema
  rollback and allow a later re-apply to recover dated paths.

## Data model

| Table | Purpose | Store isolation |
| --- | --- | --- |
| `media` | Master asset, storage identity, checksums, lifecycle, descriptive metadata | Direct required `store_id`; existing Store cascade |
| `media_variants` | `original`, `thumbnail`, `small`, `medium`, and `large` derivative records | Cascades through `media_id` |
| `product_media` | Reusable Product attachment, ordering, and primary selection | Required `store_id` plus composite Product/Media foreign keys |
| `product_variant_media` | Reusable Product Variant attachment and ordering | Required `store_id` plus composite Variant/Media foreign keys |
| `media_ai_results` | Provider-neutral, extensible AI operation results | Cascades through `media_id` |
| `media_usages` | Generic Store/resource usage records for future Collections, Pages, Blogs, Banners, Themes, and AI consumers | Composite `(media_id, store_id)` foreign key |

The database rejects cross-Store Product or Variant attachment even if an
application bug bypasses service checks. `product_media` allows one attachment
per Product/Media pair and a PostgreSQL partial unique index allows at most one
primary asset per Product. A Media/variant-name pair is unique.

Media statuses are `pending`, `processing`, `ready`, `failed`, and `deleted`.
Visibility is `private` or `public`. Deletion is logical: the row, usage audit,
original object, and derivatives stay recoverable; active Product and Variant
attachments are removed and the next ordered Product asset becomes primary.

## Upload and processing lifecycle

1. `POST /api/v1/store/media/uploads` validates membership, `manage products`,
   configured disk allow-list, size, extension, and server-detected MIME. It
   streams the object through Laravel Storage and creates a `pending` row.
2. `POST /api/v1/store/media/{media}/complete` verifies the object exists,
   locks the row, changes it to `processing`, and dispatches one queue chain.
3. `ExtractMediaMetadata` reads the object from configured local, S3, or
   S3-compatible/MinIO storage and records verified MIME and dimensions.
4. `OptimizeMedia` uses the already-installed Spatie optimizer against a
   temporary local copy, then writes through the original Storage disk.
5. `GenerateMediaVariants` uses the already-installed Spatie Image/GD boundary
   and records the original plus configured derivatives.
6. `FinalizeMediaProcessing` marks the master asset `ready`. A permanently
   failed step records a safe job/error summary in metadata and marks it
   `failed`.

`checksum` is the SHA-256 of the merchant-supplied original before optimization
so duplicate input detection remains stable. The `MediaAiService` is only a
persistence boundary (`processing`, `completed`, or `failed` result rows); no
external AI provider is called.

## Authorization and API boundary

Every endpoint requires `auth:sanctum`, Store scope, `X-Store-ID`, and active
membership. Reads require membership; upload, completion, deletion, attach,
detach, and primary selection require `manage products`. Services always query
the public media ID together with the active internal `store_id`. Private and
public content are both delivered through the authenticated Store content
route, which prevents a public locator from becoming a tenancy bypass.

The media subsystem is REST-only because uploads and the existing Product image
contract are REST. It does not add a second API style to Catalog or change the
Lighthouse schema. See the [API manual](api-manual.md) for the exact routes.

## Configuration

`config/media-management.php` owns the subsystem allow-list, maximum upload
size, variant widths/quality, and queue. It uses the existing filesystem disks
from `config/filesystems.php`. Defaults are `private,public,s3`; deployments
should set `MEDIA_ALLOWED_DISKS` to configured disks only. S3-compatible MinIO
uses the existing `s3` driver and endpoint variables; no provider is hard-coded.

## Verification

`tests/Feature/MediaManagementRestApiTest.php` covers Store uploads, cross-Store
read/write rejection, permission checks, Product and Variant attachment, asset
reuse, primary selection, checksum lookup, variant uniqueness, recoverable
deletion, terminal failure recording, the processing chain, and actual
derivative files. Tests run only on
the separate `shopnxe_test` PostgreSQL database from `.env.testing`.

Deployment and reversal steps are recorded in the
[media rollout and rollback ledger](media-management-rollout.md).
