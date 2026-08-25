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
so duplicate input detection remains stable.

## AI generation and media operations

`MediaAiService` authorizes the active Store, orchestrates the provider call,
creates or updates Media records, and records `processing`, `completed`, or
`failed` rows in `media_ai_results`. `OpenAiMediaService` is the only provider
client. It reads `OPENAI_API_KEY` on the server and never returns that key to a
frontend.

- `POST /api/v1/store/media/ai/generate` sends the merchant prompt and selected
  image settings to the OpenAI Image API. Each returned image becomes a private
  Media record with `metadata.source=ai_generated`, then enters the normal
  queued derivative lifecycle.
- `POST /api/v1/store/media/{media}/ai` accepts `generate_alt_text`,
  `generate_attributes`, `generate_tags`, `generate_seo_filename`,
  `remove_background`, or `enhance_image`. The source must be a ready image from
  the selected Store.
- Metadata operations send the selected image to the OpenAI Responses API with
  strict JSON Schema output and `store=false`. Alt text updates `media.alt_text`;
  attributes, tags, and SEO filename suggestions are retained under
  `metadata.ai`.
- Background removal and enhancement send the selected image to the OpenAI
  Image edits endpoint. They preserve the original and create a new private
  Media record linked through `metadata.ai.source_media_id`.
- Provider prompts, image bytes, and the API key are not logged. Safe request
  IDs, HTTP status, provider error type, and provider error code may be logged
  for operations. Provider rejections return a safe `502`; missing configuration
  or connection failure returns `503`.

Generation is limited to six requests per minute per Laravel throttle key and
per-media operations to ten per minute. Provider calls are synchronous within
the HTTP request and have a configurable long timeout; normal Media processing
remains asynchronous. These calls consume the OpenAI API account associated
with the configured key.

## Authorization and API boundary

Every endpoint requires `auth:sanctum`, Store scope, `X-Store-ID`, and active
membership. Reads and AI-result history require membership; upload, completion,
deletion, AI generation/operations, attach, detach, and primary selection
require `manage products`. Services always query
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

OpenAI media behavior uses `OPENAI_MEDIA_IMAGE_MODEL` (default `gpt-image-2`),
`OPENAI_MEDIA_ANALYSIS_MODEL` (default `gpt-5-mini`),
`OPENAI_MEDIA_TIMEOUT` (default 240 seconds),
`OPENAI_MEDIA_MAX_OUTPUT_TOKENS` (default 2,000),
`OPENAI_MEDIA_QUALITY` (`low`, `medium`, or `high`; default `medium`), and
`OPENAI_MEDIA_MAX_OUTPUT_BYTES` (default 20 MiB). All settings share the
server-only `OPENAI_API_KEY` already used by automatic translation.

## Verification

`tests/Feature/MediaManagementRestApiTest.php` covers Store uploads, cross-Store
read/write rejection, permission checks, Product and Variant attachment, asset
reuse, primary selection, checksum lookup, variant uniqueness, recoverable
deletion, terminal failure recording, the processing chain, and actual
derivative files. Tests run only on
the separate `shopnxe_test` PostgreSQL database from `.env.testing`.

`tests/Feature/MediaAiRestApiTest.php` covers generation, source filtering,
strict structured alt text, background-removed derivatives, safe provider
failure recording, and cross-Store rejection. It uses `Http::fake`; the test
suite does not call OpenAI or consume provider credits.

Deployment and reversal steps are recorded in the
[media rollout and rollback ledger](media-management-rollout.md).
