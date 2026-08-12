# Catalog to Settings

## Direction

Catalog consumes Settings locale and currency semantics.

## Contract

Translation rows store BCP 47-style locale strings. Variant money stores an
uppercase three-letter currency code and non-negative integer minor units.
Future write validation must accept supported public locale/currency codes and
must not expose or persist Settings bigint identifiers in Catalog resources.

Settings owns language/currency names, active state, formatting, and exchange
rates. Catalog owns historical product translations and variant price facts.
Disabling a Settings catalog row must not rewrite existing Catalog data; future
write and storefront policies decide whether that row can be selected anew.

Brand automatic translation resolves only the locales currently selected and
active for the Store. `BrandTranslationHandler` stores locale strings in its
durable snapshot; it does not persist Settings language bigint IDs. A locale
disabled before worker execution is removed during snapshot revalidation and
the stale request is superseded rather than written.
