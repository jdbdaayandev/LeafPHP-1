# Environments and `.env`

Copy `.env.example` to `.env` and adjust only the values your application needs. Do not commit `.env` because it may contain credentials.

| Variable | Default | Purpose |
|---|---|---|
| `APP_NAME` | `LeafPHP` | Name shown in the welcome page and app metadata. |
| `APP_ENV` | `local` | Selects `local`, `development`, `testing`, or `production` behavior and `.env.{environment}` overrides. |
| `APP_DEBUG` | `true` outside production | Enables detailed development errors and the floating debug toolbar. Always set `false` in production. |
| `APP_URL` | blank | Optional canonical base URL; leave blank to detect the current host and subdirectory. |
| `APP_KEY` | blank | Application secret; generate with `php leaf key:generate` before using features that require it. |
| `APP_TIMEZONE` | `Asia/Manila` | PHP timezone used by the application. |
| `DB_CONNECTION` | `sqlite` | Name of the default connection. SQLite requires no database server. |
| `DB_SQLITE_DATABASE` | `database/database.sqlite` | SQLite file path, relative to the project root unless absolute. |
| `DB_PREFIX` | blank | Optional prefix prepended to table names passed through `DB::table()` and `Schema`. Use letters, digits, underscores; do not start with a digit. |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_CHARSET` | MySQL sample values | Credentials/settings for the optional `mysql` connection. |
| `DB_REPORTING_*` | reporting sample values | Optional `mysql_reporting` connection settings. |
| `SESSION_DRIVER` | `file` | Use `file` for PHP file sessions or `database` for the migrated `sessions` table. |
| `SESSION_LIFETIME` | `120` | Session lifetime in minutes for database-backed sessions. |
| `AUTH_LOGIN_PATH` | `/login` | Redirect destination used by the built-in `auth` route filter when unauthenticated. |

For local use, SQLite is selected by default. Ensure PHP has the `pdo_sqlite` extension enabled. The first connection creates the SQLite file if its parent directory is writable.

Production example:

```dotenv
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=sqlite
DB_SQLITE_DATABASE=database/database.sqlite
```

Never expose detailed stack traces, session contents, or query bindings to production visitors. The debug toolbar is disabled in production regardless of `APP_DEBUG`.
