# Deployment

Build the pinned FrankenPHP image with `docker compose build` or the included `Dockerfile`. Provide PostgreSQL, Redis, object storage, Reverb, and (when selected) Meilisearch through secret-managed environment variables. Run `php artisan migrate --force` during a controlled release, then start Octane and Horizon as separate supervised processes and `php artisan reverb:start` for realtime traffic.

Use `php artisan octane:reload` for a graceful Octane restart after code/config changes. Horizon should be restarted with `php artisan horizon:terminate` so the supervisor starts workers from the new release. Keep dashboards disabled unless an internal network, super-admin gate, and IP allow-list are in place. Configure TLS at the edge, private S3 buckets, log retention, Pulse pruning, Telescope disabled outside local development, and database/Redis backups.
