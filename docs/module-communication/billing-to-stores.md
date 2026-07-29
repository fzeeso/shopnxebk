# Billing to Stores communication

Billing owns plan definitions and reads `stores.plan_id` only to protect catalog integrity.

## Current contract

- A plan cannot be deleted while any Store references its internal bigint ID; Platform staff archive it instead.
- Billing services never add Store memberships, enter `StoreContext`, or update Store profiles/settings.
- Public Billing APIs use plan/feature ULIDs. `stores.plan_id` remains an internal cross-module key and is not serialized by `StoreResource`.
- No plan assignment endpoint exists yet. A future subscription workflow must update the Store link through an explicit cross-module contract and transaction/event design.

Changes to plan assignment, subscription state, trials, invoices, provider data, or Store-visible entitlements must update this contract and [Stores to Billing](stores-to-billing.md).
