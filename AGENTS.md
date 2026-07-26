# Repository instructions for Codex

After changing application code, dependencies, routes, GraphQL schema, modules, migrations, configuration, operational commands, or developer workflow:

1. Update `docs/developer-guide.md` when behavior or architecture changed.
2. Add a concise `docs/development-log.md` entry for meaningful changes.
3. Run `composer docs:update` or `php scripts/update-developer-guide.php`.
4. Run `composer docs:check` or `php scripts/update-developer-guide.php --check`.
5. Run formatting and relevant PostgreSQL-backed tests.

Do not edit `docs/generated/system-inventory.md` manually. Do not record secrets, real credentials, or untracked `.env` values in documentation.
