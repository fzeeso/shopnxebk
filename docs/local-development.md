# Local development

Use Docker Compose for PostgreSQL, Redis, Meilisearch, Mailpit, and MinIO:

```bash
docker compose up -d --build
docker compose exec app php artisan migrate --seed --force
```

Mailpit is available at `http://localhost:8025`; MinIO's console is `http://localhost:9001`. The app defaults to private local storage, Redis cache/sessions/queues, and the Scout database driver. Set `SCOUT_DRIVER=meilisearch` after the Meilisearch service is reachable.

Run `composer horizon`, `composer reverb`, and `composer octane` in separate processes. `php artisan queue:work redis --queue=default,notifications,webhooks,exports,media,search,billing` is a lightweight alternative. Keep `INTERNAL_DASHBOARDS_ENABLED=false` unless you explicitly need the protected operational dashboards.

Tests require PostgreSQL (`shopnxe_test`) and use `.env.testing`/`phpunit.xml`; SQLite is intentionally unsupported. Run `php artisan test`, `composer format:check`, and `composer analyse`.
