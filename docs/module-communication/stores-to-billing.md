# Stores to Billing communication

The Billing module now owns the editable plan/feature catalog. Stores reserves nullable indexed bigint `plan_id` and `subscription_id` columns so a Store can point to its SaaS plan and a future subscription without storing Billing payloads in Store settings.

## Current contract

- The keys are internal integration values and are never exposed by `StoreResource` or the GraphQL `Store` type.
- `plan_id` may reference `plans.id`; the initial rollout deliberately keeps the historical nullable column unconstrained until existing production values are audited and a plan-assignment workflow exists.
- A null value means billing has not been assigned or migrated; it must not be interpreted as a free plan without a Billing policy.
- Store profile/settings APIs prohibit merchants from writing either integration key.

## Integration boundary

Billing owns plan and feature state now. Subscription state, provider identifiers, invoices, renewals, trial transitions, and cancellation rules remain future work. A later migration may add the `stores.plan_id` foreign key after validating existing values. Stores may read a Billing-owned projection or contract but must not update Billing tables directly.

Changes to plan/subscription identifiers, trial ownership, or public billing resources must update the Stores and Billing module documents, this communication contract, migrations, rollout notes, and cross-module tests.
