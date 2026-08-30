# shopnxebk

ShopNXE is an API-only Laravel 13 modular monolith for a multi-store SaaS commerce platform. It contains no customer or administration frontend. Authentication, Store context, Catalog persistence, and Store-scoped Customer management are implemented. The Catalog API split is Brand REST, Category/Product Type GraphQL, Product REST plus GraphQL, nested Product Image REST, and Fulfillment Type REST; Customer profiles, groups, credits, category access, and group discounts use Store REST. Other commerce workflows remain intentionally incremental.

Start with the [developer guide](docs/developer-guide.md) for the installed stack, boot sequence, information flows, execution commands, and safe change workflow. The [API manual](docs/api-manual.md) is the client/developer handoff for authentication, Store context, the Catalog REST/GraphQL exposure matrix, translations, examples, and future implementation rules. Store Admin Product create/edit behavior is documented separately in the [Product Detail guide](docs/product-detail-guide.md), and module authors should use the [Product Detail section-provider contract](docs/module-communication/product-detail-section-providers.md). Generated system and GraphQL references stay synchronized through the documentation commands and CI.

## Prerequisites

- PHP 8.4 with `pdo_pgsql`, `redis`, `mbstring`, `openssl`, `pcntl`, `intl`, `zip`, and `exif`
- Composer 2.x
- PostgreSQL 16+ (the local compose file pins PostgreSQL 18)
- Redis 7+
- Docker Compose (recommended for local infrastructure)

## Install and run

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

The supplied `.env` for the workspace uses database `shopnxe`, user `postgres`, and should contain the local password supplied out-of-band. Never commit it.

For the complete local stack:

```bash
docker compose up -d --build
docker compose exec app php artisan migrate --seed --force
```

Start workers and realtime services with `composer horizon`, `composer reverb`, and `composer octane`. Octane uses FrankenPHP and is optional for tests and ordinary Artisan commands. Deployments should use `php artisan octane:reload` for a graceful worker restart.

## API

- REST base: `/api/v1`
- Health: `GET /api/health/live` and `GET /api/health/ready`
- GraphQL: `POST /graphql`
- Dashboard session and interface profile: `GET /api/v1/auth/session`
- Interface access profile: `GET /api/v1/auth/interfaces`
- Store-scoped requests use `X-Store-ID: <store ULID>`.
- Store management: `/api/v1/stores` and `/api/v1/store/*`
- Platform users and merchants: `/api/v1/platform/users` and `/api/v1/platform/merchants`
- Store user management: `/api/v1/store/users`
- Platform Plans & Pricing: `/api/v1/platform/plans` and `/api/v1/platform/features`
- Every response includes an `X-Request-ID` header. A valid incoming ID is preserved; otherwise one is generated.

Authentication examples (use placeholders only):

```bash
curl -X POST http://localhost/shopnxebk/public/api/v1/auth/register -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"name":"Ada","email":"ada@example.test","password":"StrongPassword!123","password_confirmation":"StrongPassword!123","store_name":"Acme","store_slug":"acme"}'

curl -c cookies.txt -X POST http://localhost/shopnxebk/public/api/v1/auth/login -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"ada@example.test","password":"StrongPassword!123"}'

curl -X POST http://localhost/shopnxebk/public/api/v1/auth/token -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"ada@example.test","password":"StrongPassword!123","device_name":"cli","store_id":"<store-ulid>"}'

curl http://localhost/shopnxebk/public/api/v1/auth/me -H 'Accept: application/json' -H 'Authorization: Bearer <token>'

curl http://localhost/shopnxebk/public/graphql -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer <token>' -H 'X-Store-ID: <store-ulid>' -d '{"query":"{ viewer { id email } activeStore { id slug } }"}'

curl -X POST http://localhost/shopnxebk/public/api/v1/auth/logout -H 'Accept: application/json' -H 'Authorization: Bearer <token>'
```

The OpenAPI description of implemented REST endpoints is in `docs/openapi.yaml`; the Lighthouse schema is the source of truth for GraphQL. Use the [API manual](docs/api-manual.md) for end-to-end usage and the generated [GraphQL operation reference](docs/generated/graphql-operations.md) for the current operation index.

For Product administration, use the composed `/api/v1/store/product-detail`
contract rather than making the browser call each Product-owned service. The
[Product Detail Store Admin guide](docs/product-detail-guide.md) covers full and
selective reads, transactional dirty-section saves, request-local references,
conflict handling, limits, and frontend performance guidance.

## Configuration

Copy `.env.example` and set `APP_KEY`, PostgreSQL, Redis, mail, and (for production) S3 and Reverb values. `CORS_ALLOWED_ORIGINS` and `SANCTUM_STATEFUL_DOMAINS` are explicit comma-separated lists; wildcard origins are not supported with credentialed cookies. Keep local `SESSION_DOMAIN` empty so Sanctum emits host-only cookies. The separate admin uses a same-origin `/laravel` proxy and sends its server-side traffic to this Apache application URL. `INTERNAL_DASHBOARDS_ENABLED=false` keeps Horizon and Pulse routes disabled. Telescope is local-only and additionally requires `TELESCOPE_ENABLED=true`. Set `SCOUT_DRIVER=meilisearch` when Meilisearch is available; `database` is the reduced-infrastructure default.

## Tests and quality

Tests are PostgreSQL-backed (`DB_CONNECTION=pgsql`; no SQLite test fallback):

```bash
composer validate --strict
composer format:check
composer analyse
php artisan test
```

CI provisions PostgreSQL, Redis, and Meilisearch, then runs the same checks. `composer format` applies Pint.

Internal dashboards, when explicitly enabled, are protected by the global `Super Admin` role and an optional IP allow-list. No application Blade views or public web routes are present.

## Modules

`Authentication` owns REST authentication, global users, tokens, MFA, and permission models. `Settings` owns extensible Platform-wide configuration, currently the language and currency catalogs. `Stores` owns merchant profile/settings services, memberships, context, Store language selections, and multilingual Store Pages. `Billing` owns Platform plan prices, reusable features, and add-on assignments. `Customers` owns Store buyer profiles, multilingual group names, append-only credits, category access, and targeted group discounts. Start with the [canonical application context](docs/context.md), [user and merchant management](docs/user-merchant-management.md), [admin component guides](docs/components.md), [Platform settings](docs/settings.md), [Store management](docs/store-management.md), [Store pages](docs/pages.md), [Customer management](docs/customers.md), the [legacy customer conversion runbook](docs/customer-data-conversion.md), and [Plans & Pricing](docs/plans-and-pricing.md), then see the separate [module documents](docs/modules/) and [module communication contracts](docs/module-communication/).

## Package baseline

Laravel `13.21.1`, PHP `8.4`, Sanctum `4.3.3`, Lighthouse `6.69.0`, Spatie Permission `8.3.0`, Spatie Multitenancy `4.1.5`, Media Library `11.23.3`, Modules `13.0.0`, Scout `11.4.0`, Horizon `5.48.1`, Octane `2.18.0`, Reverb `1.11.0`, Pulse `1.7.4`, Meilisearch PHP `1.16.1`, Pint `1.29.3`, Larastan `3.10.0`, and PHPUnit `12.5.31` are locked in `composer.lock`.
