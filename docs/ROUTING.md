# Routing, Prefixes, Groups, and Resource Controllers

LeafPHP routing is defined in `routes/web.php` with `LeafPHP\\Core\\Router`.

## HTTP routes and parameters

```php
use LeafPHP\\Core\\Router;

Router::get('/books', [BookController::class, 'index'], 'books.index');
Router::post('/books', [BookController::class, 'store'], 'books.store');
Router::get('/books/{id}', [BookController::class, 'show'], 'books.show');
Router::put('/books/{id}', [BookController::class, 'update'], 'books.update');
Router::patch('/books/{id}', [BookController::class, 'update']);
Router::delete('/books/{id}', [BookController::class, 'destroy']);
```

Controller actions receive URI parameters in order. Supported methods are `get`, `post`, `put`, `patch`, and `delete`.

## URL prefixes

Use `Router::prefix(...)->group(...)` to apply a common path prefix:

```php
Router::prefix('admin')->group(function () {
    Router::get('/dashboard', [DashboardController::class, 'index'], 'admin.dashboard');
    Router::get('/users', [UserController::class, 'index'], 'admin.users.index');
});
```

These routes match `/admin/dashboard` and `/admin/users`. Prefixes can be nested:

```php
Router::prefix('api')->group(function () {
    Router::prefix('v1')->group(function () {
        Router::get('/users', [Api\\UserController::class, 'index'], 'api.v1.users.index');
    });
});
```

## Groups and middleware

```php
Router::middleware(['auth'])->group(function () {
    Router::get('/profile', [ProfileController::class, 'index'], 'profile');

    Router::prefix('admin')->group(function () {
        Router::get('/dashboard', [DashboardController::class, 'index'], 'admin.dashboard');
    });
});
```

Groups can combine prefix and middleware with `Router::group()`:

```php
Router::group(['prefix' => 'api/v1', 'middleware' => ['auth']], function () {
    Router::get('/users', [Api\\UserController::class, 'index'], 'api.users.index');
});
```

Nested groups inherit parent prefixes and middleware. Route-level filters are combined with group middleware. Middleware names must be registered with LeafPHP's filter system.

## Resource controllers

`Router::resource()` registers conventional RESTful routes. The controller must implement the actions you intend to use.

```php
Router::resource('books', BookController::class);
```

| Method | URI | Controller action | Default route name |
|---|---|---|---|
| GET | `/books` | `index` | `books.index` |
| GET | `/books/create` | `create` | `books.create` |
| POST | `/books` | `store` | `books.store` |
| GET | `/books/{book}` | `show` | `books.show` |
| GET | `/books/{book}/edit` | `edit` | `books.edit` |
| PUT or PATCH | `/books/{book}` | `update` | `books.update` |
| DELETE | `/books/{book}` | `destroy` | `books.destroy` |

Options can restrict or customize routes:

```php
Router::resource('books', BookController::class, [
    'only' => ['index', 'show', 'store'],
    // Alternatively use 'except' => ['create', 'edit'].
    'parameter' => 'book',
    'middleware' => ['auth'],
    'names' => ['index' => 'library.books'],
]);
```

`only` and `except` accept action names: `index`, `create`, `store`, `show`, `edit`, `update`, and `destroy`. `parameter` customizes the URI parameter name. `middleware` applies filters to all generated resource routes. `names` overrides individual route names.

## Named URLs

```php
$url = route('books.show', ['book' => 42]);
// URL contains /books/42
```

Required route parameters are URL-encoded. Extra parameters are appended as a query string. Route prefixes are included in generated URLs because the registered route URI contains the prefix.

## Inspect registered routes

```php
$routes = Router::routes(); // method, URI, name, filters, action
```


## Generate resource controllers with the CLI

```bash
php leaf make:model User -mcr
```

This generates `app/Models/User.php`, a `create_user_table` migration, and `app/Controllers/UserController.php` with the seven resource actions. It does not automatically modify `routes/web.php`; add this explicitly:

```php
use LeafPHP\Core\Router;
use App\Controllers\UserController;

Router::resource('users', UserController::class);
```

The generated controller methods are starter placeholders; implement validation, persistence, and responses for your application.
