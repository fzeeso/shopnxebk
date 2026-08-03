# Themes to Authentication

## Direction

Themes consumes Authentication identities and scoped authorization.

## Contract

Publisher ownership, Theme creation, version upload/approval, submission
review, license purchase, and installation audit fields reference internal
bigint `users.id`; public resources serialize the related User ULID only when
that relationship is loaded.

Platform APIs require a Platform account with `manage marketplace`. Store
APIs require a Store account, active selected-Store membership, and
`manage themes`. Authentication owns roles, permissions, credentials,
sessions, tokens, and MFA; Themes only checks the resulting authorization.

Themes never assigns roles, changes `users.scope`, creates memberships, or
stores passwords/tokens.
