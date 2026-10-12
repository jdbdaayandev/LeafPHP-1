# Authentication, Hashing, Relationships, and Named Routes

## Password hashing

```php
use LeafPHP\Security\Hash;
$hash = Hash::make($request->input('password'));
if (Hash::check($request->input('password'), $user['password'])) { /* valid */ }
```

Helpers: `hash_password($plain)` and `password_matches($plain, $hash)`. Store only the hash. Never log raw passwords.

## Session authentication

```php
if (auth()->attempt(['email' => request()->input('email'), 'password' => request()->input('password')])) {
    return redirect('/dashboard');
}
return back();

$user = auth()->user(); // default users table, by id
$userId = auth()->id();
if (auth()->check()) { /* signed in */ }
auth()->logout();
```

Default convention: `users` has `id`, `email`, and `password`. Use `auth()->attempt($credentials, 'members')` for a different table. Password hashes must be created with `Hash::make()` or `password_hash()`.

## Model relationships

Models extend `App\Models\Model`, set `$table`, and may set `$connection = 'mysql_reporting'`. Query results are associative arrays.

```php
class User extends \App\Models\Model { protected string $table = 'users'; }
class Post extends \App\Models\Model { protected string $table = 'posts'; }
$user = User::find(1);
$posts = $user?->hasMany(Post::class, 'user_id')->get();
$profile = $user?->hasOne(Profile::class, 'user_id');
$post = Post::find(10);
$author = $post?->belongsTo(User::class, 'user_id');
$tags = $post?->belongsToMany(Tag::class, 'post_tag', 'post_id', 'tag_id');
```

Relationships return query builders for `hasMany`/`hasOne` and `belongsToMany` returns rows; `belongsTo`/`hasOne` return the first row or null. Relationship eager loading and automatic persistence are not included.

## Named routes

```php
Router::get('/users/{id}', [UserController::class, 'show'], 'users.show');
$url = route('users.show', ['id' => 42]);
```

Route parameters are URL-encoded and any extra parameters become a query string.
