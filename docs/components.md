# Admin component guides

These guides define the contract between the API-only backend and a separate
administration frontend. They describe component ownership, navigation,
permissions, API calls, UI states, and acceptance criteria without prescribing
a specific frontend framework.

| Component | Route | Status | Guide |
| --- | --- | --- | --- |
| Platform admin shell | `/admin/*` | Backend navigation contract implemented | [Admin shell](components/admin-shell.md) |
| Plans & Pricing | `/admin/plans` | Backend catalog/API implemented; visual frontend is separate | [Plans & Pricing](plans-and-pricing.md) |
| Platform Settings | `/admin/settings` | Backend catalog/API implemented; visual frontend is separate | [Platform Settings admin](components/platform-settings-admin.md) |
| Store Settings | Future Store-admin route | Deliberately deferred | [Store management boundary](store-management.md) |
| Admin localization | All admin routes | Frontend dictionaries are external; synchronization contract documented | [Localization](components/localization.md) |

Platform Settings and Store Settings are different components:

- Platform Settings manages global SaaS configuration and never sends
  `X-Store-ID`.
- Store Settings manages one merchant Store, requires Store context, and never
  mutates the master language/currency catalogs.

When a frontend component is added or changed, update its guide together with
the API contract, permission/navigation metadata, relevant language
dictionaries, tests, and development log. See the
[localization synchronization contract](components/localization.md).
