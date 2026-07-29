# Billing to Authentication communication

Billing depends on Authentication only for the authenticated `User` identity and the Platform `manage plans` permission.

## Contract

- `PlatformPlanAccessService` first requires `users.scope = platform`.
- Permission evaluation runs with no Store permission-team ID.
- `Super Admin` and `Billing` initially receive `manage plans`; `Support` does not.
- Billing never assigns roles/permissions and never reads credentials, MFA secrets, sessions, or token hashes.
- The `Plans & Pricing` navigation item is a frontend hint derived by Authentication from `manage plans`; Billing services remain authoritative even if a client constructs the route manually.

Changes to Platform roles, the `manage plans` permission, navigation metadata, or account-scope enforcement must update both the Authentication and Billing module documents and their cross-scope tests.
