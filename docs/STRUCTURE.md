# Project Structure

- `app/Assets`: files served by `asset()` from `/assets/...`
- `app/Controllers`: request handlers
- `app/Models`: application models
- `app/Views`: PHP templates
- `app/config`: app and database configuration
- `bootstrap`: autoload and application bootstrap
- `database/migrations`: ordered migration files
- `database/seeders`: database seeders
- `public`: web document root
- `routes/web.php`: route declarations
- `system`: framework core and database helpers
- `storage`: local runtime storage

Keep secrets in `.env`; do not put `.env` or `storage` under `public`.


## Apache / XAMPP subdirectory deployment

Place the project folder directly under `htdocs` (for example `htdocs/appname`) and visit `http://localhost/appname/`. The root `.htaccess` internally rewrites requests to `public/`; users do not need `/public` in the URL and no root `index.php` is required. Enable Apache `mod_rewrite` and `AllowOverride All`. Keep internal directories blocked from direct HTTP access. `APP_URL` may be blank for auto-detection or set to `http://localhost/appname`.

## Debugging and route filters

Use `dd($value)` to inspect one or more values and stop the current request. The floating debug toolbar is shown only on eligible HTML responses outside production when `APP_DEBUG=true`.

Protected routes can use the built-in `auth` filter as the fourth argument to a route method, for example `Router::get('/dashboard', [DashboardController::class, 'index'], 'dashboard', ['auth'])`. See [Route Filters](FILTERS.md).
