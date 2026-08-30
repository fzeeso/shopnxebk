# Customers to Catalog

Customer groups may restrict Category access and apply Category/Product
percentage discounts. Customers owns an outbound `CatalogTargetResolver` port;
its Eloquent adapter resolves a Category or Product public ULID inside the
trusted Store and returns a small immutable reference. Customer domain services
do not accept client bigints or query Catalog tables directly.

The PostgreSQL junction/discount tables include the Store key and composite
foreign keys to Catalog's `(id, store_id)` candidate keys. Category/Product
deletion cascades the corresponding access or discount rule, not a customer or
group. Customers does not edit Catalog content, price, lifecycle, or translation
rows.
