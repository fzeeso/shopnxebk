# Stores to Billing communication

The Billing module is planned but not yet implemented. Stores reserves nullable indexed bigint `plan_id` and `subscription_id` columns so a Store can later point to its current SaaS plan and subscription without storing provider payloads in Store settings.

## Current contract

- The keys are internal integration values and are never exposed by `StoreResource` or the GraphQL `Store` type.
- No foreign keys or Eloquent relationships exist while Billing tables do not exist.
- A null value means billing has not been assigned or migrated; it must not be interpreted as a free plan without a Billing policy.

## Future integration

When Billing is introduced, Billing owns plan/subscription state, provider identifiers, invoices, renewals, trial transitions, and cancellation rules. Its migration must add the appropriate foreign-key constraints after validating existing Store values. Stores may read a Billing-owned projection or contract but must not update Billing tables directly.

Changes to plan/subscription identifiers, trial ownership, or public billing resources must update the Stores and Billing module documents, this communication contract, migrations, rollout notes, and cross-module tests.
