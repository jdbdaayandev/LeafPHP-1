# HTTP, JSON and Sessions

## JSON response
```php
return response()->json([
    'success' => true,
    'data' => $books,
    'message' => 'Books loaded',
], 200);
```

## Request input
```php
$title = request()->input('title');
$page = request()->query('page', 1);
$payload = request()->json();
```

## Session and flash
```php
session()->put('user_id', 12);
$userId = session()->get('user_id');
session()->flash('status', 'Saved successfully');
```
Flash data is available on the next request after a redirect.

## Validation and redirects
```php
$check = validator(request()->all(), [
    'name' => 'required|string|max:100',
    'email' => 'required|email',
]);

if ($check->fails()) {
    redirect('/form')->withErrors($check->errors())->withInput()->send();
}
```

## CSRF
Use `@csrf` in template forms or `<?= csrf_field() ?>` in PHP views. For JSON clients, send the token in the `X-CSRF-TOKEN` header. The current CSRF middleware rejects unsafe requests without a valid token.
