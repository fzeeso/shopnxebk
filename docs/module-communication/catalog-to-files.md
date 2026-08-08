# Catalog to Files

## Direction

Catalog consumes future Files storage and authorized-delivery boundaries.

## Contract

Brand `image` and `banner` multipart fields are stored now through the shared
`ImageService` and Spatie Media Library. Each collection is single-file,
Store-scoped, and limited to validated raster image types; replacement and
Brand deletion clean up the former physical objects and media records.

Product image and digital-asset rows still store locators plus display/file
metadata; they do not upload, scan, sign, or serve content. Digital asset
locators are private and must never be serialized as unrestricted download
URLs. A Files service must validate ownership, object paths, content type,
size, malware state, download limits, and expiry before issuing temporary
access.

Store deletion cascades Catalog metadata. Product-asset cleanup must eventually
be an idempotent after-commit Files job driven by public Store/Catalog
identifiers. Files never edits product identity, translations, pricing,
inventory policy, taxonomy, options, variants, or fulfillment rules.
