# Settings to Authentication

Settings depends on Authentication for the current `User`, exclusive account
scope, and the `manage platform settings` permission.

- Every route uses `auth:sanctum` and `user.scope:platform`.
- Catalog reads require Platform scope.
- Catalog writes require `manage platform settings` at both the HTTP request
  and application service boundaries.
- Permission checks clear the Store permission team temporarily and restore it
  afterward.
- Settings does not assign roles or permissions.
- Authentication exposes the `/admin/settings` navigation hint only when the
  Platform user has `manage platform settings`.

Authentication does not update Settings catalog tables.
