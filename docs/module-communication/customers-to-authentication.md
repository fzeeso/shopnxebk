# Customers to Authentication

Merchant/admin authorization continues to use Authentication users, Sanctum
Store-scoped tokens, active Store membership, and the existing
`manage customers` permission. `customer_credits.created_by` may reference the
authenticated merchant user for audit.

Storefront customers are a separate identity population. The Customers module
may capture an optional confirmed strong password only while a merchant creates
a customer, and the Customer model stores it through an Eloquent `hashed` cast.
It does not return the password/hash or expose login, self-registration,
password reset/change, bearer-token, session, or MFA endpoints. Legacy password
material may be staged only in hidden migration columns for a future
Authentication-owned rehash-on-login bridge. Legacy API and reset tokens are
expired/discarded and never copied into the new schema.

Any future storefront identity integration must define hash verification and
rehash, reset, token revocation, rate limiting, account enumeration protection,
and Store selection as an explicit Authentication contract. Authentication
must consume a narrow customer-identity contract rather than importing the
Customer model, and the Customers module must not issue sessions or tokens.
