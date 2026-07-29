# ShopNXE application context

This is the canonical context for architecture and future implementation work. Read it before changing a module, migration, route, API contract, authorization rule, queue job, search document, or media path.

## Domain language

ShopNXE is a multi-store SaaS commerce platform. A **store** is the business/account boundary that some libraries call a tenant. Application code, database columns, routes, headers, events, documentation, and user-facing messages use **Store**. The word “tenant” is retained only when referring to a third-party class, interface, configuration key, or command that cannot be renamed.

A **user** is an authenticated identity stored in the shared `users` table, but every row has one exclusive `scope`: `platform` or `store`. Platform administrators/support/billing staff are Platform users. Store owners/managers/sales/inventory staff are Store users. A user cannot change scope while memberships, roles, or direct permissions exist.

## Access interfaces

The two account classes and interfaces are mutually exclusive:

| Interface | Administrative user | Scoped users | Context |
| --- | --- | --- | --- |
| `platform_admin` | `Super Admin` is the SaaS Owner | `Support` and `Billing` are platform staff | Platform scope; no Store header |
| `store_admin` | `Owner` and `Manager` administer the merchant | `Sales`, `Inventory`, and future Store roles are Store staff | One selected Store ULID |

After authentication, `GET /api/v1/auth/interfaces` returns both stable response keys but exactly one side can be available. A Platform user receives only `platform_admin` roles/permissions and can never have Store membership, Store roles, or Store-bound tokens. A Store user receives only `store_admin` Stores/roles/permissions and can never receive a Platform role or permission. Backend scope middleware, Store membership/context, permissions, and policies remain authoritative.

## Identifier contract

Domain entities have two identifiers:

| Purpose | Column | Type | Visibility |
| --- | --- | --- | --- |
| Database primary key | `id` | PostgreSQL `bigint` | Internal only |
| Public identifier | `public_id` | 26-character ULID | REST, GraphQL, URLs, events exposed outside the process |
| Relationships | `*_id` | PostgreSQL `bigint` | Internal only |

Laravel route binding uses `public_id`. Requests resolve a ULID once and then use the bigint key for joins, scopes, permission teams, token binding, cache/search filters, and internal events. API resources and GraphQL types must never expose an internal bigint key as `id`.

Entity tables such as `users`, `stores`, `store_memberships`, `roles`, `permissions`, `personal_access_tokens`, `media`, and future commerce records require both columns. Pure relationship tables managed through direct package inserts may use only an internal bigint `id`, because they are not addressable public resources. Protocol/infrastructure identifiers required by Laravel packages remain exceptions: notification IDs and Media Library UUIDs are UUIDs, failed-job UUIDs remain diagnostic identifiers, and cache/queue/monitoring tables follow their package contracts.

## Store profile contract

The `stores` table is the source of truth for merchant identity, contact details, branding references, classification, locale, lifecycle, and Store-level capability switches. `business_type` is nullable during onboarding and, when present, is one of `ecommerce`, `b2b`, `services`, `digital`, `restaurant`, or `marketplace`. `status` is one of `pending`, `active`, `suspended`, or `cancelled`.

`plan_id` and `subscription_id` are nullable internal bigint integration keys reserved for the future Billing module. They are indexed but do not have foreign-key constraints until the Billing tables exist, and they must never be returned as public identifiers. `logo`, `favicon`, and `cover_image` hold nullable storage references; upload authorization and file delivery remain Files/media responsibilities.

Currency uses a three-character ISO 4217 code, country uses a two-character ISO 3166-1 alpha-2 code, language accepts a BCP 47-style code, and timezone stores an IANA timezone name. The database defaults new Stores to `USD`, `en`, `UTC`, and all capability/verification flags to `false`. Provisioning copies the display name into `legal_name`; imported historical Stores may keep optional profile values null until onboarding completes.

## Store context and request flow

```mermaid
flowchart LR
    Request["Request with Store ULID"]
    Resolve["Resolve public_id"]
    Store["Store bigint id"]
    Membership["Check store_memberships"]
    Token["Compare token.store_id"]
    Team["Set permission team to bigint store_id"]
    Domain["Run policy/action/query"]
    Public["Serialize public_id"]

    Request --> Resolve --> Store --> Membership --> Token --> Team --> Domain --> Public
```

Store-scoped operations receive `X-Store-ID: <store-ulid>`. `ResolveStore` validates the ULID and loads `stores.public_id`. `EnsureStoreMembership` verifies an active membership, prevents a store-bound bearer token from crossing stores, and sets Spatie Permission’s team to the internal `stores.id`. `ClearRequestContext` always removes store, permission-team, guard, locale, and log context after a request.

## Roles and permissions

Roles and permissions are data, not hard-coded user types. Both have a `scope` of `platform` or `store`, and the catalog is extendable.

Platform roles may be assigned only to `users.scope = platform` and are evaluated with no Store team. Store roles may be assigned only to `users.scope = store`, require active membership, and carry the matching internal `store_id`. PostgreSQL triggers enforce these boundaries for memberships, assignments, scope transitions, and Store-bound tokens. `ScopedRoleAssignmentService` is the application write path.

Platform roles:

| Role | Initial permissions |
| --- | --- |
| Super Admin | manage stores, manage plans, manage subscriptions, impersonate store, manage marketplace |
| Support | manage stores, impersonate store |
| Billing | manage plans, manage subscriptions |

Store roles:

| Role | Initial permissions |
| --- | --- |
| Owner | access/manage store, members, roles, products, orders, customers, discounts |
| Manager | access/manage store, members, products, orders, customers, discounts |
| Sales | access store, manage orders, customers, discounts |
| Inventory | access store, manage products |

Platform roles are evaluated without an active store team. Store-role assignments carry an internal `store_id`; the active store can never grant a platform role. `Super Admin` is the only global Gate bypass. New roles or permissions are added through the authorization catalog, migrations/seeders, policies, tests, and documentation—not through boolean columns on `users`.

## Module boundaries

- Authentication owns global identities, credentials, sessions, MFA, tokens, email verification, password reset, and the role/permission catalog.
- Stores owns stores, memberships, store resolution, context lifecycle, provisioning, and store isolation helpers.
- Business modules own their records and actions. Store-owned records use bigint `store_id` and `StoreScoped`, then return ULIDs publicly.
- Cross-module calls use contracts, typed actions, immutable data objects, or after-commit domain events. A module must not update another module’s tables directly.

See [Authentication module](modules/authentication.md), [Stores module](modules/stores.md), and the directional communication contracts in [module communication](module-communication/).

## Change rule

Every meaningful change must update the affected module document and directional communication document. Architecture or execution-flow changes also update the [developer guide](developer-guide.md), and every meaningful change receives a [development log](development-log.md) entry. Finish with `composer docs:update`, `composer docs:check`, Pint, and PostgreSQL-backed tests.
