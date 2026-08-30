# Catalog to Customers

Customers exports `CustomerGroupResolver`. A trusted caller supplies the active
`Store` and customer-group public ULID and receives only the internal ID, public
ID, and stable code. Future Catalog audience pricing, Orders, or Discounts code
uses this contract rather than importing the CustomerGroup model, querying
Customers tables, or accepting an internal ID from a client.

Catalog's existing nullable `customer_group_id` modifier-audience columns remain
non-public until their services explicitly adopt this resolver and define
deletion/snapshot behavior. Installing Customers does not silently enable those
inputs or retrofit foreign keys into existing tables.
