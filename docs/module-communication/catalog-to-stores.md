# Catalog to Stores

## Direction

Catalog consumes Store identity and future resolved Store context.

## Contract

Every Store-owned Catalog entity, translation, and relationship belongs to one
internal `stores.id`. Addressable resources accept and return public ULIDs at
process boundaries, while persistence keeps bigint keys. Composite foreign
keys include `store_id` so direct SQL cannot connect Catalog records from
different Stores.

Store Catalog REST and GraphQL APIs run after Stores middleware resolves
`X-Store-ID`, confirms active membership/token binding, and establishes the
permission team. Catalog then scopes every query to that internal Store ID.
Brands use REST, Categories and Product Types use GraphQL only, Products use
both, and Product Images use nested REST. Catalog never updates Store profiles,
lifecycle, settings, domains, language selections, memberships, roles, or
permissions.
