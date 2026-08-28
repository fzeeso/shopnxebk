# Deployment

The canonical AWS topology, starting sizes, feature-flag rollout/rollback
matrix, load gates, monitoring requirements, and owner decision checklist are
in the [AWS scaling and deployment decision guide](aws-scaling-deployment-guide.md).

Build the pinned FrankenPHP image with `docker compose build` or the included `Dockerfile`. Provide PostgreSQL, Redis, object storage, Reverb, and (when selected) Meilisearch through secret-managed environment variables. Run `php artisan migrate --force` during a controlled release, then start Octane and Horizon as separate supervised processes and `php artisan reverb:start` for realtime traffic.

The web-server document root must be the repository's `public/` directory,
never the repository root. The root `.htaccess` is a development defense for a
shared XAMPP `htdocs` layout, not a substitute for a dedicated VirtualHost.
Development Compose ports bind only to `127.0.0.1`; do not broaden those
bindings without authenticated services and an explicit network policy.

Use `php artisan octane:reload` for a graceful Octane restart after code/config changes. Horizon should be restarted with `php artisan horizon:terminate` so the supervisor starts workers from the new release. Keep dashboards disabled unless an internal network, super-admin gate, and IP allow-list are in place. Configure TLS at the edge, private S3 buckets, log retention, Pulse pruning, Telescope disabled outside local development, and database/Redis backups.

Scale-readiness changes are opt-in through `config/scalability.php`. Keep them
off on the first deployment, enable one at a time in staging, run the read-only
`load-tests/product-detail-read.js` profile, and use the documented rollback
flag before considering a code rollback.
