# Module map

Implemented modules:

| Module | Responsibility |
| --- | --- |
| Authentication | Identity, credentials, MFA, exclusive account scopes, tokens, roles, and permissions |
| Settings | Platform-wide language/currency catalogs and extensible global administration |
| Stores | Store profiles/settings, memberships/context, Store language selections, and isolation |
| Themes | Marketplace publishers/categories/listings, immutable releases/review, Store licenses, and installed/customized Store copies |
| Billing | Platform plan prices, reusable features, add-on assignments, and plan administration |
| Catalog | Store-local brands, collections, taxonomy, products, variants, fulfillment metadata, and custom fields |

The following modules are planned but are not created:

| Module | Responsibility | Main dependencies |
| --- | --- | --- |
| Inventory | stock levels, reservations, adjustments | Catalog |
| Orders | carts, checkout, order lifecycle | Customers, Inventory, Discounts, Taxes, Shipping, Payments |
| Customers | customer profiles and addresses | Authentication (identity contract) |
| Payments | provider-independent payment intents and captures | Orders, Billing |
| Shipping | rates, labels, fulfillment state | Orders, Inventory |
| Taxes | jurisdiction and tax calculation adapters | Orders, Catalog |
| Discounts | promotions and eligibility rules | Catalog, Customers, Orders |
| Search | indexing and store-filtered queries | Catalog, events |
| CMS | pages and content blocks | Files |
| Notifications | email, SMS, in-app delivery | Authentication, Orders |
| Subscriptions and Invoicing | Store subscriptions, invoices, renewals, provider state | Billing catalog, Payments |
| Analytics | asynchronous event ingestion and reporting | all domain events |
| Apps | installed integrations and credentials | Webhooks, OAuth adapters |
| Files and Exports | media ownership, uploads, export jobs | Media Library, Queues |
| Webhooks | signed inbound/outbound events and idempotency | Apps, Orders, Payments |

Orders must use contracts rather than importing another module's Eloquent models. Payments are isolated behind provider adapters. Search consumes events and is never the system of record; every document includes `store_id` and every query applies the active store filter. Analytics is asynchronous and must not be called synchronously from checkout logic. Inventory may depend on stable Catalog identifiers, while Catalog must not depend on Inventory state.

To add a module, use the API-only nwidart generator, keep its migrations/routes/schema/tests inside the module, add a provider and composer PSR-4 mapping, and expose cross-module behavior through contracts, actions, or domain events.

Every implemented module also receives a separate document under
`docs/modules/`. Every directional dependency receives a separate contract
under `docs/module-communication/`; update both sides whenever a cross-module
interface changes. Themes is documented in [Themes module](modules/themes.md)
and [Theme marketplace and Store themes](themes.md). Catalog is documented in
the [Catalog module](modules/catalog.md) and the complete
[Catalog schema reference](catalog.md). All modules inherit the
identifier, Store-language, and authorization rules from
[application context](context.md).
