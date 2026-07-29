# Billing module

## Ownership

`Modules/Billing` owns:

- the `plans`, `features`, and `plan_features` tables;
- plan names, public slugs, descriptions, audience, fixed/custom pricing, status, display order, and featured state;
- reusable typed feature definitions;
- included and optional add-on assignments;
- Platform-only plan/feature CRUD services, validation, resources, and REST routes;
- the idempotent initial ShopNXE plan catalog.

## Boundaries

Billing administration requires Platform scope and `manage plans`. Store identities, memberships, Store settings, and Store roles are never loaded into the admin CRUD flow.

Stores owns the nullable `stores.plan_id` integration key. Billing prevents deletion when that key references a plan, but it does not directly alter Store records. Subscription/provider identifiers, invoices, renewals, and checkout are not implemented yet.

## Write path

Controllers resolve public ULIDs and delegate to `PlanAdminService`, `FeatureAdminService`, or `PlanFeatureAdminService`. `PlatformPlanAccessService` enforces scope/permission without a Store team. Services own transactions, typed-value/add-on validation, and safe deletion. Resources serialize public ULIDs and integer minor-unit money.

See [Plans & Pricing](../plans-and-pricing.md), [Billing to Authentication](../module-communication/billing-to-authentication.md), [Billing to Stores](../module-communication/billing-to-stores.md), and [Stores to Billing](../module-communication/stores-to-billing.md).
