# Stores to Themes

## Direction

Stores calls Themes during Store provisioning.

## Contract

`ProvisionStore` depends on `Modules\Themes\Contracts\ThemeInstaller`,
not Theme Eloquent models. It passes the newly created Store, Store-scoped
Owner identity, and selected template key inside the Store provisioning
transaction.

The Theme implementation must:

1. resolve or idempotently create the bundled Platform publisher/Theme/version;
2. select a published compatible version;
3. issue a free/custom/eligible license for the Store;
4. create one installed `published` Store copy; and
5. return the installed Theme.

Any error is allowed to bubble so Stores rolls back settings, domains, Theme
records, membership, and role together. Themes never creates or updates
`stores`, `store_settings`, `store_domains`, or `store_users`.
