# LeafPHP 1.4.1

A lightweight PHP framework with a small core, readable conventions, and practical developer tools.

## Run with XAMPP/WAMP

Copy the project folder to `C:\xampp\htdocs\appname` (or the equivalent WAMP `www` folder), enable Apache `mod_rewrite` and `.htaccess` overrides, then open `http://localhost/appname/`. You do not need to run `php leaf serve` or put `/public` in the URL.

Copy `.env.example` to `.env`. The default database is SQLite at `database/database.sqlite`, so no database server is required. Enable the PHP `pdo_sqlite` extension. Set `DB_CONNECTION=mysql` if you prefer MySQL/MariaDB.

## Highlights

- `{{ $value }}` templates with HTML escaping
- Query builder, schema helpers, clean timestamped migrations and seeders
- Multiple PDO connections and configurable `DB_PREFIX`
- Authentication, password hashing, named routes, and route filters
- Validation with redirect-back errors and old input
- File or database sessions
- Development-only floating debug toolbar with session inspection and redaction
- `dd($value)` dump-and-die helper

## Useful commands

```sh
php leaf list
php leaf make:migration create_books_table
php leaf migrate
php leaf make:seeder ExampleSeeder
php leaf db:seed
php leaf migrate:fresh --seed
```

`migrate:fresh` is destructive and asks for confirmation. Back up important data first. See `docs/` for environment variables, query builder, route filters (`docs/FILTERS.md`), sessions, routes, and debugging.
