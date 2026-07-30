# Platform admin shell component

The Platform admin shell is the frontend consumer of
`GET /api/v1/auth/interfaces`. This repository supplies the response contract;
the visual shell is implemented in the separate frontend application.

## Scope selection

Exactly one interface may be available for a user:

- `platform_admin` renders the SaaS-owner/staff shell and never asks for a
  Store.
- `store_admin` renders the merchant shell after selecting an authorized Store.

The client must not merge navigation or permissions between the two
interfaces.

## Navigation contract

Render only navigation entries returned in
`data.platform_admin.navigation`.

| Key | Label | Path | Required permission |
| --- | --- | --- | --- |
| `plans_pricing` | Plans & Pricing | `/admin/plans` | `manage plans` |
| `platform_settings` | Settings | `/admin/settings` | `manage platform settings` |

Navigation metadata improves the experience but is not authorization. The
corresponding API still enforces account scope and permissions.

Use the stable `key` to resolve a frontend dictionary entry; treat the returned
English `label` as a fallback, not as the translation key. When navigation
labels change, update every relevant frontend language file according to the
[localization contract](localization.md).

## Shell states

- While interfaces load, show a shell-level loading state rather than guessing
  the account type.
- If `platform_admin.available` is false, do not mount Platform components.
- If a deep-linked component is absent from navigation, show a forbidden/not
  available state and do not issue mutation requests.
- On API `401`, return to authentication. On `403`, keep the shell and show an
  authorization message.

See [Authentication](../authentication.md),
[Authorization](../authorization.md), and
[Platform Settings admin](platform-settings-admin.md).
