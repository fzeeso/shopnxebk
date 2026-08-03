# Themes to Settings

## Direction

Themes consumes the Settings currency catalog.

## Contract

A paid Theme stores a three-letter `price_currency` that must exist in the
Settings-owned `currencies.code` catalog. Request validation accepts a public
currency code, not the currency bigint ID. Free/private Themes must keep both
price fields null.

Themes never changes currency display metadata, exchange rates, active state,
or Store currency selection. A later checkout service must define how inactive
currencies affect new purchases without rewriting historical Theme prices.
