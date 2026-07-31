# Local development

Use Docker Compose for Redis, Meilisearch, Mailpit, and MinIO. PostgreSQL and
Laravel run on the host in the current XAMPP workflow:

```powershell
docker compose up -d
& "C:\xampp\php\php.exe" artisan migrate --seed
curl.exe http://localhost/shopnxebk/public/api/health/ready
```

Mailpit is available at `http://localhost:8025`; MinIO's console is `http://localhost:9001`. The app defaults to private local storage, Redis cache/sessions/queues, and the Scout database driver. Set `SCOUT_DRIVER=meilisearch` after the Meilisearch service is reachable.

Run `composer horizon`, `composer reverb`, and `composer octane` in separate processes. `php artisan queue:work redis --queue=default,notifications,webhooks,exports,media,search,billing` is a lightweight alternative. Keep `INTERNAL_DASHBOARDS_ENABLED=false` unless you explicitly need the protected operational dashboards.

Tests require PostgreSQL (`shopnxe_test`) and use `.env.testing`/`phpunit.xml`; SQLite is intentionally unsupported. Run `php artisan test`, `composer format:check`, and `composer analyse`.

The separate Next.js admin uses `NEXT_PUBLIC_LARAVEL_API_URL=/laravel` and
rewrites browser traffic to its server-only `LARAVEL_API_URL`. For this XAMPP
checkout, set that upstream to `http://localhost/shopnxebk/public`. Keep
`SESSION_DOMAIN` empty locally and allow both `http://localhost:3000` and
`http://127.0.0.1:3000`; this prevents browser-entry hostname changes from
breaking Sanctum cookies.
