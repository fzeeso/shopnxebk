# Themes to Stores

## Direction

Themes consumes Store identity and current Store context.

## Contract

Custom marketplace Themes may reference `stores.id` as `owner_store_id`.
Licenses and installed copies use internal bigint `store_id` foreign keys,
while requests/resources use the Store public ULID. Store deletion cascades
owned custom Themes/licenses/installations according to migration constraints.

Store Theme APIs run only after Stores middleware resolves `X-Store-ID` and
confirms active `store_users` membership. Theme services always scope
`StoreTheme` and license queries to that internal Store ID and never trust a
Store ID from a request body.

Themes must not mutate Store profile, lifecycle, settings, domains,
memberships, or Store language records.
