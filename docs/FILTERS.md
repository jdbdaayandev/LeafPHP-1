# Route filters

LeafPHP supports named filters on individual routes. The built-in `auth` filter redirects unauthenticated visitors to `AUTH_LOGIN_PATH` (default `/login`); `guest` redirects authenticated visitors to `/`; `verified` checks for `email_verified_at` on the current user.

```php
use LeafPHP\Core\Router;

Router::get('/dashboard', [DashboardController::class, 'index'], 'dashboard', ['auth']);
Router::post('/profile', [ProfileController::class, 'update'], 'profile.update', ['auth']);
Router::get('/login', [AuthController::class, 'showLogin'], 'login', ['guest']);
```

Register a custom filter with `LeafPHP\Core\Filter::register()` after autoloading the framework:

```php
use LeafPHP\Core\Filter;

Filter::register('admin', static function ($request) {
    return auth()->user()['role'] === 'admin' ? null : response()->text('Forbidden', 403);
});
```

A filter may return `null`/`true` to continue or return a response to stop dispatch. For custom filters, prefer returning a `RedirectResponse` or `Response` instead of calling `exit`. Make sure a login route exists before applying `auth`.
