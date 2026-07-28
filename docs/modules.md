# Future module map

The following modules are planned but are not created in this foundation:

| Module | Responsibility | Main dependencies |
| --- | --- | --- |
| Products/Catalog | products, variants, categories, catalog identifiers | Files, Search |
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
| Billing | SaaS plans, subscriptions, invoices | Payments |
| Analytics | asynchronous event ingestion and reporting | all domain events |
| Apps | installed integrations and credentials | Webhooks, OAuth adapters |
| Files and Exports | media ownership, uploads, export jobs | Media Library, Queues |
| Webhooks | signed inbound/outbound events and idempotency | Apps, Orders, Payments |

Orders must use contracts rather than importing another module's Eloquent models. Payments are isolated behind provider adapters. Search consumes events and is never the system of record; every document includes `store_id` and every query applies the active store filter. Analytics is asynchronous and must not be called synchronously from checkout logic. Inventory may depend on stable Catalog identifiers, while Catalog must not depend on Inventory state.

To add a module, use the API-only nwidart generator, keep its migrations/routes/schema/tests inside the module, add a provider and composer PSR-4 mapping, and expose cross-module behavior through contracts, actions, or domain events.

Every implemented module also receives a separate document under `docs/modules/`. Every directional dependency receives a separate contract under `docs/module-communication/`; update both sides whenever a cross-module interface changes. All modules inherit the identifier, Store-language, and authorization rules from [application context](context.md).
