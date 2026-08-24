# Media management rollout and rollback ledger

This ledger records the complete change boundary for the 2026-08-25 media
subsystem. It is the operational handoff for deploying or reversing the work
without disturbing existing Store data or services.

## Safety status during implementation

- The live/local `shopnxe` database was inspected read-only with
  `artisan migrate:status`; no migration, seed, truncate, reset, or data update
  command was run against it.
- Schema and feature verification ran through `--env=testing` against the
  separate `shopnxe_test` PostgreSQL database.
- No existing service was stopped or restarted. No dependency was installed,
  removed, or upgraded.
- No existing table or column is renamed or dropped by `up()`. Existing media
  rows are updated only to populate the new compatibility fields with their
  current paths and lifecycle defaults.

## Live rollout record — 2026-08-25 04:11 PKT

The user explicitly authorized applying the subsystem to the live/local
`shopnxe` PostgreSQL database on 2026-08-25. The rollout completed without a
service restart or maintenance-mode interruption.

- Preflight database: `shopnxe`, PostgreSQL, 87 tables, 8.95 MB.
- Baseline rows: 3 Stores, 0 Products, 0 Product Variants, and 2 media rows.
- Pre-migration backup:
  `storage/app/backups/shopnxe-pre-media-20260825-20260825-041045.dump`.
- Backup format/validation: PostgreSQL custom archive, gzip compression, 899
  table-of-contents entries, 474,061 bytes.
- Backup SHA-256:
  `08ECD84C44F89A66BA8227BC0F0B6B7FE66F615EFAF3723DBAC2482756C9F85D`.
- Applied command:
  `php artisan migrate --path=database/migrations/2026_08_25_000100_expand_media_management_subsystem.php --force --no-ansi`.
- Result: migration batch 36, completed in 273.34 ms.
- Post-migration database: 92 tables, 9.20 MB.
- Post-migration rows: the same 3 Stores, 0 Products, 0 Product Variants, and
  2 media rows; every new child/relationship table contains 0 rows.
- Existing-media verification: both rows have non-null path and original
  filename fields, both are `ready`/`private`, and both original storage
  objects are present.
- Schema verification: all 16 new media columns, all 8 selected Store/lifecycle
  constraints, and all 6 selected media/primary indexes are present.

The new migration now reports `Ran` on batch 36. The backup is intentionally
kept under ignored runtime storage and must not be committed.

## Change inventory

The migration is
`database/migrations/2026_08_25_000100_expand_media_management_subsystem.php`.
It adds descriptive/lifecycle columns and indexes to `media`, preserves every
Spatie column, and creates `media_variants`, `product_media`,
`product_variant_media`, `media_ai_results`, and `media_usages`.

Application changes are limited to:

- media enums, models, policy, requests, resources, controllers, services, and
  jobs under `app/`;
- `Product` and `ProductVariant` relationship methods;
- a missing `App\Models\Brand` import in `BrandTranslation`, corrected because
  full Larastan verification surfaced the existing relationship namespace;
- the existing Store media path generator and application provider;
- Store REST routes in `routes/product-api.php`;
- `config/media-management.php`;
- the documented media environment contract in `.env.example`;
- focused tests and the documentation listed in this ledger.

The legacy Product Image model, service, controller, requests, routes, tables,
translations, and response contract are not modified.

## Production preflight

1. Take a PostgreSQL backup and a versioned snapshot of the configured media
   bucket/root.
2. Confirm the target database is PostgreSQL and the current migration list is
   clean with `php artisan migrate:status`.
3. Confirm `MEDIA_DISK`, `MEDIA_ALLOWED_DISKS`, queue connection, and media queue
   are configured; never copy local credentials into source or documentation.
4. Record baseline counts for `stores`, `products`, `product_variants`, and
   `media`.
5. Put only media writes into a maintenance window or place the application in
   maintenance mode, then run `php artisan migrate --force`.
6. Recheck the baseline counts. Existing counts must be unchanged; the five new
   relationship/result tables start empty.
7. Deploy workers that consume the `media` queue, exercise one non-production
   Store upload, attachment, content read, and logical deletion, then reopen
   writes.

The migration is PostgreSQL transactional. A failed `up()` does not leave a
partially created schema.

## Reversal

Reversal removes new API behavior and schema while preserving the original
`media` table and all physical objects.

1. Stop accepting media writes and drain or stop the `media` queue.
2. Back up PostgreSQL and media storage again. Export the six affected tables
   (`media`, `media_variants`, `product_media`, `product_variant_media`,
   `media_ai_results`, and `media_usages`) if new production activity exists.
3. While this release and migration file are still present, run:

   ```powershell
   php artisan migrate:rollback --path=database/migrations/2026_08_25_000100_expand_media_management_subsystem.php --force
   ```

4. Deploy the immediately preceding application revision and restart workers.
5. Verify existing Brand media and legacy Product Image APIs, Store/Product
   counts, health endpoints, and logs before reopening traffic.

`down()` drops only the five new child tables, new `media` indexes/constraints,
and newly added `media` columns. It intentionally does not delete master media
rows or storage objects. Newly uploaded master rows therefore remain preserved
but unattached under the old application. Their dated paths remain recorded in
the pre-existing `custom_properties.shopnxe_storage` JSON, so reapplying this
migration restores the correct path rather than guessing the legacy layout.

If Product/Variant attachment, variant, usage, or AI-result data must be
restored after reversal, restore those tables from the step-2 backup after
reapplying the migration. Do not manually edit a production migration record.

## Files intentionally outside the change

- No `.env` or secret is documented or committed.
- No file under `docs/generated/` is manually edited.
- No existing migration is modified.
- No binary object is stored in PostgreSQL.
- No external AI provider or new package is introduced.
