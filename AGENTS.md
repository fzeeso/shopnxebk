# Repository instructions for Codex

After changing application code, dependencies, routes, GraphQL schema, modules, migrations, configuration, operational commands, or developer workflow:

1. Update `docs/developer-guide.md` when behavior or architecture changed.
2. Update `docs/api-manual.md` when an API contract, request cycle, client
   example, authorization rule, translation flow, or integration workflow
   changed.
3. Update `docs/context.md` and the owning module document when domain or module
   behavior changed.
4. Add a concise `docs/development-log.md` entry for meaningful changes.
5. Run `composer docs:update` or `php scripts/update-developer-guide.php`.
6. Run `composer docs:check` or `php scripts/update-developer-guide.php --check`.
7. Run formatting and relevant PostgreSQL-backed tests.

Do not edit files under `docs/generated/` manually. Do not record secrets, real
credentials, or untracked `.env` values in documentation.

## Database and Store-data safety

Treat every database and all Store data as read-only during Codex work.

- Never execute SQL, Artisan, migration, rollback, seed, restore, import, API,
  GraphQL, browser, or other commands that create, update, delete, truncate,
  drop, alter, or otherwise manipulate database or Store data.
- Do not run database-backed tests when they can insert, update, delete, reset,
  refresh, migrate, or otherwise mutate a database, including a test database.
- Generic requests to implement, fix, test, verify, or deploy do not authorize
  data mutation. If completion would require a mutation, stop and report it.
- Read-only inspection, source/configuration edits, documentation generation,
  formatting, static analysis, unit tests with no database writes, and builds
  remain allowed.
- Code or migration files may be prepared when requested, but Codex must not
  execute them against any database or Store dataset.
