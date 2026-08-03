# Themes to Files

## Direction

Themes consumes media/private-storage boundaries.

## Contract

Marketplace listing imagery uses Media Library collections owned by a Theme.
Because catalog imagery is global, those media rows may have
`media.store_id = null` and use
`platform/media/{media-public-ulid}` paths. Store-owned media continues to
require Store context and Store-prefixed paths.

Theme versions store opaque source/compiled object keys, SHA-256, package
sizes, file count, manifest, schema, and validation report. Registration does
not upload, extract, compile, or execute content. A Files/artifact worker must
validate quarantine paths, archive traversal, extensions, limits, malware,
manifest/schema, and executable-code policy before marking an artifact safe.

Themes never exposes private object keys as public download URLs. Authorized
delivery must use temporary URLs or a future Files endpoint.
