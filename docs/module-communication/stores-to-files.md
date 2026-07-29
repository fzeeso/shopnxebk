# Stores to Files communication

The Files module is planned but not yet implemented. Stores owns the business meaning of `logo`, `favicon`, and `cover_image`, while file validation, storage, access control, conversion, and delivery belong to Files/media infrastructure.

## Current contract

The three Store columns are nullable storage references. They do not contain binary content and must not contain credentials or signed URLs with embedded secrets. Store resources may return the reference; clients must use the authorized file-delivery contract once it exists.

## Future integration

A Store profile action will request an upload or select an authorized Store-owned media record, then persist the stable reference after the file transaction succeeds. Replacing or clearing a reference must not delete shared media implicitly; lifecycle cleanup belongs to Files.

Changes to reference format, Store media ownership, upload endpoints, or public delivery URLs must update both module documents, this communication contract, storage tests, and rollout notes.
