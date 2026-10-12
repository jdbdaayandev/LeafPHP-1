# Multiple databases and database sessions

## Connections

The default connection is SQLite. The file is `database/database.sqlite`; LeafPHP creates the parent directory and database file on first connection when the PHP `pdo_sqlite` extension is enabled. No database server is required for a basic project.

```dotenv
DB_CONNECTION=sqlite
DB_SQLITE_DATABASE=database/database.sqlite
DB_PREFIX=
```

Switch to MySQL/MariaDB by setting `DB_CONNECTION=mysql` and configuring `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env`.

```php
$users = DB::table('users')->get();
$reports = DB::table('monthly_reports', 'mysql_reporting')->get();
DB::use('mysql_reporting'); // choose connection for DB::connection() without an explicit name
DB::connection('sqlite')->query('SELECT 1');
```

`DB::table($table, $connectionName)` and model `$connection` allow per-query/per-model connection selection. Configured PDO drivers include SQLite, MySQL, and PostgreSQL (the corresponding PHP PDO extension must be installed). Query Builder and Schema support are strongest for SQLite and MySQL/MariaDB; PostgreSQL portability is incomplete.

### Table prefix

Set `DB_PREFIX=app_` to have `DB::table('users')` and `Schema::create('users', ...)` use `app_users`. Keep the prefix empty if you do not need it. The prefix applies to Query Builder and Schema-managed table names, not arbitrary table names inside raw SQL; update raw SQL migrations manually when using a prefix.

## Database-backed sessions

1. Run `php leaf make:session-migration`.
2. Run `php leaf migrate` with the intended default connection configured.
3. Set `SESSION_DRIVER=database` in `.env`.
4. Restart/reload the application.

The generated migration supports SQLite and MySQL and respects `DB_PREFIX`. The table stores a session payload and last-activity timestamp. Keep storage and the database private; session payloads are not shown in the query inspector.
