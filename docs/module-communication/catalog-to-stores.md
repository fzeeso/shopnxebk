# Catalog to Stores

## Direction

Catalog consumes Store identity and future resolved Store context.

## Contract

Every Catalog entity, translation, and relationship belongs to one internal
`stores.id`. Addressable resources will accept and return the Store public ULID
at process boundaries, while persistence keeps bigint keys. Composite foreign
keys include `store_id` so direct SQL cannot connect Catalog records from
different Stores.

Future Catalog APIs must run after Stores middleware resolves `X-Store-ID`,
confirms active membership/token binding, and establishes the permission team.
Catalog must then scope every query to that internal Store ID. Catalog never
updates Store profiles, lifecycle, settings, domains, language selections,
memberships, roles, or permissions.
