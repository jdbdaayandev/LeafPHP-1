# Changelog

## 1.4.0

- Added named database connections and per-query/per-model connection selection.
- Added password hashing helpers and session-backed authentication helpers.
- Added explicit model relationship helpers (`hasMany`, `hasOne`, `belongsTo`, `belongsToMany`).
- Added named routes and `route()` URL generation.
- Added development-only floating debug toolbar for performance, SQL queries, routes, request metadata, logs, environment, and session inspection.
- Redacted sensitive session keys and database-session query bindings in the toolbar.
- Added database-backed session handler and `php leaf make:session-migration` generator.
- Expanded feature documentation.

## Notes

- Database-backed sessions require generating/running the session migration and setting `SESSION_DRIVER=database`.
- The migration/schema helper and upsert syntax remain MySQL/MariaDB-oriented. Other PDO drivers can be configured for direct connections, but not every query-builder or schema feature is driver-neutral.
- Live database integration was not exercised as part of the archive smoke tests.


## 1.4.1

- Added `dd()` dump-and-die helper for browser and CLI output.
- Refined the minimal monochrome welcome page and removed the “local-first” tagline.
- Switched the default database connection to SQLite and documented `.env` settings.
- Added configurable `DB_PREFIX` for Query Builder and Schema-created tables.
- Added route filters including `auth`, `guest`, and `verified`, plus custom filter registration.
- Added `migrate:fresh --seed` to rebuild the schema and seed after successful migration.
- Added SQLite-compatible migration metadata and database-session migration generation.
