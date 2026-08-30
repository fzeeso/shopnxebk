# Customers to Authentication

Merchant/admin authorization continues to use Authentication users, Sanctum
Store-scoped tokens, active Store membership, and the existing
`manage customers` permission. `customer_credits.created_by` may reference the
authenticated merchant user for audit.

Storefront customers are a separate identity population. The Customers module
does not expose login, registration, password reset, bearer-token, session, or
MFA endpoints. Legacy password material may be staged only in hidden migration
columns for a future Authentication-owned rehash-on-login bridge. Legacy API and
reset tokens are expired/discarded and never copied into the new schema.

Any future storefront identity integration must define hash verification and
rehash, reset, token revocation, rate limiting, account enumeration protection,
and Store selection as an explicit Authentication contract. It must not make
the Customers module issue credentials directly.
