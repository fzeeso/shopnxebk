# Catalog to Files

## Direction

Catalog consumes future Files storage and authorized-delivery boundaries.

## Contract

Product image and digital-asset rows store locators plus display/file metadata;
they do not upload, scan, sign, or serve content. Digital asset locators are
private and must never be serialized as unrestricted download URLs. A Files
service must validate ownership, object paths, content type, size, malware
state, download limits, and expiry before issuing temporary access.

Store deletion cascades Catalog metadata. Physical object cleanup must be an
idempotent after-commit Files job driven by public Store/Catalog identifiers.
Files never edits product identity, translations, pricing, inventory policy,
taxonomy, options, variants, or fulfillment rules.
